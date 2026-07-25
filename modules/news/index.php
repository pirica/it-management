<?php
/**
 * News module — multi-source feed reader (NVD CVE, Microsoft blogs).
 */

require_once __DIR__ . '/news_feed_bootstrap.php';

$crud_title = 'News';
$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$sourceId = trim((string)($_GET['source'] ?? 'nvd_cve'));
$feedSources = news_feed_source_catalog();
$activeSource = news_resolve_feed_source($sourceId);

$uiConfig = function_exists('itm_get_ui_configuration')
    ? itm_get_ui_configuration($conn, $company_id, $employee_id > 0 ? $employee_id : null)
    : [];

if (($uiConfig['enable_all_error_reporting'] ?? 0) == 1) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
}

$refreshNotice = '';
$refreshErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_cache'])) {
    itm_require_post_csrf();
    $postedSource = trim((string)($_POST['source'] ?? $sourceId));
    $cacheResult = news_ensure_cache_for_ui($postedSource, true);
    if (!empty($cacheResult['items'])) {
        $refreshNotice = 'Feed cache refreshed.';
    } else {
        $refreshErrors[] = 'Unable to refresh the selected feed. Please try again later.';
    }
    $activeSource = $cacheResult['source'];
    $sourceId = (string)$activeSource['id'];
} else {
    $cacheResult = news_ensure_cache_for_ui($sourceId, false);
    $activeSource = $cacheResult['source'];
    $sourceId = (string)$activeSource['id'];
}

$feedItems = is_array($cacheResult['items'] ?? null) ? $cacheResult['items'] : [];
$cacheStatus = (string)($cacheResult['status'] ?? 'ERROR');
$cacheAgeSeconds = news_cache_age_seconds($sourceId);
$showCvss = !empty($activeSource['show_cvss']);
$titleColumn = (string)($activeSource['title_column'] ?? 'Title');

$cacheAgeLabel = 'Not available';
if ($cacheAgeSeconds !== null) {
    if ($cacheAgeSeconds < 3600) {
        $cacheAgeLabel = max(1, (int)round($cacheAgeSeconds / 60)) . ' minute(s) ago';
    } elseif ($cacheAgeSeconds < 86400) {
        $cacheAgeLabel = max(1, (int)round($cacheAgeSeconds / 3600)) . ' hour(s) ago';
    } else {
        $cacheAgeLabel = max(1, (int)round($cacheAgeSeconds / 86400)) . ' day(s) ago';
    }
}

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$resolvedModuleIcon = itm_resolve_module_sidebar_icon($conn, $company_id, $employee_id, $moduleSlug);
$cleanTitle = itm_module_access_strip_catalog_label_prefix($crud_title);
$page_title = trim($resolvedModuleIcon . ' ' . $cleanTitle);
$feedUrl = news_feed_self_url($sourceId);
$activeEmoji = (string)($activeSource['emoji'] ?? '📰');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($page_title); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($uiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .news-container { display: flex; height: calc(100vh - 120px); background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .news-sidebar { width: 280px; background: var(--bg-secondary); border-right: 1px solid var(--border); padding: 20px 16px; display: flex; flex-direction: column; gap: 16px; }
        .news-sidebar-title { font-weight: 600; margin: 0 0 4px; color: var(--text-primary); }
        .news-sidebar .form-group { margin: 0; }
        .news-sidebar select { width: 100%; }
        .news-sidebar-meta { font-size: 13px; color: var(--text-secondary); line-height: 1.5; }
        .news-sidebar-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: auto; padding-top: 16px; border-top: 1px solid var(--border); }
        .news-content { flex: 1; padding: 24px 32px; overflow-y: auto; }
        .news-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
        .news-more-footer { margin-top: 12px; text-align: center; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        @media (max-width: 768px) {
            .news-container { flex-direction: column; height: auto; min-height: calc(100vh - 120px); }
            .news-sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--border); }
            .news-content { padding: 16px; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="news-container">
                <aside class="news-sidebar" aria-label="Feed sources">
                    <div>
                        <p class="news-sidebar-title">📰</p>
                        <p class="news-sidebar-meta" title="News feeds">News feeds</p>
                    </div>
                    <form method="GET" class="form-group">
                        <label for="news-source-select">Source</label>
                        <select id="news-source-select" name="source" class="form-control" onchange="this.form.submit()">
                            <?php foreach ($feedSources as $feedSource): ?>
                                <option value="<?php echo sanitize((string)$feedSource['id']); ?>" <?php echo ((string)$feedSource['id'] === $sourceId) ? 'selected' : ''; ?>>
                                    <?php echo sanitize((string)$feedSource['emoji'] . ' ' . $feedSource['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <p class="news-sidebar-meta">
                        <?php echo sanitize((string)$activeSource['description']); ?>
                    </p>
                    <div class="news-sidebar-actions">
                        <?php if ((string)($activeSource['site_link'] ?? '') !== ''): ?>
                            <a href="<?php echo sanitize((string)$activeSource['site_link']); ?>" class="btn btn-sm" title="Source website" target="_blank" rel="noopener noreferrer">Source</a>
                        <?php endif; ?>
                        <a href="<?php echo sanitize($feedUrl); ?>" class="btn btn-sm" title="RSS feed" target="_blank" rel="noopener noreferrer">📡</a>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                            <input type="hidden" name="source" value="<?php echo sanitize($sourceId); ?>">
                            <button type="submit" name="refresh_cache" value="1" class="btn btn-sm btn-primary" title="Refresh feed">🔄</button>
                        </form>
                    </div>
                </aside>

                <div class="news-content">
                    <div class="news-toolbar">
                        <h1 style="margin:0;" title="<?php echo sanitize((string)$activeSource['label']); ?>"><?php echo sanitize($activeEmoji); ?></h1>
                    </div>

                    <?php if ($refreshNotice !== ''): ?>
                        <div class="card" style="margin-bottom:12px;border-left:4px solid var(--success-color,#28a745);">
                            <p style="margin:0;"><?php echo sanitize($refreshNotice); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($refreshErrors)): ?>
                        <div class="card" style="margin-bottom:12px;border-left:4px solid var(--danger-color,#dc3545);">
                            <?php foreach ($refreshErrors as $refreshError): ?>
                                <p style="margin:0;"><?php echo sanitize($refreshError); ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="card" style="margin-bottom:12px;">
                        <p style="margin:0;" class="muted">
                            Showing up to <?php echo (int)NEWS_RESULTS_PER_PAGE; ?> items from
                            <a href="<?php echo sanitize((string)$activeSource['site_link']); ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize((string)$activeSource['label']); ?></a>.
                            Cache status: <strong><?php echo sanitize($cacheStatus); ?></strong>.
                            Last updated: <strong><?php echo sanitize($cacheAgeLabel); ?></strong>.
                        </p>
                    </div>

                    <div class="card" style="overflow:auto;">
                        <table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                            <thead>
                                <tr>
                                    <th><?php echo sanitize($titleColumn); ?></th>
                                    <?php if ($showCvss): ?>
                                        <th>Severity</th>
                                        <th>Score</th>
                                    <?php endif; ?>
                                    <th>Published</th>
                                    <th>Description</th>
                                    <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($feedItems === []): ?>
                                <tr>
                                    <td colspan="<?php echo $showCvss ? 6 : 4; ?>">No feed data available. Use refresh to fetch the latest items.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($feedItems as $feedItem): ?>
                                    <?php
                                    $publishedRaw = (string)($feedItem['published'] ?? '');
                                    $publishedDisplay = $publishedRaw;
                                    if ($publishedRaw !== '' && function_exists('itm_format_date_display')) {
                                        $parsedTs = strtotime($publishedRaw);
                                        if ($parsedTs !== false) {
                                            $publishedDisplay = itm_format_date_display(date('Y-m-d', $parsedTs));
                                        }
                                    }
                                    $description = (string)($feedItem['description'] ?? '');
                                    if (strlen($description) > 220) {
                                        $description = substr($description, 0, 217) . '...';
                                    }
                                    $itemLink = (string)($feedItem['link'] ?? '');
                                    $itemTitle = (string)($feedItem['title'] ?? $feedItem['id'] ?? '');
                                    ?>
                                    <tr>
                                        <td><?php echo sanitize($itemTitle); ?></td>
                                        <?php if ($showCvss): ?>
                                            <td><?php echo news_severity_badge_html($feedItem['severity'] ?? ''); ?></td>
                                            <td><?php echo sanitize($feedItem['base_score'] !== null ? (string)$feedItem['base_score'] : '—'); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo sanitize($publishedDisplay !== '' ? $publishedDisplay : '—'); ?></td>
                                        <td><?php echo sanitize($description !== '' ? $description : '—'); ?></td>
                                        <td class="itm-actions-cell" data-itm-actions-origin="1">
                                            <?php if ($itemLink !== ''): ?>
                                                <a class="btn btn-sm" href="<?php echo sanitize($itemLink); ?>" title="Open article" target="_blank" rel="noopener noreferrer">🔎</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ((string)($activeSource['site_link'] ?? '') !== ''): ?>
                        <div class="news-more-footer">
                            <a href="<?php echo sanitize((string)$activeSource['site_link']); ?>" class="btn btn-sm" title="More news" target="_blank" rel="noopener noreferrer">More news</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
</div>
</body>
</html>
