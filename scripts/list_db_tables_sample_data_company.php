<?php
/**
 * Lists db/01_schema.sql tables with slug links, db/02_data_sample.sql coverage, and live tenant row counts per company.
 *
 * Why: Operators need the sample-data map scoped to a selected company (multi-tenant filter).
 *
 * Browser: Admin login; scripts/list_db_tables_sample_data_company.php?run=1&company=N
 * CLI: php scripts/list_db_tables_sample_data_company.php --company=1 [--sample=no] [--tenant=empty]
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<strong>Admin login required</strong> in browser. Open <a href="list_db_tables_sample_data_company.php?run=1&amp;company=1" target="_blank" rel="nofollow noreferrer">list_db_tables_sample_data_company.php?run=1&amp;company=1</a> or switch company with the dropdown. JSON: <code>?run=1&amp;company=1&amp;format=json</code>.<br> CLI: <code>php scripts/list_db_tables_sample_data_company.php --company=1</code> · Filters: <code>--sample=no</code> · <code>--tenant=empty</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';
require_once __DIR__ . '/lib/itm_database_tables_sample_data_company_report.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

/**
 * @return array<int, string>
 */
function ldtsc_allowed_sample_filters(): array
{
    return ['yes', 'no', 'exempt', 'n/a'];
}

/**
 * @return array<int, string>
 */
function ldtsc_allowed_tenant_filters(): array
{
    return ['empty', 'populated', 'n/a', 'error'];
}

function ldtsc_resolve_company_id(bool $isCli, int $sessionCompanyId): int
{
    $companyId = 0;
    if ($isCli) {
        foreach ($GLOBALS['argv'] ?? [] as $arg) {
            if (preg_match('/^--company=(\d+)$/', (string) $arg, $match)) {
                $companyId = (int) ($match[1] ?? 0);
            }
        }
    } elseif (isset($_GET['company']) && (string) $_GET['company'] !== '') {
        $companyId = (int) $_GET['company'];
    }

    if ($companyId <= 0) {
        $companyId = $sessionCompanyId;
    }

    return $companyId;
}

function ldtsc_resolve_sample_filter(bool $isCli): string
{
    $allowed = ldtsc_allowed_sample_filters();
    $raw = '';
    if ($isCli) {
        foreach ($GLOBALS['argv'] ?? [] as $arg) {
            if (preg_match('/^--sample=(.+)$/', (string) $arg, $match)) {
                $raw = strtolower(trim((string) ($match[1] ?? '')));
            }
        }
    } elseif (isset($_GET['sample'])) {
        $raw = strtolower(trim((string) $_GET['sample']));
    }

    if ($raw === '' || $raw === 'all') {
        return '';
    }

    return in_array($raw, $allowed, true) ? $raw : '';
}

function ldtsc_resolve_tenant_filter(bool $isCli): string
{
    $allowed = ldtsc_allowed_tenant_filters();
    $raw = '';
    if ($isCli) {
        foreach ($GLOBALS['argv'] ?? [] as $arg) {
            if (preg_match('/^--tenant=(.+)$/', (string) $arg, $match)) {
                $raw = strtolower(trim((string) ($match[1] ?? '')));
            }
        }
    } elseif (isset($_GET['tenant'])) {
        $raw = strtolower(trim((string) $_GET['tenant']));
    }

    if ($raw === '' || $raw === 'all') {
        return '';
    }

    return in_array($raw, $allowed, true) ? $raw : '';
}

/**
 * @param array<string, mixed> $report
 * @return array<string, mixed>
 */
function ldtsc_apply_filters(array $report, string $sampleFilter, string $tenantFilter): array
{
    if ($sampleFilter === '' && $tenantFilter === '') {
        return $report;
    }

    $tables = [];
    foreach ($report['tables'] ?? [] as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($sampleFilter !== '' && (string) ($row['sample_data'] ?? '') !== $sampleFilter) {
            continue;
        }
        if ($tenantFilter !== '' && (string) ($row['tenant_status'] ?? '') !== $tenantFilter) {
            continue;
        }
        $tables[] = $row;
    }

    $report['tables'] = $tables;
    if ($sampleFilter !== '') {
        $report['filtered_sample'] = $sampleFilter;
    }
    if ($tenantFilter !== '') {
        $report['filtered_tenant'] = $tenantFilter;
    }

    return $report;
}

/**
 * @return array<int, array<string, mixed>>
 */
function ldtsc_load_company_options(mysqli $conn, int $selectedCompanyId): array
{
    $options = [];
    $res = mysqli_query($conn, 'SELECT id, company, incode FROM companies WHERE active = 1 ORDER BY company ASC');
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $options[] = $row;
    }

    if ($selectedCompanyId > 0 && !array_filter($options, static function (array $row) use ($selectedCompanyId): bool {
        return (int) ($row['id'] ?? 0) === $selectedCompanyId;
    })) {
        $singleRes = mysqli_query(
            $conn,
            'SELECT id, company, incode FROM companies WHERE id = ' . (int) $selectedCompanyId . ' LIMIT 1'
        );
        if ($singleRes && ($singleRow = mysqli_fetch_assoc($singleRes))) {
            $options[] = $singleRow;
        }
    }

    return $options;
}

function ldtsc_company_label(mysqli $conn, int $companyId): string
{
    if ($companyId <= 0) {
        return '';
    }

    $res = mysqli_query(
        $conn,
        'SELECT company, incode FROM companies WHERE id = ' . (int) $companyId . ' LIMIT 1'
    );
    if (!$res || !($row = mysqli_fetch_assoc($res))) {
        return 'id=' . $companyId;
    }

    $name = trim((string) ($row['company'] ?? ''));
    $incode = trim((string) ($row['incode'] ?? ''));

    if ($name === '') {
        return 'id=' . $companyId;
    }

    return $incode !== '' ? $name . ' (' . $incode . ')' : $name;
}

/**
 * @param array<string, mixed> $report
 */
function ldtsc_print_cli_report(mysqli $conn, array $report): void
{
    $nl = itm_script_output_nl();
    $companyId = (int) ($report['company_id'] ?? 0);
    $summary = $report['summary'] ?? [];
    $tenantSummary = $report['tenant_summary'] ?? [];

    echo '[INFO] Company: ' . ldtsc_company_label($conn, $companyId) . ' (id=' . $companyId . ')' . $nl;
    echo '[INFO] Schema: ' . (string) ($report['schema_path'] ?? '') . ' (' . (int) ($report['table_count'] ?? 0) . ' tables)' . $nl;
    echo '[INFO] Sample file: ' . (string) ($report['sample_path'] ?? '') . $nl;
    echo '[INFO] Sample data summary: yes=' . (int) ($summary['yes'] ?? 0)
        . ' no=' . (int) ($summary['no'] ?? 0)
        . ' exempt=' . (int) ($summary['exempt'] ?? 0)
        . ' n/a=' . (int) ($summary['n/a'] ?? 0) . $nl;
    echo '[INFO] Tenant live rows (company_id=' . $companyId . '): populated='
        . (int) ($tenantSummary['populated'] ?? 0)
        . ' empty=' . (int) ($tenantSummary['empty'] ?? 0)
        . ' n/a=' . (int) ($tenantSummary['n/a'] ?? 0)
        . ' error=' . (int) ($tenantSummary['error'] ?? 0) . $nl;

    if (!empty($report['filtered_sample']) || !empty($report['filtered_tenant'])) {
        echo '[INFO] Filters:';
        if (!empty($report['filtered_sample'])) {
            echo ' sample_data=' . (string) $report['filtered_sample'];
        }
        if (!empty($report['filtered_tenant'])) {
            echo ' tenant=' . (string) $report['filtered_tenant'];
        }
        echo ' (' . count($report['tables'] ?? []) . ' row(s))' . $nl;
    }

    echo $nl . 'table | slug | sample_data | tenant | rows | module' . $nl;
    echo str_repeat('-', 110) . $nl;

    foreach ($report['tables'] ?? [] as $row) {
        $tableName = (string) ($row['table'] ?? '');
        $slug = (string) ($row['slug'] ?? '');
        $sampleData = (string) ($row['sample_data'] ?? '');
        $tenantStatus = (string) ($row['tenant_status'] ?? '');
        $tenantRows = $row['tenant_rows'] ?? null;
        $rowsLabel = $tenantRows === null ? '-' : (string) $tenantRows;
        $moduleLabel = $slug !== '' && !empty($row['has_module'])
            ? 'modules/' . $slug . '/index.php'
            : '-';

        printf(
            "%-28s | %-20s | %-7s | %-9s | %5s | %s" . $nl,
            $tableName,
            $slug !== '' ? $slug : '-',
            $sampleData,
            $tenantStatus,
            $rowsLabel,
            $moduleLabel
        );
    }
}

function ldtsc_sample_badge_class(string $status): string
{
    if ($status === 'yes') {
        return 'badge-success';
    }
    if ($status === 'no') {
        return 'badge-danger';
    }
    if ($status === 'exempt') {
        return 'badge-warning';
    }

    return 'badge-secondary';
}

function ldtsc_tenant_badge_class(string $status): string
{
    if ($status === 'populated') {
        return 'badge-success';
    }
    if ($status === 'empty') {
        return 'badge-danger';
    }
    if ($status === 'error') {
        return 'badge-warning';
    }

    return 'badge-secondary';
}

$sessionCompanyId = (int) ($_SESSION['company_id'] ?? ($company_id ?? 0));
$companyId = ldtsc_resolve_company_id($itmIsCli, $sessionCompanyId);

if ($companyId <= 0) {
    $message = 'Company id is required. Sign in and select a company, or pass --company=N on CLI.';
    if ($itmIsCli) {
        itm_script_write_stderr($message . PHP_EOL);
        exit(1);
    }
    http_response_code(401);
    exit($message);
}

if (!($conn instanceof mysqli)) {
    $message = 'Database connection unavailable.';
    if ($itmIsCli) {
        itm_script_write_stderr($message . PHP_EOL);
        exit(1);
    }
    http_response_code(503);
    exit($message);
}

if (!$itmIsCli) {
    require_once ROOT_PATH . 'includes/itm_maintenance_script_admin_gate.php';
    itm_enforce_maintenance_script_admin_browser($conn);
}

$schemaPath = itm_database_sql_schema_path();
$samplePath = itm_database_sql_sample_path();
$report = itm_database_tables_sample_data_company_report($conn, $schemaPath, $samplePath, $companyId);

$sampleFilter = ldtsc_resolve_sample_filter($itmIsCli);
$tenantFilter = ldtsc_resolve_tenant_filter($itmIsCli);
$report = ldtsc_apply_filters($report, $sampleFilter, $tenantFilter);

$cliArgv = $GLOBALS['argv'] ?? [];
$asJson = $itmIsCli
    ? in_array('--json', $cliArgv, true)
    : isset($_GET['format']) && strtolower((string) $_GET['format']) === 'json';

if ($itmIsCli) {
    itm_script_output_begin('db tables sample data by company');

    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . itm_script_output_nl();
        itm_script_output_end();
        exit(0);
    }

    ldtsc_print_cli_report($conn, $report);
    itm_script_output_end();
    exit(0);
}

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$companyOptions = ldtsc_load_company_options($conn, $companyId);
$summary = $report['summary'] ?? [];
$tenantSummary = $report['tenant_summary'] ?? [];
$baseUrl = defined('BASE_URL') ? (string) BASE_URL : '../';
$esc = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>db tables sample data by company</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .report-wrap { max-width: 1280px; margin: 0 auto; padding: 24px 20px 48px; }
        .report-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; padding: 18px 20px; margin-bottom: 16px; }
        .report-table-scroll { overflow-x: auto; max-width: 100%; }
        .report-table { width: max-content; min-width: 100%; border-collapse: collapse; font-size: 0.94rem; }
        .report-table th, .report-table td { border: 1px solid var(--border-color, #d0d7de); padding: 8px 10px; text-align: left; vertical-align: middle; white-space: nowrap; }
        .report-table th { background: var(--table-header-bg, #f6f8fa); }
        .report-muted { color: var(--text-muted, #57606a); margin: 0 0 12px; line-height: 1.5; }
        .report-summary { display: flex; flex-wrap: wrap; gap: 12px 20px; margin: 0 0 12px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-bottom: 12px; }
        .filter-row label { display: block; font-weight: 600; margin-bottom: 6px; }
        .filter-row select { min-width: 200px; padding: 8px 10px; border: 1px solid var(--border-color, #d0d7de); border-radius: 6px; }
    </style>
</head>
<body>
<div class="report-wrap">
<?php itm_script_browser_nav_echo($baseUrl); ?>
    <div class="report-card">
        <h1 style="margin-top:0;">db tables sample data by company</h1>
        <p class="report-muted">
            Combines <code>db/01_schema.sql</code> + <code>db/02_data_sample.sql</code> with live tenant row counts for the selected company.
            Module slug links open in a new tab.
        </p>
        <p class="report-muted"><strong><?php echo $esc(ldtsc_company_label($conn, $companyId)); ?></strong> · company id <strong><?php echo (int) $companyId; ?></strong></p>
        <div class="report-summary">
            <span>Tables: <strong><?php echo (int) ($report['table_count'] ?? 0); ?></strong></span>
            <span>sample yes: <strong><?php echo (int) ($summary['yes'] ?? 0); ?></strong></span>
            <span>tenant populated: <strong><?php echo (int) ($tenantSummary['populated'] ?? 0); ?></strong></span>
            <span>tenant empty: <strong><?php echo (int) ($tenantSummary['empty'] ?? 0); ?></strong></span>
        </div>
        <form class="filter-row" method="get" action="">
            <input type="hidden" name="run" value="1">
            <div>
                <label for="company">Company</label>
                <select name="company" id="company">
                    <?php foreach ($companyOptions as $companyRow): ?>
                        <?php $optionId = (int) ($companyRow['id'] ?? 0); ?>
                        <option value="<?php echo $optionId; ?>"<?php echo $optionId === $companyId ? ' selected' : ''; ?>>
                            <?php echo $esc((string) ($companyRow['company'] ?? '') . ' (' . (string) ($companyRow['incode'] ?? '') . ') [ID: ' . $optionId . ']'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="sample">Sample data</label>
                <select name="sample" id="sample">
                    <option value="all"<?php echo $sampleFilter === '' ? ' selected' : ''; ?>>All</option>
                    <?php foreach (ldtsc_allowed_sample_filters() as $option): ?>
                        <option value="<?php echo $esc($option); ?>"<?php echo $sampleFilter === $option ? ' selected' : ''; ?>><?php echo $esc($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tenant">Tenant rows</label>
                <select name="tenant" id="tenant">
                    <option value="all"<?php echo $tenantFilter === '' ? ' selected' : ''; ?>>All</option>
                    <?php foreach (ldtsc_allowed_tenant_filters() as $option): ?>
                        <option value="<?php echo $esc($option); ?>"<?php echo $tenantFilter === $option ? ' selected' : ''; ?>><?php echo $esc($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-primary" title="Apply filters">Apply</button>
            </div>
        </form>
        <p>
            <a class="btn btn-sm" href="?run=1&amp;company=<?php echo (int) $companyId; ?>&amp;format=json">JSON</a>
            <a class="btn btn-sm" href="../index.php">Home</a>
        </p>
    </div>

    <div class="report-card">
        <div class="report-table-scroll">
        <table class="report-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Table</th>
                    <th>Slug</th>
                    <th>Sample data</th>
                    <th>Tenant</th>
                    <th>Rows</th>
                    <th>Module</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($report['tables'] ?? [] as $index => $row): ?>
                <?php
                $tableName = (string) ($row['table'] ?? '');
                $slug = (string) ($row['slug'] ?? '');
                $sampleData = (string) ($row['sample_data'] ?? '');
                $tenantStatus = (string) ($row['tenant_status'] ?? '');
                $tenantRows = $row['tenant_rows'] ?? null;
                ?>
                <tr>
                    <td><?php echo (int) $index + 1; ?></td>
                    <td><code><?php echo $esc($tableName); ?></code></td>
                    <td>
                        <?php if ($slug !== '' && !empty($row['has_module'])): ?>
                            <?php echo itm_script_format_module_link($slug, $baseUrl, $slug); ?>
                        <?php elseif ($slug !== ''): ?>
                            <?php echo $esc($slug); ?>
                        <?php else: ?>
                            <span class="report-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?php echo $esc(ldtsc_sample_badge_class($sampleData)); ?>"><?php echo $esc($sampleData); ?></span></td>
                    <td><span class="badge <?php echo $esc(ldtsc_tenant_badge_class($tenantStatus)); ?>"><?php echo $esc($tenantStatus); ?></span></td>
                    <td><?php echo $tenantRows === null ? '—' : (int) $tenantRows; ?></td>
                    <td>
                        <?php if ($slug !== '' && !empty($row['has_module'])): ?>
                            <a href="<?php echo $esc(itm_script_module_relative_href($slug)); ?>" target="_blank" rel="noopener noreferrer">modules/<?php echo $esc($slug); ?>/index.php</a>
                        <?php else: ?>
                            <span class="report-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
</body>
</html>
