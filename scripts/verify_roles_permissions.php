<?php
/**
 * Roles & Permissions module regression checks.
 *
 * CLI: php scripts/verify_roles_permissions.php
 * Browser: scripts/verify_roles_permissions.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Roles & Permissions Verification');

$nl = itm_script_output_nl();
$failures = 0;

function rp_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function rp_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    rp_verify_fail('No database connection.');
    exit(1);
}

$requiredTables = ['employee_roles', 'role_module_permissions', 'role_hierarchy', 'modules_registry'];
foreach ($requiredTables as $table) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    if (!$stmt) {
        rp_verify_fail('Could not inspect schema for table: ' . $table);
        continue;
    }
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ((int)$count !== 1) {
        rp_verify_fail('Missing table: ' . $table);
    } else {
        rp_verify_pass('Table exists: ' . $table);
    }
}

$indexPath = ROOT_PATH . 'modules/roles_permissions/index.php';
if (!is_file($indexPath)) {
    rp_verify_fail('Missing modules/roles_permissions/index.php');
} else {
    rp_verify_pass('Module entry file exists: modules/roles_permissions/index.php');
}

$jsPath = ROOT_PATH . 'js/roles-permissions-matrix.js';
if (!is_file($jsPath)) {
    rp_verify_fail('Missing js/roles-permissions-matrix.js');
} else {
    rp_verify_pass('Client script exists: js/roles-permissions-matrix.js');
}

$registryStmt = mysqli_prepare($conn, 'SELECT id, module_name FROM modules_registry WHERE module_slug = ? LIMIT 1');
$slug = 'roles_permissions';
if ($registryStmt) {
    mysqli_stmt_bind_param($registryStmt, 's', $slug);
    mysqli_stmt_execute($registryStmt);
    $res = mysqli_stmt_get_result($registryStmt);
    $registryRow = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($registryStmt);
    if (!$registryRow) {
        rp_verify_fail('modules_registry row missing for roles_permissions.');
    } else {
        rp_verify_pass('modules_registry row present for roles_permissions.');
    }
}

if (function_exists('itm_crud_rbac_exempt_module_slugs')) {
    $exempt = itm_crud_rbac_exempt_module_slugs();
    if (!in_array('roles_permissions', $exempt, true)) {
        rp_verify_fail('roles_permissions must stay in itm_crud_rbac_exempt_module_slugs().');
    } else {
        rp_verify_pass('roles_permissions is RBAC-exempt (uses admin gate for mutations).');
    }
} else {
    rp_verify_fail('itm_crud_rbac_exempt_module_slugs() helper missing.');
}

$companyId = 1;
$adminRoleId = 0;
$adminStmt = mysqli_prepare(
    $conn,
    'SELECT id FROM employee_roles WHERE company_id = ? AND LOWER(name) = ? AND active = 1 LIMIT 1'
);
if ($adminStmt) {
    $adminName = 'admin';
    mysqli_stmt_bind_param($adminStmt, 'is', $companyId, $adminName);
    mysqli_stmt_execute($adminStmt);
    mysqli_stmt_bind_result($adminStmt, $adminRoleId);
    if (!mysqli_stmt_fetch($adminStmt)) {
        rp_verify_fail('Seeded Admin role missing for company 1.');
    } else {
        rp_verify_pass('Seeded Admin role present for company 1.');
    }
    mysqli_stmt_close($adminStmt);
}

if ($adminRoleId > 0) {
    $allStmt = mysqli_prepare(
        $conn,
        'SELECT can_view, can_create, can_edit, can_delete, can_import, can_export
         FROM role_module_permissions
         WHERE company_id = ? AND role_id = ? AND module_name = ? LIMIT 1'
    );
    if ($allStmt) {
        $moduleAll = 'ALL';
        mysqli_stmt_bind_param($allStmt, 'iis', $companyId, $adminRoleId, $moduleAll);
        mysqli_stmt_execute($allStmt);
        $res = mysqli_stmt_get_result($allStmt);
        $allRow = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($allStmt);
        if (!$allRow) {
            rp_verify_fail('Admin role missing ALL wildcard row in role_module_permissions.');
        } else {
            $flags = ['can_view', 'can_create', 'can_edit', 'can_delete', 'can_import', 'can_export'];
            $missing = [];
            foreach ($flags as $flag) {
                if ((int)($allRow[$flag] ?? 0) !== 1) {
                    $missing[] = $flag;
                }
            }
            if ($missing !== []) {
                rp_verify_fail('Admin ALL wildcard missing flags: ' . implode(', ', $missing));
            } else {
                rp_verify_pass('Admin ALL wildcard grants all six RBAC flags.');
            }
        }
    }
}

$roleCountStmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) AS c FROM employee_roles WHERE company_id = ? AND active = 1'
);
if ($roleCountStmt) {
    mysqli_stmt_bind_param($roleCountStmt, 'i', $companyId);
    mysqli_stmt_execute($roleCountStmt);
    mysqli_stmt_bind_result($roleCountStmt, $roleCount);
    mysqli_stmt_fetch($roleCountStmt);
    mysqli_stmt_close($roleCountStmt);
    if ((int)$roleCount < 1) {
        rp_verify_fail('No active roles seeded for company 1.');
    } else {
        rp_verify_pass('Active roles seeded for company 1 (' . (int)$roleCount . ').');
    }
}

$hierarchyStmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) AS c FROM role_hierarchy WHERE company_id = ?'
);
if ($hierarchyStmt) {
    mysqli_stmt_bind_param($hierarchyStmt, 'i', $companyId);
    mysqli_stmt_execute($hierarchyStmt);
    mysqli_stmt_bind_result($hierarchyStmt, $hierarchyCount);
    mysqli_stmt_fetch($hierarchyStmt);
    mysqli_stmt_close($hierarchyStmt);
    if ((int)$hierarchyCount < 1) {
        rp_verify_fail('No role_hierarchy rows for company 1.');
    } else {
        rp_verify_pass('role_hierarchy rows present for company 1 (' . (int)$hierarchyCount . ').');
    }
}

$columnStmt = mysqli_query(
    $conn,
    "SELECT COLUMN_NAME FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'role_module_permissions'
       AND COLUMN_NAME IN ('can_import','can_export')"
);
$importExportCols = [];
if ($columnStmt) {
    while ($row = mysqli_fetch_assoc($columnStmt)) {
        $importExportCols[] = (string)($row['COLUMN_NAME'] ?? '');
    }
}
if (count($importExportCols) !== 2) {
    rp_verify_fail('role_module_permissions must expose can_import and can_export columns.');
} else {
    rp_verify_pass('role_module_permissions includes Import/Export columns.');
}

require_once ROOT_PATH . 'includes/itm_employee_employment_status.php';
$empStatusJoin = itm_employee_active_employment_status_join_sql('e', 'es');
$empStatusPredicate = itm_employee_active_employment_status_predicate_sql('es');
$crossCompanyAdminCount = 0;
$crossSql = 'SELECT COUNT(DISTINCT e.id) AS c
     FROM employees e
     INNER JOIN employee_roles er_assign
       ON er_assign.id = e.role_id AND er_assign.company_id = e.company_id'
    . $empStatusJoin .
    ' INNER JOIN employee_companies ec
       ON ec.employee_id = e.id AND ec.company_id = ? AND ec.active = 1
     WHERE ' . $empStatusPredicate . '
       AND er_assign.name = ?
       AND e.company_id <> ?';
$crossStmt = mysqli_prepare($conn, $crossSql);
$targetCompanyId = 2;
$adminRoleName = 'Admin';
if ($crossStmt) {
    mysqli_stmt_bind_param(
        $crossStmt,
        'isi',
        $targetCompanyId,
        $adminRoleName,
        $targetCompanyId
    );
    mysqli_stmt_execute($crossStmt);
    mysqli_stmt_bind_result($crossStmt, $crossCompanyAdminCount);
    mysqli_stmt_fetch($crossStmt);
    mysqli_stmt_close($crossStmt);
}
if ($crossCompanyAdminCount < 1) {
    rp_verify_fail('Cross-company Active Admin count for company 2 expected at least 1.');
} else {
    rp_verify_pass('Cross-company Active Admin count for company 2 includes home-company Admin (' . (int)$crossCompanyAdminCount . ').');
}

if ($failures > 0) {
    echo colorText('Verification finished with ' . $failures . ' failure(s).', 'fail') . $nl;
    exit(1);
}

echo colorText('All Roles & Permissions checks passed.', 'pass') . $nl;
exit(0);
