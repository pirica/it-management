<?php
/**
 * Settings UI locale format regression (ui_configuration money + date/time).
 *
 * CLI: php scripts/verify_ui_locale_format.php
 * Browser: scripts/verify_ui_locale_format.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: <a href="verify_ui_locale_format.php?run=1">verify_ui_locale_format.php?run=1</a> (Administrator). CLI: <code>php scripts/verify_ui_locale_format.php</code> — exit <code>1</code> on failure.
<p>Validates <code>ui_configuration</code> locale columns, Settings UI Configuration form fields, save wiring, and <code>includes/itm_ui_locale_format.php</code> display helpers.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_ui_locale_format.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('UI locale format (Settings)');

$fail = 0;
function ui_locale_fail(string $msg): void
{
    global $fail;
    $fail++;
    echo "[FAIL] {$msg}\n";
}
function ui_locale_pass(string $msg): void
{
    echo "[PASS] {$msg}\n";
}

$schemaSql = file_get_contents(ROOT_PATH . 'db/01_schema.sql');
if ($schemaSql === false) {
    ui_locale_fail('Unable to read db/01_schema.sql');
} else {
    foreach ([
        'ui_money_symbol',
        'ui_money_symbol_suffix',
        'ui_money_symbol_prefix',
        'ui_date_format',
        'ui_time_format',
        'ui_datetime_european1_enabled',
        'ui_datetime_european2_enabled',
        'ui_datetime_iso_enabled',
        'ui_datetime_readable_enabled',
        'ui_datetime_format_default',
    ] as $column) {
        if (strpos($schemaSql, '`' . $column . '`') === false) {
            ui_locale_fail('db/01_schema.sql missing ui_configuration.' . $column);
        } else {
            ui_locale_pass('Schema defines ui_configuration.' . $column);
        }
    }
}

$settingsSource = file_get_contents(ROOT_PATH . 'modules/settings/index.php');
if ($settingsSource === false) {
    ui_locale_fail('Unable to read modules/settings/index.php');
} else {
    foreach ([
        'ui_money_symbol',
        'ui_money_symbol_suffix',
        'ui_money_symbol_prefix',
        'ui_date_format',
        'ui_time_format',
        'ui_datetime_european1_enabled',
        'ui_datetime_european2_enabled',
        'ui_datetime_iso_enabled',
        'ui_datetime_readable_enabled',
        'ui_datetime_format_default',
        'itm_ui_locale_normalize_post_values',
    ] as $needle) {
        if (strpos($settingsSource, $needle) === false) {
            ui_locale_fail('modules/settings/index.php missing ' . $needle);
        } else {
            ui_locale_pass('Settings index references ' . $needle);
        }
    }
}

$sampleConfig = [
    'ui_money_symbol' => 'EUR',
    'ui_money_symbol_suffix' => 1,
    'ui_money_symbol_prefix' => 0,
    'ui_date_format' => 'us_mmddyyyy',
    'ui_time_format' => 'h24',
    'ui_datetime_european2_enabled' => 1,
    'ui_datetime_format_default' => 'european2',
];
if (itm_ui_locale_format_date_display('2026-08-17', $sampleConfig) !== '08/17/2026') {
    ui_locale_fail('US date format display failed');
} else {
    ui_locale_pass('US date format display');
}

if (itm_ui_locale_format_money_display(69.5, $sampleConfig) !== '69.50€') {
    ui_locale_fail('Suffix money format failed');
} else {
    ui_locale_pass('Suffix money format');
}

$prefixConfig = $sampleConfig;
$prefixConfig['ui_money_symbol_suffix'] = 0;
$prefixConfig['ui_money_symbol_prefix'] = 1;
if (itm_ui_locale_format_money_display(69.5, $prefixConfig) !== '€69.50') {
    ui_locale_fail('Prefix money format failed');
} else {
    ui_locale_pass('Prefix money format');
}

if (itm_ui_locale_format_datetime_display('2026-08-17 22:58:00', $sampleConfig) !== '17/Aug/2026 22:58') {
    ui_locale_fail('European datetime2 display failed');
} else {
    ui_locale_pass('European datetime2 display');
}

$post = [
    'ui_money_symbol' => 'EUR',
    'ui_money_symbol_prefix' => '1',
    'ui_date_format' => 'iso_yyyymmdd',
    'ui_time_format' => 'h12',
    'ui_datetime_european1_enabled' => '1',
    'ui_datetime_format_default' => 'european1',
];
$normalized = itm_ui_locale_normalize_post_values($post);
if (!empty($normalized['errors'])) {
    ui_locale_fail('Normalize returned errors: ' . implode('; ', $normalized['errors']));
} elseif (($normalized['values']['ui_date_format'] ?? '') !== 'iso_yyyymmdd') {
    ui_locale_fail('Normalize date format mismatch');
} elseif (!empty($normalized['values']['ui_money_symbol_suffix']) || empty($normalized['values']['ui_money_symbol_prefix'])) {
    ui_locale_fail('Prefix selection should clear suffix flag and set prefix');
} else {
    ui_locale_pass('POST normalize enforces prefix/suffix and date format');
}

itm_script_output_end($fail === 0 ? 0 : 1);
