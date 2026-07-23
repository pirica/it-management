<?php
/**
 * CVE Feed Module — HTML list of latest NVD advisories.
 */

require_once __DIR__ . '/cve_feed_bootstrap.php';

$crud_title = 'CVE Feed';
$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);

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
    $cacheResult = cve_ensure_cache_for_ui(true);
    if (!empty($cacheResult['items'])) {
        $refreshNotice = 'CVE feed cache refreshed.';
    } else {
        $refreshErrors[] = 'Unable to refresh CVE feed from NVD. Please try again later.';
    }
} else {
    $cacheResult = cve_ensure_cache_for_ui(false);
}

$cveItems = is_array($cacheResult['items'] ?? null) ? $cacheResult['items'] : [];
$cacheStatus = (string)($cacheResult['status'] ?? 'ERROR');
$cacheAgeSeconds = cve_cache_age_seconds();

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

$feedUrl = rtrim((string)BASE_URL, '/') . '/modules/cve/feed.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($page_title); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($uiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
                <h1 style="margin:0;" title="CVE Feed">🛡️</h1>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="<?php echo sanitize($feedUrl); ?>" class="btn btn-sm" title="RSS feed" target="_blank" rel="noopener noreferrer">📡</a>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                        <button type="submit" name="refresh_cache" value="1" class="btn btn-sm btn-primary" title="Refresh from NVD">🔄</button>
                    </form>
                </div>
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
                    Latest <?php echo (int)CVE_RESULTS_PER_PAGE; ?> CVEs from the
                    <a href="https://nvd.nist.gov/" target="_blank" rel="noopener noreferrer">National Vulnerability Database</a>.
                    Cache status: <strong><?php echo sanitize($cacheStatus); ?></strong>.
                    Last updated: <strong><?php echo sanitize($cacheAgeLabel); ?></strong>.
                </p>
            </div>

            <div class="card" style="overflow:auto;">
                <table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                    <thead>
                        <tr>
                            <th>CVE ID</th>
                            <th>Severity</th>
                            <th>Score</th>
                            <th>Published</th>
                            <th>Description</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($cveItems === []): ?>
                        <tr>
                            <td colspan="6">No CVE data available. Use refresh to fetch from NVD.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cveItems as $cveItem): ?>
                            <?php
                            $publishedRaw = (string)($cveItem['published'] ?? '');
                            $publishedDisplay = $publishedRaw;
                            if ($publishedRaw !== '' && function_exists('itm_format_date_display')) {
                                $publishedDisplay = itm_format_date_display(substr($publishedRaw, 0, 10));
                            }
                            $description = (string)($cveItem['description'] ?? '');
                            if (strlen($description) > 220) {
                                $description = substr($description, 0, 217) . '...';
                            }
                            $nvdLink = (string)($cveItem['link'] ?? '');
                            ?>
                            <tr>
                                <td><?php echo sanitize((string)($cveItem['id'] ?? '')); ?></td>
                                <td><?php echo cve_severity_badge_html($cveItem['severity'] ?? ''); ?></td>
                                <td><?php echo sanitize($cveItem['base_score'] !== null ? (string)$cveItem['base_score'] : '—'); ?></td>
                                <td><?php echo sanitize($publishedDisplay !== '' ? $publishedDisplay : '—'); ?></td>
                                <td><?php echo sanitize($description !== '' ? $description : '—'); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <?php if ($nvdLink !== ''): ?>
                                        <a class="btn btn-sm" href="<?php echo sanitize($nvdLink); ?>" title="View on NVD" target="_blank" rel="noopener noreferrer">🔎</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php include '../../includes/footer.php'; ?>
    </div>
</div>
</body>
</html>
