<?php
/**
 * Lists tenant-scoped tables with zero rows for the active session company.
 *
 * Browser: signed-in session; optional filter via ?company=N (defaults to session company_id). Admin gate on load.
 * CLI: php scripts/list_empty_tables.php [--company=N] [--json]
 *
 * Module links open modules/{table}/index.php in a new tab when that folder exists.
 */

declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli && !defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

/**
 * @return array<int, string>
 */
function itm_list_empty_tables_resolve_tenant_tables(mysqli $conn): array
{
    $tables = [];
    $res = mysqli_query($conn, 'SHOW TABLES');
    while ($res && ($row = mysqli_fetch_row($res))) {
        $tableName = (string)($row[0] ?? '');
        if ($tableName === '' || !itm_is_safe_identifier($tableName)) {
            continue;
        }
        if (!itm_table_has_column($conn, $tableName, 'company_id')) {
            continue;
        }
        $tables[] = $tableName;
    }
    sort($tables, SORT_NATURAL | SORT_FLAG_CASE);

    return $tables;
}

function itm_list_empty_tables_tenant_live_row_count(mysqli $conn, string $tableName, int $companyId): int
{
    if ($companyId <= 0 || !itm_is_safe_identifier($tableName)) {
        return -1;
    }

    $tableEsc = '`' . str_replace('`', '``', $tableName) . '`';
    $where = 'company_id = ' . (int)$companyId;
    if (itm_table_has_column($conn, $tableName, 'deleted_at')) {
        $where .= ' AND deleted_at IS NULL';
    }

    $sql = 'SELECT COUNT(*) AS row_count FROM ' . $tableEsc . ' WHERE ' . $where;
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return -1;
    }
    $row = mysqli_fetch_assoc($res);

    return isset($row['row_count']) ? (int)$row['row_count'] : -1;
}

/**
 * @return array{
 *   company_id:int,
 *   empty_tables:array<int,array{table:string,module_href:string,has_module:bool}>,
 *   scanned_tables:int,
 *   non_empty_tables:int
 * }
 */
function itm_list_empty_tables_collect_report(mysqli $conn, int $companyId, string $modulesRoot): array
{
    $emptyTables = [];
    $scanned = 0;
    $nonEmpty = 0;

    foreach (itm_list_empty_tables_resolve_tenant_tables($conn) as $tableName) {
        $scanned++;
        $rowCount = itm_list_empty_tables_tenant_live_row_count($conn, $tableName, $companyId);
        if ($rowCount !== 0) {
            if ($rowCount > 0) {
                $nonEmpty++;
            }
            continue;
        }

        $moduleIndex = rtrim($modulesRoot, '/\\') . DIRECTORY_SEPARATOR . $tableName . DIRECTORY_SEPARATOR . 'index.php';
        $hasModule = is_file($moduleIndex);
        $moduleHref = '../modules/' . rawurlencode($tableName) . '/index.php';

        $emptyTables[] = [
            'table' => $tableName,
            'module_href' => $moduleHref,
            'has_module' => $hasModule,
        ];
    }

    return [
        'company_id' => $companyId,
        'empty_tables' => $emptyTables,
        'scanned_tables' => $scanned,
        'non_empty_tables' => $nonEmpty,
    ];
}

if (defined('ITM_LIST_EMPTY_TABLES_LIB_ONLY')) {
    return;
}

$argvList = $GLOBALS['argv'] ?? [];
$asJson = $itmIsCli
    ? in_array('--json', $argvList, true)
    : isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json';

$sessionCompanyId = (int)($_SESSION['company_id'] ?? ($company_id ?? 0));
$companyId = 0;
if ($itmIsCli) {
    foreach ($argvList as $arg) {
        if (preg_match('/^--company=(\d+)$/', (string)$arg, $match)) {
            $companyId = (int)$match[1];
        }
    }
} elseif (isset($_GET['company']) && (string)$_GET['company'] !== '') {
    $companyId = (int)$_GET['company'];
}
if ($companyId <= 0) {
    $companyId = $sessionCompanyId;
}

if ($companyId <= 0) {
    $message = 'Company id is required. Sign in and select a company, or pass --company=N on CLI.';
    if ($itmIsCli) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    http_response_code(401);
    exit($message);
}

if (!$itmIsCli) {
    require_once ROOT_PATH . 'includes/itm_maintenance_script_admin_gate.php';
    itm_enforce_maintenance_script_admin_browser($conn);
}

$companyOptions = [];
$companyRes = mysqli_query($conn, 'SELECT id, company, incode FROM companies WHERE active = 1 ORDER BY company ASC');
while ($companyRes && ($companyRow = mysqli_fetch_assoc($companyRes))) {
    $companyOptions[] = $companyRow;
}
if ($companyId > 0 && !array_filter($companyOptions, static function (array $row) use ($companyId): bool {
    return (int)($row['id'] ?? 0) === $companyId;
})) {
    $singleRes = mysqli_query($conn, 'SELECT id, company, incode FROM companies WHERE id = ' . (int)$companyId . ' LIMIT 1');
    if ($singleRes && ($singleRow = mysqli_fetch_assoc($singleRes))) {
        $companyOptions[] = $singleRow;
    }
}

$modulesRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'modules';
$report = itm_list_empty_tables_collect_report($conn, $companyId, $modulesRoot);
$nl = itm_script_output_nl();

if ($itmIsCli) {
    itm_script_output_begin('Empty tenant tables');

    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $nl;
        exit($report['empty_tables'] === [] ? 0 : 0);
    }

    echo 'Company id : ' . $companyId . $nl;
    echo 'Scanned tables: ' . (int)$report['scanned_tables'] . $nl;
    echo 'Non-empty tables: ' . (int)$report['non_empty_tables'] . $nl;
    echo 'Empty tables: ' . count($report['empty_tables']) . $nl . $nl;

    foreach ($report['empty_tables'] as $row) {
        $line = $row['table'];
        if ($row['has_module']) {
            $line .= ' -> modules/' . $row['table'] . '/index.php';
        }
        echo $line . $nl;
    }

    itm_script_output_end();
    exit(0);
}

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/lib/script_browser_nav.php';
$baseUrl = defined('BASE_URL') ? (string)BASE_URL : '../';
$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Empty tenant tables</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .report-wrap { max-width: 960px; margin: 0 auto; padding: 24px 20px 48px; }
        .report-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; padding: 18px 20px; margin-bottom: 16px; }
        .report-table { width: 100%; border-collapse: collapse; font-size: 0.94rem; }
        .report-table th, .report-table td { border: 1px solid var(--border-color, #d0d7de); padding: 10px 12px; text-align: left; vertical-align: top; }
        .report-table th { background: var(--table-header-bg, #f6f8fa); }
        .report-muted { color: var(--text-muted, #57606a); margin: 0 0 12px; line-height: 1.5; }
        code { font-size: 0.88rem; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; margin-bottom: 12px; }
        .filter-row label { display: block; font-weight: 600; margin-bottom: 6px; }
        .filter-row select { min-width: 280px; padding: 8px 10px; border: 1px solid var(--border-color, #d0d7de); border-radius: 6px; }
    </style>
</head>
<body>
<div class="report-wrap">
<?php itm_script_browser_nav_echo($baseUrl); ?>
    <div class="report-card">
        <h1 style="margin-top:0;">Empty tenant tables</h1>
        <p class="report-muted"><strong>Company id : <?php echo (int)$report['company_id']; ?></strong><?php if ($sessionCompanyId > 0 && $sessionCompanyId !== (int)$report['company_id']): ?> · session company id : <?php echo (int)$sessionCompanyId; ?><?php endif; ?></p>
        <form class="filter-row" method="get" action="">
            <div>
                <label for="company">Company</label>
                <select name="company" id="company">
                    <?php foreach ($companyOptions as $companyRow): ?>
                        <?php $optionId = (int)($companyRow['id'] ?? 0); ?>
                        <option value="<?php echo $optionId; ?>"<?php echo $optionId === (int)$report['company_id'] ? ' selected' : ''; ?>>
                            <?php echo $esc((string)($companyRow['company'] ?? '') . ' (' . (string)($companyRow['incode'] ?? '') . ') [ID: ' . $optionId . ']'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-primary" title="Apply filter">Apply</button>
            </div>
        </form>
        <p class="report-muted">
            Scanned <code>company_id</code> tables: <strong><?php echo (int)$report['scanned_tables']; ?></strong> ·
            Non-empty: <strong><?php echo (int)$report['non_empty_tables']; ?></strong> ·
            Empty: <strong><?php echo count($report['empty_tables']); ?></strong>
            (live rows only when <code>deleted_at</code> exists).
        </p>
        <p>
            <a class="btn btn-sm" href="?company=<?php echo (int)$report['company_id']; ?>&amp;format=json">JSON</a>
            <a class="btn btn-sm" href="../index.php">Home</a>
        </p>
    </div>

    <div class="report-card">
        <?php if ($report['empty_tables'] === []): ?>
            <p class="report-muted" style="margin:0;">No empty tables for this company.</p>
        <?php else: ?>
        <table class="report-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Table</th>
                    <th>Module</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($report['empty_tables'] as $index => $row): ?>
                <tr>
                    <td><?php echo (int)$index + 1; ?></td>
                    <td><code><?php echo $esc($row['table']); ?></code></td>
                    <td>
                        <?php if ($row['has_module']): ?>
                            <a href="<?php echo $esc($row['module_href']); ?>" target="_blank" rel="noopener noreferrer">modules/<?php echo $esc($row['table']); ?>/index.php</a>
                        <?php else: ?>
                            <span class="report-muted">(no module folder)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
