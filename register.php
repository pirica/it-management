<?php
include('config/config.php');

$companies = mysqli_query($conn, 'SELECT id, company FROM companies WHERE active = 1 ORDER BY company');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $company_id = (int)($_POST['company_id'] ?? 0);

    $companyCheck = mysqli_prepare($conn, 'SELECT id FROM companies WHERE id = ? AND active = 1 LIMIT 1');
    $companyExists = false;
    if ($companyCheck) {
        mysqli_stmt_bind_param($companyCheck, 'i', $company_id);
        mysqli_stmt_execute($companyCheck);
        $companyRes = mysqli_stmt_get_result($companyCheck);
        $companyExists = $companyRes && mysqli_num_rows($companyRes) > 0;
        mysqli_stmt_close($companyCheck);
    }

    if (!$companyExists) {
        $error = 'Please select a valid company.';
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (?, ?, ?, ?, (SELECT id FROM user_roles WHERE company_id = ? AND name = "User" LIMIT 1), (SELECT id FROM access_levels WHERE company_id = ? AND name = "Limited" LIMIT 1), 1)');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isssii', $company_id, $username, $email, $password, $company_id, $company_id);
            if (mysqli_stmt_execute($stmt)) {
                $user_id = (int)mysqli_insert_id($conn);
                $uc = mysqli_prepare($conn, 'INSERT INTO user_companies (user_id, company_id, granted_by_user_id) VALUES (?, ?, NULL)');
                if ($uc) {
                    mysqli_stmt_bind_param($uc, 'ii', $user_id, $company_id);
                    mysqli_stmt_execute($uc);
                    mysqli_stmt_close($uc);
                }
                $success = 'Registration successful! You can login now.';
            } else {
                $error = 'Registration failed. Email/username may already exist.';
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
    <title>Register - IT Management</title>
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
        input, select { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; background: var(--bg); color: var(--text); margin-bottom: 16px; }
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
            <p>Create your account</p>
        </div>

        <?php if (isset($success)): ?><p style="color:#2f855a; margin-bottom:14px;"><?php echo htmlspecialchars($success); ?></p><?php endif; ?>
        <?php if (isset($error)): ?><p style="color:#d93025; margin-bottom:14px;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>

        <form method="POST">
            <label for="company_id">Company:</label>
            <select id="company_id" name="company_id" required>
                <option value="">-- Select a Company --</option>
                <?php while ($company = mysqli_fetch_assoc($companies)): ?>
                    <option value="<?php echo (int)$company['id']; ?>"><?php echo htmlspecialchars($company['company']); ?></option>
                <?php endwhile; ?>
            </select>

            <label for="username">Username</label>
            <input id="username" type="text" name="username" placeholder="Username" required>

            <label for="email">Email</label>
            <input id="email" type="email" name="email" placeholder="Email" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="Password" required>

            <button type="submit">Enter System</button>
        </form>
        <div class="links"><a href="login.php">Back to Login</a></div>
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
