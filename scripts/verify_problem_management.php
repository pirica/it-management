<?php
/**
 * Regression checks for Problem Management and Known Error Database.
 *
 * Usage: php scripts/verify_problem_management.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_problem_management.php</code> — exit <code>1</code> on failure. Run when changing <code>includes/itm_problem_management.php</code>, <code>modules/problems/</code>, ticket linking, or chatbot known-error search.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Problem Management Verification');
$nl = itm_script_output_nl();
$failures = 0;

function pm_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function pm_verify_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    pm_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

require_once ROOT_PATH . 'includes/itm_problem_management.php';
require_once ROOT_PATH . 'includes/itm_automation_rules.php';

/**
 * Apply problem-table audit triggers from db/03_triggers.sql when missing after DDL migration.
 */
function pm_verify_apply_problem_triggers_if_missing($conn)
{
    if (!($conn instanceof mysqli)) {
        return;
    }
    $needApply = false;
    foreach (['problems', 'problem_ticket_links', 'known_errors'] as $table) {
        $tEsc = mysqli_real_escape_string($conn, $table);
        $tRes = mysqli_query(
            $conn,
            "SELECT COUNT(*) AS c FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND event_object_table = '{$tEsc}'"
        );
        $tCount = 0;
        if ($tRes && ($tRow = mysqli_fetch_assoc($tRes))) {
            $tCount = (int)($tRow['c'] ?? 0);
        }
        if ($tCount < 3) {
            $needApply = true;
            break;
        }
    }
    if (!$needApply) {
        return;
    }
    $bundlePath = ROOT_PATH . 'db/03_triggers.sql';
    $bundle = is_file($bundlePath) ? (string)file_get_contents($bundlePath) : '';
    if ($bundle === '') {
        return;
    }
    foreach (['problems', 'problem_ticket_links', 'known_errors'] as $table) {
        foreach (['insert', 'update', 'delete'] as $action) {
            $triggerName = 'trg_' . $table . '_audit_' . $action;
            $pattern = '/DROP TRIGGER IF EXISTS `' . preg_quote($triggerName, '/') . '`\$\$\s*(CREATE TRIGGER.*?END)\$\$/s';
            if (!preg_match($pattern, $bundle, $matches)) {
                continue;
            }
            $createSql = trim($matches[1]);
            if ($createSql === '') {
                continue;
            }
            mysqli_query($conn, 'DROP TRIGGER IF EXISTS `' . $triggerName . '`');
            mysqli_query($conn, $createSql);
        }
    }
}

pm_verify_apply_problem_triggers_if_missing($conn);

foreach (['problems', 'problem_ticket_links', 'known_errors'] as $table) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    if (!$res || mysqli_num_rows($res) === 0) {
        pm_verify_fail("Missing table {$table} — apply db/migrations/problem_management.sql");
    } else {
        pm_verify_pass("Table {$table} exists");
    }
}

foreach (['problems', 'problem_ticket_links', 'known_errors'] as $table) {
    $tEsc = mysqli_real_escape_string($conn, $table);
    $tRes = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND event_object_table = '{$tEsc}'"
    );
    $tCount = 0;
    if ($tRes && ($tRow = mysqli_fetch_assoc($tRes))) {
        $tCount = (int)($tRow['c'] ?? 0);
    }
    if ($tCount < 3) {
        pm_verify_fail("Expected 3 audit triggers on {$table}, found {$tCount} — re-import db/03_triggers.sql after migration");
    }
}
pm_verify_pass('Audit triggers present for problem tables (9 total)');

$automationSlugs = itm_automation_rules_trigger_slugs();
foreach (['problem.created', 'problem.status_changed', 'known_error.published'] as $slug) {
    if (!in_array($slug, $automationSlugs, true)) {
        pm_verify_fail("Automation trigger missing: {$slug}");
    }
}
pm_verify_pass('Automation trigger slugs registered');

if (!function_exists('itm_webhook_queue_event_types')) {
    require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
}
$webhookTypes = itm_webhook_queue_event_types();
foreach (['problem.created', 'problem.status_changed', 'known_error.published'] as $evt) {
    if (!in_array($evt, $webhookTypes, true)) {
        pm_verify_fail("Webhook event missing: {$evt}");
    }
}
pm_verify_pass('Webhook event types registered');

$testEmp = itm_script_test_employee_create($conn, 1, ['script_slug' => 'verify-problem-mgmt']);
if (!$testEmp || empty($testEmp['id'])) {
    pm_verify_fail('Could not create disposable test employee.');
    itm_script_output_end();
    exit(1);
}
$actorId = (int)$testEmp['id'];
itm_script_test_employee_register_teardown($conn, $testEmp);

$createResult = itm_problem_create($conn, 1, [
    'title' => 'PM Verify Network Outage ' . bin2hex(random_bytes(4)),
    'description' => 'Investigating recurring network outage incidents.',
    'status' => 'investigating',
    'owner_employee_id' => $actorId,
], $actorId);
if (empty($createResult['ok']) || (int)($createResult['id'] ?? 0) <= 0) {
    pm_verify_fail('itm_problem_create failed.');
    itm_script_output_end();
    exit(1);
}
$problemId = (int)$createResult['id'];
pm_verify_pass('Created disposable problem #' . $problemId);

$ticketTitle = 'PM Verify Ticket ' . bin2hex(random_bytes(3));
$tStmt = mysqli_prepare($conn, 'INSERT INTO tickets (company_id, title, description, created_by_employee_id, active) VALUES (1, ?, ?, ?, 1)');
$ticketIds = [];
if ($tStmt) {
    $desc = 'Network outage symptom for problem link test.';
    mysqli_stmt_bind_param($tStmt, 'ssi', $ticketTitle, $desc, $actorId);
    mysqli_stmt_execute($tStmt);
    $ticketIds[] = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($tStmt);
    $tStmt2 = mysqli_prepare($conn, 'INSERT INTO tickets (company_id, title, description, created_by_employee_id, active) VALUES (1, ?, ?, ?, 1)');
    if ($tStmt2) {
        $title2 = $ticketTitle . ' B';
        mysqli_stmt_bind_param($tStmt2, 'ssi', $title2, $desc, $actorId);
        mysqli_stmt_execute($tStmt2);
        $ticketIds[] = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($tStmt2);
    }
}
if (count($ticketIds) < 2) {
    pm_verify_fail('Could not seed disposable tickets.');
} else {
    foreach ($ticketIds as $tid) {
        $link = itm_problem_link_ticket($conn, 1, $problemId, (int)$tid, $actorId);
        if (empty($link['ok'])) {
            pm_verify_fail('Link ticket failed for #' . $tid);
        }
    }
    pm_verify_pass('Linked two incident tickets');
    $incidents = itm_problem_list_incidents($conn, 1, $problemId);
    if (count($incidents) < 2) {
        pm_verify_fail('Incident list count expected >= 2');
    } else {
        pm_verify_pass('Incident list returns linked tickets with labels');
    }
}

$keResult = itm_known_error_upsert($conn, 1, $problemId, [
    'title' => 'Network outage workaround',
    'workaround' => 'Restart core switch and flush DNS on affected workstations.',
    'symptom_keywords' => 'network,outage,dns',
], $actorId, false);
if (empty($keResult['ok'])) {
    pm_verify_fail('Known error upsert failed.');
} else {
    pm_verify_pass('Published known error on problem');
}

$suggestions = itm_known_error_suggest_for_ticket($conn, 1, 'Network outage', 'DNS failure on workstations', 5);
$matched = false;
foreach ($suggestions as $sug) {
    if ((int)($sug['problem_id'] ?? 0) === $problemId) {
        $matched = true;
        break;
    }
}
if (!$matched) {
    pm_verify_fail('Suggest for ticket did not return seeded known error');
} else {
    pm_verify_pass('Ticket suggest matches known error tokens');
}

$chatSuggestions = itm_known_error_search_for_query($conn, 1, 'network outage dns', 3);
if (empty($chatSuggestions)) {
    pm_verify_fail('Chat known-error search returned no rows');
} else {
    pm_verify_pass('Chat known-error search tenant-scoped helper');
}

$kbResult = itm_known_error_upsert($conn, 1, $problemId, [
    'title' => 'Network outage workaround',
    'workaround' => 'Restart core switch and flush DNS on affected workstations.',
    'symptom_keywords' => 'network,outage,dns',
], $actorId, true);
if (empty($kbResult['ok']) || (int)($kbResult['knowledge_base_id'] ?? 0) <= 0) {
    pm_verify_fail('KB publish during known error upsert failed.');
} else {
    pm_verify_pass('KB article linked (id ' . (int)$kbResult['knowledge_base_id'] . ')');
}

if (!empty($ticketIds[0])) {
    $unlink = itm_problem_unlink_ticket($conn, 1, $problemId, (int)$ticketIds[0], $actorId);
    if (empty($unlink['ok'])) {
        pm_verify_fail('Unlink ticket failed.');
    } else {
        pm_verify_pass('Unlinked one incident ticket');
    }
}

itm_problem_soft_delete($conn, 1, $problemId, $actorId);
pm_verify_pass('Soft-deleted disposable problem');

foreach ($ticketIds as $tid) {
    if ($tid > 0) {
        mysqli_query($conn, 'DELETE FROM tickets WHERE id = ' . (int)$tid . ' AND company_id = 1');
    }
}

if ($failures > 0) {
    echo itm_script_format_status_line($failures . ' failure(s).') . $nl;
    itm_script_output_end();
    exit(1);
}

echo itm_script_format_status_line('All problem management checks passed.') . $nl;
itm_script_output_end();
exit(0);
