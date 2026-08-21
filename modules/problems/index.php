<?php
/**
 * Problem Management — flattened list with search, sort, pagination, and Excel import.
 */

$crud_title = 'Problem Management';
$moduleSlug = 'problems';
$crud_action = $crud_action ?? 'index';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';

itm_require_crud_role_module_permission($conn, 'view', $moduleSlug);

$companyId = (int)$company_id;
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$errors = [];
$csrfToken = itm_get_csrf_token();
$listUrl = dirname($_SERVER['PHP_SELF']) . '/index.php';

// Why: table-tools.js posts JSON import payloads to the endpoint named on the list table.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode((string)$rawBody, true);
    if (is_array($jsonBody) && isset($jsonBody['import_excel_rows'])) {
        header('Content-Type: application/json; charset=utf-8');
        itm_require_crud_role_module_permission($conn, 'import', $moduleSlug);

        $requestToken = (string)($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($requestToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ($companyId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Import requires an active company.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $importRows = $jsonBody['import_excel_rows'];
        if (!is_array($importRows) || count($importRows) < 2) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'The uploaded file has no data rows.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $headerRow = array_map(static function ($v) {
            return strtolower(trim(preg_replace('/\s+/', ' ', (string)$v)));
        }, (array)($importRows[0] ?? []));

        $colIndex = static function (array $headers, array $names) {
            foreach ($names as $name) {
                $key = array_search(strtolower($name), $headers, true);
                if ($key !== false) {
                    return (int)$key;
                }
            }
            return -1;
        };

        $titleIdx = $colIndex($headerRow, ['title']);
        $descIdx = $colIndex($headerRow, ['description']);
        $rootIdx = $colIndex($headerRow, ['root cause', 'root_cause']);
        $statusIdx = $colIndex($headerRow, ['status']);
        $ownerIdx = $colIndex($headerRow, ['owner', 'owner employee', 'owner_employee_id']);

        if ($titleIdx < 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Import requires a Title column.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $insertedRows = 0;
        $importErrors = [];
        for ($rowIndex = 1; $rowIndex < count($importRows); $rowIndex++) {
            $sourceRow = (array)$importRows[$rowIndex];
            if (empty(array_filter($sourceRow, static function ($v) {
                return trim((string)$v) !== '';
            }))) {
                continue;
            }

            $title = trim((string)($sourceRow[$titleIdx] ?? ''));
            if ($title === '') {
                continue;
            }

            $ownerId = 0;
            if ($ownerIdx >= 0) {
                $ownerRaw = trim((string)($sourceRow[$ownerIdx] ?? ''));
                if ($ownerRaw !== '') {
                    if (ctype_digit($ownerRaw)) {
                        $ownerId = (int)$ownerRaw;
                    } else {
                        $ownerStmt = mysqli_prepare(
                            $conn,
                            'SELECT id FROM employees WHERE company_id = ? AND deleted_at IS NULL AND (username = ? OR CONCAT(COALESCE(first_name,""), " ", COALESCE(last_name,"")) = ?) LIMIT 1'
                        );
                        if ($ownerStmt) {
                            mysqli_stmt_bind_param($ownerStmt, 'iss', $companyId, $ownerRaw, $ownerRaw);
                            mysqli_stmt_execute($ownerStmt);
                            $ownerRes = mysqli_stmt_get_result($ownerStmt);
                            if ($ownerRes && ($ownerRow = mysqli_fetch_assoc($ownerRes))) {
                                $ownerId = (int)$ownerRow['id'];
                            }
                            mysqli_stmt_close($ownerStmt);
                        }
                    }
                }
            }

            $payload = [
                'title' => $title,
                'description' => $descIdx >= 0 ? (string)($sourceRow[$descIdx] ?? '') : '',
                'root_cause' => $rootIdx >= 0 ? (string)($sourceRow[$rootIdx] ?? '') : '',
                'status' => $statusIdx >= 0 ? (string)($sourceRow[$statusIdx] ?? 'investigating') : 'investigating',
                'owner_employee_id' => $ownerId,
            ];
            $result = itm_problem_create($conn, $companyId, $payload, $employeeId);
            if (!empty($result['ok'])) {
                $insertedRows++;
            } else {
                $importErrors[] = 'Row ' . ($rowIndex + 1) . ': ' . (string)($result['error'] ?? 'Create failed.');
            }
        }

        echo json_encode([
            'ok' => empty($importErrors),
            'inserted' => $insertedRows,
            'errors' => $importErrors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$uiColumns = ['title', 'status', 'owner_employee_id', 'incident_count', 'master_ticket', 'known_error'];
// Why: Search and list share visible columns; alias matches role/ui_configuration modules.
$displayFieldColumns = $uiColumns;

$sortableColumns = ['title', 'status', 'owner_employee_id', 'incident_count', 'master_ticket', 'known_error'];
$sort = (string)($_GET['sort'] ?? 'title');
$dir = strtoupper((string)($_GET['dir'] ?? 'ASC'));
if (!in_array($sort, $sortableColumns, true)) {
    $sort = 'title';
}
if (!in_array($dir, ['ASC', 'DESC'], true)) {
    $dir = 'ASC';
}

$searchRaw = trim((string)($_GET['search'] ?? ''));
$whereParts = ['p.company_id = ?', 'p.deleted_at IS NULL'];
$whereTypes = 'i';
$whereParams = [$companyId];

if ($searchRaw !== '') {
    $searchLike = '%' . $searchRaw . '%';
    $searchParts = [
        'p.title LIKE ?',
        'p.description LIKE ?',
        'p.root_cause LIKE ?',
        'p.status LIKE ?',
        'CAST((SELECT COUNT(*) FROM problem_ticket_links l WHERE l.problem_id = p.id AND l.company_id = p.company_id AND l.deleted_at IS NULL) AS CHAR) LIKE ?',
        'CAST(COALESCE(p.master_ticket_id, 0) AS CHAR) LIKE ?',
        'EXISTS (SELECT 1 FROM employees se WHERE se.id = p.owner_employee_id AND se.company_id = p.company_id AND (se.username LIKE ? OR CONCAT(COALESCE(se.first_name,""), " ", COALESCE(se.last_name,"")) LIKE ?))',
    ];
    $whereParts[] = '(' . implode(' OR ', $searchParts) . ')';
    $whereTypes .= 'ssssssss';
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
}

$whereSql = ' WHERE ' . implode(' AND ', $whereParts);

$incidentSub = '(SELECT COUNT(*) FROM problem_ticket_links l WHERE l.problem_id = p.id AND l.company_id = p.company_id AND l.deleted_at IS NULL)';
$knownSub = '(SELECT COUNT(*) FROM known_errors ke WHERE ke.problem_id = p.id AND ke.company_id = p.company_id AND ke.deleted_at IS NULL AND ke.active = 1)';

$sortSql = 'p.title ASC';
if ($sort === 'title') {
    $sortSql = 'p.title ' . $dir;
} elseif ($sort === 'status') {
    $sortSql = 'p.status ' . $dir;
} elseif ($sort === 'owner_employee_id') {
    $sortSql = 'owner_name ' . $dir . ', p.title ASC';
} elseif ($sort === 'incident_count') {
    $sortSql = 'incident_count ' . $dir . ', p.title ASC';
} elseif ($sort === 'master_ticket') {
    $sortSql = 'p.master_ticket_id ' . $dir . ', p.title ASC';
} elseif ($sort === 'known_error') {
    $sortSql = 'known_error_flag ' . $dir . ', p.title ASC';
}

$countSql = 'SELECT COUNT(*) AS total_rows FROM problems p LEFT JOIN employees e ON e.id = p.owner_employee_id AND e.company_id = p.company_id' . $whereSql;
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
    $bind = [$whereTypes];
    foreach ($whereParams as $i => $v) {
        $bind[] = &$whereParams[$i];
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$countStmt], $bind));
    mysqli_stmt_execute($countStmt);
    $countRes = mysqli_stmt_get_result($countStmt);
    if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
        $totalRows = (int)($countRow['total_rows'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}

$perPage = itm_resolve_records_per_page($ui_config ?? null);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$showBulkActions = ($totalRows >= $perPage);
$page = max(1, (int)($_GET['page'] ?? 1));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$listSql = 'SELECT p.id, p.title, p.status, p.owner_employee_id, p.master_ticket_id,
    TRIM(CONCAT(COALESCE(e.first_name, ""), " ", COALESCE(e.last_name, ""))) AS owner_name,
    e.username AS owner_username,
    ' . $incidentSub . ' AS incident_count,
    ' . $knownSub . ' AS known_error_flag
    FROM problems p
    LEFT JOIN employees e ON e.id = p.owner_employee_id AND e.company_id = p.company_id'
    . $whereSql . ' ORDER BY ' . $sortSql . ' LIMIT ?, ?';

$listTypes = $whereTypes . 'ii';
$listParams = $whereParams;
$listParams[] = $offset;
$listParams[] = $perPage;

$listStmt = mysqli_prepare($conn, $listSql);
$rows = [];
if ($listStmt) {
    $bind = [$listTypes];
    foreach ($listParams as $i => $v) {
        $bind[] = &$listParams[$i];
    }
    call_user_func_array('mysqli_stmt_bind_param', array_merge([$listStmt], $bind));
    mysqli_stmt_execute($listStmt);
    $listRes = mysqli_stmt_get_result($listStmt);
    while ($listRes && ($row = mysqli_fetch_assoc($listRes))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($listStmt);
}

$moduleListHeading = itm_sidebar_label_for_module($moduleSlug) ?: $crud_title;
$newButtonPosition = itm_resolve_new_button_position($ui_config ?? null);

function problems_humanize_column($field)
{
    $map = [
        'owner_employee_id' => 'Owner',
        'incident_count' => 'Incidents',
        'master_ticket' => 'Master',
        'known_error' => 'Known Error',
    ];
    if (isset($map[$field])) {
        return $map[$field];
    }
    return ucwords(str_replace('_', ' ', (string)$field));
}

function problems_render_owner_label(array $row)
{
    $name = trim((string)($row['owner_name'] ?? ''));
    if ($name === '') {
        $name = (string)($row['owner_username'] ?? '');
    }
    return $name !== '' ? sanitize($name) : '—';
}

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
    $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $companyId, $employeeId, $moduleSlug, (string)$crud_title);
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
            <?php echo itm_render_alert_errors($errors); ?>
            <?php if (!empty($_SESSION['crud_error'])): ?>
                <?php echo itm_render_alert_errors([(string)$_SESSION['crud_error']]); unset($_SESSION['crud_error']); ?>
            <?php endif; ?>

            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;min-height:40px;">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <div style="display:flex;gap:8px;">
                        <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    </div>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;" title="Problem Management"><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <div style="display:flex;gap:8px;">
                        <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                    </div>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <?php if ($showBulkActions): ?>
                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;" data-itm-bulk-delete-bound="1">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <?php if (function_exists('itm_crud_render_delete_hidden_audit_inputs')) {
                            itm_crud_render_delete_hidden_audit_inputs();
                        } ?>
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <button type="button" class="btn btn-sm" data-itm-bulk-cancel="1">Cancel</button>
                        <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card" style="margin-bottom:16px;">
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
                        <a href="index.php" class="btn" title="Clear">🔙</a>
                    </div>
                </form>
            </div>

            <div class="card" style="overflow:auto;">
                <table data-itm-db-import-endpoint="index.php">
                    <thead>
                    <tr>
                        <?php if ($showBulkActions): ?>
                            <th style="width:36px;"></th>
                        <?php endif; ?>
                        <?php foreach ($uiColumns as $col): ?>
                            <?php
                            $nextDir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
                            $sortHref = '?search=' . urlencode($searchRaw) . '&sort=' . urlencode($col) . '&dir=' . $nextDir . '&page=1';
                            ?>
                            <th>
                                <a href="<?php echo sanitize($sortHref); ?>" style="text-decoration:none;color:inherit;">
                                    <?php echo sanitize(problems_humanize_column($col)); ?>
                                    <?php if ($sort === $col): ?>
                                        <?php echo $dir === 'ASC' ? '▲' : '▼'; ?>
                                    <?php endif; ?>
                                </a>
                            </th>
                        <?php endforeach; ?>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php if ($showBulkActions): ?>
                                    <td>
                                        <input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form" style="display:none;">
                                    </td>
                                <?php endif; ?>
                                <td><?php echo sanitize($row['title'] ?? ''); ?></td>
                                <td><?php echo itm_problem_status_badge($row['status'] ?? ''); ?></td>
                                <td><?php echo problems_render_owner_label($row); ?></td>
                                <td><?php echo (int)($row['incident_count'] ?? 0); ?></td>
                                <td>
                                    <?php if ((int)($row['master_ticket_id'] ?? 0) > 0): ?>
                                        <a href="view.php?id=<?php echo (int)$row['id']; ?>#master-ticket">#<?php echo (int)$row['master_ticket_id']; ?></a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo ((int)($row['known_error_flag'] ?? 0) > 0) ? 'Yes' : 'No'; ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?php echo (int)$row['id']; ?>" title="Edit">✏️</a>
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this problem record?');">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <input type="hidden" name="bulk_action" value="single_delete">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                            <?php if (function_exists('itm_crud_render_delete_hidden_audit_inputs')) {
                                                itm_crud_render_delete_hidden_audit_inputs();
                                            } ?>
                                            <button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="<?php echo count($uiColumns) + ($showBulkActions ? 2 : 1); ?>" style="text-align:center;">No records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalRows > $perPage): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:12px;">
                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=1" title="First page">⏮️</a>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page - 1; ?>" title="Previous page">◀️</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $page + 1; ?>" title="Next page">▶️</a>
                            <a class="btn btn-sm" href="?search=<?php echo urlencode($searchRaw); ?>&sort=<?php echo urlencode($sort); ?>&dir=<?php echo urlencode($dir); ?>&page=<?php echo $totalPages; ?>" title="Last page">⏭️</a>
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
