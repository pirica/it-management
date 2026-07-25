<?php
/**
 * One-shot safe matrix runner (tiers 1-3). Writes JSON results for the agent report.
 * Not cataloged — disposable for this session.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$php = 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe';
$bash = 'C:\\Program Files\\Git\\bin\\bash.exe';
$matrixPath = $root . '/scripts/SCRIPTS_TEST_MATRIX.md';
$outJson = $root . '/qa-reports/scripts-matrix-safe-run-raw.json';
$outLogDir = $root . '/qa-reports/scripts-matrix-logs';

if (!is_dir($outLogDir)) {
    mkdir($outLogDir, 0777, true);
}
if (!is_dir(dirname($outJson))) {
    mkdir(dirname($outJson), 0777, true);
}

$branch = trim((string)shell_exec('git -C ' . escapeshellarg($root) . ' branch --show-current 2>nul'));
$started = date('c');

$lines = file($matrixPath, FILE_IGNORE_NEW_LINES);
$entries = [];
foreach ($lines as $line) {
    if (!preg_match('/^\|\s*([0-5])\s*\|\s*`([^`]+)`\s*\|/', $line, $m)) {
        continue;
    }
    $tier = (int)$m[1];
    $script = $m[2];
    $entries[] = ['tier' => $tier, 'script' => $script];
}

$results = [];
$smokePassed = false;

function itm_matrix_tail(string $text, int $max = 2500): string
{
    $text = preg_replace('/\x1b\[[0-9;]*m/', '', $text);
    if (strlen($text) <= $max) {
        return $text;
    }
    return substr($text, -$max);
}

function itm_matrix_run(string $cmd, int $timeoutSec, string $cwd): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, $cwd);
    if (!is_resource($proc)) {
        return ['exit' => 127, 'out' => 'proc_open failed', 'sec' => 0];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = '';
    $start = microtime(true);
    $timedOut = false;
    while (true) {
        $status = proc_get_status($proc);
        $out .= stream_get_contents($pipes[1]);
        $out .= stream_get_contents($pipes[2]);
        if (!$status['running']) {
            break;
        }
        if ((microtime(true) - $start) > $timeoutSec) {
            $timedOut = true;
            proc_terminate($proc, 9);
            break;
        }
        usleep(50000);
    }
    $out .= stream_get_contents($pipes[1]);
    $out .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($timedOut) {
        $code = 124;
        $out .= "\n[TIMEOUT after {$timeoutSec}s]\n";
    }
    return ['exit' => (int)$code, 'out' => $out, 'sec' => round(microtime(true) - $start, 2)];
}

foreach ($entries as $entry) {
    $tier = $entry['tier'];
    $script = $entry['script'];
    $row = [
        'tier' => $tier,
        'script' => $script,
        'status' => 'SKIP',
        'exit' => null,
        'sec' => 0,
        'note' => '',
        'tail' => '',
    ];

    if ($tier === 0) {
        $row['status'] = 'SKIP';
        $row['note'] = 'docs-only';
        $results[] = $row;
        fwrite(STDOUT, "SKIP tier0 {$script}\n");
        continue;
    }
    if ($tier >= 4) {
        $row['status'] = 'EXCLUDED';
        $row['note'] = $tier === 4 ? 'tier4-mutates-DB' : 'tier5-destructive-or-maintenance';
        $results[] = $row;
        continue;
    }
    if ($script === 'verify_database_sql_import.sh') {
        $row['status'] = 'SKIP';
        $row['note'] = 'destroys-DB; substituted verify_database_schema.php + count_db_tables.php';
        $results[] = $row;
        fwrite(STDOUT, "SKIP wipe {$script}\n");
        continue;
    }
    if (in_array($script, ['check_csrf_coverage.php', 'check_fk_label_search_coverage.php', 'check_sql_injection_coverage.php'], true) && $smokePassed) {
        $row['status'] = 'COVERED';
        $row['note'] = 'covered by smoke_test.sh';
        $results[] = $row;
        fwrite(STDOUT, "COVERED {$script}\n");
        continue;
    }

    $path = $root . '/scripts/' . $script;
    if (!is_file($path) && $script !== 'smoke_test.sh') {
        // some live at scripts/ root with exact name
        $path = $root . '/scripts/' . $script;
    }
    if ($script === 'smoke_test.sh') {
        $path = $root . '/scripts/smoke_test.sh';
    }
    if (!is_file($path)) {
        $row['status'] = 'SKIP';
        $row['note'] = 'file-missing';
        $results[] = $row;
        fwrite(STDOUT, "SKIP missing {$script}\n");
        continue;
    }

    $timeout = 120;
    if ($script === 'run_tests.php' || $script === 'smoke_test.sh') {
        $timeout = 600;
    }
    if (preg_match('/^module_browser|^idfs_sync|^explorer_human/', $script)) {
        $timeout = 900;
    }

    $ext = strtolower(pathinfo($script, PATHINFO_EXTENSION));
    if ($ext === 'sh') {
        $cmd = 'set PHP_BIN=' . $php . '&& "' . $bash . '" "' . $path . '"';
        putenv('PHP_BIN=' . $php);
        putenv('PATH=' . dirname($php) . PATH_SEPARATOR . (string)getenv('PATH'));
    } elseif ($ext === 'py') {
        $py = trim((string)shell_exec('where python 2>nul'));
        if ($py === '') {
            $py = trim((string)shell_exec('where python3 2>nul'));
        }
        if ($py === '') {
            $row['status'] = 'SKIP';
            $row['note'] = 'python-not-on-PATH';
            $results[] = $row;
            fwrite(STDOUT, "SKIP no-python {$script}\n");
            continue;
        }
        $pyBin = preg_split('/\r?\n/', $py)[0];
        $cmd = '"' . $pyBin . '" "' . $path . '"';
    } elseif ($ext === 'php') {
        $cmd = '"' . $php . '" "' . $path . '"';
    } else {
        $row['status'] = 'SKIP';
        $row['note'] = 'unsupported-ext';
        $results[] = $row;
        continue;
    }

    fwrite(STDOUT, "RUN tier{$tier} {$script} ... ");
    flush();
    $run = itm_matrix_run($cmd, $timeout, $root);
    $row['exit'] = $run['exit'];
    $row['sec'] = $run['sec'];
    $row['tail'] = itm_matrix_tail($run['out']);
    $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $script);
    file_put_contents($outLogDir . '/' . $safeName . '.log', $run['out']);

    if ($run['exit'] === 0) {
        $row['status'] = 'PASS';
        $row['note'] = 'ok';
        if ($script === 'smoke_test.sh') {
            $smokePassed = true;
        }
        fwrite(STDOUT, "PASS ({$run['sec']}s)\n");
    } elseif ($run['exit'] === 124) {
        $row['status'] = 'FAIL';
        $row['note'] = 'timeout';
        fwrite(STDOUT, "TIMEOUT\n");
    } else {
        // Treat SMTP scripts that clearly need external mail as SKIP if message says so
        $low = strtolower($run['out']);
        if (strpos($script, 'test_email') !== false || strpos($script, 'test_register_mail') !== false) {
            if (strpos($low, 'smtp') !== false || strpos($low, 'mail') !== false) {
                // still FAIL if exit non-zero — classify later
            }
        }
        $row['status'] = 'FAIL';
        $row['note'] = 'exit-' . $run['exit'];
        fwrite(STDOUT, "FAIL exit={$run['exit']} ({$run['sec']}s)\n");
    }
    $results[] = $row;
}

// Substitute schema checks for skipped wipe
foreach (['verify_database_schema.php', 'count_db_tables.php'] as $extra) {
    $already = false;
    foreach ($results as $r) {
        if ($r['script'] === $extra) {
            $already = true;
            break;
        }
    }
    if ($already) {
        continue;
    }
    $path = $root . '/scripts/' . $extra;
    if (!is_file($path)) {
        continue;
    }
    fwrite(STDOUT, "RUN substitute {$extra} ... ");
    $run = itm_matrix_run('"' . $php . '" "' . $path . '"', 120, $root);
    $results[] = [
        'tier' => 1,
        'script' => $extra,
        'status' => $run['exit'] === 0 ? 'PASS' : 'FAIL',
        'exit' => $run['exit'],
        'sec' => $run['sec'],
        'note' => 'substitute-for-verify_database_sql_import.sh',
        'tail' => itm_matrix_tail($run['out']),
    ];
    file_put_contents($outLogDir . '/' . $extra . '.log', $run['out']);
    fwrite(STDOUT, ($run['exit'] === 0 ? 'PASS' : 'FAIL') . "\n");
}

$counts = ['PASS' => 0, 'FAIL' => 0, 'SKIP' => 0, 'EXCLUDED' => 0, 'COVERED' => 0];
foreach ($results as $r) {
    if (isset($counts[$r['status']])) {
        $counts[$r['status']]++;
    }
}

$payload = [
    'started' => $started,
    'finished' => date('c'),
    'branch' => $branch,
    'php' => $php,
    'counts' => $counts,
    'results' => $results,
];
file_put_contents($outJson, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
fwrite(STDOUT, "\nDONE counts=" . json_encode($counts) . "\nWrote {$outJson}\n");
exit(0);
