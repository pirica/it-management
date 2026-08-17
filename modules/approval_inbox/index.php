<?php
/**
 * Unified Approval Inbox — list pending approvals for the signed-in assignee (admins see all).
 */
$crud_action = $crud_action ?? 'index';
require_once '../../config/config.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$isAdmin = itm_is_admin($conn, $employee_id);
$csrfToken = itm_get_csrf_token();
$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$resolvedModuleIcon = itm_resolve_module_sidebar_icon($conn, $company_id, $employee_id, $moduleSlug);
$moduleListHeading = trim($resolvedModuleIcon . ' ' . itm_module_access_strip_catalog_label_prefix('Approval Inbox'));
$ui_config = itm_get_ui_configuration($conn, $company_id, $employee_id);
$perPage = itm_resolve_records_per_page($ui_config ?? null);

require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, $moduleSlug, $moduleListHeading);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['inbox_decide'])) {
    itm_require_post_csrf();
    $itemId = (int)($_POST['item_id'] ?? 0);
    $decision = (string)($_POST['inbox_decide'] ?? '');
    $result = itm_approval_inbox_decide($conn, $company_id, $employee_id, $itemId, $decision);
    if (!empty($result['ok'])) {
        $_SESSION['crud_success'] = (string)($result['message'] ?? 'Decision saved.');
    } else {
        $_SESSION['crud_error'] = (string)($result['message'] ?? 'Unable to save decision.');
    }
    $redirect = 'index.php';
    $qs = [];
    foreach (['status', 'search', 'page'] as $key) {
        if (!empty($_POST[$key])) {
            $qs[$key] = (string)$_POST[$key];
        }
    }
    if ($qs !== []) {
        $redirect .= '?' . http_build_query($qs);
    }
    header('Location: ' . $redirect);
    exit;
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
if ($statusFilter !== '' && !in_array($statusFilter, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
    $statusFilter = '';
}
$searchRaw = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$listOptions = [
    'status' => $statusFilter,
    'search' => $searchRaw,
    'limit' => $perPage,
    'offset' => $offset,
    'mine_only' => !$isAdmin,
];
$totalRows = itm_approval_inbox_count_rows($conn, $company_id, $employee_id, $listOptions);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
    $listOptions['offset'] = $offset;
}
$rows = itm_approval_inbox_fetch_for_assignee($conn, $company_id, $employee_id, $listOptions);

$successMessage = $_SESSION['crud_success'] ?? '';
$errorMessage = $_SESSION['crud_error'] ?? '';
unset($_SESSION['crud_success'], $_SESSION['crud_error']);

function ai_inbox_query_string(array $overrides = []): string
{
    $params = array_merge([
        'status' => trim((string)($_GET['status'] ?? '')),
        'search' => trim((string)($_GET['search'] ?? '')),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ], $overrides);
    $built = http_build_query($params);
    return $built === '' ? '' : ('?' . $built);
}

function ai_inbox_module_label($slug)
{
    $map = [
        'request_password' => 'Request Password',
        'employee_onboarding_requests' => 'Employee Onboarding',
    ];
    return $map[(string)$slug] ?? ucwords(str_replace('_', ' ', (string)$slug));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config ?? null)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="card">
                <h1 title="Approval inbox" data-itm-new-button-managed="server"><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if ($successMessage !== ''): ?><div class="alert alert-success"><?php echo sanitize($successMessage); ?></div><?php endif; ?>
                <?php if ($errorMessage !== ''): ?><div class="alert alert-danger"><?php echo sanitize($errorMessage); ?></div><?php endif; ?>
                <p><?php echo $isAdmin ? 'Showing all company approval items.' : 'Showing items assigned to you.'; ?></p>
                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" action="index.php" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <div class="form-group" style="margin:0;min-width:160px;">
                            <label for="ai-status-filter">Status</label>
                            <select id="ai-status-filter" name="status" class="form-control">
                                <option value="">All</option>
                                <?php foreach (['pending', 'approved', 'rejected', 'cancelled'] as $st): ?>
                                    <option value="<?php echo sanitize($st); ?>"<?php echo $statusFilter === $st ? ' selected' : ''; ?>><?php echo sanitize(ucfirst($st)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="ai-search">Search</label>
                            <input type="text" id="ai-search" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Title, module, stage, record id…">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn" title="Clear">🔙</a>
                        </div>
                    </form>
                </div>
                <table data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                    <thead>
                    <tr>
                        <th>Module</th>
                        <th>Stage</th>
                        <th>Title</th>
                        <th>Requester</th>
                        <th>Assignee</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo sanitize(ai_inbox_module_label($row['module_slug'] ?? '')); ?></td>
                            <td><?php echo sanitize(strtoupper((string)($row['approval_stage'] ?? ''))); ?></td>
                            <td><?php echo sanitize((string)($row['title'] ?? '')); ?></td>
                            <td><?php echo sanitize(trim((string)($row['requester_name'] ?? '')) ?: '—'); ?></td>
                            <td><?php echo sanitize(trim((string)($row['assignee_name'] ?? '')) ?: '—'); ?></td>
                            <td><?php echo itm_approval_inbox_status_badge($row['status'] ?? 'pending'); ?></td>
                            <td><?php echo sanitize(itm_format_audit_timestamp_display($row['updated_at'] ?? $row['created_at'] ?? '')); ?></td>
                            <td class="itm-actions-cell" data-itm-actions-origin="1">
                                <div class="itm-actions-wrap">
                                    <?php
                                    $actionUrl = trim((string)($row['action_url'] ?? ''));
                                    if ($actionUrl !== '') {
                                        $href = (strpos($actionUrl, 'http') === 0) ? $actionUrl : (BASE_URL . ltrim($actionUrl, '/'));
                                        echo '<a class="btn btn-sm" href="' . sanitize($href) . '" title="Open source record">🔎</a>';
                                    }
                                    if ((string)($row['status'] ?? '') === 'pending') {
                                        $canDecide = $isAdmin || ((int)($row['assignee_employee_id'] ?? 0) === $employee_id);
                                        if ($canDecide) {
                                            ?>
                                            <form method="post" action="index.php" style="display:inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                                <input type="hidden" name="item_id" value="<?php echo (int)$row['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo sanitize($statusFilter); ?>">
                                                <input type="hidden" name="search" value="<?php echo sanitize($searchRaw); ?>">
                                                <input type="hidden" name="page" value="<?php echo (int)$page; ?>">
                                                <button type="submit" class="btn btn-sm btn-success" name="inbox_decide" value="approve" title="Approve">✅</button>
                                                <button type="submit" class="btn btn-sm btn-danger" name="inbox_decide" value="reject" title="Reject">❌</button>
                                            </form>
                                            <?php
                                        }
                                    }
                                    ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="8">No approval items found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;flex-wrap:wrap;gap:8px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-sm" href="index.php<?php echo ai_inbox_query_string(['page' => 1]); ?>" title="First page">⏮️</a>
                                <a class="btn btn-sm" href="index.php<?php echo ai_inbox_query_string(['page' => $page - 1]); ?>" title="Previous page">◀️</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn btn-sm" href="index.php<?php echo ai_inbox_query_string(['page' => $page + 1]); ?>" title="Next page">▶️</a>
                                <a class="btn btn-sm" href="index.php<?php echo ai_inbox_query_string(['page' => $totalPages]); ?>" title="Last page">⏭️</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
