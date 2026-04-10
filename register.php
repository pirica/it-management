<?php
/**
 * User Registration Page
 * 
 * Allows new users to create an account and select which companies they belong to.
 * Automatically assigns default roles and access levels based on the primary company.
 */

include('config/config.php');
$csrfToken = itm_get_csrf_token();

// Fetch all active companies for the multi-select dropdown
$companiesStmt = mysqli_prepare($conn, 'SELECT id, company FROM companies WHERE active = 1 ORDER BY company');
$companies = false;
if ($companiesStmt) {
    mysqli_stmt_execute($companiesStmt);
    $companies = mysqli_stmt_get_result($companiesStmt);
    mysqli_stmt_close($companiesStmt);
}
$companyOptions = [];
if ($companies) {
    while ($company = mysqli_fetch_assoc($companies)) {
        $companyOptions[] = $company;
    }
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $rawPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $selected_company_ids = $_POST['company_ids'] ?? [];
    
    // Normalize and sanitize selected company IDs
    if (!is_array($selected_company_ids)) {
        $selected_company_ids = [];
    }
    $selected_company_ids = array_values(array_unique(array_filter(array_map('intval', $selected_company_ids))));

    // Basic validation
    if ($rawPassword === '' || $confirmPassword === '') {
        $error = 'Password and confirmation are required.';
    } elseif ($rawPassword !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } elseif (empty($selected_company_ids)) {
        $error = 'Please select at least one valid company.';
    } else {
        // Verify that all selected companies are active and valid
        $placeholders = implode(',', array_fill(0, count($selected_company_ids), '?'));
        $types = str_repeat('i', count($selected_company_ids));
        $companyCheck = mysqli_prepare($conn, "SELECT id FROM companies WHERE active = 1 AND id IN ($placeholders)");
        $valid_company_ids = [];

        if ($companyCheck) {
            mysqli_stmt_bind_param($companyCheck, $types, ...$selected_company_ids);
            mysqli_stmt_execute($companyCheck);
            $companyRes = mysqli_stmt_get_result($companyCheck);
            if ($companyRes) {
                while ($row = mysqli_fetch_assoc($companyRes)) {
                    $valid_company_ids[] = (int)$row['id'];
                }
            }
            mysqli_stmt_close($companyCheck);
        }

        // Compare submitted IDs with valid IDs from the database
        sort($valid_company_ids);
        $submitted_ids = $selected_company_ids;
        sort($submitted_ids);
        $allCompaniesValid = !empty($valid_company_ids) && $submitted_ids === $valid_company_ids;

        if (!$allCompaniesValid) {
            $error = 'Please select only valid active companies.';
        }
    }

    // Proceed if there are no validation errors
    if (!isset($error)) {
        $password = password_hash($rawPassword, PASSWORD_DEFAULT);
        $primary_company_id = $selected_company_ids[0];
        
        // Insert new user into the database
        // Assigns default "User" role and "Limited" access level for the primary company
        $stmt = mysqli_prepare($conn, 'INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (?, ?, ?, ?, (SELECT id FROM user_roles WHERE company_id = ? AND name = "User" LIMIT 1), (SELECT id FROM access_levels WHERE company_id = ? AND name = "Limited" LIMIT 1), 1)');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isssii', $primary_company_id, $username, $email, $password, $primary_company_id, $primary_company_id);
            if (mysqli_stmt_execute($stmt)) {
                $user_id = (int)mysqli_insert_id($conn);
                
                // Assign all selected companies to the new user in a transaction
                mysqli_begin_transaction($conn);
                $uc = mysqli_prepare($conn, 'INSERT INTO user_companies (user_id, company_id, granted_by_user_id) VALUES (?, ?, NULL)');
                if ($uc) {
                    $insertedRows = 0;
                    foreach ($selected_company_ids as $company_id) {
                        mysqli_stmt_bind_param($uc, 'ii', $user_id, $company_id);
                        if (!mysqli_stmt_execute($uc)) {
                            break;
                        }
                        $insertedRows += mysqli_stmt_affected_rows($uc);
                    }
                    mysqli_stmt_close($uc);
                    
                    if ($insertedRows === count($selected_company_ids)) {
                        mysqli_commit($conn);
                        $success = 'Registration successful! You can login now.';
                    } else {
                        // Rollback and cleanup on partial failure
                        mysqli_rollback($conn);
                        $cleanup = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
                        if ($cleanup) {
                            mysqli_stmt_bind_param($cleanup, 'i', $user_id);
                            mysqli_stmt_execute($cleanup);
                            mysqli_stmt_close($cleanup);
                        }
                        $error = 'Registration failed while assigning companies. Please try again.';
                    }
                } else {
                    mysqli_rollback($conn);
                    $cleanup = mysqli_prepare($conn, 'DELETE FROM users WHERE id = ?');
                    if ($cleanup) {
                        mysqli_stmt_bind_param($cleanup, 'i', $user_id);
                        mysqli_stmt_execute($cleanup);
                        mysqli_stmt_close($cleanup);
                    }
                    $error = 'Registration failed while assigning companies. Please try again.';
                }
            } else {
                $error = 'Registration failed. Email/username may already exist.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Preserve selected company IDs on form submission error
$posted_company_ids = [];
if (isset($_POST['company_ids']) && is_array($_POST['company_ids'])) {
    $posted_company_ids = array_map('intval', $_POST['company_ids']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ⚙️ IT Controls</title>
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
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <label for="company_ids">Companies:</label>
            <select id="company_ids" name="company_ids[]" multiple required size="6">
                <?php foreach ($companyOptions as $company): ?>
                    <option value="<?php echo (int)$company['id']; ?>" <?php echo in_array((int)$company['id'], $posted_company_ids, true) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($company['company']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="email">Email</label>
            <input id="email" type="email" name="email" placeholder="Email" required>

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
