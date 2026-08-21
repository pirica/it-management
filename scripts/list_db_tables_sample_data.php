<?php
/**
 * Lists every CREATE TABLE in db/01_schema.sql with module slug links and db/02_data_sample.sql coverage.
 *
 * Why: Operators need a single read-only map of schema tables, module slugs, and Add sample data templates.
 *
 * Browser: Admin login; open scripts/list_db_tables_sample_data.php?run=1
 * CLI: php scripts/list_db_tables_sample_data.php [--json] [--sample=yes|no|exempt|n/a]
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<strong>Admin login required</strong> in browser. Open <a href="list_db_tables_sample_data.php?run=1" target="_blank" rel="nofollow noreferrer">list_db_tables_sample_data.php?run=1</a> or <a href="list_db_tables_sample_data.php?run=1&amp;format=json">?run=1&amp;format=json</a>.<br> CLI: <code>php scripts/list_db_tables_sample_data.php</code> · JSON: <code>--json</code> · Filter: <code>--sample=no</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';
require_once __DIR__ . '/lib/itm_database_tables_sample_data_report.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

/**
 * @return array<int, string>
 */
function ldtsd_allowed_sample_filters(): array
{
    return ['yes', 'no', 'exempt', 'n/a'];
}

function ldtsd_resolve_sample_filter(bool $isCli): string
{
    $allowed = ldtsd_allowed_sample_filters();
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

/**
 * @param array<string, mixed> $report
 * @return array<string, mixed>
 */
function ldtsd_filter_report(array $report, string $sampleFilter): array
{
    if ($sampleFilter === '') {
        return $report;
    }

    $tables = [];
    foreach ($report['tables'] as $row) {
        if ((string) ($row['sample_data'] ?? '') === $sampleFilter) {
            $tables[] = $row;
        }
    }
    $report['tables'] = $tables;
    $report['filtered_sample'] = $sampleFilter;

    return $report;
}

/**
 * @param array<string, mixed> $report
 */
function ldtsd_print_cli_report(array $report): void
{
    $nl = itm_script_output_nl();
    $summary = $report['summary'] ?? [];

    echo '[INFO] Schema: ' . (string) ($report['schema_path'] ?? '') . ' (' . (int) ($report['table_count'] ?? 0) . ' tables)' . $nl;
    echo '[INFO] Sample file: ' . (string) ($report['sample_path'] ?? '') . $nl;
    echo '[INFO] Sample data summary: yes=' . (int) ($summary['yes'] ?? 0)
        . ' no=' . (int) ($summary['no'] ?? 0)
        . ' exempt=' . (int) ($summary['exempt'] ?? 0)
        . ' n/a=' . (int) ($summary['n/a'] ?? 0) . $nl;

    if (!empty($report['filtered_sample'])) {
        echo '[INFO] Filter: sample_data=' . (string) $report['filtered_sample']
            . ' (' . count($report['tables']) . ' row(s))' . $nl;
    }

    echo $nl . 'table | slug | sample_data | module' . $nl;
    echo str_repeat('-', 100) . $nl;

    foreach ($report['tables'] as $row) {
        $tableName = (string) ($row['table'] ?? '');
        $slug = (string) ($row['slug'] ?? '');
        $sampleData = (string) ($row['sample_data'] ?? '');
        $moduleLabel = $slug !== '' && !empty($row['has_module'])
            ? 'modules/' . $slug . '/index.php'
            : '-';

        printf(
            "%-32s | %-24s | %-7s | %s" . $nl,
            $tableName,
            $slug !== '' ? $slug : '-',
            $sampleData,
            $moduleLabel
        );
    }
}

function ldtsd_sample_badge_class(string $status): string
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

$schemaPath = itm_database_sql_schema_path();
$samplePath = itm_database_sql_sample_path();
$report = itm_database_tables_sample_data_report($schemaPath, $samplePath);

$sampleFilter = ldtsd_resolve_sample_filter($itmIsCli);
$report = ldtsd_filter_report($report, $sampleFilter);

$cliArgv = $GLOBALS['argv'] ?? [];
$asJson = $itmIsCli
    ? in_array('--json', $cliArgv, true)
    : isset($_GET['format']) && strtolower((string) $_GET['format']) === 'json';

if ($itmIsCli) {
    itm_script_output_begin('db tables and sample data');

    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . itm_script_output_nl();
        itm_script_output_end();
        exit(0);
    }

    ldtsd_print_cli_report($report);
    itm_script_output_end();
    exit(0);
}

if (!isset($company_id) || (int) $company_id <= 0) {
    http_response_code(401);
    exit('Login required. Sign in to the app, then open this script again.');
}

require_once ROOT_PATH . 'includes/itm_maintenance_script_admin_gate.php';
itm_enforce_maintenance_script_admin_browser($conn);

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$esc = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$summary = $report['summary'] ?? [];
$baseUrl = defined('BASE_URL') ? (string) BASE_URL : '../';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>db tables and sample data</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .report-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px 48px; }
        .report-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; padding: 18px 20px; margin-bottom: 16px; }
        .report-table-scroll { overflow-x: auto; max-width: 100%; }
        .report-table { width: max-content; min-width: 100%; border-collapse: collapse; font-size: 0.94rem; }
        .report-table th, .report-table td { border: 1px solid var(--border-color, #d0d7de); padding: 8px 10px; text-align: left; vertical-align: middle; white-space: nowrap; }
        .report-table th { background: var(--table-header-bg, #f6f8fa); }
        .report-muted { color: var(--text-muted, #57606a); margin: 0 0 12px; line-height: 1.5; }
        .report-summary { display: flex; flex-wrap: wrap; gap: 12px 20px; margin: 0 0 12px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-bottom: 12px; }
        .filter-row label { display: block; font-weight: 600; margin-bottom: 6px; }
        .filter-row select { min-width: 180px; padding: 8px 10px; border: 1px solid var(--border-color, #d0d7de); border-radius: 6px; }
    </style>
</head>
<body>
<div class="report-wrap">
<?php itm_script_browser_nav_echo($baseUrl); ?>
    <div class="report-card">
        <h1 style="margin-top:0;">db tables and sample data</h1>
        <p class="report-muted">
            Parses <code>db/01_schema.sql</code> and <code>db/02_data_sample.sql</code> (no live MySQL required).
            <strong>sample_data</strong>: <code>yes</code> = template row in <code>02_data_sample.sql</code>;
            <code>exempt</code> = skipped by <code>itm_sample_sql_exempt_tables()</code>;
            <code>n/a</code> = no <code>company_id</code> column;
            <code>no</code> = tenant table missing a template.
        </p>
        <div class="report-summary">
            <span>Tables: <strong><?php echo (int) ($report['table_count'] ?? 0); ?></strong></span>
            <span>yes: <strong><?php echo (int) ($summary['yes'] ?? 0); ?></strong></span>
            <span>no: <strong><?php echo (int) ($summary['no'] ?? 0); ?></strong></span>
            <span>exempt: <strong><?php echo (int) ($summary['exempt'] ?? 0); ?></strong></span>
            <span>n/a: <strong><?php echo (int) ($summary['n/a'] ?? 0); ?></strong></span>
        </div>
        <form class="filter-row" method="get" action="">
            <input type="hidden" name="run" value="1">
            <div>
                <label for="sample">Sample data filter</label>
                <select name="sample" id="sample">
                    <option value="all"<?php echo $sampleFilter === '' ? ' selected' : ''; ?>>All</option>
                    <?php foreach (ldtsd_allowed_sample_filters() as $option): ?>
                        <option value="<?php echo $esc($option); ?>"<?php echo $sampleFilter === $option ? ' selected' : ''; ?>><?php echo $esc($option); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-primary" title="Apply filter">Apply</button>
            </div>
        </form>
        <p>
            <a class="btn btn-sm" href="?run=1&amp;format=json">JSON</a>
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
                    <th>Module</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($report['tables'] as $index => $row): ?>
                <?php
                $tableName = (string) ($row['table'] ?? '');
                $slug = (string) ($row['slug'] ?? '');
                $sampleData = (string) ($row['sample_data'] ?? '');
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
                    <td><span class="badge <?php echo $esc(ldtsd_sample_badge_class($sampleData)); ?>"><?php echo $esc($sampleData); ?></span></td>
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
