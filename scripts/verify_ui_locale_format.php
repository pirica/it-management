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
<p>Validates <code>ui_configuration</code> locale columns, Settings UI Configuration form fields, save wiring, <code>includes/itm_ui_locale_format.php</code> display helpers, date-format flips via <code>$GLOBALS['ui_config']</code>, audit-pass module cross-check, and money formatting in <code>itm_format_cell_scalar_display()</code>.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_ui_locale_format.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_module_date_format_display_audit.php';

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

// Why: Modules read locale via $GLOBALS['ui_config'] through itm_format_date_display() / cell scalar hook.
$sampleYmd = '2026-08-17';
$formatCycles = [
    ['ui_date_format' => 'european_ddmmmyyyy', 'expected' => '17/Aug/2026'],
    ['ui_date_format' => 'us_mmddyyyy', 'expected' => '08/17/2026'],
    ['ui_date_format' => 'iso_yyyymmdd', 'expected' => '2026-08-17'],
    ['ui_date_format' => 'european_ddmmyyyy', 'expected' => '17/08/2026'],
];
$savedUiConfig = $GLOBALS['ui_config'] ?? null;
$baseUiConfig = is_array($savedUiConfig) ? $savedUiConfig : [];
foreach ($formatCycles as $index => $cycle) {
    $GLOBALS['ui_config'] = array_merge($baseUiConfig, ['ui_date_format' => $cycle['ui_date_format']]);
    $viaDateHelper = itm_format_date_display($sampleYmd);
    $viaCellScalar = itm_format_cell_scalar_display('purchase_date', $sampleYmd);
    if ($viaDateHelper !== $cycle['expected']) {
        ui_locale_fail('Format cycle ' . ($index + 1) . ' itm_format_date_display expected ' . $cycle['expected'] . ' got ' . $viaDateHelper);
    } elseif ($viaCellScalar !== $cycle['expected']) {
        ui_locale_fail('Format cycle ' . ($index + 1) . ' itm_format_cell_scalar_display expected ' . $cycle['expected'] . ' got ' . $viaCellScalar);
    } else {
        ui_locale_pass('Format cycle ' . ($index + 1) . ' (' . $cycle['ui_date_format'] . ') => ' . $cycle['expected']);
    }
}
if ($savedUiConfig !== null) {
    $GLOBALS['ui_config'] = $savedUiConfig;
} else {
    unset($GLOBALS['ui_config']);
}

$repoRoot = dirname(__DIR__);
$warnRows = itm_module_date_format_display_audit_run([
    'root' => $repoRoot,
    'only_warn' => true,
    'show_module_skips' => false,
]);
$allSlugs = itm_module_date_format_display_audit_list_module_slugs($repoRoot);
$passModules = itm_module_date_format_display_audit_pass_module_slugs($allSlugs, $warnRows);
if (count($passModules) < 100) {
    ui_locale_fail('Audit pass module count low (' . count($passModules) . '); expected at least 100 OK modules');
} else {
    ui_locale_pass('Date display audit reports ' . count($passModules) . ' OK modules (no WARN)');
}

$spotModules = ['license_management', 'equipment', 'tickets', 'expenses', 'catalogs'];
$spotChecked = 0;
foreach ($spotModules as $slug) {
    if (!in_array($slug, $passModules, true)) {
        continue;
    }
    $moduleFiles = itm_module_date_format_display_audit_collect_module_files($repoRoot, $slug);
    if ($moduleFiles === []) {
        ui_locale_fail('Audit-pass module has no PHP entry files: ' . $slug);
        continue;
    }
    $usesHelper = false;
    foreach ($moduleFiles as $filePath) {
        $src = (string) @file_get_contents($filePath);
        if ($src !== '' && (
            strpos($src, 'itm_format_date_display') !== false
            || strpos($src, 'itm_format_cell_scalar_display') !== false
            || strpos($src, 'itm_format_datetime_display') !== false
        )) {
            $usesHelper = true;
            break;
        }
    }
    if (!$usesHelper) {
        ui_locale_fail('Audit-pass module ' . $slug . ' lacks date display helpers in scanned PHP');
        continue;
    }
    $spotChecked++;
    ui_locale_pass('Audit-pass spot check: modules/' . $slug . ' uses locale date helpers');
}
if ($spotChecked < 3) {
    ui_locale_fail('Expected at least 3 spot-checked audit-pass modules, got ' . $spotChecked);
}

// Second format flip on a due_date field (common on tickets/todo pass modules).
$GLOBALS['ui_config'] = array_merge($baseUiConfig, ['ui_date_format' => 'us_mmddyyyy']);
$usDue = itm_format_cell_scalar_display('due_date', $sampleYmd);
$GLOBALS['ui_config'] = array_merge($baseUiConfig, ['ui_date_format' => 'european_ddmmmyyyy']);
$euDue = itm_format_cell_scalar_display('due_date', $sampleYmd);
if ($savedUiConfig !== null) {
    $GLOBALS['ui_config'] = $savedUiConfig;
} else {
    unset($GLOBALS['ui_config']);
}
if ($usDue !== '08/17/2026' || $euDue !== '17/Aug/2026') {
    ui_locale_fail('due_date flip expected US then EU, got US=' . $usDue . ' EU=' . $euDue);
} else {
    ui_locale_pass('due_date cell scalar flips US => EU when ui_date_format changes');
}

$GLOBALS['ui_config'] = array_merge($baseUiConfig, [
    'ui_money_symbol' => 'EUR',
    'ui_money_symbol_suffix' => 1,
    'ui_money_symbol_prefix' => 0,
]);
$suffixPrice = itm_format_cell_scalar_display('price', '69.5');
$GLOBALS['ui_config'] = array_merge($baseUiConfig, [
    'ui_money_symbol' => 'EUR',
    'ui_money_symbol_suffix' => 0,
    'ui_money_symbol_prefix' => 1,
]);
$prefixPrice = itm_format_cell_scalar_display('purchase_cost', '69.5');
if ($savedUiConfig !== null) {
    $GLOBALS['ui_config'] = $savedUiConfig;
} else {
    unset($GLOBALS['ui_config']);
}
if ($suffixPrice !== '69.50€' || $prefixPrice !== '€69.50') {
    ui_locale_fail('Money cell scalar expected suffix 69.50€ and prefix €69.50, got price=' . $suffixPrice . ' purchase_cost=' . $prefixPrice);
} else {
    ui_locale_pass('Money cell scalar respects suffix/prefix ui_configuration');
}

itm_script_output_end($fail === 0 ? 0 : 1);
