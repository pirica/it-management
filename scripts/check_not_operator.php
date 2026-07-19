<?php
/**
 * Static audit (Warning): unary ! on variables in application PHP.
 *
 * Why: Codacy flags "Operator ! prohibited; use === FALSE instead" for patterns
 * like if (!$ok). This script is informational only — many hits are intentional
 * falsy checks (null, mysqli handles) and must not be bulk-replaced with === false.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_not_operator_audit.php';

$isCli = PHP_SAPI === 'cli';
itm_script_output_begin('Not-operator audit [Warning] (modules/, includes/, config/)');

$nl = itm_script_output_nl();
$includeScripts = false;

if ($isCli) {
    $includeScripts = in_array('--include-scripts', $argv ?? [], true);
} else {
    $includeScripts = isset($_GET['include_scripts']) && (string)$_GET['include_scripts'] === '1';
}

$scanDirs = ['modules', 'includes', 'config'];
if ($includeScripts) {
    $scanDirs[] = 'scripts';
}

$warnings = itm_not_operator_collect_warnings($root, $scanDirs);
$scopeLabel = $includeScripts ? 'modules/, includes/, config/, and scripts/' : 'modules/, includes/, and config/';

if ($warnings === []) {
    echo colorText('PASS: 0 unary ! on $variable warning(s) in ' . $scopeLabel, 'pass') . $nl;
    itm_script_output_end();
    exit(0);
}

echo colorText(
    '[WARNING] Found ' . count($warnings) . ' unary ! on $variable pattern(s) in ' . $scopeLabel . ' (informational — exit 0)',
    'warn'
) . $nl;
foreach ($warnings as $msg) {
    echo colorText('  - ' . $msg, 'warn') . $nl;
}

echo $nl
    . 'Severity: Warning only — do not bulk-replace with === false (null/0/empty string differ from false).' . $nl
    . 'Fix case-by-case: strict boolean false → $var === false; missing row → $var === null or !is_array($var).' . $nl
    . 'Exempt intentional falsy checks: same-line comment itm-not-operator-exempt: with reason.' . $nl
    . 'Note: !function_exists(), !is_array(), !preg_match(), and !== are not flagged.' . $nl;

itm_script_output_end();
exit(0);
