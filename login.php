<?php
session_start();
include('config/config.php');
$csrfToken = itm_get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $loginIdentifier = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

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
        $passwordMatches = $user && (
            password_verify($password, $storedPassword)
            || hash_equals($storedPassword, $password)
        );

        if ($passwordMatches) {
            $userId = (int)$user['id'];
            $_SESSION['user_id'] = $userId;
            unset($_SESSION['company_id'], $_SESSION['company_name']);

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

            if ($isAdmin) {
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

            if (!$hasActiveEmployeeMatch) {
                $_SESSION['read_only_user_config'] = 1;
                header('Location: user-config.php');
                exit();
            }

            header('Location: index.php');
            exit();
        }
    }

    $error = 'Invalid credentials.';
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
        function toggleTheme() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'light' : 'dark');
            localStorage.setItem('theme', document.documentElement.getAttribute('data-theme'));
        }
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
    </script>
</body>
</html>
