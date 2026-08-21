<?php
/**
 * Master Tickets — global cross-company rollup (master_tickets has no company_id).
 */

$moduleSlug = 'master_tickets';
$pageTitle = 'Master Tickets';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';

itm_require_crud_role_module_permission($conn, 'view', $moduleSlug);

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$sessionCompanyId = (int)$company_id;
$allowedCompanyIds = itm_master_ticket_allowed_company_ids($conn, $employeeId);
$csrfToken = itm_get_csrf_token();
$listUrl = 'index.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();
    itm_require_crud_role_module_permission($conn, 'create', $moduleSlug);

    $seedError = '';
    $inserted = itm_master_ticket_seed_five_company_sample($conn, $employeeId, $sessionCompanyId, $seedError);
    if ($inserted <= 0 && $seedError !== '') {
        $_SESSION['crud_error'] = $seedError;
    }

    header('Location: ' . $listUrl);
    exit;
}

$search = trim((string)($_GET['search'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = itm_resolve_records_per_page($ui_config ?? null);

$listData = itm_master_ticket_list_page($conn, $allowedCompanyIds, $search, $sort, $dir, $page, $perPage);
$rows = $listData['rows'];
$totalRows = (int)$listData['total'];
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$showSampleDataButton = $totalRows === 0 && itm_master_ticket_count_live_rows($conn) === 0;

$sortToggle = static function ($column) use ($sort, $dir, $search, $page) {
    $nextDir = ($sort === $column && $dir === 'ASC') ? 'DESC' : 'ASC';
    $qs = http_build_query([
        'search' => $search,
        'sort' => $column,
        'dir' => $nextDir,
        'page' => $page,
    ]);
    $arrow = ($sort === $column) ? ($dir === 'ASC' ? '▲' : '▼') : '';
    return [$qs, $arrow];
};

$moduleSlugPath = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    if (!isset($currentUiConfig)) {
        $currentUiConfig = $ui_config ?? [];
    }
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $browserTitle = itm_crud_apply_module_icon_to_browser_title($conn, $sessionCompanyId, $employeeId, $moduleSlugPath, $pageTitle);
    ?>
    <title><?= sanitize($browserTitle) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                <h1 title="Master tickets — global rollup">🎫</h1>
                <?php if (itm_user_has_role_module_permission($conn, $employeeId, $sessionCompanyId, itm_resolve_rbac_module_name_for_slug($conn, $moduleSlug), 'create')): ?>
                    <a href="create.php" class="btn btn-primary" title="Create">➕</a>
                <?php endif; ?>
            </div>

            <p class="itm-muted" style="margin-bottom:16px;">
                Global rollup table (<code>master_tickets</code> has no <code>company_id</code>).
                Visibility follows linked problems in companies you can access.
            </p>

            <?php if (!empty($_SESSION['crud_error'])): ?>
                <?php echo itm_render_alert_errors([(string)$_SESSION['crud_error']]); unset($_SESSION['crud_error']); ?>
            <?php endif; ?>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;">
                    <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search title, description, root cause" class="form-control" style="min-width:220px;">
                    <button type="submit" class="btn btn-primary" title="Search">Search</button>
                    <?php if ($search !== ''): ?>
                        <a href="index.php" class="btn" title="Clear">🔙</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card">
                <table data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                    <thead>
                    <tr>
                        <?php
                        list($qsId, $arrowId) = $sortToggle('id');
                        list($qsTitle, $arrowTitle) = $sortToggle('title');
                        list($qsCompanies, $arrowCompanies) = $sortToggle('company_count');
                        list($qsIncidents, $arrowIncidents) = $sortToggle('incident_count');
                        list($qsCreated, $arrowCreated) = $sortToggle('created_at');
                        ?>
                        <th><a href="?<?php echo sanitize($qsId); ?>" style="text-decoration:none;color:inherit;">ID <?php echo $arrowId; ?></a></th>
                        <th><a href="?<?php echo sanitize($qsTitle); ?>" style="text-decoration:none;color:inherit;">Title <?php echo $arrowTitle; ?></a></th>
                        <th><a href="?<?php echo sanitize($qsCompanies); ?>" style="text-decoration:none;color:inherit;">Companies <?php echo $arrowCompanies; ?></a></th>
                        <th><a href="?<?php echo sanitize($qsIncidents); ?>" style="text-decoration:none;color:inherit;">Incidents <?php echo $arrowIncidents; ?></a></th>
                        <th>Active</th>
                        <th><a href="?<?php echo sanitize($qsCreated); ?>" style="text-decoration:none;color:inherit;">Created <?php echo $arrowCreated; ?></a></th>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <?php $isActive = ((int)($row['active'] ?? 0) === 1); ?>
                            <tr>
                                <td><?php echo (int)($row['id'] ?? 0); ?></td>
                                <td><?php echo sanitize($row['title'] ?? ''); ?></td>
                                <td><?php echo (int)($row['company_count'] ?? 0); ?></td>
                                <td><?php echo (int)($row['incident_count'] ?? 0); ?></td>
                                <td>
                                    <span class="badge <?php echo $isActive ? 'badge-success' : 'badge-danger'; ?>">
                                        <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo sanitize(itm_format_audit_timestamp_display($row['created_at'] ?? '')); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">No master tickets visible for your company access.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                        <?php
                        $pageQs = static function ($p) use ($search, $sort, $dir) {
                            return '?' . http_build_query(['search' => $search, 'sort' => $sort, 'dir' => $dir, 'page' => $p]);
                        };
                        ?>
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="<?php echo sanitize($pageQs(1)); ?>" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="<?php echo sanitize($pageQs($page - 1)); ?>" title="Previous page">◀️</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="<?php echo sanitize($pageQs($page + 1)); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="<?php echo sanitize($pageQs($totalPages)); ?>" title="Last page">⏭️</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($showSampleDataButton && itm_user_has_role_module_permission($conn, $employeeId, $sessionCompanyId, itm_resolve_rbac_module_name_for_slug($conn, $moduleSlug), 'create')): ?>
                <div class="card" style="margin-top:12px;">
                    <form method="POST" style="display:flex;justify-content:center;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
