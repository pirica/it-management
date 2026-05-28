<?php
/**
 * User Registration Page (Invitation-Based)
 *
 * New users can only register with a valid invitation code tied to their email.
 * Company membership and optional role/access assignments come from the invitation.
 */

include('config/config.php');
$csrfToken = itm_get_csrf_token();

$prefilledEmail = trim((string)($_GET['email'] ?? ''));
$prefilledInviteCode = trim((string)($_GET['invite'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $rawPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $inviteCode = trim($_POST['invite_code'] ?? '');

    if ($inviteCode === '') {
        $error = 'Invitation code is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif ($rawPassword === '' || $confirmPassword === '') {
        $error = 'Password and confirmation are required.';
    } elseif ($rawPassword !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } else {
        mysqli_begin_transaction($conn);

        $inviteSql = 'SELECT i.id, i.company_id, i.invited_by_user_id, i.role_id, i.access_level_id
                      FROM registration_invitations i
                      INNER JOIN companies c ON c.id = i.company_id
                      WHERE i.invitation_code = ?
                        AND i.email = ?
                        AND i.active = 1
                        AND i.accepted_at IS NULL
                        AND (i.expires_at IS NULL OR i.expires_at >= NOW())
                        AND c.active = 1
                      LIMIT 1
                      FOR UPDATE';
        $inviteStmt = mysqli_prepare($conn, $inviteSql);
        $invitation = null;

        if ($inviteStmt) {
            mysqli_stmt_bind_param($inviteStmt, 'ss', $inviteCode, $email);
            mysqli_stmt_execute($inviteStmt);
            $inviteResult = mysqli_stmt_get_result($inviteStmt);
            if ($inviteResult) {
                $invitation = mysqli_fetch_assoc($inviteResult) ?: null;
            }
            mysqli_stmt_close($inviteStmt);
        }

        if (!$invitation) {
            mysqli_rollback($conn);
            $error = 'Invalid, expired, or already-used invitation for this email.';
        } else {
            $companyId = (int)$invitation['company_id'];
            $invitationId = (int)$invitation['id'];
            $grantedByUserId = isset($invitation['invited_by_user_id']) ? (int)$invitation['invited_by_user_id'] : null;

            $roleId = isset($invitation['role_id']) ? (int)$invitation['role_id'] : 0;
            if ($roleId <= 0) {
                $roleStmt = mysqli_prepare($conn, 'SELECT id FROM user_roles WHERE company_id = ? AND name = "User" LIMIT 1');
                if ($roleStmt) {
                    mysqli_stmt_bind_param($roleStmt, 'i', $companyId);
                    mysqli_stmt_execute($roleStmt);
                    $roleResult = mysqli_stmt_get_result($roleStmt);
                    $roleRow = $roleResult ? mysqli_fetch_assoc($roleResult) : null;
                    $roleId = $roleRow ? (int)$roleRow['id'] : 0;
                    mysqli_stmt_close($roleStmt);
                }
            }

            $accessLevelId = isset($invitation['access_level_id']) ? (int)$invitation['access_level_id'] : 0;
            if ($accessLevelId <= 0) {
                $accessStmt = mysqli_prepare($conn, 'SELECT id FROM access_levels WHERE company_id = ? AND name = "Limited" LIMIT 1');
                if ($accessStmt) {
                    mysqli_stmt_bind_param($accessStmt, 'i', $companyId);
                    mysqli_stmt_execute($accessStmt);
                    $accessResult = mysqli_stmt_get_result($accessStmt);
                    $accessRow = $accessResult ? mysqli_fetch_assoc($accessResult) : null;
                    $accessLevelId = $accessRow ? (int)$accessRow['id'] : 0;
                    mysqli_stmt_close($accessStmt);
                }
            }

            if ($roleId <= 0 || $accessLevelId <= 0) {
                mysqli_rollback($conn);
                $error = 'Registration cannot continue because default role/access settings are missing for this company.';
            } else {
                $password = password_hash($rawPassword, PASSWORD_DEFAULT);

                $userStmt = mysqli_prepare($conn, 'INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (?, ?, ?, ?, ?, ?, 1)');
                if ($userStmt) {
                    mysqli_stmt_bind_param($userStmt, 'isssii', $companyId, $username, $email, $password, $roleId, $accessLevelId);
                    $userInserted = mysqli_stmt_execute($userStmt);
                    $newUserId = $userInserted ? (int)mysqli_insert_id($conn) : 0;
                    mysqli_stmt_close($userStmt);

                    if ($userInserted && $newUserId > 0) {
                        $companyLinkStmt = mysqli_prepare($conn, 'INSERT INTO user_companies (user_id, company_id, granted_by_user_id) VALUES (?, ?, ?)');
                        $invitationUpdateStmt = mysqli_prepare($conn, 'UPDATE registration_invitations SET accepted_at = NOW(), active = 0 WHERE id = ?');

                        if ($companyLinkStmt && $invitationUpdateStmt) {
                            mysqli_stmt_bind_param($companyLinkStmt, 'iii', $newUserId, $companyId, $grantedByUserId);
                            mysqli_stmt_bind_param($invitationUpdateStmt, 'i', $invitationId);

                            $companyLinked = mysqli_stmt_execute($companyLinkStmt);
                            $invitationMarkedUsed = mysqli_stmt_execute($invitationUpdateStmt);

                            mysqli_stmt_close($companyLinkStmt);
                            mysqli_stmt_close($invitationUpdateStmt);

                            if ($companyLinked && $invitationMarkedUsed) {
                                mysqli_commit($conn);
                                $success = 'Registration successful! You can login now.';
                                $prefilledEmail = '';
                                $prefilledInviteCode = '';
                            } else {
                                mysqli_rollback($conn);
                                $error = 'Registration failed while finalizing invitation usage. Please try again.';
                            }
                        } else {
                            if ($companyLinkStmt) {
                                mysqli_stmt_close($companyLinkStmt);
                            }
                            if ($invitationUpdateStmt) {
                                mysqli_stmt_close($invitationUpdateStmt);
                            }
                            mysqli_rollback($conn);
                            $error = 'Registration failed while finalizing invitation usage. Please try again.';
                        }
                    } else {
                        mysqli_rollback($conn);
                        $error = 'Registration failed. Email/username may already exist.';
                    }
                } else {
                    mysqli_rollback($conn);
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }

    if (isset($error)) {
        $prefilledEmail = $email;
        $prefilledInviteCode = $inviteCode;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></title>
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
        .help-text { color: var(--muted); font-size: 12px; margin-top: -10px; margin-bottom: 14px; }
    </style>
</head>
<body>
    <button class="theme-btn" onclick="toggleTheme()">🌙</button>
    <div class="container">
        <div class="logo">
            <h1><?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></h1>
            <p>Create your account with an invitation</p>
        </div>

        <?php if (isset($success)): ?><p style="color:#2f855a; margin-bottom:14px;"><?php echo htmlspecialchars($success); ?></p><?php endif; ?>
        <?php if (isset($error)): ?><p style="color:#d93025; margin-bottom:14px;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <label for="invite_code">Invitation Code</label>
            <input id="invite_code" type="text" name="invite_code" placeholder="Paste invitation code" value="<?php echo htmlspecialchars($prefilledInviteCode); ?>" required>

            <label for="email">Email</label>
            <input id="email" type="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($prefilledEmail); ?>" required>
            <p class="help-text">Use the same email address that received the invitation.</p>

            <label for="username">Username</label>
            <input id="username" type="text" name="username" placeholder="Username" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="Password" required>

            <label for="confirm_password">Re-confirm Password</label>
            <input id="confirm_password" type="password" name="confirm_password" placeholder="Re-confirm Password" required>

            <button type="submit">Register</button>
        </form>
        <div class="links"><a href="login.php">Back to Login</a></div>
    </div>
    <script>
        /**
         * Toggle between light and dark themes.
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
