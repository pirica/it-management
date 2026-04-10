<?php
/**
 * Main Dashboard Page
 * 
 * Displays key statistics and overview information for the selected company.
 * Shows counts for equipment, tickets, and employees.
 */

require 'config/config.php';

$companyId = (int)$company_id;
$userId = (int)($_SESSION['user_id'] ?? 0);
$csrfToken = itm_get_csrf_token();

// Determine if the current user can access all companies
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
             INNER JOIN user_companies uc ON uc.company_id = c.id
             WHERE c.id = ? AND uc.user_id = ? AND c.active = 1
             LIMIT 1'
        );
        if ($switchStmt) {
            mysqli_stmt_bind_param($switchStmt, 'ii', $requestedCompanyId, $userId);
            if (mysqli_stmt_execute($switchStmt)) {
                $switchRes = mysqli_stmt_get_result($switchStmt);
                $selectedCompany = $switchRes ? mysqli_fetch_assoc($switchRes) : null;
            }
            mysqli_stmt_close($switchStmt);
        }
    }

    if ($selectedCompany) {
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
         INNER JOIN user_companies uc ON uc.company_id = c.id
         WHERE c.active = 1 AND uc.user_id = ?
         ORDER BY c.company'
    );
    if ($companiesStmt) {
        mysqli_stmt_bind_param($companiesStmt, 'i', $userId);
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

/**
 * Helper function to fetch record counts scoped to a company
 * 
 * @param mysqli $conn The database connection
 * @param string $sql The SQL query (must include a placeholder for company_id)
 * @param int $companyId The company ID to filter by
 * @return int The number of records found
 */
function fetch_company_count(mysqli $conn, string $sql, int $companyId): int
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $res = mysqli_stmt_get_result($stmt);
    $count = (int)(($res ? mysqli_fetch_assoc($res) : [])['count'] ?? 0);
    mysqli_stmt_close($stmt);
    return $count;
}

/**
 * Checks if a table has a specific column
 * 
 * Useful for conditional queries when the schema might vary.
 * Uses a static cache to avoid redundant database queries.
 */
function table_has_column(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $cacheKey = $table . '.' . $column;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $sql = 'SELECT COUNT(*) AS count
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $cache[$cacheKey] = false;
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        $cache[$cacheKey] = false;
        return false;
    }

    $res = mysqli_stmt_get_result($stmt);
    $count = (int)(($res ? mysqli_fetch_assoc($res) : [])['count'] ?? 0);
    mysqli_stmt_close($stmt);

    $cache[$cacheKey] = $count > 0;
    return $cache[$cacheKey];
}

// Build dynamic queries for dashboard cards based on available columns
$equipmentSql = 'SELECT COUNT(*) AS count FROM equipment WHERE company_id = ?';
if (table_has_column($conn, 'equipment', 'active')) {
    $equipmentSql .= ' AND active = 1';
}


// Fetch the actual counts for the dashboard cards
$equipment_count = fetch_company_count($conn, $equipmentSql, $companyId);
$tickets_count = fetch_company_count($conn, 'SELECT COUNT(*) AS count FROM tickets WHERE company_id = ?', $companyId);
$employees_count = fetch_company_count($conn, 'SELECT COUNT(*) AS count FROM employees WHERE company_id = ?', $companyId);

// Fetch logged-in user details for the welcome message
$userDisplayName = '';
$userEmail = '';

if ($userId > 0) {
    $userStmt = mysqli_prepare($conn, 'SELECT username, email FROM users WHERE id = ? LIMIT 1');
    if ($userStmt) {
        mysqli_stmt_bind_param($userStmt, 'i', $userId);
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
                    <a class="stat-card stat-card-link" href="<?php echo BASE_URL; ?>modules/equipment/">
                        <div class="stat-label">Equipment</div>
                        <div class="stat-number"><?php echo $equipment_count; ?></div>
                    </a>
                    <a class="stat-card stat-card-link" href="<?php echo BASE_URL; ?>modules/tickets/">
                        <div class="stat-label">Tickets</div>
                        <div class="stat-number"><?php echo $tickets_count; ?></div>
                    </a>
                    <a class="stat-card stat-card-link" href="<?php echo BASE_URL; ?>modules/employees/">
                        <div class="stat-label">Employees</div>
                        <div class="stat-number"><?php echo $employees_count; ?></div>
                    </a>
                </div>

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
                        <p><strong>Company:</strong> <?php echo sanitize($company_data['company']); ?></p>
                        <p><strong>InCode:</strong> <?php echo sanitize($company_data['incode']); ?></p>
                        <p><strong>Location:</strong> <?php echo sanitize(trim(($company_data['city'] ?? '') . ', ' . ($company_data['country'] ?? ''), ', ')); ?></p>
                        <p><strong>Phone:</strong> <?php echo sanitize((string)($company_data['phone'] ?? '')); ?></p>

                        <?php if ($companies && mysqli_num_rows($companies) > 0): ?>
                            <form method="POST" style="margin-top:20px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                <div style="margin-bottom: 12px;">
                                    <label for="company" style="display:block;margin-bottom:8px;"><strong>Switch Company:</strong></label>
                                    <select name="company_id" id="company" required onchange="updateName()" data-addable-select="1" data-add-table="companies" data-add-id-col="id" data-add-label-col="company" data-add-company-scoped="0" data-add-friendly="company">
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
