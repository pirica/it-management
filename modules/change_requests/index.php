<?php
/**
 * Change Requests — list (tenant-scoped, CMDB blast-radius workflow).
 */
require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_change_requests.php';
require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';

$search = trim((string)($_GET['search'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$sortableColumns = ['title', 'status', 'change_type', 'risk_level', 'scheduled_start', 'created_at'];
$sort = (string)($_GET['sort'] ?? 'created_at');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'created_at';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortColumnMap = [
    'title' => 'cr.title',
    'status' => 'cr.status',
    'change_type' => 'cr.change_type',
    'risk_level' => 'cr.risk_level',
    'scheduled_start' => 'cr.scheduled_start',
    'created_at' => 'cr.created_at',
];
$sortSql = $sortColumnMap[$sort] . ' ' . $dir;
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$offset = ($page - 1) * $perPage;
$companyId = (int)$company_id;
$csrfToken = itm_get_csrf_token();
$crud_title = 'Change Requests';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $companyId, (int)($_SESSION['employee_id'] ?? 0), 'change_requests', $crud_title);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();

    if ($companyId <= 0) {
        $_SESSION['crud_error'] = 'Sample data requires an active company.';
        header('Location: index.php');
        exit;
    }

    $existingRows = function_exists('itm_seed_tenant_row_count')
        ? itm_seed_tenant_row_count($conn, 'change_requests', $companyId)
        : 0;

    if ($existingRows > 0) {
        $_SESSION['crud_error'] = 'Sample data can only be added when no records exist.';
        header('Location: index.php');
        exit;
    }

    require_once ROOT_PATH . 'includes/itm_sample_data_seed.php';
    $seedError = '';
    $insertedRows = itm_seed_table_from_database_sql($conn, 'change_requests', $companyId, $seedError);
    if ($insertedRows <= 0 && $seedError !== '') {
        $_SESSION['crud_error'] = $seedError;
    }

    header('Location: index.php');
    exit;
}

$where = 'cr.company_id = ? AND cr.deleted_at IS NULL';
$params = [$companyId];
$types = 'i';
if ($search !== '') {
    $where .= ' AND (cr.title LIKE ? OR cr.description LIKE ? OR cr.rollback_plan LIKE ? OR ci.name LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

$countSql = "SELECT COUNT(*) AS total FROM change_requests cr
             INNER JOIN configuration_items ci ON ci.id = cr.source_configuration_item_id AND ci.company_id = cr.company_id
             WHERE {$where}";
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
  $bind = [$types];
  foreach ($params as $i => $v) {
    $bind[] = &$params[$i];
  }
  call_user_func_array([$countStmt, 'bind_param'], $bind);
  mysqli_stmt_execute($countStmt);
  $cRes = mysqli_stmt_get_result($countStmt);
  $cRow = $cRes ? mysqli_fetch_assoc($cRes) : null;
  $totalRows = (int)($cRow['total'] ?? 0);
  mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = "SELECT cr.id, cr.title, cr.status, cr.change_type, cr.risk_level, cr.scheduled_start, cr.scheduled_end, cr.created_at,
                   ci.name AS source_ci_name, cit.icon AS source_ci_icon
            FROM change_requests cr
            INNER JOIN configuration_items ci ON ci.id = cr.source_configuration_item_id AND ci.company_id = cr.company_id
            INNER JOIN configuration_item_types cit ON cit.id = ci.ci_type_id AND cit.company_id = ci.company_id
            WHERE {$where}
            ORDER BY {$sortSql}
            LIMIT ? OFFSET ?";
$listStmt = mysqli_prepare($conn, $listSql);
$rows = [];
if ($listStmt) {
    $listTypes = $types . 'ii';
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;
    $bind = [$listTypes];
    foreach ($listParams as $i => $v) {
        $bind[] = &$listParams[$i];
    }
    call_user_func_array([$listStmt, 'bind_param'], $bind);
    mysqli_stmt_execute($listStmt);
    $res = mysqli_stmt_get_result($listStmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($listStmt);
}

$statuses = itm_change_request_statuses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($crud_title); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config ?? [])); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1><?php echo sanitize($crud_title); ?></h1>
                <a href="create.php" class="btn btn-primary" title="Create">➕</a>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;flex:1;min-width:240px;">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo sanitize($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn" title="Clear">🔙</a>
                </form>
            </div>

            <div class="card">
                <table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                    <thead>
                    <tr>
                        <?php foreach ([
                            'title' => 'Title',
                            'status' => 'Status',
                            'change_type' => 'Type',
                            'risk_level' => 'Risk',
                            'scheduled_start' => 'Scheduled',
                        ] as $sortField => $sortLabel):
                            $nextDir = ($sort === $sortField && $dir === 'ASC') ? 'DESC' : 'ASC';
                            ?>
                        <th>
                            <a href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sortField); ?>&dir=<?php echo $nextDir; ?>&page=<?php echo (int)$page; ?>" style="text-decoration:none;color:inherit;">
                                <?php echo sanitize($sortLabel); ?>
                                <?php if ($sort === $sortField): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                            </a>
                        </th>
                        <?php endforeach; ?>
                        <th>Source CI</th>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                    <tr><td colspan="7">No change requests found.</td></tr>
                    <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo sanitize((string)($row['title'] ?? '')); ?></td>
                        <td><span class="badge"><?php echo sanitize(itm_change_request_status_label((string)($row['status'] ?? ''))); ?></span></td>
                        <td><?php echo sanitize(itm_change_request_type_label((string)($row['change_type'] ?? ''))); ?></td>
                        <td><?php echo sanitize(itm_change_request_risk_label((string)($row['risk_level'] ?? ''))); ?></td>
                        <td><?php
                        $start = (string)($row['scheduled_start'] ?? '');
                        $end = (string)($row['scheduled_end'] ?? '');
                        echo sanitize(trim($start . ($end !== '' ? ' → ' . $end : '')));
                        ?></td>
                        <td><?php echo sanitize((string)($row['source_ci_icon'] ?? '') . ' ' . (string)($row['source_ci_name'] ?? '')); ?></td>
                        <td class="itm-actions-cell" data-itm-actions-origin="1">
                            <div class="itm-actions-wrap">
                                <a class="btn btn-sm" href="view.php?id=<?php echo (int)($row['id'] ?? 0); ?>" title="View">🔎</a>
                                <a class="btn btn-sm" href="edit.php?id=<?php echo (int)($row['id'] ?? 0); ?>" title="Edit">✏️</a>
                                <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                    <input type="hidden" name="id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <?php if (function_exists('itm_crud_render_delete_hidden_audit_inputs')) {
                                        itm_crud_render_delete_hidden_audit_inputs();
                                    } ?>
                                    <button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <?php if ($totalPages > 1): ?>
                <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
                    <?php if ($page > 1): ?>
                    <a class="btn btn-sm" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=1" title="First page">⏮️</a>
                    <a class="btn btn-sm" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="Previous page">◀️</a>
                    <?php endif; ?>
                    <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a class="btn btn-sm" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="Next page">▶️</a>
                    <a class="btn btn-sm" href="?search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $totalPages; ?>" title="Last page">⏭️</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($companyId > 0 && $totalRows === 0): ?>
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
<script src="../../js/theme.js"></script>
</body>
</html>
