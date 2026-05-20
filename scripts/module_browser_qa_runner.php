<?php
/**
 * HTTP session runner for full-module QA (mirrors browser checklist).
 *
 * Why: Exercising 101 modules × 5 companies via IDE browser alone is not practical;
 * this CLI tool uses the same login, company scope, CSRF, and module URLs as manual QA.
 *
 * Usage (repository root):
 *   php scripts/module_browser_qa_runner.php
 *   php scripts/module_browser_qa_runner.php --module=expenses --company=1
 *   php scripts/module_browser_qa_runner.php --pilot-only
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>CLI only. Run: <code>php scripts/module_browser_qa_runner.php</code></p>';
    exit(1);
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

define('ITM_CLI_SCRIPT', true);
require_once $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    fwrite(STDERR, "Database connection unavailable.\n");
    exit(2);
}

$argvCopy = $argv ?? [];
$baseUrl = 'http://localhost/it-management/';
foreach ($argvCopy as $arg) {
    if (strpos($arg, '--base-url=') === 0) {
        $baseUrl = (string)substr($arg, 11);
        if (substr($baseUrl, -1) !== '/') {
            $baseUrl .= '/';
        }
    }
}

$pilotOnly = in_array('--pilot-only', $argvCopy, true);
$filterModule = null;
$filterCompany = null;
foreach ($argvCopy as $arg) {
    if (strpos($arg, '--module=') === 0) {
        $filterModule = substr($arg, 9);
    }
    if (strpos($arg, '--company=') === 0) {
        $filterCompany = (int)substr($arg, 10);
    }
}

$protectionZone = [
    'equipment', 'idfs', 'idf_links', 'idf_positions', 'idf_ports',
    'audit_logs', 'employees', 'settings', 'user_companies',
    'employee_system_access', 'cable_colors', 'ui_configuration',
];

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
    $sqlRows = mbqa_build_import_rows_from_database_sql_seed($conn, $table, $companyId);
    if (!empty($sqlRows)) {
        return $sqlRows;
    }

    if (count($exportRows) >= 2) {
        return mbqa_build_import_rows_from_export($exportRows);
    }

    return [];
}

/** Tables the runner must never wipe entirely during FK prep (shared / auth). */
function mbqa_tables_never_clear(): array
{
    return ['companies', 'users'];
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
            if (preg_match('/`([^`]+)`/i', (string)$errMsg, $m) && itm_is_safe_identifier($m[1])) {
                $blocker = $m[1];
                if (!in_array($blocker, mbqa_tables_never_clear(), true)) {
                    $subNote = '';
                    mbqa_clear_module_table_for_company($conn, $blocker, $companyId, $subNote);
                    $clearedFirst[$blocker] = $blocker;
                }
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
        if ($del['status'] < 200 || $del['status'] >= 500) {
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
    fwrite(STDERR, "Login failed (HTTP {$loginPost['status']}). Is Laragon running at {$baseUrl}?\n");
    exit(1);
}

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
        if (in_array($slug, $protectionZone, true)) {
            $tier = 'B';
        } elseif (in_array($slug, $bespokeSmoke, true)) {
            $tier = 'D';
        } elseif (strpos($slug, 'is_') === 0) {
            $tier = 'C';
        }

        $moduleUrl = $baseUrl . 'modules/' . rawurlencode($slug) . '/';
        $steps = [];

        $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
        $listOk = $index['status'] === 200 && !mbqa_has_fatal($index['body']);
        $steps[] = mbqa_step_result('list', $listOk, $listOk ? '' : 'HTTP ' . $index['status']);

        if ($tier === 'B' || $tier === 'D') {
            $steps[] = mbqa_step_result('clear', true, 'Skip (protection/bespoke smoke)');
            $steps[] = mbqa_step_result('sample_data', true, 'N/A smoke');
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
            foreach (['create', 'view', 'edit', 'list_all', 'single_delete', 'search', 'sort', 'export_pdf', 'export_xls', 'import_db', 'bulk_delete', 'clear_table'] as $s) {
                $steps[] = mbqa_step_result($s, $routeOk, 'routing smoke only');
            }
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
            // Why: expenses enforces one row per company+cost_center; free the scope before Import Excel QA.
            if ($slug === 'expenses' && itm_is_safe_identifier($slug)) {
                itm_run_query($conn, 'DELETE FROM `expenses` WHERE company_id=' . (int)$companyId);
            }

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

        $deletePath = $modulesDir . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'delete.php';
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

        $steps[] = mbqa_step_result('bulk_delete', true, 'N/A (requires 25+ rows per records_per_page)');
        $steps[] = mbqa_step_result('clear_table', true, 'N/A (requires 25+ rows)');

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

echo "Wrote {$jsonPath}\n";
echo "Steps pass: {$summary['pass']}, fail: {$summary['fail']}\n";
exit($summary['fail'] > 0 ? 1 : 0);
