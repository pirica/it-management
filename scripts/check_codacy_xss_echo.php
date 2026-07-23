<?php
/**
 * Static audit: Codacy-risky user-input echo patterns in module PHP.
 *
 * Why: Codacy flags short-echo search fields and echoed http_build_query hrefs as XSS.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_codacy_xss_echo_audit.php';

$isCli = PHP_SAPI === 'cli';
itm_script_output_begin('Codacy XSS echo audit (modules/)');

$nl = itm_script_output_nl();
$strict = false;
if ($isCli) {
    $strict = in_array('--strict', $argv ?? [], true);
} else {
    $strict = isset($_GET['strict']) && (string)$_GET['strict'] === '1';
}

$violations = itm_codacy_xss_echo_collect_violations($root);

if ($violations === []) {
    echo 'PASS: 0 Codacy-risky echo patterns in modules/' . $nl;
    itm_script_output_end();
    exit(0);
}

echo 'Found ' . count($violations) . ' Codacy-risky echo pattern(s) in modules/:' . $nl;
foreach ($violations as $msg) {
    echo '  - ' . $msg . $nl;
}

echo $nl
    . 'Fix: use <?php echo sanitize(...); ?> for search inputs; pre-escape href query strings with htmlspecialchars().' . $nl
    . 'Exempt: same-line comment itm-codacy-xss-exempt: with reason.' . $nl;

if (!$strict) {
    echo $nl . 'INFO: default run is informational (exit 0). Re-run with --strict to fail the check.' . $nl;
    itm_script_output_end();
    exit(0);
}

itm_script_output_end();
exit(1);
