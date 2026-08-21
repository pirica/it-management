<?php
/**
 * List modules that actually render decorated inline links (default blue styling).
 *
 * Why: Naive PHP source scans false-positive on dead scaffold branches inside
 * cr_render_cell_value(). This audit probes render output per $crud_table and scans
 * template HTML outside that function.
 *
 * Browser: [list_module_decorated_links.php?run=1](http://localhost/it-management/scripts/list_module_decorated_links.php?run=1)
 * CLI: php scripts/list_module_decorated_links.php [--module=slug] [--json]
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<strong>Admin login required.</strong> Open <a href="list_module_decorated_links.php?run=1" target="_blank" rel="nofollow noreferrer">list_module_decorated_links.php?run=1</a> · JSON: <code>?run=1&amp;format=json</code> · filter: <code>?module=problems</code><br>
CLI: <code>php scripts/list_module_decorated_links.php</code> · <code>--module=problems</code> · <code>--json</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$itmIsCli = PHP_SAPI === 'cli';
if ($itmIsCli) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/itm_module_decorated_links_report.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/script_browser_nav.php';

$root = dirname(__DIR__);
$nl = itm_script_output_nl();

$moduleSlug = '';
$asJson = false;

if ($itmIsCli) {
    foreach ($argv ?? [] as $arg) {
        if (strpos($arg, '--module=') === 0) {
            $moduleSlug = substr($arg, 9);
        } elseif ($arg === '--json') {
            $asJson = true;
        }
    }
} else {
    require_once __DIR__ . '/lib/itm_script_browser_usage.php';
    itm_script_browser_usage_maybe_gate(['title' => 'Module decorated links audit']);
    $moduleSlug = trim((string)($_GET['module'] ?? ''));
    $asJson = isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json';
}

$rows = itm_module_decorated_links_collect_report($root, [
    'module_slug' => $moduleSlug,
]);
$slugCounts = itm_module_decorated_links_summarize_by_slug($rows);
$groupedBySlug = itm_module_decorated_links_group_by_slug($rows);

if ($asJson) {
    $modulesJson = [];
    foreach ($slugCounts as $slug => $count) {
        $modulesJson[$slug] = [
            'count' => $count,
            'url' => itm_script_modules_repo_path_to_local_url('modules/' . $slug . '/index.php'),
        ];
    }
    if (!$itmIsCli) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'summary' => [
            'finding_count' => count($rows),
            'module_count' => count($slugCounts),
            'module_filter' => $moduleSlug,
        ],
        'modules' => $modulesJson,
        'findings' => $rows,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $nl;
    exit(0);
}

if ($itmIsCli) {
    itm_script_output_begin('Module decorated links audit');

    echo '[INFO] Module decorated links audit (inline <a> — not btn, not itm-plain-link, not sort color:inherit)' . $nl;
    echo '[INFO] Modules: ' . count($slugCounts) . $nl;
    if ($moduleSlug !== '') {
        echo '[INFO] Filter: module=' . $moduleSlug . $nl;
    }
    echo '[INFO] Open list_module_decorated_links.php?run=1 in the browser for clickable module links.' . $nl;
    echo $nl;

    foreach ($groupedBySlug as $slug => $findings) {
        echo '[INFO] link=' . $slug . $nl;
        foreach ($findings as $finding) {
            echo '[INFO]   ' . itm_module_decorated_links_format_finding_line($finding) . $nl;
        }
        echo $nl;
    }

    exit(0);
}

header('Content-Type: text/html; charset=utf-8');
$esc = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$baseUrl = defined('BASE_URL') ? (string)BASE_URL : '../';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Module decorated links audit</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .report-wrap { max-width: 1100px; margin: 0 auto; padding: 24px 20px 48px; }
        .report-card { background: var(--card-bg, #fff); border: 1px solid var(--border-color, #d0d7de); border-radius: 8px; padding: 18px 20px; margin-bottom: 16px; }
        .report-table { width: 100%; border-collapse: collapse; font-size: 0.94rem; }
        .report-table th, .report-table td { border: 1px solid var(--border-color, #d0d7de); padding: 10px 12px; text-align: left; vertical-align: top; }
        .report-table th { background: var(--table-header-bg, #f6f8fa); }
        .report-muted { color: var(--text-muted, #57606a); margin: 0 0 12px; line-height: 1.5; }
        code { font-size: 0.88rem; }
        .info-line { margin: 0 0 12px; font-family: Consolas, "Courier New", monospace; font-size: 0.94rem; }
    </style>
</head>
<body>
<div class="report-wrap">
<?php itm_script_browser_nav_echo($baseUrl); ?>
    <div class="report-card">
        <h1 style="margin-top:0;">Module decorated links audit</h1>
        <p class="report-muted">
            Inline <code>&lt;a&gt;</code> under <code>modules/**</code> that are not <code>btn</code>,
            not <code>itm-plain-link</code>, and not sort-header <code>color:inherit</code>.
        </p>
        <p class="report-muted">
            Modules: <strong><?php echo count($slugCounts); ?></strong>
            <?php if ($moduleSlug !== ''): ?> · module: <code><?php echo $esc($moduleSlug); ?></code><?php endif; ?>
        </p>
        <p>
            <a class="btn btn-sm" href="?run=1&amp;format=json<?php echo $moduleSlug !== '' ? '&amp;module=' . rawurlencode($moduleSlug) : ''; ?>">JSON</a>
        </p>
    </div>

    <?php if ($slugCounts !== []): ?>
    <div class="report-card">
        <?php foreach ($groupedBySlug as $slug => $findings): ?>
            <?php $moduleUrl = itm_script_modules_repo_path_to_local_url('modules/' . $slug . '/index.php'); ?>
            <p class="info-line">[INFO] link=<?php echo itm_script_external_link_html($moduleUrl, $slug); ?></p>
            <ul style="margin:0 0 16px 1.2rem;padding:0;list-style:disc;">
                <?php foreach ($findings as $finding): ?>
                    <li class="info-line" style="margin-bottom:6px;">[INFO] <?php echo $esc(itm_module_decorated_links_format_finding_line($finding)); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="report-card">
        <p class="report-muted" style="margin:0;">[INFO] No decorated links found.</p>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
