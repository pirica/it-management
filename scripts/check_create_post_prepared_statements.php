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
Browser: plain-text inventory (Administrator). CLI: <code>php scripts/check_create_post_prepared_statements.php</code> — default informational (exit <code>0</code>); <code>--strict</code> / <code>?strict=1</code> exits <code>1</code> when <code>escape_sql</code>, <code>scaffold_string_sql</code>, or <code>bespoke_string_sql</code> remain. Optional <code>--module=slug</code>, <code>--verbose</code> (all buckets), <code>--list-scaffold</code> (scaffold rows only), <code>--json</code> / <code>?json=1</code>. Run after changing module create save paths.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_create_post_prepared_statement_audit.php';
require_once __DIR__ . '/lib/script_browser_nav.php';

$nl = itm_check_script_begin_browser_admin('Create POST prepared-statement audit');

$root = dirname(__DIR__);
$strict = false;
$moduleSlug = null;
$verbose = false;
$listScaffold = false;
$asJson = false;

if (PHP_SAPI === 'cli') {
    $argvLocal = $argv ?? [];
    $strict = in_array('--strict', $argvLocal, true);
    $verbose = in_array('--verbose', $argvLocal, true);
    $listScaffold = in_array('--list-scaffold', $argvLocal, true);
    $asJson = in_array('--json', $argvLocal, true);
    foreach ($argvLocal as $arg) {
        if (strpos($arg, '--module=') === 0) {
            $moduleSlug = substr($arg, strlen('--module='));
        }
    }
} else {
    $strict = isset($_GET['strict']) && (string)$_GET['strict'] === '1';
    $verbose = isset($_GET['verbose']) && (string)$_GET['verbose'] === '1';
    $listScaffold = isset($_GET['list_scaffold']) && (string)$_GET['list_scaffold'] === '1';
    $asJson = isset($_GET['json']) && (string)$_GET['json'] !== '0';
    if (isset($_GET['module']) && (string)$_GET['module'] !== '') {
        $moduleSlug = (string)$_GET['module'];
    }
}

$result = itm_create_post_prepared_audit_scan($root, $moduleSlug);
$byStatus = $result['by_status'];
$findings = $result['findings'];
$summary = $result['summary'] ?? [];

if ($asJson) {
    if (!itm_script_access_is_cli() && !headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . $nl;
    itm_script_output_end();
    exit($strict && count($findings) > 0 ? 1 : 0);
}

$labels = itm_create_post_prepared_audit_status_labels();
$defaultListStatuses = itm_create_post_prepared_audit_status_list_verbose_by_default();

echo 'Create POST prepared-statement audit' . $nl;
echo '[INFO] Scanned ' . (int)$result['scanned'] . ' modules/*/create.php file(s).' . $nl;
if ($moduleSlug !== null && $moduleSlug !== '') {
    echo '[INFO] Filter: module=' . $moduleSlug . $nl;
}
echo '[INFO] Summary: escape_sql=' . (int)($summary['escape_sql'] ?? 0)
    . ' | scaffold_string_sql=' . (int)($summary['scaffold_string_sql'] ?? 0)
    . ' | bespoke_string_sql=' . (int)($summary['bespoke_string_sql'] ?? 0)
    . ' | prepared=' . (int)($summary['prepared'] ?? 0)
    . ' | wrapper=' . (int)($summary['wrapper'] ?? 0)
    . ' | no_local_post_save=' . (int)($summary['no_local_post_save'] ?? 0) . $nl;
echo $nl;

foreach ($labels as $status => $label) {
    $rows = $byStatus[$status] ?? [];
    if ($rows === []) {
        continue;
    }

    $listRows = $verbose
        || in_array($status, $defaultListStatuses, true)
        || ($status === 'scaffold_string_sql' && $listScaffold);

    echo $label . ' ' . $status . ' (' . count($rows) . ')' . $nl;
    if ($listRows) {
        foreach ($rows as $row) {
            $pathLabel = itm_script_format_modules_file_local_dev_link((string)$row['path']);
            echo '  - ' . $pathLabel . ' — ' . $row['reason'] . $nl;
        }
        echo $nl;
    } elseif ($status === 'scaffold_string_sql') {
        echo '  (collapsed — use --list-scaffold or --verbose to list all scaffold modules)' . $nl . $nl;
    }
}

if (!$verbose) {
    $wrapperCount = count($byStatus['wrapper'] ?? []);
    $infoCount = count($byStatus['no_local_post_save'] ?? []);
    $hints = [];
    if ($wrapperCount > 0 || $infoCount > 0) {
        $hints[] = '--verbose lists wrapper (' . $wrapperCount . ') and no_local_post_save (' . $infoCount . ') rows';
    }
    $scaffoldCount = count($byStatus['scaffold_string_sql'] ?? []);
    if ($scaffoldCount > 0 && !$listScaffold) {
        $hints[] = '--list-scaffold lists all scaffold_string_sql (' . $scaffoldCount . ') rows';
    }
    if ($hints !== []) {
        echo '[INFO] ' . implode('; ', $hints) . '.' . $nl . $nl;
    }
}

if ((int)($summary['escape_sql'] ?? 0) === 0) {
    echo 'PASS: no escape_sql() on scanned create.php files.' . $nl;
}

if ($findings === []) {
    itm_script_output_end();
    exit(0);
}

echo 'Legacy POST save SQL: ' . count($findings) . ' file(s)'
    . ' (scaffold ' . (int)($summary['scaffold_string_sql'] ?? 0)
    . ', bespoke ' . (int)($summary['bespoke_string_sql'] ?? 0) . ').' . $nl;
echo 'Fix: convert INSERT/UPDATE to mysqli_prepare + mysqli_stmt_bind_param (see '
    . itm_script_format_modules_file_local_dev_link('modules/tickets/create.php')
    . ').' . $nl;
echo 'Wrapper modules: audit modules/{slug}/index.php POST save separately.' . $nl;

if (!$strict) {
    echo $nl . 'INFO: default run is informational (exit 0). Re-run with --strict to fail the check.' . $nl;
    itm_script_output_end();
    exit(0);
}

itm_script_output_end();
exit(1);
