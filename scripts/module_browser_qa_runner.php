<?php
/**
 * HTTP session runner for full-module QA (mirrors browser checklist).
 *
 * Why: Exercising 101 modules × 5 companies via IDE browser alone is not practical;
 * this tool uses the same login, company scope, CSRF, and module URLs as manual QA.
 * Tier A seeds FK parents, fills required NOT NULL columns, adds 30 random tenant rows (add step),
 * then bulk_delete/clear_table when row count >= records_per_page.
 * Each module starts by deleting error_log.txt; Tier A ends with HTTP sample_data (empty table) and error_log check.
 *
 * Usage (repository root, CLI):
 *   php scripts/module_browser_qa_runner.php
 *   php scripts/module_browser_qa_runner.php --module=expenses --company=1
 *   php scripts/module_browser_qa_runner.php --pilot-only
 *
 * Browser (Laragon): open scripts/module_browser_qa_runner.php, set options, Run QA.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/script_browser_nav.php';

/**
 * @return array{run:bool, help:bool, pilot_only:bool, base_url:string, module:?string, company:?int}
 */
function mbqa_parse_run_options(): array
{
    $options = [
        'run' => false,
        'help' => false,
        'pilot_only' => false,
        'base_url' => 'http://localhost/it-management/',
        'module' => null,
        'company' => null,
    ];

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        $options['run'] = true;
        foreach (array_slice($GLOBALS['argv'] ?? [], 1) as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $options['help'] = true;
                $options['run'] = false;
                continue;
            }
            if ($arg === '--pilot-only') {
                $options['pilot_only'] = true;
                continue;
            }
            if (strpos($arg, '--base-url=') === 0) {
                $options['base_url'] = (string)substr($arg, 11);
                continue;
            }
            if (strpos($arg, '--module=') === 0) {
                $options['module'] = substr($arg, 9);
                continue;
            }
            if (strpos($arg, '--company=') === 0) {
                $options['company'] = (int)substr($arg, 10);
            }
        }
    } else {
        $options['run'] = isset($_GET['run']) || isset($_POST['run']);
        $options['help'] = isset($_GET['help']);
        $options['pilot_only'] = isset($_GET['pilot_only']) || isset($_POST['pilot_only']);
        if (isset($_REQUEST['base_url'])) {
            $options['base_url'] = trim((string)$_REQUEST['base_url']);
        }
        if (isset($_REQUEST['module']) && trim((string)$_REQUEST['module']) !== '') {
            $options['module'] = trim((string)$_REQUEST['module']);
        }
        if (isset($_REQUEST['company']) && trim((string)$_REQUEST['company']) !== '') {
            $options['company'] = (int)$_REQUEST['company'];
        }
    }

    if (substr($options['base_url'], -1) !== '/') {
        $options['base_url'] .= '/';
    }

    return $options;
}

function mbqa_is_cli_sapi(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function mbqa_out(string $message): void
{
    echo $message;
    if (!mbqa_is_cli_sapi()) {
        @flush();
        @ob_flush();
    }
}

function mbqa_err(string $message): void
{
    if (mbqa_is_cli_sapi()) {
        fwrite(STDERR, $message);
    } else {
        mbqa_out($message);
    }
}

function mbqa_print_help(): void
{
    if (!mbqa_is_cli_sapi()) {
        header('Content-Type: text/html; charset=utf-8');
        itm_script_browser_nav_echo();
        echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
        echo '<h1>Module browser QA runner</h1>';
    } else {
        itm_script_output_begin('Module browser QA runner');
    }

    mbqa_out("Module browser QA runner\n\n");
    mbqa_out("Options:\n");
    mbqa_out("  --base-url=URL   App root (default http://localhost/it-management/)\n");
    mbqa_out("  --module=SLUG    Single module folder under modules/\n");
    mbqa_out("  --company=N      Company id 1–5 only\n");
    mbqa_out("  --pilot-only     Expenses module only (all companies)\n");
    mbqa_out("  --help           Show this help\n\n");
    mbqa_out("Output: qa-reports/module-browser-qa-YYYY-MM-DD.json\n\n");

    if (!mbqa_is_cli_sapi()) {
        mbqa_out("Browser: open this script, submit the form with Run QA, or use query flags:\n");
        mbqa_out("  ?run=1&module=expenses&company=4\n");
        echo '<p><a href="module_browser_qa_runner.php">← Back to runner form</a></p></main>';
    }
}

/**
 * @param array{run:bool, help:bool, pilot_only:bool, base_url:string, module:?string, company:?int} $options
 */
function mbqa_render_browser_form(array $options): void
{
    header('Content-Type: text/html; charset=utf-8');
    itm_script_browser_nav_echo();

    $baseUrl = htmlspecialchars($options['base_url'], ENT_QUOTES, 'UTF-8');
    $module = htmlspecialchars((string)($options['module'] ?? ''), ENT_QUOTES, 'UTF-8');
    $company = $options['company'] !== null ? (int)$options['company'] : '';
    $pilotChecked = $options['pilot_only'] ? ' checked' : '';

    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:720px;margin:16px;">';
    echo '<h1>Module browser QA runner</h1>';
    echo '<p>Runs the full-module HTTP checklist (login, company switch, clear/seed/CRUD, export/import, delete). ';
    echo 'Writes <code>qa-reports/module-browser-qa-YYYY-MM-DD.json</code>. Long runs may take several minutes.</p>';
    echo '<form method="get" action="module_browser_qa_runner.php" style="display:grid;gap:12px;max-width:520px;">';
    echo '<input type="hidden" name="run" value="1">';
    echo '<label>Base URL<br><input type="url" name="base_url" value="' . $baseUrl . '" style="width:100%;padding:8px;"></label>';
    echo '<label>Module (optional)<br><input type="text" name="module" value="' . $module . '" placeholder="expenses" style="width:100%;padding:8px;"></label>';
    echo '<label>Company id 1–5 (optional)<br><input type="number" name="company" min="1" max="5" value="' . htmlspecialchars((string)$company, ENT_QUOTES, 'UTF-8') . '" style="width:100%;padding:8px;"></label>';
    echo '<label><input type="checkbox" name="pilot_only" value="1"' . $pilotChecked . '> Pilot only (expenses)</label>';
    echo '<button type="submit" style="padding:10px 16px;font-weight:600;">Run QA</button>';
    echo '</form>';
    echo '<p style="margin-top:20px;font-size:0.9rem;"><a href="module_browser_qa_runner.php?help=1">CLI options / help</a> · ';
    echo '<a href="module_browser_qa_build_report.php">Build markdown report</a></p>';
    echo '</main>';
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    mbqa_err("Unable to resolve project root.\n");
    exit(2);
}

$mbqaOptions = mbqa_parse_run_options();

if ($mbqaOptions['help']) {
    mbqa_print_help();
    exit(0);
}

if (!mbqa_is_cli_sapi() && !$mbqaOptions['run']) {
    mbqa_render_browser_form($mbqaOptions);
    exit(0);
}

// Why: config.php starts the session and may set headers; browser HTML output must come after require.
define('ITM_CLI_SCRIPT', true);
require_once $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';

if (!mbqa_is_cli_sapi()) {
    @set_time_limit(0);
    @ignore_user_abort(true);
    itm_script_output_begin('Module browser QA runner');
    mbqa_out("Running QA…\n\n");
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    mbqa_err("Database connection unavailable.\n");
    exit(2);
}

$baseUrl = $mbqaOptions['base_url'];
$pilotOnly = $mbqaOptions['pilot_only'];
$filterModule = $mbqaOptions['module'];
$filterCompany = $mbqaOptions['company'];

$bespokeSmoke = [
    'budget_report', 'expiring', 'rack_planner', 'floor_plans', 'companies',
];

$skipClear = ['companies', 'users'];

/** Why: Some modules need lookup parents in database.sql before sample seed succeeds for a tenant. */
$sampleSeedPrerequisites = [
    'expenses' => ['budget_categories', 'cost_centers', 'gl_accounts'],
    'employee_positions' => ['departments'],
    'employee_onboarding_requests' => ['departments', 'employee_positions'],
    'inventory_items' => ['inventory_categories', 'suppliers'],
];

$companyNames = [
    1 => 'TechCorp Global',
    2 => 'DataCenter Plus',
    3 => 'Network Solutions',
    4 => 'CloudTech Services',
    5 => 'Enterprise IT',
];

$lookupWave = [
    'departments', 'manufacturers', 'vlans', 'location_types', 'equipment_types',
    'budget_categories', 'cost_centers', 'gl_accounts', 'supplier_statuses',
    'ticket_categories', 'ticket_statuses', 'ticket_priorities', 'employee_statuses',
    'employee_positions', 'approver_type', 'approvers', 'equipment_statuses',
    'rj45_speed', 'warranty_types', 'workstation_modes', 'workstation_os_types',
];

$budgetWave = [
    'annual_budgets', 'monthly_budgets', 'forecast_revisions_status',
    'forecast_revisions', 'approvals_stage', 'approvals', 'expenses',
];

$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';
$allModules = [];
foreach (scandir($modulesDir) ?: [] as $item) {
    if ($item === '.' || $item === '..') {
        continue;
    }
    $path = $modulesDir . DIRECTORY_SEPARATOR . $item;
    if (is_dir($path) && is_file($path . DIRECTORY_SEPARATOR . 'index.php')) {
        $allModules[] = $item;
    }
}
sort($allModules);

function mbqa_http(string $url, string $method = 'GET', ?string $body = null, array $headers = [], string $cookieFile = ''): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($cookieFile !== '') {
        $opts[CURLOPT_COOKIEJAR] = $cookieFile;
        $opts[CURLOPT_COOKIEFILE] = $cookieFile;
    }
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    if (!empty($headers)) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $curlError = $raw === false ? (curl_error($ch) ?: 'curl failed') : '';
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        return ['status' => 0, 'body' => '', 'headers' => '', 'error' => $errno ? ('curl errno ' . $errno . ': ' . $curlError) : $curlError];
    }
    $headerSize = strpos($raw, "\r\n\r\n");
    $respHeaders = $headerSize !== false ? substr($raw, 0, $headerSize) : '';
    $respBody = $headerSize !== false ? substr($raw, $headerSize + 4) : $raw;
    return ['status' => $status, 'body' => $respBody, 'headers' => $respHeaders, 'error' => $errno ? 'curl errno ' . $errno : ''];
}

function mbqa_extract_csrf(string $html): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return '';
}

function mbqa_has_fatal(string $html): bool
{
    return stripos($html, 'Fatal error') !== false
        || stripos($html, 'Parse error') !== false
        || stripos($html, 'Uncaught Error') !== false;
}

function mbqa_row_ids(string $html): array
{
    $ids = [];
    if (preg_match_all('/name="ids\[\]"\s+value="(\d+)"/', $html, $m)) {
        foreach ($m[1] as $id) {
            $ids[(int)$id] = (int)$id;
        }
    }
    if (preg_match_all('/name="id"\s+value="(\d+)"/', $html, $m2)) {
        foreach ($m2[1] as $id) {
            $ids[(int)$id] = (int)$id;
        }
    }
    return array_values($ids);
}

function mbqa_step_result(string $step, bool $ok, string $note = ''): array
{
    return [
        'step' => $step,
        'status' => $ok ? 'Pass' : 'Fail',
        'notes' => $note,
    ];
}

function mbqa_index_is_empty(string $html): bool
{
    return stripos($html, 'No records found') !== false;
}

function mbqa_index_has_sample_seed_error(string $html): bool
{
    return stripos($html, 'No sample rows found in database.sql') !== false;
}

/**
 * Mirrors table-tools.js: read list table headers/rows for Excel export → import round-trip.
 *
 * @return array<int, array<int, string>>
 */
function mbqa_extract_table_export_rows(string $html): array
{
    if (!class_exists('DOMDocument')) {
        return [];
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    if (@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html) === false) {
        libxml_clear_errors();
        return [];
    }
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $tables = $xpath->query('//div[contains(@class,"card")]//table[thead and tbody]');
    if ($tables === false || $tables->length === 0) {
        $tables = $xpath->query('//table[thead and tbody]');
    }
    if ($tables === false || $tables->length === 0) {
        return [];
    }

    /** @var DOMElement $table */
    $table = $tables->item(0);
    $skipHeaders = ['actions', 'select to delete', ''];

    $headers = [];
    $columnIndices = [];
    $headerNodes = $xpath->query('.//thead/tr[1]/th', $table);
    if ($headerNodes !== false) {
        foreach ($headerNodes as $colIndex => $th) {
            $label = mbqa_normalize_cell_text($th->textContent ?? '');
            $key = strtolower(trim($label));
            if (in_array($key, $skipHeaders, true)) {
                continue;
            }
            if ($key === '' && $xpath->query('.//input[@type="checkbox"]', $th)->length > 0) {
                continue;
            }
            $columnIndices[] = (int)$colIndex;
            $headers[] = $label;
        }
    }

    if (empty($headers)) {
        return [];
    }

    $rows = [$headers];
    $bodyRows = $xpath->query('.//tbody/tr', $table);
    if ($bodyRows === false) {
        return $rows;
    }

    foreach ($bodyRows as $tr) {
        $cells = $xpath->query('./td', $tr);
        if ($cells === false || $cells->length === 0) {
            continue;
        }
        $row = [];
        foreach ($columnIndices as $colIndex) {
            $td = $cells->item($colIndex);
            $row[] = mbqa_normalize_cell_text($td ? ($td->textContent ?? '') : '');
        }
        if (count($row) === count($headers) && implode('', $row) !== '') {
            $rows[] = $row;
        }
    }

    return count($rows) >= 2 ? $rows : [];
}

function mbqa_normalize_cell_text(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function mbqa_index_has_export_buttons(string $html): array
{
    // Why: table-tools.js injects Export PDF/Excel at runtime; CLI runner only sees the server HTML + shared script tag.
    $hasTableTools = stripos($html, 'table-tools.js') !== false && stripos($html, '<table') !== false;

    return [
        'pdf' => $hasTableTools
            || stripos($html, 'Export PDF') !== false
            || stripos($html, '📄 Export PDF') !== false,
        'excel' => $hasTableTools
            || stripos($html, 'Export Excel') !== false
            || stripos($html, '📗 Export Excel') !== false,
    ];
}

/**
 * HTTP sample seed, then database.sql seed (and FK parents) when the UI reports missing SQL samples.
 *
 * @return array{ok:bool, note:string, html:string, csrf:string, na:bool}
 */
function mbqa_ensure_sample_data(
    mysqli $conn,
    string $slug,
    int $companyId,
    string $moduleUrl,
    string $cookieFile
): array {
    global $sampleSeedPrerequisites;

    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    $csrf = mbqa_extract_csrf($index['body']);
    $hasSampleBtn = stripos($index['body'], 'name="add_sample_data"') !== false
        || stripos($index['body'], 'Add sample data') !== false;

    if (!$hasSampleBtn) {
        return ['ok' => true, 'note' => 'N/A (no handler)', 'html' => $index['body'], 'csrf' => $csrf, 'na' => true];
    }

    if (!mbqa_index_is_empty($index['body'])) {
        return ['ok' => true, 'note' => 'N/A (rows exist)', 'html' => $index['body'], 'csrf' => $csrf, 'na' => true];
    }

    if ($csrf !== '') {
        mbqa_http(
            $moduleUrl . 'index.php',
            'POST',
            http_build_query(['add_sample_data' => '1', 'csrf_token' => $csrf]),
            ['Content-Type: application/x-www-form-urlencoded'],
            $cookieFile
        );
        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $csrf = mbqa_extract_csrf($index['body']);
        if (!mbqa_index_is_empty($index['body']) && !mbqa_index_has_sample_seed_error($index['body'])) {
            return ['ok' => true, 'note' => 'HTTP sample seed', 'html' => $index['body'], 'csrf' => $csrf, 'na' => false];
        }
    }

    if (!function_exists('itm_seed_table_from_database_sql')) {
        return ['ok' => false, 'note' => 'Still empty; itm_seed_table_from_database_sql missing', 'html' => $index['body'], 'csrf' => $csrf, 'na' => false];
    }

    $_SESSION['company_id'] = $companyId;

    $seedParents = static function (mysqli $conn, string $table, int $companyId) use ($sampleSeedPrerequisites): void {
        $tables = $sampleSeedPrerequisites[$table] ?? [];
        if (function_exists('itm_table_outbound_fk_map')) {
            foreach (itm_table_outbound_fk_map($conn, $table) as $fkMeta) {
                $parentTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
                if ($parentTable !== '' && itm_is_safe_identifier($parentTable)) {
                    $tables[] = $parentTable;
                }
            }
        }
        $tables = array_values(array_unique($tables));
        foreach ($tables as $parentTable) {
            if (!itm_is_safe_identifier($parentTable) || in_array($parentTable, mbqa_tables_never_clear(), true)) {
                continue;
            }
            $parentErr = '';
            itm_seed_table_from_database_sql($conn, $parentTable, $companyId, $parentErr);
        }
    };

    $seedParents($conn, $slug, $companyId);

    $seedErr = '';
    $inserted = itm_seed_table_from_database_sql($conn, $slug, $companyId, $seedErr);
    if ($inserted <= 0) {
        $seedErr = '';
        $inserted = itm_seed_table_from_database_sql($conn, $slug, $companyId, $seedErr);
    }

    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    $csrf = mbqa_extract_csrf($index['body']);
    $ok = !mbqa_index_is_empty($index['body']);

    if ($ok) {
        $note = $inserted > 0
            ? ('DB sample seed (' . $inserted . ' row(s) from database.sql)')
            : 'DB sample seed (rows present after FK parent seed)';
        return ['ok' => true, 'note' => $note, 'html' => $index['body'], 'csrf' => $csrf, 'na' => false];
    }

    $note = $seedErr !== '' ? $seedErr : 'Still empty or seed error';
    if (mbqa_index_has_sample_seed_error($index['body'])) {
        $note = 'No sample rows in database.sql (HTTP + DB seed)';
    }

    return ['ok' => false, 'note' => $note, 'html' => $index['body'], 'csrf' => $csrf, 'na' => false];
}

function mbqa_error_log_path(): string
{
    return (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__) . DIRECTORY_SEPARATOR) . 'error_log.txt';
}

function mbqa_error_log_byte_offset(): int
{
    $path = mbqa_error_log_path();

    return is_file($path) ? (int)filesize($path) : 0;
}

/**
 * HTTP-only sample seed at end of module QA (after clear_table); does not use database.sql fallback.
 *
 * @return array{ok:bool,note:string,na:bool}
 */
function mbqa_http_sample_seed_end(string $moduleUrl, string $cookieFile): array
{
    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    $csrf = mbqa_extract_csrf($index['body']);
    $hasSampleBtn = stripos($index['body'], 'name="add_sample_data"') !== false
        || stripos($index['body'], 'Add sample data') !== false;

    if (!$hasSampleBtn) {
        return ['ok' => true, 'note' => 'N/A (no handler)', 'na' => true];
    }

    if (!mbqa_index_is_empty($index['body'])) {
        return ['ok' => true, 'note' => 'N/A (rows exist)', 'na' => true];
    }

    if ($csrf === '') {
        return ['ok' => false, 'note' => 'No CSRF for sample seed', 'na' => false];
    }

    mbqa_http(
        $moduleUrl . 'index.php',
        'POST',
        http_build_query(['add_sample_data' => '1', 'csrf_token' => $csrf]),
        ['Content-Type: application/x-www-form-urlencoded'],
        $cookieFile
    );
    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    $ok = !mbqa_index_is_empty($index['body']) && !mbqa_index_has_sample_seed_error($index['body']);

    return [
        'ok' => $ok,
        'note' => $ok ? 'HTTP sample seed' : 'HTTP sample seed failed or empty',
        'na' => false,
    ];
}

/**
 * @return array{ok:bool,note:string,count:int}
 */
function mbqa_read_error_log_since(int $byteOffset): array
{
    $path = mbqa_error_log_path();
    if (!is_file($path)) {
        return ['ok' => true, 'note' => '0 errors', 'count' => 0];
    }

    $size = (int)filesize($path);
    if ($byteOffset >= $size) {
        return ['ok' => true, 'note' => '0 errors', 'count' => 0];
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return ['ok' => false, 'note' => 'Unable to read error_log.txt', 'count' => 0];
    }

    fseek($handle, $byteOffset);
    $chunk = (string)stream_get_contents($handle);
    fclose($handle);

    $lines = [];
    foreach (preg_split('/\r\n|\r|\n/', $chunk) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    $count = count($lines);
    if ($count === 0) {
        return ['ok' => true, 'note' => '0 errors', 'count' => 0];
    }

    $note = $count . ' error(s)';
    if ($count <= 2) {
        $note .= ': ' . implode(' | ', array_map(static function ($l) {
            return substr($l, 0, 160);
        }, $lines));
    } else {
        $note .= ' (first: ' . substr($lines[0], 0, 160) . '…)';
    }

    return ['ok' => false, 'note' => $note, 'count' => $count];
}

function mbqa_delete_error_log_file(): void
{
    $path = mbqa_error_log_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Build import rows from exported table data (adds one QA row for insert verification).
 *
 * @param array<int, array<int, string>> $exportRows
 * @return array<int, array<int, string>>
 */
function mbqa_build_import_rows_from_export(array $exportRows): array
{
    if (count($exportRows) < 2) {
        return $exportRows;
    }

    $template = $exportRows[1];
    $newRow = $template;
    $suffix = date('YmdHis');
    foreach ($exportRows[0] as $i => $header) {
        $headerKey = strtolower(trim(preg_replace('/\s+/', ' ', (string)$header)));
        if ($headerKey === 'id') {
            $newRow[$i] = '';
            continue;
        }
        if (strpos($headerKey, 'invoice') !== false) {
            $newRow[$i] = 'INV-QA-IMPORT-' . $suffix;
        } elseif (
            $headerKey === 'description'
            || $headerKey === 'name'
            || strpos($headerKey, 'code') !== false
            || strpos($headerKey, 'title') !== false
        ) {
            $base = trim((string)($template[$i] ?? ''));
            $newRow[$i] = ($base !== '' ? $base . ' ' : '') . 'QA-IMPORT-' . $suffix;
        }
    }

    // Why: Import only the derived row so FK labels/values from Export Excel match one insert attempt.
    return [$exportRows[0], $newRow];
}

function mbqa_humanize_field_label(string $field): string
{
    $label = preg_replace('/_id$/', '', trim($field));
    if ($label === 'id') {
        return 'ID';
    }

    return ucwords(str_replace('_', ' ', $label));
}

/**
 * @return array<string, true> column name => true
 */
function mbqa_table_column_names(mysqli $conn, string $table): array
{
    if (!itm_is_safe_identifier($table)) {
        return [];
    }

    $cols = [];
    $tableEsc = '`' . str_replace('`', '``', $table) . '`';
    $res = mysqli_query($conn, 'SHOW COLUMNS FROM ' . $tableEsc);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $field = (string)($row['Field'] ?? '');
        if ($field !== '' && itm_is_safe_identifier($field)) {
            $cols[$field] = true;
        }
    }

    return $cols;
}

/**
 * Maps list-table header text (module-specific labels) back to a DB column for import payloads.
 */
function mbqa_match_list_header_to_column(string $header, array $columnNames): ?string
{
    $normHeader = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
    if ($normHeader === '' || $normHeader === 'id') {
        return null;
    }

    foreach (array_keys($columnNames) as $field) {
        if (in_array($field, ['id', 'company_id', 'created_at', 'updated_at'], true)) {
            continue;
        }
        if (strtolower(mbqa_humanize_field_label($field)) === $normHeader) {
            return $field;
        }
    }

    foreach (array_keys($columnNames) as $field) {
        if (!preg_match('/_id$/', (string)$field)) {
            continue;
        }
        $stem = strtolower(preg_replace('/_id$/', '', $field));
        $stemSpaced = str_replace('_', ' ', $stem);
        if ($stem !== '' && (strpos($normHeader, $stem) !== false || strpos($normHeader, $stemSpaced) !== false)) {
            return $field;
        }
    }

    return null;
}

/**
 * Raw insertable values keyed by column name from database.sql (tenant-resolved FK ids).
 *
 * @return array<string, string>
 */
function mbqa_database_sql_values_by_column(mysqli $conn, string $table, int $companyId): array
{
    if (!function_exists('itm_parse_database_sql_inserts') || !itm_is_safe_identifier($table) || $companyId <= 0) {
        return [];
    }

    $sqlPath = ROOT_PATH . 'database.sql';
    if (!is_file($sqlPath)) {
        return [];
    }

    $sqlBody = @file_get_contents($sqlPath);
    if ($sqlBody === false) {
        return [];
    }

    $parsed = itm_parse_database_sql_inserts($sqlBody, $table);
    $tableRows = $parsed[$table] ?? [];
    if (empty($tableRows)) {
        return [];
    }

    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $suffix = date('YmdHis');
    $chosen = null;

    foreach ($tableRows as $rowEntry) {
        $rawColumns = $rowEntry['columns'] ?? [];
        $rawValues = $rowEntry['values'] ?? [];
        $rowCompanyId = null;
        foreach ($rawColumns as $index => $columnToken) {
            $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
            if ($columnName !== 'company_id') {
                continue;
            }
            $rawCompanyToken = trim((string)($rawValues[$index] ?? ''), "'\"");
            $rowCompanyId = (int)$rawCompanyToken;
            break;
        }
        if ($rowCompanyId !== null && $rowCompanyId !== $companyId) {
            continue;
        }
        $chosen = $rowEntry;
        break;
    }

    if ($chosen === null) {
        $chosen = $tableRows[0];
    }

    $byColumn = [];
    foreach ($chosen['columns'] as $index => $columnToken) {
        $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
        if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
            continue;
        }
        if (in_array($columnName, ['id', 'company_id', 'created_at', 'updated_at'], true)) {
            continue;
        }

        $valueToken = trim((string)($chosen['values'][$index] ?? ''), "'\"");
        if (isset($fkMap[$columnName]) && function_exists('itm_fk_resolve_company_equivalent_id')) {
            $storedFkId = (int)$valueToken;
            if ($storedFkId > 0) {
                $resolved = itm_fk_resolve_company_equivalent_id($conn, $fkMap[$columnName], $companyId, $storedFkId);
                if ($resolved > 0) {
                    $valueToken = (string)$resolved;
                }
            }
        }

        $key = strtolower(mbqa_humanize_field_label($columnName));
        if (strpos($key, 'invoice') !== false) {
            $valueToken = 'INV-QA-IMPORT-' . $suffix;
        } elseif ($key === 'description' || $key === 'name' || strpos($key, 'code') !== false) {
            $valueToken = ($valueToken !== '' ? $valueToken . ' ' : '') . 'QA-IMPORT-' . $suffix;
        }

        $byColumn[$columnName] = $valueToken;
    }

    if ($table === 'expenses' && isset($byColumn['cost_center_id'])) {
        $pickSql = 'SELECT cc.id AS cc_id, gl.id AS gl_id
            FROM cost_centers cc
            INNER JOIN gl_accounts gl ON gl.company_id = cc.company_id
            LEFT JOIN expenses e ON e.company_id = cc.company_id AND e.cost_center_id = cc.id
            WHERE cc.company_id = ' . (int)$companyId . ' AND e.id IS NULL
            ORDER BY cc.id ASC
            LIMIT 1';
        $pickRes = mysqli_query($conn, $pickSql);
        $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
        if ($pick) {
            $byColumn['cost_center_id'] = (string)(int)($pick['cc_id'] ?? 0);
            if (isset($byColumn['gl_account_id'])) {
                $byColumn['gl_account_id'] = (string)(int)($pick['gl_id'] ?? 0);
            }
        }
    }

    return $byColumn;
}

/**
 * Fallback import payload with raw DB values (FK ids) when label-based Excel import inserts 0 rows.
 *
 * @return array<int, array<int, string>>
 */
function mbqa_build_import_rows_from_db_template(mysqli $conn, string $table, int $companyId): array
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0 || !itm_table_has_column($conn, $table, 'company_id')) {
        return [];
    }

    $tableEsc = '`' . str_replace('`', '``', $table) . '`';
    $res = mysqli_query($conn, 'SELECT * FROM ' . $tableEsc . ' WHERE company_id=' . (int)$companyId . ' ORDER BY id ASC LIMIT 1');
    if (!$res || !($row = mysqli_fetch_assoc($res))) {
        return [];
    }

    $headers = [];
    $values = [];
    foreach ($row as $field => $value) {
        if ($field === 'id' || $field === 'company_id' || $field === 'created_at' || $field === 'updated_at') {
            continue;
        }
        $headers[] = mbqa_humanize_field_label((string)$field);
        $values[] = (string)$value;
    }

    if (empty($headers)) {
        return [];
    }

    $suffix = date('YmdHis');
    $importValues = $values;
    foreach ($headers as $i => $label) {
        $key = strtolower($label);
        if (strpos($key, 'invoice') !== false) {
            $importValues[$i] = 'INV-QA-IMPORT-' . $suffix;
        } elseif ($key === 'description' || $key === 'name' || strpos($key, 'code') !== false) {
            $importValues[$i] = trim($importValues[$i] . ' QA-IMPORT-' . $suffix);
        }
    }

    return [$headers, $importValues];
}

/**
 * Import payload from database.sql INSERT samples (raw FK ids), when UI export labels fail to insert.
 *
 * @return array<int, array<int, string>>
 */
function mbqa_build_import_rows_from_database_sql_seed(mysqli $conn, string $table, int $companyId): array
{
    if (!function_exists('itm_parse_database_sql_inserts') || !itm_is_safe_identifier($table) || $companyId <= 0) {
        return [];
    }

    $sqlPath = ROOT_PATH . 'database.sql';
    if (!is_file($sqlPath)) {
        return [];
    }

    $sqlBody = @file_get_contents($sqlPath);
    if ($sqlBody === false) {
        return [];
    }

    $parsed = itm_parse_database_sql_inserts($sqlBody, $table);
    $tableRows = $parsed[$table] ?? [];
    if (empty($tableRows)) {
        return [];
    }

    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $suffix = date('YmdHis');
    $chosen = null;

    foreach ($tableRows as $rowEntry) {
        $rawColumns = $rowEntry['columns'] ?? [];
        $rawValues = $rowEntry['values'] ?? [];
        $rowCompanyId = null;
        foreach ($rawColumns as $index => $columnToken) {
            $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
            if ($columnName !== 'company_id') {
                continue;
            }
            $rawCompanyToken = trim((string)($rawValues[$index] ?? ''), "'\"");
            $rowCompanyId = (int)$rawCompanyToken;
            break;
        }
        if ($rowCompanyId !== null && $rowCompanyId !== $companyId) {
            continue;
        }
        $chosen = $rowEntry;
        break;
    }

    if ($chosen === null) {
        $chosen = $tableRows[0];
    }

    $headers = [];
    $values = [];
    foreach ($chosen['columns'] as $index => $columnToken) {
        $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
        if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
            continue;
        }
        if (in_array($columnName, ['id', 'company_id', 'created_at', 'updated_at'], true)) {
            continue;
        }

        $valueToken = trim((string)($chosen['values'][$index] ?? ''), "'\"");
        if (isset($fkMap[$columnName]) && function_exists('itm_fk_resolve_company_equivalent_id')) {
            $storedFkId = (int)$valueToken;
            if ($storedFkId > 0) {
                $resolved = itm_fk_resolve_company_equivalent_id($conn, $fkMap[$columnName], $companyId, $storedFkId);
                if ($resolved > 0) {
                    $valueToken = (string)$resolved;
                }
            }
        }

        $key = strtolower(mbqa_humanize_field_label($columnName));
        if (strpos($key, 'invoice') !== false) {
            $valueToken = 'INV-QA-IMPORT-' . $suffix;
        } elseif ($key === 'description' || $key === 'name' || strpos($key, 'code') !== false) {
            $valueToken = ($valueToken !== '' ? $valueToken . ' ' : '') . 'QA-IMPORT-' . $suffix;
        }

        $headers[] = mbqa_humanize_field_label($columnName);
        $values[] = $valueToken;
    }

    if (empty($headers)) {
        return [];
    }

    $rows = [$headers, $values];
    if ($table === 'expenses') {
        $rows = mbqa_unique_expense_import_row($conn, $companyId, $rows);
    }

    return $rows;
}

/**
 * expenses.uq_expenses_company_scope allows one row per company + cost_center; pick a free cost center for import QA.
 *
 * @param array<int, array<int, string>> $sqlRows
 * @return array<int, array<int, string>>
 */
function mbqa_unique_expense_import_row(mysqli $conn, int $companyId, array $sqlRows): array
{
    if ($companyId <= 0 || count($sqlRows) < 2) {
        return $sqlRows;
    }

    $headers = $sqlRows[0];
    $values = $sqlRows[1];
    $ccIndex = array_search('Cost Center', $headers, true);
    $glIndex = array_search('Gl Account', $headers, true);
    if ($ccIndex === false) {
        return $sqlRows;
    }

    $pickSql = 'SELECT cc.id AS cc_id, gl.id AS gl_id
        FROM cost_centers cc
        INNER JOIN gl_accounts gl ON gl.company_id = cc.company_id
        LEFT JOIN expenses e ON e.company_id = cc.company_id AND e.cost_center_id = cc.id
        WHERE cc.company_id = ' . (int)$companyId . ' AND e.id IS NULL
        ORDER BY cc.id ASC
        LIMIT 1';
    $pickRes = mysqli_query($conn, $pickSql);
    $pick = $pickRes ? mysqli_fetch_assoc($pickRes) : null;
    if ($pick) {
        $values[$ccIndex] = (string)(int)($pick['cc_id'] ?? 0);
        if ($glIndex !== false) {
            $values[$glIndex] = (string)(int)($pick['gl_id'] ?? 0);
        }
    }

    return [$headers, $values];
}

/**
 * Import rows for round-trip: Export Excel headers from the list table + insertable values from database.sql.
 *
 * @param array<int, array<int, string>> $exportRows
 * @return array<int, array<int, string>>
 */
function mbqa_import_rows_for_round_trip(mysqli $conn, string $table, int $companyId, array $exportRows): array
{
    if (count($exportRows) >= 2) {
        $importRows = mbqa_build_import_rows_from_export($exportRows);
        $byColumn = mbqa_database_sql_values_by_column($conn, $table, $companyId);
        if (!empty($byColumn)) {
            $columnNames = mbqa_table_column_names($conn, $table);
            $headers = $importRows[0];
            $values = $importRows[1];
            foreach ($headers as $i => $header) {
                $col = mbqa_match_list_header_to_column((string)$header, $columnNames);
                if ($col !== null && isset($byColumn[$col])) {
                    $values[$i] = $byColumn[$col];
                }
            }
            $importRows[1] = $values;
        }

        return $importRows;
    }

    $sqlRows = mbqa_build_import_rows_from_database_sql_seed($conn, $table, $companyId);
    if (!empty($sqlRows)) {
        return $sqlRows;
    }

    return [];
}

/** Tables the runner must never wipe during FK prep or delete-retry clears (shared auth only). */
function mbqa_tables_never_clear(): array
{
    return ['companies', 'users'];
}

/** Ideal row count so bulk_delete / clear_table UI gates (default records_per_page 25) are exercisable when schema allows. */
function mbqa_bulk_row_target_ideal(mysqli $conn): int
{
    return max(30, mbqa_records_per_page($conn) + 1);
}

/**
 * Max tenant rows allowed by unique indexes (e.g. expenses: one row per company_id + cost_center_id).
 */
function mbqa_unique_scope_capacity(mysqli $conn, string $table, int $companyId): int
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return PHP_INT_MAX;
    }

    $uniqueSets = mbqa_table_unique_column_sets($conn, $table);
    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $limiting = [];

    foreach ($uniqueSets as $set) {
        $nonId = array_values(array_filter($set, static function ($col) {
            return $col !== 'id';
        }));
        if (empty($nonId)) {
            continue;
        }

        $setCapacity = PHP_INT_MAX;
        foreach ($nonId as $col) {
            if ($col === 'company_id') {
                continue;
            }

            $refTable = mbqa_fk_reference_table($col, $fkMap);
            if ($refTable !== '' && itm_is_safe_identifier($refTable)) {
                if (itm_table_has_column($conn, $refTable, 'company_id')) {
                    $colCapacity = mbqa_tenant_row_count($conn, $refTable, $companyId);
                } else {
                    $colCapacity = count(mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId));
                }
                $setCapacity = min($setCapacity, max(0, $colCapacity));
            }
        }

        if ($setCapacity < PHP_INT_MAX) {
            $limiting[] = max(1, $setCapacity);
        }
    }

    if (empty($limiting)) {
        return PHP_INT_MAX;
    }

    return max(1, min($limiting));
}

/**
 * Row target for add step: pursue bulk-action coverage but never exceed per-tenant unique scope.
 */
function mbqa_bulk_row_target_for_table(mysqli $conn, string $table, int $companyId): int
{
    $ideal = mbqa_bulk_row_target_ideal($conn);
    $capacity = mbqa_unique_scope_capacity($conn, $table, $companyId);

    if ($capacity === PHP_INT_MAX) {
        return $ideal;
    }

    return max(1, min($ideal, $capacity));
}

/**
 * Parent tables referenced in unique indexes (e.g. cost_centers for expenses) — grow these so capacity can reach the ideal row target.
 *
 * @return string[]
 */
function mbqa_unique_scope_limiting_parent_tables(mysqli $conn, string $table, int $companyId): array
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return [];
    }

    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $parents = [];

    foreach (mbqa_table_unique_column_sets($conn, $table) as $set) {
        foreach ($set as $col) {
            if ($col === 'id' || $col === 'company_id') {
                continue;
            }
            $refTable = mbqa_fk_reference_table($col, $fkMap);
            if ($refTable !== '' && itm_is_safe_identifier($refTable) && itm_table_has_column($conn, $refTable, 'company_id')) {
                $parents[$refTable] = $refTable;
            }
        }
    }

    return array_values($parents);
}

function mbqa_grow_unique_scope_parents(mysqli $conn, string $table, int $companyId, int $goalCount): void
{
    $goalCount = max(1, $goalCount);
    foreach (mbqa_unique_scope_limiting_parent_tables($conn, $table, $companyId) as $parentTable) {
        if (in_array($parentTable, mbqa_tables_never_clear(), true)) {
            continue;
        }
        $parentCount = mbqa_tenant_row_count($conn, $parentTable, $companyId);
        if ($parentCount < $goalCount) {
            mbqa_insert_random_rows($conn, $parentTable, $companyId, $goalCount - $parentCount, 0);
        }
    }
}

function mbqa_records_per_page(mysqli $conn): int
{
    if (!function_exists('itm_get_ui_configuration') || !function_exists('itm_resolve_records_per_page')) {
        return 25;
    }

    $companyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
    $uiConfig = itm_get_ui_configuration($conn, $companyId > 0 ? $companyId : null);

    return itm_resolve_records_per_page($uiConfig);
}

function mbqa_tenant_row_count(mysqli $conn, string $table, int $companyId): int
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0 || !itm_table_has_column($conn, $table, 'company_id')) {
        return 0;
    }

    $tableEsc = '`' . str_replace('`', '``', $table) . '`';
    $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM ' . $tableEsc . ' WHERE company_id=' . (int)$companyId);
    if (!$res || !($row = mysqli_fetch_assoc($res))) {
        return 0;
    }

    return (int)($row['c'] ?? 0);
}

/**
 * @return array<int, array{name:string,type:string,null:string,default:?string,extra:string,key:string}>
 */
function mbqa_table_column_metas(mysqli $conn, string $table): array
{
    if (!itm_is_safe_identifier($table)) {
        return [];
    }

    $metas = [];
    $res = mysqli_query($conn, 'DESCRIBE `' . str_replace('`', '``', $table) . '`');
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $name = (string)($row['Field'] ?? '');
        if ($name === '' || !itm_is_safe_identifier($name)) {
            continue;
        }
        $metas[] = [
            'name' => $name,
            'type' => strtolower((string)($row['Type'] ?? '')),
            'null' => strtoupper((string)($row['Null'] ?? 'YES')),
            'default' => $row['Default'] ?? null,
            'extra' => strtolower((string)($row['Extra'] ?? '')),
            'key' => strtoupper((string)($row['Key'] ?? '')),
        ];
    }

    return $metas;
}

/**
 * @return array<int, array<int, string>>
 */
function mbqa_table_unique_column_sets(mysqli $conn, string $table): array
{
    if (!itm_is_safe_identifier($table)) {
        return [];
    }

    $sql = 'SELECT INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME <> ?
            ORDER BY INDEX_NAME, SEQ_IN_INDEX';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    $primary = 'PRIMARY';
    mysqli_stmt_bind_param($stmt, 'ss', $table, $primary);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $byIndex = [];
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $indexName = (string)($row['INDEX_NAME'] ?? '');
        $columnName = (string)($row['COLUMN_NAME'] ?? '');
        if ($indexName === '' || $columnName === '' || !itm_is_safe_identifier($columnName)) {
            continue;
        }
        $byIndex[$indexName][] = $columnName;
    }
    mysqli_stmt_close($stmt);

    return array_values($byIndex);
}

function mbqa_column_in_unique_set(string $column, array $uniqueSets): bool
{
    foreach ($uniqueSets as $set) {
        if (in_array($column, $set, true)) {
            return true;
        }
    }

    return false;
}

function mbqa_column_skipped_for_insert(string $name, array $meta): bool
{
    if ($name === 'id' || strpos((string)$meta['extra'], 'auto_increment') !== false) {
        return true;
    }

    return $name === 'created_at' || $name === 'updated_at';
}

function mbqa_column_has_db_default(array $meta): bool
{
    $default = $meta['default'];
    if ($default === null) {
        return false;
    }

    $defaultStr = strtoupper(trim((string)$default));
    if ($defaultStr === '' || $defaultStr === 'NULL') {
        return false;
    }

    return true;
}

/** NOT NULL columns without a DB default must receive an explicit insert value. */
function mbqa_column_is_required(array $meta): bool
{
    if (($meta['null'] ?? 'YES') === 'YES') {
        return false;
    }

    return !mbqa_column_has_db_default($meta);
}

function mbqa_fk_reference_table(string $column, array $fkMap): string
{
    if (isset($fkMap[$column])) {
        return (string)($fkMap[$column]['REFERENCED_TABLE_NAME'] ?? '');
    }

    if (preg_match('/_by$/', $column)) {
        return 'users';
    }

    if (preg_match('/_id$/', $column) && $column !== 'company_id' && preg_match('/^(.+)_id$/', $column, $idMatch)) {
        return (string)($idMatch[1] ?? '');
    }

    return '';
}

/**
 * Seeds database.sql rows and ensures each outbound FK parent has enough tenant rows.
 */
function mbqa_ensure_parent_rows_for_inserts(mysqli $conn, string $table, int $companyId, int $minimumRows): void
{
    mbqa_seed_lookup_parents_for_table($conn, $table, $companyId);

    if (!function_exists('itm_table_outbound_fk_map') || !itm_is_safe_identifier($table) || $companyId <= 0) {
        return;
    }

    $minimumRows = max(1, $minimumRows);
    foreach (itm_table_outbound_fk_map($conn, $table) as $fkMeta) {
        $parentTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
        if ($parentTable === '' || !itm_is_safe_identifier($parentTable) || in_array($parentTable, mbqa_tables_never_clear(), true)) {
            continue;
        }

        if (itm_table_has_column($conn, $parentTable, 'company_id')) {
            $parentCount = mbqa_tenant_row_count($conn, $parentTable, $companyId);
            if ($parentCount < $minimumRows) {
                mbqa_insert_random_rows($conn, $parentTable, $companyId, $minimumRows - $parentCount, 1);
            }
            continue;
        }

        if ($parentTable === 'users' && mbqa_query_first_id($conn, 'users', $companyId) <= 0 && function_exists('itm_seed_table_from_database_sql')) {
            $seedErr = '';
            itm_seed_table_from_database_sql($conn, 'users', $companyId, $seedErr);
        }
    }
}

/**
 * @param array<int, array{name:string,type:string,null:string,default:?string,extra:string,key:string}> $columnMetas
 * @return string[]
 */
function mbqa_required_column_names(array $columnMetas): array
{
    $required = [];
    foreach ($columnMetas as $meta) {
        $name = (string)($meta['name'] ?? '');
        if ($name === '' || mbqa_column_skipped_for_insert($name, $meta)) {
            continue;
        }
        if (mbqa_column_is_required($meta)) {
            $required[] = $name;
        }
    }

    return $required;
}

/**
 * Fills a required or optional scalar (non-FK) column with QA-safe random data.
 */
function mbqa_fill_scalar_value(
    string $name,
    string $type,
    int $sequence,
    string $tag,
    bool $inUnique,
    bool $forceRequired = false
): ?string {
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit)/', $type) || $name === 'active') {
        return (string)($inUnique ? ($sequence + 1) : 1);
    }

    if (strpos($type, 'enum(') === 0) {
        if (preg_match("/enum\\((.+)\\)/", $type, $enumMatch)) {
            $opts = str_getcsv(str_replace("'", '', $enumMatch[1]));

            return (string)($opts[0] ?? '1');
        }

        return '1';
    }

    if (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
        return date('Y-m-d H:i:s');
    }

    if (preg_match('/\bdate\b/', $type)) {
        return date('Y-m-d');
    }

    if (strpos($type, 'time') !== false && strpos($type, 'datetime') === false) {
        return '12:00:00';
    }

    if (strpos($type, 'year') !== false) {
        return date('Y');
    }

    if (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
        return number_format((float)($sequence % 999 + 1), 2, '.', '');
    }

    if (strpos($type, 'json') !== false) {
        return '{}';
    }

    if (strpos($type, 'char') !== false || strpos($type, 'text') !== false) {
        if ($inUnique || preg_match('/(name|title|code|label|hostname|email|username|slug|sku|number|invoice|description|subject|summary)/i', $name)) {
            return $tag;
        }

        return 'QA ' . str_replace('_', ' ', $name) . ' ' . $sequence;
    }

    if ($forceRequired) {
        return $tag . '-' . $name;
    }

    return null;
}

/**
 * Seeds database.sql parents for a module so random inserts can resolve NOT NULL FKs.
 */
function mbqa_seed_lookup_parents_for_table(mysqli $conn, string $table, int $companyId): void
{
    global $sampleSeedPrerequisites;

    if (!function_exists('itm_seed_table_from_database_sql') || !itm_is_safe_identifier($table) || $companyId <= 0) {
        return;
    }

    $parents = $sampleSeedPrerequisites[$table] ?? [];
    if (function_exists('itm_table_outbound_fk_map')) {
        foreach (itm_table_outbound_fk_map($conn, $table) as $fkMeta) {
            $parentTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
            if ($parentTable !== '' && itm_is_safe_identifier($parentTable) && !in_array($parentTable, mbqa_tables_never_clear(), true)) {
                $parents[] = $parentTable;
            }
        }
    }

    foreach (array_values(array_unique($parents)) as $parentTable) {
        $seedErr = '';
        itm_seed_table_from_database_sql($conn, $parentTable, $companyId, $seedErr);
    }
}

/**
 * @return int[]
 */
function mbqa_query_fk_ids_for_tenant(mysqli $conn, string $refTable, int $companyId, int $limit = 200): array
{
    if (!itm_is_safe_identifier($refTable) || $limit <= 0) {
        return [];
    }

    $tableEsc = '`' . str_replace('`', '``', $refTable) . '`';
    if (itm_table_has_column($conn, $refTable, 'company_id')) {
        $sql = 'SELECT id FROM ' . $tableEsc . ' WHERE company_id=' . (int)$companyId . ' ORDER BY id ASC LIMIT ' . (int)$limit;
    } else {
        $sql = 'SELECT id FROM ' . $tableEsc . ' ORDER BY id ASC LIMIT ' . (int)$limit;
    }

    $ids = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return $ids;
}

function mbqa_query_first_id(mysqli $conn, string $refTable, int $companyId): int
{
    $ids = mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId, 1);

    return $ids[0] ?? 0;
}

function mbqa_pick_fk_value(mysqli $conn, string $refTable, int $companyId, bool $ensureParent = true, int $sequence = 1): int
{
    if (!itm_is_safe_identifier($refTable)) {
        return 0;
    }

    $ids = mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId);
    if (!empty($ids)) {
        return (int)$ids[($sequence - 1) % count($ids)];
    }

    if (!$ensureParent) {
        return 0;
    }

    if (in_array($refTable, mbqa_tables_never_clear(), true)) {
        return 0;
    }

    static $ensuring = [];
    if (isset($ensuring[$refTable])) {
        return 0;
    }
    $ensuring[$refTable] = true;

    if (function_exists('itm_seed_table_from_database_sql')) {
        $seedErr = '';
        itm_seed_table_from_database_sql($conn, $refTable, $companyId, $seedErr);
    }

    $ids = mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId);
    if (empty($ids)) {
        mbqa_insert_random_rows($conn, $refTable, $companyId, 1, 1);
        $ids = mbqa_query_fk_ids_for_tenant($conn, $refTable, $companyId);
    }

    unset($ensuring[$refTable]);

    if (empty($ids)) {
        return 0;
    }

    return (int)$ids[($sequence - 1) % count($ids)];
}

/**
 * Builds one random insert payload (not from database.sql) for QA volume testing.
 *
 * @param array<int, array{name:string,type:string,null:string,default:?string,extra:string,key:string}> $columnMetas
 * @param array<string, array{REFERENCED_TABLE_NAME:string,REFERENCED_COLUMN_NAME:string}> $fkMap
 * @param array<int, array<int, string>> $uniqueSets
 * @return array{columns:array<int,string>,values:array<int,string>,types:array<int,string>}|null
 */
function mbqa_build_random_insert_row(
    mysqli $conn,
    string $table,
    int $companyId,
    int $sequence,
    array $columnMetas,
    array $fkMap,
    array $uniqueSets
): ?array {
    $columns = [];
    $values = [];
    $types = [];
    $tag = 'MBQA-' . $table . '-' . $companyId . '-' . $sequence . '-' . substr(md5($table . (string)$companyId . (string)$sequence), 0, 6);

    foreach ($columnMetas as $meta) {
        $name = (string)$meta['name'];
        if (mbqa_column_skipped_for_insert($name, $meta)) {
            continue;
        }

        $type = (string)$meta['type'];
        $required = mbqa_column_is_required($meta);
        $nullable = ($meta['null'] === 'YES');
        $inUnique = mbqa_column_in_unique_set($name, $uniqueSets);

        if ($name === 'company_id') {
            $columns[] = '`company_id`';
            $values[] = (string)$companyId;
            $types[] = 'i';
            continue;
        }

        $value = null;
        $bindType = 's';
        $refTable = mbqa_fk_reference_table($name, $fkMap);
        $isFkColumn = ($refTable !== '' || isset($fkMap[$name]));

        if ($isFkColumn) {
            if ($refTable === '') {
                $refTable = mbqa_fk_reference_table($name, $fkMap);
            }
            $fkId = $refTable !== '' ? mbqa_pick_fk_value($conn, $refTable, $companyId, true, $sequence) : 0;
            if ($fkId > 0) {
                $value = (string)$fkId;
                $bindType = 'i';
            }
        } else {
            $scalar = mbqa_fill_scalar_value($name, $type, $sequence, $tag, $inUnique, $required);
            if ($scalar !== null) {
                $value = $scalar;
                if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit)/', $type) || $name === 'active') {
                    $bindType = 'i';
                }
            }
        }

        if ($value === null && mbqa_column_has_db_default($meta)) {
            $value = (string)$meta['default'];
        }

        if ($value === null && !$isFkColumn) {
            $value = mbqa_fill_scalar_value($name, $type, $sequence, $tag, $inUnique, true);
            if ($value !== null && preg_match('/^(tinyint|smallint|mediumint|int|bigint|bit)/', $type)) {
                $bindType = 'i';
            }
        }

        if ($value === null) {
            if ($nullable && !$required) {
                continue;
            }

            return null;
        }

        $columns[] = '`' . str_replace('`', '``', $name) . '`';
        $values[] = $value;
        $types[] = $bindType;
    }

    if (empty($columns)) {
        return null;
    }

    $requiredNames = mbqa_required_column_names($columnMetas);
    $present = [];
    foreach ($columns as $colEsc) {
        $present[trim($colEsc, '`')] = true;
    }
    foreach ($requiredNames as $requiredName) {
        if (!isset($present[$requiredName])) {
            return null;
        }
    }

    return ['columns' => $columns, 'values' => $values, 'types' => $types];
}

/**
 * @return array{inserted:int,last_error:string}
 */
function mbqa_insert_random_rows(mysqli $conn, string $table, int $companyId, int $needed, int $parentDepth = 0): array
{
    if ($needed <= 0 || !itm_is_safe_identifier($table) || !itm_table_has_column($conn, $table, 'company_id')) {
        return ['inserted' => 0, 'last_error' => ''];
    }

    if ($parentDepth > 4) {
        return ['inserted' => 0, 'last_error' => 'FK parent depth limit'];
    }

    if ($parentDepth === 0) {
        mbqa_ensure_parent_rows_for_inserts($conn, $table, $companyId, mbqa_bulk_row_target_for_table($conn, $table, $companyId));
    }

    $columnMetas = mbqa_table_column_metas($conn, $table);
    if (empty($columnMetas)) {
        return ['inserted' => 0, 'last_error' => 'No column metadata'];
    }

    $fkMap = function_exists('itm_table_outbound_fk_map') ? itm_table_outbound_fk_map($conn, $table) : [];
    $uniqueSets = mbqa_table_unique_column_sets($conn, $table);
    $inserted = 0;
    $lastError = '';
    $sequence = (int)(microtime(true) * 1000) % 100000;
    $maxAttempts = max($needed * 6, $needed + 10);

    for ($attempt = 0; $attempt < $maxAttempts && $inserted < $needed; $attempt++) {
        $sequence++;
        $row = mbqa_build_random_insert_row($conn, $table, $companyId, $sequence, $columnMetas, $fkMap, $uniqueSets);
        if ($row === null) {
            $requiredCols = mbqa_required_column_names($columnMetas);
            $lastError = 'Could not build insert payload (missing FK parent or required column)';
            if (!empty($requiredCols)) {
                $lastError .= '; required: ' . implode(', ', array_slice($requiredCols, 0, 6));
            }
            continue;
        }

        $placeholders = implode(',', array_fill(0, count($row['columns']), '?'));
        $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $row['columns']) . ') VALUES (' . $placeholders . ')';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $lastError = mysqli_error($conn);
            continue;
        }

        $bindTypes = implode('', $row['types']);
        $bindValues = $row['values'];
        $bindParams = [$stmt, $bindTypes];
        for ($bi = 0, $bn = count($bindValues); $bi < $bn; $bi++) {
            $bindParams[] = &$bindValues[$bi];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bindParams);
        if (mysqli_stmt_execute($stmt)) {
            $inserted++;
            $lastError = '';
        } else {
            $lastError = mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    return ['inserted' => $inserted, 'last_error' => $lastError];
}

/**
 * @return array{ok:bool,note:string,na:bool,count:int}
 */
function mbqa_ensure_bulk_sample_rows(mysqli $conn, string $table, int $companyId): array
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return ['ok' => false, 'note' => 'Invalid table or company', 'na' => false, 'count' => 0];
    }

    if (in_array($table, mbqa_tables_never_clear(), true)) {
        return ['ok' => true, 'note' => 'N/A (shared auth table)', 'na' => true, 'count' => 0];
    }

    if (!itm_table_has_column($conn, $table, 'company_id')) {
        return ['ok' => true, 'note' => 'N/A (no company_id)', 'na' => true, 'count' => 0];
    }

    $ideal = mbqa_bulk_row_target_ideal($conn);
    // Why: e.g. expenses needs enough cost_centers before uq_expenses_company_scope allows 30 expense rows.
    mbqa_grow_unique_scope_parents($conn, $table, $companyId, $ideal);

    $target = mbqa_bulk_row_target_for_table($conn, $table, $companyId);
    $capacity = mbqa_unique_scope_capacity($conn, $table, $companyId);
    $targetNote = ($capacity < $ideal && $capacity < PHP_INT_MAX)
        ? (' target=' . $target . ' capped by unique scope')
        : (' target=' . $target);

    mbqa_ensure_parent_rows_for_inserts($conn, $table, $companyId, $ideal);

    $current = mbqa_tenant_row_count($conn, $table, $companyId);
    if ($current >= $target) {
        return ['ok' => true, 'note' => 'Already ' . $current . ' rows (>=' . $target . ')' . $targetNote, 'na' => false, 'count' => $current];
    }

    if ($current === 0 && function_exists('itm_seed_table_from_database_sql')) {
        $seedErr = '';
        itm_seed_table_from_database_sql($conn, $table, $companyId, $seedErr);
        $current = mbqa_tenant_row_count($conn, $table, $companyId);
    }

    $needed = $target - $current;
    $insertResult = mbqa_insert_random_rows($conn, $table, $companyId, $needed);
    $added = (int)$insertResult['inserted'];
    $final = mbqa_tenant_row_count($conn, $table, $companyId);
    $ok = $final >= $target;

    if ($added > 0) {
        $note = 'inserted ' . $added . ' random row(s); total=' . $final . $targetNote;
    } else {
        $note = 'Could not insert random rows; total=' . $final . $targetNote;
        if ($insertResult['last_error'] !== '') {
            $note .= '; ' . $insertResult['last_error'];
        }
    }

    return ['ok' => $ok, 'note' => $note, 'na' => false, 'count' => $final];
}

function mbqa_index_shows_bulk_actions(string $html): bool
{
    return stripos($html, 'name="ids[]"') !== false
        && (stripos($html, 'bulk_action') !== false || stripos($html, 'bulk-delete-form') !== false);
}

/**
 * @param int[] $ids
 * @return array{ok:bool,note:string}
 */
function mbqa_run_bulk_delete(string $moduleUrl, string $cookieFile, string $csrf, array $ids): array
{
    if ($csrf === '' || empty($ids)) {
        return ['ok' => false, 'note' => 'N/A no csrf/ids'];
    }

    $parts = ['bulk_action=bulk_delete', 'csrf_token=' . rawurlencode($csrf)];
    foreach ($ids as $id) {
        $parts[] = 'ids[]=' . (int)$id;
    }
    $body = implode('&', $parts);

    $resp = mbqa_http(
        $moduleUrl . 'delete.php',
        'POST',
        $body,
        ['Content-Type: application/x-www-form-urlencoded'],
        $cookieFile
    );
    if ($resp['status'] < 200 || $resp['status'] >= 400) {
        return ['ok' => false, 'note' => 'bulk_delete HTTP ' . $resp['status']];
    }

    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    if ($index['status'] !== 200 || mbqa_has_fatal($index['body'])) {
        return ['ok' => false, 'note' => 'index after bulk_delete HTTP ' . $index['status']];
    }

    $remaining = 0;
    foreach ($ids as $id) {
        if (mbqa_index_still_has_row($index['body'], (int)$id)) {
            $remaining++;
        }
    }

    if ($remaining > 0) {
        return ['ok' => false, 'note' => $remaining . ' selected id(s) still listed'];
    }

    return ['ok' => true, 'note' => 'deleted ids=' . implode(',', array_map('intval', $ids))];
}

/**
 * @return array{ok:bool,note:string}
 */
function mbqa_run_clear_table(string $moduleUrl, string $cookieFile, string $csrf): array
{
    if ($csrf === '') {
        return ['ok' => false, 'note' => 'N/A no csrf'];
    }

    $resp = mbqa_http(
        $moduleUrl . 'delete.php',
        'POST',
        http_build_query(['bulk_action' => 'clear_table', 'csrf_token' => $csrf]),
        ['Content-Type: application/x-www-form-urlencoded'],
        $cookieFile
    );
    if ($resp['status'] < 200 || $resp['status'] >= 400) {
        return ['ok' => false, 'note' => 'clear_table HTTP ' . $resp['status']];
    }

    $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
    if ($index['status'] !== 200 || mbqa_has_fatal($index['body'])) {
        return ['ok' => false, 'note' => 'index after clear_table HTTP ' . $index['status']];
    }

    if (!mbqa_index_is_empty($index['body'])) {
        return ['ok' => false, 'note' => 'rows still present after clear_table'];
    }

    return ['ok' => true, 'note' => 'table empty for tenant'];
}

/**
 * Extracts the blocking child table from a MySQL 1451 FK error (not the schema name).
 */
function mbqa_mysql_fk_blocker_table(string $errMsg): string
{
    if (preg_match('/foreign key constraint fails\s*\(\s*`[^`]+`\.`([^`]+)`/i', $errMsg, $m)) {
        $table = (string)($m[1] ?? '');
        return itm_is_safe_identifier($table) ? $table : '';
    }

    if (preg_match('/REFERENCES\s+`[^`]+`\.`([^`]+)`/i', $errMsg, $m)) {
        $table = (string)($m[1] ?? '');
        return itm_is_safe_identifier($table) ? $table : '';
    }

    if (preg_match_all('/`([^`]+)`/', $errMsg, $matches) && count($matches[1]) >= 2) {
        $table = (string)$matches[1][count($matches[1]) - 1];
        return itm_is_safe_identifier($table) ? $table : '';
    }

    return '';
}

/**
 * Inbound FK children of a table (MySQL information_schema).
 *
 * @return array<int, array{child_table:string, child_column:string, parent_column:string}>
 */
function mbqa_inbound_fk_refs(mysqli $conn, string $parentTable): array
{
    if (!itm_is_safe_identifier($parentTable)) {
        return [];
    }

    $sql = 'SELECT kcu.TABLE_NAME AS child_table,
                   kcu.COLUMN_NAME AS child_column,
                   kcu.REFERENCED_COLUMN_NAME AS parent_column
            FROM information_schema.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.REFERENCED_TABLE_NAME = ?
              AND kcu.REFERENCED_COLUMN_NAME IS NOT NULL';

    $refs = [];
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 's', $parentTable);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $child = (string)($row['child_table'] ?? '');
        $col = (string)($row['child_column'] ?? '');
        $parentCol = (string)($row['parent_column'] ?? 'id');
        if (!itm_is_safe_identifier($child) || !itm_is_safe_identifier($col) || !itm_is_safe_identifier($parentCol)) {
            continue;
        }
        $refs[] = [
            'child_table' => $child,
            'child_column' => $col,
            'parent_column' => $parentCol,
        ];
    }
    mysqli_stmt_close($stmt);

    return $refs;
}

/**
 * Deletes tenant rows from a table (company_id) or rows tied to a parent tenant via FK join.
 */
function mbqa_delete_table_company_scoped(
    mysqli $conn,
    string $table,
    int $companyId,
    ?string $parentTable = null,
    ?string $childFkColumn = null,
    ?string $parentPkColumn = 'id'
): bool {
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return false;
    }

    $tableEsc = '`' . str_replace('`', '``', $table) . '`';

    if (itm_table_has_column($conn, $table, 'company_id')) {
        $sql = 'DELETE FROM ' . $tableEsc . ' WHERE company_id=' . (int)$companyId;
        return itm_run_query($conn, $sql) !== false;
    }

    if (
        $parentTable !== null
        && $childFkColumn !== null
        && itm_is_safe_identifier($parentTable)
        && itm_is_safe_identifier($childFkColumn)
        && itm_is_safe_identifier($parentPkColumn)
        && itm_table_has_column($conn, $parentTable, 'company_id')
    ) {
        $parentEsc = '`' . str_replace('`', '``', $parentTable) . '`';
        $colEsc = '`' . str_replace('`', '``', $childFkColumn) . '`';
        $pkEsc = '`' . str_replace('`', '``', $parentPkColumn) . '`';
        $sql = 'DELETE c FROM ' . $tableEsc . ' c INNER JOIN ' . $parentEsc . ' p ON c.' . $colEsc . ' = p.' . $pkEsc
            . ' WHERE p.company_id=' . (int)$companyId;

        return itm_run_query($conn, $sql) !== false;
    }

    return false;
}

/**
 * Clears inbound FK dependents first, then the target module table for one company.
 */
function mbqa_clear_module_table_for_company(mysqli $conn, string $table, int $companyId, string &$note = ''): bool
{
    $note = '';
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        $note = 'Invalid table or company';
        return false;
    }
    if (in_array($table, mbqa_tables_never_clear(), true)) {
        $note = 'Skip never-clear table';
        return false;
    }
    if (!itm_table_has_column($conn, $table, 'company_id')) {
        $note = 'No company_id column';
        return false;
    }

    $clearedFirst = [];
    $visited = [];

    $clearRecursive = static function (mysqli $conn, string $target, int $companyId, array &$visited, array &$clearedFirst) use (&$clearRecursive): void {
        if (isset($visited[$target])) {
            return;
        }
        $visited[$target] = true;

        foreach (mbqa_inbound_fk_refs($conn, $target) as $ref) {
            $child = $ref['child_table'];
            if (in_array($child, mbqa_tables_never_clear(), true)) {
                continue;
            }
            $clearRecursive($conn, $child, $companyId, $visited, $clearedFirst);
            if (mbqa_delete_table_company_scoped($conn, $child, $companyId, $target, $ref['child_column'], $ref['parent_column'])) {
                $clearedFirst[$child] = $child;
            }
        }
    };

    $clearRecursive($conn, $table, $companyId, $visited, $clearedFirst);

    $tableEsc = '`' . str_replace('`', '``', $table) . '`';
    $sql = 'DELETE FROM ' . $tableEsc . ' WHERE company_id=' . (int)$companyId;
    $errCode = 0;
    $errMsg = '';
    $ok = itm_run_query($conn, $sql, $errCode, $errMsg) !== false;

    if (!$ok && (int)$errCode === 1451) {
        for ($attempt = 0; $attempt < 12 && !$ok; $attempt++) {
            $blocker = mbqa_mysql_fk_blocker_table((string)$errMsg);
            if ($blocker !== '' && !in_array($blocker, mbqa_tables_never_clear(), true)) {
                $subNote = '';
                mbqa_clear_module_table_for_company($conn, $blocker, $companyId, $subNote);
                $clearedFirst[$blocker] = $blocker;
            }
            $errCode = 0;
            $errMsg = '';
            $ok = itm_run_query($conn, $sql, $errCode, $errMsg) !== false;
        }
    }

    if ($ok) {
        if (!empty($clearedFirst)) {
            $note = 'SQL tenant clear (first cleared: ' . implode(', ', array_values($clearedFirst)) . ')';
        } else {
            $note = 'SQL tenant clear';
        }
    } else {
        $note = $errMsg !== '' ? ('Clear failed: ' . $errMsg) : 'Clear failed';
    }

    return $ok;
}

/**
 * Parses "in use by: employee_positions (1), …" from module error banners.
 *
 * @return string[]
 */
function mbqa_parse_in_use_tables(string $html): array
{
    $tables = [];
    if (!preg_match('/in use by:\s*(.+?)(?:\.|<\/)/is', $html, $m)) {
        return [];
    }
    $segment = (string)$m[1];
    if (preg_match_all('/\b([a-z][a-z0-9_]*)\s*\(\d+\)/i', $segment, $matches)) {
        foreach ($matches[1] as $name) {
            if (itm_is_safe_identifier($name)) {
                $tables[$name] = $name;
            }
        }
    }

    return array_values($tables);
}

function mbqa_index_still_has_row(string $html, int $id): bool
{
    return preg_match('/name="ids\[\]"\s+value="' . preg_quote((string)$id, '/') . '"/', $html) === 1
        || preg_match('/view\.php\?id=' . preg_quote((string)$id, '/') . '\b/', $html) === 1;
}

/**
 * POST delete.php and, on "in use by" errors, clear blockers for the tenant then retry.
 */
function mbqa_delete_record_with_fk_retry(
    mysqli $conn,
    string $moduleUrl,
    string $table,
    int $recordId,
    int $companyId,
    string $csrf,
    string $cookieFile
): array {
    if ($recordId <= 0 || $csrf === '') {
        return ['ok' => false, 'note' => 'N/A no row/csrf'];
    }

    $cleared = [];
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $del = mbqa_http(
            $moduleUrl . 'delete.php',
            'POST',
            http_build_query(['id' => (string)$recordId, 'csrf_token' => $csrf]),
            ['Content-Type: application/x-www-form-urlencoded'],
            $cookieFile
        );
        if ($del['status'] < 200 || $del['status'] >= 400) {
            return ['ok' => false, 'note' => 'delete HTTP ' . $del['status']];
        }

        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        if ($index['status'] !== 200 || mbqa_has_fatal($index['body'])) {
            return ['ok' => false, 'note' => 'index after delete HTTP ' . $index['status']];
        }

        if (!mbqa_index_still_has_row($index['body'], $recordId)) {
            $note = 'deleted id=' . $recordId;
            if (!empty($cleared)) {
                $note .= '; first cleared: ' . implode(', ', $cleared);
            }
            return ['ok' => true, 'note' => $note];
        }

        $blockers = mbqa_parse_in_use_tables($index['body']);
        if (empty($blockers) && function_exists('itm_find_record_usage')) {
            $usage = itm_find_record_usage($conn, $table, 'id', $recordId, $companyId);
            foreach ($usage as $row) {
                $t = (string)($row['table'] ?? '');
                if ($t !== '' && itm_is_safe_identifier($t)) {
                    $blockers[] = $t;
                }
            }
            $blockers = array_values(array_unique($blockers));
        }

        if (empty($blockers)) {
            return ['ok' => false, 'note' => 'row still listed; no blockers parsed'];
        }

        $progress = false;
        foreach ($blockers as $blocker) {
            if (in_array($blocker, mbqa_tables_never_clear(), true)) {
                continue;
            }
            $subNote = '';
            if (mbqa_clear_module_table_for_company($conn, $blocker, $companyId, $subNote)) {
                $cleared[$blocker] = $blocker;
                $progress = true;
            }
        }

        if (!$progress) {
            return ['ok' => false, 'note' => 'blocked by ' . implode(', ', $blockers)];
        }
    }

    return ['ok' => false, 'note' => 'delete retries exhausted'];
}

$cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itm_qa_cookies_' . getmypid() . '.txt';
@unlink($cookieFile);

// Login
$loginGet = mbqa_http($baseUrl . 'login.php', 'GET', null, [], $cookieFile);
$csrf = mbqa_extract_csrf($loginGet['body']);
$loginPost = mbqa_http(
    $baseUrl . 'login.php',
    'POST',
    http_build_query(['email' => 'Admin', 'password' => 'Admin', 'csrf_token' => $csrf]),
    ['Content-Type: application/x-www-form-urlencoded'],
    $cookieFile
);
if ($loginPost['status'] < 200 || $loginPost['status'] >= 400 || mbqa_has_fatal($loginPost['body'])) {
    mbqa_err("Login failed (HTTP {$loginPost['status']}). Is Laragon running at {$baseUrl}?\n");
    exit(1);
}

// Why: First action of the run — each module also deletes the log before its steps so errors are scoped per module.
mbqa_delete_error_log_file();

$orderedModules = array_unique(array_merge($lookupWave, $budgetWave, $allModules));
if ($pilotOnly) {
    $orderedModules = ['expenses'];
}
if ($filterModule !== null) {
    $orderedModules = in_array($filterModule, $allModules, true) ? [$filterModule] : [$filterModule];
}

$companiesToRun = array_keys($companyNames);
if ($filterCompany !== null && $filterCompany > 0) {
    $companiesToRun = [$filterCompany];
}

$results = [];
$summary = ['pass' => 0, 'fail' => 0, 'na' => 0, 'modules' => 0];

foreach ($companiesToRun as $companyId) {
    $dashGet = mbqa_http($baseUrl . 'dashboard.php', 'GET', null, [], $cookieFile);
    $csrfDash = mbqa_extract_csrf($dashGet['body']);
    $switch = mbqa_http(
        $baseUrl . 'dashboard.php',
        'POST',
        http_build_query(['company_id' => (string)$companyId, 'csrf_token' => $csrfDash]),
        ['Content-Type: application/x-www-form-urlencoded'],
        $cookieFile
    );
    $companyOk = $switch['status'] >= 200 && $switch['status'] < 400
        && stripos($switch['body'], (string)$companyNames[$companyId]) !== false;
    $results[] = [
        'module' => '_preflight',
        'company_id' => $companyId,
        'company_name' => $companyNames[$companyId],
        'tier' => 'preflight',
        'steps' => [mbqa_step_result('company_switch', $companyOk, $companyOk ? '' : 'Company name not found after switch')],
    ];

    // Why: A failed switch leaves the prior tenant active; skip destructive module steps for this company.
    if (!$companyOk) {
        continue;
    }

    foreach ($orderedModules as $slug) {
        if (!is_dir($modulesDir . DIRECTORY_SEPARATOR . $slug)) {
            continue;
        }

        $tier = 'A';
        if (in_array($slug, $bespokeSmoke, true)) {
            $tier = 'D';
        } elseif (strpos($slug, 'is_') === 0) {
            $tier = 'C';
        }

        $moduleUrl = $baseUrl . 'modules/' . rawurlencode($slug) . '/';
        $steps = [];

        mbqa_delete_error_log_file();
        $errorLogOffset = 0;
        $steps[] = mbqa_step_result('error_log', true, 'deleted error_log.txt');

        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $listOk = $index['status'] === 200 && !mbqa_has_fatal($index['body']);
        $steps[] = mbqa_step_result('list', $listOk, $listOk ? '' : 'HTTP ' . $index['status']);

        if ($tier === 'D') {
            $steps[] = mbqa_step_result('clear', true, 'Skip (bespoke smoke)');
            $steps[] = mbqa_step_result('sample_data', true, 'N/A smoke');
            $steps[] = mbqa_step_result('add', true, 'N/A smoke');
            $steps[] = mbqa_step_result('create', true, 'N/A smoke');
            $steps[] = mbqa_step_result('view', true, 'N/A smoke');
            $steps[] = mbqa_step_result('edit', true, 'N/A smoke');
            $steps[] = mbqa_step_result('list_all', true, 'N/A');
            $steps[] = mbqa_step_result('search', $listOk, 'index only');
            $steps[] = mbqa_step_result('sort', $listOk, 'index only');
            $steps[] = mbqa_step_result('export_pdf', true, 'N/A smoke');
            $steps[] = mbqa_step_result('export_xls', true, 'N/A smoke');
            $steps[] = mbqa_step_result('import_db', true, 'N/A smoke');
            $steps[] = mbqa_step_result('single_delete', true, 'N/A smoke');
            $steps[] = mbqa_step_result('bulk_delete', true, 'N/A');
            $steps[] = mbqa_step_result('clear_table', true, 'N/A');
            $steps[] = mbqa_step_result('sample_data', true, 'N/A (end restore)');
            $errorLog = mbqa_read_error_log_since($errorLogOffset);
            $steps[] = mbqa_step_result('error_log', $errorLog['ok'], $errorLog['note']);
            $results[] = [
                'module' => $slug,
                'company_id' => $companyId,
                'company_name' => $companyNames[$companyId],
                'tier' => $tier,
                'steps' => $steps,
            ];
            continue;
        }

        if ($tier === 'C' && $slug !== 'is_switch') {
            $routeOk = $listOk;
            $steps[] = mbqa_step_result('clear', true, 'N/A façade routing');
            $steps[] = mbqa_step_result('sample_data', true, 'N/A façade');
            $steps[] = mbqa_step_result('add', true, 'N/A façade');
            foreach (['create', 'view', 'edit', 'list_all', 'single_delete', 'search', 'sort', 'export_pdf', 'export_xls', 'import_db', 'bulk_delete', 'clear_table'] as $s) {
                $steps[] = mbqa_step_result($s, $routeOk, 'routing smoke only');
            }
            $steps[] = mbqa_step_result('sample_data', true, 'N/A (end restore)');
            $errorLog = mbqa_read_error_log_since($errorLogOffset);
            $steps[] = mbqa_step_result('error_log', $errorLog['ok'], $errorLog['note']);
            $results[] = [
                'module' => $slug,
                'company_id' => $companyId,
                'company_name' => $companyNames[$companyId],
                'tier' => $tier,
                'steps' => $steps,
            ];
            continue;
        }

        $csrfIndex = mbqa_extract_csrf($index['body']);
        $ids = mbqa_row_ids($index['body']);

        if (!in_array($slug, $skipClear, true) && itm_is_safe_identifier($slug)) {
            $clearNote = '';
            $cleared = mbqa_clear_module_table_for_company($conn, $slug, $companyId, $clearNote);
            $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
            $csrfIndex = mbqa_extract_csrf($index['body']);
            $steps[] = mbqa_step_result('clear', $cleared, $clearNote !== '' ? $clearNote : ($cleared ? 'SQL tenant clear' : 'Clear failed'));
        } else {
            $steps[] = mbqa_step_result('clear', true, 'Skip destructive clear');
        }

        $seedResult = mbqa_ensure_sample_data($conn, $slug, $companyId, $moduleUrl, $cookieFile);
        $index['body'] = $seedResult['html'];
        $csrfIndex = $seedResult['csrf'];
        if ($seedResult['na']) {
            $steps[] = mbqa_step_result('sample_data', true, $seedResult['note']);
        } else {
            $steps[] = mbqa_step_result('sample_data', $seedResult['ok'], $seedResult['note']);
        }

        $_SESSION['company_id'] = $companyId;
        $bulkResult = mbqa_ensure_bulk_sample_rows($conn, $slug, $companyId);
        if ($bulkResult['na']) {
            $steps[] = mbqa_step_result('add', true, $bulkResult['note']);
        } else {
            $steps[] = mbqa_step_result('add', $bulkResult['ok'], $bulkResult['note']);
        }

        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $csrfIndex = mbqa_extract_csrf($index['body']);

        $deletePath = $modulesDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'delete.php';
        $perPage = mbqa_records_per_page($conn);
        $rowCountAfterAdd = mbqa_tenant_row_count($conn, $slug, $companyId);
        $canBulkAfterAdd = $rowCountAfterAdd >= $perPage && mbqa_index_shows_bulk_actions($index['body']);

        if ($canBulkAfterAdd && is_file($deletePath) && $csrfIndex !== '') {
            $bulkIds = array_slice(mbqa_row_ids($index['body']), 0, 3);
            if (!empty($bulkIds)) {
                $bulkDelEarly = mbqa_run_bulk_delete($moduleUrl, $cookieFile, $csrfIndex, $bulkIds);
                $steps[] = mbqa_step_result('bulk_delete', $bulkDelEarly['ok'], $bulkDelEarly['note']);
                $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
                $csrfIndex = mbqa_extract_csrf($index['body']);
            } else {
                $steps[] = mbqa_step_result('bulk_delete', true, 'N/A (no ids[] on index after add)');
            }
        } else {
            $steps[] = mbqa_step_result(
                'bulk_delete',
                true,
                'N/A (' . $rowCountAfterAdd . ' rows < perPage ' . $perPage . ' after add)'
            );
        }

        $search = mbqa_http($moduleUrl . 'index.php?search=sample&page=1', 'GET', null, [], $cookieFile);
        $steps[] = mbqa_step_result('search', $search['status'] === 200 && !mbqa_has_fatal($search['body']), 'HTTP ' . $search['status']);

        $sortField = 'id';
        if (preg_match('/\?[^"\']*sort=([a-zA-Z0-9_]+)/', $index['body'], $sortMatch)) {
            $sortField = $sortMatch[1];
        }
        $sort = mbqa_http($moduleUrl . 'index.php?sort=' . rawurlencode($sortField) . '&dir=DESC&page=1', 'GET', null, [], $cookieFile);
        $sortOk = $sort['status'] === 200
            && (preg_match('/sort=' . preg_quote($sortField, '/') . '[^"\']*dir=DESC/i', $sort['body'])
                || stripos($sort['body'], '▼') !== false
                || stripos($sort['body'], '&#9660;') !== false
                || stripos($sort['body'], 'dir=DESC') !== false);
        $steps[] = mbqa_step_result('sort', $sortOk, $sortOk ? 'sort=' . $sortField : 'Sort indicators missing');

        $create = mbqa_http($moduleUrl . 'create.php', 'GET', null, [], $cookieFile);
        $steps[] = mbqa_step_result('create', $create['status'] === 200 && !mbqa_has_fatal($create['body']), 'HTTP ' . $create['status']);

        $ids = mbqa_row_ids($index['body']);
        $viewId = $ids[0] ?? 0;
        if ($viewId > 0) {
            $view = mbqa_http($moduleUrl . 'view.php?id=' . $viewId, 'GET', null, [], $cookieFile);
            $steps[] = mbqa_step_result('view', $view['status'] === 200 && !mbqa_has_fatal($view['body']), 'id=' . $viewId);
            $edit = mbqa_http($moduleUrl . 'edit.php?id=' . $viewId, 'GET', null, [], $cookieFile);
            $steps[] = mbqa_step_result('edit', $edit['status'] === 200 && !mbqa_has_fatal($edit['body']), 'id=' . $viewId);
        } else {
            $steps[] = mbqa_step_result('view', true, 'N/A no rows');
            $steps[] = mbqa_step_result('edit', true, 'N/A no rows');
        }

        $listAllPath = $modulesDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'list_all.php';
        if (is_file($listAllPath)) {
            $la = mbqa_http($moduleUrl . 'list_all.php', 'GET', null, [], $cookieFile);
            $steps[] = mbqa_step_result('list_all', $la['status'] === 200 && !mbqa_has_fatal($la['body']), 'HTTP ' . $la['status']);
        } else {
            $steps[] = mbqa_step_result('list_all', true, 'N/A');
        }

        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $csrfIndex = mbqa_extract_csrf($index['body']);

        $exportButtons = mbqa_index_has_export_buttons($index['body']);
        $exportRows = mbqa_extract_table_export_rows($index['body']);
        $hasTableExport = count($exportRows) >= 2;

        $steps[] = mbqa_step_result(
            'export_pdf',
            $exportButtons['pdf'] && $hasTableExport,
            $hasTableExport
                ? ('table export ready, ' . (count($exportRows) - 1) . ' row(s)')
                : ($exportButtons['pdf'] ? 'Export PDF button; no table rows' : 'No Export PDF / table')
        );

        $steps[] = mbqa_step_result(
            'export_xls',
            $exportButtons['excel'] && $hasTableExport,
            $hasTableExport
                ? ('extracted ' . (count($exportRows) - 1) . ' row(s) like Export Excel')
                : ($exportButtons['excel'] ? 'Export Excel button; no extractable rows' : 'No Export Excel / table')
        );

        $hasImportEndpoint = stripos($index['body'], 'data-itm-db-import-endpoint') !== false;
        if ($hasImportEndpoint && $csrfIndex !== '' && $hasTableExport) {
            // Why: import rows pick a free cost_center via mbqa_unique_expense_import_row; do not wipe add-step rows here.

            $indexImport = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
            $csrfIndex = mbqa_extract_csrf($indexImport['body']);
            $_SESSION['company_id'] = $companyId;
            $importRows = mbqa_import_rows_for_round_trip($conn, $slug, $companyId, $exportRows);
            if (empty($importRows)) {
                $importRows = mbqa_build_import_rows_from_export($exportRows);
            }
            $importNote = 'Export Excel headers with insertable row (database.sql FK ids when needed)';
            $importPayload = json_encode([
                'csrf_token' => $csrfIndex,
                'import_excel_rows' => $importRows,
            ]);
            $import = mbqa_http(
                $moduleUrl . 'index.php',
                'POST',
                $importPayload,
                ['Content-Type: application/json'],
                $cookieFile
            );
            $inserted = 0;
            if (preg_match('/"inserted"\s*:\s*(\d+)/', $import['body'], $insMatch)) {
                $inserted = (int)$insMatch[1];
            }
            $importOk = $import['status'] === 200
                && stripos($import['body'], '"ok":true') !== false
                && $inserted > 0;

            if (!$importOk) {
                $dbImportRows = mbqa_build_import_rows_from_db_template($conn, $slug, $companyId);
                if (!empty($dbImportRows)) {
                    $importPayload = json_encode([
                        'csrf_token' => $csrfIndex,
                        'import_excel_rows' => $dbImportRows,
                    ]);
                    $import = mbqa_http(
                        $moduleUrl . 'index.php',
                        'POST',
                        $importPayload,
                        ['Content-Type: application/json'],
                        $cookieFile
                    );
                    $inserted = 0;
                    if (preg_match('/"inserted"\s*:\s*(\d+)/', $import['body'], $insMatch)) {
                        $inserted = (int)$insMatch[1];
                    }
                    $importOk = $import['status'] === 200
                        && stripos($import['body'], '"ok":true') !== false
                        && $inserted > 0;
                    $importNote = 'from live DB row after round-trip import';
                }
            }

            $steps[] = mbqa_step_result(
                'import_db',
                $importOk,
                $importOk
                    ? ('imported ' . $importNote . '; inserted=' . $inserted)
                    : substr($import['body'], 0, 120)
            );
        } else {
            $steps[] = mbqa_step_result(
                'import_db',
                true,
                $hasImportEndpoint ? 'N/A (need table rows for export/import)' : 'N/A (no import endpoint)'
            );
        }

        if ($viewId > 0 && is_file($deletePath) && $csrfIndex !== '') {
            $delResult = mbqa_delete_record_with_fk_retry(
                $conn,
                $moduleUrl,
                $slug,
                $viewId,
                $companyId,
                $csrfIndex,
                $cookieFile
            );
            $steps[] = mbqa_step_result('single_delete', $delResult['ok'], $delResult['note']);
        } else {
            $steps[] = mbqa_step_result('single_delete', true, $viewId > 0 ? 'N/A (no delete.php/csrf)' : 'N/A no rows');
        }

        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $csrfIndex = mbqa_extract_csrf($index['body']);
        $rowCount = mbqa_tenant_row_count($conn, $slug, $companyId);
        $canClearTable = $rowCountAfterAdd >= $perPage && mbqa_index_shows_bulk_actions($index['body']);

        if ($canClearTable && is_file($deletePath) && $csrfIndex !== '') {
            $clearResult = mbqa_run_clear_table($moduleUrl, $cookieFile, $csrfIndex);
            $steps[] = mbqa_step_result('clear_table', $clearResult['ok'], $clearResult['note']);
        } else {
            $steps[] = mbqa_step_result(
                'clear_table',
                true,
                $canClearTable ? 'N/A (no delete.php/csrf)' : 'N/A (' . $rowCountAfterAdd . ' rows < perPage ' . $perPage . ' after add)'
            );
        }

        $endSeed = mbqa_http_sample_seed_end($moduleUrl, $cookieFile);
        if ($endSeed['na']) {
            $steps[] = mbqa_step_result('sample_data', true, $endSeed['note']);
        } else {
            $steps[] = mbqa_step_result('sample_data', $endSeed['ok'], $endSeed['note']);
        }

        $errorLog = mbqa_read_error_log_since($errorLogOffset);
        $steps[] = mbqa_step_result('error_log', $errorLog['ok'], $errorLog['note']);

        $results[] = [
            'module' => $slug,
            'company_id' => $companyId,
            'company_name' => $companyNames[$companyId],
            'tier' => $tier,
            'steps' => $steps,
        ];
    }
}

@unlink($cookieFile);

$outDir = $root . DIRECTORY_SEPARATOR . 'qa-reports';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$date = date('Y-m-d');
$jsonPath = $outDir . DIRECTORY_SEPARATOR . 'module-browser-qa-' . $date . '.json';
file_put_contents($jsonPath, json_encode($results, JSON_PRETTY_PRINT));

$failuresByCategory = [];
foreach ($results as $row) {
    foreach ($row['steps'] as $step) {
        if ($step['status'] === 'Pass') {
            $summary['pass']++;
        } else {
            $summary['fail']++;
            $cat = $row['module'] . ':' . $step['step'];
            $failuresByCategory[$cat] = ($failuresByCategory[$cat] ?? 0) + 1;
        }
    }
    if (($row['module'] ?? '') !== '_preflight') {
        $summary['modules']++;
    }
}

$exitCode = $summary['fail'] > 0 ? 1 : 0;
mbqa_out("Wrote {$jsonPath}\n");
mbqa_out("Steps pass: {$summary['pass']}, fail: {$summary['fail']}\n");

if (!mbqa_is_cli_sapi()) {
    $jsonRel = '../qa-reports/module-browser-qa-' . $date . '.json';
    $reportHref = 'module_browser_qa_build_report.php?run=1&amp;date=' . rawurlencode($date);
    itm_script_output_close_pre();
    echo '<div style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;margin:16px;max-width:720px;">';
    echo '<p><strong>' . ($exitCode === 0 ? 'Completed' : 'Completed with failures') . '</strong> — ';
    echo (int)$summary['pass'] . ' pass, ' . (int)$summary['fail'] . ' fail</p>';
    echo '<p><a href="' . htmlspecialchars($jsonRel, ENT_QUOTES, 'UTF-8') . '">Download JSON</a> · ';
    echo '<a href="' . htmlspecialchars($reportHref, ENT_QUOTES, 'UTF-8') . '">Build markdown report</a> · ';
    echo '<a href="module_browser_qa_runner.php">Run again</a></p></div>';
    itm_script_output_end();
}

exit($exitCode);
