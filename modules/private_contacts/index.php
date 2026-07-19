<?php
$crud_table = 'private_contacts';
$crud_title = 'Private Contacts';
$crud_action = $crud_action ?? 'index';
require_once 'index_logic.php';
require_once __DIR__ . '/private_contacts_list_helpers.php';

$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}

$searchRaw = trim((string)($_GET['search'] ?? ''));
$sortableColumns = pc_list_sortable_columns();
$sort = (string)($_GET['sort'] ?? 'first_name');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'first_name';
}
$sortSql = pc_resolve_list_sort_sql($sort);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = itm_resolve_records_per_page($ui_config ?? null);

$searchConditions = [];
if ($searchRaw !== '') {
    $searchConditions[] = 'first_name LIKE ?';
    $searchConditions[] = 'last_name LIKE ?';
    $searchConditions[] = 'email1_value LIKE ?';
    $searchConditions[] = 'organization_name LIKE ?';
    $searchConditions[] = 'phone1_value LIKE ?';
    $searchConditions[] = 'labels LIKE ?';
}

$listResult = pc_query_contacts_for_list($conn, [
    'employee_id' => $employeeId,
    'search' => $searchRaw,
    'sort' => $sort,
    'dir' => $dir,
    'page' => $page,
    'per_page' => $perPage,
]);
$contacts = $listResult['rows'];
$totalRows = (int)$listResult['totalRows'];
$totalPages = (int)$listResult['totalPages'];
$page = (int)$listResult['page'];
$offset = (int)$listResult['offset'];
$listOrderClause = 'ORDER BY is_favorite DESC, ' . $sortSql . ' ' . $dir;
$listLimitClause = 'LIMIT ' . $offset . ', ' . (int)$perPage;
$search = $searchRaw;

$pcListQueryState = [
    'search' => $searchRaw,
    'sort' => $sort,
    'dir' => $dir,
    'page' => $page,
];

// JSON API for Import Excel (table-tools.js).
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = (string)@file_get_contents('php://input');
    $itmImportJsonBody = json_decode($itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        if (!itm_validate_csrf_token($itmImportJsonBody['csrf_token'] ?? '')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        itm_handle_json_table_import($conn, 'private_contacts', (int)$companyId, $itmImportJsonBody);
        exit;
    }
}

$pcListColumns = [
    'first_name' => 'Name',
    'email1_value' => 'Email',
    'phone1_value' => 'Phone',
    'organization_name' => 'Organization',
    'labels' => 'Labels',
];

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
if (!isset($crud_title)) {
    $crud_title = 'Private Contacts';
}
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
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <div style="display:flex;gap:8px;">
                        <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    </div>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <div style="display:flex;gap:8px;">
                        <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    </div>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <input type="hidden" name="dir" value="<?php echo sanitize($dir); ?>">
                        <input type="hidden" name="page" value="1">
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="moduleSearch">Search (all fields)</label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($searchRaw); ?>" placeholder="Type to search records...">
                        </div>
                        <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card" style="overflow:auto;">
                <table class="table table-hover mb-0" data-itm-db-import-endpoint="index.php">
                    <thead>
                        <tr>
                            <th width="50"></th>
                            <?php foreach ($pcListColumns as $colKey => $colLabel): ?>
                                <?php
                                $nextDir = ($sort === $colKey && $dir === 'ASC') ? 'DESC' : 'ASC';
                                $sortHref = pc_build_list_url(array_merge($pcListQueryState, ['sort' => $colKey, 'dir' => $nextDir, 'page' => 1]));
                                ?>
                                <th>
                                    <a href="<?php echo sanitize($sortHref); ?>" style="text-decoration:none;color:inherit;">
                                        <?php echo sanitize($colLabel); ?>
                                        <?php if ($sort === $colKey): ?>
                                            <?php echo $dir === 'ASC' ? '▲' : '▼'; ?>
                                        <?php endif; ?>
                                    </a>
                                </th>
                            <?php endforeach; ?>
                            <th width="120" class="text-right itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No contacts found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td>
                                        <span class="favorite-star cursor-pointer" data-id="<?php echo (int)$contact['id']; ?>" data-favorite="<?php echo (int)$contact['is_favorite']; ?>">
                                            <i class="<?php echo $contact['is_favorite'] ? 'fas' : 'far'; ?> fa-star text-warning"></i>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($contact['photo']): ?>
                                                <img src="<?= itm_files_serve_url('Private/' . $_SESSION['username'] . '_' . $_SESSION['employee_id'] . '/private_contacts/' . $contact['photo']) ?>" class="rounded-circle mr-2" width="30" height="30" alt="" style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle border mr-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                                    <i class="fas fa-user text-muted" style="font-size: 14px;"></i>
                                                </div>
                                            <?php endif; ?>
                                            <a href="view.php?id=<?php echo (int)$contact['id']; ?>" class="font-weight-bold">
                                                <?php echo htmlspecialchars(trim($contact['first_name'] . ' ' . $contact['last_name'])); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)$contact['email1_value']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$contact['phone1_value']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$contact['organization_name']); ?></td>
                                    <td>
                                        <?php
                                        if ($contact['labels']) {
                                            foreach (explode(',', (string)$contact['labels']) as $label) {
                                                echo '<span class="badge badge-secondary mr-1">' . htmlspecialchars(trim($label)) . '</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td class="text-right itm-actions-cell" data-itm-actions-origin="1">
                                        <div class="itm-actions-wrap" style="display:flex;justify-content:flex-end;gap:4px;">
                                            <a class="btn btn-sm" href="view.php?id=<?php echo (int)$contact['id']; ?>" title="View">🔎</a>
                                            <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$contact['id']; ?>" title="Edit">✏️</a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$contact['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalRows > $perPage): ?>
                <div class="card" style="margin-top:16px;">
                    <div class="card-body" style="display:flex;justify-content:center;gap:8px;align-items:center;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="<?php echo sanitize(pc_build_list_url(array_merge($pcListQueryState, ['page' => $page - 1]))); ?>" title="◀️ Previous">Previous</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;"><?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="<?php echo sanitize(pc_build_list_url(array_merge($pcListQueryState, ['page' => $page + 1]))); ?>" title="▶️ Next">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../../js/theme.js"></script>
<script>
$(document).ready(function() {
    $('.favorite-star').click(function() {
        var $star = $(this);
        var id = $star.data('id');
        var current = $star.data('favorite');
        var newVal = current ? 0 : 1;

        $.post('index_logic.php', {
            csrf_token: $('input[name="csrf_token"]').val(),
            action: 'toggle_favorite',
            id: id,
            is_favorite: newVal
        }, function() {
            $star.data('favorite', newVal);
            $star.find('i').toggleClass('fas far');
        });
    });
});
</script>

</body>
</html>
