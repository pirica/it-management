<?php
/**
 * Audit Logs Module - Index
 * 
 * Provides a searchable, filterable history of all changes made within the system.
 * Shows who made a change, when, in which table, and exactly what values changed.
 * Logs are scoped to the current company context.
 */

require '../../config/config.php';
// Handle Excel/CSV database import requests from table-tools.js.
if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $itmImportRawBody = file_get_contents('php://input');
    $itmImportJsonBody = json_decode((string)$itmImportRawBody, true);
    if (is_array($itmImportJsonBody) && isset($itmImportJsonBody['import_excel_rows'])) {
        itm_handle_json_table_import($conn, 'audit_logs', (int)($company_id ?? 0));
    }
}


/**
 * Audit Logs - Search and List
 * 
 * Displays a historical record of all database changes (INSERT, UPDATE, DELETE)
 * that occurred within the current company's context. 
 * It allows IT staff to track who changed what and when.
 */

// Ensure the user has a valid company context
$companyId = (int)($_SESSION['company_id'] ?? 0);
if ($companyId <= 0) {
    http_response_code(403);
    exit('Company context is required.');
}

// Respect company-level UI policy so disabled audit logs remain hidden everywhere.
if ((int)($ui_config['enable_audit_logs'] ?? 1) !== 1) {
    http_response_code(403);
    exit('Audit logs are disabled in Settings.');
}

$messages = [];
$errors = [];
$csrfToken = itm_get_csrf_token();

if (!empty($_SESSION['audit_logs_flash_success']) && is_array($_SESSION['audit_logs_flash_success'])) {
    $messages = array_merge($messages, array_map('strval', $_SESSION['audit_logs_flash_success']));
    unset($_SESSION['audit_logs_flash_success']);
}
if (!empty($_SESSION['audit_logs_flash_error']) && is_array($_SESSION['audit_logs_flash_error'])) {
    $errors = array_merge($errors, array_map('strval', $_SESSION['audit_logs_flash_error']));
    unset($_SESSION['audit_logs_flash_error']);
}

// Keep compatibility with modules that pass one-off alerts through the URL.
$alertMessage = trim((string)($_GET['alert'] ?? ''));
if ($alertMessage !== '') {
    $messages[] = $alertMessage;
}

/**
 * Why: Deletion operations remove row data from the source table, so we must
 * capture pre-delete state first if we want meaningful DELETE audit payloads.
 */
function itm_audit_logs_collect_rows_by_ids($conn, $companyId, array $ids) {
    $rows = [];
    foreach ($ids as $id) {
        $rowId = (int)$id;
        if ($rowId <= 0) {
            continue;
        }
        $rows[$rowId] = itm_fetch_audit_record($conn, 'audit_logs', $rowId, $companyId);
    }

    return $rows;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $bulkAction = (string)($_POST['bulk_action'] ?? '');
    if ($bulkAction === 'clear_table') {
        $existingCount = 0;
        $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total_rows FROM audit_logs WHERE company_id = ?');
        if ($countStmt) {
            mysqli_stmt_bind_param($countStmt, 'i', $companyId);
            mysqli_stmt_execute($countStmt);
            $countResult = mysqli_stmt_get_result($countStmt);
            if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
                $existingCount = (int)($countRow['total_rows'] ?? 0);
            }
            mysqli_stmt_close($countStmt);
        }

        $clearStmt = mysqli_prepare($conn, 'DELETE FROM audit_logs WHERE company_id = ?');
        if ($clearStmt) {
            mysqli_stmt_bind_param($clearStmt, 'i', $companyId);
            if (mysqli_stmt_execute($clearStmt)) {
                if ($existingCount > 0) {
                    itm_log_audit(
                        $conn,
                        'audit_logs',
                        0,
                        'DELETE',
                        [
                            'operation' => 'clear_table',
                            'deleted_count' => $existingCount,
                            'company_id' => $companyId,
                        ],
                        null
                    );
                }
                $messages[] = 'All audit logs for this company were cleared.';
            } else {
                $errors[] = 'Unable to clear audit logs.';
            }
            mysqli_stmt_close($clearStmt);
        } else {
            $errors[] = 'Unable to prepare clear-table operation.';
        }
    } elseif ($bulkAction === 'bulk_delete') {
        $selectedIds = $_POST['ids'] ?? [];
        if (!is_array($selectedIds) || $selectedIds === []) {
            $errors[] = 'Select at least one row to delete.';
        } else {
            $selectedIds = array_values(array_filter(array_map('intval', $selectedIds), static fn($id) => $id > 0));
            if ($selectedIds === []) {
                $errors[] = 'Invalid row selection.';
            } else {
                $oldRowsById = itm_audit_logs_collect_rows_by_ids($conn, $companyId, $selectedIds);
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $deleteSql = 'DELETE FROM audit_logs WHERE company_id = ? AND id IN (' . $placeholders . ')';
                $deleteStmt = mysqli_prepare($conn, $deleteSql);
                if ($deleteStmt) {
                    $bindTypes = 'i' . str_repeat('i', count($selectedIds));
                    $bindValues = array_merge([$companyId], $selectedIds);
                    mysqli_stmt_bind_param($deleteStmt, $bindTypes, ...$bindValues);
                    if (mysqli_stmt_execute($deleteStmt)) {
                        $deletedCount = mysqli_stmt_affected_rows($deleteStmt);
                        if ($deletedCount > 0) {
                            foreach ($selectedIds as $selectedId) {
                                $selectedId = (int)$selectedId;
                                if ($selectedId <= 0) {
                                    continue;
                                }
                                if (!array_key_exists($selectedId, $oldRowsById) || !is_array($oldRowsById[$selectedId])) {
                                    continue;
                                }
                                itm_log_audit($conn, 'audit_logs', $selectedId, 'DELETE', $oldRowsById[$selectedId], null);
                            }
                        }
                        $messages[] = $deletedCount > 0
                            ? ('Deleted ' . (int)$deletedCount . ' selected audit log row(s).')
                            : 'No matching rows were deleted.';
                    } else {
                        $errors[] = 'Unable to delete selected rows.';
                    }
                    mysqli_stmt_close($deleteStmt);
                } else {
                    $errors[] = 'Unable to prepare bulk delete operation.';
                }
            }
        }
    }

    // Why: POST-Redirect-GET keeps alert banners visible after bulk actions and
    // avoids duplicate submissions when operators refresh the page.
    $_SESSION['audit_logs_flash_success'] = $messages;
    $_SESSION['audit_logs_flash_error'] = $errors;
    header('Location: index.php');
    exit;
}

// Extract filter parameters from the URL for persistent search/filtering
$search = trim((string)($_GET['search'] ?? ''));
$action = strtoupper(trim((string)($_GET['action_filter'] ?? '')));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$allowedActions = ['INSERT', 'UPDATE', 'DELETE'];
if (!in_array($action, $allowedActions, true)) {
    $action = '';
}

// Initialize dynamic query components
$where = ['al.company_id = ?'];
$params = [$companyId];
$types = 'i';

// Apply text search across multiple fields
if ($search !== '') {
    $where[] = '(al.table_name LIKE ? OR CAST(al.record_id AS CHAR) LIKE ? OR CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, "")) LIKE ? OR COALESCE(al.actor_username, u.username, "") LIKE ? OR COALESCE(al.actor_email, u.email, "") LIKE ?)';
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= 'sssss';
}

// Apply action filter (INSERT/UPDATE/DELETE)
if ($action !== '') {
    $where[] = 'al.action = ?';
    $params[] = $action;
    $types .= 's';
}

// Apply date range filters
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
    $where[] = 'al.changed_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
    $types .= 's';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
    $where[] = 'al.changed_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
    $types .= 's';
}

// Resolve pagination limit
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$countSql = 'SELECT COUNT(*) AS total '
          . 'FROM audit_logs al '
          . 'LEFT JOIN users u ON u.id = al.user_id '
          . 'WHERE ' . implode(' AND ', $where);
$countStmt = mysqli_prepare($conn, $countSql);
$totalRows = 0;
if ($countStmt) {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
    mysqli_stmt_execute($countStmt);
    $countResult = mysqli_stmt_get_result($countStmt);
    if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
        $totalRows = (int)($countRow['total'] ?? 0);
    }
    mysqli_stmt_close($countStmt);
}
$totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Final query construction. 
// Uses JOINs to resolve user details and Prepared Statements for security.
$sql = 'SELECT al.*, u.username, u.email, u.first_name, u.last_name '
     . 'FROM audit_logs al '
     . 'LEFT JOIN users u ON u.id = al.user_id '
     . 'WHERE ' . implode(' AND ', $where) . ' '
     . 'ORDER BY al.changed_at DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    http_response_code(500);
    exit('Unable to load audit logs.');
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];
while ($result && ($row = mysqli_fetch_assoc($result))) {
    $rows[] = $row;
}
mysqli_stmt_close($stmt);

/**
 * Utility to truncate long JSON strings for cleaner table display.
 */
function itm_audit_preview($text, $limit = 120) {
    $text = trim((string)$text);
    if ($text === '') {
        return '—';
    }

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return mb_substr($text, 0, $limit) . '...';
}

/**
 * Normalize audit payload text so empty-ish values render consistently.
 *
 * Why: Audit triggers can store blank strings or literal "null", and showing
 * those as real payloads confuses operators when they expect "no value".
 */
function itm_audit_normalize_value($text) {
    $text = trim((string)$text);
    if ($text === '' || strcasecmp($text, 'null') === 0) {
        return '—';
    }

    return $text;
}

/**
 * Provide action-aware empty-state messaging for old/new payload sections.
 *
 * Why: INSERT events legitimately have no previous values (and DELETE events have
 * no new values), so plain dashes can look like missing data defects.
 */
function itm_audit_describe_payload($action, $normalizedValue, $isOldValue) {
    if ($normalizedValue !== '—') {
        return $normalizedValue;
    }

    $action = strtoupper(trim((string)$action));
    if ($isOldValue && $action === 'INSERT') {
        return '— Not applicable for INSERT events.';
    }
    if (!$isOldValue && $action === 'DELETE') {
        return '— Not applicable for DELETE events.';
    }

    return '—';
}

$moduleListHeading = '🧾 Audit Logs';
$newButtonPosition = (string)($ui_config['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .audit-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
        .audit-toolbar h1 { margin:0; font-size:1.5rem; font-weight:700; }
        .audit-filters form { display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto auto; gap:10px; align-items:end; }
        .audit-kpis { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-bottom:14px; }
        .audit-kpi { border:1px solid var(--border); border-radius:10px; padding:10px 12px; background:var(--input-bg); }
        .audit-kpi .label { font-size:12px; opacity:.8; margin-bottom:4px; }
        .audit-kpi .value { font-size:18px; font-weight:700; }
        .audit-table-wrap { overflow-x:auto; }
        .audit-row-chip { display:inline-block; padding:4px 8px; border-radius:999px; font-size:11px; font-weight:700; border:1px solid transparent; }
        .audit-row-chip.insert { background:#e8f8ee; border-color:#9cd8b1; color:#18794e; }
        .audit-row-chip.update { background:#eef4ff; border-color:#9eb8ee; color:#1d4f91; }
        .audit-row-chip.delete { background:#fdecec; border-color:#f0b6b6; color:#a52727; }
        .audit-user { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .audit-summary { display:grid; gap:8px; }
        .audit-json { white-space:pre-wrap; word-break:break-word; max-width:520px; margin:0; font-size:12px; line-height:1.4; background:var(--input-bg); border:1px solid var(--border); border-radius:8px; padding:10px; }
        @media (max-width:1080px) { .audit-filters form { grid-template-columns:1fr 1fr; } .audit-kpis { grid-template-columns:1fr 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>

        <div class="content">
            <div class="audit-toolbar" data-itm-new-button-managed="server">
                <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <a href="index.php" class="btn btn-primary">🔄 Refresh</a>
                <?php else: ?>
                    <span aria-hidden="true"></span>
                <?php endif; ?>
                <h1><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <a href="index.php" class="btn btn-primary">🔄 Refresh</a>
                <?php else: ?>
                    <span aria-hidden="true"></span>
                <?php endif; ?>
            </div>

            <?php foreach ($messages as $message): ?>
                <div class="alert alert-success" style="margin-bottom:10px;"><?php echo sanitize($message); ?></div>
            <?php endforeach; ?>
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger" style="margin-bottom:10px;"><?php echo sanitize($error); ?></div>
            <?php endforeach; ?>

            <div class="card" style="margin-bottom:16px;">
                <form id="bulk-delete-form" method="POST" action="index.php" style="display:flex;gap:8px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                    <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                </form>
            </div>

            <!-- SEARCH AND FILTER FORM -->
            <div class="card audit-filters" style="margin-bottom:16px;">
                <form method="GET">
                    <div class="form-group" style="margin:0;">
                        <label>Search</label>
                        <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Table, record id, or user name">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Action</label>
                        <select name="action_filter">
                            <option value="">All</option>
                            <?php foreach ($allowedActions as $opt): ?>
                                <option value="<?php echo sanitize($opt); ?>" <?php echo $action === $opt ? 'selected' : ''; ?>><?php echo sanitize($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="<?php echo sanitize($dateFrom); ?>">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="<?php echo sanitize($dateTo); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn">🔙</a>
                </form>
            </div>

            <div class="audit-kpis">
                <div class="audit-kpi"><div class="label">Rows on Screen</div><div class="value"><?php echo (int)count($rows); ?></div></div>
                <div class="audit-kpi"><div class="label">Insert Events</div><div class="value"><?php echo (int)count(array_filter($rows, static fn($r) => (($r['action'] ?? '') === 'INSERT'))); ?></div></div>
                <div class="audit-kpi"><div class="label">Update Events</div><div class="value"><?php echo (int)count(array_filter($rows, static fn($r) => (($r['action'] ?? '') === 'UPDATE'))); ?></div></div>
                <div class="audit-kpi"><div class="label">Delete Events</div><div class="value"><?php echo (int)count(array_filter($rows, static fn($r) => (($r['action'] ?? '') === 'DELETE'))); ?></div></div>
            </div>

            <!-- LOG DATA TABLE -->
            <div class="card audit-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:36px;">Select <input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th>
                            <th>Date &amp; Time</th>
                            <th>User</th>
                            <th>Table Name</th>
                            <th>Record ID</th>
                            <th>Action</th>
                            <th>Change Summary</th>
                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="8">No audit logs found for the selected filters.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            // Determine the most appropriate user display name
                            $userName = trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
                            $userEmail = trim((string)($row['actor_email'] ?? $row['email'] ?? ''));
                            if ($userName === '') {
                                $userName = trim((string)($row['actor_username'] ?? ''));
                            }
                            if ($userName === '' && $userEmail !== '') {
                                $userName = $userEmail;
                            }
                            if ($userName === '') {
                                $userName = trim((string)($row['username'] ?? ''));
                            }
                            if ($userName === '') {
                                $userName = $row['user_id'] ? ('User #' . (int)$row['user_id']) : 'System';
                            }

                            // Styling based on action type
                            $actionClass = 'update';
                            if (($row['action'] ?? '') === 'INSERT') {
                                $actionClass = 'insert';
                            } elseif (($row['action'] ?? '') === 'DELETE') {
                                $actionClass = 'delete';
                            }

                            $oldValues = itm_audit_normalize_value($row['old_values'] ?? '');
                            $newValues = itm_audit_normalize_value($row['new_values'] ?? '');
                            $oldValuesDisplay = itm_audit_describe_payload($row['action'] ?? '', $oldValues, true);
                            $newValuesDisplay = itm_audit_describe_payload($row['action'] ?? '', $newValues, false);
                            $previewText = 'Old: ' . itm_audit_preview($oldValuesDisplay, 80) . ' | New: ' . itm_audit_preview($newValuesDisplay, 80);
                            ?>
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="<?php echo (int)($row['id'] ?? 0); ?>" form="bulk-delete-form"></td>
                                <td><?php echo sanitize((string)$row['changed_at']); ?></td>
                                <td class="audit-user" title="<?php echo sanitize($userEmail !== '' ? ($userName . ' <' . $userEmail . '>') : $userName); ?>">
                                    <?php echo sanitize($userName); ?>
                                    <?php if ($userEmail !== ''): ?>
                                        <br><small><?php echo sanitize($userEmail); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo sanitize((string)$row['table_name']); ?></td>
                                <td><?php echo (int)$row['record_id']; ?></td>
                                <td><span class="audit-row-chip <?php echo sanitize($actionClass); ?>"><?php echo sanitize((string)$row['action']); ?></span></td>
                                <td>
                                    <div class="audit-summary">
                                        <span><?php echo sanitize($previewText); ?></span>
                                        <details>
                                            <summary class="btn btn-sm btn-primary" style="cursor:pointer;">Click to see more</summary>
                                            <div class="audit-json"><strong>Old Values</strong><br><?php echo sanitize($oldValuesDisplay); ?></div>
                                            <div class="audit-json"><strong>New Values</strong><br><?php echo sanitize($newValuesDisplay); ?></div>
                                        </details>
                                    </div>
                                </td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm btn-primary" href="view.php?id=<?php echo (int)($row['id'] ?? 0); ?>" title="View audit log">🔎</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($totalPages > 1): ?>
                    <div style="display:flex;justify-content:center;gap:8px;margin-top:14px;flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(http_build_query(['search' => $search, 'action_filter' => $action, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $page - 1])); ?>">« Prev</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.85;">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a class="btn btn-sm" href="?<?php echo sanitize(http_build_query(['search' => $search, 'action_filter' => $action, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $page + 1])); ?>">Next »</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAllRows = document.getElementById('select-all-rows');
    const bulkDeleteForm = document.getElementById('bulk-delete-form');
    const toggleButton = bulkDeleteForm ? bulkDeleteForm.querySelector('button[name="bulk_action"][value="bulk_delete"]') : null;
    const rowCheckboxes = bulkDeleteForm ? document.querySelectorAll('input[name="ids[]"][form="' + bulkDeleteForm.id + '"]') : [];
    const deleteCells = Array.from(rowCheckboxes).map(function (checkbox) { return checkbox.closest('td'); }).filter(Boolean);
    const selectAllHeaderCell = selectAllRows ? selectAllRows.closest('th') : null;
    let selectionMode = false;

    if (!selectAllRows || !bulkDeleteForm || !toggleButton) {
        return;
    }

    /**
     * Keep the delete-selection column hidden until explicitly enabled.
     *
     * Why: Audit logs are review-heavy, so hiding checkboxes by default keeps the
     * table compact and mirrors the proven System Access interaction pattern.
     */
    function setSelectionVisibility(visible) {
        if (selectAllHeaderCell) {
            selectAllHeaderCell.style.display = visible ? '' : 'none';
        }
        deleteCells.forEach(function (cell) {
            cell.style.display = visible ? '' : 'none';
        });
    }

    selectAllRows.addEventListener('change', function () {
        rowCheckboxes.forEach(function (checkbox) {
            checkbox.checked = selectAllRows.checked;
        });
    });

    setSelectionVisibility(false);
    bulkDeleteForm.addEventListener('submit', function (event) {
        if (event.submitter !== toggleButton) {
            return;
        }

        if (!selectionMode) {
            event.preventDefault();
            selectionMode = true;
            setSelectionVisibility(true);
            toggleButton.textContent = 'Delete Selected';
            return;
        }

        const anySelected = Array.from(rowCheckboxes).some(function (checkbox) {
            return checkbox.checked;
        });

        if (!anySelected) {
            event.preventDefault();
            alert('Please select at least one record to delete.');
            return;
        }

        if (!confirm('Delete selected records?')) {
            event.preventDefault();
        }
    });
});
</script>
</body>
</html>
