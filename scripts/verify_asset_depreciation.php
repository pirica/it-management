<?php
/**
 * Asset lifecycle and depreciation regression checks.
 * CLI: php scripts/verify_asset_depreciation.php
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM'
<code>php scripts/verify_asset_depreciation.php</code> — validates equipment lifecycle columns, depreciation math, and event logging.
ITM;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_asset_depreciation.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify Asset Depreciation');
$nl = itm_script_output_nl();
$failures = 0;

function ad_fail($msg)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $msg, 'fail') . $nl;
}
function ad_pass($msg)
{
    global $nl;
    echo colorText('[PASS] ' . $msg, 'pass') . $nl;
}

$cols = ['lifecycle_stage', 'depreciation_start_date', 'useful_life_months', 'salvage_value', 'disposal_date', 'disposal_reason', 'disposal_pending_at', 'disposal_pending_date', 'disposal_pending_reason', 'disposal_pending_by'];
$colSql = "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'equipment'";
$res = mysqli_query($conn, $colSql);
$found = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $found[] = strtolower((string) ($row['COLUMN_NAME'] ?? $row['column_name'] ?? ''));
}
foreach ($cols as $col) {
    if (!in_array($col, $found, true)) {
        ad_fail('equipment missing column ' . $col);
    }
}
if (count(array_intersect($cols, $found)) === count($cols)) {
    ad_pass('Equipment lifecycle columns present');
}

$res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'equipment_lifecycle_events'");
$row = $res ? mysqli_fetch_assoc($res) : null;
if ((int)($row['c'] ?? 0) < 1) {
    ad_fail('equipment_lifecycle_events table missing');
} else {
    ad_pass('equipment_lifecycle_events table exists');
}

$months = itm_asset_depreciation_months_elapsed('2024-01-15', new DateTimeImmutable('2025-03-10'));
if ($months !== 14) {
    ad_fail('Months elapsed expected 14, got ' . $months);
} else {
    ad_pass('Months elapsed calculation');
}

$calc = itm_asset_depreciation_compute_book_value([
    'purchase_cost' => 1200,
    'salvage_value' => 200,
    'useful_life_months' => 12,
    'depreciation_start_date' => '2025-01-01',
], new DateTimeImmutable('2025-07-01'));
if ((float) $calc['book_value'] < 650 || (float) $calc['book_value'] > 750) {
    ad_fail('Book value out of expected range: ' . $calc['book_value']);
} else {
    ad_pass('Straight-line book value sample');
}

$reportsHelper = dirname(__DIR__) . '/modules/reports/api/helpers.php';
$reportsSource = is_file($reportsHelper) ? (string) file_get_contents($reportsHelper) : '';
if (strpos($reportsSource, 'function get_asset_lifecycle_stage_summary') === false) {
    ad_fail('Reports helper get_asset_lifecycle_stage_summary() missing');
} else {
    ad_pass('Reports helper get_asset_lifecycle_stage_summary() present');
}

if (!function_exists('itm_asset_lifecycle_record_disposal')) {
    ad_fail('itm_asset_lifecycle_record_disposal() missing');
} else {
    ad_pass('itm_asset_lifecycle_record_disposal() loaded');
}

$stages = itm_asset_lifecycle_stages();
if (!isset($stages['written_off'])) {
    ad_fail('written_off lifecycle stage missing from itm_asset_lifecycle_stages()');
} else {
    ad_pass('written_off lifecycle stage registered');
}

foreach (['itm_asset_lifecycle_company_requires_disposal_approval', 'itm_asset_lifecycle_request_disposal', 'itm_asset_lifecycle_approve_pending_disposal', 'itm_asset_lifecycle_submit_disposal'] as $fn) {
    if (!function_exists($fn)) {
        ad_fail('Missing helper ' . $fn . '()');
    } else {
        ad_pass('Helper ' . $fn . '() loaded');
    }
}

$companyColRes = mysqli_query($conn, "SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'companies' AND column_name = 'asset_disposal_approval_required'");
$companyColRow = $companyColRes ? mysqli_fetch_assoc($companyColRes) : null;
if ((int)($companyColRow['c'] ?? 0) < 1) {
    ad_fail('companies.asset_disposal_approval_required column missing');
} else {
    ad_pass('companies.asset_disposal_approval_required column present');
}

$companyId = 1;
$equipRes = mysqli_query($conn, "SELECT id FROM equipment WHERE company_id = {$companyId} AND deleted_at IS NULL AND disposal_date IS NULL AND lifecycle_stage <> 'disposed' ORDER BY id ASC LIMIT 1");
$equipRow = $equipRes ? mysqli_fetch_assoc($equipRes) : null;
$testEquipId = (int)($equipRow['id'] ?? 0);
if ($testEquipId <= 0) {
    $typeRes = mysqli_query($conn, "SELECT id FROM equipment_types WHERE company_id = {$companyId} ORDER BY id ASC LIMIT 1");
    $typeRow = $typeRes ? mysqli_fetch_assoc($typeRes) : null;
    $typeId = (int)($typeRow['id'] ?? 0);
    $statusRes = mysqli_query($conn, "SELECT id FROM equipment_statuses WHERE company_id = {$companyId} ORDER BY id ASC LIMIT 1");
    $statusRow = $statusRes ? mysqli_fetch_assoc($statusRes) : null;
    $statusId = (int)($statusRow['id'] ?? 0);
    if ($typeId > 0 && $statusId > 0) {
        $name = 'MBQA-Disposal-Verify-' . bin2hex(random_bytes(3));
        $nameEsc = mysqli_real_escape_string($conn, $name);
        $insertSql = "INSERT INTO equipment (company_id, equipment_type_id, status_id, name, lifecycle_stage, active)
                      VALUES ({$companyId}, {$typeId}, {$statusId}, '{$nameEsc}', 'in_service', 1)";
        if (itm_run_query($conn, $insertSql)) {
            $testEquipId = (int)mysqli_insert_id($conn);
        }
    }
}
if ($testEquipId <= 0) {
    ad_fail('No equipment row available for disposal test');
} else {
    $reason = 'MBQA disposal verify ' . bin2hex(random_bytes(4));
    $result = itm_asset_lifecycle_record_disposal($conn, $companyId, $testEquipId, date('Y-m-d'), $reason, 1);
    if (empty($result['ok'])) {
        ad_fail('Disposal helper failed: ' . (string)($result['message'] ?? ''));
    } else {
        ad_pass('Disposal helper recorded event for equipment ' . $testEquipId);
        mysqli_query($conn, "DELETE FROM equipment_lifecycle_events WHERE company_id = {$companyId} AND equipment_id = {$testEquipId} AND notes = '" . mysqli_real_escape_string($conn, $reason) . "'");
        $nameRes = mysqli_query($conn, "SELECT name FROM equipment WHERE id = {$testEquipId} AND company_id = {$companyId} LIMIT 1");
        $nameRow = $nameRes ? mysqli_fetch_assoc($nameRes) : null;
        $equipName = (string)($nameRow['name'] ?? '');
        if (strpos($equipName, 'MBQA-Disposal-Verify-') === 0) {
            mysqli_query($conn, "DELETE FROM equipment WHERE id = {$testEquipId} AND company_id = {$companyId} LIMIT 1");
        } else {
            mysqli_query($conn, "UPDATE equipment SET lifecycle_stage = 'in_service', disposal_date = NULL, disposal_reason = NULL WHERE id = {$testEquipId} AND company_id = {$companyId} LIMIT 1");
        }
    }
}

itm_script_output_end();
exit($failures > 0 ? 1 : 0);
