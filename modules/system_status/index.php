<?php
/**
 * System Status Module - Index
 *
 * Admin dashboard: Monitoring, PHP Settings, and Database tabs read cached JSON
 * from system_status; Refresh collects live metrics and upserts the cache.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/itm_system_status_cache.php';

// Authorization check - Admin only
if (!isset($_SESSION['employee_id']) || !itm_is_admin($conn, $_SESSION['employee_id'])) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$active_tab = $_GET['tab'] ?? 'monitoring';
$allowed_tabs = ['monitoring', 'php_settings', 'database'];
if (!in_array($active_tab, $allowed_tabs)) {
    $active_tab = 'monitoring';
}

$refreshErrors = [];
$refreshNotice = '';
$sessionCompanyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
$cacheCompanyId = $sessionCompanyId;
if ($cacheCompanyId <= 0) {
    if (defined('SYSTEM_STATUS_DISABLE_TENANT_FALLBACK') && SYSTEM_STATUS_DISABLE_TENANT_FALLBACK) {
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
    // Why: Admin diagnostics remain usable before company selection; cache key fallback only (admin gate above).
    $correlationId = itm_system_status_make_correlation_id();
    error_log(
        'system_status: cache company_id fallback to '
        . ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID
        . ' (session company_id missing/invalid). correlation_id='
        . $correlationId
    );
    $cacheCompanyId = ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_cache'])) {
    itm_require_post_csrf();
    $refreshCompanyId = $cacheCompanyId;
    $refreshResult = itm_system_status_refresh_all($conn, $refreshCompanyId);
    if ($refreshResult['ok']) {
        $refreshNotice = 'Cache refreshed for all tabs.';
    } else {
        $refreshErrors = $refreshResult['errors'];
    }
}

$ssCache = itm_system_status_cache_get($conn, $active_tab, $cacheCompanyId);
if ($ssCache === null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Why: First visit should populate cache so tabs are not empty until manual Refresh.
    itm_system_status_refresh_tab($conn, $active_tab, $cacheCompanyId);
    $ssCache = itm_system_status_cache_get($conn, $active_tab, $cacheCompanyId);
}

$ssPayload = is_array($ssCache['payload'] ?? null) ? $ssCache['payload'] : null;
$ssRefreshedAt = isset($ssCache['refreshed_at']) ? (string)$ssCache['refreshed_at'] : null;
$ssRefreshedDisplay = '';
if ($ssRefreshedAt !== null && $ssRefreshedAt !== '') {
    $refreshedDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ssRefreshedAt);
    if ($refreshedDt instanceof DateTimeImmutable) {
        $ssRefreshedDisplay = $refreshedDt->format('d/m/Y H:i');
    }
}

$page_title = 'System Status';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo sanitize($page_title); ?> - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <script src="../../js/vendor/chart.js"></script>
    <style>
        .status-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; flex-wrap: wrap; }
        .status-tab { padding: 8px 16px; text-decoration: none; color: var(--text-primary); border-radius: 6px; white-space: nowrap; flex-shrink: 0; font-weight: 500; }
        .status-tab.active { background: var(--accent); color: #fff; font-weight: 600; }
        .status-tab:hover:not(.active) { background: var(--bg-secondary); }
        .refresh-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px; }
        .refresh-toolbar h1 { margin: 0; font-size: clamp(1.25rem, 4vw, 1.5rem); }
        .refresh-toolbar-meta { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .ss-cache-meta { font-size: 0.875rem; color: var(--text-secondary); }
        .ss-form-inline { margin: 0; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr)); gap: 16px; }
        .metrics-stack { display: flex; flex-direction: column; gap: 16px; }
        .ss-metric-span-wide { grid-column: 1 / -1; }
        .ss-metric-span-full { grid-column: 1 / -1; }
        .ss-disk-grid { grid-template-columns: repeat(auto-fill, minmax(min(100%, 160px), 1fr)); }
        .ss-storage-section { margin-top: 16px; }
        .ss-section-intro { margin-top: 0; }
        .ss-error-msg { color: #a52727; }
        .ss-phpinfo-actions { margin-top: 12px; }
        .ss-extension-item { padding: 2px 0; }
        .ss-note-spaced { margin-top: 12px; }
        .ss-metric-block { padding: 10px 0; }
        .ss-metric-block-lg { padding: 20px 0; }
        .ss-table-num { text-align: right; }
        .ss-extensions-list { max-height: 320px; overflow: auto; }
        .ss-extensions-columns { column-count: 1; gap: 20px; margin: 0; padding: 0; list-style: none; font-size: 0.85rem; }
        .metric-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 16px; min-width: 0; }
        .metric-card h3 { margin-top: 0; margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px; }
        .metric-value { font-size: 1.25rem; font-weight: 700; }
        .metric-label { font-size: 0.875rem; color: var(--text-secondary); }
        .gauge-container { width: min(200px, 100%); max-width: 100%; margin: 0 auto; }
        .progress-bar-container { height: 8px; background: #f0f3f6; border-radius: 4px; margin: 8px 0; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: #17a2b8; border-radius: 4px; }
        .info-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .info-table td, .info-table th { padding: 8px 0; border-bottom: 1px solid var(--border-subtle); vertical-align: top; }
        .info-table td:first-child, .info-table th:first-child { font-weight: 600; width: 34%; color: var(--text-secondary); padding-right: 12px; }
        .info-table td:last-child, .info-table th:last-child { word-break: break-all; overflow-wrap: anywhere; }
        .info-table .ss-path-value { display: block; font-size: 0.85rem; line-height: 1.45; }
        .audit-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
        .status-running { background: #e8f8ee; color: #18794e; border: 1px solid #9cd8b1; }
        .status-stopped { background: #fdecec; color: #a52727; border: 1px solid #f0b6b6; }
        .ss-storage-details { margin: 8px 0; border: 1px solid var(--border-subtle); border-radius: 6px; padding: 8px 10px; background: var(--bg-secondary); }
        .ss-storage-summary { cursor: pointer; list-style: none; display: grid; grid-template-columns: minmax(120px, 1.2fr) auto; gap: 4px 12px; align-items: start; }
        .ss-storage-summary::-webkit-details-marker { display: none; }
        .ss-storage-summary::before { content: '▸'; margin-right: 8px; color: var(--text-secondary); }
        .ss-storage-details[open] > .ss-storage-summary::before { content: '▾'; }
        .ss-storage-label { font-weight: 600; }
        .ss-storage-meta { color: var(--text-secondary); font-size: 0.85rem; }
        .ss-storage-path { display: block; grid-column: 1 / -1; font-size: 0.8rem; color: var(--text-secondary); word-break: break-all; overflow-wrap: anywhere; margin-top: 2px; }
        .ss-storage-leaf { display: grid; grid-template-columns: minmax(120px, 1.2fr) auto; gap: 4px 12px; padding: 8px 0; border-bottom: 1px solid var(--border-subtle); }
        .ss-storage-leaf:last-child { border-bottom: 0; }
        .ss-storage-children { margin-top: 8px; }
        .ss-storage-total { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); font-weight: 700; }
        @media (min-width: 768px) {
            .ss-extensions-columns { column-count: 2; }
            .ss-metric-span-wide { grid-column: span 2; }
        }
        @media (min-width: 1024px) {
            .ss-extensions-columns { column-count: 3; }
            .ss-metric-span-full { grid-column: span 3; }
        }
        @media (max-width: 575px) {
            .info-table td:first-child, .info-table th:first-child { width: auto; }
            .ss-storage-summary, .ss-storage-leaf { grid-template-columns: 1fr; }
            .ss-storage-meta { word-break: break-word; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
            <div class="refresh-toolbar">
                <h1><?php echo sanitize($page_title); ?></h1>
                <div class="refresh-toolbar-meta">
                    <?php if ($ssRefreshedDisplay !== ''): ?>
                        <span class="ss-cache-meta">Last refreshed: <?php echo sanitize($ssRefreshedDisplay); ?></span>
                    <?php endif; ?>
                    <form method="POST" action="?tab=<?php echo sanitize($active_tab); ?>" class="ss-form-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                        <button type="submit" name="refresh_cache" value="1" class="btn btn-primary">🔄 Refresh</button>
                    </form>
                </div>
            </div>

            <?php if ($refreshNotice !== ''): ?>
                <div class="alert alert-success"><?php echo sanitize($refreshNotice); ?></div>
            <?php endif; ?>
            <?php if (!empty($refreshErrors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($refreshErrors as $refreshError): ?>
                        <div><?php echo sanitize($refreshError); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="status-tabs">
                <a href="?tab=monitoring" class="status-tab <?php echo $active_tab === 'monitoring' ? 'active' : ''; ?>">📊 Monitoring</a>
                <a href="?tab=php_settings" class="status-tab <?php echo $active_tab === 'php_settings' ? 'active' : ''; ?>">🧩 PHP Settings</a>
                <a href="?tab=database" class="status-tab <?php echo $active_tab === 'database' ? 'active' : ''; ?>">🗄️ Database</a>
            </div>

            <?php
                $tab_file = __DIR__ . '/tabs/' . $active_tab . '.php';
                if (file_exists($tab_file)) {
                    include $tab_file;
                } else {
                    echo "<div class='alert alert-danger'>Tab content not found.</div>";
                }
            ?>
        </div>
    </div>
</div>
</body>
</html>
