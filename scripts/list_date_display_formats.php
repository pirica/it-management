<?php
/**
 * List module date display patterns — dd/mmm/yyyy helpers OK, other formats WARN.
 *
 * Static scan of module PHP under modules/ for UK date helpers vs raw ISO.
 * Does not mutate code (detection only).
 *
 * CLI:
 *   php scripts/list_date_display_formats.php
 *   php scripts/list_date_display_formats.php --only-warn
 *   php scripts/list_date_display_formats.php --module=tickets
 *   php scripts/list_date_display_formats.php --include-inputs
 *
 * Browser (Administrator): scripts/list_date_display_formats.php?run=1
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Static audit: flags module PHP where <strong>displayed</strong> dates omit the UK <code>dd/mmm/yyyy</code> contract.<br>
<strong>OK</strong> — <code>itm_format_date_display()</code>, <code>itm_format_cell_scalar_display()</code>, <code>itm_format_datetime_display()</code>, explicit <code>date('d/M/Y')</code>, or <code>itm_format_hotel_date_display()</code>.<br>
<strong>WARN</strong> — raw ISO echo in list/view (<code>$row['due_date']</code> without a helper), legacy <code>date('d/m/Y')</code>, <code>date('Y-m-d')</code> on output lines, US/text <code>date()</code> patterns.<br>
Native <code>type="date"</code> / <code>datetime-local</code> form controls are <strong>skipped</strong> by default (ISO value is normal for HTML5 inputs). Pass <code>--include-inputs</code> to WARN on those too.<br>
Default lists <strong>WARN</strong> rows, one <strong>OK</strong> <code>module_pass</code> per clean module, and <strong>SKIP</strong> <code>module_skip</code> for exempt modules (<code>reports</code>, <code>ops_report</code>, <code>ops_report_*</code>, <code>settings</code>, <code>backup_tape_log</code>, <code>birthdays</code>, <code>resignations</code>, <code>calendar</code>, <code>explorer</code>, <code>hotel*</code>). Does not scan <code>booking/</code> guest portal (outside <code>modules/</code>). <code>--all</code> adds line-level OK hits; <code>--no-show-pass</code> / <code>--no-show-skips</code> hide pass or skip rows.<br>
CLI examples:<br>
<code>php scripts/list_date_display_formats.php</code><br>
<code>php scripts/list_date_display_formats.php --module=ticket_survey_dashboard</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_module_date_format_display_audit.php';

itm_script_output_begin('List date display formats');

$isCli = itm_script_cli_is_cli();
$nl = itm_script_output_nl();
$root = rtrim(ROOT_PATH, '/\\');

$moduleFilter = '';
$onlyWarn = true;
$showAll = false;
$includeInputs = false;
$showPass = true;
$showModuleSkips = true;

if ($isCli) {
    foreach ($argv ?? [] as $arg) {
        if (strpos($arg, '--module=') === 0) {
            $moduleFilter = trim((string) substr($arg, 9));
        } elseif ($arg === '--only-warn') {
            $onlyWarn = true;
            $showAll = false;
        } elseif ($arg === '--all') {
            $showAll = true;
            $onlyWarn = false;
        } elseif ($arg === '--include-inputs') {
            $includeInputs = true;
        } elseif ($arg === '--show-pass') {
            $showPass = true;
        } elseif ($arg === '--no-show-pass') {
            $showPass = false;
        } elseif ($arg === '--no-show-skips') {
            $showModuleSkips = false;
        }
    }
} else {
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
    $moduleFilter = isset($_GET['module']) ? trim((string) $_GET['module']) : '';
    $onlyWarn = !isset($_GET['all']) || (string) $_GET['all'] !== '1';
    $showAll = isset($_GET['all']) && (string) $_GET['all'] === '1';
    $includeInputs = isset($_GET['include_inputs']) && (string) $_GET['include_inputs'] === '1';
    $showPass = !isset($_GET['no_show_pass']) || (string) $_GET['no_show_pass'] !== '1';
    $showModuleSkips = !isset($_GET['no_show_skips']) || (string) $_GET['no_show_skips'] !== '1';
}

$rows = itm_module_date_format_display_audit_run([
    'root' => $root,
    'module' => $moduleFilter,
    'only_warn' => $onlyWarn && !$showAll,
    'all' => $showAll,
    'include_inputs' => $includeInputs,
    'show_pass' => $showPass,
    'show_module_skips' => $showModuleSkips,
]);

$okCount = 0;
$warnCount = 0;
$skipCount = 0;

foreach ($rows as $row) {
    $status = (string) ($row['status'] ?? 'ok');
    if ($status === 'warn') {
        $warnCount++;
    } elseif ($status === 'skip') {
        $skipCount++;
    } elseif ($status === 'ok') {
        $okCount++;
    }
}

$exitCode = $warnCount > 0 ? 1 : 0;

if (!$isCli) {
    itm_script_output_close_pre();

    echo '<h1>Date display format audit</h1>';
    echo '<p><strong>Root:</strong> <code>' . sanitize($root) . '</code></p>';
    if ($moduleFilter !== '') {
        echo '<p><strong>Module filter:</strong> <code>' . sanitize($moduleFilter) . '</code></p>';
    }
    if ($showAll) {
        echo '<p><strong>Filter:</strong> all rows (OK + WARN)</p>';
    } else {
        echo '<p><strong>Filter:</strong> WARN rows only (<code>?all=1</code> for OK too)</p>';
    }
    if ($includeInputs) {
        echo '<p><strong>Include inputs:</strong> native <code>type="date"</code> / <code>datetime-local</code> counted as WARN</p>';
    } else {
        echo '<p><strong>Display-only:</strong> native date inputs skipped (add <code>?include_inputs=1</code> to WARN them)</p>';
    }

    if ($rows === []) {
        echo '<p>' . colorText('[INFO] No matching rows in scope.', 'info') . '</p>';
        itm_script_output_end();
        exit(0);
    }

    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:16px 0;width:100%;max-width:100%;font-size:13px;">';
    echo '<thead><tr>';
    echo '<th>Status</th><th>Module</th><th>File</th><th>Line</th><th>Pattern</th><th>Format</th><th>Notes</th><th>Snippet</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $status = strtoupper((string) ($row['status'] ?? 'ok'));
        $statusColor = '#1a7f37';
        if ($status === 'WARN') {
            $statusColor = '#bf8700';
        } elseif ($status === 'SKIP') {
            $statusColor = '#6e7781';
        }

        $slug = (string) ($row['module'] ?? '');
        $moduleCell = sanitize($slug);
        if ($slug !== '' && function_exists('itm_script_format_modules_file_local_dev_link')) {
            $moduleCell = itm_script_format_modules_file_local_dev_link('modules/' . $slug . '/index.php', $slug);
        }

        $fileRel = (string) ($row['file'] ?? '');
        $fileCell = '<code>' . sanitize($fileRel) . '</code>';
        if ($fileRel !== '' && function_exists('itm_script_format_modules_file_local_dev_link')) {
            $fileCell = itm_script_format_modules_file_local_dev_link($fileRel, basename($fileRel));
        }

        echo '<tr>';
        echo '<td style="color:' . $statusColor . ';font-weight:600;">' . sanitize($status) . '</td>';
        echo '<td>' . $moduleCell . '</td>';
        echo '<td>' . $fileCell . '</td>';
        echo '<td>' . (int) ($row['line'] ?? 0) . '</td>';
        echo '<td><code>' . sanitize((string) ($row['pattern'] ?? '')) . '</code></td>';
        echo '<td><code>' . sanitize((string) ($row['format'] ?? '')) . '</code></td>';
        echo '<td>' . sanitize((string) ($row['notes'] ?? '')) . '</td>';
        echo '<td><code>' . sanitize((string) ($row['snippet'] ?? '')) . '</code></td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<p><strong>Summary:</strong> ok=' . (int) $okCount . ' warn=' . (int) $warnCount . ' skip=' . (int) $skipCount . '</p>';

    echo '<div style="margin:16px 0;padding:12px;border:1px dashed #d0d7de;border-radius:6px;font-size:13px;">';
    echo '<p><strong>OK</strong> = UK <code>dd/mmm/yyyy</code> via shared helpers or explicit <code>date(\'d/M/Y\')</code>.</p>';
    echo '<p><strong>WARN</strong> = list/view shows ISO or non-UK format without shared helpers.</p>';
    echo '<p>Native form <code>type="date"</code> inputs are skipped unless <code>--include-inputs</code>.</p>';
    echo '<p>Canonical contract: <code>includes/itm_date_format.php</code> and <code>AGENTS.md</code> → Character encoding / dates.</p>';
    echo '</div>';

    if ($exitCode === 1) {
        echo '<p>' . colorText('[WARN] Non-UK date display patterns found.', 'warn') . '</p>';
    } else {
        echo '<p>' . colorText('[PASS] No WARN rows in scope.', 'pass') . '</p>';
    }

    itm_script_output_end();
    exit($exitCode);
}

echo colorText('Date display format audit', 'info') . $nl;
echo 'Root: ' . $root . $nl;
if ($moduleFilter !== '') {
    echo 'Module filter: ' . $moduleFilter . $nl;
}
if ($showAll) {
    echo 'Filter: all rows' . $nl;
} else {
    echo 'Filter: WARN only (pass --all for OK rows)' . $nl;
}
if ($includeInputs) {
    echo 'Include inputs: yes (native date fields WARN)' . $nl;
} else {
    echo 'Display-only: native date inputs skipped (pass --include-inputs to WARN)' . $nl;
}
echo $nl;

if ($rows === []) {
    echo colorText('[INFO] No matching rows in scope.', 'info') . $nl;
    itm_script_output_end();
    exit(0);
}

echo str_pad('Status', 8) . ' '
    . str_pad('Module', 28) . ' '
    . str_pad('File', 42) . ' '
    . str_pad('Line', 6) . ' '
    . str_pad('Pattern', 24) . ' '
    . str_pad('Format', 22) . ' '
    . 'Notes' . $nl;
echo str_repeat('-', 140) . $nl;

foreach ($rows as $row) {
    echo str_pad(strtoupper((string) ($row['status'] ?? 'ok')), 8) . ' '
        . str_pad((string) ($row['module'] ?? ''), 28) . ' '
        . str_pad((string) ($row['file'] ?? ''), 42) . ' '
        . str_pad((string) ((int) ($row['line'] ?? 0)), 6) . ' '
        . str_pad((string) ($row['pattern'] ?? ''), 24) . ' '
        . str_pad((string) ($row['format'] ?? ''), 22) . ' '
        . (string) ($row['notes'] ?? '') . $nl;
}

echo $nl;
echo 'Summary: ok=' . $okCount . ' warn=' . $warnCount . ' skip=' . $skipCount . $nl;
echo $nl;
echo 'OK = dd/mmm/yyyy via itm_format_* helpers.' . $nl;
echo 'WARN = other display format (detection only).' . $nl;

if ($exitCode === 1) {
    echo colorText('[WARN] Non-UK date display patterns found.', 'warn') . $nl;
} else {
    echo colorText('[PASS] No WARN rows in scope.', 'pass') . $nl;
}

itm_script_output_end();
exit($exitCode);
