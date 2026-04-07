<?php
/**
 * Main Dashboard Page
 * 
 * Displays key statistics and overview information for the selected company.
 * Shows counts for equipment, tickets, and employees.
 */

require 'config/config.php';

$companyId = (int)$company_id;

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

$userId = (int)($_SESSION['user_id'] ?? 0);
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
    <title>Dashboard - IT Management</title>
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
                    <p style="color: var(--text-secondary); margin: 0;"><?php echo sanitize($welcomeMessage); ?></p>
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
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/theme.js"></script>
    <script src="js/script.js"></script>
</body>
</html>
