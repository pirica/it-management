<?php
/**
 * Copy appointment_visit_reasons from company 1 to every other company (INSERT IGNORE).
 *
 * Why: Fresh imports now replicate before late seeds; live DBs may still lack reasons on companies 2–5.
 *
 * CLI: php scripts/apply_appointment_visit_reasons_replicate.php
 * CLI: php scripts/apply_appointment_visit_reasons_replicate.php --apply
 * CLI: php scripts/apply_appointment_visit_reasons_replicate.php --apply --source=1
 * Browser: scripts/apply_appointment_visit_reasons_replicate.php?run=1
 * Browser apply: scripts/apply_appointment_visit_reasons_replicate.php?run=1&apply=1 (Admin)
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/apply_appointment_visit_reasons_replicate.php</code> — dry-run. <code>php scripts/apply_appointment_visit_reasons_replicate.php --apply</code> copies company 1 visit reasons to companies 2–5 with <code>INSERT IGNORE</code> (unique <code>company_id</code> + <code>name</code>).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Replicate appointment visit reasons', ['skip_db_tests' => false]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$isCli = $boot['is_cli'];
$argv = $boot['argv'];
$conn = isset($boot['conn']) && $boot['conn'] instanceof mysqli ? $boot['conn'] : null;

$sourceCompanyId = 1;
if ($isCli) {
    foreach ($argv as $arg) {
        if (preg_match('/^--source=(\d+)$/', (string)$arg, $m)) {
            $sourceCompanyId = max(1, (int)$m[1]);
        }
    }
} else {
    $sourceCompanyId = max(1, (int)($_GET['source'] ?? 1));
}

if (!($conn instanceof mysqli)) {
    echo colorText('[FAIL] Database connection required.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$countStmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) AS c FROM appointment_visit_reasons WHERE company_id = ? AND deleted_at IS NULL'
);
$sourceCount = 0;
if ($countStmt) {
    mysqli_stmt_bind_param($countStmt, 'i', $sourceCompanyId);
    mysqli_stmt_execute($countStmt);
    $countRes = mysqli_stmt_get_result($countStmt);
    $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
    $sourceCount = (int)($countRow['c'] ?? 0);
    mysqli_stmt_close($countStmt);
}

if ($sourceCount === 0) {
    echo colorText('[FAIL] No visit reasons on source company ' . $sourceCompanyId . '.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$sql = 'INSERT IGNORE INTO appointment_visit_reasons (company_id, name, sort_order, active, created_at)
        SELECT c.id, t.name, t.sort_order, t.active, NOW()
        FROM appointment_visit_reasons t
        JOIN companies c ON c.id <> t.company_id
        WHERE t.company_id = ? AND t.deleted_at IS NULL';

echo colorText('Appointment visit reasons replicate (source company ' . $sourceCompanyId . ')', 'info') . $nl;
echo 'Source rows: ' . $sourceCount . $nl;

if (!$apply) {
    echo colorText('[DRY-RUN] Would run INSERT IGNORE for all other companies.', 'warn') . $nl;
    itm_script_output_end();
    exit(0);
}

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    echo colorText('[FAIL] Prepare failed.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
mysqli_stmt_bind_param($stmt, 'i', $sourceCompanyId);
mysqli_stmt_execute($stmt);
$inserted = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

echo colorText('[PASS] Inserted ' . $inserted . ' visit reason row(s) on target companies.', 'ok') . $nl;
itm_script_output_end();
exit(0);
