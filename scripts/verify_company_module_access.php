<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$isCli = (php_sapi_name() === 'cli');
$nl = itm_script_output_nl();
$failures = 0;

if (!$isCli) {
    itm_script_browser_nav_echo();
    echo '<h1>Verify Company Module Access</h1>';
}

if (!$conn instanceof mysqli) {
    echo '[FAIL] Database connection is required.' . $nl;
    exit(1);
}

if (!itm_module_access_table_exists($conn, 'modules_registry') || !itm_module_access_table_exists($conn, 'company_module_access')) {
    echo '[FAIL] Required tables modules_registry and company_module_access are missing.' . $nl;
    exit(1);
}

itm_sync_modules_registry_from_filesystem($conn);
$registryCount = count(itm_list_all_modules_registry($conn));
$discovered = count(itm_discover_module_slugs_for_registry());

if ($registryCount < $discovered) {
    echo '[FAIL] Registry row count (' . $registryCount . ') is lower than discovered modules (' . $discovered . ').' . $nl;
    $failures++;
} else {
    echo '[PASS] Registry contains ' . $registryCount . ' module rows (discovered ' . $discovered . ').' . $nl;
}

if (!has_module_access($conn, 1, 'settings')) {
    echo '[FAIL] settings should remain accessible for company 1.' . $nl;
    $failures++;
} else {
    echo '[PASS] settings access allowed for company 1.' . $nl;
}

$expectedAccessRows = $registryCount * 5;
$accessCountRes = mysqli_query($conn, 'SELECT COUNT(*) AS count FROM company_module_access');
$accessCountRow = $accessCountRes ? mysqli_fetch_assoc($accessCountRes) : null;
$accessCount = (int)($accessCountRow['count'] ?? 0);
if ($accessCount < $expectedAccessRows) {
    echo '[FAIL] company_module_access row count (' . $accessCount . ') is lower than expected company x module seeds (' . $expectedAccessRows . ').' . $nl;
    $failures++;
} else {
    echo '[PASS] company_module_access contains ' . $accessCount . ' seeded rows (expected at least ' . $expectedAccessRows . ').' . $nl;
}

$suppliersId = 0;
$stmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? LIMIT 1');
if ($stmt) {
    $slug = 'suppliers';
    mysqli_stmt_bind_param($stmt, 's', $slug);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $suppliersId = (int)($row['id'] ?? 0);
    mysqli_stmt_close($stmt);
}

if ($suppliersId > 0) {
    itm_set_company_module_access($conn, 1, $suppliersId, 0);
    if (has_module_access($conn, 1, 'suppliers')) {
        echo '[FAIL] suppliers should be denied after explicit enabled=0 for company 1.' . $nl;
        $failures++;
    } else {
        echo '[PASS] suppliers denied after explicit enabled=0 for company 1.' . $nl;
    }
    $stmtDelete = mysqli_prepare($conn, 'DELETE FROM company_module_access WHERE company_id = 1 AND module_id = ? LIMIT 1');
    if ($stmtDelete) {
        mysqli_stmt_bind_param($stmtDelete, 'i', $suppliersId);
        mysqli_stmt_execute($stmtDelete);
        mysqli_stmt_close($stmtDelete);
    }
    if (has_module_access($conn, 1, 'suppliers')) {
        echo '[FAIL] suppliers should be denied when company_module_access row is missing (strict opt-in).' . $nl;
        $failures++;
    } else {
        echo '[PASS] suppliers denied when company_module_access row is missing.' . $nl;
    }
    itm_set_company_module_access($conn, 1, $suppliersId, 1);
} else {
    echo '[FAIL] suppliers registry row not found.' . $nl;
    $failures++;
}

$allRows = itm_list_all_modules_registry($conn);
$hasExcluded = false;
foreach (['password_entries', 'floor_plan_tags'] as $excludedSlug) {
    foreach ($allRows as $registryRow) {
        if ((string)($registryRow['module_slug'] ?? '') === $excludedSlug) {
            $hasExcluded = true;
            break 2;
        }
    }
}
if (!$hasExcluded) {
    echo '[FAIL] Registry is missing sidebar-excluded module slugs used by the admin matrix.' . $nl;
    $failures++;
} else {
    echo '[PASS] Registry includes sidebar-excluded slugs for admin matrix visibility.' . $nl;
}

if ($failures > 0) {
    echo '[FAIL] Verification finished with ' . $failures . ' failure(s).' . $nl;
    exit(1);
}

echo '[PASS] Company module access verification succeeded.' . $nl;
exit(0);
