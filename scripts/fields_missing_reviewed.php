<?php
/**
 * Reviewed bespoke gate exceptions for fields_missing.php (read-only manifest).
 *
 * Data file: scripts/data/fields_missing_reviewed.json
 *
 * Browser: scripts/fields_missing_reviewed.php (Admin). CLI: php scripts/fields_missing_reviewed.php [--json]
 */
declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_fields_missing_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

$jsonOutput = false;
if ($itmIsCli) {
    foreach ($GLOBALS['argv'] ?? [] as $arg) {
        if ((string) $arg === '--json') {
            $jsonOutput = true;
        }
    }
} elseif (isset($_GET['json'])) {
    $jsonOutput = true;
}

$registry = itm_fields_missing_load_reviewed_registry();
$validation = itm_fields_missing_validate_reviewed_registry($registry);

if ($jsonOutput) {
    if (!$itmIsCli) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(
        [
            'registry' => $registry,
            'validation' => $validation,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit($validation['ok'] ? 0 : 1);
}

itm_script_output_begin('Fields missing — reviewed bespoke gate exceptions');

if (!$itmIsCli) {
    itm_script_output_close_pre();
    echo '<h1>Fields missing — reviewed bespoke gate exceptions</h1>';
    echo '<p>Read-only manifest consumed by <a href="fields_missing.php">fields_missing.php</a>. '
        . 'Matching bespoke <code>[SKIP][fail]</code> lines print as <code>[SKIP][fail][reviewed]</code>.</p>';
    echo '<p><a href="fields_missing_reviewed.php?json=1">JSON</a></p><pre>';
}

$nl = "\n";
$path = itm_fields_missing_reviewed_registry_path();
echo itm_script_escape_browser_pre_text('Registry: ' . $path) . $nl;
echo itm_script_escape_browser_pre_text('Version: ' . (int) ($registry['version'] ?? 0)) . $nl;
echo itm_script_escape_browser_pre_text(trim((string) ($registry['description'] ?? ''))) . $nl . $nl;

if (!$validation['ok']) {
    foreach ($validation['errors'] as $error) {
        echo colorText(itm_script_escape_browser_pre_text('[FAIL] ' . $error), 'fail') . $nl;
    }
    itm_script_output_end();
    exit(1);
}

$moduleCount = count($registry['modules'] ?? []);
$checkCount = 0;
foreach ($registry['modules'] ?? [] as $moduleEntry) {
    if (!is_array($moduleEntry)) {
        continue;
    }
    $checkCount += count($moduleEntry['checks'] ?? []);
}

echo itm_script_escape_browser_pre_text('Modules: ' . $moduleCount . ' · reviewed checks: ' . $checkCount) . $nl . $nl;

foreach ($registry['modules'] ?? [] as $moduleSlug => $moduleEntry) {
    if (!is_array($moduleEntry)) {
        continue;
    }
    echo itm_script_escape_browser_pre_text(str_repeat('-', 72)) . $nl;
    echo itm_script_escape_browser_pre_text((string) $moduleSlug) . $nl;
    $reviewedAt = trim((string) ($moduleEntry['reviewed_at'] ?? ''));
    if ($reviewedAt !== '') {
        echo itm_script_escape_browser_pre_text('  reviewed_at: ' . $reviewedAt) . $nl;
    }
    $reason = trim((string) ($moduleEntry['reason'] ?? ''));
    if ($reason !== '') {
        echo itm_script_escape_browser_pre_text('  reason: ' . $reason) . $nl;
    }
    foreach ($moduleEntry['checks'] ?? [] as $checkEntry) {
        if (!is_array($checkEntry)) {
            continue;
        }
        $label = trim((string) ($checkEntry['check'] ?? ''));
        $code = trim((string) ($checkEntry['code'] ?? ''));
        $note = trim((string) ($checkEntry['note'] ?? ''));
        $line = '  - ' . $label;
        if ($code !== '') {
            $line .= ' (' . $code . ')';
        }
        if ($note !== '') {
            $line .= ' — ' . $note;
        }
        echo itm_script_escape_browser_pre_text($line) . $nl;
    }
    echo $nl;
}

echo colorText(itm_script_escape_browser_pre_text('Result: registry valid.'), 'pass') . $nl;
itm_script_output_end();
exit(0);
