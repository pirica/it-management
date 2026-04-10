<?php
/**
 * User Login Page
 * 
 * Handles user authentication via email/username and password.
 * Uses secure password verification only and migrates legacy hash formats on login.
 * Implements CSRF protection and role-based redirection.
 */

session_start();
include('config/config.php');
$csrfToken = itm_get_csrf_token();

/**
 * Why: Login endpoint is public and attractive for credential stuffing, so we
 * keep a lightweight audit trail for throttling and incident response.
 */
function itm_record_login_attempt(mysqli $conn, string $attemptType, string $ipAddress, ?string $identifier = null, ?int $userId = null): void
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO login_attempts (attempt_type, ip_address, email, user_id) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'sssi', $attemptType, $ipAddress, $identifier, $userId);
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
        'SELECT COUNT(*) FROM login_attempts WHERE attempt_type = \'failure\' AND ip_address = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)'
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
            'SELECT COUNT(*) FROM login_attempts WHERE attempt_type = \'failure\' AND email = ? AND created_at >= (NOW() - INTERVAL 15 MINUTE)'
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

/**
 * Why: We want deterministic IP storage for security analytics: prefer IPv4,
 * then use IPv6 only when IPv4 is unavailable.
 */
function itm_get_login_request_ip(): string
{
    $resolved = trim((string)(function_exists('itm_get_client_ip_address') ? itm_get_client_ip_address() : ''));
    if ($resolved !== '' && filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        return $resolved;
    }
    if ($resolved !== '' && filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        return $resolved;
    }

    $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        return $remoteAddr;
    }
    if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        return $remoteAddr;
    }

    return '0.0.0.0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token for all POST requests
    itm_require_post_csrf();
    
    $loginIdentifier = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $requestIp = substr(itm_get_login_request_ip(), 0, 45);
    $isRateLimited = itm_is_login_rate_limited($conn, $requestIp, $loginIdentifier);

    if ($isRateLimited) {
        itm_record_login_attempt($conn, 'failure', $requestIp, $loginIdentifier === '' ? null : $loginIdentifier, null);
        $error = 'Too many login attempts. Please wait a few minutes and try again.';
    } else {

        // Search for an active user by email or username
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, password FROM users WHERE active = 1 AND (LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)) LIMIT 1'
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $loginIdentifier, $loginIdentifier);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            $storedPassword = (string)($user['password'] ?? '');
            $userId = (int)($user['id'] ?? 0);
            $passwordMatches = false;

        if ($user) {
            // Why: Accept modern password_hash() values and transparently upgrade cost/algorithm when needed.
            if (password_verify($password, $storedPassword)) {
                $passwordMatches = true;

                if (password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                    $rehash = password_hash($password, PASSWORD_DEFAULT);
                    $rehashStmt = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ? LIMIT 1');
                    if ($rehashStmt) {
                        mysqli_stmt_bind_param($rehashStmt, 'si', $rehash, $userId);
                        mysqli_stmt_execute($rehashStmt);
                        mysqli_stmt_close($rehashStmt);
                    }
                }
            } else {
                // Why: Development/staged databases may still contain one-way legacy hashes.
                //      We allow one-time login via known legacy hashes, then immediately migrate
                //      to password_hash() without keeping plaintext comparison fallback.
                $legacyMd5 = md5($password);
                $legacySha1 = sha1($password);

                if (
                    hash_equals(strtolower($storedPassword), strtolower($legacyMd5))
                    || hash_equals(strtolower($storedPassword), strtolower($legacySha1))
                ) {
                    $passwordMatches = true;
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $migrateStmt = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ? LIMIT 1');
                    if ($migrateStmt) {
                        mysqli_stmt_bind_param($migrateStmt, 'si', $newHash, $userId);
                        mysqli_stmt_execute($migrateStmt);
                        mysqli_stmt_close($migrateStmt);
                    }
                }
            }
        }

            if ($passwordMatches) {
                itm_record_login_attempt($conn, 'success', $requestIp, $loginIdentifier === '' ? null : $loginIdentifier, $userId);

            $userId = (int)$user['id'];
            $_SESSION['user_id'] = $userId;
            
            // Clear any previously stored company context to ensure a fresh selection
            unset($_SESSION['company_id'], $_SESSION['company_name']);

            // Determine if the user is an admin
            $isAdmin = false;
            $adminStmt = mysqli_prepare(
                $conn,
                'SELECT 1
                 FROM users u
                 LEFT JOIN user_roles ur ON ur.id = u.role_id
                 WHERE u.id = ? AND (LOWER(COALESCE(ur.name, "")) = "admin" OR LOWER(u.username) = "admin")
                 LIMIT 1'
            );
            if ($adminStmt) {
                mysqli_stmt_bind_param($adminStmt, 'i', $userId);
                mysqli_stmt_execute($adminStmt);
                $adminRes = mysqli_stmt_get_result($adminStmt);
                $isAdmin = $adminRes && mysqli_num_rows($adminRes) > 0;
                mysqli_stmt_close($adminStmt);
            }

            $_SESSION['read_only_user_config'] = 0;

            // Admin users bypass employee status checks and go straight to the dashboard
            if ($isAdmin) {
                // Pre-select the first available company for admins for convenience
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

            // Regular users must have an associated 'Active' employee record to gain full system access
            $statusStmt = mysqli_prepare(
                $conn,
                'SELECT 1
                 FROM users u
                 INNER JOIN employees e ON LOWER(TRIM(e.email)) = LOWER(TRIM(u.email))
                 INNER JOIN employee_statuses es ON es.id = e.employment_status_id
                 WHERE u.id = ?
                   AND e.active = 1
                   AND LOWER(TRIM(COALESCE(es.name, ""))) = "active"
                 LIMIT 1'
            );
            $hasActiveEmployeeMatch = false;
            if ($statusStmt) {
                mysqli_stmt_bind_param($statusStmt, 'i', $userId);
                mysqli_stmt_execute($statusStmt);
                $statusRes = mysqli_stmt_get_result($statusStmt);
                $hasActiveEmployeeMatch = $statusRes && mysqli_num_rows($statusRes) > 0;
                mysqli_stmt_close($statusStmt);
            }

            // Users without an active employee record are restricted to the user-config page
            if (!$hasActiveEmployeeMatch) {
                $_SESSION['read_only_user_config'] = 1;
                header('Location: user-config.php');
                exit();
            }

            // Successfully authenticated regular user proceeds to company selection
                header('Location: index.php');
                exit();
            }

            itm_record_login_attempt($conn, 'failure', $requestIp, $loginIdentifier === '' ? null : $loginIdentifier, $userId > 0 ? $userId : null);
        }

        $error = 'Invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IT Management</title>
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
    </style>
</head>
<body>
    <button class="theme-btn" onclick="toggleTheme()">🌙</button>
    <div class="container">
        <div class="logo">
            <h1>⚙️ IT Management</h1>
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
            <a href="register.php">Register</a>
        </div>
    </div>
    <script>
        /**
         * Toggle light/dark theme and persist choice to localStorage
         */
        function toggleTheme() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'light' : 'dark');
            localStorage.setItem('theme', document.documentElement.getAttribute('data-theme'));
        }
        
        // Load initial theme from storage
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
    </script>
</body>
</html>
