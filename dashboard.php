<?php
require 'config/config.php';

$companyId = (int)$company_id;

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

$equipmentSql = 'SELECT COUNT(*) AS count FROM equipment WHERE company_id = ?';
if (table_has_column($conn, 'equipment', 'active')) {
    $equipmentSql .= ' AND active = 1';
}

$workstationsSql = 'SELECT COUNT(*) AS count FROM workstations WHERE company_id = ?';
if (table_has_column($conn, 'workstations', 'active')) {
    $workstationsSql .= ' AND active = 1';
}

$equipment_count = fetch_company_count($conn, $equipmentSql, $companyId);
$workstations_count = fetch_company_count($conn, $workstationsSql, $companyId);
$tickets_count = fetch_company_count($conn, 'SELECT COUNT(*) AS count FROM tickets WHERE company_id = ?', $companyId);
$employees_count = fetch_company_count($conn, 'SELECT COUNT(*) AS count FROM employees WHERE company_id = ?', $companyId);
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
                <h1>📊 Dashboard</h1>
                <p style="color: var(--text-secondary); margin-bottom: 30px;">Welcome to <?php echo sanitize($company_data['company']); ?></p>

                <div class="stats-grid">
                    <a class="stat-card stat-card-link" href="<?php echo BASE_URL; ?>modules/equipment/">
                        <div class="stat-label">Equipment</div>
                        <div class="stat-number"><?php echo $equipment_count; ?></div>
                    </a>
                    <a class="stat-card stat-card-link" href="<?php echo BASE_URL; ?>modules/workstations/">
                        <div class="stat-label">Workstations</div>
                        <div class="stat-number"><?php echo $workstations_count; ?></div>
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

                <div class="card">
                    <div class="card-header"><h2>Settings</h2></div>
                    <div class="card-body">
                        <p>Manage backups and system maintenance options from one page.</p>
                        <a class="btn btn-primary" href="<?php echo BASE_URL; ?>modules/settings/">Open Settings</a>
                    </div>
                </div>

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
