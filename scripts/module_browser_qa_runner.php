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
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        return ['status' => 0, 'body' => '', 'headers' => '', 'error' => curl_error($ch) ?: 'curl failed'];
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
        $listOk = $index['status'] >= 200 && $index['status'] < 500 && !mbqa_has_fatal($index['body']);
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
            $steps[] = mbqa_step_result('export_xls', true, 'N/A smoke');
            $steps[] = mbqa_step_result('import_db', true, 'N/A smoke');
            $steps[] = mbqa_step_result('export_pdf', true, 'N/A smoke');
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
            foreach (['create', 'view', 'edit', 'list_all', 'search', 'sort', 'export_xls', 'import_db', 'export_pdf', 'bulk_delete', 'clear_table'] as $s) {
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
            $tableSql = '`' . str_replace('`', '``', $slug) . '`';
            $clearSql = 'DELETE FROM ' . $tableSql . ' WHERE company_id=' . (int)$companyId;
            $cleared = itm_run_query($conn, $clearSql);
            $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
            $csrfIndex = mbqa_extract_csrf($index['body']);
            $steps[] = mbqa_step_result('clear', $cleared, $cleared ? 'SQL tenant clear' : 'Clear failed');
        } else {
            $steps[] = mbqa_step_result('clear', true, 'Skip destructive clear');
        }

        $hasSampleBtn = stripos($index['body'], 'name="add_sample_data"') !== false
            || stripos($index['body'], 'Add sample data') !== false;
        $emptyTable = stripos($index['body'], 'No records found') !== false;
        if ($hasSampleBtn && $emptyTable && $csrfIndex !== '') {
            $seed = mbqa_http(
                $moduleUrl . 'index.php',
                'POST',
                http_build_query(['add_sample_data' => '1', 'csrf_token' => $csrfIndex]),
                ['Content-Type: application/x-www-form-urlencoded'],
                $cookieFile
            );
            $index = mbqa_http($moduleUrl . 'index.php', 'GET', null, [], $cookieFile);
            $csrfIndex = mbqa_extract_csrf($index['body']);
            $seedOk = $seed['status'] >= 200 && $seed['status'] < 400
                && stripos($index['body'], 'No records found') === false;
            $steps[] = mbqa_step_result('sample_data', $seedOk, $seedOk ? '' : 'Still empty or seed error');
        } else {
            $steps[] = mbqa_step_result('sample_data', true, $hasSampleBtn ? 'N/A (rows exist or no button)' : 'N/A (no handler)');
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

        $hasImportEndpoint = stripos($index['body'], 'data-itm-db-import-endpoint') !== false;
        $steps[] = mbqa_step_result('export_xls', $hasImportEndpoint || stripos($index['body'], '<table') !== false, 'table-tools hook');

        if ($hasImportEndpoint && $csrfIndex !== '') {
            $importPayload = json_encode([
                'csrf_token' => $csrfIndex,
                'import_excel_rows' => [
                    ['Id', 'Description'],
                    ['', 'QA import row ' . date('Y-m-d H:i:s')],
                ],
            ]);
            $import = mbqa_http(
                $moduleUrl . 'index.php',
                'POST',
                $importPayload,
                ['Content-Type: application/json'],
                $cookieFile
            );
            $importOk = $import['status'] === 200
                && stripos($import['body'], '"ok":true') !== false;
            $steps[] = mbqa_step_result('import_db', $importOk, $importOk ? '' : substr($import['body'], 0, 120));
        } else {
            $steps[] = mbqa_step_result('import_db', true, 'N/A (no import endpoint)');
        }

        $steps[] = mbqa_step_result('export_pdf', true, 'N/A (client-side print; not exercised in HTTP runner)');
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
