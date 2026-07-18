<?php
/**
 * Lists modules/ folders that are not linked from the application sidebar.
 *
 * Why: auto-discovery adds CRUD modules for many database tables; some are internal
 * (floor plan folders/tags) and are intentionally hidden from navigation.
 *
 * Browser: open while logged in (read-only report on load).
 * CLI: php scripts/list_modules_not_on_sidebar.php [--json]
 */

declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';

/**
 * @return array{
 *   total_modules_with_index:int,
 *   sidebar_item_count:int,
 *   policy_hidden_ids:array<int,string>,
 *   not_on_sidebar:array<int,array{module:string,crud_table:?string,reason:string}>
 * }
 */
function itm_collect_modules_not_on_sidebar_report(): array
{
    $modulesRoot = ROOT_PATH . 'modules/';
    $sidebarCatalog = itm_sidebar_item_catalog();
    $sidebarIds = array_fill_keys(array_keys($sidebarCatalog), true);
    $policyHidden = array_fill_keys(itm_sidebar_excluded_module_ids(), true);

    $rows = [];
    $entries = scandir($modulesRoot) ?: [];
    sort($entries, SORT_NATURAL | SORT_FLAG_CASE);
    $totalWithIndex = 0;

    foreach ($entries as $moduleName) {
        if ($moduleName === '.' || $moduleName === '..') {
            continue;
        }
        if (!is_file($modulesRoot . $moduleName . '/index.php')) {
            continue;
        }
        $totalWithIndex++;

        if (isset($sidebarIds[$moduleName])) {
            continue;
        }

        $reason = 'not in sidebar catalog';
        if (isset($policyHidden[$moduleName])) {
            $reason = 'hidden by policy (internal/support table module)';
        }

        $rows[] = [
            'module' => $moduleName,
            'crud_table' => itm_read_module_crud_table_for_sidebar_audit($modulesRoot . $moduleName . '/index.php'),
            'reason' => $reason,
        ];
    }

    return [
        'total_modules_with_index' => $totalWithIndex,
        'sidebar_item_count' => count($sidebarCatalog),
        'policy_hidden_ids' => itm_sidebar_excluded_module_ids(),
        'not_on_sidebar' => $rows,
    ];
}

/**
 * @return string|null
 */
function itm_read_module_crud_table_for_sidebar_audit(string $indexPath): ?string
{
    if (!is_file($indexPath)) {
        return null;
    }
    $lines = @file($indexPath);
    if (!is_array($lines)) {
        return null;
    }
    foreach ($lines as $lineText) {
        if (preg_match('/\$crud_table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $lineText, $matches)) {
            return (string)$matches[1];
        }
    }
    return null;
}

$report = itm_collect_modules_not_on_sidebar_report();
$asJson = $itmIsCli
    ? in_array('--json', $argv ?? [], true)
    : isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json';

require_once __DIR__ . '/lib/script_cli_output.php';

if ($itmIsCli) {
    itm_script_output_begin();

    $nl = itm_script_output_nl();
    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . $nl;
        exit(0);
    }

    echo "Modules not listed on the sidebar" . $nl;
    echo str_repeat('=', 72) . $nl;
    echo 'Sidebar items: ' . (int)$report['sidebar_item_count'] . $nl;
    echo 'Policy-hidden module IDs: ' . implode(', ', $report['policy_hidden_ids']) . $nl . $nl;

    if ($report['not_on_sidebar'] === []) {
        echo "All modules with index.php appear on the sidebar." . $nl;
        exit(0);
    }

    printf("%-32s %-28s %s" . $nl, 'Module', 'CRUD table', 'Reason');
    echo str_repeat('-', 72) . $nl;
    foreach ($report['not_on_sidebar'] as $row) {
        printf(
            "%-32s %-28s %s" . $nl,
            $row['module'],
            $row['crud_table'] ?? '(unknown)',
            $row['reason']
        );
    }

    echo $nl . "Total not on sidebar: " . count($report['not_on_sidebar']) . $nl;
    exit(0);
}

if (!isset($company_id) || (int)$company_id <= 0) {
    http_response_code(401);
    exit('Login required. Sign in to the app, then open this script again.');
}

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/lib/script_browser_nav.php';
$itmListSidebarBaseUrl = defined('BASE_URL') ? (string)BASE_URL : '../';
$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modules not on sidebar</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .report-wrap { max-width: 1100px; margin: 0 auto; padding: 24px 20px 48px; }
        .report-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; padding: 18px 20px; margin-bottom: 16px; }
        .report-table { width: 100%; border-collapse: collapse; font-size: 0.94rem; }
        .report-table th, .report-table td { border: 1px solid var(--border-color, #d0d7de); padding: 10px 12px; text-align: left; vertical-align: top; }
        .report-table th { background: var(--table-header-bg, #f6f8fa); }
        .report-muted { color: var(--text-muted, #57606a); margin: 0 0 12px; line-height: 1.5; }
        code { font-size: 0.88rem; }
    </style>
</head>
<body>
<div class="report-wrap">
<?php itm_script_browser_nav_echo($itmListSidebarBaseUrl); ?>
    <div class="report-card">
        <h1 style="margin-top:0;">Modules not on the sidebar</h1>
        <p class="report-muted">
            Compares every <code>modules/*/index.php</code> folder to the live sidebar catalog from
            <code>includes/ui_config.php</code> (including policy-hidden internal modules such as floor plan folders/tags).
        </p>
        <p class="report-muted">
            Sidebar items: <strong><?php echo (int)$report['sidebar_item_count']; ?></strong> ·
            Modules with <code>index.php</code>: <strong><?php echo (int)$report['total_modules_with_index']; ?></strong> ·
            Not on sidebar: <strong><?php echo count($report['not_on_sidebar']); ?></strong>
        </p>
        <p class="report-muted">
            Policy-hidden IDs:
            <code><?php echo $esc(implode(', ', $report['policy_hidden_ids'])); ?></code>
        </p>
        <p>
            <a class="btn btn-sm" href="?format=json">JSON</a>
            <a class="btn btn-sm" href="../index.php">Home</a>
        </p>
    </div>

    <div class="report-card">
        <?php if ($report['not_on_sidebar'] === []): ?>
            <p class="report-muted" style="margin:0;">All modules with <code>index.php</code> appear on the sidebar.</p>
        <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>CRUD table</th>
                        <th>Reason</th>
                        <th>Path</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report['not_on_sidebar'] as $row): ?>
                        <tr>
                            <td><?php echo itm_script_format_module_link((string)$row['module'], $itmListSidebarBaseUrl); ?></td>
                            <td><?php
                                $crudTable = (string)($row['crud_table'] ?? '');
                                echo $crudTable !== '' ? itm_script_format_table_link($crudTable) : $esc('(unknown)');
                            ?></td>
                            <td><?php echo $esc($row['reason']); ?></td>
                            <td><?php echo itm_script_external_link_html('../modules/' . rawurlencode((string)$row['module']) . '/', 'modules/' . $row['module'] . '/'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

itm_script_output_end();
