<?php
/**
 * Regression checks for IT Change Management (change_requests ITSM pack).
 *
 * Usage: php scripts/verify_change_requests.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_change_requests.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_change_requests.php</code>, CAB workflow, calendar feed, reminders, or automation/webhook events.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Change Requests ITSM Verification');
$nl = itm_script_output_nl();
$failures = 0;

function cr_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function cr_verify_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    cr_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

require_once ROOT_PATH . 'includes/itm_change_requests.php';
require_once ROOT_PATH . 'includes/itm_automation_rules.php';
require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
require_once ROOT_PATH . 'includes/itm_approval_inbox.php';
require_once ROOT_PATH . 'includes/itm_sample_data_seed.php';

$companyId = 1;
$employeeId = 1;
if (function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
    $employeeId = (int)itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);
}
if ($employeeId <= 0) {
    $employeeId = 1;
}

foreach (['change_type', 'risk_level', 'rollback_plan', 'ticket_id', 'reminder_sent_at'] as $column) {
    $esc = mysqli_real_escape_string($conn, $column);
    $colRes = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'change_requests' AND column_name = '{$esc}'"
    );
    $colRow = $colRes ? mysqli_fetch_assoc($colRes) : null;
    if ((int)($colRow['c'] ?? 0) < 1) {
        cr_verify_fail('Missing change_requests.' . $column);
    }
}

foreach (['change_request_cab_members', 'change_request_approvals', 'change_request_settings'] as $table) {
    $tEsc = mysqli_real_escape_string($conn, $table);
    $tRes = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$tEsc}'"
    );
    $tRow = $tRes ? mysqli_fetch_assoc($tRes) : null;
    if ((int)($tRow['c'] ?? 0) < 1) {
        cr_verify_fail('Missing table ' . $table);
    } else {
        cr_verify_pass('Table ' . $table . ' present');
    }
}

$automationTriggers = itm_automation_rules_trigger_slugs();
foreach (['change.submitted', 'change.approved', 'change.rejected', 'change.status_changed', 'change.implemented'] as $trigger) {
    if (!in_array($trigger, $automationTriggers, true)) {
        cr_verify_fail('Automation trigger missing: ' . $trigger);
    }
}
cr_verify_pass('Automation change.* triggers registered');

$webhookEvents = itm_webhook_queue_event_types();
foreach (['change.submitted', 'change.approved', 'change.implemented'] as $event) {
    if (!in_array($event, $webhookEvents, true)) {
        cr_verify_fail('Webhook event missing: ' . $event);
    }
}
cr_verify_pass('Webhook change.* events registered');

$adapters = itm_approval_inbox_adapter_slugs();
if (!in_array('change_requests', $adapters, true)) {
    cr_verify_fail('Approval inbox adapter missing change_requests');
} else {
    cr_verify_pass('Approval inbox adapter includes change_requests');
}

$ciStmt = mysqli_prepare(
    $conn,
    'SELECT id FROM configuration_items WHERE company_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1'
);
$sourceCiId = 0;
if ($ciStmt) {
    mysqli_stmt_bind_param($ciStmt, 'i', $companyId);
    mysqli_stmt_execute($ciStmt);
    $ciRes = mysqli_stmt_get_result($ciStmt);
    $ciRow = $ciRes ? mysqli_fetch_assoc($ciRes) : null;
    $sourceCiId = (int)($ciRow['id'] ?? 0);
    mysqli_stmt_close($ciStmt);
}
if ($sourceCiId <= 0) {
    cr_verify_fail('No configuration item for company 1 — cannot exercise save workflow.');
    itm_script_output_end();
    exit($failures > 0 ? 1 : 0);
}

itm_change_request_ensure_settings_row($conn, $companyId, $employeeId);
itm_change_request_ensure_cab_members($conn, $companyId, $employeeId);
$cabIds = itm_change_request_list_cab_member_ids($conn, $companyId);
if ($cabIds === []) {
    cr_verify_fail('No CAB members resolved for company 1.');
} else {
    cr_verify_pass('CAB members resolved (' . count($cabIds) . ')');
}

$uniqueTitle = 'MBQA change verify ' . date('YmdHis');
$save = itm_change_request_save($conn, $companyId, $employeeId, 0, [
    'title' => $uniqueTitle,
    'description' => 'Verify script disposable change',
    'change_type' => 'standard',
    'risk_level' => 'high',
    'rollback_plan' => 'Revert package and restart service.',
    'ticket_id' => 0,
    'status' => 'submitted',
    'source_configuration_item_id' => $sourceCiId,
    'scheduled_start' => date('d/M/Y', strtotime('+14 days')),
    'scheduled_end' => date('d/M/Y', strtotime('+14 days')),
], [$sourceCiId]);

if (empty($save['ok'])) {
    cr_verify_fail('Save failed: ' . (string)($save['error'] ?? ''));
    itm_script_output_end();
    exit(1);
}

$changeId = (int)($save['id'] ?? 0);
if ($changeId <= 0) {
    cr_verify_fail('Save did not return change id.');
    itm_script_output_end();
    exit(1);
}
cr_verify_pass('Created submitted change request id=' . $changeId);

$row = itm_change_request_fetch_row($conn, $companyId, $changeId);
if (!$row || (string)($row['status'] ?? '') !== 'submitted') {
    cr_verify_fail('Submitted change status not persisted.');
} else {
    cr_verify_pass('Submitted status persisted');
}

$approvals = itm_change_request_list_approval_rows($conn, $companyId, $changeId);
if ($approvals === []) {
    cr_verify_fail('CAB approval rows missing after submit.');
} else {
    cr_verify_pass('CAB approval rows created (' . count($approvals) . ')');
}

$calendarRows = itm_change_request_list_calendar_rows(
    $conn,
    $companyId,
    date('Y-m-d'),
    date('Y-m-d', strtotime('+30 days'))
);
$foundCalendar = false;
foreach ($calendarRows as $calRow) {
    if ((int)($calRow['id'] ?? 0) === $changeId) {
        $foundCalendar = true;
        break;
    }
}
if (!$foundCalendar) {
    cr_verify_fail('Calendar feed did not return the scheduled change.');
} else {
    cr_verify_pass('Calendar feed includes scheduled change');
}

$approverId = (int)($cabIds[0] ?? 0);
if ($approverId > 0) {
  if ($row['change_type'] === 'emergency') {
    // emergency needs 1 approval
  }
  $required = itm_change_request_required_approval_count($conn, $companyId, (string)($row['change_type'] ?? 'standard'), count($approvals));
  $approvedCount = 0;
  foreach ($approvals as $approval) {
    $aid = (int)($approval['approver_employee_id'] ?? 0);
    if ($aid <= 0) {
      continue;
    }
    if (!itm_change_request_apply_cab_decision($conn, $companyId, $changeId, $aid, 'approve')) {
      cr_verify_fail('CAB approve failed for employee ' . $aid);
      break;
    }
    $approvedCount++;
    if ($approvedCount >= $required) {
      break;
    }
  }
  $after = itm_change_request_fetch_row($conn, $companyId, $changeId);
  if (!$after || (string)($after['status'] ?? '') !== 'approved') {
    cr_verify_fail('CAB quorum did not promote change to approved.');
  } else {
    cr_verify_pass('CAB quorum promoted change to approved');
  }
}

$soft = mysqli_prepare(
    $conn,
    'UPDATE change_requests SET active = 0, deleted_by = ?, deleted_at = NOW() WHERE id = ? AND company_id = ? LIMIT 1'
);
if ($soft) {
    mysqli_stmt_bind_param($soft, 'iii', $employeeId, $changeId, $companyId);
    mysqli_stmt_execute($soft);
    mysqli_stmt_close($soft);
}

itm_script_output_end();
exit($failures > 0 ? 1 : 0);
