<?php
/**
 * Employee System Access Module - View
 *
 * Provides a read-only summary of one employee's granted system access entries.
 */

require '../../config/config.php';
require '../../includes/employee_system_access.php';

// Keep relation/catalog tables available so this view can run on fresh installs.
esa_ensure_table($conn);

// Accept both `id` and legacy `employee_id` so shared links like
// `modules/<name>/view.php?id=<id>` open this module consistently.
$employeeId = (int)($_GET['id'] ?? $_GET['employee_id'] ?? 0);
$employee = null;
$systemAccessCatalog = [];
$grantedAccessMap = [];

if ($employeeId > 0) {
    $employeeStmt = mysqli_prepare(
        $conn,
        'SELECT id, first_name, last_name, display_name, email FROM employees WHERE id = ? AND company_id = ? LIMIT 1'
    );
    if ($employeeStmt) {
        mysqli_stmt_bind_param($employeeStmt, 'ii', $employeeId, $company_id);
        mysqli_stmt_execute($employeeStmt);
        $employeeResult = mysqli_stmt_get_result($employeeStmt);
        if ($employeeResult && mysqli_num_rows($employeeResult) === 1) {
            $employee = mysqli_fetch_assoc($employeeResult);
        }
        mysqli_stmt_close($employeeStmt);
    }
}

if ($employee) {
    $systemAccessCatalog = esa_get_system_access_catalog($conn, (int)$company_id, false);
    $grantedAccessIds = esa_get_employee_access_ids($conn, (int)$company_id, (int)$employee['id']);
    $grantedAccessMap = array_fill_keys(array_map('intval', $grantedAccessIds), true);
}

$employeeDisplayName = '';
if ($employee) {
    $employeeDisplayName = (string)($employee['display_name'] ?? '');
    if ($employeeDisplayName === '') {
        $employeeDisplayName = trim((string)($employee['first_name'] ?? '') . ' ' . (string)($employee['last_name'] ?? ''));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Employee System Access</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>🔎 View Employee System Access</h1>

            <div class="card">
                <?php if (!$employee): ?>
                    <div class="alert alert-danger">Employee not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr>
                            <th style="width: 260px;">Employee</th>
                            <td><?php echo sanitize($employeeDisplayName); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>
                                <?php if (!empty($employee['email'])): ?>
                                    <a href="mailto:<?php echo sanitize((string)$employee['email']); ?>"><?php echo sanitize((string)$employee['email']); ?></a>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <h2 style="margin-top: 20px;">System Access Grants</h2>
                    <?php if (empty($systemAccessCatalog)): ?>
                        <p>No active System Access records were found. Add some in <a href="../system_access/">System Access</a>.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                            <tr>
                                <th>System</th>
                                <th style="width: 130px;">Granted</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($systemAccessCatalog as $itmAccess): ?>
                                <?php $itmAccessId = (int)($itmAccess['id'] ?? 0); ?>
                                <tr>
                                    <td><?php echo sanitize((string)($itmAccess['name'] ?? '')); ?></td>
                                    <td><?php echo isset($grantedAccessMap[$itmAccessId]) ? '✅' : '❌'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <a href="index.php" class="btn">🔙</a>
                    <?php if ($employee): ?>
                        <a href="edit.php?id=<?php echo (int)$employee['id']; ?>" class="btn btn-primary">✏️</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
