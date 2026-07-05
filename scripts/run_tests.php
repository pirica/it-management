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
require_once __DIR__ . '/lib/script_cli_output.php';
$nl = itm_script_output_nl();

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
 * Why: PHPUnit needs Xdebug or PCOV; without them --coverage-html only prints a warning.
 */
function itm_run_tests_has_coverage_driver()
{
    return extension_loaded('xdebug') || extension_loaded('pcov');
}

/**
 * Why: PHPUnit writes index.html; we expose coverage.html as the stable report entry point.
 */
function itm_run_tests_finalize_coverage_report($coverageHtmlDir)
{
    $indexFile = $coverageHtmlDir . '/index.html';
    $coverageFile = $coverageHtmlDir . '/coverage.html';
    if (is_file($indexFile)) {
        @rename($indexFile, $coverageFile);
    }

    return is_file($coverageFile) ? $coverageFile : null;
}

/**
 * Why: Shared coverage report link after browser/CLI test runs.
 */
function itm_run_tests_echo_coverage_link($isCli, $wantCoverage, $coverageReportFile, $coverageSkippedNoDriver)
{
    $reportExists = is_file($coverageReportFile);

    if ($isCli) {
        if ($reportExists) {
            echo "\nHTML coverage report: phpunit/coverage/html/coverage.html" . $nl;
            echo 'Full path: ' . $coverageReportFile . "\n";
        } elseif ($wantCoverage) {
            if ($coverageSkippedNoDriver) {
                echo "\nCoverage report not generated: enable Xdebug or PCOV in PHP, then re-run with --coverage." . $nl;
            } else {
                echo "\nCoverage report not generated." . $nl;
            }
        }
        return;
    }

    if ($reportExists) {
        echo '<p style="margin-top:16px;padding:12px 14px;background:#f6ffed;border:1px solid #94d194;border-radius:8px;">';
        echo '<strong>HTML coverage report:</strong> ';
        echo '<a href="../phpunit/coverage/html/coverage.html" target="_blank" rel="noopener">phpunit/coverage/html/coverage.html</a>';
        echo '</p>';
        return;
    }

    if ($wantCoverage) {
        echo '<p style="margin-top:16px;padding:12px 14px;background:#fff8e6;border:1px solid #d4a72c;border-radius:8px;color:#57606a;">';
        echo '<strong>Coverage report not generated.</strong> ';
        if ($coverageSkippedNoDriver) {
            echo 'Enable <strong>Xdebug</strong> or <strong>PCOV</strong> in Laragon (Menu → PHP → Extensions), restart Apache, then run <strong>HTML coverage</strong> again.';
        } else {
            echo 'Re-run with HTML coverage mode after fixing any test failures.';
        }
        echo '</p>';
    }
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
    $coverageDriverOk = itm_run_tests_has_coverage_driver();

    itm_script_output_begin('PHPUnit Test Suite');
    itm_script_browser_nav_echo();
    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
    echo '<h1>PHPUnit Test Suite</h1>';
    echo '<p>Choose how to run tests from <code>phpunit/tests/Unit/</code>. Output is verbose (test names and details).</p>';
    echo '<p style="font-size:0.95rem;color:#57606a;">Database: '
        . ($dbAvailable
            ? '<strong style="color:#1a7f37;">connected</strong> — full suite including DB tests.'
            : '<strong style="color:#9a6700;">unavailable</strong> — DB-dependent tests will be skipped unless you fix MySQL and reload.')
        . '<br>Coverage driver: '
        . ($coverageDriverOk
            ? '<strong style="color:#1a7f37;">Xdebug/PCOV available</strong>'
            : '<strong style="color:#9a6700;">not available</strong> — HTML coverage needs Xdebug or PCOV.')
        . '</p>';

    if (is_file($coverageReportPath)) {
        echo '<p style="font-size:0.95rem;">Latest HTML coverage report: '
            . '<a href="../phpunit/coverage/html/coverage.html">phpunit/coverage/html/coverage.html</a></p>';
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
    echo '<strong>HTML coverage</strong> — verbose run + report at <code>phpunit/coverage/html/coverage.html</code>';
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
$coverage_report_file = $coverage_html_dir . '/coverage.html';

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
$coverage_driver_ok = itm_run_tests_has_coverage_driver();
$coverage_skipped_no_driver = ($want_coverage && !$coverage_driver_ok);
$run_coverage_html = ($want_coverage && $coverage_driver_ok);

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
if ($run_coverage_html) {
    if (!is_dir($coverage_html_dir)) {
        mkdir($coverage_html_dir, 0777, true);
    }
    $command .= ' --coverage-html ' . escapeshellarg($coverage_html_dir);
} else {
    // Why: phpunit.xml defines <coverage>; skip it unless driver is available and requested.
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
    if ($coverage_skipped_no_driver) {
        echo '<p style="padding:10px 12px;background:#fff8e6;border:1px solid #d4a72c;border-radius:6px;color:#57606a;">';
        echo 'Coverage driver not available — running tests without HTML coverage. ';
        echo 'Enable Xdebug or PCOV, then run HTML coverage again.</p>';
    }
    echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:5px;">';
} else {
    if ($coverage_skipped_no_driver) {
        echo "Note: Coverage driver not available — running without HTML coverage." . $nl;
        echo "Enable Xdebug or PCOV in PHP, then re-run with --coverage.\n" . $nl;
    }
    echo "Running command: $command\n" . $nl;
}

passthru($command, $return_var);

if ($run_coverage_html) {
    $finalized = itm_run_tests_finalize_coverage_report($coverage_html_dir);
    if ($finalized !== null) {
        $coverage_report_file = $finalized;
    }
}

if (!$isCli) {
    echo '</pre>';
    itm_run_tests_echo_coverage_link($isCli, $want_coverage, $coverage_report_file, $coverage_skipped_no_driver);
    if ($return_var === 0) {
        echo '<p style="color:green;font-weight:bold;">✅ All tests passed!</p>';
    } else {
        echo '<p style="color:red;font-weight:bold;">❌ Some tests failed (Exit Code: ' . (int)$return_var . ').</p>';
    }
} else {
    itm_run_tests_echo_coverage_link($isCli, $want_coverage, $coverage_report_file, $coverage_skipped_no_driver);
    exit($return_var);
}
