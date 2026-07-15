<?php
/**
 * Shared build-report logic for module_browser_qa_build_report.php.
 *
 * Entry scripts set $GLOBALS['mbqa_build_report_config'] before requiring this file.
 */
declare(strict_types=1);

require_once __DIR__ . '/script_cli_output.php';
require_once __DIR__ . '/script_browser_nav.php';
require_once __DIR__ . '/utf8_file.php';
require_once __DIR__ . '/mbqa_report_paths.php';
require_once __DIR__ . '/mbqa_report_xlsx.php';
require_once __DIR__ . '/mbqa_runner_tiers.php';
require_once __DIR__ . '/mbqa_step_display.php';

/**
 * @return array{self_script:string,runner_script:string,runner_label:string,page_title:string,rerun_ui_click_smoke:bool,md_runner_cli:string,md_runner_browser:string}
 */
function mbqar_app_config(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $custom = $GLOBALS['mbqa_build_report_config'] ?? null;
    if (!is_array($custom)) {
        $custom = [];
    }

    $cached = [
        'self_script' => (string)($custom['self_script'] ?? 'module_browser_qa_build_report.php'),
        'runner_script' => (string)($custom['runner_script'] ?? 'module_browser_qa_runner.php'),
        'runner_label' => (string)($custom['runner_label'] ?? 'Run QA runner'),
        'page_title' => (string)($custom['page_title'] ?? 'Module browser QA — build report'),
        'rerun_ui_click_smoke' => !empty($custom['rerun_ui_click_smoke']),
        'md_runner_cli' => (string)($custom['md_runner_cli'] ?? 'php scripts/module_browser_qa_runner.php'),
        'md_runner_browser' => (string)($custom['md_runner_browser'] ?? 'scripts/module_browser_qa_runner.php'),
    ];

    return $cached;
}

/**
 * @return array{run:bool, help:bool, date:string, date_explicit:bool}
 */
function mbqar_parse_options(): array
{
    $options = [
        'run' => false,
        'help' => false,
        'date' => date('Y-m-d'),
        'date_explicit' => false,
    ];

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        $options['run'] = true;
        foreach (array_slice($GLOBALS['argv'] ?? [], 1) as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $options['help'] = true;
                $options['run'] = false;
                continue;
            }
            if (strpos($arg, '--date=') === 0) {
                $options['date'] = trim(substr($arg, 7));
                $options['date_explicit'] = true;
            }
        }
    } else {
        $options['run'] = isset($_GET['run']) || isset($_POST['run']);
        $options['help'] = isset($_GET['help']);
        if (isset($_REQUEST['date']) && trim((string)$_REQUEST['date']) !== '') {
            $options['date'] = trim((string)$_REQUEST['date']);
            $options['date_explicit'] = true;
        }
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $options['date'])) {
        $options['date'] = date('Y-m-d');
    }

    return $options;
}

function mbqar_is_cli_sapi(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function mbqar_out(string $message): void
{
    echo $message;
}

function mbqar_err(string $message): void
{
    if (mbqar_is_cli_sapi()) {
        fwrite(STDERR, $message);
    } else {
        mbqar_out($message);
    }
}

function mbqar_print_help(): void
{
    $cfg = mbqar_app_config();
    if (!mbqar_is_cli_sapi()) {
        header('Content-Type: text/html; charset=utf-8');
        itm_script_browser_nav_echo();
        echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
        echo '<h1>' . htmlspecialchars($cfg['page_title'], ENT_QUOTES, 'UTF-8') . '</h1>';
    } else {
        itm_script_output_begin($cfg['page_title']);
    }

    mbqar_out("Build markdown from the latest qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json\n\n");
    mbqar_out("Options:\n");
    mbqar_out("  --date=YYYY-MM-DD   Use qa-reports/module-browser-qa-YYYY-MM-DD.json (legacy day file)\n");
    mbqar_out("  --help              Show this help\n\n");
    mbqar_out("Output: qa-reports/module-browser-qa.md and a timestamped module-browser-qa-*.xlsx\n\n");
    mbqar_out("QA runner tier reference:\n");
    mbqar_out("  \$bespokeSmoke (Tier D): " . implode(', ', mbqa_runner_bespoke_smoke_modules()) . "\n");
    mbqar_out("  \$skipClear: " . implode(', ', mbqa_runner_skip_clear_modules()) . "\n\n");

    if (!mbqar_is_cli_sapi()) {
        mbqar_out("Browser: submit the form or use ?run=1\n");
        mbqar_echo_runner_tier_reference_html();
        echo '<p><a href="' . htmlspecialchars($cfg['self_script'], ENT_QUOTES, 'UTF-8') . '">← Back to form</a></p></main>';
    }
}

/**
 * @param array{run:bool, help:bool, date:string} $options
 */
/**
 * Browser URL to re-run the QA runner with the same scope as the JSON report.
 *
 * @param array<string, mixed> $payload Decoded runner JSON (results + optional run_options).
 */
function mbqar_rerun_runner_href(array $payload): string
{
    $cfg = mbqar_app_config();
    $runnerScript = $cfg['runner_script'];
    $params = ['autostart' => '1'];
    $opts = $payload['run_options'] ?? null;

    if (!is_array($opts)) {
        $modules = [];
        $companies = [];
        $rows = $payload['results'] ?? [];
        if (!is_array($rows)) {
            $rows = is_array($payload) && isset($payload[0]) ? $payload : [];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug = (string)($row['module'] ?? '');
            if ($slug === '' || $slug === '_preflight') {
                continue;
            }
            $modules[$slug] = true;
            $companyId = (int)($row['company_id'] ?? 0);
            if ($companyId > 0) {
                $companies[$companyId] = true;
            }
        }
        $moduleList = array_keys($modules);
        sort($moduleList, SORT_STRING);
        $companyList = array_keys($companies);
        sort($companyList, SORT_NUMERIC);
        if (count($moduleList) === 1) {
            $params['module'] = $moduleList[0];
        }
        if (count($companyList) === 1) {
            $params['company'] = (string)$companyList[0];
        }
        if (count($moduleList) === 1
            && $moduleList[0] === 'expenses'
            && count($companyList) === 5) {
            $params['pilot_only'] = '1';
            unset($params['module']);
        }

        return $runnerScript . '?' . http_build_query($params);
    }

    $module = $opts['module'] ?? null;
    if (is_string($module) && trim($module) !== '') {
        $params['module'] = trim($module);
    }
    $company = $opts['company'] ?? null;
    if ($company !== null && (int)$company > 0) {
        $params['company'] = (string)(int)$company;
    }
    if (!empty($opts['pilot_only'])) {
        $params['pilot_only'] = '1';
        unset($params['module']);
    }
    if ($cfg['rerun_ui_click_smoke'] && !empty($opts['ui_click_smoke'])) {
        $params['ui_click_smoke'] = '1';
    }
    $baseUrl = trim((string)($opts['base_url'] ?? ''));
    if ($baseUrl !== '' && $baseUrl !== 'http://localhost/it-management/') {
        $params['base_url'] = $baseUrl;
    }

    return $runnerScript . '?' . http_build_query($params);
}

/**
 * Plain-language label for a runner step slug (markdown tables).
 */
function mbqar_human_step_label(string $step): string
{
    static $labels = [
        'mysql' => 'database.sql seed rows',
        'error_log' => 'Error log',
        'list' => 'List page',
        'ui_check' => 'Table Actions UI',
        'clear' => 'Tenant clear',
        'sample_data' => 'Sample data',
        'add' => 'Bulk random rows',
        'pagination' => 'Pagination',
        'bulk_cancel' => 'Bulk Cancel UI',
        'bulk_delete' => 'Bulk delete',
        'search' => 'Search',
        'sort' => 'Sort links',
        'create' => 'Create form',
        'view' => 'View record',
        'edit' => 'Edit form',
        'list_all' => 'List all',
        'export_pdf' => 'Export PDF',
        'export_xls' => 'Export Excel (.xlsx)',
        'export_xlsx' => 'Export Excel (.xlsx)',
        'import_db' => 'Import Excel',
        'single_delete' => 'Single delete',
        'clear_table' => 'Clear table',
        'company_switch' => 'Company switch',
    ];

    return $labels[$step] ?? $step;
}

/**
 * Default explanation when a step fails often (Tier A checklist).
 */
function mbqar_step_typical_cause(string $step): string
{
    static $causes = [
        'mysql' => 'database.sql could not be read or parsed for INSERT rows on this module table',
        'error_log' => 'PHP warnings, notices, or fatals were written during this module run',
        'list' => 'Index page did not return HTTP 200 or contained a fatal error',
        'ui_check' => 'Actions column missing class itm-actions-cell and/or data-itm-actions-origin="1" on header or body cells',
        'clear' => 'Could not delete tenant rows (FK children still reference parent rows)',
        'sample_data' => 'Sample seed POST failed or left the table empty (missing seed rows or FK parents)',
        'add' => 'Runner could not insert enough random rows (unique keys, missing FK parents, or column out of range)',
        'pagination' => 'Page 1/2 missing Next or Previous link when row count exceeds records per page',
        'bulk_cancel' => 'Bulk delete form or Cancel button missing from index HTML',
        'bulk_delete' => 'Bulk delete POST to delete.php failed or returned an error',
        'search' => 'Search input or results table missing on the index page',
        'sort' => 'Default sort column link missing (visible default may not be id)',
        'create' => 'Create form missing or blocked (often missing FK parent rows after clear)',
        'view' => 'No row to open or view page returned an error',
        'edit' => 'No row to edit or edit form returned an error',
        'list_all' => 'List-all page missing table or returned an error',
        'export_pdf' => 'Export PDF control missing from the list table HTML',
        'export_xls' => 'Export Excel (.xlsx) control or SheetJS library missing from index HTML',
        'export_xlsx' => 'Export Excel (.xlsx) control or SheetJS library missing from index HTML',
        'import_db' => 'Import round-trip failed (endpoint, headers, values, or unique constraint)',
        'single_delete' => 'Delete POST blocked by FK usage or returned an error',
        'clear_table' => 'Clear-table POST failed or transaction rolled back',
        'company_switch' => 'Session company scope did not switch to the requested tenant',
    ];

    return $causes[$step] ?? 'See module notes below or the Failures only index';
}

/**
 * Shorten a runner note for summary tables (pipe-safe).
 */
function mbqar_shorten_note(string $note, int $maxLen = 120): string
{
    $note = trim(str_replace(["\r", "\n"], ' ', $note));
    $note = str_replace('|', '/', $note);
    if (strlen($note) <= $maxLen) {
        return $note;
    }

    return substr($note, 0, $maxLen - 3) . '...';
}

/**
 * Turn the first failure note for a step into a plain-language “this run” hint.
 */
function mbqar_this_run_hint(string $step, array $failRows): string
{
    foreach ($failRows as $fr) {
        if (($fr['step'] ?? '') !== $step) {
            continue;
        }
        $note = trim((string)($fr['notes'] ?? ''));
        if ($note === '') {
            continue;
        }
        if (preg_match("/Out of range value for column '([^']+)'/i", $note, $m)) {
            return "This run: value out of range for column `{$m[1]}`";
        }
        if (stripos($note, 'Could not insert random rows') !== false) {
            return 'This run: ' . mbqar_shorten_note($note, 100);
        }
        if (stripos($note, 'HTTP sample seed') !== false || stripos($note, 'sample seed') !== false) {
            return 'This run: ' . mbqar_shorten_note($note, 100);
        }
        if (stripos($note, 'missing') !== false || stripos($note, 'not found') !== false) {
            return 'This run: ' . mbqar_shorten_note($note, 100);
        }

        return 'This run: ' . mbqar_shorten_note($note, 100);
    }

    return '';
}

/**
 * Pass/Fail → short result text for preflight and quick tables.
 */
function mbqar_human_status(string $status, string $note = ''): string
{
    return mbqa_step_human_result($status, $note);
}

/**
 * Keep aligned with mbqa_step_note_is_skip_quick_index() in module_browser_qa_runner.php.
 */
function mbqar_step_note_is_skip_quick_index(string $note): bool
{
    return mbqa_step_note_is_na_or_skip($note);
}

/**
 * @param array{run:bool, help:bool, date:string, date_explicit:bool} $options
 * @return array{json_path:string, using_legacy:bool}
 */
function mbqar_resolve_report_json_path(string $root, array $options): array
{
    $date = $options['date'];
    $legacyPath = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
        ? mbqa_report_legacy_json_path($root, $date)
        : '';

    if ($options['date_explicit'] && $legacyPath !== '') {
        return ['json_path' => $legacyPath, 'using_legacy' => true];
    }

    $latestPath = mbqa_report_find_latest_json_path($root);
    if ($latestPath !== '') {
        return ['json_path' => $latestPath, 'using_legacy' => false];
    }

    if ($legacyPath !== '' && is_file($legacyPath)) {
        return ['json_path' => $legacyPath, 'using_legacy' => true];
    }

    return ['json_path' => mbqa_report_dir($root) . DIRECTORY_SEPARATOR . 'module-browser-qa.json', 'using_legacy' => false];
}

function mbqar_render_browser_form(array $options): void
{
    $cfg = mbqar_app_config();
    header('Content-Type: text/html; charset=utf-8');
    itm_script_browser_nav_echo();

    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
    echo '<h1>' . htmlspecialchars($cfg['page_title'], ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<p>Reads the latest <code>qa-reports/module-browser-qa-YYYY-MM-DD-HH-MM-SS.json</code> from the runner and writes <code>module-browser-qa.md</code> plus a matching timestamped XLSX.</p>';
    echo '<form method="get" action="' . htmlspecialchars($cfg['self_script'], ENT_QUOTES, 'UTF-8') . '" style="display:grid;gap:12px;max-width:360px;">';
    echo '<input type="hidden" name="run" value="1">';
    echo '<button type="submit" style="padding:10px 16px;font-weight:600;">Build report</button>';
    echo '</form>';
    if ($options['date'] !== date('Y-m-d')) {
        $legacyDate = htmlspecialchars($options['date'], ENT_QUOTES, 'UTF-8');
        echo '<p style="font-size:0.9rem;">Legacy dated JSON: <a href="'
            . htmlspecialchars($cfg['self_script'], ENT_QUOTES, 'UTF-8') . '?run=1&amp;date='
            . rawurlencode($legacyDate) . '">build from ' . $legacyDate . '</a></p>';
    }
    echo '<p style="margin-top:20px;font-size:0.9rem;"><a href="'
        . htmlspecialchars($cfg['runner_script'], ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($cfg['runner_label'], ENT_QUOTES, 'UTF-8') . '</a> · ';
    echo '<a href="' . htmlspecialchars($cfg['self_script'], ENT_QUOTES, 'UTF-8') . '?help=1">Help</a></p>';
    mbqar_echo_runner_tier_reference_html();
    echo '</main>';
}

/**
 * @param array<int, mixed> $data
 * @param array<string, array<string, string>> $moduleStepExceptions
 * @return array{md:string, pass:int, fail:int, json_path:string, out_path:string}
 */
function mbqar_build_markdown(string $root, string $date, array $data, array $moduleStepExceptions = []): array
{
    $pass = 0;
    $fail = 0;
    $skipRows = [];
    $failRows = [];
    $moduleRows = [];
    $pilotRows = [];
    $preflight = [];

    foreach ($data as $row) {
        if (($row['module'] ?? '') === '_preflight') {
            $preflight[] = $row;
            continue;
        }
        $moduleRows[] = $row;
        foreach ($row['steps'] as $step) {
            if (($step['status'] ?? '') === 'Pass') {
                $pass++;
                $note = (string)($step['notes'] ?? '');
                if (mbqar_step_note_is_skip_quick_index($note)) {
                    $skipRows[] = [
                        'module' => $row['module'],
                        'company_id' => $row['company_id'],
                        'step' => $step['step'],
                        'notes' => $note,
                    ];
                }
            } else {
                $fail++;
                $failRows[] = [
                    'module' => $row['module'],
                    'company_id' => $row['company_id'],
                    'step' => $step['step'],
                    'notes' => $step['notes'] ?? '',
                ];
            }
        }
        if (($row['module'] ?? '') === 'expenses') {
            $pilotRows[] = $row;
        }
    }

    $failCats = [];
    foreach ($data as $row) {
        if (($row['module'] ?? '') === '_preflight') {
            continue;
        }
        foreach ($row['steps'] as $step) {
            if (($step['status'] ?? '') === 'Fail') {
                $k = (string)($step['step'] ?? 'unknown');
                $failCats[$k] = ($failCats[$k] ?? 0) + 1;
            }
        }
    }
    arsort($failCats);

    $cfg = mbqar_app_config();
    $md = "# Module browser QA — {$date}\n\n";
    $md .= "## Summary\n\n";
    $md .= "- Environment: `http://localhost/it-management/` (Laragon)\n";
    $md .= "- Auth: Admin / Admin\n";
    $md .= "- Companies: 5 (TechCorp Global … Enterprise IT)\n";
    $md .= "- Step outcomes: **{$pass} Pass**, **{$fail} Fail**\n";
    $md .= '- Modules in this report: ' . count($moduleRows) . "\n";
    $md .= '- Runner: `' . $cfg['md_runner_cli'] . '` or browser form at `' . $cfg['md_runner_browser'] . "`\n";
    $md .= "- Bulk delete / Clear table: N/A when row count &lt; `records_per_page` (25)\n\n";
    $md .= mbqar_runner_tier_reference_markdown();

    if (!empty($moduleStepExceptions)) {
        $md .= "### Skipped steps (configured exceptions — counted as Pass/N/A)\n\n";
        $md .= "| Module | Step | Label | Reason |\n|---|---|---|---|\n";
        foreach ($moduleStepExceptions as $moduleSlug => $stepNotes) {
            if (!is_array($stepNotes)) {
                continue;
            }
            foreach ($stepNotes as $stepName => $note) {
                $stepSlug = (string)$stepName;
                $md .= '| ' . $moduleSlug . ' | `' . $stepSlug . '` | '
                    . mbqar_human_step_label($stepSlug) . ' | '
                    . str_replace('|', '/', (string)$note) . " |\n";
            }
        }
        $md .= "\n";
    }

    $md .= "### Failure summary (by step)\n\n";
    $md .= "Counts are across all modules and companies in this JSON. ";
    $md .= "**Typical cause** is the usual reason; **This run** is taken from the first matching failure note when available.\n\n";
    if (empty($failCats)) {
        $md .= "_No failing steps in this run._\n\n";
    } else {
        $md .= "| Step | Label | Failures | Typical cause | This run |\n|---|---|---:|---|---|\n";
        foreach ($failCats as $k => $v) {
            $typical = mbqar_step_typical_cause($k);
            $thisRun = mbqar_this_run_hint($k, $failRows);
            $md .= '| `' . $k . '` | ' . mbqar_human_step_label($k) . ' | ' . $v . ' | '
                . $typical . ' | ' . $thisRun . " |\n";
        }
        $md .= "\n";
    }

    $md .= "## Preflight (company switch)\n\n";
    $md .= "Verifies the runner can switch session scope to each company before module tests.\n\n";
    if (empty($preflight)) {
        $md .= "_No preflight rows in JSON (single-company runs may omit this)._\n\n";
    } else {
        $md .= "| Company ID | Company name | Result | Notes |\n|---:|---|---|---|\n";
        foreach ($preflight as $pf) {
            $step = $pf['steps'][0] ?? [];
            $st = (string)($step['status'] ?? '?');
            $notes = mbqar_shorten_note((string)($step['notes'] ?? ''), 80);
            if ($st === 'Pass' && $notes === '') {
                $notes = 'Session switched to this company';
            }
            $md .= '| ' . (int)$pf['company_id'] . ' | ' . ($pf['company_name'] ?? '') . ' | '
                . mbqar_human_status($st, $notes) . ' | ' . $notes . " |\n";
        }
        $md .= "\n";
    }

    $md .= "## Results by module (Pass and Fail)\n\n";
    if (empty($moduleRows)) {
        $md .= "_No module rows in JSON._\n";
    } else {
        foreach ($moduleRows as $moduleRow) {
            $md .= '### ' . ($moduleRow['module'] ?? '') . ' — company ' . (int)($moduleRow['company_id'] ?? 0);
            $companyName = trim((string)($moduleRow['company_name'] ?? ''));
            if ($companyName !== '') {
                $md .= ' (' . $companyName . ')';
            }
            $md .= "\n\n| Step | Label | Result | Notes |\n|---|---|---|---|\n";
            foreach ($moduleRow['steps'] as $step) {
                $stepSlug = (string)($step['step'] ?? '');
                $note = mbqar_shorten_note((string)($step['notes'] ?? ''), 160);
                $md .= '| `' . $stepSlug . '` | ' . mbqar_human_step_label($stepSlug) . ' | '
                    . mbqar_human_status((string)($step['status'] ?? ''), $note) . ' | ' . $note . " |\n";
            }
            $md .= "\n";
        }
    }

    if (!empty($pilotRows)) {
        $md .= "\n## Expenses pilot (5 companies)\n\n";
        foreach ($pilotRows as $pr) {
            $md .= "### Company " . (int)$pr['company_id'] . ' — ' . ($pr['company_name'] ?? '') . "\n\n";
            $md .= "| Step | Label | Result | Notes |\n|---|---|---|---|\n";
            foreach ($pr['steps'] as $step) {
                $stepSlug = (string)($step['step'] ?? '');
                $pilotNote = mbqar_shorten_note((string)($step['notes'] ?? ''), 160);
                $md .= '| `' . $stepSlug . '` | ' . mbqar_human_step_label($stepSlug) . ' | '
                    . mbqar_human_status((string)($step['status'] ?? ''), $pilotNote) . ' | '
                    . $pilotNote . " |\n";
            }
            $md .= "\n";
        }
    }

    $md .= "## Failures only (quick index)\n\n";
    if (empty($failRows)) {
        $md .= "_No failures recorded._\n";
    } else {
        $md .= "| Module | Co | Step | Label | Notes |\n|---|---|---|---|---|\n";
        $shown = 0;
        foreach ($failRows as $fr) {
            $stepSlug = (string)$fr['step'];
            $md .= '| ' . $fr['module'] . ' | ' . $fr['company_id'] . ' | `' . $stepSlug . '` | '
                . mbqar_human_step_label($stepSlug) . ' | '
                . mbqar_shorten_note((string)$fr['notes'], 160) . " |\n";
            if (++$shown >= 200) {
                $md .= "\n_(truncated; see JSON for full list)_\n";
                break;
            }
        }
    }

    $md .= "\n## Skip (quick index)\n\n";
    if (empty($skipRows)) {
        $md .= "_No skipped steps recorded (notes starting with Skip or N/A)._\n";
    } else {
        $md .= "| Module | Co | Step | Label | Notes |\n|---|---|---|---|---|\n";
        $shown = 0;
        foreach ($skipRows as $sr) {
            $stepSlug = (string)$sr['step'];
            $md .= '| ' . $sr['module'] . ' | ' . $sr['company_id'] . ' | `' . $stepSlug . '` | '
                . mbqar_human_step_label($stepSlug) . ' | '
                . mbqar_shorten_note((string)$sr['notes'], 160) . " |\n";
            if (++$shown >= 500) {
                $md .= "\n_(truncated at 500 rows; see JSON for full list)_\n";
                break;
            }
        }
    }

    $jsonPath = mbqa_report_find_latest_json_path($root);
    if ($jsonPath === '') {
        $jsonPath = mbqa_report_dir($root) . DIRECTORY_SEPARATOR . 'module-browser-qa.json';
    }
    $outPath = mbqa_report_markdown_path($root);

    return [
        'md' => $md,
        'pass' => $pass,
        'fail' => $fail,
        'json_path' => $jsonPath,
        'out_path' => $outPath,
    ];
}

$root = realpath(__DIR__ . '/../..');
if ($root === false) {
    mbqar_err("Unable to resolve project root.\n");
    exit(2);
}

$options = mbqar_parse_options();

if ($options['help']) {
    mbqar_print_help();
    exit(0);
}

if (!mbqar_is_cli_sapi() && !$options['run']) {
    mbqar_render_browser_form($options);
    exit(0);
}

$date = $options['date'];
$resolved = mbqar_resolve_report_json_path($root, $options);
$jsonPath = $resolved['json_path'];
$usingLegacyJson = $resolved['using_legacy'];

if (!is_file($jsonPath)) {
    $cfg = mbqar_app_config();
    if (!mbqar_is_cli_sapi()) {
        header('Content-Type: text/html; charset=utf-8');
        itm_script_browser_nav_echo();
        echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
        echo '<h1>Report not available</h1>';
        echo '<p>JSON not found: <code>' . htmlspecialchars($jsonPath, ENT_QUOTES, 'UTF-8') . '</code></p>';
        if ($options['date_explicit']) {
            echo '<p>Requested date <code>' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8')
                . '</code> — copy or keep <code>module-browser-qa-' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8')
                . '.json</code>, or build without <code>date=</code> to use the latest timestamped JSON in <code>qa-reports/</code>.</p>';
        }
        echo '<p><a href="' . htmlspecialchars($cfg['runner_script'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($cfg['runner_label'], ENT_QUOTES, 'UTF-8') . '</a> first, then return here.</p>';
        echo '</main>';
        exit(1);
    }
    if ($options['date_explicit']) {
        mbqar_err("Missing {$jsonPath} (explicit --date={$date}; will not fall back to latest timestamped JSON)\n");
    } else {
        mbqar_err("Missing {$jsonPath}\n");
    }
    exit(1);
}

$jsonRaw = itm_read_utf8_text_file($jsonPath);
$data = json_decode($jsonRaw, true);
if (!is_array($data)) {
    if (!mbqar_is_cli_sapi()) {
        header('Content-Type: text/html; charset=utf-8');
        itm_script_browser_nav_echo();
        echo '<main style="margin:16px;font-family:sans-serif;"><h1>Invalid JSON</h1>';
        echo '<p>The runner output file could not be parsed. Re-run the QA runner.</p></main>';
        exit(1);
    }
    mbqar_err("Invalid JSON\n");
    exit(1);
}

$runnerRows = $data;
$reportPayload = $data;
if (isset($data['results']) && is_array($data['results'])) {
    $runnerRows = $data['results'];
    $reportPayload = $data;
} elseif (is_array($data) && isset($data[0])) {
    $runnerRows = $data;
    $reportPayload = ['results' => $data];
}

$reportTitleDate = $date;
if (!$usingLegacyJson && isset($reportPayload['generated_at']) && trim((string)$reportPayload['generated_at']) !== '') {
    $reportTitleDate = substr((string)$reportPayload['generated_at'], 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportTitleDate)) {
        $reportTitleDate = $date;
    }
}

$built = mbqar_build_markdown(
    $root,
    $reportTitleDate,
    $runnerRows,
    is_array($data['module_step_exceptions'] ?? null) ? $data['module_step_exceptions'] : []
);
$rerunHref = mbqar_rerun_runner_href($reportPayload);
$cfg = mbqar_app_config();
// BOM helps Windows Notepad/openers detect UTF-8; file content remains UTF-8 (see AGENTS.md).
itm_write_utf8_text_file($built['out_path'], $built['md'], true);

$generatedAt = trim((string)($reportPayload['generated_at'] ?? ''));
$reportFiles = mbqa_report_files_from_json_path($jsonPath);
$xlsxBuilt = mbqar_build_runner_xlsx(
    $root,
    $runnerRows,
    (int)$built['pass'],
    (int)$built['fail'],
    $generatedAt,
    $reportFiles['xlsx_path'] ?? null
);

if (mbqar_is_cli_sapi()) {
    mbqar_out("Wrote {$built['out_path']}\n");
    if ($xlsxBuilt['ok']) {
        mbqar_out("Wrote {$xlsxBuilt['path']}\n");
    } else {
        mbqar_err("XLSX: {$xlsxBuilt['error']}\n");
    }
    mbqar_out("Steps pass: {$built['pass']}, fail: {$built['fail']}\n");
    exit($built['fail'] > 0 ? 1 : 0);
}

header('Content-Type: text/html; charset=utf-8');
itm_script_browser_nav_echo();
$jsonBasename = basename($jsonPath);
echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:900px;margin:16px;">';
echo '<h1>Report built</h1>';
echo '<p><strong>' . (int)$built['pass'] . ' pass</strong>, <strong>' . (int)$built['fail'] . ' fail</strong> ';
echo '(from <code>' . htmlspecialchars($jsonBasename, ENT_QUOTES, 'UTF-8') . '</code>)</p>';
echo '<p style="font-size:0.9rem;color:#57606a;"><strong>Rebuild report</strong> regenerates markdown/XLSX from the existing JSON. ';
echo '<strong>Re-Run Test</strong> starts a new QA run (same module/company scope) and overwrites the JSON.</p>';
$mdRel = '../qa-reports/' . mbqa_report_markdown_basename();
$xlsxRel = ($xlsxBuilt['ok'] && $reportFiles !== null)
    ? ('../qa-reports/' . $reportFiles['xlsx_basename'])
    : '';
$actionLinks = [
    '<a href="module_clean_tests_qa_runner.php?run=1">Clean Tests</a>',
    '<a href="' . htmlspecialchars($mdRel, ENT_QUOTES, 'UTF-8') . '">Open markdown file</a>',
];
if ($xlsxRel !== '') {
    $actionLinks[] = '<a href="' . htmlspecialchars($xlsxRel, ENT_QUOTES, 'UTF-8') . '">Download XLSX</a>';
} else {
    $actionLinks[] = '<span style="color:#57606a;">Download XLSX (not found)</span>';
}
$actionLinks[] = '<a href="' . htmlspecialchars($cfg['self_script'], ENT_QUOTES, 'UTF-8') . '?run=1">Rebuild report</a>';
$actionLinks[] = '<a href="' . htmlspecialchars($rerunHref, ENT_QUOTES, 'UTF-8') . '">Re-Run Test</a>';
$actionLinks[] = '<a href="' . htmlspecialchars($cfg['runner_script'], ENT_QUOTES, 'UTF-8') . '">'
    . htmlspecialchars($cfg['runner_label'], ENT_QUOTES, 'UTF-8') . '</a>';
echo '<p>' . implode(' · ', $actionLinks) . '</p>';
echo '<h2>Preview</h2>';
echo '<pre style="background:#f6f8fa;border:1px solid #d0d7de;padding:12px;overflow:auto;max-height:70vh;font-size:12px;width:auto;">';
echo htmlspecialchars($built['md'], ENT_QUOTES, 'UTF-8');
echo '</pre></main>';
exit($built['fail'] > 0 ? 1 : 0);

itm_script_output_end();
