<?php
/**
 * Password Reset Page
 * 
 * Finalizes the password reset process. Validates the reset token from the URL,
 * updates the user's password with a new hash, and clears the token.
 */

include('config/config.php');
// Get the token from the URL query parameter
$token = $_GET['token'] ?? '';
$tokenHash = $token !== '' ? hash('sha256', $token) : '';
$csrfToken = itm_get_csrf_token();

/**
 * Why: Password reset completion is public, so we record attempts to enforce
 * per-IP/per-account throttles and reduce brute-force pressure.
 */
function itm_record_password_reset_completion_attempt(mysqli $conn, string $ipAddress, ?int $userId = null): void
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO password_reset_attempts (attempt_type, ip_address, user_id) VALUES (\'reset\', ?, ?)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'si', $ipAddress, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Why: Checking limits before password updates reduces token-guess attempts
 * and protects real user accounts from repeated automated resets.
 */
function itm_is_password_reset_completion_rate_limited(mysqli $conn, string $ipAddress, ?int $userId = null): bool
{
    $maxIpAttempts = 20;
    $maxUserAttempts = 6;

    $stmtIp = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) FROM password_reset_attempts WHERE attempt_type = \'reset\' AND ip_address = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)'
    );
    if ($stmtIp) {
        mysqli_stmt_bind_param($stmtIp, 's', $ipAddress);
        mysqli_stmt_execute($stmtIp);
        mysqli_stmt_bind_result($stmtIp, $ipAttempts);
        mysqli_stmt_fetch($stmtIp);
        mysqli_stmt_close($stmtIp);
        if ((int)$ipAttempts >= $maxIpAttempts) {
            return true;
        }
    }

    if ($userId !== null) {
        $stmtUser = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) FROM password_reset_attempts WHERE attempt_type = \'reset\' AND user_id = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)'
        );
        if ($stmtUser) {
            mysqli_stmt_bind_param($stmtUser, 'i', $userId);
            mysqli_stmt_execute($stmtUser);
            mysqli_stmt_bind_result($stmtUser, $userAttempts);
            mysqli_stmt_fetch($stmtUser);
            mysqli_stmt_close($stmtUser);
            if ((int)$userAttempts >= $maxUserAttempts) {
                return true;
            }
        }
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    itm_require_post_csrf();

    // Why: Forwarded headers can carry the real client address when Apache/PHP
    // runs behind a local reverse proxy and REMOTE_ADDR is only loopback (::1).
    $requestIp = substr((string)(itm_get_client_ip_address() ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')), 0, 45);
    $matchedUserId = null;

    // Look up a valid, unexpired token hash before attempting an update.
    $findUserStmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE reset_token_hash = ? AND reset_token_expires_at >= NOW() LIMIT 1');
    if ($findUserStmt) {
        mysqli_stmt_bind_param($findUserStmt, 's', $tokenHash);
        mysqli_stmt_execute($findUserStmt);
        mysqli_stmt_bind_result($findUserStmt, $matchedUserId);
        mysqli_stmt_fetch($findUserStmt);
        mysqli_stmt_close($findUserStmt);
    }

    $isRateLimited = itm_is_password_reset_completion_rate_limited($conn, $requestIp, $matchedUserId);
    itm_record_password_reset_completion_attempt($conn, $requestIp, $matchedUserId);

    if (!$isRateLimited && $matchedUserId !== null) {
        // Hash the new password before storing it.
        $new_password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

        // Update password only for valid unexpired token hash and clear reset state after one use.
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE users
             SET password = ?, reset_token = NULL, reset_token_hash = NULL, reset_token_expires_at = NULL
             WHERE id = ? AND reset_token_hash = ? AND reset_token_expires_at >= NOW()'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sis', $new_password, $matchedUserId, $tokenHash);
            if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                $success = true;
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - IT Management</title>
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
            <p>Create a new password</p>
        </div>

        <?php if (!empty($success)): ?><p style="color:#2f855a; margin-bottom:14px;">Password updated! <a href="login.php">Login</a></p><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <label for="password">New Password</label>
            <input id="password" type="password" name="password" placeholder="New Password" required>
            <button type="submit">Update</button>
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
