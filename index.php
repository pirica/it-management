<?php
// Force enable all error reporting for maximum visibility
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

/**
 * Company Selection Page
 * 
 * This is the entry point after login. It allows users to select which company
 * context they want to work in. Admin users can see all active companies,
 * while regular users only see companies they are assigned to.
 */

require 'config/config.php';

// Redirect to login if the user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Early error reporting for database connection issues
// This helps diagnose connection failures before the UI is rendered
if (!$conn) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

$userId = (int)$_SESSION['user_id'];
$csrfToken = itm_get_csrf_token();
$isAdmin = false;

// Check if the current user has administrative privileges
// We check both the role name and the username for 'admin'
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

// Handle company selection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token for security
    itm_require_post_csrf();
    
    $company_id = (int)($_POST['company_id'] ?? 0);
    $company = null;

    if ($isAdmin) {
        // Admins can select any active company
        $stmt = mysqli_prepare($conn, 'SELECT c.company FROM companies c WHERE c.id = ? AND c.active = 1 LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $company_id);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $company = $res ? mysqli_fetch_assoc($res) : null;
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        // Regular users must be assigned to the company
        $stmt = mysqli_prepare($conn, 'SELECT c.company FROM companies c INNER JOIN user_companies uc ON uc.company_id = c.id WHERE c.id = ? AND uc.user_id = ? AND c.active = 1 LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $company_id, $userId);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $company = $res ? mysqli_fetch_assoc($res) : null;
            }
            mysqli_stmt_close($stmt);
        }
    }

    // If valid company selected, store in session and proceed to dashboard
    if ($company) {
        $_SESSION['company_id'] = $company_id;
        $_SESSION['company_name'] = $company['company'];
        header('Location: dashboard.php');
        exit();
    }
}

// Fetch available companies for the selection dropdown
if ($isAdmin) {
    $companies = mysqli_query($conn, 'SELECT c.* FROM companies c WHERE c.active = 1 ORDER BY c.company');
} else {
    $stmtCompanies = mysqli_prepare($conn, 'SELECT c.* FROM companies c INNER JOIN user_companies uc ON uc.company_id = c.id WHERE c.active = 1 AND uc.user_id = ? ORDER BY c.company');
    $companies = false;
    if ($stmtCompanies) {
        mysqli_stmt_bind_param($stmtCompanies, 'i', $userId);
        mysqli_stmt_execute($stmtCompanies);
        $companies = mysqli_stmt_get_result($stmtCompanies);
    }
}
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
            --muted: #666;
        }
        [data-theme="dark"] {
            --accent: #58a6ff;
            --bg: #0d1117;
            --text: #c9d1d9;
            --muted: #8b949e;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .container { background: var(--bg); padding: 40px; border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: var(--accent); font-size: 28px; }
        .logo p { color: var(--muted); font-size: 14px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--text); }
        select, input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg);
            color: var(--text);
        }
        button, .logout-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .logout-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            width: auto;
            padding: 10px 14px;
            background: rgba(13, 17, 23, 0.82);
        }
        .theme-btn { position: absolute; top: 20px; right: 20px; background: var(--bg); border: none; width: 50px; height: 50px; border-radius: 50%; cursor: pointer; font-size: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <!-- Logout form with CSRF protection -->
    <form method="POST" action="logout.php" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <button type="submit" class="logout-btn">Logout</button>
    </form>
    <button class="theme-btn" onclick="toggleTheme()">🌙</button>

    <div class="container">
        <div class="logo">
            <h1>⚙️ IT Management</h1>
            <p>Select Your Company</p>
        </div>

        <?php if ($companies && mysqli_num_rows($companies) > 0): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
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
        /**
         * Update the hidden company name input when selection changes
         */
        function updateName() {
            const select = document.getElementById('company');
            document.getElementById('company_name').value = select.options[select.selectedIndex].getAttribute('data-name');
        }

        /**
         * Toggle between light and dark themes
         */
        function toggleTheme() {
            const theme = document.documentElement.getAttribute('data-theme');
            document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'light' : 'dark');
            localStorage.setItem('theme', document.documentElement.getAttribute('data-theme'));
        }
        
        // Initialize theme from local storage
        document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
    </script>
</body>
</html>
