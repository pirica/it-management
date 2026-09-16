<?php
/**
 * Job Queue — admin read-only list with filters and manual retry.
 */

require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_job_queue.php';

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if (!itm_is_admin($conn, $employeeId)) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['retry_job_id'])) {
    itm_require_post_csrf();
    $retryId = (int)($_POST['retry_job_id'] ?? 0);
    $result = itm_job_queue_retry_failed($conn, $retryId, $employeeId);
    $msg = !empty($result['ok']) ? 'Job queued for retry.' : (string)($result['error'] ?? 'Retry failed.');
    $returnQuery = trim((string)($_POST['return_query'] ?? ''));
    header('Location: index.php?' . ($returnQuery !== '' ? $returnQuery . '&' : '') . 'msg=' . rawurlencode($msg));
    exit;
}

$search = trim((string)($_GET['search'] ?? ''));
$filterType = trim((string)($_GET['job_type'] ?? ''));
$filterStatus = trim((string)($_GET['status'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
$sortable = [
    'id' => 'jq.id',
    'job_type' => 'jq.job_type',
    'status' => 'jq.status',
    'priority' => 'jq.priority',
    'attempts' => 'jq.attempts',
    'scheduled_at' => 'jq.scheduled_at',
    'created_at' => 'jq.created_at',
];
if (!isset($sortable[$sort])) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = ' WHERE jq.deleted_at IS NULL';
$types = '';
$params = [];

if ($filterType !== '' && in_array($filterType, itm_job_queue_job_types(), true)) {
    $where .= ' AND jq.job_type = ?';
    $types .= 's';
    $params[] = $filterType;
}
if ($filterStatus !== '' && in_array($filterStatus, ['pending', 'running', 'done', 'failed'], true)) {
    $where .= ' AND jq.status = ?';
    $types .= 's';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $like = '%' . $search . '%';
    $where .= ' AND (jq.job_type LIKE ? OR jq.last_error LIKE ? OR CAST(jq.id AS CHAR) LIKE ? OR c.company LIKE ?)';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

$countSql = 'SELECT COUNT(*) AS total_rows FROM job_queue jq LEFT JOIN companies c ON c.id = jq.company_id' . $where;
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param($countStmt, $types, ...$params);
    }
    mysqli_stmt_execute($countStmt);
    $countRes = mysqli_stmt_get_result($countStmt);
    if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
        $totalRows = (int)($countRow['total_rows'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}

$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$listSql = 'SELECT jq.id, jq.company_id, jq.job_type, jq.status, jq.priority, jq.attempts, jq.max_attempts,
        jq.scheduled_at, jq.started_at, jq.finished_at, jq.last_error, jq.created_at, c.company AS company_name
    FROM job_queue jq
    LEFT JOIN companies c ON c.id = jq.company_id'
    . $where . ' ORDER BY ' . $sortable[$sort] . ' ' . $dir . ' LIMIT ?, ?';
$listTypes = $types . 'ii';
$listParams = array_merge($params, [$offset, $perPage]);
$listStmt = mysqli_prepare($conn, $listSql);
$rows = [];
if ($listStmt) {
    mysqli_stmt_bind_param($listStmt, $listTypes, ...$listParams);
    mysqli_stmt_execute($listStmt);
    $listRes = mysqli_stmt_get_result($listStmt);
    while ($listRes && ($row = mysqli_fetch_assoc($listRes))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($listStmt);
}

$statusCounts = ['pending' => 0, 'running' => 0, 'done' => 0, 'failed' => 0];
$kpiRes = mysqli_query($conn, "SELECT status, COUNT(*) AS c FROM job_queue WHERE deleted_at IS NULL GROUP BY status");
while ($kpiRes && ($kpi = mysqli_fetch_assoc($kpiRes))) {
    $st = (string)($kpi['status'] ?? '');
    if (isset($statusCounts[$st])) {
        $statusCounts[$st] = (int)($kpi['c'] ?? 0);
    }
}

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$crud_title = 'Job Queue';
$icon = itm_resolve_module_sidebar_icon($conn, (int)($company_id ?? 0), $employeeId, $moduleSlug);
$listHeading = trim($icon . ' ' . itm_module_access_strip_catalog_label_prefix($crud_title));
$csrfToken = itm_get_csrf_token();
$flashMessage = trim((string)($_GET['msg'] ?? ''));
$queryBase = http_build_query(array_filter([
    'search' => $search !== '' ? $search : null,
    'job_type' => $filterType !== '' ? $filterType : null,
    'status' => $filterStatus !== '' ? $filterStatus : null,
    'sort' => $sort,
    'dir' => $dir,
]));
$listQuerySuffix = ($queryBase !== '' ? $queryBase . '&' : '') . 'page=';
$workerUrl = BASE_URL . 'scripts/run_job_queue.php?run=1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title(
        $conn,
        (int)($company_id ?? 0),
        $employeeId,
        $moduleSlug,
        (string)$crud_title
    );
    ?>
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config ?? [])); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                <h1 title="Job queue"><?php echo sanitize($listHeading); ?></h1>
                <a href="<?php echo sanitize($workerUrl); ?>" class="btn btn-sm" target="_blank" rel="noopener noreferrer" title="Run worker">▶️</a>
            </div>

            <?php if ($flashMessage !== ''): ?>
            <div class="card" style="margin-bottom:16px;padding:12px 14px;border-color:#9eb8ee;background:#eef4ff;">
                <?php echo sanitize($flashMessage); ?>
            </div>
            <?php endif; ?>

            <div class="card" style="margin-bottom:16px;padding:12px 14px;">
                <p style="margin:0 0 8px;font-size:13px;line-height:1.45;color:var(--text-muted, #6b7280);">
                    Background jobs are enqueued with <code>itm_job_queue_enqueue()</code> and processed by
                    <a class="itm-plain-link" href="<?php echo sanitize($workerUrl); ?>" target="_blank" rel="noopener noreferrer">run_job_queue.php</a>
                    (schedule every minute). Integration webhooks still use their dedicated delivery table in parallel.
                </p>
                <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:13px;">
                    <span><strong>Pending:</strong> <?php echo (int)$statusCounts['pending']; ?></span>
                    <span><strong>Running:</strong> <?php echo (int)$statusCounts['running']; ?></span>
                    <span><strong>Done:</strong> <?php echo (int)$statusCounts['done']; ?></span>
                    <span><strong>Failed:</strong> <?php echo (int)$statusCounts['failed']; ?></span>
                </div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" action="index.php" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search id, type, error, company…" class="form-control" style="min-width:200px;">
                    <select name="job_type" class="form-control">
                        <option value="">All types</option>
                        <?php foreach (itm_job_queue_job_types() as $type): ?>
                        <option value="<?php echo sanitize($type); ?>"<?php echo $filterType === $type ? ' selected' : ''; ?>><?php echo sanitize($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        <?php foreach (['pending', 'running', 'done', 'failed'] as $st): ?>
                        <option value="<?php echo sanitize($st); ?>"<?php echo $filterStatus === $st ? ' selected' : ''; ?>><?php echo sanitize(ucfirst($st)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                    <button type="submit" class="btn btn-sm btn-primary" title="Search">Search</button>
                    <a href="index.php" class="btn btn-sm" title="Clear">🔙</a>
                </form>
            </div>

            <div class="card">
                <table class="table" data-itm-no-import-excel="1" data-itm-no-export-excel="1" data-itm-no-export-pdf="1">
                    <thead>
                        <tr>
                            <?php
                            $headers = [
                                'id' => 'ID',
                                'job_type' => 'Type',
                                'status' => 'Status',
                                'priority' => 'Priority',
                                'attempts' => 'Attempts',
                                'company_name' => 'Company',
                                'scheduled_at' => 'Scheduled',
                                'created_at' => 'Created',
                            ];
                            foreach ($headers as $col => $label):
                                $nextDir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
                                $arrow = ($sort === $col) ? ($dir === 'ASC' ? '▲' : '▼') : '';
                                $sortHref = '?' . http_build_query(array_filter([
                                    'search' => $search !== '' ? $search : null,
                                    'job_type' => $filterType !== '' ? $filterType : null,
                                    'status' => $filterStatus !== '' ? $filterStatus : null,
                                    'sort' => $col,
                                    'dir' => $nextDir,
                                ]));
                            ?>
                            <th><a style="text-decoration:none;color:inherit;" class="sm-sort-link" href="<?php echo sanitize($sortHref); ?>"><?php echo sanitize($label . ($arrow !== '' ? ' ' . $arrow : '')); ?></a></th>
                            <?php endforeach; ?>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows === []): ?>
                        <tr><td colspan="9">No jobs found.</td></tr>
                        <?php else: ?>
                        <?php foreach ($rows as $row):
                            $status = (string)($row['status'] ?? '');
                            $badgeClass = 'badge-secondary';
                            if ($status === 'pending') {
                                $badgeClass = 'badge-warning';
                            } elseif ($status === 'running') {
                                $badgeClass = 'badge-info';
                            } elseif ($status === 'done') {
                                $badgeClass = 'badge-success';
                            } elseif ($status === 'failed') {
                                $badgeClass = 'badge-danger';
                            }
                            $viewHref = 'view.php?id=' . (int)($row['id'] ?? 0) . '&return_query=' . rawurlencode($queryBase);
                        ?>
                        <tr>
                            <td><?php echo (int)($row['id'] ?? 0); ?></td>
                            <td><?php echo sanitize((string)($row['job_type'] ?? '')); ?></td>
                            <td><span class="badge <?php echo sanitize($badgeClass); ?>"><?php echo sanitize(ucfirst($status)); ?></span></td>
                            <td><?php echo (int)($row['priority'] ?? 0); ?></td>
                            <td><?php echo (int)($row['attempts'] ?? 0); ?> / <?php echo (int)($row['max_attempts'] ?? 0); ?></td>
                            <td><?php echo sanitize((string)($row['company_name'] ?? '—')); ?></td>
                            <td><?php echo sanitize(itm_format_datetime_display($row['scheduled_at'] ?? '')); ?></td>
                            <td><?php echo sanitize(itm_format_datetime_display($row['created_at'] ?? '')); ?></td>
                            <td class="itm-actions-cell" data-itm-actions-origin="1">
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="<?php echo sanitize($viewHref); ?>" title="View">🔎</a>
                                    <?php if (in_array($status, ['failed', 'done'], true)): ?>
                                    <form method="POST" action="index.php" style="display:inline;margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="retry_job_id" value="<?php echo (int)($row['id'] ?? 0); ?>">
                                        <input type="hidden" name="return_query" value="<?php echo sanitize($queryBase); ?>">
                                        <button type="submit" class="btn btn-sm" title="Retry">🔄</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                <div style="margin-top:12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <?php if ($page > 1): ?>
                    <a class="btn btn-sm" href="?<?php echo sanitize($listQuerySuffix); ?>1" title="First page">⏮️</a>
                    <a class="btn btn-sm" href="?<?php echo sanitize($listQuerySuffix . ($page - 1)); ?>" title="Previous page">◀️</a>
                    <?php endif; ?>
                    <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <?php if ($page < $totalPages): ?>
                    <a class="btn btn-sm" href="?<?php echo sanitize($listQuerySuffix . ($page + 1)); ?>" title="Next page">▶️</a>
                    <a class="btn btn-sm" href="?<?php echo sanitize($listQuerySuffix . $totalPages); ?>" title="Last page">⏭️</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
