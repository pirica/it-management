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

$cols = ['lifecycle_stage', 'depreciation_start_date', 'useful_life_months', 'salvage_value', 'disposal_date', 'disposal_reason'];
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

itm_script_output_end($failures === 0 ? 0 : 1);
