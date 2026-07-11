<?php
/**
 * User Login Page
 * 
 * Handles employee authentication via work email/username and password.
 * Uses secure password verification only and migrates legacy hash formats on login.
 * Implements CSRF protection and role-based redirection.
 */

include('config/config.php');
require_once __DIR__ . '/includes/itm_employee_employment_status.php';
$csrfToken = itm_get_csrf_token();

/**
 * Why: Login endpoint is public and attractive for credential stuffing, so we
 * keep a lightweight audit trail for throttling and incident response.
 */
function itm_record_login_attempt(mysqli $conn, string $attemptType, string $ipAddress, ?string $identifier = null, ?int $employeeId = null): void
{
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO attempts (attempt_source, attempt_type, ip_address, email, employee_id, active)
         VALUES ('login', ?, ?, ?, ?, IF(
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
        mysqli_stmt_bind_param($stmt, 'sssis', $attemptType, $ipAddress, $identifier, $employeeId, $identifier);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Why: Public authentication should be cheaply rate-limited per-IP and
 * per-account identifier so brute-force attempts burn out quickly.
 */
function itm_is_login_rate_limited(mysqli $conn, string $ipAddress, ?string $identifier = null): bool
{
    $maxIpFailures = 12;
    $maxIdentifierFailures = 6;

    $stmtIp = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) FROM attempts WHERE attempt_source = 'login' AND attempt_type = 'failure' AND ip_address = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
    );
    if ($stmtIp) {
        mysqli_stmt_bind_param($stmtIp, 's', $ipAddress);
        mysqli_stmt_execute($stmtIp);
        mysqli_stmt_bind_result($stmtIp, $ipAttempts);
        mysqli_stmt_fetch($stmtIp);
        mysqli_stmt_close($stmtIp);
        if ((int)$ipAttempts >= $maxIpFailures) {
            return true;
        }
    }

    if ($identifier !== null && $identifier !== '') {
        $stmtIdentifier = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) FROM attempts WHERE attempt_source = 'login' AND attempt_type = 'failure' AND email = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)"
        );
        if ($stmtIdentifier) {
            mysqli_stmt_bind_param($stmtIdentifier, 's', $identifier);
            mysqli_stmt_execute($stmtIdentifier);
            mysqli_stmt_bind_result($stmtIdentifier, $identifierAttempts);
            mysqli_stmt_fetch($stmtIdentifier);
            mysqli_stmt_close($stmtIdentifier);
            if ((int)$identifierAttempts >= $maxIdentifierFailures) {
                return true;
            }
        }
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $loginIdentifier = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $requestIp = substr(itm_get_login_request_ip(), 0, 45);
    $storedAttemptIdentifier = itm_normalize_login_attempt_identifier($loginIdentifier);
    $isRateLimited = itm_is_login_rate_limited($conn, $requestIp, $storedAttemptIdentifier);

    if ($isRateLimited) {
        itm_record_login_attempt($conn, 'failure', $requestIp, $storedAttemptIdentifier, null);
        $error = 'Too many login attempts. Please wait a few minutes and try again.';
    } else {
        $join = itm_employee_active_employment_status_join_sql('e', 'es');
        $activePredicate = itm_employee_active_employment_status_predicate_sql('es');
        $stmt = mysqli_prepare(
            $conn,
            'SELECT e.id, e.password, e.work_email, e.personal_email, e.username, er.name AS role_name
             FROM employees e'
            . $join .
            ' LEFT JOIN employee_roles er ON e.role_id = er.id
             WHERE ' . $activePredicate . '
               AND e.password IS NOT NULL
               AND (
                    LOWER(COALESCE(e.work_email, "")) = LOWER(?)
                    OR LOWER(COALESCE(e.personal_email, "")) = LOWER(?)
                    OR LOWER(COALESCE(e.username, "")) = LOWER(?)
               )
             LIMIT 1'
        );

        $user = null;
        $employeeId = 0;
        $passwordMatches = false;
        $resolvedEmail = '';

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sss', $loginIdentifier, $loginIdentifier, $loginIdentifier);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
        }

        if ($user) {
            $storedPassword = (string)($user['password'] ?? '');
            $employeeId = (int)($user['id'] ?? 0);
            $resolvedEmail = trim((string)($user['work_email'] ?? ''));
            if ($resolvedEmail === '') {
                $resolvedEmail = trim((string)($user['personal_email'] ?? ''));
            }

            if (password_verify($password, $storedPassword)) {
                $passwordMatches = true;
                if (password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                    $rehash = password_hash($password, PASSWORD_DEFAULT);
                    $rehashStmt = mysqli_prepare($conn, 'UPDATE employees SET password = ? WHERE id = ? LIMIT 1');
                    if ($rehashStmt) {
                        mysqli_stmt_bind_param($rehashStmt, 'si', $rehash, $employeeId);
                        mysqli_stmt_execute($rehashStmt);
                        mysqli_stmt_close($rehashStmt);
                    }
                }
            } else {
                $legacyMd5 = md5($password);
                $legacySha1 = sha1($password);
                if (
                    hash_equals(strtolower($storedPassword), strtolower($legacyMd5))
                    || hash_equals(strtolower($storedPassword), strtolower($legacySha1))
                ) {
                    $passwordMatches = true;
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $migrateStmt = mysqli_prepare($conn, 'UPDATE employees SET password = ? WHERE id = ? LIMIT 1');
                    if ($migrateStmt) {
                        mysqli_stmt_bind_param($migrateStmt, 'si', $newHash, $employeeId);
                        mysqli_stmt_execute($migrateStmt);
                        mysqli_stmt_close($migrateStmt);
                    }
                }
            }
        }

        if ($passwordMatches && $employeeId > 0) {
            $successIdentifier = $resolvedEmail !== ''
                ? itm_normalize_login_attempt_identifier($resolvedEmail)
                : $storedAttemptIdentifier;
            itm_record_login_attempt(
                $conn,
                'success',
                $requestIp,
                $successIdentifier,
                $employeeId
            );

            $_SESSION['employee_id'] = $employeeId;
            $_SESSION['username'] = (string)($user['username'] ?? 'User');
            $_SESSION['email'] = $resolvedEmail;
            $_SESSION['role_name'] = (string)($user['role_name'] ?? '');
            unset($_SESSION['company_id'], $_SESSION['company_name']);

            $isAdmin = itm_is_admin($conn, $employeeId);
            $_SESSION['read_only_user_config'] = 0;

            if ($isAdmin) {
                $_SESSION['role_name'] = 'admin';
                $companyStmt = mysqli_prepare($conn, 'SELECT id, company FROM companies WHERE active = 1 ORDER BY company ASC LIMIT 1');
                if ($companyStmt) {
                    mysqli_stmt_execute($companyStmt);
                    $companyRes = mysqli_stmt_get_result($companyStmt);
                    $company = $companyRes ? mysqli_fetch_assoc($companyRes) : null;
                    mysqli_stmt_close($companyStmt);
                    if ($company) {
                        $_SESSION['company_id'] = (int)$company['id'];
                        $_SESSION['company_name'] = (string)$company['company'];
                    }
                }
                header('Location: dashboard.php');
                exit();
            }

            if (!itm_employee_has_active_employment_status($conn, $employeeId)) {
                $_SESSION['read_only_user_config'] = 1;
                header('Location: user-config.php');
                exit();
            }

            header('Location: index.php');
            exit();
        }

        itm_record_login_attempt(
            $conn,
            'failure',
            $requestIp,
            $storedAttemptIdentifier,
            $employeeId > 0 ? $employeeId : null
        );
        $error = 'Invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></title>
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
            <p>Sign in to continue</p>
        </div>

        <?php if (isset($error)): ?><p style="color:#d93025; margin-bottom:14px;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <label for="email">Email or Username</label>
            <input id="email" type="text" name="email" placeholder="Email or username" required>
            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <div class="links">
            <a href="forgot-password.php">Forgot Password?</a> ·
            <a href="register.php">Register with Invite</a>
        </div>
    </div>
    <script>
        function toggleTheme() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'light' : 'dark');
            localStorage.setItem('theme', document.documentElement.getAttribute('data-theme'));
        }
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
    </script>
</body>
</html>
