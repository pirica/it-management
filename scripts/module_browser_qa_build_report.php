<?php
/**
 * Build markdown QA report from JSON output of module_browser_qa_runner.php.
 *
 * CLI: php scripts/module_browser_qa_build_report.php [--date=YYYY-MM-DD]
 * Browser: scripts/module_browser_qa_build_report.php (form) or ?run=1&date=YYYY-MM-DD
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/script_browser_nav.php';

/**
 * @return array{run:bool, help:bool, date:string}
 */
function mbqar_parse_options(): array
{
    $options = [
        'run' => false,
        'help' => false,
        'date' => date('Y-m-d'),
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
            }
        }
    } else {
        $options['run'] = isset($_GET['run']) || isset($_POST['run']);
        $options['help'] = isset($_GET['help']);
        if (isset($_REQUEST['date']) && trim((string)$_REQUEST['date']) !== '') {
            $options['date'] = trim((string)$_REQUEST['date']);
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
    if (!mbqar_is_cli_sapi()) {
        header('Content-Type: text/html; charset=utf-8');
        itm_script_browser_nav_echo();
        echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
        echo '<h1>Module browser QA — build report</h1>';
    } else {
        itm_script_output_begin('Module browser QA — build report');
    }

    mbqar_out("Build markdown from qa-reports/module-browser-qa-YYYY-MM-DD.json\n\n");
    mbqar_out("Options:\n");
    mbqar_out("  --date=YYYY-MM-DD   Report date (default today)\n");
    mbqar_out("  --help              Show this help\n\n");
    mbqar_out("Output: qa-reports/module-browser-qa-YYYY-MM-DD.md\n\n");

    if (!mbqar_is_cli_sapi()) {
        mbqar_out("Browser: submit the form or use ?run=1&date=2026-05-20\n");
        echo '<p><a href="module_browser_qa_build_report.php">← Back to form</a></p></main>';
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
    $params = ['run' => '1'];
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

        return 'module_browser_qa_runner.php?' . http_build_query($params);
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
    $baseUrl = trim((string)($opts['base_url'] ?? ''));
    if ($baseUrl !== '' && $baseUrl !== 'http://localhost/it-management/') {
        $params['base_url'] = $baseUrl;
    }

    return 'module_browser_qa_runner.php?' . http_build_query($params);
}

function mbqar_render_browser_form(array $options): void
{
    header('Content-Type: text/html; charset=utf-8');
    itm_script_browser_nav_echo();

    $date = htmlspecialchars($options['date'], ENT_QUOTES, 'UTF-8');

    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
    echo '<h1>Module browser QA — build report</h1>';
    echo '<p>Reads <code>qa-reports/module-browser-qa-&lt;date&gt;.json</code> from the runner and writes a markdown summary.</p>';
    echo '<form method="get" action="module_browser_qa_build_report.php" style="display:grid;gap:12px;max-width:360px;">';
    echo '<input type="hidden" name="run" value="1">';
    echo '<label>Report date<br><input type="date" name="date" value="' . $date . '" style="width:100%;padding:8px;"></label>';
    echo '<button type="submit" style="padding:10px 16px;font-weight:600;">Build report</button>';
    echo '</form>';
    echo '<p style="margin-top:20px;font-size:0.9rem;"><a href="module_browser_qa_runner.php">Run QA runner</a> · ';
    echo '<a href="module_browser_qa_build_report.php?help=1">Help</a></p>';
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
    $passRows = [];
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
                $passRows[] = [
                    'module' => $row['module'],
                    'company_id' => $row['company_id'],
                    'step' => $step['step'],
                    'notes' => $step['notes'] ?? '',
                ];
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

    $md = "# Module browser QA — {$date}\n\n";
    $md .= "## Summary\n\n";
    $md .= "- Environment: `http://localhost/it-management/` (Laragon)\n";
    $md .= "- Auth: Admin / Admin\n";
    $md .= "- Companies: 5 (TechCorp Global … Enterprise IT)\n";
    $md .= "- Step outcomes: **{$pass} Pass**, **{$fail} Fail**\n";
    $md .= '- Modules in this report: ' . count($moduleRows) . "\n";
    $md .= "- Runner: `php scripts/module_browser_qa_runner.php` or browser form at `scripts/module_browser_qa_runner.php`\n";
    $md .= "- Bulk delete / Clear table: N/A when row count &lt; `records_per_page` (25)\n\n";

    if (!empty($moduleStepExceptions)) {
        $md .= "### Module step exceptions (reported Pass/N/A; runner still runs full Tier A)\n\n";
        foreach ($moduleStepExceptions as $moduleSlug => $stepNotes) {
            if (!is_array($stepNotes)) {
                continue;
            }
            $md .= '- **' . $moduleSlug . '**: ';
            $parts = [];
            foreach ($stepNotes as $stepName => $note) {
                $parts[] = '`' . $stepName . '` — ' . $note;
            }
            $md .= implode('; ', $parts) . "\n";
        }
        $md .= "\n";
    }

    $md .= "### Failure categories (automated run)\n\n";
    $md .= "| Step | Fail count | Typical cause |\n|---|---|---|\n";
    $causeMap = [
        'sort' => 'Default sort column may not be `id`',
        'clear' => 'FK constraints when parents cleared out of safe order',
        'sample_data' => 'Missing database.sql seed or FK parents',
        'error_log' => 'PHP warnings/notices/fatals logged during module HTTP steps',
        'create' => 'Missing FK parents after clear',
        'view' => 'No rows after failed seed',
        'edit' => 'No rows after failed seed',
        'import_db' => 'Import headers/values mismatch or unique constraints',
    ];
    foreach ($failCats as $k => $v) {
        $md .= '| ' . $k . ' | ' . $v . ' | ' . ($causeMap[$k] ?? '') . " |\n";
    }

    $md .= "\n## Preflight (company switch)\n\n";
    $md .= "| Company ID | Company | Switch |\n|---|---|---|\n";
    foreach ($preflight as $pf) {
        $st = $pf['steps'][0]['status'] ?? '?';
        $md .= '| ' . (int)$pf['company_id'] . ' | ' . ($pf['company_name'] ?? '') . ' | ' . $st . " |\n";
    }

    $md .= "\n## Results by module (Pass and Fail)\n\n";
    if (empty($moduleRows)) {
        $md .= "_No module rows in JSON._\n";
    } else {
        foreach ($moduleRows as $moduleRow) {
            $md .= '### ' . ($moduleRow['module'] ?? '') . ' — company ' . (int)($moduleRow['company_id'] ?? 0);
            $companyName = trim((string)($moduleRow['company_name'] ?? ''));
            if ($companyName !== '') {
                $md .= ' (' . $companyName . ')';
            }
            $md .= "\n\n| Step | Status | Notes |\n|---|---|---|\n";
            foreach ($moduleRow['steps'] as $step) {
                $note = str_replace('|', '/', (string)($step['notes'] ?? ''));
                if (strlen($note) > 160) {
                    $note = substr($note, 0, 157) . '...';
                }
                $md .= '| ' . ($step['step'] ?? '') . ' | ' . ($step['status'] ?? '') . ' | ' . $note . " |\n";
            }
            $md .= "\n";
        }
    }

    if (!empty($pilotRows)) {
        $md .= "\n## Expenses pilot (5 companies)\n\n";
        foreach ($pilotRows as $pr) {
            $md .= "### Company " . (int)$pr['company_id'] . ' — ' . ($pr['company_name'] ?? '') . "\n\n";
            $md .= "| Step | Status | Notes |\n|---|---|---|\n";
            foreach ($pr['steps'] as $step) {
                $md .= '| ' . ($step['step'] ?? '') . ' | ' . ($step['status'] ?? '') . ' | ' . str_replace('|', '/', (string)($step['notes'] ?? '')) . " |\n";
            }
            $md .= "\n";
        }
    }

    $md .= "## Failures only (quick index)\n\n";
    if (empty($failRows)) {
        $md .= "_No failures recorded._\n";
    } else {
        $md .= "| Module | Co | Step | Notes |\n|---|---|---|---|\n";
        $shown = 0;
        foreach ($failRows as $fr) {
            $md .= '| ' . $fr['module'] . ' | ' . $fr['company_id'] . ' | ' . $fr['step'] . ' | ' . str_replace('|', '/', $fr['notes']) . " |\n";
            if (++$shown >= 200) {
                $md .= "\n_(truncated; see JSON for full list)_\n";
                break;
            }
        }
    }

    $md .= "\n## Pass only (quick index)\n\n";
    if (empty($passRows)) {
        $md .= "_No passes recorded._\n";
    } else {
        $md .= "| Module | Co | Step | Notes |\n|---|---|---|---|\n";
        $shown = 0;
        foreach ($passRows as $pr) {
            $note = str_replace('|', '/', (string)$pr['notes']);
            if (strlen($note) > 160) {
                $note = substr($note, 0, 157) . '...';
            }
            $md .= '| ' . $pr['module'] . ' | ' . $pr['company_id'] . ' | ' . $pr['step'] . ' | ' . $note . " |\n";
            if (++$shown >= 500) {
                $md .= "\n_(truncated at 500 rows; see JSON for full list)_\n";
                break;
            }
        }
    }

    $jsonPath = $root . '/qa-reports/module-browser-qa-' . $date . '.json';
    $outPath = $root . '/qa-reports/module-browser-qa-' . $date . '.md';

    return [
        'md' => $md,
        'pass' => $pass,
        'fail' => $fail,
        'json_path' => $jsonPath,
        'out_path' => $outPath,
    ];
}

$root = realpath(__DIR__ . '/..');
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
$jsonPath = $root . '/qa-reports/module-browser-qa-' . $date . '.json';

if (!is_file($jsonPath)) {
    if (!mbqar_is_cli_sapi()) {
        header('Content-Type: text/html; charset=utf-8');
        itm_script_browser_nav_echo();
        echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
        echo '<h1>Report not available</h1>';
        echo '<p>JSON not found: <code>' . htmlspecialchars($jsonPath, ENT_QUOTES, 'UTF-8') . '</code></p>';
        echo '<p><a href="module_browser_qa_runner.php?run=1">Run the QA runner</a> first, then return here.</p>';
        echo '</main>';
        exit(1);
    }
    mbqar_err("Missing {$jsonPath}\n");
    exit(1);
}

$data = json_decode((string)file_get_contents($jsonPath), true);
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

$built = mbqar_build_markdown($root, $date, $runnerRows, is_array($data['module_step_exceptions'] ?? null) ? $data['module_step_exceptions'] : []);
$rerunHref = mbqar_rerun_runner_href($reportPayload);
file_put_contents($built['out_path'], $built['md']);

if (mbqar_is_cli_sapi()) {
    mbqar_out("Wrote {$built['out_path']}\n");
    mbqar_out("Steps pass: {$built['pass']}, fail: {$built['fail']}\n");
    exit($built['fail'] > 0 ? 1 : 0);
}

header('Content-Type: text/html; charset=utf-8');
itm_script_browser_nav_echo();
echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:900px;margin:16px;">';
echo '<h1>Report built</h1>';
echo '<p><strong>' . (int)$built['pass'] . ' pass</strong>, <strong>' . (int)$built['fail'] . ' fail</strong> ';
echo '(from <code>' . htmlspecialchars(basename($built['json_path']), ENT_QUOTES, 'UTF-8') . '</code>)</p>';
$mdRel = '../qa-reports/module-browser-qa-' . $date . '.md';
echo '<p><a href="' . htmlspecialchars($mdRel, ENT_QUOTES, 'UTF-8') . '">Open markdown file</a> · ';
echo '<a href="module_browser_qa_build_report.php">Build another date</a> · ';
echo '<a href="' . htmlspecialchars($rerunHref, ENT_QUOTES, 'UTF-8') . '">Re-Run Test</a> · ';
echo '<a href="module_browser_qa_runner.php">Run QA runner</a></p>';
echo '<h2>Preview</h2>';
echo '<pre style="background:#f6f8fa;border:1px solid #d0d7de;padding:12px;overflow:auto;max-height:70vh;font-size:12px;">';
echo htmlspecialchars($built['md'], ENT_QUOTES, 'UTF-8');
echo '</pre></main>';
exit($built['fail'] > 0 ? 1 : 0);
