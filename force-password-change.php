<?php
/**
 * Mandatory password change after login when employees.must_change_password = 1.
 */

include('config/config.php');

$error = '';
$csrfToken = itm_get_csrf_token();
$employeeId = (int)($_SESSION['employee_id'] ?? 0);

if ($employeeId <= 0) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

if (!itm_employee_must_change_password($conn, $employeeId)) {
    $isAdmin = itm_is_admin($conn, $employeeId);
    itm_force_password_change_redirect_after_auth($conn, $employeeId, $isAdmin);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_try_post_csrf()) {
        $error = 'Invalid CSRF token.';
        $csrfToken = itm_get_csrf_token();
    } else {
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $validation = itm_force_password_change_validate_new_password(
            $conn,
            $employeeId,
            $newPassword,
            $confirmPassword
        );

        if (!$validation['ok']) {
            $error = $validation['error'];
        } elseif (itm_force_password_change_apply_new_password($conn, $employeeId, $newPassword)) {
            if (function_exists('itm_log_audit')) {
                itm_log_audit(
                    $conn,
                    'employees',
                    $employeeId,
                    'UPDATE',
                    ['action' => 'force_password_change_required'],
                    ['action' => 'force_password_change_completed']
                );
            }

            $isAdmin = itm_is_admin($conn, $employeeId);
            itm_force_password_change_redirect_after_auth($conn, $employeeId, $isAdmin);
        } else {
            $error = 'Unable to update password. Please try again.';
        }
    }
}

$minLength = itm_force_password_change_min_length();
$username = (string)($_SESSION['username'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change password - <?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></title>
    <?php if (!empty($favicon_url)): ?>
        <link rel="icon" type="image/x-icon" href="<?php echo sanitize($favicon_url); ?>">
    <?php endif; ?>
    <style>
        :root { --accent: #0969da; --bg: #ffffff; --text: #24292f; --muted: #666; }
        [data-theme="dark"] { --accent: #58a6ff; --bg: #0d1117; --text: #c9d1d9; --muted: #8b949e; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex; justify-content: center; align-items: center; min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px;
        }
        .container { background: var(--bg); padding: 40px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: var(--accent); font-size: 28px; }
        .logo p { color: var(--muted); font-size: 14px; line-height: 1.5; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text); }
        input { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; background: var(--bg); color: var(--text); margin-bottom: 16px; }
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .links { margin-top: 14px; text-align: center; }
        .links a { color: var(--accent); text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1><?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></h1>
            <p>Signed in as <strong><?php echo sanitize($username); ?></strong>. Set a new password before continuing (minimum <?php echo (int)$minLength; ?> characters).</p>
        </div>

        <?php if ($error !== ''): ?><p style="color:#d93025; margin-bottom:14px;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <label for="new_password">New password</label>
            <input id="new_password" type="password" name="new_password" autocomplete="new-password" required minlength="<?php echo (int)$minLength; ?>">
            <label for="confirm_password">Confirm new password</label>
            <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" required minlength="<?php echo (int)$minLength; ?>">
            <button type="submit" title="Save new password">Save new password</button>
        </form>
        <div class="links">
            <a href="<?php echo sanitize(BASE_URL); ?>logout.php" title="Sign out">Sign out</a>
        </div>
    </div>
</body>
</html>
