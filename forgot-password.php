<?php
/**
 * Forgot Password Page
 * 
 * Initiates the password reset process by generating a unique token
 * and sending it to the user's email via the MailerLite API.
 */

include('config/config.php');
$csrfToken = itm_get_csrf_token();

/**
 * Why: Reset endpoints are public and are attractive for scripted abuse.
 * Keeping a compact audit trail enables cheap per-IP and per-account throttling.
 */
function itm_record_password_reset_attempt(mysqli $conn, string $attemptType, string $ipAddress, ?string $email = null, ?int $userId = null): void
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO password_reset_attempts (attempt_type, ip_address, email, user_id) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sssi', $attemptType, $ipAddress, $email, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Why: Password reset tracking should link attempts to a concrete user record
 * whenever an email matches, so security analytics can pivot by user_id.
 */
function itm_find_password_reset_user(mysqli $conn, string $email): array
{
    $user = [
        'id' => null,
        'username' => null,
        'company_id' => null,
    ];

    if ($email === '') {
        return $user;
    }

    $stmt = mysqli_prepare($conn, 'SELECT id, username, company_id FROM users WHERE email = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $foundUserId, $foundUsername, $foundCompanyId);
        if (mysqli_stmt_fetch($stmt)) {
            $user['id'] = (int)$foundUserId;
            $user['username'] = (string)$foundUsername;
            $user['company_id'] = (int)$foundCompanyId;
        }
        mysqli_stmt_close($stmt);
    }

    return $user;
}

/**
 * Why: Public password reset should fail safely under brute-force load while
 * still returning generic UX text to prevent account enumeration.
 */
function itm_is_password_reset_rate_limited(mysqli $conn, string $attemptType, string $ipAddress, ?string $email = null): bool
{
    $maxIpAttempts = 10;
    $maxEmailAttempts = 5;

    $stmtIp = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) FROM password_reset_attempts WHERE attempt_type = ? AND ip_address = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)'
    );
    if ($stmtIp) {
        mysqli_stmt_bind_param($stmtIp, 'ss', $attemptType, $ipAddress);
        mysqli_stmt_execute($stmtIp);
        mysqli_stmt_bind_result($stmtIp, $ipAttempts);
        mysqli_stmt_fetch($stmtIp);
        mysqli_stmt_close($stmtIp);
        if ((int)$ipAttempts >= $maxIpAttempts) {
            return true;
        }
    }

    if ($email !== null && $email !== '') {
        $stmtEmail = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) FROM password_reset_attempts WHERE attempt_type = ? AND email = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)'
        );
        if ($stmtEmail) {
            mysqli_stmt_bind_param($stmtEmail, 'ss', $attemptType, $email);
            mysqli_stmt_execute($stmtEmail);
            mysqli_stmt_bind_result($stmtEmail, $emailAttempts);
            mysqli_stmt_fetch($stmtEmail);
            mysqli_stmt_close($stmtEmail);
            if ((int)$emailAttempts >= $maxEmailAttempts) {
                return true;
            }
        }
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    
    $email = trim($_POST['email'] ?? '');
    // Why: Forwarded headers can carry the real client address when Apache/PHP
    // runs behind a local reverse proxy and REMOTE_ADDR is only loopback (::1).
    $requestIp = substr((string)(itm_get_client_ip_address() ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')), 0, 45);
    $isRateLimited = itm_is_password_reset_rate_limited($conn, 'request', $requestIp, $email);
    $requestUser = itm_find_password_reset_user($conn, $email);

    itm_record_password_reset_attempt(
        $conn,
        'request',
        $requestIp,
        $email === '' ? null : $email,
        $requestUser['id']
    );

    if (!$isRateLimited) {
        // Resolve and set audit company scope before mutating users.
        // Why: Audit triggers rely on @app_company_id. On public pages there is no session company,
        // so we derive it from the target account to avoid FK violations in audit_logs.
        $auditCompanyId = $requestUser['company_id'];
        mysqli_query($conn, 'SET @app_company_id = ' . ($auditCompanyId === null ? 'NULL' : (string)$auditCompanyId));

        // Generate a secure random reset token and keep a hash for validation.
        // Why: Keeping the hash enables constant-size lookup while the raw token
        // can still support legacy admin diagnostics that expect reset_token.
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $tokenExpiresAt = date('Y-m-d H:i:s', time() + (60 * 60));

        // Store both token forms and expiry if the email exists.
        $stmt = mysqli_prepare($conn, 'UPDATE users SET reset_token = ?, reset_token_hash = ?, reset_token_expires_at = ? WHERE email = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssss', $token, $tokenHash, $tokenExpiresAt, $email);
            mysqli_stmt_execute($stmt);

            // Only attempt to send email if an account was actually found and updated.
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $link = BASE_URL . 'reset-password.php?token=' . urlencode($token);
                $payload = json_encode([
                    'from' => 'verified@yourdomain.com', // Replace with your verified sender
                    'to' => $email,
                    'subject' => 'Reset Your Password',
                    'html' => "Click here to reset your IT Management System password: <a href='$link'>$link</a>",
                ]);

                // Send the reset link using the configured MailerLite API.
                $ch = curl_init(MAILERLITE_URL);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . MAILERLITE_API_KEY,
                ]);
                curl_exec($ch);
                curl_close($ch);
            }

            mysqli_stmt_close($stmt);
        }
    }

    // Always show the same message to prevent email enumeration.
    $message = 'If your email exists, a reset link has been sent.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - IT Management</title>
    <style>
        :root { --accent: #0969da; --bg: #ffffff; --text: #24292f; --muted: #666; }
        [data-theme="dark"] { --accent: #58a6ff; --bg: #0d1117; --text: #c9d1d9; --muted: #8b949e; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; }
        .container { background: var(--bg); padding: 40px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: var(--accent); font-size: 28px; }
        .logo p { color: var(--muted); font-size: 14px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text); }
        input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; background: var(--bg); color: var(--text); margin-bottom: 16px; }
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .links { margin-top: 14px; text-align: center; }
        .links a { color: var(--accent); text-decoration: none; }
        .theme-btn { position: absolute; top: 20px; right: 20px; background: var(--bg); border: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; font-size: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <button class="theme-btn" onclick="toggleTheme()">🌙</button>
    <div class="container">
        <div class="logo">
            <h1>⚙️ IT Management</h1>
            <p>Reset your password</p>
        </div>
        <?php if (isset($message)): ?><p style="color:#2f855a; margin-bottom:14px;"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" placeholder="Email" required>
            <button type="submit">Send Email</button>
        </form>
        <div class="links"><a href="login.php">Back to Login</a></div>
    </div>
    <script>
        /**
         * Toggle between light and dark themes
         */
        function toggleTheme() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'light' : 'dark');
            localStorage.setItem('theme', document.documentElement.getAttribute('data-theme'));
        }
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
    </script>
</body>
</html>
