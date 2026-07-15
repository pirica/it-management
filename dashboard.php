<?php
/**
 * Main Dashboard Page (Optimized by Bolt ⚡)
 *
 * Displays key statistics and overview information for the selected company.
 * Consolidates multiple count queries into a single database round-trip.
 */

require 'config/config.php';

// SEMPRE pega da session, não de variável global
$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$csrfToken = itm_get_csrf_token();

// Determine if the current user can access all companies
$isAdmin = false;
$adminStmt = mysqli_prepare(
    $conn,
    'SELECT 1
     FROM employees u
     LEFT JOIN employee_roles ur ON ur.id = u.role_id
     WHERE u.id = ? AND (LOWER(COALESCE(ur.name, "")) = "admin" OR LOWER(u.username) = "admin")
     LIMIT 1'
);
if ($adminStmt) {
    mysqli_stmt_bind_param($adminStmt, 'i', $employeeId);
    mysqli_stmt_execute($adminStmt);
    $adminRes = mysqli_stmt_get_result($adminStmt);
    $isAdmin = $adminRes && mysqli_num_rows($adminRes) > 0;
    mysqli_stmt_close($adminStmt);
}

// Allow switching company directly from the dashboard information card
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_id'])) {
    itm_require_post_csrf();

    $requestedCompanyId = (int)($_POST['company_id'] ?? 0);
    $selectedCompany = null;

    if ($isAdmin) {
        $switchStmt = mysqli_prepare($conn, 'SELECT company FROM companies WHERE id = ? AND active = 1 LIMIT 1');
        if ($switchStmt) {
            mysqli_stmt_bind_param($switchStmt, 'i', $requestedCompanyId);
            if (mysqli_stmt_execute($switchStmt)) {
                $switchRes = mysqli_stmt_get_result($switchStmt);
                $selectedCompany = $switchRes ? mysqli_fetch_assoc($switchRes) : null;
            }
            mysqli_stmt_close($switchStmt);
        }
    } else {
        $switchStmt = mysqli_prepare(
            $conn,
            'SELECT c.company
             FROM companies c
             INNER JOIN employee_companies uc ON uc.company_id = c.id
             WHERE c.id = ? AND uc.employee_id = ? AND c.active = 1
             LIMIT 1'
        );
        if ($switchStmt) {
            mysqli_stmt_bind_param($switchStmt, 'ii', $requestedCompanyId, $employeeId);
            if (mysqli_stmt_execute($switchStmt)) {
                $switchRes = mysqli_stmt_get_result($switchStmt);
                $selectedCompany = $switchRes ? mysqli_fetch_assoc($switchRes) : null;
            }
            mysqli_stmt_close($switchStmt);
        }
    }

if ($selectedCompany) {
    // Busca o ID do employee nessa empresa nova
    $empStmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE username = ? AND company_id = ? LIMIT 1');
    $currentUsername = $_SESSION['username'] ?? '';
    mysqli_stmt_bind_param($empStmt, 'si', $currentUsername, $requestedCompanyId);
    mysqli_stmt_execute($empStmt);
    $empRes = mysqli_stmt_get_result($empStmt);
    $newEmployee = mysqli_fetch_assoc($empRes);
    mysqli_stmt_close($empStmt);

    if ($newEmployee) {
        $_SESSION['employee_id'] = (int)$newEmployee['id'];
    }

    $_SESSION['company_id'] = $requestedCompanyId;
    $_SESSION['company_name'] = (string)$selectedCompany['company'];
    header('Location: dashboard.php');
    exit();
}
}

// Fetch company options for the dashboard switcher
$companies = false;
if ($isAdmin) {
    $companies = mysqli_query($conn, 'SELECT id, company FROM companies WHERE active = 1 ORDER BY company');
} else {
    $companiesStmt = mysqli_prepare(
        $conn,
        'SELECT c.id, c.company
         FROM companies c
         INNER JOIN employee_companies uc ON uc.company_id = c.id
         WHERE c.active = 1 AND uc.employee_id = ?
         ORDER BY c.company'
    );
    if ($companiesStmt) {
        mysqli_stmt_bind_param($companiesStmt, 'i', $employeeId);
        mysqli_stmt_execute($companiesStmt);
        $companies = mysqli_stmt_get_result($companiesStmt);
    }
}

// Fetch details for the currently selected company
$company_data = null;
$companyStmt = mysqli_prepare($conn, 'SELECT * FROM companies WHERE id = ? LIMIT 1');
if ($companyStmt) {
    mysqli_stmt_bind_param($companyStmt, 'i', $companyId);
    if (mysqli_stmt_execute($companyStmt)) {
        $companyRes = mysqli_stmt_get_result($companyStmt);
        $company_data = $companyRes ? mysqli_fetch_assoc($companyRes) : null;
    }
    mysqli_stmt_close($companyStmt);
}

// Optimized by Bolt ⚡: Consolidate stats into a single query
$equipment_count = 0;
$tickets_count = 0;
$employees_count = 0;
$active_employees_count = 0;
$on_leave_count = 0;

if ($companyId > 0) {
    // Why: Consolidate stats into a single query to reduce database round-trips.
    // Note: 'active' column check removed as it is not present in the current 'equipment' schema.
    $statsSql = "SELECT
        (SELECT COUNT(*) FROM equipment WHERE company_id = ?) AS equipment_count,
        (SELECT COUNT(*) FROM tickets WHERE company_id = ?) AS tickets_count,
        (SELECT COUNT(*) FROM employees WHERE company_id = ?) AS employees_count,
        (SELECT COUNT(e.id) FROM employees e
         INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id
         WHERE e.company_id = ? AND LOWER(TRIM(es.name)) = 'active') AS active_count,
        (SELECT COUNT(e.id) FROM employees e
         INNER JOIN employee_statuses es ON es.id = e.employment_status_id AND es.company_id = e.company_id
         WHERE e.company_id = ? AND LOWER(TRIM(es.name)) = 'on leave') AS on_leave_count";

    $statsStmt = mysqli_prepare($conn, $statsSql);
    if ($statsStmt) {
        mysqli_stmt_bind_param($statsStmt, 'iiiii', $companyId, $companyId, $companyId, $companyId, $companyId);
        mysqli_stmt_execute($statsStmt);
        $statsRes = mysqli_stmt_get_result($statsStmt);
        if ($statsRes && ($counts = mysqli_fetch_assoc($statsRes))) {
            $equipment_count = (int)$counts['equipment_count'];
            $tickets_count = (int)$counts['tickets_count'];
            $employees_count = (int)$counts['employees_count'];
            $active_employees_count = (int)$counts['active_count'];
            $on_leave_count = (int)$counts['on_leave_count'];
        }
        mysqli_stmt_close($statsStmt);
    }
}

require_once ROOT_PATH . 'includes/itm_active_sessions.php';
$online_now_count = itm_count_logged_in_users_for_company($companyId, $conn);

// Fetch logged-in user details for the welcome message
$userDisplayName = '';
$userEmail = '';

if ($employeeId > 0) {
    $userStmt = mysqli_prepare($conn, 'SELECT username, email FROM employees WHERE id = ? LIMIT 1');
    if ($userStmt) {
        mysqli_stmt_bind_param($userStmt, 'i', $employeeId);
        if (mysqli_stmt_execute($userStmt)) {
            $userRes = mysqli_stmt_get_result($userStmt);
            $userData = $userRes ? mysqli_fetch_assoc($userRes) : null;
            $userDisplayName = trim((string)($userData['username'] ?? ''));
            $userEmail = trim((string)($userData['email'] ?? ''));
        }
        mysqli_stmt_close($userStmt);
    }
}

// Compose the personalized welcome message
$welcomeMessage = 'Welcome to DataCenter Plus';
if ($userDisplayName !== '' && $userEmail !== '') {
    $welcomeMessage .= ', ' . $userDisplayName . ' (' . $userEmail . ')';
} elseif ($userDisplayName !== '') {
    $welcomeMessage .= ', ' . $userDisplayName;
} elseif ($userEmail !== '') {
    $welcomeMessage .= ' - (' . $userEmail . ')';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>


            <div class="content">
                <div style="position:relative;display:flex;justify-content:flex-end;align-items:center;margin-bottom:20px;min-height:40px;">
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;">📊 Dashboard</h1>
                    <p style="margin: 0;"><a href="<?php echo BASE_URL; ?>user-config.php" style="color: var(--text-secondary); text-decoration: none;"><?php echo sanitize($welcomeMessage); ?></a></p>
                </div>

                <div class="stats-grid">
                    <!-- Dashboard Statistics Cards -->
                    <?php if (has_module_access($conn, $companyId, 'equipment')): ?>
                    <a class="stat-card stat-card-link" href="<?php echo BASE_URL; ?>modules/equipment/">
                        <div class="stat-label">Equipment</div>
                        <div class="stat-number"><?php echo $equipment_count; ?></div>
                    </a>
                    <?php endif; ?>
                    <?php if (has_module_access($conn, $companyId, 'tickets')): ?>
                    <a class="stat-card stat-card-link" href="<?php echo BASE_URL; ?>modules/tickets/">
                        <div class="stat-label">Tickets</div>
                        <div class="stat-number"><?php echo $tickets_count; ?></div>
                    </a>
                    <?php endif; ?>
                    <?php if (has_module_access($conn, $companyId, 'employees')): ?>
                    <a class="stat-card stat-card-link" href="<?php echo BASE_URL; ?>modules/employees/">
                        <div class="stat-label">Employees</div>
                        <div class="stat-number"><?php echo $employees_count; ?></div>
                    </a>
                    <?php endif; ?>
                </div>

                <?php if ($companyId > 0): ?>
                <div class="stats-grid">
                    <?php if (has_module_access($conn, $companyId, 'employees')): ?>
                    <a class="stat-card stat-card-link" href="<?php echo BASE_URL; ?>modules/employees/" title="Active employees for this company">
                        <div class="stat-label">Active</div>
                        <div class="stat-number"><?php echo (int)$active_employees_count; ?></div>
                    </a>
                    <?php endif; ?>
                    <div class="stat-card" title="Employees currently signed in for this company">
                        <div class="stat-label">Online now</div>
                        <div class="stat-number"><?php echo (int)$online_now_count; ?></div>
                    </div>
                    <?php if (has_module_access($conn, $companyId, 'employees')): ?>
                    <a class="stat-card stat-card-link" href="<?php echo BASE_URL; ?>modules/employees/" title="Employees on leave for this company">
                        <div class="stat-label">On Leave</div>
                        <div class="stat-number"><?php echo (int)$on_leave_count; ?></div>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- System Settings Quick Link -->
                <div class="card">
                    <div class="card-header"><h2>Settings</h2></div>
                    <div class="card-body">
                        <p>Manage backups and system maintenance options from one page.</p>
                        <a class="btn btn-primary" href="<?php echo BASE_URL; ?>modules/settings/">Open Settings</a>
                    </div>
                </div>

                <!-- Company Information Summary -->
                <div class="card">
                    <div class="card-header">
                        <h2>Information</h2>
                    </div>
                    <div class="card-body">
                        <?php if (is_array($company_data)): ?>
                            <p><strong>Company:</strong> <?php echo sanitize((string)($company_data['company'] ?? '')); ?></p>
                            <p><strong>InCode:</strong> <?php echo sanitize((string)($company_data['incode'] ?? '')); ?></p>
                            <p><strong>Location:</strong> <?php echo sanitize(trim(((string)($company_data['city'] ?? '')) . ', ' . ((string)($company_data['country'] ?? '')), ', ')); ?></p>
                            <p><strong>Phone:</strong> <?php echo sanitize((string)($company_data['phone'] ?? '')); ?></p>
                        <?php else: ?>
                            <p style="color: #999; margin-top: 12px;">Please switch company to view company information.</p>
                        <?php endif; ?>

                        <?php if ($companies && mysqli_num_rows($companies) > 0): ?>
                            <form method="POST" style="margin-top:20px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <div style="margin-bottom: 12px;">
                                    <label for="company" style="display:block;margin-bottom:8px;"><strong>Switch Company:</strong></label>
                                    <select name="company_id" id="company" required onchange="updateName()">
                                        <option value="">-- Select a Company --</option>
                                        <?php while ($c = mysqli_fetch_assoc($companies)): ?>
                                            <option value="<?php echo $c['id']; ?>" data-name="<?php echo htmlspecialchars($c['company']); ?>" <?php echo ((int)$c['id'] === $companyId) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($c['company']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <input type="hidden" name="company_name" id="company_name">
                                </div>
                                <button type="submit" class="btn btn-primary">Change Company</button>
                            </form>
                        <?php else: ?>
                            <p style="color: #999; margin-top: 12px;">No companies available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateName() {
            const select = document.getElementById('company');
            if (!select) {
                return;
            }

            const selectedOption = select.options[select.selectedIndex];
            const hiddenName = document.getElementById('company_name');
            if (hiddenName) {
                hiddenName.value = selectedOption ? (selectedOption.getAttribute('data-name') || '') : '';
            }
        }

        updateName();
    </script>
    <script src="js/theme.js"></script>
    <script src="js/script.js"></script>
    <script>
        window.ITM_BASE_URL = <?php echo json_encode(BASE_URL); ?>;
    </script>
    <script src="js/select-add-option.js"></script>
</body>
</html>
