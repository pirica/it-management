<?php
/**
 * Static audit: environment variables read in code vs documented in `.env.example`.
 *
 * Browser: scripts/check_env_vars_in_use.php (Administrator session).
 * CLI: php scripts/check_env_vars_in_use.php [--strict] [--json]
 */
require_once __DIR__ . '/lib/itm_script_access_helpers.php';
require_once __DIR__ . '/lib/itm_env_vars_audit.php';

$nl = itm_check_script_begin_browser_admin('Environment variables in use');

$strict = false;
$asJson = false;

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--strict') {
            $strict = true;
        } elseif ($arg === '--json') {
            $asJson = true;
        }
    }
} else {
    $strict = isset($_GET['strict']) && (string)$_GET['strict'] !== '0';
    $asJson = isset($_GET['json']) && (string)$_GET['json'] !== '0';
}

$root = itm_env_vars_audit_project_root();
$report = itm_env_vars_audit_build_report($root);

if ($asJson) {
    if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' && !headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . $nl;
    itm_script_output_end();
    exit($strict && $report['strict_issue_count'] > 0 ? 1 : 0);
}

echo 'Environment variable audit' . $nl;
echo 'Scanned: PHP, Python (.py), and shell (.sh) under project root (excludes vendor, phpunit/coverage, qa-reports).' . $nl;
echo '.env.example: ' . str_replace('\\', '/', $report['env_example_path']) . $nl;
echo $nl;

echo '[IN USE + DOCUMENTED] (' . count($report['matched']) . ')' . $nl;
if (empty($report['matched'])) {
    echo ' (none)' . $nl;
} else {
    foreach ($report['matched'] as $name => $paths) {
        echo ' - ' . $name . ' — ' . implode(', ', $paths) . $nl;
    }
}
echo $nl;

echo '[DOCUMENTED IN .env.example ONLY — not read in scanned code] (' . count($report['example_only']) . ')' . $nl;
if (empty($report['example_only'])) {
    echo ' (none)' . $nl;
} else {
    foreach ($report['example_only'] as $name) {
        echo ' - ' . $name . $nl;
    }
}
echo $nl;

echo '[IN CODE — app/runtime, not in .env.example] (' . count($report['undocumented']['app']) . ')' . $nl;
if (empty($report['undocumented']['app'])) {
    echo ' (none)' . $nl;
} else {
    foreach ($report['undocumented']['app'] as $name => $paths) {
        echo ' - ' . $name . ' — ' . implode(', ', $paths) . $nl;
    }
}
echo $nl;

echo '[IN CODE — tooling / CI / scripts only] (' . count($report['undocumented']['tooling']) . ')' . $nl;
if (empty($report['undocumented']['tooling'])) {
    echo ' (none)' . $nl;
} else {
    foreach ($report['undocumented']['tooling'] as $name => $paths) {
        echo ' - ' . $name . ' — ' . implode(', ', $paths) . $nl;
    }
}
echo $nl;

echo '[IN CODE — OS / host] (' . count($report['undocumented']['os']) . ')' . $nl;
if (empty($report['undocumented']['os'])) {
    echo ' (none)' . $nl;
} else {
    foreach ($report['undocumented']['os'] as $name => $paths) {
        echo ' - ' . $name . ' — ' . implode(', ', $paths) . $nl;
    }
}
echo $nl;

$issueCount = (int)$report['strict_issue_count'];
if ($issueCount === 0) {
    echo 'PASS: No .env.example drift (app vars documented; no dead example keys).' . $nl;
    itm_script_output_end();
    exit(0);
}

if (!$strict) {
    echo 'INFO: ' . $issueCount . ' strict drift item(s) — re-run with --strict to fail CI.' . $nl;
    itm_script_output_end();
    exit(0);
}

echo 'FAIL: ' . $issueCount . ' strict drift item(s) (example-only keys or undocumented app vars).' . $nl;
itm_script_output_end();
exit(1);
