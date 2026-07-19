<?php
/**
 * Static audit: unary ! on variables in application PHP.
 *
 * Why: Codacy flags "Operator ! prohibited; use === FALSE instead" for patterns
 * like if (!$ok); this complements Codacy with a repo-local gate.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_not_operator_audit.php';

$isCli = PHP_SAPI === 'cli';
itm_script_output_begin('Not-operator audit (modules/, includes/, config/)');

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

$scanDirs = ['modules', 'includes', 'config'];
if ($includeScripts) {
    $scanDirs[] = 'scripts';
}

$violations = itm_not_operator_collect_violations($root, $scanDirs);
$scopeLabel = $includeScripts ? 'modules/, includes/, config/, and scripts/' : 'modules/, includes/, and config/';

if ($violations === []) {
    echo 'PASS: 0 unary ! on $variable pattern(s) in ' . $scopeLabel . $nl;
    itm_script_output_end();
    exit(0);
}

echo 'Found ' . count($violations) . ' unary ! on $variable pattern(s) in ' . $scopeLabel . ':' . $nl;
foreach ($violations as $msg) {
    echo '  - ' . $msg . $nl;
}

echo $nl
    . 'Fix: when testing strict boolean false, use $var === false (lowercase false per PSR). When testing general falsiness (null, 0, empty string), keep ! or compare explicitly.' . $nl
    . 'Exempt: same-line comment itm-not-operator-exempt: with reason.' . $nl
    . 'Note: !function_exists(), !is_array(), !preg_match(), and !== are not flagged.' . $nl;

if (!$strict) {
    echo $nl . 'INFO: default run is informational (exit 0). Re-run with --strict to fail the check.' . $nl;
    itm_script_output_end();
    exit(0);
}

itm_script_output_end();
exit(1);
