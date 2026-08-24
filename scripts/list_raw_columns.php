<?php
/**
 * List FK columns that still render raw numeric IDs in scaffold list/view cells.
 *
 * Static: flags module index.php files whose cr_render_cell_value() lacks the shared
 * $GLOBALS['fkMap'][$field] label branch (or bespoke FK label handling) for schema FK
 * columns with a human-readable label column (name, title, username, …).
 *
 * CLI:
 *   php scripts/list_raw_columns.php
 *   php scripts/list_raw_columns.php --only-raw
 *   php scripts/list_raw_columns.php --module=problem_ticket_links
 *   php scripts/list_raw_columns.php --only-repro --company=1
 *   php scripts/list_raw_columns.php --all
 *
 * Browser (Administrator): scripts/list_raw_columns.php?run=1
 */

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Static audit: finds flattened CRUD modules whose <strong>list/view</strong> cells still echo raw FK ids (e.g. <code>problem_id = 3</code> instead of <code>problems.title</code>).<br>
Checks <code>cr_render_cell_value()</code> for the shared <code>$GLOBALS['fkMap'][$field]</code> branch or a bespoke FK label resolver.<br>
Optional live repro: pass <code>company=1</code> to probe one tenant row and mark <code>REPRO</code> when rendered HTML is still the raw id.<br>
CLI examples:<br>
<code>php scripts/list_raw_columns.php --only-raw</code><br>
<code>php scripts/list_raw_columns.php --module=problem_ticket_links --company=1</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_raw_fk_column_display_audit.php';

itm_script_output_begin('List raw FK columns');

$isCli = itm_script_cli_is_cli();
$nl = itm_script_output_nl();
$root = rtrim(ROOT_PATH, '/\\');

$companyId = 0;
$moduleFilter = '';
$onlyRaw = false;
$onlyRepro = false;
$showAll = false;

if ($isCli) {
    foreach ($argv ?? [] as $arg) {
        if (strpos($arg, '--company=') === 0) {
            $companyId = (int) substr($arg, 10);
        } elseif (strpos($arg, '--module=') === 0) {
            $moduleFilter = trim((string) substr($arg, 9));
        } elseif ($arg === '--only-raw') {
            $onlyRaw = true;
        } elseif ($arg === '--only-repro') {
            $onlyRepro = true;
        } elseif ($arg === '--all') {
            $showAll = true;
        }
    }
} else {
    itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');
    $companyId = isset($_GET['company']) ? (int) $_GET['company'] : 0;
    $moduleFilter = isset($_GET['module']) ? trim((string) $_GET['module']) : '';
    $onlyRaw = isset($_GET['only_raw']) && (string) $_GET['only_raw'] === '1';
    $onlyRepro = isset($_GET['only_repro']) && (string) $_GET['only_repro'] === '1';
    $showAll = isset($_GET['all']) && (string) $_GET['all'] === '1';
}

$rows = itm_raw_fk_column_audit_run([
    'root' => $root,
    'module' => $moduleFilter,
    'only_raw' => $onlyRaw,
    'only_repro' => $onlyRepro,
    'all' => $showAll,
    'conn' => $conn,
    'company_id' => $companyId,
]);

$rawCount = 0;
$reproCount = 0;
$okCount = 0;
$skipCount = 0;

foreach ($rows as $row) {
    $status = (string) ($row['status'] ?? 'ok');
    if ($status === 'repro') {
        $reproCount++;
        $rawCount++;
    } elseif ($status === 'raw') {
        $rawCount++;
    } elseif ($status === 'skip') {
        $skipCount++;
    } elseif ($status === 'ok') {
        $okCount++;
    }
}

$exitCode = ($rawCount > 0 || $reproCount > 0) ? 1 : 0;

if (!$isCli) {
    itm_script_output_close_pre();

    echo '<h1>Raw FK column display audit</h1>';
    echo '<p><strong>Root:</strong> <code>' . sanitize($root) . '</code></p>';
    if ($companyId > 0) {
        echo '<p><strong>Live company_id:</strong> ' . (int) $companyId . '</p>';
    }
    if ($moduleFilter !== '') {
        echo '<p><strong>Module filter:</strong> <code>' . sanitize($moduleFilter) . '</code></p>';
    }
    if ($onlyRaw) {
        echo '<p><strong>Filter:</strong> only raw / repro columns</p>';
    }
    if ($onlyRepro) {
        echo '<p><strong>Filter:</strong> only live REPRO rows</p>';
    }

    if ($rows === []) {
        echo '<p>' . colorText('[INFO] No matching modules or FK columns in scope.', 'info') . '</p>';
        itm_script_output_end();
        exit(0);
    }

    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:16px 0;width:100%;max-width:100%;font-size:13px;">';
    echo '<thead><tr>';
    echo '<th>Status</th><th>Module</th><th>Table</th><th>Column</th><th>Ref table</th><th>Label col</th><th>Handler</th><th>Notes</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $status = (string) ($row['status'] ?? 'ok');
        $statusLabel = strtoupper($status);
        if (!empty($row['repro'])) {
            $statusLabel = 'REPRO';
        }

        $statusColor = '#1a7f37';
        if ($statusLabel === 'REPRO' || $status === 'repro') {
            $statusColor = '#cf222e';
        } elseif ($status === 'raw') {
            $statusColor = '#bf8700';
        } elseif ($status === 'skip') {
            $statusColor = '#6e7781';
        }

        $slug = (string) ($row['slug'] ?? '');
        $moduleCell = sanitize($slug);
        if ($slug !== '' && function_exists('itm_script_format_modules_file_local_dev_link')) {
            $moduleCell = itm_script_format_modules_file_local_dev_link('modules/' . $slug . '/index.php', $slug);
        }

        echo '<tr>';
        echo '<td style="color:' . $statusColor . ';font-weight:600;">' . sanitize($statusLabel) . '</td>';
        echo '<td>' . $moduleCell . '</td>';
        echo '<td><code>' . sanitize((string) ($row['table'] ?? '')) . '</code></td>';
        echo '<td><code>' . sanitize((string) ($row['column'] ?? '')) . '</code></td>';
        echo '<td><code>' . sanitize((string) ($row['ref_table'] ?? '')) . '</code></td>';
        echo '<td><code>' . sanitize((string) ($row['label_col'] ?? '')) . '</code></td>';
        echo '<td><code>' . sanitize((string) ($row['handler'] ?? '')) . '</code></td>';
        echo '<td>' . sanitize((string) ($row['notes'] ?? '')) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<p><strong>Summary:</strong> ok=' . (int) $okCount . ' raw=' . (int) $rawCount . ' repro=' . (int) $reproCount . ' skip=' . (int) $skipCount . '</p>';

    echo '<div style="margin:16px 0;padding:12px;border:1px dashed #d0d7de;border-radius:6px;font-size:13px;">';
    echo '<p><strong>RAW</strong> = schema FK column with a label table, but <code>cr_render_cell_value()</code> has no shared <code>$GLOBALS[\'fkMap\']</code> branch or bespoke resolver.</p>';
    echo '<p><strong>REPRO</strong> = live tenant row still renders the numeric id in HTML when <code>company=</code> is set.</p>';
    echo '<p><strong>Fix pattern:</strong> <code>modules/license_management/index.php</code> — add <code>cr_fk_label_by_id()</code> + <code>isset($GLOBALS[\'fkMap\'][$field])</code> in <code>cr_render_cell_value()</code>.</p>';
    echo '<p><strong>Example:</strong> <a href="http://localhost/it-management/modules/problem_ticket_links/index.php" target="_blank" rel="noopener noreferrer">modules/problem_ticket_links/index.php</a> — <code>problem_id</code> should show <code>problems.title</code>.</p>';
    echo '</div>';

    if ($exitCode === 1) {
        echo '<p>' . colorText('[FAIL] Raw or live REPRO FK columns found.', 'fail') . '</p>';
    } else {
        echo '<p>' . colorText('[PASS] No raw FK list/view columns in scope.', 'pass') . '</p>';
    }

    itm_script_output_end();
    exit($exitCode);
}

echo colorText('Raw FK column display audit', 'info') . $nl;
echo 'Root: ' . $root . $nl;
if ($companyId > 0) {
    echo 'Live company_id: ' . $companyId . $nl;
}
if ($moduleFilter !== '') {
    echo 'Module filter: ' . $moduleFilter . $nl;
}
echo $nl;

if ($rows === []) {
    echo colorText('[INFO] No matching modules or FK columns in scope.', 'info') . $nl;
    itm_script_output_end();
    exit(0);
}

echo str_pad('Status', 8) . ' '
    . str_pad('Module', 34) . ' '
    . str_pad('Table', 28) . ' '
    . str_pad('Column', 22) . ' '
    . str_pad('Ref', 20) . ' '
    . str_pad('Label', 10) . ' '
    . 'Notes' . $nl;
echo str_repeat('-', 130) . $nl;

foreach ($rows as $row) {
    $status = (string) ($row['status'] ?? 'ok');
    $statusLabel = strtoupper($status);
    if (!empty($row['repro'])) {
        $statusLabel = 'REPRO';
    }

    echo str_pad($statusLabel, 8) . ' '
        . str_pad((string) ($row['slug'] ?? ''), 34) . ' '
        . str_pad((string) ($row['table'] ?? ''), 28) . ' '
        . str_pad((string) ($row['column'] ?? ''), 22) . ' '
        . str_pad((string) ($row['ref_table'] ?? ''), 20) . ' '
        . str_pad((string) ($row['label_col'] ?? ''), 10) . ' '
        . (string) ($row['notes'] ?? '') . $nl;
}

echo $nl;
echo 'Summary: ok=' . $okCount . ' raw=' . $rawCount . ' repro=' . $reproCount . ' skip=' . $skipCount . $nl;
echo $nl;
echo 'RAW = missing $GLOBALS[\'fkMap\'] / bespoke FK label branch in cr_render_cell_value().' . $nl;
echo 'REPRO = live render still shows numeric id when company= is set.' . $nl;
echo 'Fix pattern: modules/license_management/index.php — cr_fk_label_by_id() + isset($GLOBALS[\'fkMap\'][$field]).' . $nl;

if ($exitCode === 1) {
    echo colorText('[FAIL] Raw or live REPRO FK columns found.', 'fail') . $nl;
} else {
    echo colorText('[PASS] No raw FK list/view columns in scope.', 'pass') . $nl;
}

itm_script_output_end();
exit($exitCode);
