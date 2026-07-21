<?php
/**
 * Lists modules_registry rows that are not in itm_qr_share_capable_module_slugs() (No share UI).
 *
 * Browser: Admin session — module names link to modules/{slug}/index.php when the folder exists.
 * CLI: php scripts/list_modules_without_share.php [--json] [--active-only]
 */

declare(strict_types=1);

$itmIsCli = PHP_SAPI === 'cli';

if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/itm_list_modules_without_share_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$activeOnly = $itmIsCli
    ? in_array('--active-only', $argv ?? [], true)
    : isset($_GET['active_only']) && (string)$_GET['active_only'] === '1';

$report = itm_collect_modules_without_share_report($conn, ['active_only' => $activeOnly]);
$asJson = $itmIsCli
    ? in_array('--json', $argv ?? [], true)
    : isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json';

if ($itmIsCli) {
    itm_script_output_begin('Modules without share');

    $nl = itm_script_output_nl();
    if ($asJson) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . $nl;
        exit(0);
    }

    $summary = $report['summary'];
    echo 'Modules without share UI (No share UI)' . $nl;
    echo str_repeat('=', 72) . $nl;
    echo 'Registry rows: ' . (int)$summary['registry_total'] . $nl;
    echo 'Share-capable slugs: ' . (int)$summary['capable_count'] . $nl;
    echo 'Without share: ' . (int)$summary['without_share_count'];
    if ($activeOnly) {
        echo ' (active registry only)';
    }
    echo $nl . str_repeat('-', 72) . $nl;

    if ($report['without_share'] === []) {
        echo 'No registry rows without share implementation.' . $nl;
        exit(0);
    }

    printf('%-28s %-32s %-8s %s' . $nl, 'Slug', 'Module name', 'Active', 'Module path');
    foreach ($report['without_share'] as $row) {
        $slug = (string)$row['module_slug'];
        $name = (string)$row['module_name'];
        if ($name === '') {
            $name = $slug;
        }
        $path = $row['has_module_folder']
            ? 'modules/' . $slug . '/index.php'
            : '—';
        printf(
            '%-28s %-32s %-8s %s' . $nl,
            $slug,
            mb_substr($name, 0, 32),
            ((int)$row['active'] === 1) ? 'yes' : 'no',
            $path
        );
    }

    exit(0);
}

if (!isset($company_id) || (int)$company_id <= 0) {
    http_response_code(401);
    exit('Login required. Sign in to the app, then open this script again.');
}

require_once ROOT_PATH . 'includes/itm_maintenance_script_admin_gate.php';
itm_enforce_maintenance_script_admin_browser($conn);

if ($asJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$itmListShareBaseUrl = defined('BASE_URL') ? (string)BASE_URL : '../';
$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$activeQuery = $activeOnly ? '1' : '0';
$toggleActiveUrl = '?active_only=' . ($activeOnly ? '0' : '1');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modules without share</title>
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
<?php itm_script_browser_nav_echo($itmListShareBaseUrl); ?>
    <div class="report-card">
        <h1 style="margin-top:0;">Modules without share UI</h1>
        <p class="report-muted">
            Registry rows whose <code>module_slug</code> is <strong>not</strong> in
            <code>itm_qr_share_capable_module_slugs()</code> — the same set that shows
            <span class="badge">No share UI</span> in <a href="../modules/share_modules/index.php">Share Modules</a>.
            Canonical capable inventory: <code>docs/CRUD_RECORD_SHARE.md</code>.
        </p>
        <p class="report-muted">
            Registry rows: <strong><?php echo (int)$report['summary']['registry_total']; ?></strong> ·
            Share-capable: <strong><?php echo (int)$report['summary']['capable_count']; ?></strong> ·
            Without share: <strong><?php echo (int)$report['summary']['without_share_count']; ?></strong>
            <?php if ($activeOnly): ?> (active only)<?php endif; ?>
        </p>
        <p>
            <a class="btn btn-sm" href="<?php echo $esc($toggleActiveUrl); ?>"><?php echo $activeOnly ? 'Show all registry rows' : 'Active registry only'; ?></a>
            <a class="btn btn-sm" href="?format=json&amp;active_only=<?php echo $esc($activeQuery); ?>">JSON</a>
            <a class="btn btn-sm" href="../modules/share_modules/index.php">Share Modules matrix</a>
        </p>
    </div>

    <?php if ($report['without_share'] !== []): ?>
    <div class="report-card">
        <table class="report-table">
            <thead>
                <tr>
                    <th>Module</th>
                    <th>Slug</th>
                    <th>Active</th>
                    <th>Folder</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report['without_share'] as $row): ?>
                    <?php
                    $slug = (string)$row['module_slug'];
                    $label = (string)$row['module_name'];
                    if ($label === '') {
                        $label = $slug;
                    }
                    ?>
                    <tr>
                        <td>
                            <?php if ($row['has_module_folder']): ?>
                                <?php echo itm_script_format_module_link($slug, $itmListShareBaseUrl, $label); ?>
                            <?php else: ?>
                                <?php echo $esc($label); ?>
                                <span class="report-muted"> (no <code>modules/<?php echo $esc($slug); ?>/</code>)</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo $esc($slug); ?></code></td>
                        <td><?php echo (int)$row['active'] === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                        <td>
                            <?php if ($row['has_module_folder']): ?>
                                <?php echo itm_script_external_link_html(itm_script_module_relative_href($slug), 'modules/' . $slug . '/'); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="report-card">
        <p class="report-muted" style="margin:0;">No registry rows without share implementation for the current filter.</p>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
