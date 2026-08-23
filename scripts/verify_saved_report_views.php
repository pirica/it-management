<?php
/**
 * Saved report views regression checks.
 * CLI: php scripts/verify_saved_report_views.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM'
<code>php scripts/verify_saved_report_views.php</code> — validates <code>saved_report_views</code> schema, filter whitelist, save/run API helpers.
ITM;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_saved_reports.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify Saved Report Views');
$nl = itm_script_output_nl();
$failures = 0;

function srv_fail($msg)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $msg, 'fail') . $nl;
}
function srv_pass($msg)
{
    global $nl;
    echo colorText('[PASS] ' . $msg, 'pass') . $nl;
}

$colRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'saved_report_views'");
$row = $colRes ? mysqli_fetch_assoc($colRes) : null;
if ((int)($row['c'] ?? 0) < 12) {
    srv_fail('saved_report_views table missing or incomplete');
} else {
    srv_pass('saved_report_views columns present');
}

$srCol = mysqli_query($conn, "SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'scheduled_reports' AND column_name = 'saved_view_id'");
$srRow = $srCol ? mysqli_fetch_assoc($srCol) : null;
if ((int)($srRow['c'] ?? 0) !== 1) {
    srv_fail('scheduled_reports.saved_view_id column missing');
} else {
    srv_pass('scheduled_reports.saved_view_id present');
}

$badFilter = itm_saved_reports_validate_filters('tickets', ['search' => 'ok', 'evil_field' => 'DROP']);
if (!$badFilter['ok'] || isset($badFilter['filters']['evil_field'])) {
    srv_fail('Filter whitelist should drop unknown keys');
} else {
    srv_pass('Filter whitelist strips unknown keys');
}

$badModule = itm_saved_reports_validate_filters('unknown_module', ['search' => 'x']);
if ($badModule['ok']) {
    srv_fail('Unsupported module should fail filter validation');
} else {
    srv_pass('Unsupported module rejected');
}

$companyId = 1;
$employeeId = 1;
$save = itm_saved_reports_save($conn, [
    'company_id' => $companyId,
    'employee_id' => $employeeId,
    'module_slug' => 'tickets',
    'name' => 'MBQA Saved View ' . bin2hex(random_bytes(4)),
    'shared_scope' => 'private',
    'filters' => ['search' => 'test', 'sort' => 'id', 'dir' => 'DESC'],
    'columns' => ['id', 'title', 'status_name'],
]);
if (empty($save['ok']) || (int)($save['id'] ?? 0) <= 0) {
    srv_fail('Save helper failed: ' . (string)($save['error'] ?? ''));
} else {
    srv_pass('Save helper inserted row #' . (int) $save['id']);
    $viewId = (int) $save['id'];
    $row = itm_saved_reports_fetch_by_id($conn, $viewId, $companyId);
    if (!$row || !itm_saved_reports_can_view($conn, $row, $employeeId, $companyId)) {
        srv_fail('Owner cannot view saved row');
    } else {
        srv_pass('Owner access check');
    }
    $run = itm_saved_reports_run_query($conn, $companyId, $row, ['limit' => 5, 'offset' => 0]);
    if (empty($run['ok']) || !isset($run['rows']) || !is_array($run['rows'])) {
        srv_fail('Run query failed');
    } else {
        srv_pass('Run query returned rows array (total=' . (int)($run['total'] ?? 0) . ')');
    }
    $slug = itm_saved_reports_scheduled_slug($viewId);
    if (itm_saved_reports_parse_scheduled_slug($slug) !== $viewId) {
        srv_fail('Scheduled slug round-trip failed');
    } else {
        srv_pass('Scheduled slug helpers');
    }
    itm_saved_reports_soft_delete($conn, $viewId, $employeeId, $companyId);
}

itm_script_output_end($failures === 0 ? 0 : 1);
