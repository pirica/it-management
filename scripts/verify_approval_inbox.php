<?php
/**
 * Regression checks for unified approval inbox helpers and table.
 *
 * Usage: php scripts/verify_approval_inbox.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_approval_inbox.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_approval_inbox.php</code>, <code>modules/approval_inbox/</code>, or source-module sync wiring.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Approval Inbox Verification');
$nl = itm_script_output_nl();

$failures = 0;

function ai_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function ai_verify_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    ai_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

foreach (['itm_approval_inbox_adapter_slugs', 'itm_approval_inbox_map_source_status', 'itm_approval_inbox_upsert', 'itm_approval_inbox_fetch_for_assignee', 'itm_approval_inbox_count_rows', 'itm_approval_inbox_decide', 'itm_approval_inbox_sync_module_record'] as $fn) {
    if (!function_exists($fn)) {
        ai_verify_fail("Missing helper {$fn}()");
    } else {
        ai_verify_pass("Helper {$fn}() loaded");
    }
}

$slugs = itm_approval_inbox_adapter_slugs();
$expectedSlugs = ['request_password', 'employee_onboarding_requests', 'approvals', 'forecast_revisions'];
sort($slugs);
sort($expectedSlugs);
if ($slugs !== $expectedSlugs) {
    ai_verify_fail('Adapter slugs mismatch: ' . implode(', ', $slugs));
} else {
    ai_verify_pass('Adapter registry slugs');
}

if (!function_exists('itm_approval_inbox_count_for_assignee')) {
    ai_verify_fail('Missing helper itm_approval_inbox_count_for_assignee()');
} else {
    ai_verify_pass('Helper itm_approval_inbox_count_for_assignee() loaded');
}

if (itm_approval_inbox_map_source_status('Waiting') !== 'pending' || itm_approval_inbox_map_source_status('Approved') !== 'approved' || itm_approval_inbox_map_source_status('Declined') !== 'rejected') {
    ai_verify_fail('map_source_status mapping failed');
} else {
    ai_verify_pass('map_source_status mappings');
}

$res = mysqli_query($conn, "SHOW TABLES LIKE 'approval_inbox_items'");
if (!$res || mysqli_num_rows($res) === 0) {
    ai_verify_fail('Missing table approval_inbox_items');
} else {
    ai_verify_pass('Table approval_inbox_items exists');
}

$companyId = 1;
$employeeRes = mysqli_query($conn, 'SELECT id FROM employees WHERE company_id = ' . (int)$companyId . ' AND active = 1 ORDER BY id ASC LIMIT 1');
$employeeId = 0;
if ($employeeRes && ($row = mysqli_fetch_assoc($employeeRes))) {
    $employeeId = (int)$row['id'];
}
if ($employeeId <= 0) {
    ai_verify_fail('Need at least one active employee in company 1');
    itm_script_output_end();
    exit(1);
}

$probeRecordId = 999999;
$stage = 'verify';
$title = 'MBQA-approval-inbox-' . bin2hex(random_bytes(4));
if (!itm_approval_inbox_upsert($conn, [
    'company_id' => $companyId,
    'module_slug' => 'request_password',
    'record_id' => $probeRecordId,
    'approval_stage' => $stage,
    'title' => $title,
    'requester_employee_id' => $employeeId,
    'assignee_employee_id' => $employeeId,
    'status' => 'pending',
    'action_url' => 'modules/request_password/view.php?id=' . $probeRecordId,
])) {
    ai_verify_fail('Upsert probe row failed');
} else {
    ai_verify_pass('Upsert probe row');
}

$rows = itm_approval_inbox_fetch_for_assignee($conn, $companyId, $employeeId, [
    'mine_only' => true,
    'search' => $title,
    'limit' => 5,
]);
$found = false;
foreach ($rows as $row) {
    if ((string)($row['title'] ?? '') === $title) {
        $found = true;
        break;
    }
}
if (!$found) {
    ai_verify_fail('Fetch probe row not found');
} else {
    ai_verify_pass('Fetch probe row');
}

$count = itm_approval_inbox_count_rows($conn, $companyId, $employeeId, [
    'mine_only' => true,
    'search' => $title,
]);
if ($count < 1) {
    ai_verify_fail('Count probe row failed');
} else {
    ai_verify_pass('Count probe row');
}

mysqli_query($conn, "DELETE FROM approval_inbox_items WHERE company_id = {$companyId} AND module_slug = 'request_password' AND record_id = {$probeRecordId} AND approval_stage = '{$stage}' LIMIT 1");
ai_verify_pass('Cleanup probe row');

if ($failures > 0) {
    echo $nl . itm_script_format_status_line('[FAIL] ' . $failures . ' check(s) failed') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . itm_script_format_status_line('[PASS] All approval inbox checks passed') . $nl;
itm_script_output_end();
exit(0);
