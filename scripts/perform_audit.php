<?php
/**
 * Dynamic PHP Script Error Auditor
 *
 * Discovers and executes PHP scripts under scripts/ via subprocesses.
 * Collects exit codes and PHP error log lines per script (isolated deltas).
 *
 * CLI: php scripts/perform_audit.php [--loop]
 * Report: scripts/php_error_audit_results.json
 */

define('ITM_CLI_SCRIPT', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_perform_audit.php';

itm_script_output_begin('PHP Script Error Audit');
$nl = itm_script_output_nl();

$argvList = $GLOBALS['argv'] ?? [];
$loopMode = in_array('--loop', $argvList, true);

$errorLog = ROOT_PATH . 'error_log.txt';
$resultsFile = __DIR__ . '/php_error_audit_results.json';
$allowlistFile = __DIR__ . '/data/perform_audit_allowlist.json';

itm_perform_audit_prepare_db_env();
itm_perform_audit_truncate_error_log($errorLog);

$phpBinary = itm_perform_audit_resolve_php_binary();
$scripts = itm_perform_audit_discover_scripts(__DIR__);
$allowlist = itm_perform_audit_load_allowlist($allowlistFile);

$results = [];
$stats = [
    'passed' => 0,
    'failed_exit' => 0,
    'failed_errors' => 0,
    'excluded' => count(itm_perform_audit_static_exclusions()),
    'allowlisted' => 0,
    'scanned' => count($scripts),
];

echo colorText('PHP Script Error Audit', 'info') . $nl;
echo '[INFO] PHP binary: ' . $phpBinary . $nl;
echo '[INFO] Scripts scanned: ' . $stats['scanned'] . ' (Tier 5 / menu scripts excluded)' . $nl;
echo '[INFO] Allowlist entries: ' . count($allowlist) . $nl . $nl;

foreach ($scripts as $script) {
    $scriptPath = __DIR__ . '/' . $script;
    $run = itm_perform_audit_run_script($phpBinary, $scriptPath, $errorLog);

    $exitCode = (int)$run['exit_code'];
    $cliErrors = $run['cli_errors'];
    $stdoutHits = $run['stdout_hits'];
    $hasErrors = ($cliErrors !== [] || $stdoutHits !== []);
    $allowlisted = itm_perform_audit_is_allowlisted_exit($script, $exitCode, $cliErrors, $stdoutHits, $allowlist);

    if ($hasErrors) {
        $stats['failed_errors']++;
        $results[$script] = [
            'exit_code' => $exitCode,
            'cli_errors' => $cliErrors,
            'stdout_hits' => $stdoutHits,
            'allowlisted' => false,
        ];
        continue;
    }

    if ($exitCode !== 0) {
        if ($allowlisted) {
            $stats['allowlisted']++;
            continue;
        }
        $stats['failed_exit']++;
        $results[$script] = [
            'exit_code' => $exitCode,
            'cli_errors' => [],
            'stdout_hits' => [],
            'allowlisted' => false,
        ];
        continue;
    }

    $stats['passed']++;
}

$payload = [
    'generated_at' => gmdate('c'),
    'php_binary' => $phpBinary,
    'summary' => $stats,
    'failures' => $results,
];

file_put_contents(
    $resultsFile,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo colorText('--- Summary ---', 'info') . $nl;
echo '[INFO] Passed: ' . $stats['passed'] . $nl;
echo '[INFO] Failed (exit code): ' . $stats['failed_exit'] . $nl;
echo '[INFO] Failed (PHP errors): ' . $stats['failed_errors'] . $nl;
echo '[INFO] Allowlisted exit-only: ' . $stats['allowlisted'] . $nl;
echo '[INFO] Report: scripts/php_error_audit_results.json' . $nl . $nl;

$unresolved = $stats['failed_exit'] + $stats['failed_errors'];
if ($unresolved === 0) {
    echo itm_script_format_status_line('[PASS] Audit verified — no unallowlisted failures.') . $nl;
    itm_script_output_end();
    exit(0);
}

echo itm_script_format_status_line('[FAIL] Audit found ' . $unresolved . ' unallowlisted failure(s).') . $nl;

if ($results !== []) {
    $shown = 0;
    foreach ($results as $script => $row) {
        if ($shown >= 10) {
            break;
        }
        $errCount = count($row['cli_errors'] ?? []);
        echo '  - ' . $script . ' exit=' . (int)($row['exit_code'] ?? 0) . ' errors=' . $errCount . $nl;
        $shown++;
    }
    if (count($results) > 10) {
        echo '  ... and ' . (count($results) - 10) . ' more (see JSON)' . $nl;
    }
}

itm_script_output_end();
exit(1);
