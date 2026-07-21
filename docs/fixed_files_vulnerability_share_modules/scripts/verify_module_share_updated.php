<?php
/**
 * CLI: php scripts/verify_module_share.php
 * Verifies company_module_share matrix helpers and share_modules admin gate.
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Module Share Matrix Verification');
$nl = itm_script_output_nl();
$failures = 0;

function module_share_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function module_share_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

if (!($conn instanceof mysqli)) {
    module_share_verify_fail('Database connection unavailable.');
    exit(1);
}

if (!itm_module_share_table_exists($conn, 'company_module_share')) {
    module_share_verify_fail('company_module_share table missing — re-import db/ or apply db/migrations/share_sessions_unified.sql.');
    exit(1);
}

$notesId = itm_module_share_registry_id_by_slug($conn, 'notes');
if ($notesId <= 0) {
    module_share_verify_fail('modules_registry row for notes missing.');
    exit(1);
}

if (!has_module_share_access($conn, 1, 'notes')) {
    module_share_verify_fail('notes share should be allowed when company_module_share has enabled=1 for company 1.');
    $failures++;
} else {
    module_share_verify_pass('notes share allowed from company_module_share DB row for company 1.');
}

itm_set_company_module_share($conn, 1, $notesId, 0);
if (has_module_share_access($conn, 1, 'notes')) {
    module_share_verify_fail('notes share should be denied after enabled=0.');
    $failures++;
} else {
    module_share_verify_pass('notes share denied after explicit enabled=0.');
}

itm_set_company_module_share($conn, 1, $notesId, 1);
if (!has_module_share_access($conn, 1, 'notes')) {
    module_share_verify_fail('notes share should be allowed when company_module_share row has enabled=1.');
    $failures++;
} else {
    module_share_verify_pass('notes share allowed when company_module_share row has enabled=1.');
}

$badRowRes = $conn->query(
    "SELECT COUNT(*) AS cnt
     FROM company_module_share cms
     INNER JOIN modules_registry mr ON mr.id = cms.module_id
     WHERE mr.module_slug NOT IN (
       'notes', 'passwords', 'bookmarks', 'todo', 'events',
       'private_contacts', 'explorer', 'floor_plans', 'rack_planner'
     )"
);
$badRowCnt = ($badRowRes && ($badRow = $badRowRes->fetch_assoc())) ? (int)($badRow['cnt'] ?? 0) : -1;
if ($badRowCnt !== 0) {
    module_share_verify_fail('company_module_share must only contain share-capable module slugs (found ' . $badRowCnt . ' extra row(s)). Apply db/migrations/company_module_share_capable_seed.sql.');
    $failures++;
} else {
    module_share_verify_pass('company_module_share rows limited to share-capable modules.');
}

$capableCountRes = $conn->query(
    "SELECT COUNT(*) AS cnt FROM company_module_share cms
     INNER JOIN modules_registry mr ON mr.id = cms.module_id
     WHERE mr.module_slug = 'notes' AND cms.enabled = 1"
);
$capableCount = ($capableCountRes && ($capRow = $capableCountRes->fetch_assoc())) ? (int)($capRow['cnt'] ?? 0) : 0;
if ($capableCount < 1) {
    module_share_verify_fail('notes should have at least one enabled company_module_share row — re-import db/ or run company_module_share_capable_seed.sql.');
    $failures++;
} else {
    module_share_verify_pass('notes has enabled company_module_share row(s) in DB.');
}

if (!has_module_share_access($conn, 1, 'suppliers')) {
    module_share_verify_pass('non-capable slug suppliers correctly blocked at helper layer.');
} else {
    module_share_verify_fail('non-capable slug suppliers should not allow share.');
}

$shareTable = $conn->query("SHOW TABLES LIKE 'share_sessions'");
if (!$shareTable || $shareTable->num_rows === 0) {
    module_share_verify_fail('share_sessions table missing.');
    $failures++;
} else {
    module_share_verify_pass('share_sessions table present.');
}

echo $nl . ($failures === 0 ? colorText('All module share checks passed.', 'pass') : colorText("Failed with {$failures} issue(s).", 'fail')) . $nl;
exit($failures === 0 ? 0 : 1);
