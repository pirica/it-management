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
    $runRequested = (($_GET['run'] ?? '') === '1')
        || (($_GET['coverage_job'] ?? '') === '1')
        || (($_GET['coverage_start'] ?? '') === '1');
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
require_once ROOT_PATH . 'includes/itm_cli_binary.php';

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

    if (($_GET['coverage_start'] ?? '') === '1') {
        return true;
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
function itm_run_tests_has_coverage_driver($phpBin = null)
{
    if ($phpBin !== null && $phpBin !== '') {
        return itm_cli_php_binary_has_coverage_driver((string) $phpBin);
    }

    return extension_loaded('xdebug') || extension_loaded('pcov');
}

/**
 * Why: HTML coverage static analysis over the whole tree exceeds default 128M php.ini limits.
 */
function itm_run_tests_resolve_memory_limit()
{
    $raw = getenv('ITM_PHPUNIT_MEMORY_LIMIT');
    if ($raw !== false && $raw !== '') {
        return (string) $raw;
    }

    return '512M';
}

/**
 * Why: PHPUnit runs in a CLI subprocess; pass -d memory_limit so report generation does not fatal.
 */
function itm_run_tests_php_ini_memory_flag()
{
    $limit = itm_run_tests_resolve_memory_limit();

    return '-d memory_limit=' . $limit;
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
    $nl = $isCli ? "\n" : '';

    if ($isCli) {
        if ($reportExists) {
            echo "\nHTML coverage report: phpunit/coverage/html/coverage.html" . $nl;
            echo 'Full path: ' . $coverageReportFile . "\n";
        } elseif ($wantCoverage) {
            if ($coverageSkippedNoDriver) {
                echo "\nCoverage report not generated: enable Xdebug or PCOV in PHP, then re-run with --coverage." . $nl;
            } else {
                echo "\nCoverage report not generated." . $nl;
                echo 'If PHPUnit fatals with "memory size exhausted" during HTML report generation, raise '
                    . 'ITM_PHPUNIT_MEMORY_LIMIT (default 512M), e.g. set ITM_PHPUNIT_MEMORY_LIMIT=1024M.' . $nl;
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
            echo 'Re-run after fixing test failures. If the log shows <strong>memory size exhausted</strong> during report generation, set '
                . '<code>ITM_PHPUNIT_MEMORY_LIMIT=1024M</code> (default <code>512M</code>) and run again.';
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
    $coverageDriverOk = itm_run_tests_has_coverage_driver(itm_resolve_phpunit_cli_binary(true));
    $phpunitPhpBin = itm_resolve_phpunit_cli_binary(false);
    $phpunitMissingExt = itm_cli_php_binary_missing_extensions($phpunitPhpBin, itm_phpunit_required_extensions());

    itm_script_output_begin('PHPUnit Test Suite');
    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
    echo '<h1>PHPUnit Test Suite</h1>';
    echo '<p>Choose how to run tests from <code>phpunit/tests/Unit/</code>. Output is verbose (test names and details).</p>';
    echo '<p style="font-size:0.95rem;color:#57606a;">CLI PHP: <code>' . itm_run_tests_h($phpunitPhpBin) . '</code><br>';
    echo 'PHPUnit extensions (required): <code>dom</code>, <code>json</code>, <code>libxml</code>, <code>mbstring</code>, <code>tokenizer</code>, <code>xml</code>, <code>xmlwriter</code> — ';
    if ($phpunitMissingExt === []) {
        echo '<strong style="color:#1a7f37;">all loaded</strong>';
    } else {
        echo '<strong style="color:#cf222e;">missing: ' . itm_run_tests_h(implode(', ', $phpunitMissingExt)) . '</strong>';
        echo ' — run <code>powershell -ExecutionPolicy Bypass -File scripts/setup_dunebox_php_from_laragon.ps1</code> (Dunebox) or enable extensions in <code>php.ini</code>.';
    }
    echo '<br>Database: '
        . ($dbAvailable
            ? '<strong style="color:#1a7f37;">connected</strong> — full suite including DB tests.'
            : '<strong style="color:#9a6700;">unavailable</strong> — DB-dependent tests will be skipped unless you fix MySQL and reload.')
        . '<br>Coverage driver: '
        . ($coverageDriverOk
            ? '<strong style="color:#1a7f37;">Xdebug/PCOV available</strong> (HTML coverage)'
            : '<strong style="color:#9a6700;">not available</strong> — HTML coverage needs <strong>Xdebug</strong> or <strong>PCOV</strong> with coverage mode on the CLI binary above.')
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
    echo '<strong>HTML coverage</strong> — report at <code>phpunit/coverage/html/coverage.html</code>';
    echo ' <span style="color:#57606a;">(starts a <strong>background CLI</strong> job — avoids gateway timeout)</span></label>';
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
    $probe_conn = itm_mysqli_connect();
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
$php_bin = itm_resolve_phpunit_cli_binary($want_coverage);
$phpunit_missing_ext = itm_cli_php_binary_missing_extensions($php_bin, itm_phpunit_required_extensions());
if ($phpunit_missing_ext !== []) {
    $hint = 'PHPUnit requires extensions: dom, json, libxml, mbstring, tokenizer, xml, xmlwriter. Missing on this binary: '
        . implode(', ', $phpunit_missing_ext) . '. CLI: ' . $php_bin
        . '. Dunebox: powershell -ExecutionPolicy Bypass -File scripts/setup_dunebox_php_from_laragon.ps1'
        . ' (copies Xdebug from Laragon portable into D:\\dunebox-v1.0.6). See scripts/SCRIPTS.md → PHPUnit test runner.';
    $msg = $hint;
    if ($isCli) {
        fwrite(STDERR, $msg . $nl);
        exit(1);
    }
    itm_script_output_begin('PHPUnit Test Suite');
    echo '<main style="max-width:720px;margin:16px;font-family:sans-serif;">';
    echo '<h1>PHPUnit Test Suite</h1>';
    echo '<p><a href="run_tests.php">← Choose another run mode</a></p>';
    echo '<p style="color:#cf222e;"><strong>' . itm_run_tests_h($msg) . '</strong></p>';
    echo '</main>';
    itm_script_output_end();
    exit;
}

$coverage_driver_ok = itm_run_tests_has_coverage_driver($php_bin);
$coverage_skipped_no_driver = ($want_coverage && !$coverage_driver_ok);
$run_coverage_html = ($want_coverage && $coverage_driver_ok);

if ($run_coverage_html) {
    @ini_set('memory_limit', itm_run_tests_resolve_memory_limit());
}

require_once __DIR__ . '/lib/itm_run_tests_browser_coverage.php';

// Why: Browser + Xdebug HTML coverage exceeds Apache/proxy timeouts; run detached CLI instead of passthru.
$coverage_job_view = (!$isCli && (($_GET['coverage_job'] ?? '') === '1'));
if (!$isCli && $runRequested && ($run_coverage_html || $coverage_job_view)) {
    if ($coverage_job_view) {
        itm_run_tests_browser_coverage_render_job_page($coverage_report_file);
        itm_script_output_end();
        exit;
    }
    if (!$run_coverage_html) {
        itm_script_output_begin('PHPUnit Test Suite');
        echo '<p style="color:#cf222e;">HTML coverage needs Xdebug or PCOV on CLI PHP. <a href="run_tests.php">← Back</a></p>';
        itm_script_output_end();
        exit;
    }
    if (($_GET['coverage_start'] ?? '') !== '1') {
        itm_run_tests_browser_coverage_render_intro_page($user_wants_skip, $php_bin);
        itm_script_output_end();
        exit;
    }
    if (!itm_run_tests_browser_coverage_spawn_cli_job($php_bin, $user_wants_skip, ROOT_PATH)) {
        itm_script_output_begin('PHPUnit Test Suite');
        echo '<p style="color:#cf222e;">Unable to start background coverage job. Use CLI: <code>php scripts/run_tests.php --coverage</code></p>';
        itm_script_output_end();
        exit;
    }
    if (!headers_sent()) {
        header('Location: run_tests.php?coverage_job=1');
    }
    itm_script_output_end();
    exit;
}

$phpunit_bin = ROOT_PATH . 'phpunit/phpunit.phar';
$phpunit_xml = ROOT_PATH . 'phpunit/phpunit.xml';

// Why: Browser SAPI (php-cgi) often lacks mbstring; PHPUnit runs in a CLI subprocess with PHP_EXE / Dunebox php.ini.

// Why: Inline environment variables (VAR=val cmd) are not supported by Windows cmd.exe.
// We rely on putenv('ITM_SKIP_DB_TESTS=1') called earlier in this script.
$command = escapeshellarg($php_bin);
if ($run_coverage_html) {
    $command .= ' ' . itm_run_tests_php_ini_memory_flag();
}
$command .= ' ' . escapeshellarg($phpunit_bin)
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
    @set_time_limit(0);
    @ini_set('max_execution_time', '0');
    @ignore_user_abort(true);
    itm_script_output_begin('PHPUnit Test Suite Results');
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

itm_script_output_end();
