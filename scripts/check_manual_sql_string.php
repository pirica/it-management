<?php
/**
 * Static audit: manually-constructed SQL strings in module PHP.
 *
 * Why: Complements check_sql_injection_coverage.php by flagging SQL built via
 * concatenation/interpolation; excludes URL http_build_query false positives.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_manual_sql_string_audit.php';

$isCli = PHP_SAPI === 'cli';
itm_script_output_begin('Manual SQL string audit (modules/)');

$nl = itm_script_output_nl();
$strict = false;
$includeScripts = false;

if ($isCli) {
    $strict = in_array('--strict', $argv ?? [], true);
    $includeScripts = in_array('--include-scripts', $argv ?? [], true);
} else {
    $strict = isset($_GET['strict']) && (string)$_GET['strict'] === '1';
    $includeScripts = isset($_GET['include_scripts']) && (string)$_GET['include_scripts'] === '1';
}

$scanDirs = ['modules'];
if ($includeScripts) {
    $scanDirs[] = 'scripts';
}

$violations = itm_manual_sql_string_collect_violations($root, $scanDirs);
$scopeLabel = $includeScripts ? 'modules/ and scripts/' : 'modules/';

if ($violations === []) {
    echo 'PASS: 0 manual SQL string pattern(s) in ' . $scopeLabel . $nl;
    itm_script_output_end();
    exit(0);
}

echo 'Found ' . count($violations) . ' manual SQL string pattern(s) in ' . $scopeLabel . ':' . $nl;
foreach ($violations as $msg) {
    echo '  - ' . $msg . $nl;
}

echo $nl
    . 'Fix: use mysqli_prepare() + mysqli_stmt_bind_param() or bound itm_run_query() patterns.' . $nl
    . 'Exempt: same-line comment itm-manual-sql-exempt: with reason.' . $nl
    . 'URL hrefs: http_build_query() / .php? query strings are not SQL and are excluded.' . $nl;

if (!$strict) {
    echo $nl . 'INFO: default run is informational (exit 0). Re-run with --strict to fail the check.' . $nl;
    itm_script_output_end();
    exit(0);
}

itm_script_output_end();
exit(1);
