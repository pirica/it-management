<?php
/**
 * Browser HTML coverage runs in a detached CLI subprocess (avoids Apache/proxy gateway timeouts).
 */

if (!function_exists('itm_run_tests_browser_coverage_log_path')) {
    function itm_run_tests_browser_coverage_log_path(): string
    {
        return ROOT_PATH . 'qa-reports/run_tests_browser_coverage.log';
    }
}

if (!function_exists('itm_run_tests_browser_coverage_ensure_qa_dir')) {
    function itm_run_tests_browser_coverage_ensure_qa_dir(): void
    {
        $dir = ROOT_PATH . 'qa-reports';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}

if (!function_exists('itm_run_tests_browser_coverage_spawn_cli_job')) {
    function itm_run_tests_browser_coverage_spawn_cli_job(string $phpBin, bool $skipDb, string $rootPath, string $filter = ''): bool
    {
        itm_run_tests_browser_coverage_ensure_qa_dir();
        $logFile = itm_run_tests_browser_coverage_log_path();
        $started = gmdate('Y-m-d H:i:s') . ' UTC';
        $filter = function_exists('itm_run_tests_normalize_browser_filter')
            ? itm_run_tests_normalize_browser_filter($filter)
            : trim($filter);
        $filterArgs = '';
        if ($filter !== '') {
            $filterArgs = ' --filter ' . escapeshellarg($filter);
        }
        file_put_contents(
            $logFile,
            "=== ITM browser coverage job started {$started} ===\n",
            LOCK_EX
        );

        $phpBin = (string) $phpBin;
        $runner = $rootPath . 'scripts' . DIRECTORY_SEPARATOR . 'run_tests.php';
        $skipFlag = $skipDb ? '1' : '0';

        if (DIRECTORY_SEPARATOR === '\\') {
            $logEsc = escapeshellarg($logFile);
            $line = 'set ITM_SKIP_DB_TESTS=' . $skipFlag
                . '&& ' . escapeshellarg($phpBin)
                . ' ' . escapeshellarg($runner)
                . ' --coverage' . $filterArgs . ' >> ' . $logEsc . ' 2>&1';
            $cmd = 'cmd /C start "" /B ' . $line;
            $handle = @popen($cmd, 'r');
            if ($handle === false) {
                return false;
            }
            pclose($handle);

            return true;
        }

        $env = 'ITM_SKIP_DB_TESTS=' . $skipFlag;
        $cmd = $env . ' ' . escapeshellarg($phpBin)
            . ' ' . escapeshellarg($runner)
            . ' --coverage' . $filterArgs . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
        exec($cmd);

        return true;
    }
}

if (!function_exists('itm_run_tests_browser_coverage_tail_log')) {
    function itm_run_tests_browser_coverage_tail_log(int $maxLines = 120): string
    {
        $path = itm_run_tests_browser_coverage_log_path();
        if (!is_file($path)) {
            return '';
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return '';
        }
        if (count($lines) <= $maxLines) {
            return implode("\n", $lines);
        }

        return implode("\n", array_slice($lines, -$maxLines));
    }
}

if (!function_exists('itm_run_tests_browser_coverage_job_likely_running')) {
    function itm_run_tests_browser_coverage_job_likely_running(): bool
    {
        $path = itm_run_tests_browser_coverage_log_path();
        if (!is_file($path)) {
            return false;
        }
        $tail = itm_run_tests_browser_coverage_tail_log(40);
        if ($tail === '') {
            return false;
        }
        if (preg_match('/\bOK\s*\(\d+ tests/i', $tail)) {
            return false;
        }
        if (preg_match('/FAILURES!/i', $tail)) {
            return false;
        }
        if (preg_match('/\bERRORS!/i', $tail)) {
            return false;
        }

        return (time() - (int) filemtime($path)) < 7200;
    }
}

if (!function_exists('itm_run_tests_browser_coverage_render_intro_page')) {
    function itm_run_tests_browser_coverage_render_intro_page(bool $skipDb, string $phpBin, string $filter = ''): void
    {
        require_once ROOT_PATH . 'scripts/lib/script_cli_output.php';
        itm_script_output_begin('PHPUnit HTML coverage (background)');

        $skipQ = $skipDb ? '&skip_db=1' : '';
        $filter = function_exists('itm_run_tests_normalize_browser_filter')
            ? itm_run_tests_normalize_browser_filter($filter)
            : trim($filter);
        $filterQ = ($filter !== '') ? '&filter=' . rawurlencode($filter) : '';
        $startUrl = 'run_tests.php?run=1&mode=coverage&coverage_start=1' . $skipQ . $filterQ;
        $jobUrl = 'run_tests.php?coverage_job=1';

        echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
        echo '<h1>HTML coverage (background)</h1>';
        echo '<p><a href="run_tests.php">← Choose another run mode</a></p>';
        echo '<p>Running <strong>~800 tests</strong> with <strong>Xdebug coverage</strong> inside Apache often triggers <strong>Gateway Timeout</strong> (504) after 60–120 seconds. ';
        echo 'The suite can take <strong>several minutes</strong> in coverage mode.</p>';
        if ($filter !== '') {
            echo '<p>Filter: <code>' . htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') . '</code> (PHPUnit <code>--filter</code>)</p>';
        }
        echo '<p>Start a <strong>detached CLI job</strong> (same as <code>php scripts/run_tests.php --coverage</code>) and monitor the log below. ';
        echo 'CLI PHP: <code>' . htmlspecialchars($phpBin, ENT_QUOTES, 'UTF-8') . '</code></p>';
        echo '<p><a class="btn btn-primary" href="' . htmlspecialchars($startUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:10px 16px;font-weight:600;">Start background coverage run</a> ';
        echo '<a class="btn" href="' . htmlspecialchars($jobUrl, ENT_QUOTES, 'UTF-8') . '" style="margin-left:8px;">View job log</a></p>';
        echo '<p style="font-size:0.9rem;color:#57606a;">Preferred: PowerShell from repo root — ';
        echo '<code>&quot;D:\\dunebox-v1.0.6\\system\\apps\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe&quot; scripts\\run_tests.php --coverage</code></p>';
        echo '</main>';
    }
}

if (!function_exists('itm_run_tests_browser_coverage_render_job_page')) {
    function itm_run_tests_browser_coverage_render_job_page(string $coverageReportFile): void
    {
        require_once ROOT_PATH . 'scripts/lib/script_cli_output.php';
        itm_script_output_begin('PHPUnit coverage job');

        $running = itm_run_tests_browser_coverage_job_likely_running();
        $tail = itm_run_tests_browser_coverage_tail_log();
        $reportReady = is_file($coverageReportFile);

        echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:900px;margin:16px;">';
        echo '<h1>PHPUnit coverage job</h1>';
        echo '<p><a href="run_tests.php">← Choose another run mode</a> · ';
        echo '<a href="run_tests.php?coverage_job=1">Refresh</a></p>';
        if ($running) {
            echo '<p style="padding:10px 12px;background:#fff8e6;border:1px solid #d4a72c;border-radius:6px;">';
            echo '<strong>Status:</strong> running (log updates every refresh; auto-refresh in 20s)</p>';
            echo '<meta http-equiv="refresh" content="20">';
        } elseif ($reportReady) {
            echo '<p style="padding:10px 12px;background:#f6ffed;border:1px solid #94d194;border-radius:6px;">';
            echo '<strong>Status:</strong> finished — ';
            echo '<a href="../phpunit/coverage/html/coverage.html" target="_blank" rel="noopener">Open coverage report</a></p>';
        } else {
            echo '<p style="padding:10px 12px;background:#f0f0f0;border:1px solid #d0d7de;border-radius:6px;">';
            echo '<strong>Status:</strong> idle or no log yet. <a href="run_tests.php?run=1&mode=coverage&coverage_start=1">Start background run</a></p>';
        }
        echo '<h2>Log tail</h2>';
        echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:15px;border-radius:5px;max-height:70vh;overflow:auto;">';
        echo htmlspecialchars($tail !== '' ? $tail : '(empty — start a background run)', ENT_QUOTES, 'UTF-8');
        echo '</pre>';
        echo '</main>';
    }
}
