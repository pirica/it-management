<?php
/**
 * Lists modules/ folders that are not linked from the live sidebar catalog.
 *
 * Why: Sidebar discovery now merges base ui_config items, filesystem modules, and
 * modules_registry rows. This report flags module folders missing from sidebar match_dir,
 * sidebar entries missing module folders, and active registry rows without folders.
 *
 * Browser: Admin session (read-only report on load).
 * CLI: php scripts/list_modules_not_on_sidebar.php [--json]
 */

declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_list_modules_not_on_sidebar_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

/**
 * @param array<string, mixed> $report
 */
function itm_list_modules_not_on_sidebar_echo_summary(array $report, string $nl): void
{
    $summary = $report['summary'] ?? [];
    echo '--- Summary ---' . $nl;
    echo 'Modules with index.php: ' . (int) ($summary['modules_with_index'] ?? 0) . $nl;
    echo 'Sidebar catalog items: ' . (int) ($summary['sidebar_catalog_count'] ?? 0) . $nl;
    echo 'Sidebar match_dir entries: ' . (int) ($summary['sidebar_match_dir_count'] ?? 0) . $nl;
    echo 'Modules not on sidebar: ' . (int) ($summary['modules_not_on_sidebar'] ?? 0) . $nl;
    echo 'Sidebar entries missing module folder: ' . (int) ($summary['sidebar_missing_module'] ?? 0) . $nl;
    echo 'Registry active without module folder: ' . (int) ($summary['registry_without_module'] ?? 0)
        . ' (policy-hidden: ' . (int) ($summary['registry_without_module_policy'] ?? 0)
        . ', unexpected: ' . (int) ($summary['registry_without_module_unexpected'] ?? 0) . ')' . $nl;
    echo 'Policy-hidden module IDs: ' . implode(', ', $report['policy_hidden_module_ids'] ?? []) . $nl;
    echo '---------------' . $nl . $nl;
}

$report = itm_collect_modules_not_on_sidebar_report($conn);
$asJson = $itmIsCli
    ? in_array('--json', $argv ?? [], true)
    : isset($_GET['format']) && strtolower((string) $_GET['format']) === 'json';

if ($itmIsCli) {
    itm_script_output_begin('Modules not on sidebar');

    $nl = itm_script_output_nl();
    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . $nl;
        $hasIssues = ((int) ($report['summary']['modules_not_on_sidebar'] ?? 0))
            + ((int) ($report['summary']['sidebar_missing_module'] ?? 0))
            + ((int) ($report['summary']['registry_without_module_unexpected'] ?? 0));
        exit($hasIssues > 0 ? 1 : 0);
    }

    echo 'Modules / sidebar / registry audit' . $nl;
    echo str_repeat('=', 72) . $nl;
    itm_list_modules_not_on_sidebar_echo_summary($report, $nl);

    if ($report['modules_not_on_sidebar'] !== []) {
        echo 'MODULES NOT ON SIDEBAR' . $nl;
        echo str_repeat('-', 72) . $nl;
        printf('%-32s %-28s %s' . $nl, 'Module', 'CRUD table', 'Reason');
        foreach ($report['modules_not_on_sidebar'] as $row) {
            printf(
                '%-32s %-28s %s' . $nl,
                $row['module'],
                $row['crud_table'] ?? '(unknown)',
                $row['reason']
            );
        }
        echo $nl;
    }

    if ($report['sidebar_missing_module'] !== []) {
        echo 'SIDEBAR ENTRIES MISSING MODULE FOLDER' . $nl;
        echo str_repeat('-', 72) . $nl;
        printf('%-28s %-24s %s' . $nl, 'match_dir', 'sidebar_id', 'Reason');
        foreach ($report['sidebar_missing_module'] as $row) {
            printf(
                '%-28s %-24s %s' . $nl,
                $row['match_dir'],
                $row['sidebar_id'],
                $row['reason']
            );
        }
        echo $nl;
    }

    if ($report['registry_without_module'] !== []) {
        echo 'REGISTRY SLUGS WITHOUT MODULE FOLDER' . $nl;
        echo str_repeat('-', 72) . $nl;
        printf('%-28s %-28s %s' . $nl, 'module_slug', 'module_name', 'Reason');
        foreach ($report['registry_without_module'] as $row) {
            printf(
                '%-28s %-28s %s' . $nl,
                $row['module_slug'],
                $row['module_name'] !== '' ? $row['module_name'] : '-',
                $row['reason']
            );
        }
        echo $nl;
    }

    if ($report['modules_not_on_sidebar'] === []
        && $report['sidebar_missing_module'] === []
        && (int) ($report['summary']['registry_without_module_unexpected'] ?? 0) === 0) {
        echo 'No unexpected sidebar/module/registry gaps.' . $nl;
        if ($report['registry_without_module'] !== []) {
            echo 'Policy-hidden registry rows without folders are listed above for reference.' . $nl;
        }
    }

    $hasIssues = ((int) ($report['summary']['modules_not_on_sidebar'] ?? 0))
        + ((int) ($report['summary']['sidebar_missing_module'] ?? 0))
        + ((int) ($report['summary']['registry_without_module_unexpected'] ?? 0));
    exit($hasIssues > 0 ? 1 : 0);
}

if (!isset($company_id) || (int) $company_id <= 0) {
    http_response_code(401);
    exit('Login required. Sign in to the app, then open this script again.');
}

require_once ROOT_PATH . 'includes/itm_maintenance_script_admin_gate.php';
itm_enforce_maintenance_script_admin_browser($conn);

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/lib/script_browser_nav.php';
$itmListSidebarBaseUrl = defined('BASE_URL') ? (string) BASE_URL : '../';
$esc = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
        <h1 style="margin-top:0;">Modules / sidebar / registry audit</h1>
        <p class="report-muted">
            Compares <code>modules/*/index.php</code> folders to the live sidebar catalog from
            <code>itm_sidebar_structure()</code> (base <code>ui_config.php</code> items, filesystem discovery,
            and <code>modules_registry</code> merge). Also lists active registry rows without module folders.
        </p>
        <p class="report-muted">
            Modules with <code>index.php</code>: <strong><?php echo (int) $report['summary']['modules_with_index']; ?></strong> ·
            Sidebar <code>match_dir</code> entries: <strong><?php echo (int) $report['summary']['sidebar_match_dir_count']; ?></strong> ·
            Not on sidebar: <strong><?php echo (int) $report['summary']['modules_not_on_sidebar']; ?></strong> ·
            Sidebar missing folder: <strong><?php echo (int) $report['summary']['sidebar_missing_module']; ?></strong> ·
            Registry without folder: <strong><?php echo (int) $report['summary']['registry_without_module']; ?></strong>
            (unexpected: <strong><?php echo (int) $report['summary']['registry_without_module_unexpected']; ?></strong>)
        </p>
        <p class="report-muted">
            Policy-hidden IDs:
            <code><?php echo $esc(implode(', ', $report['policy_hidden_module_ids'])); ?></code>
        </p>
        <p>
            <a class="btn btn-sm" href="?format=json">JSON</a>
            <a class="btn btn-sm" href="../index.php">Home</a>
        </p>
    </div>

    <?php if ($report['modules_not_on_sidebar'] !== []): ?>
    <div class="report-card">
        <h2 style="margin-top:0;">Modules not on sidebar</h2>
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
                <?php foreach ($report['modules_not_on_sidebar'] as $row): ?>
                    <tr>
                        <td><?php echo itm_script_format_module_link((string) $row['module'], $itmListSidebarBaseUrl); ?></td>
                        <td><?php
                            $crudTable = (string) ($row['crud_table'] ?? '');
                            echo $crudTable !== '' ? itm_script_format_table_link($crudTable) : $esc('(unknown)');
                        ?></td>
                        <td><?php echo $esc($row['reason']); ?></td>
                        <td><?php echo itm_script_external_link_html('../modules/' . rawurlencode((string) $row['module']) . '/', 'modules/' . $row['module'] . '/'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($report['sidebar_missing_module'] !== []): ?>
    <div class="report-card">
        <h2 style="margin-top:0;">Sidebar entries missing module folder</h2>
        <table class="report-table">
            <thead>
                <tr>
                    <th>match_dir</th>
                    <th>sidebar_id</th>
                    <th>Label</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['sidebar_missing_module'] as $row): ?>
                    <tr>
                        <td><code><?php echo $esc($row['match_dir']); ?></code></td>
                        <td><code><?php echo $esc($row['sidebar_id']); ?></code></td>
                        <td><?php echo $esc($row['label']); ?></td>
                        <td><?php echo $esc($row['reason']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($report['registry_without_module'] !== []): ?>
    <div class="report-card">
        <h2 style="margin-top:0;">Registry slugs without module folder</h2>
        <table class="report-table">
            <thead>
                <tr>
                    <th>module_slug</th>
                    <th>module_name</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['registry_without_module'] as $row): ?>
                    <tr>
                        <td><code><?php echo $esc($row['module_slug']); ?></code></td>
                        <td><?php echo $esc($row['module_name'] !== '' ? $row['module_name'] : '-'); ?></td>
                        <td><?php echo $esc($row['reason']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($report['modules_not_on_sidebar'] === []
        && $report['sidebar_missing_module'] === []
        && (int) $report['summary']['registry_without_module_unexpected'] === 0): ?>
    <div class="report-card">
        <p class="report-muted" style="margin:0;">
            No unexpected sidebar/module/registry gaps.
            <?php if ($report['registry_without_module'] !== []): ?>
                Policy-hidden registry rows without folders are listed above for reference.
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
