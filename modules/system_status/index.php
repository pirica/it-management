<?php
/**
 * System Status Module - Index
 *
 * Provides a comprehensive overview of the server status, including
 * real-time monitoring, PHP settings, and database metrics.
 * Uses PowerShell scripts on Windows Laragon for metrics collection.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

// Authorization check - Admin only
if (!isset($_SESSION['user_id']) || !itm_is_admin($conn, $_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$active_tab = $_GET['tab'] ?? 'monitoring';
$allowed_tabs = ['monitoring', 'php_settings', 'database'];
if (!in_array($active_tab, $allowed_tabs)) {
    $active_tab = 'monitoring';
}

$page_title = "System Status";
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
        .refresh-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .refresh-toolbar h1 { margin: 0; font-size: 1.5rem; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px; }
        .metric-card { background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 16px; min-width: 0; }
        .metric-card h3 { margin-top: 0; margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px; }
        .metric-value { font-size: 1.25rem; font-weight: 700; }
        .metric-label { font-size: 0.875rem; color: var(--text-secondary); }
        .gauge-container { width: 200px; margin: 0 auto; }
        .info-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .info-table td, .info-table th { padding: 8px 0; border-bottom: 1px solid var(--border-subtle); vertical-align: top; }
        .info-table td:first-child, .info-table th:first-child { font-weight: 600; width: 34%; color: var(--text-secondary); padding-right: 12px; }
        .info-table td:last-child, .info-table th:last-child { word-break: break-all; overflow-wrap: anywhere; }
        .info-table .ss-path-value { display: block; font-size: 0.85rem; line-height: 1.45; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
        .status-running { background: #e8f8ee; color: #18794e; border: 1px solid #9cd8b1; }
        .status-stopped { background: #fdecec; color: #a52727; border: 1px solid #f0b6b6; }
        .ss-storage-details { margin: 8px 0; border: 1px solid var(--border-subtle); border-radius: 6px; padding: 8px 10px; background: var(--bg-secondary); }
        .ss-storage-summary { cursor: pointer; list-style: none; display: grid; grid-template-columns: minmax(120px, 1.2fr) auto; gap: 4px 12px; align-items: start; }
        .ss-storage-summary::-webkit-details-marker { display: none; }
        .ss-storage-summary::before { content: '▸'; margin-right: 8px; color: var(--text-secondary); }
        .ss-storage-details[open] > .ss-storage-summary::before { content: '▾'; }
        .ss-storage-label { font-weight: 600; }
        .ss-storage-meta { color: var(--text-secondary); font-size: 0.85rem; white-space: nowrap; }
        .ss-storage-path { display: block; grid-column: 1 / -1; font-size: 0.8rem; color: var(--text-secondary); word-break: break-all; overflow-wrap: anywhere; margin-top: 2px; }
        .ss-storage-leaf { display: grid; grid-template-columns: minmax(120px, 1.2fr) auto; gap: 4px 12px; padding: 8px 0; border-bottom: 1px solid var(--border-subtle); }
        .ss-storage-leaf:last-child { border-bottom: 0; }
        .ss-storage-children { margin-top: 8px; }
        .ss-storage-total { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); font-weight: 700; }
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
                <a href="?tab=<?php echo sanitize($active_tab); ?>" class="btn btn-primary">🔄 Refresh</a>
            </div>

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
