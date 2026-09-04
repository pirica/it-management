<?php
/**
 * Static audit: modules create.php POST save SQL (escape_sql, $sqlValues scaffold, prepared).
 *
 * Why: Complements check_sql_injection_coverage.php — flags legacy escape_sql and
 * flattened CRUD string INSERT/UPDATE on create handlers even when escaped.
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: plain-text inventory. CLI: <code>php scripts/check_create_post_prepared_statements.php</code> — default informational (exit <code>0</code>); <code>--strict</code> / <code>?strict=1</code> exits <code>1</code> when <code>escape_sql</code>, <code>scaffold_string_sql</code>, or <code>bespoke_string_sql</code> remain. Optional <code>--module=slug</code>, <code>--verbose</code> (list wrapper/skip rows). Run after changing module create save paths.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_create_post_prepared_statement_audit.php';

$nl = itm_check_script_begin_browser_admin('Create POST prepared-statement audit');

$root = dirname(__DIR__);
$strict = false;
$moduleSlug = null;
$verbose = false;

if (PHP_SAPI === 'cli') {
    $strict = in_array('--strict', $argv ?? [], true);
    $verbose = in_array('--verbose', $argv ?? [], true);
    foreach ($argv ?? [] as $arg) {
        if (strpos($arg, '--module=') === 0) {
            $moduleSlug = substr($arg, strlen('--module='));
        }
    }
} else {
    $strict = isset($_GET['strict']) && (string)$_GET['strict'] === '1';
    $verbose = isset($_GET['verbose']) && (string)$_GET['verbose'] === '1';
    if (isset($_GET['module']) && (string)$_GET['module'] !== '') {
        $moduleSlug = (string)$_GET['module'];
    }
}

$result = itm_create_post_prepared_audit_scan($root, $moduleSlug);
$byStatus = $result['by_status'];
$findings = $result['findings'];

echo '[INFO] Scanned ' . (int)$result['scanned'] . ' modules/*/create.php file(s).' . $nl . $nl;

$labels = [
    'escape_sql' => '[FAIL]',
    'scaffold_string_sql' => '[WARN]',
    'bespoke_string_sql' => '[WARN]',
    'prepared' => '[OK]',
    'wrapper' => '[SKIP]',
    'no_local_post_save' => '[INFO]',
];

foreach ($labels as $status => $label) {
    $rows = $byStatus[$status] ?? [];
    if ($rows === []) {
        continue;
    }

    $listVerbosely = $verbose
        || in_array($status, ['escape_sql', 'scaffold_string_sql', 'bespoke_string_sql', 'prepared'], true);

    echo $label . ' ' . $status . ' (' . count($rows) . ')' . $nl;
    if ($listVerbosely) {
        foreach ($rows as $row) {
            echo '  - ' . $row['path'] . ' — ' . $row['reason'] . $nl;
        }
        echo $nl;
    }
}

if (!$verbose) {
    $wrapperCount = count($byStatus['wrapper'] ?? []);
    $infoCount = count($byStatus['no_local_post_save'] ?? []);
    if ($wrapperCount > 0 || $infoCount > 0) {
        echo '[INFO] Use --verbose to list all wrapper (' . $wrapperCount . ') and no_local_post_save (' . $infoCount . ') modules.' . $nl . $nl;
    }
}

if ($findings === []) {
    echo 'PASS: no escape_sql, scaffold_string_sql, or bespoke_string_sql on scanned create.php files.' . $nl;
    itm_script_output_end();
    exit(0);
}

echo 'Found ' . count($findings) . ' create.php file(s) still using legacy POST save SQL.' . $nl;
echo 'Fix: convert INSERT/UPDATE to mysqli_prepare + mysqli_stmt_bind_param (see modules/tickets/create.php).' . $nl;
echo 'Wrapper modules: audit modules/{slug}/index.php POST save separately.' . $nl;

if (!$strict) {
    echo $nl . 'INFO: default run is informational (exit 0). Re-run with --strict to fail the check.' . $nl;
    itm_script_output_end();
    exit(0);
}

itm_script_output_end();
exit(1);
