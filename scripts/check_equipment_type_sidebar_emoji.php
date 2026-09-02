<?php
/**
 * Static gate: canonical equipment-type sidebar labels and db/02_data.sql emoji seeds.
 *
 * CLI: php scripts/check_equipment_type_sidebar_emoji.php
 * Browser: scripts/check_equipment_type_sidebar_emoji.php?run=1
 *
 * Why: ???? sidebar labels from bad UTF-8 SQL import are not visible in source mojibake scans alone.
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/check_equipment_type_sidebar_emoji.php</code> — exit <code>1</code> when canonical <code>is_*</code> labels or <code>equipment_types.field_edit_emoji</code> seeds in <code>db/02_data.sql</code> are missing or question-mark corrupted. Tier 2 static batch (no MySQL).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_utf8_emoji_corruption.php';

itm_script_output_begin('Check equipment-type sidebar emoji');

$nl = itm_script_output_nl();
$root = dirname(__DIR__);
$failures = itm_utf8_audit_equipment_sidebar_sources($root);

if ($failures === []) {
    echo colorText('[PASS] Canonical is_* sidebar labels and equipment_types seeds are valid UTF-8 emoji.', 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

foreach ($failures as $failure) {
    echo colorText('[FAIL] ' . $failure, 'fail') . $nl;
}

echo $nl . colorText('Found ' . count($failures) . ' equipment sidebar / seed emoji issue(s).', 'fail') . $nl;
itm_script_output_end();
exit(1);
