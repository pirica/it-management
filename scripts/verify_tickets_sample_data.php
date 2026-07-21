<?php
/**
 * CLI: php scripts/verify_tickets_sample_data.php
 * Verifies tickets Add sample data on empty tenants (including zero local employees).
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'modules/tickets/sample_seed_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Tickets sample data verification');

$failures = 0;

function vtsd_fail($message)
{
    global $failures;
    $failures++;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function vtsd_pass($message)
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

if (!($conn instanceof mysqli)) {
    vtsd_fail('Database connection unavailable.');
    exit(1);
}

$companyId = 4;
$_SESSION['employee_id'] = 1;
$_SESSION['company_id'] = $companyId;

mysqli_query($conn, 'DELETE FROM tickets WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM ticket_categories WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM ticket_statuses WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM ticket_priorities WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM equipment WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM employees WHERE company_id = ' . (int)$companyId);

$err = '';
$inserted = itm_seed_table_from_database_sql($conn, 'tickets', $companyId, $err);
if ($inserted !== 1) {
    vtsd_fail('Expected one tickets sample row on empty tenant without local employees; inserted=' . (int)$inserted . ' err=' . $err);
} else {
    vtsd_pass('Tickets sample inserts on empty tenant without local employees.');
}

$active = tickets_tenant_active_row_count($conn, $companyId);
if ($active !== 1) {
    vtsd_fail('Expected one active list row after sample seed; active=' . (int)$active);
} else {
    vtsd_pass('Sample ticket is visible on the active list (is_archived = 0).');
}

$rowRes = mysqli_query(
    $conn,
    "SELECT ticket_external_code, title, is_archived FROM tickets WHERE company_id = " . (int)$companyId . " LIMIT 1"
);
$row = $rowRes ? mysqli_fetch_assoc($rowRes) : null;
if (!is_array($row) || ($row['ticket_external_code'] ?? '') !== 'TCK-0001') {
    vtsd_fail('Sample ticket external code is not TCK-0001.');
} else {
    vtsd_pass('Sample ticket uses canonical TCK-0001 row.');
}

if ((int)($row['is_archived'] ?? 1) !== 0) {
    vtsd_fail('Sample ticket must not be archived.');
} else {
    vtsd_pass('Sample ticket is not archived.');
}

mysqli_query($conn, 'DELETE FROM tickets WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM ticket_categories WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM ticket_statuses WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM ticket_priorities WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, "INSERT INTO ticket_categories (company_id, name, code, active) VALUES ({$companyId}, 'Sample deadbeef', 'SMP', 1)");
mysqli_query($conn, "INSERT INTO ticket_statuses (company_id, name, color, is_closed, active) VALUES ({$companyId}, 'Sample deadbeef', '#808080', 0, 1)");
mysqli_query($conn, "INSERT INTO ticket_priorities (company_id, name, level, color, active) VALUES ({$companyId}, 'Sample deadbeef', 1, '#808080', 1)");

$garbageErr = '';
$garbageInserted = itm_seed_insert_tickets_sample_row($conn, $companyId, $garbageErr);
if ($garbageInserted !== 1) {
    vtsd_fail('Expected sample seed to succeed when only generic fallback lookup rows exist; inserted=' . (int)$garbageInserted . ' err=' . $garbageErr);
} else {
    vtsd_pass('Tickets sample seed succeeds when lookup parents only have generic fallback rows.');
}

mysqli_query($conn, 'DELETE FROM tickets WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM ticket_categories WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM ticket_statuses WHERE company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM ticket_priorities WHERE company_id = ' . (int)$companyId);
$_SESSION['company_id'] = 99999;
mysqli_query($conn, 'SET @app_company_id = 99999');

$staleErr = '';
$staleInserted = itm_seed_insert_tickets_sample_row($conn, $companyId, $staleErr);
if ($staleInserted !== 1) {
    vtsd_fail('Expected sample seed to succeed with stale session company_id; inserted=' . (int)$staleInserted . ' err=' . $staleErr);
} else {
    vtsd_pass('Tickets sample seed succeeds when PHP session company_id is stale.');
}

exit($failures === 0 ? 0 : 1);
