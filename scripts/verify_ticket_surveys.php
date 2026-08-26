<?php
/**
 * Ticket questionnaires / surveys regression checks.
 *
 * Usage: php scripts/verify_ticket_surveys.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM'
<code>php scripts/verify_ticket_surveys.php</code> — five-company seeds, issue/submit, activity, webhook/automation, merge tag, merge cancel, stats. See <code>docs/TICKET_SURVEYS.md</code>.
ITM;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_ticket_survey.php';
require_once ROOT_PATH . 'includes/itm_saved_reports.php';
require_once ROOT_PATH . 'includes/itm_tickets_list_query.php';
require_once ROOT_PATH . 'includes/itm_automation_rules.php';
require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify Ticket Surveys');
$nl = itm_script_output_nl();
$failures = 0;

function ts_fail($msg)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $msg, 'fail') . $nl;
}

function ts_pass($msg)
{
    global $nl;
    echo colorText('[PASS] ' . $msg, 'pass') . $nl;
}

function ts_activity_count($conn, $companyId, $ticketId, $eventType)
{
    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM ticket_activity WHERE company_id = ? AND ticket_id = ? AND event_type = ?'
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'iis', $companyId, $ticketId, $eventType);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int)($row['c'] ?? 0);
}

function ts_pending_survey_count($conn, $companyId, $ticketId)
{
    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM ticket_surveys WHERE company_id = ? AND ticket_id = ? AND completed_at IS NULL'
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $ticketId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int)($row['c'] ?? 0);
}

if (!($conn instanceof mysqli)) {
    ts_fail('Database connection unavailable.');
    itm_script_output_end(1);
    exit(1);
}

$requiredFiles = [
    'includes/itm_ticket_survey.php',
    'ticket-survey.php',
    'modules/ticket_questionnaires/index.php',
    'modules/ticket_surveys/index.php',
    'modules/ticket_survey_dashboard/index.php',
];
foreach ($requiredFiles as $path) {
    is_file(ROOT_PATH . $path) ? ts_pass('File: ' . $path) : ts_fail('Missing: ' . $path);
}

foreach (
    ['ticket_questionnaires', 'ticket_questionnaire_questions', 'ticket_surveys', 'ticket_survey_answers'] as $table
) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    if ((int)($row['c'] ?? 0) < 1) {
        ts_fail('Table missing: ' . $table);
    } else {
        ts_pass('Table exists: ' . $table);
    }
}

$schemaReady = true;
foreach (['ticket_questionnaires', 'ticket_surveys'] as $probeTable) {
    $probe = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    if (!$probe) {
        $schemaReady = false;
        break;
    }
    mysqli_stmt_bind_param($probe, 's', $probeTable);
    mysqli_stmt_execute($probe);
    $probeRes = mysqli_stmt_get_result($probe);
    $probeRow = $probeRes ? mysqli_fetch_assoc($probeRes) : null;
    mysqli_stmt_close($probe);
    if ((int)($probeRow['c'] ?? 0) < 1) {
        $schemaReady = false;
        break;
    }
}

if (!$schemaReady) {
    echo colorText('Survey tables missing — re-import db/01_schema.sql or apply migrations before runtime checks.', 'fail') . $nl;
    itm_script_output_end(1);
    exit(1);
}

// Five-company seed probes (questionnaires + demo surveys).
$seedExpectations = [
    1 => ['ticket_id' => 6, 'completed' => true],
    2 => ['ticket_id' => 7, 'completed' => true],
    3 => ['ticket_id' => 8, 'completed' => true],
    4 => ['ticket_id' => 9, 'completed' => false],
    5 => ['ticket_id' => 10, 'completed' => false],
];
foreach ($seedExpectations as $companyId => $expect) {
    $qStmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM ticket_questionnaires WHERE company_id = ? AND deleted_at IS NULL AND active = 1'
    );
    if (!$qStmt) {
        ts_fail('Company ' . $companyId . ' questionnaire probe failed (import db/ bundle?)');
        continue;
    }
    mysqli_stmt_bind_param($qStmt, 'i', $companyId);
    mysqli_stmt_execute($qStmt);
    $qRes = mysqli_stmt_get_result($qStmt);
    $qRow = $qRes ? mysqli_fetch_assoc($qRes) : null;
    mysqli_stmt_close($qStmt);
    $qCount = (int)($qRow['c'] ?? 0);
    if ($qCount < 1) {
        ts_fail('Company ' . $companyId . ' missing active questionnaire seed');
        continue;
    }
    ts_pass('Company ' . $companyId . ' questionnaires seeded (' . $qCount . ')');

    $ticketId = (int)$expect['ticket_id'];
    $survey = itm_ticket_survey_get_latest_for_ticket($conn, $companyId, $ticketId);
    if (!is_array($survey)) {
        ts_fail('Company ' . $companyId . ' missing seed survey on ticket ' . $ticketId);
        continue;
    }
    $isCompleted = !empty($survey['completed_at']);
    if ($expect['completed'] && !$isCompleted) {
        ts_fail('Company ' . $companyId . ' expected completed survey on ticket ' . $ticketId);
    } elseif (!$expect['completed'] && $isCompleted) {
        ts_fail('Company ' . $companyId . ' expected pending survey on ticket ' . $ticketId);
    } else {
        ts_pass('Company ' . $companyId . ' seed survey state OK (ticket ' . $ticketId . ')');
    }
}

$companyId = 1;
$employee = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-ticket-surveys']);
if (!$employee) {
    ts_fail('Could not create disposable test employee');
    itm_script_output_end(1);
    exit(1);
}
$employeeId = (int)$employee['id'];
itm_script_test_employee_register_teardown($conn, $employeeId);
ts_pass('Disposable test employee #' . $employeeId);

$statusStmt = mysqli_prepare(
    $conn,
    'SELECT id FROM ticket_statuses WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY id ASC LIMIT 1'
);
mysqli_stmt_bind_param($statusStmt, 'i', $companyId);
mysqli_stmt_execute($statusStmt);
$statusRes = mysqli_stmt_get_result($statusStmt);
$statusRow = $statusRes ? mysqli_fetch_assoc($statusRes) : null;
mysqli_stmt_close($statusStmt);
$openStatusId = (int)($statusRow['id'] ?? 0);
if ($openStatusId <= 0) {
    ts_fail('No ticket status for company 1');
    itm_script_output_end(1);
    exit(1);
}

$insertTicket = mysqli_prepare(
    $conn,
    'INSERT INTO tickets (company_id, title, description, status_id, created_by_employee_id, assigned_to_employee_id, active)
     VALUES (?, ?, ?, ?, ?, ?, 1)'
);
$title = 'MBQA survey verify ' . bin2hex(random_bytes(4));
$desc = 'Disposable survey regression ticket';
mysqli_stmt_bind_param($insertTicket, 'issiii', $companyId, $title, $desc, $openStatusId, $employeeId, $employeeId);
if (!mysqli_stmt_execute($insertTicket)) {
    ts_fail('Could not insert test ticket');
    itm_script_output_end(1);
    exit(1);
}
$testTicketId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($insertTicket);
ts_pass('Created test ticket #' . $testTicketId);

$activityBeforeIssue = ts_activity_count($conn, $companyId, $testTicketId, 'survey_issued');
$surveyId = itm_ticket_survey_issue($conn, $companyId, $testTicketId, 0, 'survey-verify@example.com', false);
if ($surveyId <= 0) {
    ts_fail('itm_ticket_survey_issue returned 0');
} else {
    ts_pass('Issued survey #' . $surveyId);
}

if (ts_activity_count($conn, $companyId, $testTicketId, 'survey_issued') <= $activityBeforeIssue) {
    ts_fail('survey_issued activity not logged');
} else {
    ts_pass('survey_issued activity logged');
}

$surveyRow = itm_ticket_survey_get_latest_for_ticket($conn, $companyId, $testTicketId);
$token = is_array($surveyRow) ? (string)($surveyRow['token'] ?? '') : '';
if ($token === '') {
    ts_fail('Issued survey missing token');
} else {
    $verified = itm_ticket_survey_verify_token($conn, $token);
    if (!is_array($verified) || (int)($verified['survey_id'] ?? 0) !== $surveyId) {
        ts_fail('itm_ticket_survey_verify_token mismatch');
    } else {
        ts_pass('Token verify OK');
    }
    $url = itm_ticket_survey_build_public_url($token);
    if (strpos($url, 'ticket-survey.php') === false || strpos($url, rawurlencode($token)) === false) {
        ts_fail('Public URL builder missing token path');
    } else {
        ts_pass('Public URL builder');
    }
}

$mergedBody = itm_ticket_survey_merge_canned_body($conn, 'Feedback: {{survey_url}}', $companyId, $testTicketId);
if (strpos($mergedBody, '{{survey_url}}') !== false || strpos($mergedBody, 'ticket-survey.php') === false) {
    ts_fail('{{survey_url}} merge tag did not resolve');
} else {
    ts_pass('Canned merge tag {{survey_url}}');
}

$questionnaireId = is_array($surveyRow) ? (int)($surveyRow['questionnaire_id'] ?? 0) : 0;
$questions = itm_ticket_survey_load_questions($conn, $questionnaireId, $companyId);
if ($questions === []) {
    ts_fail('No questions loaded for questionnaire');
} else {
    ts_pass('Loaded ' . count($questions) . ' questionnaire questions');
}

$answers = [];
foreach ($questions as $q) {
    $qid = (int)$q['id'];
    if (($q['question_type'] ?? '') === 'rating_1_5') {
        $answers[$qid] = 5;
    } else {
        $answers[$qid] = 'MBQA survey comment';
    }
}

$activityBeforeComplete = ts_activity_count($conn, $companyId, $testTicketId, 'survey_completed');
if (!itm_ticket_survey_submit($conn, $surveyId, $answers, 1)) {
    ts_fail('itm_ticket_survey_submit failed');
} else {
    ts_pass('Survey submitted');
}

if (ts_activity_count($conn, $companyId, $testTicketId, 'survey_completed') <= $activityBeforeComplete) {
    ts_fail('survey_completed activity not logged');
} else {
    ts_pass('survey_completed activity logged');
}
if (ts_activity_count($conn, $companyId, $testTicketId, 'csat_submitted') < 1) {
    ts_fail('csat_submitted activity not logged');
} else {
    ts_pass('csat_submitted activity logged');
}

$csatStmt = mysqli_prepare($conn, 'SELECT csat_score, csat_comment FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
mysqli_stmt_bind_param($csatStmt, 'ii', $testTicketId, $companyId);
mysqli_stmt_execute($csatStmt);
$csatRes = mysqli_stmt_get_result($csatStmt);
$csatRow = $csatRes ? mysqli_fetch_assoc($csatRes) : null;
mysqli_stmt_close($csatStmt);
if ((int)($csatRow['csat_score'] ?? 0) < 1) {
    ts_fail('tickets.csat_score not synced after submit');
} else {
    ts_pass('tickets.csat_score synced (' . (int)$csatRow['csat_score'] . ')');
}

// Automation: ticket.survey_completed + average_score condition.
$ruleName = 'MBQA-Survey-Verify-' . date('YmdHis');
$conditionsJson = json_encode([
    ['field' => 'average_score', 'op' => 'not_empty'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$actionsJson = json_encode([
    [
        'type' => 'notify_employee',
        'employee_id' => $employeeId,
        'title' => 'Survey verify',
        'body' => 'Automation survey_completed probe',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$nameEsc = mysqli_real_escape_string($conn, $ruleName);
$conditionsEsc = mysqli_real_escape_string($conn, (string)$conditionsJson);
$actionsEsc = mysqli_real_escape_string($conn, (string)$actionsJson);
$insertRuleSql = "INSERT INTO automation_rules (company_id, name, trigger_slug, conditions_json, actions_json, enabled, active, created_by)
                  VALUES ({$companyId}, '{$nameEsc}', 'ticket.survey_completed', '{$conditionsEsc}', '{$actionsEsc}', 1, 1, {$employeeId})";
if (!itm_run_query($conn, $insertRuleSql)) {
    ts_fail('Could not insert survey automation rule');
} else {
    $lookup = mysqli_query(
        $conn,
        "SELECT id FROM automation_rules WHERE company_id = {$companyId} AND name = '{$nameEsc}' AND deleted_at IS NULL ORDER BY id DESC LIMIT 1"
    );
    $lookupRow = $lookup ? mysqli_fetch_assoc($lookup) : null;
    $ruleId = (int)($lookupRow['id'] ?? 0);
    if ($ruleId <= 0) {
        ts_fail('Could not resolve survey automation rule id');
    } else {
        $ctx = itm_automation_rules_build_ticket_context($conn, $companyId, $testTicketId, [
            'automation_depth' => 0,
            'survey_id' => $surveyId,
            'average_score' => 5.0,
        ]);
        if (!itm_automation_rules_conditions_match(json_decode($conditionsJson, true) ?: [], $ctx)) {
            ts_fail('average_score condition should match survey context');
        } else {
            ts_pass('Automation average_score condition');
        }
        if (!itm_automation_rules_run_rule($conn, $companyId, $ruleId, $ctx)) {
            ts_fail('Survey automation rule run failed');
        } else {
            $runRes = mysqli_query(
                $conn,
                "SELECT status FROM automation_rule_runs WHERE company_id = {$companyId} AND rule_id = {$ruleId} ORDER BY id DESC LIMIT 1"
            );
            $runRow = $runRes ? mysqli_fetch_assoc($runRes) : null;
            if (($runRow['status'] ?? '') !== 'success') {
                ts_fail('Survey automation run status not success');
            } else {
                ts_pass('Automation ticket.survey_completed dispatch');
            }
        }
        mysqli_query($conn, 'DELETE FROM automation_rule_runs WHERE rule_id = ' . $ruleId);
        mysqli_query($conn, 'DELETE FROM automation_rules WHERE id = ' . $ruleId);
    }
}

// Webhook enqueue on ticket.survey_completed.
$hookName = 'MBQA-Survey-Webhook-' . bin2hex(random_bytes(3));
$hookNameEsc = mysqli_real_escape_string($conn, $hookName);
$eventsEsc = mysqli_real_escape_string($conn, 'ticket.survey_completed');
$urlEsc = mysqli_real_escape_string($conn, 'https://example.com/itm-survey-hook');
$secretEsc = mysqli_real_escape_string($conn, 'survey-verify-secret');
$hookSql = "INSERT INTO integration_webhooks (company_id, name, target_url, event_types, secret_encrypted, max_attempts, active, created_by)
            VALUES ({$companyId}, '{$hookNameEsc}', '{$urlEsc}', '{$eventsEsc}', '{$secretEsc}', 3, 1, {$employeeId})";
if (!itm_run_query($conn, $hookSql)) {
    ts_fail('Could not insert integration webhook');
} else {
    $hookLookup = mysqli_query(
        $conn,
        "SELECT id FROM integration_webhooks WHERE company_id = {$companyId} AND name = '{$hookNameEsc}' ORDER BY id DESC LIMIT 1"
    );
    $hookRow = $hookLookup ? mysqli_fetch_assoc($hookLookup) : null;
    $hookId = (int)($hookRow['id'] ?? 0);
    if ($hookId <= 0) {
        ts_fail('Could not resolve webhook id');
    } else {
        $queued = itm_webhook_queue_enqueue($conn, $companyId, 'ticket.survey_completed', [
            'ticket_id' => $testTicketId,
            'survey_id' => $surveyId,
            'average_score' => 5.0,
        ]);
        if ($queued < 1) {
            ts_fail('Webhook enqueue returned 0 for ticket.survey_completed');
        } else {
            ts_pass('Webhook ticket.survey_completed enqueue (' . $queued . ' delivery row(s))');
        }
        mysqli_query($conn, 'DELETE FROM integration_webhook_deliveries WHERE webhook_id = ' . $hookId);
        mysqli_query($conn, 'DELETE FROM integration_webhooks WHERE id = ' . $hookId);
    }
}

// Merge cancel: pending survey on source removed when merging into target.
require_once ROOT_PATH . 'includes/itm_ticket_merge.php';
$srcInsert = mysqli_prepare(
    $conn,
    'INSERT INTO tickets (company_id, title, description, status_id, created_by_employee_id, assigned_to_employee_id, active)
     VALUES (?, ?, ?, ?, ?, ?, 1)'
);
$srcTitle = 'MBQA merge source ' . bin2hex(random_bytes(3));
mysqli_stmt_bind_param($srcInsert, 'issiii', $companyId, $srcTitle, $desc, $openStatusId, $employeeId, $employeeId);
mysqli_stmt_execute($srcInsert);
$sourceTicketId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($srcInsert);

$tgtInsert = mysqli_prepare(
    $conn,
    'INSERT INTO tickets (company_id, title, description, status_id, created_by_employee_id, assigned_to_employee_id, active)
     VALUES (?, ?, ?, ?, ?, ?, 1)'
);
$tgtTitle = 'MBQA merge target ' . bin2hex(random_bytes(3));
mysqli_stmt_bind_param($tgtInsert, 'issiii', $companyId, $tgtTitle, $desc, $openStatusId, $employeeId, $employeeId);
mysqli_stmt_execute($tgtInsert);
$targetTicketId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($tgtInsert);

$pendingSurveyId = itm_ticket_survey_issue($conn, $companyId, $sourceTicketId, 0, 'merge@example.com', false);
if ($pendingSurveyId <= 0 || ts_pending_survey_count($conn, $companyId, $sourceTicketId) < 1) {
    ts_fail('Could not create pending survey on merge source');
} else {
    $mergeResult = itm_ticket_merge_tickets($conn, $companyId, $sourceTicketId, $targetTicketId, $employeeId);
    if (empty($mergeResult['ok'])) {
        ts_fail('Merge failed: ' . (string)($mergeResult['error'] ?? ''));
    } elseif (ts_pending_survey_count($conn, $companyId, $sourceTicketId) > 0) {
        ts_fail('Pending survey still exists on source after merge');
    } else {
        ts_pass('Merge cancels pending surveys on source ticket');
    }
}

// Saved report filters + list parser.
$filterCheck = itm_saved_reports_validate_filters('tickets', [
    'survey_status' => 'pending',
    'csat_min' => 4,
    'evil' => 'x',
]);
if (empty($filterCheck['ok']) || !isset($filterCheck['filters']['survey_status']) || isset($filterCheck['filters']['evil'])) {
    ts_fail('Saved report survey filters validation');
} else {
    ts_pass('Saved report survey_status / csat_min whitelist');
}

$parsed = itm_tickets_list_parse_filters(['survey_status' => 'completed', 'csat_min' => '3']);
if (($parsed['survey_status'] ?? '') !== 'completed' || (int)($parsed['csat_min'] ?? 0) !== 3) {
    ts_fail('itm_tickets_list_parse_filters survey keys');
} else {
    ts_pass('Tickets list survey filter parser');
}

$stats = itm_ticket_survey_stats_aggregate($conn, $companyId);
if ((int)($stats['issued'] ?? 0) < 1 || (int)($stats['completed'] ?? 0) < 1) {
    ts_fail('Stats aggregate issued/completed counts');
} else {
    ts_pass('Stats aggregate (issued=' . (int)$stats['issued'] . ', completed=' . (int)$stats['completed'] . ')');
}

// Cleanup disposable tickets (surveys cascade or orphan — delete surveys first).
mysqli_query($conn, 'DELETE FROM ticket_surveys WHERE ticket_id IN (' . $testTicketId . ',' . $sourceTicketId . ',' . $targetTicketId . ')');
mysqli_query($conn, 'DELETE FROM ticket_activity WHERE ticket_id IN (' . $testTicketId . ',' . $sourceTicketId . ',' . $targetTicketId . ')');
mysqli_query($conn, 'DELETE FROM tickets WHERE id IN (' . $testTicketId . ',' . $sourceTicketId . ',' . $targetTicketId . ')');

if ($failures > 0) {
    echo colorText($failures . ' failure(s)', 'fail') . $nl;
    itm_script_output_end(1);
    exit(1);
}

echo colorText('All ticket survey checks passed.', 'pass') . $nl;
itm_script_output_end(0);
exit(0);
