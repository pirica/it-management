<?php
require 'config/config.php';

// Early error reporting for database connection issues
if (!$conn) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    itm_require_post_csrf();
    $company_id = (int)$_POST['company_id'];
    $company = null;
    $stmt = mysqli_prepare($conn, 'SELECT company FROM companies WHERE id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        if (mysqli_stmt_execute($stmt)) {
            $res = mysqli_stmt_get_result($stmt);
            $company = $res ? mysqli_fetch_assoc($res) : null;
        }
        mysqli_stmt_close($stmt);
    }
    
    if ($company) {
        $_SESSION['company_id'] = $company_id;
        $_SESSION['company_name'] = $company['company'];
        header('Location: dashboard.php');
        exit();
    }
}

$companies = mysqli_query($conn, "SELECT * FROM companies WHERE active = 1 ORDER BY company");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Management System - Company Selector</title>
    <style>
        :root {
            --accent: #0969da;
            --bg: #ffffff;
            --text: #24292f;
        }
        [data-theme="dark"] {
            --accent: #58a6ff;
            --bg: #0d1117;
            --text: #c9d1d9;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container { background: var(--bg); padding: 40px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: var(--accent); font-size: 28px; }
        .logo p { color: #666; font-size: 14px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text); }
        select { width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px; background: var(--bg); color: var(--text); }
        button { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .theme-btn { position: absolute; top: 20px; right: 20px; background: var(--bg); border: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; font-size: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <button class="theme-btn" onclick="toggleTheme()">🌙</button>
    
    <div class="container">
        <div class="logo">
            <h1>⚙️ IT Management</h1>
            <p>Select Your Company</p>
        </div>

        <?php if (mysqli_num_rows($companies) > 0): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                <div style="margin-bottom: 20px;">
                    <label for="company">Company:</label>
                    <select name="company_id" id="company" required onchange="updateName()" data-addable-select="1" data-add-table="companies" data-add-id-col="id" data-add-label-col="company" data-add-company-scoped="0" data-add-friendly="company">
                        <option value="">-- Select a Company --</option>
                        <?php while ($c = mysqli_fetch_assoc($companies)): ?>
                            <option value="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['company']); ?>">
                                <?php echo htmlspecialchars($c['company']); ?>
                            </option>
                        <?php endwhile; ?>
                        <option value="__add_new__">➕</option>
                    </select>
                    <input type="hidden" name="company_name" id="company_name">
                </div>
                <button type="submit">Enter System</button>
            </form>
        <?php else: ?>
            <p style="text-align: center; color: #999;">No companies available.</p>
        <?php endif; ?>
    </div>


    <script>
        window.ITM_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
    </script>
    <script src="js/select-add-option.js"></script>

    <script>
        function updateName() {
            const select = document.getElementById('company');
            document.getElementById('company_name').value = select.options[select.selectedIndex].getAttribute('data-name');
        }
        function toggleTheme() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'light' : 'dark');
            localStorage.setItem('theme', document.documentElement.getAttribute('data-theme'));
        }
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
    </script>
</body>
</html>
