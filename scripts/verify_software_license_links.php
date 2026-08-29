<?php
/**
 * Software catalog ↔ license_management bidirectional link regression.
 *
 * Browser: scripts/verify_software_license_links.php?run=1 (Administrator session).
 * CLI: php scripts/verify_software_license_links.php
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_software_license_links.php</code> — schema, sync from software and license forms, list helpers (including equipment listing), soft-delete unlink. Run after changing <code>includes/itm_software_license_link.php</code>, License Management Equipment tab, or <code>software_license_links</code> DDL.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Software ↔ License link verification');
$nl = itm_script_output_nl();
$failures = 0;

function sll_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function sll_pass($message)
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    sll_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

if (!function_exists('itm_software_license_tables_ready') || !itm_software_license_tables_ready($conn)) {
    sll_fail('software_license_links table missing — import db/ or run migration software_license_links.sql');
    itm_script_output_end();
    exit(1);
}
sll_pass('software_license_links table present');

$companyId = 1;
$employeeId = 1;
$tag = 'SLL-VERIFY-' . date('YmdHis');
$softwareId = 0;
$licenseId = 0;

$softwareName = $tag . '-SW';
$licenseName = $tag . '-LM';

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO software (company_id, name, active, created_by, updated_by)
     VALUES (?, ?, 1, ?, ?)'
);
if (!$stmt) {
    sll_fail('Unable to seed software row');
    itm_script_output_end();
    exit(1);
}
mysqli_stmt_bind_param($stmt, 'isii', $companyId, $softwareName, $employeeId, $employeeId);
if (!mysqli_stmt_execute($stmt)) {
    sll_fail('Software seed insert failed');
    itm_script_output_end();
    exit(1);
}
$softwareId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

$typeId = 0;
$typeRes = mysqli_query($conn, 'SELECT id FROM license_types WHERE company_id = ' . (int)$companyId . ' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
if ($typeRes && ($typeRow = mysqli_fetch_assoc($typeRes))) {
    $typeId = (int)($typeRow['id'] ?? 0);
}
if ($typeId <= 0) {
    sll_fail('No license_types row for company 1');
    itm_script_output_end();
    exit(1);
}

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO license_management (company_id, name, license_type_id, quantity, active, created_by, updated_by)
     VALUES (?, ?, ?, 1, 1, ?, ?)'
);
if (!$stmt) {
    sll_fail('Unable to seed license row');
    itm_script_output_end();
    exit(1);
}
mysqli_stmt_bind_param($stmt, 'isiii', $companyId, $licenseName, $typeId, $employeeId, $employeeId);
if (!mysqli_stmt_execute($stmt)) {
    sll_fail('License seed insert failed');
    itm_script_output_end();
    exit(1);
}
$licenseId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if ($softwareId <= 0 || $licenseId <= 0) {
    sll_fail('Seed ids missing');
    itm_script_output_end();
    exit(1);
}

$syncError = itm_software_license_sync_for_software($conn, $companyId, $softwareId, [$licenseId], $employeeId);
if ($syncError !== '') {
    sll_fail('Sync from software failed: ' . $syncError);
} else {
    sll_pass('Sync from software linked license id ' . $licenseId);
}

$licenseIds = itm_software_license_ids_for_software($conn, $companyId, $softwareId);
if (in_array($licenseId, $licenseIds, true)) {
    sll_pass('ids_for_software returns linked license');
} else {
    sll_fail('ids_for_software missing license ' . json_encode($licenseIds));
}

$softwareIds = itm_software_license_ids_for_license($conn, $companyId, $licenseId);
if (in_array($softwareId, $softwareIds, true)) {
    sll_pass('ids_for_license returns linked software');
} else {
    sll_fail('ids_for_license missing software ' . json_encode($softwareIds));
}

$listForSoftware = itm_software_license_list_for_software($conn, $companyId, $softwareId);
if (count($listForSoftware) === 1 && (int)($listForSoftware[0]['id'] ?? 0) === $licenseId) {
    sll_pass('list_for_software returns one license row');
} else {
    sll_fail('list_for_software unexpected ' . json_encode($listForSoftware));
}

$listForLicense = itm_software_license_list_for_license($conn, $companyId, $licenseId);
if (count($listForLicense) === 1 && (int)($listForLicense[0]['id'] ?? 0) === $softwareId) {
    sll_pass('list_for_license returns one software row');
} else {
    sll_fail('list_for_license unexpected ' . json_encode($listForLicense));
}

$syncError = itm_software_license_sync_for_software($conn, $companyId, $softwareId, [], $employeeId);
if ($syncError !== '') {
    sll_fail('Unlink from software failed: ' . $syncError);
} elseif (itm_software_license_ids_for_software($conn, $companyId, $softwareId) === []) {
    sll_pass('Unlink from software cleared active links');
} else {
    sll_fail('Unlink from software left active links');
}

$syncError = itm_software_license_sync_for_license($conn, $companyId, $licenseId, [$softwareId], $employeeId);
if ($syncError !== '') {
    sll_fail('Sync from license failed: ' . $syncError);
} else {
    sll_pass('Sync from license restored link');
}

$eqId = 0;
$eqRes = mysqli_query($conn, 'SELECT id FROM equipment WHERE company_id = ' . (int)$companyId . ' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
if ($eqRes && ($eqRow = mysqli_fetch_assoc($eqRes))) {
    $eqId = (int)($eqRow['id'] ?? 0);
}
if ($eqId > 0 && function_exists('itm_software_license_list_equipment')) {
    $linkEq = mysqli_prepare(
        $conn,
        'INSERT IGNORE INTO equipment_software (company_id, equipment_id, software_id, active, created_by, updated_by)
         VALUES (?, ?, ?, 1, ?, ?)'
    );
    if ($linkEq) {
        mysqli_stmt_bind_param($linkEq, 'iiiii', $companyId, $eqId, $softwareId, $employeeId, $employeeId);
        if (mysqli_stmt_execute($linkEq)) {
            $eqList = itm_software_license_list_equipment($conn, $companyId, $softwareId);
            $foundEq = false;
            foreach ($eqList as $eqItem) {
                if ((int)($eqItem['id'] ?? 0) === $eqId) {
                    $foundEq = true;
                    break;
                }
            }
            if ($foundEq) {
                sll_pass('list_equipment returns linked asset for software filter');
            } else {
                sll_fail('list_equipment missing equipment id ' . $eqId);
            }
        } else {
            sll_fail('Unable to attach disposable equipment_software row: ' . mysqli_stmt_error($linkEq));
        }
        mysqli_stmt_close($linkEq);
        mysqli_query($conn, 'DELETE FROM equipment_software WHERE company_id = ' . (int)$companyId . ' AND equipment_id = ' . (int)$eqId . ' AND software_id = ' . (int)$softwareId);
    } else {
        sll_fail('Unable to prepare equipment_software insert');
    }
} else {
    sll_pass('list_equipment skipped (no live equipment row for company 1)');
}

mysqli_query($conn, 'DELETE FROM software_license_links WHERE company_id = ' . (int)$companyId . ' AND software_id = ' . (int)$softwareId);
mysqli_query($conn, 'DELETE FROM license_management WHERE id = ' . (int)$licenseId . ' AND company_id = ' . (int)$companyId);
mysqli_query($conn, 'DELETE FROM software WHERE id = ' . (int)$softwareId . ' AND company_id = ' . (int)$companyId);

if ($failures === 0) {
    sll_pass('All software ↔ license link checks passed');
}

itm_script_output_end();
exit($failures > 0 ? 1 : 0);
