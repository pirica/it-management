<?php
/**
 * Regression: Explorer api.php per-employee hourly rate limit (ITM-PENTEST-015).
 *
 * CLI: php scripts/verify_explorer_api_rate_limit.php
 * Browser: scripts/verify_explorer_api_rate_limit.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_explorer_api_rate_limit.php</code> — exit <code>1</code> on failure. Confirms <code>modules/explorer/api.php</code> calls <code>itm_explorer_api_enforce_rate_limit_or_exit()</code> and the helper blocks over-cap requests.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_explorer_api_rate_limit.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Explorer API rate limit verification');
$nl = itm_script_output_nl();
$failures = 0;

if (!verify_explorer_api_source_contains('modules/explorer/api.php', 'itm_explorer_api_enforce_rate_limit_or_exit')) {
    echo colorText('[FAIL] modules/explorer/api.php must call itm_explorer_api_enforce_rate_limit_or_exit()', 'fail') . $nl;
    $failures++;
} else {
    echo colorText('[PASS] Explorer api.php enforces per-employee hourly rate limit', 'pass') . $nl;
}

$probeCompanyId = 1;
$probeEmployeeId = 1;
$probeLimit = 3;
$probeConfig = ['explorer_api_rate_limit_per_hour' => $probeLimit];
$probePath = itm_explorer_api_rate_limit_dir() . DIRECTORY_SEPARATOR . hash('sha256', $probeCompanyId . ':' . $probeEmployeeId) . '.json';
if (is_file($probePath)) {
    @unlink($probePath);
}

for ($i = 0; $i < $probeLimit; $i++) {
    $ok = itm_explorer_api_rate_limit_check($probeCompanyId, $probeEmployeeId, true, $probeConfig);
    if (empty($ok['ok'])) {
        echo colorText('[FAIL] Expected allow on attempt ' . ($i + 1) . ' of ' . $probeLimit, 'fail') . $nl;
        $failures++;
        break;
    }
}
if ($failures === 0) {
    $blocked = itm_explorer_api_rate_limit_check($probeCompanyId, $probeEmployeeId, true, $probeConfig);
    if (!empty($blocked['ok'])) {
        echo colorText('[FAIL] Expected block after ' . $probeLimit . ' requests in rolling hour', 'fail') . $nl;
        $failures++;
    } else {
        echo colorText('[PASS] Rolling-hour cap blocks request ' . ($probeLimit + 1), 'pass') . $nl;
    }
}

if (is_file($probePath)) {
    @unlink($probePath);
}

if ($failures > 0) {
    echo $nl . colorText("FAILED: {$failures} check(s).", 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('Explorer API rate limit checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);

function verify_explorer_api_source_contains(string $relativePath, string $needle): bool
{
    $path = ROOT_PATH . ltrim($relativePath, '/');
    if (!is_readable($path)) {
        return false;
    }
    $source = file_get_contents($path);

    return $source !== false && strpos($source, $needle) !== false;
}
