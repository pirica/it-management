<?php
/**
 * Dynamic PHP Script Error Auditor
 *
 * Discovers and executes all executable PHP scripts in the scripts/ folder
 * using subprocesses. Collects execution exit codes and any logged PHP error logs.
 */

// Define that we are in a CLI script context to bypass web-only auth/logic
define('ITM_CLI_SCRIPT', true);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('PHP Script Error Audit');
$nl = itm_script_output_nl();

$errorLog = ROOT_PATH . 'error_log.txt';
$resultsFile = __DIR__ . '/php_error_audit_results.json';

// Discover all PHP scripts in the scripts/ folder (except specific helper/library files)
$allFiles = glob(__DIR__ . '/*.php');
$scripts = [];

$exclusions = [
    'perform_audit.php',
    'scripts.php',
];

foreach ($allFiles as $file) {
    $base = basename($file);
    if (in_array($base, $exclusions, true)) {
        continue;
    }
    $scripts[] = $base;
}

sort($scripts);

$lastSize = 0;
$results = [];

function get_new_errors($logPath, &$lastSize) {
    clearstatcache();
    $currentSize = file_exists($logPath) ? filesize($logPath) : 0;
    if ($currentSize <= $lastSize) {
        $lastSize = $currentSize;
        return [];
    }
    
    $fp = fopen($logPath, 'r');
    if (!$fp) {
        return [];
    }
    fseek($fp, $lastSize);
    $newContent = '';
    while (!feof($fp)) {
        $newContent .= fread($fp, 8192);
    }
    fclose($fp);
    
    $lastSize = $currentSize;
    return array_values(array_filter(array_map('trim', explode("\n", $newContent))));
}

// Ensure the MySQL DB is connected during the audit
$envPrefix = "DB_HOST=127.0.0.1 DB_USER=root DB_PASS=itmanagement DB_NAME=itmanagement ";

echo "Starting error audit on " . count($scripts) . " scripts..." . $nl;

foreach ($scripts as $script) {
    $scriptPath = __DIR__ . '/' . $script;
    
    // We execute the CLI command in a sub-process once, capturing stdout, stderr, and exit code.
    $cmd = "{$envPrefix}ITM_CLI_SCRIPT=1 php " . escapeshellarg($scriptPath) . " 2>&1";
    $output = [];
    $exitCode = 0;
    
    exec($cmd, $output, $exitCode);
    $errors = get_new_errors($errorLog, $lastSize);
    
    // Filter output for obvious PHP warnings, notices, errors, or uncaught exceptions
    $cleanOutputErrors = array_values(array_filter(array_map('trim', $output), function($line) {
        return (stripos($line, 'error') !== false || stripos($line, 'warning') !== false || stripos($line, 'notice') !== false || stripos($line, 'fatal') !== false || stripos($line, 'exception') !== false);
    }));

    $allErrors = array_unique(array_merge($errors, $cleanOutputErrors));

    if (!empty($allErrors) || $exitCode !== 0) {
        $results[$script] = [
            'cli' => $allErrors,
            'browser' => []
        ];
    }
}

file_put_contents($resultsFile, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo colorText("[PASS] Audit complete. Error report written to scripts/php_error_audit_results.json", 'pass') . $nl;

itm_script_output_end();
