<?php
/**
 * Regression: flattened CRUD FK label search matches related lookup names.
 *
 * CLI: php scripts/verify_crud_fk_label_search.php
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_crud_fk_label_search.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('CRUD FK Label Search Verification');

$nl = itm_script_output_nl();
$companyId = 1;
$failed = false;

function verify_crud_fk_label_run_isolated($scriptPath, array $session, array $get = [])
{
    $sessionExport = var_export($session, true);
    $getExport = var_export($get, true);
    $dir = var_export(dirname($scriptPath), true);
    $base = var_export(basename($scriptPath), true);
    $code = "<?php
define('ITM_CLI_SCRIPT', true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
\$_SESSION = {$sessionExport};
\$_GET = {$getExport};
chdir({$dir});
ob_start();
include {$base};
echo ob_get_clean();
";
    $tmpFile = tempnam(sys_get_temp_dir(), 'verify_crud_fk_search');
    file_put_contents($tmpFile, $code);
    $output = [];
    exec(PHP_BINARY . ' -d error_reporting=0 ' . escapeshellarg($tmpFile) . ' 2>&1', $output);
    unlink($tmpFile);

    return implode("\n", $output);
}

// Why: Disposable employee carries employment_status_id=1 (Active) for employees search regression.
$testUser = itm_script_test_employee_create($conn, $companyId, [
    'script_slug' => 'verify-crud-fk-search',
    'employment_status_id' => 1,
    'first_name' => 'FkSearch',
    'last_name' => 'Probe',
]);
if (!is_array($testUser)) {
    echo colorText('[FAIL] Unable to create disposable test employee.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$employeeId = (int)$testUser['id'];
itm_script_test_employee_register_teardown($conn, $employeeId);

$session = [
    'employee_id' => 1,
    'company_id' => $companyId,
    'username' => 'Admin',
    'role' => 'Admin',
];

// Employees: search Active should list disposable row (employment_statuses.name).
$employeesHtml = verify_crud_fk_label_run_isolated(
    ROOT_PATH . 'modules/employees/index.php',
    $session,
    ['search' => 'Active', 'sort' => 'id', 'dir' => 'DESC']
);
if (strpos($employeesHtml, (string)$employeeId) === false && stripos($employeesHtml, 'FkSearch') === false) {
    echo colorText('[FAIL] employees search=Active did not return Active-status disposable row.', 'fail') . $nl;
    $failed = true;
} else {
    echo colorText('[PASS] employees search=Active matches employment_statuses.name.', 'pass') . $nl;
}

// License management: search a known license type label when seeded.
$typeRes = mysqli_query($conn, "SELECT lt.name FROM license_types lt WHERE lt.company_id = {$companyId} ORDER BY lt.id ASC LIMIT 1");
$typeRow = $typeRes ? mysqli_fetch_assoc($typeRes) : null;
$typeName = trim((string)($typeRow['name'] ?? ''));
if ($typeName !== '') {
    $licenseHtml = verify_crud_fk_label_run_isolated(
        ROOT_PATH . 'modules/license_management/index.php',
        $session,
        ['search' => $typeName, 'sort' => 'id', 'dir' => 'DESC']
    );
    if (stripos($licenseHtml, htmlspecialchars($typeName, ENT_QUOTES, 'UTF-8')) === false && stripos($licenseHtml, $typeName) === false) {
        echo colorText('[FAIL] license_management search did not match license_types.name (' . $typeName . ').', 'fail') . $nl;
        $failed = true;
    } else {
        echo colorText('[PASS] license_management search matches license_types.name.', 'pass') . $nl;
    }
} else {
    echo colorText('[SKIP] license_types seed missing for company 1.', 'warn') . $nl;
}

// Helper unit probe: EXISTS fragment is emitted for employment_status_id.
$fkMap = [];
$fkSql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
          FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'employees'
            AND REFERENCED_TABLE_NAME IS NOT NULL";
$res = mysqli_query($conn, $fkSql);
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $fkMap[$row['COLUMN_NAME']] = $row;
}
$conds = itm_crud_fk_label_search_conditions($conn, 'employees', 'e', $fkMap, ['employment_status_id'], $companyId, '%Active%');
if (empty($conds) || stripos($conds[0], 'employee_statuses') === false) {
    echo colorText('[FAIL] itm_crud_fk_label_search_conditions did not build employee_statuses EXISTS.', 'fail') . $nl;
    $failed = true;
} else {
    echo colorText('[PASS] Shared FK label search helper builds EXISTS predicate.', 'pass') . $nl;
}


itm_script_output_end();
exit($failed ? 1 : 0);
