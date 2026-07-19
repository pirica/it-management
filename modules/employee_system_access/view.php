<?php
/**
 * Employee System Access Module - View
 *
 * Provides a read-only summary of one employee's granted system access entries.
 */

require '../../config/config.php';
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);
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
        'SELECT id, first_name, last_name, display_name, work_email, personal_email FROM employees WHERE id = ? AND company_id = ? LIMIT 1'
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
    $abilityFields = esa_resolve_ability_fields($conn, (int)$company_id);
    $mappedSystemAccessCatalog = [];
    foreach ($systemAccessCatalog as $itmAccessRow) {
        if (esa_resolve_field_for_catalog_row($itmAccessRow, $abilityFields) !== '') {
            $mappedSystemAccessCatalog[] = $itmAccessRow;
        }
    }
    $systemAccessCatalog = $mappedSystemAccessCatalog;
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

$esaRow = [];
if ($employee) {
    $esaStmt = mysqli_prepare(
        $conn,
        'SELECT * FROM employee_system_access WHERE employee_id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
    );
    if ($esaStmt) {
        mysqli_stmt_bind_param($esaStmt, 'ii', $employee['id'], $company_id);
        mysqli_stmt_execute($esaStmt);
        $esaResult = mysqli_stmt_get_result($esaStmt);
        if ($esaResult && mysqli_num_rows($esaResult) === 1) {
            $esaRow = mysqli_fetch_assoc($esaResult);
        }
        mysqli_stmt_close($esaStmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Employee system access';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="View employee system access">🔎</h1>

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
                                <?php $effectiveEmail = trim((string)($employee['work_email'] ?? '')) ?: (string)($employee['personal_email'] ?? ''); ?>
                                <?php if ($effectiveEmail !== ''): ?>
                                    <a href="mailto:<?php echo sanitize($effectiveEmail); ?>"><?php echo sanitize($effectiveEmail); ?></a>
                                <?php else: ?>
                                    <span>—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>

                    <?php if (!empty($esaRow)): ?>
                    <h2 style="margin-top: 20px;">Record meta</h2>
                    <table>
                        <tbody>
                        <?php itm_crud_render_view_audit_meta_rows($conn, (int)$company_id, $esaRow); ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

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
