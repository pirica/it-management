<?php
/**
 * Employee reassignment planner — what to move before deleting an employee.
 *
 * Why: Shows row counts, skip reasons, optional related-column SQL, and inbound FK
 * rules so admins know exactly what to run before delete.
 *
 * Default: dry-run (plan only). Apply: CLI --apply or browser apply=1 (Admin).
 * Filter: employee_id / --employee-id (source preview), rows_only hides 0-row tables.
 *
 * Browser: scripts/generate_reassignment.php?employee_id=N or ?from=N&to=M
 * CLI: php scripts/generate_reassignment.php --employee-id=N [--to=M] [--all-tables] [--apply]
 * Bare CLI (no employee id): prints usage and exits 0 (perform_audit.php safe).
 */

declare(strict_types=1);

$itmIsCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_employee_reassignment.php';

if (!$itmIsCli) {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_require_admin_script_or_exit($conn, 'Administrator access required for employee reassignment planning.');
}

/**
 * @return array{from:int,to:int,apply:bool,rows_only:bool,company_id:int}
 */
function itm_generate_reassignment_parse_request(bool $isCli): array
{
    $from = 0;
    $to = 0;
    $apply = false;
    $rowsOnly = true;
    $companyId = 0;

    if ($isCli) {
        $options = getopt('', ['from:', 'to:', 'employee-id:', 'apply', 'all-tables', 'company:']);
        $from = isset($options['from']) ? (int)$options['from'] : 0;
        if ($from <= 0 && isset($options['employee-id'])) {
            $from = (int)$options['employee-id'];
        }
        $to = isset($options['to']) ? (int)$options['to'] : 0;
        $apply = isset($options['apply']);
        $rowsOnly = !isset($options['all-tables']);
        $companyId = isset($options['company']) ? (int)$options['company'] : 0;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        itm_require_post_csrf();
        $from = (int)($_POST['from_id'] ?? $_POST['employee_id'] ?? 0);
        $to = (int)($_POST['to_id'] ?? 0);
        $apply = !empty($_POST['apply']);
        $rowsOnly = !isset($_POST['all_tables']);
        $companyId = (int)($_POST['company_id'] ?? 0);
    } else {
        $from = (int)($_GET['from'] ?? $_GET['from_id'] ?? $_GET['employee_id'] ?? 0);
        $to = (int)($_GET['to'] ?? $_GET['to_id'] ?? 0);
        $apply = isset($_GET['apply']) && (string)$_GET['apply'] === '1';
        $rowsOnly = !isset($_GET['all_tables']) || (string)$_GET['all_tables'] !== '1';
        $companyId = (int)($_GET['company_id'] ?? 0);
    }

    return [
        'from' => $from,
        'to' => $to,
        'apply' => $apply,
        'rows_only' => $rowsOnly,
        'company_id' => $companyId,
    ];
}

/**
 * @return never
 */
function itm_generate_reassignment_render_form(mysqli $conn, int $defaultCompanyId = 0, int $prefillFrom = 0, int $prefillTo = 0): void
{
    $baseUrl = defined('BASE_URL') ? (string)BASE_URL : '../';
    $scriptSelf = $baseUrl . 'scripts/generate_reassignment.php';
    $csrf = itm_get_csrf_token();
    $sessionCompanyId = (int)($_SESSION['company_id'] ?? 0);
    $companyFilter = $defaultCompanyId > 0 ? $defaultCompanyId : $sessionCompanyId;
    $employees = itm_employee_reassignment_list_employees($conn, $companyFilter);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Employee Reassignment Plan</title>';
    echo '<style>';
    echo 'body{font-family:Segoe UI,sans-serif;background:#f6f8fa;color:#24292f;margin:0;padding:20px;}';
    echo '.gr-wrap{max-width:820px;margin:0 auto;}';
    echo '.gr-card{background:#fff;border:1px solid #d0d7de;border-radius:8px;padding:20px;margin-bottom:16px;}';
    echo 'label{display:block;font-weight:600;margin:8px 0 6px;}';
    echo 'input[type=number],select{width:100%;max-width:360px;padding:8px;border:1px solid #d0d7de;border-radius:6px;}';
    echo '.gr-muted{color:#57606a;line-height:1.5;}';
    echo '.gr-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px;}';
    echo '.btn{display:inline-block;padding:8px 14px;border-radius:6px;border:1px solid #d0d7de;background:#f6f8fa;color:#24292f;text-decoration:none;cursor:pointer;font-size:0.95rem;}';
    echo '.btn-primary{background:#0969da;border-color:#0969da;color:#fff;}';
    echo '.btn-danger{background:#cf222e;border-color:#cf222e;color:#fff;}';
    echo '.gr-filter{display:grid;gap:12px;margin-bottom:12px;}';
    echo '</style></head><body><div class="gr-wrap">';
    itm_script_browser_nav_echo($baseUrl);
    echo '<div class="gr-card"><h1>Employee reassignment plan</h1>';
    echo '<p class="gr-muted">Filter by <strong>employee id</strong> (source leaving user). Optional target id builds apply-ready SQL. Default output hides tables with <strong>0 rows</strong> for this employee.</p>';
    echo '<form method="post" action="' . htmlspecialchars($scriptSelf, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">';
    echo '<div class="gr-filter">';
    echo '<label for="company_id">Company filter (employee picker)</label>';
    echo '<input type="number" name="company_id" id="company_id" min="0" value="' . (int)$companyFilter . '" title="0 = all companies">';
    echo '<label for="employee_id">Source employee id (leaving) — required</label>';
    echo '<input type="number" name="from_id" id="employee_id" list="gr-employee-ids" min="1" required value="' . ($prefillFrom > 0 ? (int)$prefillFrom : '') . '">';
    echo '<datalist id="gr-employee-ids">';
    foreach ($employees as $employee) {
        echo '<option value="' . (int)$employee['id'] . '">' . htmlspecialchars($employee['label'], ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</datalist>';
    echo '<label for="to_id">Target employee id (successor) — optional for preview</label>';
    echo '<input type="number" name="to_id" id="to_id" list="gr-employee-ids" min="1" value="' . ($prefillTo > 0 ? (int)$prefillTo : '') . '">';
    echo '<label><input type="checkbox" name="all_tables" value="1"> Show all tables (include 0-row entries)</label>';
    echo '</div>';
    echo '<div class="gr-actions">';
    echo '<button type="submit" class="btn btn-primary" name="dry_run" value="1">Build plan (dry-run)</button>';
    echo '<button type="submit" class="btn btn-danger" name="apply" value="1" onclick="return confirm(\'Apply employee_id reassignment updates now?\');">Apply employee_id moves</button>';
    echo '</div>';
    echo '</form>';
    echo '<p class="gr-muted">Quick preview: <code>?employee_id=14</code> · full pair: <code>?from=14&amp;to=1</code> · CLI: <code>php scripts/generate_reassignment.php --employee-id=14 --to=1</code></p>';
    echo '</div></div></body></html>';
    exit(0);
}

/**
 * @param array<string, mixed> $employee
 */
function itm_generate_reassignment_format_employee(array $employee): string
{
    $name = trim((string)($employee['first_name'] ?? '') . ' ' . (string)($employee['last_name'] ?? ''));
    $username = (string)($employee['username'] ?? '');
    $label = $username !== '' ? $username : '(no username)';

    return 'id=' . (int)$employee['id']
        . ' company_id=' . (int)$employee['company_id']
        . ' user=' . $label
        . ' name=' . ($name !== '' ? $name : '(blank)');
}

/**
 * @param array<int, array{row_count:int}> $rows
 * @return array<int, array{row_count:int}>
 */
function itm_generate_reassignment_filter_rows(array $rows, bool $rowsOnly): array
{
    if (!$rowsOnly) {
        return $rows;
    }

    return array_values(array_filter($rows, static function ($row) {
        return (int)$row['row_count'] > 0;
    }));
}

$request = itm_generate_reassignment_parse_request($itmIsCli);
$fromId = (int)$request['from'];
$toId = (int)$request['to'];
$apply = (bool)$request['apply'];
$rowsOnly = (bool)$request['rows_only'];
$companyFilter = (int)$request['company_id'];
$dryRun = !$apply;

if (!$itmIsCli && $fromId <= 0) {
    itm_generate_reassignment_render_form($conn, $companyFilter);
}

$nl = itm_script_output_nl();
itm_script_output_begin('Employee reassignment plan');

if ($fromId <= 0) {
    if ($itmIsCli) {
        echo colorText('[INFO] No employee id — planner requires --employee-id=N or --from=N (browser form when opened without id).', 'info') . $nl;
        echo 'Example: php scripts/generate_reassignment.php --employee-id=1 --to=2' . $nl;
        echo '         php scripts/generate_reassignment.php --employee-id=1 --all-tables' . $nl;
    } else {
        echo colorText('[FAIL] Specify --employee-id=N or --from=N (or use the browser form).', 'fail') . $nl;
    }
    itm_script_output_end();
    exit($itmIsCli ? 0 : 1);
}

if ($apply && $toId <= 0) {
    echo colorText('[FAIL] Apply requires a target employee (--to=M or to_id in the form).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText(
    $dryRun
        ? 'Mode: DRY-RUN (plan / debug only — no writes)'
        : 'Mode: APPLY (runs employee_id UPDATE statements from the plan)',
    $dryRun ? 'info' : 'warn'
) . $nl;
echo 'Employee filter: source id=' . $fromId . ($toId > 0 ? ', target id=' . $toId : ' (preview — no target yet)') . $nl;
echo 'Row filter: ' . ($rowsOnly ? 'rows with data only (add all_tables=1 to show zeros)' : 'all tables') . $nl;

$plan = itm_employee_reassignment_build_plan($conn, $fromId, $toId);

if ($plan['from'] !== null) {
    echo 'Source: ' . itm_generate_reassignment_format_employee($plan['from']) . $nl;
}
if ($plan['to'] !== null) {
    echo 'Target: ' . itm_generate_reassignment_format_employee($plan['to']) . $nl;
} elseif (!empty($plan['preview_only'])) {
    echo colorText('Target: (not set — SQL uses <TARGET_EMPLOYEE_ID> placeholder)', 'info') . $nl;
}

if (!$plan['ok']) {
    echo colorText('[FAIL] ' . $plan['message'], 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$reassignRows = itm_generate_reassignment_filter_rows($plan['reassign'], $rowsOnly);
$skipRows = itm_generate_reassignment_filter_rows($plan['skip'], $rowsOnly);
$relatedRows = itm_generate_reassignment_filter_rows($plan['related'], $rowsOnly);
$inboundRows = itm_generate_reassignment_filter_rows($plan['inbound_fks'], $rowsOnly);

echo $nl . '=== Summary ===' . $nl;
echo 'employee_id reassign rows shown: ' . count($reassignRows) . $nl;
echo 'employee_id skip rows shown: ' . count($skipRows) . $nl;
echo 'Other employee FK rows shown: ' . count($relatedRows) . $nl;
echo 'Inbound FK rows shown: ' . count($inboundRows) . $nl;

echo $nl . colorText('=== 1) REASSIGN employee_id (apply runs this section only) ===', 'info') . $nl;
if ($reassignRows === []) {
    echo colorText($rowsOnly ? '(no employee_id rows on source — try all_tables=1)' : '(no reassignable tables)', 'pass') . $nl;
}
foreach ($reassignRows as $row) {
    $count = (int)$row['row_count'];
    echo ' - ' . $row['table'] . ': ' . colorText((string)$count . ' row(s)', 'warn') . $nl;
    echo '   SQL: ' . $row['sql'] . $nl;
}

echo $nl . colorText('=== 2) SKIP employee_id (do not bulk-reassign) ===', 'info') . $nl;
if ($skipRows === []) {
    echo colorText($rowsOnly ? '(no skipped tables with rows on source)' : '(none listed)', 'pass') . $nl;
}
foreach ($skipRows as $row) {
    echo ' - ' . $row['table'] . ' (' . (int)$row['row_count'] . ' row(s) on source)' . $nl;
    echo '   Why: ' . $row['reason'] . $nl;
}

echo $nl . colorText('=== 3) OTHER employee FK columns (optional — not applied automatically) ===', 'info') . $nl;
if ($relatedRows === []) {
    echo colorText($rowsOnly ? '(no related-column rows on source)' : '(none listed)', 'pass') . $nl;
}
foreach ($relatedRows as $row) {
    echo ' - ' . $row['table'] . '.' . $row['column'] . ': ' . (int)$row['row_count'] . ' row(s) [' . $row['action'] . ']' . $nl;
    echo '   Why: ' . $row['reason'] . $nl;
    if ($row['sql'] !== '') {
        echo '   Suggested SQL: ' . $row['sql'] . $nl;
    }
}

echo $nl . colorText('=== 4) Inbound FK debug (employees referenced by) ===', 'info') . $nl;
if ($inboundRows === []) {
    echo colorText($rowsOnly ? '(no inbound FK rows on source)' : '(none listed)', 'pass') . $nl;
}
foreach ($inboundRows as $fk) {
    $ruleColor = $fk['delete_rule'] === 'CASCADE' ? 'warn' : 'pass';
    echo ' - ' . $fk['table'] . '.' . $fk['column']
        . ' → ' . $fk['constraint']
        . ' ON DELETE ' . colorText($fk['delete_rule'], $ruleColor)
        . ' · ' . (int)$fk['row_count'] . ' row(s) on source' . $nl;
}

echo $nl . colorText('=== 5) Suggested next steps ===', 'info') . $nl;
echo '1. Re-run with target id when preview looks correct: ?from=' . $fromId . '&to=<successor>' . $nl;
echo '2. Apply employee_id moves (section 1) when both ids are set.' . $nl;
echo '3. Run related-column SQL from section 3 manually if needed.' . $nl;
echo '4. Handle skipped private data (section 2) via admin delete or delete_clone_employee.php.' . $nl;

if ($dryRun) {
    echo $nl . colorText($plan['message'], 'pass') . $nl;
    if ($itmIsCli) {
        if ($toId > 0) {
            echo 'Apply: php scripts/generate_reassignment.php --from=' . $fromId . ' --to=' . $toId . ' --apply' . $nl;
        } else {
            echo 'Add target: php scripts/generate_reassignment.php --employee-id=' . $fromId . ' --to=<id>' . $nl;
        }
    } else {
        $qs = 'employee_id=' . $fromId;
        if ($toId > 0) {
            $qs = 'from=' . $fromId . '&to=' . $toId;
        }
        if (!$rowsOnly) {
            $qs .= '&all_tables=1';
        }
        echo 'Re-run: generate_reassignment.php?' . $qs . $nl;
        if ($toId > 0) {
            echo 'Apply: generate_reassignment.php?' . $qs . '&apply=1' . $nl;
        }
    }
    itm_script_output_end();
    exit(0);
}

$applyResult = itm_employee_reassignment_apply_plan($conn, $plan['reassign']);
if (!$applyResult['ok']) {
    echo $nl . colorText('[FAIL] ' . $applyResult['message'], 'fail') . $nl;
    foreach ($applyResult['errors'] as $error) {
        echo ' - ' . $error . $nl;
    }
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('[PASS] ' . $applyResult['message'], 'pass') . $nl;
if ($applyResult['updated'] !== []) {
    echo 'Tables updated: ' . implode(', ', $applyResult['updated']) . $nl;
}

itm_script_output_end();
exit(0);
