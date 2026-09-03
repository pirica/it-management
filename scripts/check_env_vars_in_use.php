<?php
/**
 * Static audit: environment variables read in code vs documented in `.env.example`.
 *
 * Browser: scripts/check_env_vars_in_use.php (Administrator session).
 * CLI: php scripts/check_env_vars_in_use.php [--strict] [--json]
 */

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Browser: plain-text report (Administrator session). CLI: <code>php scripts/check_env_vars_in_use.php</code> — default informational (exit <code>0</code>); <code>--strict</code> / <code>?strict=1</code> exits <code>1</code> on example-only or undocumented app drift. JSON: <code>--json</code> / <code>?json=1</code>. Includes an HTML table comparing live <code>.env</code> vs <code>.env.example</code> (live column shows <code>(Not Empty)</code> only — no real values). Run when changing <code>.env.example</code>, <code>config/config.php</code>, or integration env reads.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
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
$envCompare = $report['env_compare'] ?? itm_env_vars_audit_compare_env_files($root);

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

if (itm_script_access_is_cli()) {
    itm_env_vars_audit_print_env_compare_cli($envCompare, $nl);
} else {
    require_once __DIR__ . '/lib/script_cli_output.php';
    itm_script_output_close_pre();
    itm_env_vars_audit_echo_env_compare_html($envCompare);
    echo '<pre style="font-family:Consolas,\'Courier New\',monospace;font-size:13px;margin:16px;line-height:1.4;white-space:pre-wrap;word-break:break-word;">';
    $GLOBALS['itm_script_pre_closed'] = false;
}

echo 'Code scan vs .env.example' . $nl;
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
