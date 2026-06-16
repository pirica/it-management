<?php
/**
 * Test Runner Script
 *
 * This script runs the PHPUnit test suite and displays the results.
 * It is intended for both CLI and Browser access.
 */

// Define that we are in a CLI script context to bypass web-only auth/logic
define('ITM_CLI_SCRIPT', true);

$isCli = (PHP_SAPI === 'cli');
$runRequested = $isCli;

if (!$isCli) {
    $runRequested = (($_GET['run'] ?? '') === '1');
}

// Browser menu may set skip_db; CLI uses env only until run starts.
$user_wants_skip = false;
if ($runRequested) {
    $user_wants_skip = ($isCli
        ? ((getenv('ITM_SKIP_DB_TESTS') ?? '') === '1')
        : (($_GET['skip_db'] ?? '') === '1'));
}

// Why: We force skip during the parent's config.php load to avoid connection fatals.
putenv('ITM_SKIP_DB_TESTS=1');
$_ENV['ITM_SKIP_DB_TESTS'] = '1';

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'scripts/lib/script_browser_nav.php';
require_once ROOT_PATH . 'scripts/lib/script_cli_output.php';

/**
 * Why: Browser users pick standard vs coverage on a menu; CLI uses flags/env.
 */
function itm_run_tests_want_coverage($isCli)
{
    if ($isCli) {
        global $argv;
        return in_array('--coverage', $argv ?? [], true)
            || (getenv('ITM_COVERAGE') === '1');
    }

    $mode = strtolower(trim((string)($_GET['mode'] ?? '')));
    if ($mode === 'coverage') {
        return true;
    }
    if ($mode === 'standard') {
        return false;
    }

    // Legacy query param from earlier browser support.
    return (($_GET['coverage'] ?? '') === '1') || (getenv('ITM_COVERAGE') === '1');
}

/**
 * Why: Shared HTML escape for the browser menu (scripts run outside module layout).
 */
function itm_run_tests_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Why: Browser entry shows choices instead of auto-running a long PHPUnit process.
 */
function itm_run_tests_render_browser_menu($dbAvailable, $coverageReportPath)
{
    $scriptSelf = 'run_tests.php';
    $skipDbChecked = (($_GET['skip_db'] ?? '') === '1') ? ' checked' : '';
    $modeStandard = (($_GET['mode'] ?? 'standard') !== 'coverage') ? ' checked' : '';
    $modeCoverage = (($_GET['mode'] ?? '') === 'coverage') ? ' checked' : '';

    itm_script_output_begin('PHPUnit Test Suite');
    itm_script_browser_nav_echo();
    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
    echo '<h1>PHPUnit Test Suite</h1>';
    echo '<p>Choose how to run tests from <code>phpunit/tests/Unit/</code>. Output is verbose (test names and details).</p>';
    echo '<p style="font-size:0.95rem;color:#57606a;">Database: '
        . ($dbAvailable
            ? '<strong style="color:#1a7f37;">connected</strong> — full suite including DB tests.'
            : '<strong style="color:#9a6700;">unavailable</strong> — DB-dependent tests will be skipped unless you fix MySQL and reload.')
        . '</p>';

    if (is_file($coverageReportPath)) {
        echo '<p style="font-size:0.95rem;">Latest HTML coverage report: '
            . '<a href="../phpunit/coverage/html/index.html">phpunit/coverage/html/index.html</a></p>';
    }

    echo '<form method="get" action="' . itm_run_tests_h($scriptSelf) . '" style="display:grid;gap:14px;max-width:520px;margin-top:16px;">';
    echo '<input type="hidden" name="run" value="1">';
    echo '<fieldset style="border:1px solid #d0d7de;border-radius:8px;padding:12px 14px;margin:0;">';
    echo '<legend style="padding:0 6px;font-weight:600;">Run mode</legend>';
    echo '<label style="display:block;margin-bottom:8px;cursor:pointer;">';
    echo '<input type="radio" name="mode" value="standard"' . $modeStandard . '> ';
    echo '<strong>Standard</strong> — verbose test run (no code coverage)</label>';
    echo '<label style="display:block;cursor:pointer;">';
    echo '<input type="radio" name="mode" value="coverage"' . $modeCoverage . '> ';
    echo '<strong>HTML coverage</strong> — verbose run + report at <code>phpunit/coverage/html/index.html</code>';
    echo ' <span style="color:#57606a;">(requires Xdebug or PCOV)</span></label>';
    echo '</fieldset>';
    echo '<label style="cursor:pointer;"><input type="checkbox" name="skip_db" value="1"' . $skipDbChecked . '> ';
    echo 'Skip database tests (<code>ITM_SKIP_DB_TESTS=1</code>)</label>';
    echo '<button type="submit" class="btn btn-primary" style="padding:10px 16px;font-weight:600;width:fit-content;">Run tests</button>';
    echo '</form>';
    echo '<p style="margin-top:20px;font-size:0.9rem;color:#57606a;">CLI: <code>php scripts/run_tests.php</code> · ';
    echo '<code>php scripts/run_tests.php --coverage</code></p>';
    echo '</main>';
}

$db_available = false;
if (!$user_wants_skip) {
    $probe_conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$probe_conn && DB_HOST === 'localhost') {
        $probe_conn = @mysqli_connect('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    }
    if ($probe_conn) {
        $db_available = true;
        mysqli_close($probe_conn);
    }
}

$coverage_html_dir = ROOT_PATH . 'phpunit/coverage/html';
$coverage_report_file = $coverage_html_dir . '/index.html';

if (!$isCli && !$runRequested) {
    itm_run_tests_render_browser_menu($db_available, $coverage_report_file);
    exit;
}

if ($db_available && !$user_wants_skip) {
    putenv('ITM_SKIP_DB_TESTS=0');
    $_ENV['ITM_SKIP_DB_TESTS'] = '0';
} else {
    putenv('ITM_SKIP_DB_TESTS=1');
    $_ENV['ITM_SKIP_DB_TESTS'] = '1';
}

$want_coverage = itm_run_tests_want_coverage($isCli);

$phpunit_bin = ROOT_PATH . 'phpunit/phpunit.phar';
$phpunit_xml = ROOT_PATH . 'phpunit/phpunit.xml';

$php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
if (strpos($php_bin, 'php-cgi') !== false) {
    $php_bin = str_replace('php-cgi', 'php', $php_bin);
}

// Why: Inline environment variables (VAR=val cmd) are not supported by Windows cmd.exe.
// We rely on putenv('ITM_SKIP_DB_TESTS=1') called earlier in this script.
$command = escapeshellarg($php_bin) . ' ' . escapeshellarg($phpunit_bin)
    . ' -c ' . escapeshellarg($phpunit_xml)
    . ' --verbose';
if ($want_coverage) {
    if (!is_dir($coverage_html_dir)) {
        mkdir($coverage_html_dir, 0777, true);
    }
    $command .= ' --coverage-html ' . escapeshellarg($coverage_html_dir);
} else {
    // Why: phpunit.xml defines <coverage>; skip it unless explicitly requested.
    $command .= ' --no-coverage';
}
$command .= ' 2>&1';

if (!$isCli) {
    itm_script_output_begin('PHPUnit Test Suite Results');
    itm_script_browser_nav_echo();
    echo '<h1>PHPUnit Test Suite</h1>';
    echo '<p><a href="run_tests.php">← Choose another run mode</a></p>';
    echo '<p>Running from <code>phpunit/tests/Unit/</code> — mode: <strong>'
        . itm_run_tests_h($want_coverage ? 'HTML coverage' : 'Standard')
        . '</strong></p>';
    echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:5px;">';
} else {
    echo "Running command: $command\n\n";
}

passthru($command, $return_var);

if (!$isCli) {
    echo '</pre>';
    if ($want_coverage && is_file($coverage_report_file)) {
        echo '<p>HTML coverage report: <a href="../phpunit/coverage/html/index.html">'
            . 'phpunit/coverage/html/index.html</a></p>';
    }
    if ($return_var === 0) {
        echo '<p style="color:green;font-weight:bold;">✅ All tests passed!</p>';
    } else {
        echo '<p style="color:red;font-weight:bold;">❌ Some tests failed (Exit Code: ' . (int)$return_var . ').</p>';
    }
} else {
    exit($return_var);
}
