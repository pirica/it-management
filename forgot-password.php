<?php
/**
 * Forgot Password Page
 * 
 * Initiates the password reset process by generating a unique token
 * and sending it to the user's email via the MailerLite API.
 */

include('config/config.php');
$csrfToken = itm_get_csrf_token();
$error = '';
$message = '';

/**
 * Why: Reset endpoints are public and are attractive for scripted abuse.
 * Keeping a compact audit trail enables cheap per-IP and per-account throttling.
 */
function itm_record_password_reset_attempt(mysqli $conn, string $attemptType, string $ipAddress, ?string $email = null, ?int $employeeId = null): void
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO attempts (attempt_source, attempt_type, ip_address, email, employee_id, active)
         VALUES ('password_reset', ?, ?, ?, ?, IF(
            EXISTS(
                SELECT 1 FROM employees
                WHERE LOWER(TRIM(COALESCE(work_email, personal_email, ''))) = LOWER(TRIM(COALESCE(?, '')))
                LIMIT 1
            ),
            1,
            0
         ))"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sssis', $attemptType, $ipAddress, $email, $employeeId, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Why: Password reset tracking should link attempts to a concrete user record
 * whenever an email matches, so security analytics can pivot by user_id.
 */
function itm_find_password_reset_user(mysqli $conn, string $identifier): array
{
    $user = itm_password_reset_find_user_by_identifier($conn, $identifier);
    return [
        'id' => $user['id'],
        'username' => $user['username'],
        'company_id' => $user['company_id'],
    ];
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
        "SELECT COUNT(*) FROM attempts WHERE attempt_source = 'password_reset' AND attempt_type = ? AND ip_address = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
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
            "SELECT COUNT(*) FROM attempts WHERE attempt_source = 'password_reset' AND attempt_type = ? AND email = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
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

    $identifier = trim((string)($_POST['email'] ?? ''));
    $requestIp = substr(itm_get_login_request_ip(), 0, 45);
    $storedAttemptIdentifier = itm_normalize_login_attempt_identifier($identifier);
    $isRateLimited = itm_is_password_reset_rate_limited($conn, 'request', $requestIp, $storedAttemptIdentifier);
    $requestUser = itm_password_reset_find_user_by_identifier($conn, $identifier);

    itm_record_password_reset_attempt(
        $conn,
        'request',
        $requestIp,
        $storedAttemptIdentifier,
        $requestUser['id']
    );

    if ($isRateLimited) {
        $error = 'Too many reset requests. Please wait a few minutes and try again.';
    } elseif ((int)($requestUser['id'] ?? 0) > 0 && $requestUser['deliverable_email'] === '') {
        $error = 'This account has no email on file. Contact your administrator to add one.';
    } elseif (!$isRateLimited && (int)($requestUser['id'] ?? 0) > 0) {
        $auditCompanyId = (int)($requestUser['company_id'] ?? 0);
        mysqli_query($conn, 'SET @app_company_id = ' . ($auditCompanyId > 0 ? (string)$auditCompanyId : 'NULL'));

        $token = bin2hex(random_bytes(32));
        if (itm_password_reset_store_token_for_employee($conn, (int)$requestUser['id'], $token)) {
            $deliverTo = (string)$requestUser['deliverable_email'];
            $link = BASE_URL . 'reset-password.php?token=' . urlencode($token);
            $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
            $html = '<p>We received a request to reset your password. Use the button below to choose a new one.</p>'
                . '<p>If the button does not work, copy and paste this link into your browser:</p>'
                . '<p style="word-break:break-all;"><a href="' . $safeLink . '" style="color:#0969da;">' . $safeLink . '</a></p>';
            itm_send_email($deliverTo, 'Reset Your Password', $html, $auditCompanyId > 0 ? $auditCompanyId : null, [
                'email_template' => [
                    'subtitle' => 'Reset your password',
                    'button_text' => 'Reset password',
                    'button_url' => $link,
                    'footer_text' => 'If you did not request this, you can ignore this email.',
                ],
            ]);
            $message = 'If your email exists, a reset link has been sent.';
        }
    } else {
        $message = 'If your email exists, a reset link has been sent.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></title>
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
        @media (max-width: 480px) {
            body { padding: 12px; }
            .container { padding: 24px 20px; }
            .logo h1 { font-size: 24px; }
            .theme-btn { top: 12px; right: 12px; width: 44px; height: 44px; font-size: 20px; }
        }
    </style>
</head>
<body>
    <button class="theme-btn" onclick="toggleTheme()">🌙</button>
    <div class="container">
        <div class="logo">
            <h1><?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></h1>
            <p>Reset your password</p>
        </div>
        <?php if ($error !== ''): ?><p style="color:#d93025; margin-bottom:14px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <?php if ($message !== ''): ?><p style="color:#2f855a; margin-bottom:14px;"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <label for="email">Email or Username</label>
            <input id="email" type="text" name="email" placeholder="Email or username" required autocomplete="username">
            <button type="submit" title="Send reset email">Send Email</button>
        </form>
        <div class="links"><a href="<?php echo sanitize(BASE_URL); ?>login.php">Back to Login</a></div>
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
