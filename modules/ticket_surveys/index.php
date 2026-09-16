<?php
/**
 * Ticket Surveys — read-only list of issued/completed customer surveys.
 */

$crud_table = 'ticket_surveys';
$crud_title = 'Ticket Surveys';

require_once dirname(__DIR__, 2) . '/config/config.php';

itm_require_crud_role_module_permission($conn, 'view', $crud_table);

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$searchRaw = trim((string)($_GET['search'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'id');
$dir = strtoupper((string)($_GET['dir'] ?? 'DESC'));
$sortMap = [
    'id' => 'ts.id',
    'reference' => 'ts.reference',
    'questionnaire_name' => 'tq.name',
    'average_score' => 'ts.average_score',
    'completed_at' => 'ts.completed_at',
    'respondent_email' => 'ts.respondent_email',
];
if (!isset($sortMap[$sort])) {
    $sort = 'id';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'DESC';
}
$sortSql = $sortMap[$sort] . ' ' . $dir;
$offset = ($page - 1) * $perPage;

$where = ' WHERE ts.company_id = ?';
$types = 'i';
$params = [(int)$company_id];

if ($searchRaw !== '') {
    $like = '%' . $searchRaw . '%';
    $where .= ' AND (ts.reference LIKE ? OR ts.respondent_email LIKE ? OR t.ticket_external_code LIKE ? OR t.title LIKE ? OR tq.name LIKE ?)';
    $types .= 'sssss';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}

$countSql = 'SELECT COUNT(*) AS total_rows
    FROM ticket_surveys ts
    INNER JOIN tickets t ON t.id = ts.ticket_id AND t.company_id = ts.company_id
    INNER JOIN ticket_questionnaires tq ON tq.id = ts.questionnaire_id AND tq.company_id = ts.company_id'
    . $where;
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
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

$listSql = 'SELECT ts.id, ts.reference, ts.respondent_email, ts.average_score, ts.completed_at, ts.created_at,
        t.ticket_external_code, t.title AS ticket_title,
        tq.name AS questionnaire_name
    FROM ticket_surveys ts
    INNER JOIN tickets t ON t.id = ts.ticket_id AND t.company_id = ts.company_id
    INNER JOIN ticket_questionnaires tq ON tq.id = ts.questionnaire_id AND tq.company_id = ts.company_id'
    . $where . ' ORDER BY ' . $sortSql . ' LIMIT ?, ?';
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

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$tsEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
$tsIcon = itm_resolve_module_sidebar_icon($conn, (int)$company_id, $tsEmployeeId, $moduleSlug);
$moduleListHeading = trim($tsIcon . ' ' . itm_module_access_strip_catalog_label_prefix($crud_title));
$csrfToken = itm_get_csrf_token();
$tsListQueryBase = 'search=' . urlencode($searchRaw) . '&sort=' . urlencode($sort) . '&dir=' . urlencode($dir) . '&page=';
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
    $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), $tsEmployeeId, $moduleSlug, (string)$crud_title);
    ?>
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="Ticket Surveys"><?php echo sanitize($moduleListHeading); ?></h1>

            <div class="card" style="margin-bottom:16px;">
                <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                    <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                    <input type="hidden" name="page" value="1">
                    <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                        <label for="tsSearch">Search</label>
                        <input type="text" id="tsSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Ticket, email, questionnaire…">
                    </div>
                    <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn" title="Clear">🔙</a>
                    </div>
                </form>
            </div>

            <div class="card" style="overflow:auto;">
                <table data-itm-no-import-excel="1">
                    <thead>
                    <tr>
                        <?php foreach (['reference' => 'Ticket', 'questionnaire_name' => 'Questionnaire', 'average_score' => 'Average', 'completed_at' => 'Completed', 'respondent_email' => 'Respondent email'] as $field => $label): ?>
                            <?php $nextDir = ($sort === $field && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>
                            <th><a href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($field); ?>&dir=<?php echo $nextDir; ?>&page=1" style="text-decoration:none;color:inherit;"><?php echo sanitize($label); ?><?php if ($sort === $field): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?></a></th>
                        <?php endforeach; ?>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $surveyRow): ?>
                            <?php
                            $ticketRef = trim((string)($surveyRow['reference'] ?? ''));
                            if ($ticketRef === '') {
                                $ticketRef = trim((string)($surveyRow['ticket_external_code'] ?? ''));
                            }
                            if ($ticketRef === '') {
                                $ticketRef = '#' . (int)($surveyRow['id'] ?? 0);
                            }
                            $completedAt = $surveyRow['completed_at'] ?? null;
                            ?>
                            <tr>
                                <td><?php echo sanitize($ticketRef); ?></td>
                                <td><?php echo sanitize($surveyRow['questionnaire_name'] ?? '—'); ?></td>
                                <td><?php echo $surveyRow['average_score'] !== null ? sanitize((string)$surveyRow['average_score']) : '—'; ?></td>
                                <td><?php echo $completedAt ? sanitize(itm_format_cell_scalar_display('completed_at', $completedAt)) : '<span class="badge badge-warning">Pending</span>'; ?></td>
                                <td><?php echo sanitize($surveyRow['respondent_email'] ?? '—'); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$surveyRow['id']; ?>" title="View">🔎</a>
                                        <?php if ($completedAt === null || $completedAt === ''): ?>
                                            <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this pending survey invite?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$surveyRow['id']; ?>">
                                                <button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No surveys found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalRows > $perPage): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php
                        if ($page > 1): ?>
                            <a class="btn btn-sm" href="?<?php echo $tsListQueryBase; ?>1" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="?<?php echo $tsListQueryBase . ($page - 1); ?>" title="Previous page">◀️</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?<?php echo $tsListQueryBase . ($page + 1); ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="?<?php echo $tsListQueryBase . $totalPages; ?>" title="Last page">⏭️</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
