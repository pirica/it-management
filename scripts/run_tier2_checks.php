<?php
/**
 * Tier 2 static check_* batch runner (pre-merge static cluster).
 *
 * CLI:  php scripts/run_tier2_checks.php [--continue] [--list] [--only=check_ui_action_emoji.php,...]
 * Browser menu: run_tier2_checks.php
 * Browser run: run_tier2_checks.php?run=1&continue=1 (optional continue=1)
 *
 * Why: SCRIPTS_TEST_MATRIX.md Tier 2 lists ~20 static gates not covered by smoke_test.sh.
 */
declare(strict_types=1);

$isCli = (PHP_SAPI === 'cli');
$argvList = $GLOBALS['argv'] ?? [];

$runRequested = $isCli;
if (!$isCli) {
    $runRequested = (($_GET['run'] ?? '') === '1');
}

$continueOnFail = $isCli
    ? in_array('--continue', $argvList, true)
    : (($_GET['continue'] ?? '') === '1');

$wantList = $isCli
    ? in_array('--list', $argvList, true)
    : (($_GET['list'] ?? '') === '1');

$onlyFilter = [];
if ($isCli) {
    foreach ($argvList as $arg) {
        if (strpos((string)$arg, '--only=') === 0) {
            $raw = substr((string)$arg, 7);
            $onlyFilter = array_merge($onlyFilter, preg_split('/\s*,\s*/', $raw) ?: []);
        }
    }
} elseif (isset($_GET['only']) && (string)$_GET['only'] !== '') {
    $onlyFilter = preg_split('/\s*,\s*/', (string)$_GET['only']) ?: [];
}

if (!$isCli && $runRequested) {
    require_once __DIR__ . '/lib/itm_script_regression_entry.php';
} elseif (!$isCli) {
    require_once dirname(__DIR__) . '/config/config.php';
}

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_tier2_check_scripts.php';

$root = dirname(__DIR__);
$scriptsDir = __DIR__;
$nl = itm_script_output_nl();

$resolved = itm_tier2_check_scripts_resolve_list($root);
$scripts = itm_tier2_check_scripts_filter_only($resolved['scripts'], $onlyFilter);

if ($wantList) {
    itm_script_output_begin('Tier 2 static checks (list)');
    echo '[INFO] Source: ' . $resolved['source'] . $nl;
    echo '[INFO] Scripts: ' . count($scripts) . $nl . $nl;
    foreach ($scripts as $script) {
        echo $script . $nl;
    }
    exit(0);
}

if (!$runRequested) {
    itm_script_output_begin('Tier 2 static checks');
    $self = basename(__FILE__);
    echo colorText('Tier 2 static check batch', 'info') . $nl . $nl;
    echo 'Runs every Tier 2 `check_*` script from SCRIPTS_TEST_MATRIX.md (no DB mutation).' . $nl;
    echo 'Source: ' . htmlspecialchars($resolved['source'], ENT_QUOTES, 'UTF-8') . ' — '
        . count($resolved['scripts']) . ' script(s).' . $nl . $nl;
    echo 'CLI:' . $nl;
    echo '  php scripts/' . $self . $nl;
    echo '  php scripts/' . $self . ' --continue' . $nl;
    echo '  php scripts/' . $self . ' --only=check_ui_action_emoji.php' . $nl . $nl;
    if (!$isCli) {
        itm_script_output_close_pre();
        $runUrl = htmlspecialchars($self . '?run=1', ENT_QUOTES, 'UTF-8');
        $runContinueUrl = htmlspecialchars($self . '?run=1&continue=1', ENT_QUOTES, 'UTF-8');
        echo '<p><a class="btn btn-primary" href="' . $runUrl . '">Run Tier 2 batch</a> ';
        echo '<a class="btn" href="' . $runContinueUrl . '">Run all (continue on fail)</a></p>';
        itm_script_output_end();
    }
    exit(0);
}

itm_script_output_begin('Tier 2 static checks (running)');
$phpBinary = itm_tier2_check_scripts_resolve_php_binary();

echo colorText('Tier 2 static check batch', 'info') . $nl;
echo '[INFO] PHP binary: ' . $phpBinary . $nl;
echo '[INFO] Script list: ' . $resolved['source'] . ' (' . count($scripts) . ' selected)' . $nl;
echo '[INFO] Mode: ' . ($continueOnFail ? 'continue on failure' : 'stop on first failure') . $nl . $nl;

if ($scripts === []) {
    echo itm_script_format_status_line('[FAIL] No Tier 2 scripts matched the filter.') . $nl;
    exit(1);
}

$passed = 0;
$failed = 0;
$missing = 0;
$failures = [];

foreach ($scripts as $index => $basename) {
    $step = ($index + 1) . '/' . count($scripts);
    echo '==> [' . $step . '] ' . $basename . $nl;

    $run = itm_tier2_check_scripts_run_one($phpBinary, $scriptsDir, $basename);
    $exit = (int)$run['exit'];

    if ($exit === 127 && strpos($run['output'], '[MISSING]') === 0) {
        $missing++;
        $failed++;
        echo itm_script_format_status_line('[FAIL] Missing script file.') . $nl;
        $failures[] = ['script' => $basename, 'exit' => $exit, 'output' => $run['output']];
        if (!$continueOnFail) {
            break;
        }
        echo $nl;
        continue;
    }

    if ($exit === 0) {
        $passed++;
        echo itm_script_format_status_line('[PASS] exit 0 (' . $run['seconds'] . 's)') . $nl;
    } else {
        $failed++;
        echo itm_script_format_status_line('[FAIL] exit ' . $exit . ' (' . $run['seconds'] . 's)') . $nl;
        $tail = trim(itm_tier2_check_scripts_tail_output($run['output']));
        if ($tail !== '') {
            echo '--- output tail ---' . $nl . $tail . $nl . '--- end tail ---' . $nl;
        }
        $failures[] = ['script' => $basename, 'exit' => $exit, 'output' => $run['output']];
        if (!$continueOnFail) {
            break;
        }
    }

    echo $nl;
}

echo str_repeat('-', 60) . $nl;
echo '[INFO] Summary: pass=' . $passed . ' fail=' . $failed . ' missing=' . $missing . $nl;

if ($failed > 0) {
    echo itm_script_format_status_line('[FAIL] Tier 2 batch failed.') . $nl;
    foreach ($failures as $row) {
        echo '  - ' . $row['script'] . ' (exit ' . (int)$row['exit'] . ')' . $nl;
    }
    exit(1);
}

echo itm_script_format_status_line('[PASS] All Tier 2 static checks passed.') . $nl;
exit(0);
