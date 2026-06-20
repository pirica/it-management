<?php
/**
 * Password Reset Page
 *
 * Finalizes the password reset process. Validates the reset token from the URL,
 * updates the user's password with a new hash, and clears the token.
 */

include('config/config.php');

// Why: POST must keep the token when query strings are stripped by proxies or bookmarks.
$token = itm_password_reset_normalize_raw_token($_POST['token'] ?? $_GET['token'] ?? '');
$csrfToken = itm_get_csrf_token();
$error = '';
$formError = '';
$success = false;
$tokenUserId = null;
$tokenUserEmail = null;
$tokenIsValid = false;
$minPasswordLength = 8;

/**
 * Why: Password reset completion is public, so we record attempts to enforce
 * per-IP/per-account throttles and reduce brute-force pressure.
 */
function itm_record_password_reset_completion_attempt(mysqli $conn, string $ipAddress, ?int $employeeId = null, ?string $email = null): void
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO attempts (attempt_source, attempt_type, ip_address, employee_id, email, active)
         VALUES ('password_reset', 'reset', ?, ?, ?, IF(
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
        mysqli_stmt_bind_param($stmt, 'siss', $ipAddress, $employeeId, $email, $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Why: Checking limits before password updates reduces token-guess attempts
 * and protects real user accounts from repeated automated resets.
 */
function itm_is_password_reset_completion_rate_limited(mysqli $conn, string $ipAddress, ?int $employeeId = null): bool
{
    $maxIpAttempts = 20;
    $maxUserAttempts = 6;

    $stmtIp = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) FROM attempts WHERE attempt_source = 'password_reset' AND attempt_type = 'reset' AND ip_address = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
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

    if ($employeeId !== null) {
        $stmtUser = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) FROM attempts WHERE attempt_source = 'password_reset' AND attempt_type = 'reset' AND employee_id = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
        );
        if ($stmtUser) {
            mysqli_stmt_bind_param($stmtUser, 'i', $employeeId);
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

/**
 * Why: Shared lookup for GET display and POST update so invalid links fail with clear copy.
 */
if ($token !== '') {
    $tokenLookup = itm_password_reset_lookup_employee_by_token($conn, $token);
    $tokenUserId = $tokenLookup['id'];
    $tokenUserEmail = $tokenLookup['email'];
    $tokenIsValid = $tokenUserId !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $password = trim((string)($_POST['password'] ?? ''));
    $passwordConfirm = trim((string)($_POST['password_confirm'] ?? ''));

    if ($token === '') {
        $error = 'Reset link is missing. Request a new password reset email.';
    } elseif (!$tokenIsValid || $tokenUserId === null) {
        $error = 'This reset link is invalid or has expired. Request a new password reset email.';
    } else {
        $requestIp = substr(itm_get_login_request_ip(), 0, 45);
        $isRateLimited = itm_is_password_reset_completion_rate_limited($conn, $requestIp, $tokenUserId);
        itm_record_password_reset_completion_attempt($conn, $requestIp, $tokenUserId, $tokenUserEmail);

        if ($isRateLimited) {
            $error = 'Too many reset attempts. Please wait a few minutes and try again.';
        } elseif ($password === '') {
            $formError = 'Enter a new password.';
        } elseif ($passwordConfirm === '') {
            $formError = 'Enter a confirmation password.';
        } elseif (strlen($password) < $minPasswordLength) {
            $formError = 'Password must be at least ' . $minPasswordLength . ' characters.';
        } elseif ($password !== $passwordConfirm) {
            $formError = 'Passwords do not match.';
        } else {
            $newPasswordHash = password_hash($password, PASSWORD_DEFAULT);
            if (itm_password_reset_complete_for_employee($conn, (int)$tokenUserId, $token, $newPasswordHash)) {
                $success = true;
                $tokenIsValid = false;
            } else {
                $error = 'Unable to update your password. The link may have expired or already been used.';
            }
        }
    }
} elseif ($token !== '' && !$tokenIsValid) {
    $error = 'This reset link is invalid or has expired. Request a new password reset email.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></title>
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
        .form-hint { font-size: 13px; color: var(--muted); margin: 8px 0 0; }
        .client-error { color: #d93025; margin-bottom: 14px; font-size: 14px; font-weight: 600; }
        input.input-invalid { border-color: #d93025; }
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
    <button class="theme-btn" onclick="toggleTheme()" title="Toggle theme">🌙</button>
    <div class="container">
        <div class="logo">
            <h1><?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></h1>
            <p>Create a new password</p>
            <?php if (!$success && $tokenIsValid): ?>
                <p class="form-hint">Password must be at least <?php echo (int)$minPasswordLength; ?> characters.</p>
            <?php endif; ?>
        </div>

        <?php if ($success): ?>
            <p style="color:#2f855a; margin-bottom:14px;">Password updated successfully. <a href="login.php">Sign in</a></p>
        <?php elseif ($error !== ''): ?>
            <p class="client-error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <?php if (!$success && $tokenIsValid): ?>
        <form id="reset-password-form" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
            <div id="reset-client-error" class="client-error" role="alert" aria-live="assertive"<?php echo $formError !== '' ? '' : ' style="display:none;"'; ?>><?php echo htmlspecialchars($formError, ENT_QUOTES, 'UTF-8'); ?></div>
            <label for="password">New Password</label>
            <input id="password" type="password" name="password" placeholder="New password (8+ characters)" autocomplete="new-password">
            <label for="password_confirm">Confirm New Password</label>
            <input id="password_confirm" type="password" name="password_confirm" placeholder="Confirm new password" autocomplete="new-password">
            <button type="submit" title="Update password">Update</button>
        </form>
        <?php endif; ?>

        <div class="links">
            <?php if (!$success): ?>
                <a href="<?php echo sanitize(BASE_URL); ?>forgot-password.php">Request a new reset link</a> ·
            <?php endif; ?>
            <a href="<?php echo sanitize(BASE_URL); ?>login.php">Back to Login</a>
        </div>
    </div>
    <script>
        function toggleTheme() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'light' : 'dark');
            localStorage.setItem('theme', document.documentElement.getAttribute('data-theme'));
        }
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');

        (function () {
            const form = document.getElementById('reset-password-form');
            if (!form) {
                return;
            }

            const minLen = <?php echo (int)$minPasswordLength; ?>;
            const clientError = document.getElementById('reset-client-error');
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('password_confirm');

            function clearFieldErrors() {
                passwordInput?.classList.remove('input-invalid');
                confirmInput?.classList.remove('input-invalid');
            }

            function showClientError(message, invalidField) {
                clearFieldErrors();
                if (invalidField) {
                    invalidField.classList.add('input-invalid');
                    invalidField.focus();
                }
                if (!clientError) {
                    return;
                }
                clientError.textContent = message;
                clientError.style.display = 'block';
            }

            function hideClientError() {
                clearFieldErrors();
                if (!clientError) {
                    return;
                }
                clientError.textContent = '';
                clientError.style.display = 'none';
            }

            form.addEventListener('submit', function (event) {
                hideClientError();

                const password = (passwordInput?.value || '').trim();
                const passwordConfirm = (confirmInput?.value || '').trim();

                if (password === '') {
                    event.preventDefault();
                    showClientError('Enter a new password.', passwordInput);
                    return;
                }
                if (passwordConfirm === '') {
                    event.preventDefault();
                    showClientError('Enter a confirmation password.', confirmInput);
                    return;
                }
                if (password.length < minLen) {
                    event.preventDefault();
                    showClientError('Password must be at least ' + minLen + ' characters.', passwordInput);
                    return;
                }
                if (password !== passwordConfirm) {
                    event.preventDefault();
                    showClientError('Passwords do not match.', confirmInput);
                }
            });

            [passwordInput, confirmInput].forEach(function (field) {
                if (!field) {
                    return;
                }
                field.addEventListener('input', function () {
                    if (clientError && clientError.style.display !== 'none') {
                        hideClientError();
                    }
                });
            });
        })();
    </script>
</body>
</html>
