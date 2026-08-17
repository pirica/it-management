<?php
/**
 * Workflow automation rules regression checks.
 *
 * CLI: php scripts/verify_automation_rules.php
 * Browser: scripts/verify_automation_rules.php?run=1
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_automation_rules.php</code> — seeds a rule, runs it by id via <code>itm_automation_rules_run_rule()</code>, and checks <code>automation_rule_runs</code> for <code>success</code>.
Run when changing <code>includes/itm_automation_rules.php</code>, <code>modules/automation_rules/</code>, or automation tables in <code>db/</code>.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Automation Rules Verification');

$nl = itm_script_output_nl();
$failures = 0;

function ar_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function ar_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

function ar_verify_ensure_fixtures($conn)
{
    $companyId = 1;
    mysqli_query($conn, "INSERT IGNORE INTO companies (id, company, active) VALUES ({$companyId}, 'TechCorp Global', 1)");

    $empRes = mysqli_query($conn, "SELECT id FROM employees WHERE company_id = {$companyId} ORDER BY id ASC LIMIT 1");
    $empRow = $empRes ? mysqli_fetch_assoc($empRes) : null;
    $employeeId = (int)($empRow['id'] ?? 0);
    if ($employeeId <= 0) {
        $statusEmpRes = mysqli_query($conn, "SELECT id FROM employee_statuses WHERE company_id = {$companyId} ORDER BY id ASC LIMIT 1");
        $statusEmpRow = $statusEmpRes ? mysqli_fetch_assoc($statusEmpRes) : null;
        $employmentStatusId = (int)($statusEmpRow['id'] ?? 0);
        if ($employmentStatusId <= 0) {
            $insertEmpStatus = "INSERT INTO employee_statuses (company_id, name, active) VALUES ({$companyId}, 'Active', 1)";
            if (itm_run_query($conn, $insertEmpStatus)) {
                $employmentStatusId = (int)mysqli_insert_id($conn);
            }
        }
        if ($employmentStatusId > 0) {
            $insertEmp = "INSERT INTO employees (company_id, first_name, last_name, username, work_email, employment_status_id, active)
                          VALUES ({$companyId}, 'Admin', 'User', 'Admin', 'admin@example.com', {$employmentStatusId}, 1)";
            if (itm_run_query($conn, $insertEmp)) {
                $employeeId = (int)mysqli_insert_id($conn);
            }
        }
    }

    $statusRes = mysqli_query($conn, "SELECT id FROM ticket_statuses WHERE company_id = {$companyId} ORDER BY id ASC LIMIT 1");
    $statusRow = $statusRes ? mysqli_fetch_assoc($statusRes) : null;
    $statusId = (int)($statusRow['id'] ?? 0);
    if ($statusId <= 0) {
        $insertStatus = "INSERT INTO ticket_statuses (company_id, name, active) VALUES ({$companyId}, 'Open', 1)";
        if (itm_run_query($conn, $insertStatus)) {
            $statusId = (int)mysqli_insert_id($conn);
        }
    }

    $ticketRes = mysqli_query($conn, "SELECT id FROM tickets WHERE company_id = {$companyId} AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");
    $ticketRow = $ticketRes ? mysqli_fetch_assoc($ticketRes) : null;
    $ticketId = (int)($ticketRow['id'] ?? 0);
    if ($ticketId <= 0 && $employeeId > 0) {
        $statusSql = $statusId > 0 ? (string)$statusId : 'NULL';
        $insertTicket = "INSERT INTO tickets (company_id, title, description, status_id, created_by_employee_id, active)
                         VALUES ({$companyId}, 'Automation verify ticket', 'Fixture', {$statusSql}, {$employeeId}, 1)";
        if (itm_run_query($conn, $insertTicket)) {
            $ticketId = (int)mysqli_insert_id($conn);
        }
    }

    return [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'ticket_id' => $ticketId,
    ];
}

$tables = ['automation_rules', 'automation_rule_runs'];
foreach ($tables as $table) {
    $sql = "SELECT COUNT(*) AS c FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if ((int)($row['c'] ?? 0) < 1) {
        ar_verify_fail("Table {$table} missing — import schema or apply migration");
    } else {
        ar_verify_pass("Table {$table} exists");
    }
}

foreach ($tables as $table) {
    $sql = "SELECT COUNT(*) AS c FROM information_schema.triggers
            WHERE trigger_schema = DATABASE() AND event_object_table = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    $count = (int)($row['c'] ?? 0);
    if ($count < 3) {
        ar_verify_fail("Missing audit triggers for {$table} (expected 3, found {$count})");
    } else {
        ar_verify_pass("Audit triggers present for {$table}");
    }
}

$fixtures = ar_verify_ensure_fixtures($conn);
$companyId = (int)$fixtures['company_id'];
$adminId = (int)$fixtures['employee_id'];
$ticketId = (int)$fixtures['ticket_id'];

if ($adminId <= 0) {
    ar_verify_fail('Could not resolve fixture employee for company 1');
}
if ($ticketId <= 0) {
    ar_verify_fail('Could not create fixture ticket for dispatch test');
    itm_script_output_end(1);
    exit(1);
}

$ruleName = 'MBQA-Automation-Verify-' . date('YmdHis');
$conditionsJson = '[]';
$actionsJson = json_encode([
    [
        'type' => 'notify_employee',
        'employee_id' => $adminId,
        'title' => 'Automation verify',
        'body' => 'Verify script dispatch test',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$nameEsc = mysqli_real_escape_string($conn, $ruleName);
$conditionsEsc = mysqli_real_escape_string($conn, $conditionsJson);
$actionsEsc = mysqli_real_escape_string($conn, $actionsJson);
$insertSql = "INSERT INTO automation_rules (company_id, name, trigger_slug, conditions_json, actions_json, enabled, active, created_by)
              VALUES ({$companyId}, '{$nameEsc}', 'ticket.created', '{$conditionsEsc}', '{$actionsEsc}', 1, 1, {$adminId})";
if (!itm_run_query($conn, $insertSql)) {
    ar_verify_fail('Could not insert test automation rule');
    itm_script_output_end(1);
    exit(1);
}
$ruleId = (int)mysqli_insert_id($conn);
if ($ruleId <= 0) {
    $lookup = mysqli_query($conn, "SELECT id FROM automation_rules WHERE company_id = {$companyId} AND name = '{$nameEsc}' ORDER BY id DESC LIMIT 1");
    $lookupRow = $lookup ? mysqli_fetch_assoc($lookup) : null;
    $ruleId = (int)($lookupRow['id'] ?? 0);
}
if ($ruleId <= 0) {
    ar_verify_fail('Could not resolve test automation rule id after insert');
    itm_script_output_end(1);
    exit(1);
}
ar_verify_pass('Inserted test rule id ' . $ruleId);

$context = itm_automation_rules_build_ticket_context($conn, $companyId, $ticketId, ['automation_depth' => 0]);
if (!itm_automation_rules_run_rule($conn, $companyId, $ruleId, $context)) {
    ar_verify_fail('Could not execute test automation rule id ' . $ruleId);
}

$runSql = "SELECT status, message FROM automation_rule_runs WHERE company_id = {$companyId} AND rule_id = {$ruleId} ORDER BY id DESC LIMIT 1";
$runRes = mysqli_query($conn, $runSql);
$runRow = $runRes ? mysqli_fetch_assoc($runRes) : null;
$runStatus = (string)($runRow['status'] ?? '');
if ($runStatus !== 'success') {
    $runMessage = trim((string)($runRow['message'] ?? ''));
    $detail = $runStatus === '' ? 'no run row logged' : $runStatus;
    if ($runMessage !== '') {
        $detail .= ' — ' . $runMessage;
    }
    ar_verify_fail('Expected automation_rule_runs status success, got ' . $detail);
} else {
    ar_verify_pass('Dispatch logged success run for rule ' . $ruleId);
}

mysqli_query($conn, "DELETE FROM automation_rule_runs WHERE rule_id = {$ruleId}");
mysqli_query($conn, "DELETE FROM automation_rules WHERE id = {$ruleId}");

$triggerSlugs = itm_automation_rules_trigger_slugs();
foreach (['alert.created', 'equipment.certificate_expiring', 'expense.created', 'ticket.priority_changed', 'equipment.disposed'] as $slug) {
    if (!in_array($slug, $triggerSlugs, true)) {
        ar_verify_fail("Trigger slug missing: {$slug}");
    }
}
if ($failures === 0) {
    ar_verify_pass('Extended trigger slugs registered');
}

$helperSource = (string) file_get_contents(dirname(__DIR__) . '/includes/itm_automation_rules.php');
foreach (['assign_ticket', 'set_ticket_priority', 'emit_webhook', 'create_ticket'] as $actionType) {
    if (strpos($helperSource, "'{$actionType}'") === false && strpos($helperSource, "\"{$actionType}\"") === false) {
        ar_verify_fail("Action handler missing: {$actionType}");
    }
}
if ($failures === 0) {
    ar_verify_pass('Extended action handlers present in itm_automation_rules.php');
}

foreach (['contains', 'not_equals', 'not_empty'] as $conditionOp) {
    if (strpos($helperSource, "'{$conditionOp}'") === false) {
        ar_verify_fail("Condition operator missing: {$conditionOp}");
    }
}
if ($failures === 0) {
    ar_verify_pass('Extended condition operators present');
}

if (strpos($helperSource, 'function itm_automation_rules_create_ticket') === false) {
    ar_verify_fail('create_ticket helper missing');
} else {
    ar_verify_pass('create_ticket helper present');
}

$conditions = [
    ['field' => 'title', 'op' => 'contains', 'value' => 'Auto'],
    ['field' => 'status_name', 'op' => 'not_empty', 'value' => ''],
];
$matchContext = ['title' => 'Automation verify', 'status_name' => 'Open'];
if (!itm_automation_rules_conditions_match($conditions, $matchContext)) {
    ar_verify_fail('Extended conditions should match sample context');
} else {
    ar_verify_pass('Extended conditions match sample context');
}

itm_script_output_end($failures > 0 ? 1 : 0);
