<?php
/**
 * Network Discovery v2 — discovery profiles and staging review queue.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_network_discovery.php';

$companyId = (int)($company_id ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$isAdmin = function_exists('itm_is_admin') && itm_is_admin();
$csrfToken = itm_get_csrf_token();

$ndApiBase = 'api.php';
$ndSubnetListUrl = '../ip_subnets/index.php';
$ndProfilesTabUrl = 'index.php?tab=profiles';
$ndStagingTabUrl = 'index.php?tab=staging';
$ndExternalModuleUrl = '';
$ndEquipmentViewPrefix = '../equipment/';
$ndPaginationPrefix = '?';
$ndStagingFormBase = 'index.php?tab=staging';

require __DIR__ . '/../ip_subnets/includes/network_discovery_handlers.php';
require __DIR__ . '/../ip_subnets/includes/network_discovery_bootstrap.php';

$flash = $ndFlash;
$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$pageTitle = 'Network Discovery';
$resolvedIcon = itm_resolve_module_sidebar_icon($conn, $companyId, $employeeId, $moduleSlug);
$moduleListHeading = trim($resolvedIcon . ' ' . itm_module_access_strip_catalog_label_prefix($pageTitle));
$currentUiConfig = $ui_config ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $companyId, $employeeId, $moduleSlug, $pageTitle);
    ?>
    <title><?php echo sanitize($crud_title); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if ($flash !== ''): ?>
                <div class="alert alert-success"><?php echo sanitize($flash); ?></div>
            <?php endif; ?>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                <h1 style="margin:0;" title="Network Discovery"><?php echo sanitize($moduleListHeading); ?></h1>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a class="btn btn-sm" href="../ip_subnets/index.php" title="IP Subnets">🧭</a>
                </div>
            </div>

            <?php require __DIR__ . '/../ip_subnets/includes/partials/network_discovery_tabs.php'; ?>

            <?php if ($activeTab === 'profiles'): ?>
                <?php require __DIR__ . '/../ip_subnets/includes/partials/network_discovery_profiles.php'; ?>
            <?php else: ?>
                <?php require __DIR__ . '/../ip_subnets/includes/partials/network_discovery_staging.php'; ?>
                <?php require __DIR__ . '/../ip_subnets/includes/partials/network_discovery_assets.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
