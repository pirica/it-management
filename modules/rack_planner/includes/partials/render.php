<?php
// View partial: mutating POST handlers call itm_require_post_csrf() in ../handlers.php.
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
    $crud_title = 'Partials';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        /* Rack Visualizer Styles from image.png */
        .rack-visualizer-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: var(--bg-secondary);
            padding: 40px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-top: 20px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }
        .rack-visualizer-top {
            width: min(600px, 100%);
            height: 35px;
            background: #f2f2f2;
            border: 1px solid #d9d9d9;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            position: relative;
        }
        .rack-visualizer-top::after {
            content: '';
            position: absolute;
            top: 5px;
            left: 5px;
            right: 5px;
            bottom: 0;
            border: 1px solid #e0e0e0;
            border-bottom: none;
            border-radius: 2px 2px 0 0;
        }
        .rack-visualizer-frame {
            width: min(600px, 100%);
            background: #fff;
            border: 1px solid #d9d9d9;
            padding: 20px 50px;
            position: relative;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.02);
        }
        .rack-visualizer-rail {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 24px;
            background: #e6e6e6;
            border: 1px solid #d0d0d0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 2px;
            gap: 0;
        }
        .rack-visualizer-rail-left { left: 55px; }
        .rack-visualizer-rail-right { right: 55px; }
        .rack-visualizer-rail-unit {
            width: 100%;
            height: 40px;
            background-image: url('../../assets/unit_empty.svg');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 100% 100%;
        }
        .rack-visualizer-content {
            border: 1px solid #f0f0f0;
            min-height: 100px;
        }
        .rack-visualizer-u {
            height: 40px;
            border-bottom: 1px dashed #d9d9d9;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            color: #ccc;
            font-size: 10px;
            background-image: url('../../assets/unit_empty.svg');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 100% 100%;
        }
        .rack-visualizer-u.has-device {
            color: #1f2937;
        }
        .rack-visualizer-u.has-device-anchor {
            z-index: 2;
            cursor: move;
        }
        .rack-visualizer-u.has-device-anchor.has-device-image::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            height: calc(40px - 1px);
            background-image: var(--rack-device-image);
            background-repeat: no-repeat;
            background-position: center;
            background-size: 100% 100%;
            z-index: 1;
            pointer-events: none;
        }
        .rack-visualizer-u.has-device-anchor.has-device-image[data-device-size="2"]::after {
            height: calc(80px - 2px);
        }
        .rack-visualizer-u.has-device-anchor.has-device-image.has-device-image-catalog[data-device-size="2"]::after {
            background-image: var(--rack-device-image), var(--rack-device-image);
            background-repeat: no-repeat, no-repeat;
            background-position: center top, center bottom;
            background-size: 100% 50%, 100% 50%;
        }
        .rack-visualizer-u-label {
            display: none;
            max-width: 88%;
            font-size: 11px;
            line-height: 1.2;
            color: #1f2937;
            background: rgba(255,255,255,0.85);
            border: 1px solid #d1d5db;
            border-radius: 999px;
            padding: 3px 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            pointer-events: none;
            position: relative;
            z-index: 4;
        }
        .rack-visualizer-u.has-device .rack-visualizer-u-label {
            display: inline-block;
        }
        .rack-visualizer-u.has-device-anchor[data-device-size="2"] .rack-visualizer-u-label {
            position: absolute;
            left: 50%;
            top: calc(40px - 1px);
            transform: translate(-50%, -50%);
            width: auto;
            max-width: 88%;
            height: auto;
            display: inline-block;
            white-space: nowrap;
            text-align: center;
            border-radius: 999px;
            z-index: 4;
        }
        .rack-visualizer-u::before {
            content: attr(data-u);
            position: absolute;
            left: -40px;
            color: #999;
        }
        .rack-visualizer-base {
            width: min(600px, 100%);
            height: 70px;
            background: #f2f2f2;
            border: 1px solid #d9d9d9;
            border-top: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 0 0 4px 4px;
        }
        .rack-visualizer-vents {
            display: flex;
            gap: 6px;
        }
        .rack-visualizer-vent {
            width: 6px;
            height: 35px;
            background: #d9d9d9;
            border-radius: 3px;
        }
        .rack-visualizer-feet {
            width: min(520px, 100%);
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
        }
        .rack-visualizer-foot {
            width: 90px;
            height: 12px;
            background: #e6e6e6;
            border: 1px solid #d9d9d9;
            border-top: none;
            border-radius: 0 0 4px 4px;
        }

        .rack-planner-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .rack-unit-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 15px;
        }
        .rack-unit-modal-overlay.is-open { display: flex; }
        .rack-unit-modal {
            width: min(520px, 100%);
            background: #fff;
            border: 1px solid #d9d9d9;
            border-radius: 8px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
        }
        .rack-unit-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 16px;
            border-bottom: 1px solid #e5e5e5;
            background: #f5f5f5;
            font-weight: 600;
        }
        .rack-unit-modal-close {
            border: 0;
            background: transparent;
            font-size: 20px;
            cursor: pointer;
            line-height: 1;
        }
        .rack-unit-modal-body { padding: 14px 16px 18px; }
        .rack-unit-modal-row { display: flex; align-items: center; gap: 10px; }
        .rack-unit-modal-row label { font-weight: 600; min-width: 52px; }
        .rack-unit-modal-row.is-hidden { display: none; }
        .rack-unit-modal-row input[type="text"] { flex: 1; min-width: 0; }
        .rack-unit-modal-note { margin: 8px 0 0; font-size: 12px; color: #6b7280; }
        .rack-unit-modal-actions { margin-top: 12px; display: flex; justify-content: flex-end; }
        .rack-unit-modal-actions .btn[hidden] { display: none !important; }
        .rack-visualizer-total {
            margin-top: 12px;
            font-weight: 700;
            color: #111827;
            background: #f9fafb;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 12px;
        }
        .rack-visualizer-export-actions {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .rack-visualizer-u.rack-drop-target {
            outline: 2px solid #2563eb;
            outline-offset: -2px;
        }
        .rack-drag-trash {
            position: fixed;
            right: 18px;
            bottom: 18px;
            min-width: 120px;
            height: 46px;
            border-radius: 12px;
            border: 1px solid #ef4444;
            background: #fef2f2;
            color: #991b1b;
            display: none;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.18);
            z-index: 10001;
            user-select: none;
        }
        .rack-drag-trash.is-visible {
            display: flex;
        }
        .rack-drag-trash.is-active {
            background: #dc2626;
            color: #fff;
            border-color: #b91c1c;
        }
        @media (max-width: 768px) {
            .rack-visualizer-container { padding: 16px; }
            .rack-planner-header { flex-wrap: wrap; gap: 12px; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo sanitize($success_msg); ?></div>
            <?php endif; ?>

            <?php if ($crud_action === 'index' || $crud_action === 'list_all'): ?>
                <?php
                $whereClause = " WHERE company_id = ? AND deleted_at IS NULL";
                $params = [$company_id];
                $types = "i";

                if ($search !== '') {
                    $whereClause .= " AND (name LIKE ? OR notes LIKE ?)";
                    $searchParam = "%$search%";
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                    $types .= "ss";
                }

                // itm_is_safe_identifier check for sort column
                $allowedSort = ['id', 'name', 'rack_units', 'status_id', 'active'];
                if (!in_array($sort, $allowedSort) || !itm_is_safe_identifier($sort)) {
                    $sort = 'id';
                }

                $sqlCount = "SELECT COUNT(*) as total FROM rack_planner $whereClause";
                $stmtCount = mysqli_prepare($conn, $sqlCount);
                $totalRows = 0;
                if ($stmtCount) {
                    mysqli_stmt_bind_param($stmtCount, $types, ...$params);
                    mysqli_stmt_execute($stmtCount);
                    $resCount = mysqli_stmt_get_result($stmtCount);
                    if ($countRow = mysqli_fetch_assoc($resCount)) {
                        $totalRows = (int)$countRow['total'];
                    }
                    mysqli_stmt_close($stmtCount);
                }

                $totalPages = max(1, (int)ceil($totalRows / $perPage));
                if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }
                $showBulkActions = ($totalRows >= $perPage);
                $rackPlannerListColspan = $showBulkActions ? 6 : 5;
                ?>

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

                <?php if ($showBulkActions): ?>
                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;" data-itm-bulk-delete-bound="1">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
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
                        <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                            <label for="moduleSearch">Search (all fields)</label>
                            <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($search); ?>" placeholder="Type to search records...">
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
                                <?php if ($showBulkActions): ?><th style="width:36px;"><input type="checkbox" id="select-all-rows" aria-label="Select all rows"></th><?php endif; ?>
                                <?php
                                $rackPlannerSortQueryBase = 'search=' . urlencode($search) . '&page=' . (int)$page;
                                $rackPlannerSortLinkStyle = 'text-decoration:none;color:inherit;';
                                ?>
                                <th>
                                    <a href="?<?php echo $rackPlannerSortQueryBase; ?>&sort=name&dir=<?php echo ($sort === 'name' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>" style="<?php echo $rackPlannerSortLinkStyle; ?>">
                                        Name<?php if ($sort === 'name'): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?<?php echo $rackPlannerSortQueryBase; ?>&sort=rack_units&dir=<?php echo ($sort === 'rack_units' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>" style="<?php echo $rackPlannerSortLinkStyle; ?>">
                                        Units<?php if ($sort === 'rack_units'): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                    </a>
                                </th>
                                <th>Notes</th>
                                <th>
                                    <a href="?<?php echo $rackPlannerSortQueryBase; ?>&sort=status_id&dir=<?php echo ($sort === 'status_id' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>" style="<?php echo $rackPlannerSortLinkStyle; ?>">
                                        Status<?php if ($sort === 'status_id'): ?> <?php echo $dir === 'ASC' ? '▲' : '▼'; ?><?php endif; ?>
                                    </a>
                                </th>
                                <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT *, (SELECT name FROM rack_statuses WHERE id = status_id) AS status_name, active FROM rack_planner $whereClause ORDER BY $sort $dir LIMIT ?, ?";
                            $stmt = mysqli_prepare($conn, $sql);
                            if ($stmt) {
                                $currentParams = $params;
                                $currentParams[] = $offset;
                                $currentParams[] = $perPage;
                                $currentTypes = $types . "ii";
                                mysqli_stmt_bind_param($stmt, $currentTypes, ...$currentParams);
                                mysqli_stmt_execute($stmt);
                                $res = mysqli_stmt_get_result($stmt);
                                if ($res && mysqli_num_rows($res) > 0):
                                    while ($row = mysqli_fetch_assoc($res)):
                            ?>
                                <tr>
                                    <?php if ($showBulkActions): ?><td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td><?php endif; ?>
                                    <td><?php echo sanitize($row['name']); ?></td>
                                    <td><?php echo (int)$row['rack_units']; ?> U</td>
                                    <td><?php echo sanitize($row['notes']); ?></td>
                                    <td>
                                        <?php
                                        $statusName = $row['status_name'] ?? 'Active';
                                        $badgeClass = 'badge';
                                        if (stripos($statusName, 'Active') !== false) {
                                            $badgeClass = 'badge badge-success';
                                        } elseif (stripos($statusName, 'Decommissioned') !== false) {
                                            $badgeClass = 'badge badge-danger';
                                        } elseif (stripos($statusName, 'Maintenance') !== false || stripos($statusName, 'Full') !== false) {
                                            $badgeClass = 'badge badge-warning';
                                        }
                                        ?>
                                        <span class="<?php echo $badgeClass; ?>"><?php echo sanitize($statusName); ?></span>
                                    </td>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <div class="itm-actions-wrap">
                                            <a class="btn btn-sm" href="view.php?id=<?php echo $row['id']; ?>">🔎</a>
                                            <a class="btn btn-sm" href="edit.php?id=<?php echo $row['id']; ?>">✏️</a>
                                            <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this plan?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button class="btn btn-sm btn-danger" type="submit">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                                    endwhile;
                                else:
                            ?>
                                <tr><td colspan="<?php echo (int)$rackPlannerListColspan; ?>" style="text-align:center;">No rack plans found.</td></tr>
                            <?php
                                endif;
                                mysqli_stmt_close($stmt);
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalRows > $perPage): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                        <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                        <div style="display:flex;gap:5px;">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm" title="◀️ Previous">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm" title="▶️ Next">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($totalRows === 0): ?>
                    <div class="card" style="margin-top:12px;">
                        <form method="POST" style="display:flex;justify-content:center;">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                        </form>
                    </div>
                <?php endif; ?>

            <?php elseif ($crud_action === 'create' || $crud_action === 'edit'): ?>
                <h1><?php echo $crud_action === 'create' ? 'New' : 'Edit'; ?> Rack Plan</h1>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$data['id']; ?>">
                    <input type="hidden" name="layout_json" id="layoutJsonInput" value="<?php echo sanitize($data['layout_json']); ?>">

                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="<?php echo sanitize($data['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Rack Units</label>
                        <input type="number" name="rack_units" value="<?php echo (int)$data['rack_units']; ?>" min="1" max="100">
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes"><?php echo sanitize($data['notes']); ?></textarea>
                    </div>

                    <input type="hidden" name="active" value="1">

                    <div class="form-group">
                        <label>Status</label>
                        <select name="status_id"
                                data-addable-select="1"
                                data-add-table="rack_statuses"
                                data-add-friendly="status"
                                data-add-id-col="id"
                                data-add-label-col="name"
                                data-add-company-scoped="1"
                                data-add-value-label="Name"
                                required>
                            <option value="">-- Select --</option>
                            <?php
                            $statusSql = "SELECT id, name FROM rack_statuses WHERE company_id = ? ORDER BY name ASC";
                            $statusStmt = mysqli_prepare($conn, $statusSql);
                            if ($statusStmt) {
                                mysqli_stmt_bind_param($statusStmt, 'i', $company_id);
                                mysqli_stmt_execute($statusStmt);
                                $statusRes = mysqli_stmt_get_result($statusStmt);
                                while ($statusRow = mysqli_fetch_assoc($statusRes)) {
                                    $selected = ((int)$statusRow['id'] === (int)$data['status_id']) ? 'selected' : '';
                                    echo '<option value="' . (int)$statusRow['id'] . '" ' . $selected . '>' . sanitize($statusRow['name']) . '</option>';
                                }
                                mysqli_stmt_close($statusStmt);
                            }
                            ?>
                            <option value="__add_new__">➕</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" title="Save">💾</button>
                        <a href="index.php" class="btn" title="Cancel">🔙</a>
                    </div>
                </form>

                <div class="rack-visualizer-export-scope" id="rackExportScope">
                    <div class="rack-visualizer-container">
                        <div class="rack-visualizer-top"></div>
                        <div class="rack-visualizer-frame">
                            <div class="rack-visualizer-rail rack-visualizer-rail-left">
                                <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                    <div class="rack-visualizer-rail-unit" aria-hidden="true"></div>
                                <?php endfor; ?>
                            </div>
                            <div class="rack-visualizer-rail rack-visualizer-rail-right">
                                <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                    <div class="rack-visualizer-rail-unit" aria-hidden="true"></div>
                                <?php endfor; ?>
                            </div>
                            <div class="rack-visualizer-content">
                                <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                    <?php $assignment = $rackAssignmentsByUnit[$u] ?? null; ?>
                                    <div
                                        class="rack-visualizer-u<?php echo $assignment ? ' has-device' : ''; ?>"
                                        data-u="<?php echo $u; ?>"
                                        data-device-code="<?php echo $assignment ? sanitize($assignment['code']) : ''; ?>"
                                        data-device-label="<?php echo $assignment ? sanitize($assignment['label']) : ''; ?>"
                                        data-device-size="<?php echo $assignment ? (int)$assignment['size'] : ''; ?>"
                                        data-device-start-u="<?php echo $assignment ? (int)$assignment['start_u'] : ''; ?>"
                                        data-device-price="<?php echo ($assignment && isset($assignment['price']) && is_numeric($assignment['price'])) ? number_format((float)$assignment['price'], 2, '.', '') : ''; ?>"
                                    >
                                        <span class="rack-visualizer-u-label"><?php echo $assignment ? sanitize($assignment['label']) : ''; ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="rack-visualizer-base">
                            <div class="rack-visualizer-vents">
                                <?php for($i=0; $i<30; $i++): ?>
                                    <div class="rack-visualizer-vent"></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="rack-visualizer-feet">
                            <div class="rack-visualizer-foot"></div>
                            <div class="rack-visualizer-foot"></div>
                        </div>
                    </div>
                    <div class="rack-visualizer-total">
                        TOTAL: <span id="rackTotalAmount"><?php echo number_format($layoutTotalAmount, 2, '.', ','); ?></span>
                        <div class="rack-visualizer-export-actions">
                            <button type="button" class="btn btn-sm" id="rackSaveImageBtn">Save as Image</button>
                            <button type="button" class="btn btn-sm" id="rackExportPdfBtn">PDF Export</button>
                            <button type="button" class="btn btn-sm" id="rackExportExcelBtn">Excel Export</button>
                        </div>
                    </div>
                </div>
                <div class="rack-unit-modal-overlay" id="rackUnitModal" aria-hidden="true">
                    <div class="rack-unit-modal" role="dialog" aria-modal="true" aria-labelledby="rackUnitModalTitle">
                        <div class="rack-unit-modal-header">
                            <p id="rackUnitModalTitle">Component</p>
                            <button type="button" class="rack-unit-modal-close" id="rackUnitModalClose" aria-label="Close">&times;</button>
                        </div>
                        <div class="rack-unit-modal-body">
                            <div class="rack-unit-modal-row">
                                <label for="unitTypeSelect">Type</label>
                                <select name="unitTypeSelect" id="unitTypeSelect" class="block w-full rounded-md bg-full-white border-0 py-1.5 ring-1 ring-gray-300 ring-inset focus:ring-1 focus:ring-brand-blue text-sm">
                                    <option value="">- Choose -</option>
                                    <?php foreach ($componentGroups as $groupLabel => $groupCodes): ?>
                                        <optgroup label="<?php echo sanitize($groupLabel); ?>">
                                            <?php foreach ($groupCodes as $groupCode): ?>
                                                <?php if (!isset($componentCatalog[$groupCode])) { continue; } ?>
                                                <?php $meta = $componentCatalog[$groupCode]; ?>
                                                <option
                                                    value="<?php echo sanitize($groupCode); ?>"
                                                    data-size="<?php echo (int)$meta['size']; ?>"
                                                    data-label="<?php echo sanitize($meta['label']); ?>"
                                                    data-price=""
                                                >
                                                    <?php echo sanitize($meta['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                    <?php if (!empty($catalogOptions)): ?>
                                        <optgroup label="Catalog">
                                            <?php foreach ($catalogOptions as $catalogOption): ?>
                                                <?php
                                                $catalogCode = trim((string)($catalogOption['code'] ?? ''));
                                                if ($catalogCode === '') { continue; }
                                                $catalogLabel = trim((string)($catalogOption['select_text'] ?? $catalogOption['label'] ?? $catalogCode));
                                                $catalogSize = ((int)($catalogOption['size'] ?? 1) === 2) ? 2 : 1;
                                                $catalogPriceRaw = (isset($catalogOption['price_value']) && is_numeric($catalogOption['price_value'])) ? number_format((float)$catalogOption['price_value'], 2, '.', '') : '';
                                                ?>
                                                <option
                                                    value="<?php echo sanitize($catalogCode); ?>"
                                                    data-size="<?php echo $catalogSize; ?>"
                                                    data-label="<?php echo sanitize($catalogLabel); ?>"
                                                    data-price="<?php echo sanitize($catalogPriceRaw); ?>"
                                                    data-source="catalog"
                                                >
                                                    <?php echo sanitize($catalogLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                            <option value="__add_new__" data-source="catalog_add">➕</option>
                                        </optgroup>
                                    <?php endif; ?>
                                    <?php if (!empty($equipmentPickerOptions)): ?>
                                        <optgroup label="Equipment">
                                            <?php foreach ($equipmentPickerOptions as $equipmentOption): ?>
                                                <?php
                                                $equipmentCode = trim((string)($equipmentOption['code'] ?? ''));
                                                if ($equipmentCode === '') { continue; }
                                                $equipmentLabel = trim((string)($equipmentOption['select_text'] ?? $equipmentOption['label'] ?? $equipmentCode));
                                                $equipmentSize = ((int)($equipmentOption['size'] ?? 1) === 2) ? 2 : 1;
                                                $equipmentPriceRaw = (isset($equipmentOption['price_value']) && is_numeric($equipmentOption['price_value'])) ? number_format((float)$equipmentOption['price_value'], 2, '.', '') : '';
                                                ?>
                                                <option
                                                    value="<?php echo sanitize($equipmentCode); ?>"
                                                    data-size="<?php echo $equipmentSize; ?>"
                                                    data-label="<?php echo sanitize($equipmentLabel); ?>"
                                                    data-price="<?php echo sanitize($equipmentPriceRaw); ?>"
                                                    data-source="equipment_picker"
                                                >
                                                    <?php echo sanitize($equipmentLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="rack-unit-modal-actions">
                                <button type="button" class="btn btn-sm" id="rackEditPriceBtn" hidden>Edit Price</button>
                                <button type="button" class="btn btn-sm btn-primary" id="rackSaveCatalogBtn" hidden title="Save">💾</button>
                            </div>
                            <div class="rack-unit-modal-row is-hidden" id="placeholderMessageRow">
                                <label for="placeholderMessageInput">Message</label>
                                <input type="text" id="placeholderMessageInput" placeholder="Write placeholder text">
                                <button type="button" class="btn btn-sm" id="placeholderApplyBtn">Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rack-unit-modal-overlay" id="rackPriceModal" aria-hidden="true">
                    <div class="rack-unit-modal" role="dialog" aria-modal="true" aria-labelledby="rackPriceModalTitle">
                        <div class="rack-unit-modal-header">
                            <p id="rackPriceModalTitle">Insert Price (optional)</p>
                            <button type="button" class="rack-unit-modal-close" id="rackPriceModalClose" aria-label="Close">&times;</button>
                        </div>
                        <div class="rack-unit-modal-body">
                            <div class="rack-unit-modal-row">
                                <label for="rackPriceInput">Price</label>
                                <input type="text" id="rackPriceInput" placeholder="e.g. 21.21">
                            </div>
                            <p class="rack-unit-modal-note">Leave empty to skip. Empty component cannot have a price.</p>
                            <div class="rack-unit-modal-actions">
                                <button type="button" class="btn btn-sm" id="rackPriceSkipBtn">Skip</button>
                                <button type="button" class="btn btn-sm btn-primary" id="rackPriceSaveBtn" title="Save">💾</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="rackDragTrash" class="rack-drag-trash" aria-hidden="true">Delete</div>

            <?php elseif ($crud_action === 'view'): ?>
                <?php
                $rackShareAjaxUrl = 'index.php?ajax_action=create_share_session';
                $rackSharePlanId = (int)($data['id'] ?? 0);
                ?>
                <div class="rack-planner-header">
                    <h1>View Rack Plan: <?php echo sanitize($data['name']); ?></h1>
                    <div>
                        <a href="edit.php?id=<?php echo $data['id']; ?>" class="btn btn-primary" title="Edit">✏️</a>
                        <a href="index.php" class="btn" title="Back">🔙</a>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 20px;">
                    <table>
                        <tbody>
                            <tr>
                                <th style="width:240px;">Name</th>
                                <td><?php echo sanitize($data['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?php
                                    $statusName = $data['status_name'] ?? '';
                                    if ($statusName !== ''):
                                        $badgeClass = 'badge';
                                        if (stripos($statusName, 'Active') !== false) {
                                            $badgeClass = 'badge badge-success';
                                        } elseif (stripos($statusName, 'Decommissioned') !== false) {
                                            $badgeClass = 'badge badge-danger';
                                        } elseif (stripos($statusName, 'Maintenance') !== false || stripos($statusName, 'Full') !== false) {
                                            $badgeClass = 'badge badge-warning';
                                        }
                                        ?>
                                        <span class="<?php echo $badgeClass; ?>"><?php echo sanitize($statusName); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Units</th>
                                <td><?php echo (int)$data['rack_units']; ?> U</td>
                            </tr>
                            <tr>
                                <th>Notes</th>
                                <td><?php echo sanitize($data['notes']); ?></td>
                            </tr>
                            <?php itm_crud_render_view_audit_meta_rows($conn, (int)$company_id, $data); ?>
                        </tbody>
                    </table>
                </div>

                <div class="rack-visualizer-export-scope" id="rackExportScope">
                    <div class="rack-visualizer-container">
                        <div class="rack-visualizer-top"></div>
                        <div class="rack-visualizer-frame">
                            <div class="rack-visualizer-rail rack-visualizer-rail-left">
                                <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                    <div class="rack-visualizer-rail-unit" aria-hidden="true"></div>
                                <?php endfor; ?>
                            </div>
                            <div class="rack-visualizer-rail rack-visualizer-rail-right">
                                <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                    <div class="rack-visualizer-rail-unit" aria-hidden="true"></div>
                                <?php endfor; ?>
                            </div>
                            <div class="rack-visualizer-content">
                                <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                    <?php $assignment = $rackAssignmentsByUnit[$u] ?? null; ?>
                                    <div
                                        class="rack-visualizer-u<?php echo $assignment ? ' has-device' : ''; ?>"
                                        data-u="<?php echo $u; ?>"
                                        data-device-code="<?php echo $assignment ? sanitize($assignment['code']) : ''; ?>"
                                        data-device-label="<?php echo $assignment ? sanitize($assignment['label']) : ''; ?>"
                                        data-device-size="<?php echo $assignment ? (int)$assignment['size'] : ''; ?>"
                                        data-device-start-u="<?php echo $assignment ? (int)$assignment['start_u'] : ''; ?>"
                                        data-device-price="<?php echo ($assignment && isset($assignment['price']) && is_numeric($assignment['price'])) ? number_format((float)$assignment['price'], 2, '.', '') : ''; ?>"
                                    >
                                        <span class="rack-visualizer-u-label"><?php echo $assignment ? sanitize($assignment['label']) : ''; ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="rack-visualizer-base">
                            <div class="rack-visualizer-vents">
                                <?php for($i=0; $i<30; $i++): ?>
                                    <div class="rack-visualizer-vent"></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <div class="rack-visualizer-feet">
                            <div class="rack-visualizer-foot"></div>
                            <div class="rack-visualizer-foot"></div>
                        </div>
                    </div>
                    <div class="rack-visualizer-total">
                        TOTAL: <span id="rackTotalAmount"><?php echo number_format($layoutTotalAmount, 2, '.', ','); ?></span>
                        <div class="rack-visualizer-export-actions">
                            <button type="button" class="btn btn-sm" id="rackSaveImageBtn">Save as Image</button>
                            <button type="button" class="btn btn-sm" id="rackExportPdfBtn">PDF Export</button>
                            <button type="button" class="btn btn-sm" id="rackExportExcelBtn">Excel Export</button>
                            <button type="button" class="btn btn-sm" onclick="itmOpenQrShareModal('<?php echo sanitize($rackShareAjaxUrl); ?>', <?php echo $rackSharePlanId; ?>)" title="Share to device">📱</button>
                            <button type="button" class="btn btn-sm" onclick="itmOpenWhatsAppShare('<?php echo sanitize($rackShareAjaxUrl); ?>', <?php echo $rackSharePlanId; ?>, null, 'rack plan')" title="Share on WhatsApp"><img src="../../images/whatsapp.svg" alt="" width="16" height="16" style="display:block;"></button>
                            <button type="button" class="btn btn-sm" onclick="itmOpenOutlookShare('<?php echo sanitize($rackShareAjaxUrl); ?>', <?php echo $rackSharePlanId; ?>, null, 'rack plan')" title="Share on Outlook">📨</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/table-tools.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script>
const rackComponentCatalog = <?php echo json_encode($componentCatalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const rackCatalogOptions = <?php echo json_encode($catalogOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const rackEquipmentPickerOptions = <?php echo json_encode($equipmentPickerOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const rackCombinedCodeMeta = <?php echo json_encode($combinedCodeMeta, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

(function () {
    const rackExportScope = document.getElementById('rackExportScope');
    const rackSaveImageBtn = document.getElementById('rackSaveImageBtn');
    const rackExportPdfBtn = document.getElementById('rackExportPdfBtn');
    const rackExportExcelBtn = document.getElementById('rackExportExcelBtn');
    const rackPlanNameInput = document.querySelector('form.form-grid input[name="name"]');
    let rackExportBusy = false;

    function rackExtractPriceFromText(text) {
        const input = String(text || '').trim();
        if (input === '') {
            return null;
        }

        const endMatch = input.match(/(?:^|[\s:;,\-])([+-]?\d[\d.,]*)\s*(?:\u20AC|\$|usd|eur)?\s*$/i);
        if (!endMatch || endMatch.length < 2) {
            return null;
        }

        let normalized = String(endMatch[1] || '').replace(/\s+/g, '');
        if (normalized === '') {
            return null;
        }

        const hasComma = normalized.indexOf(',') !== -1;
        const hasDot = normalized.indexOf('.') !== -1;
        if (hasComma && hasDot) {
            if (normalized.lastIndexOf(',') > normalized.lastIndexOf('.')) {
                normalized = normalized.replace(/\./g, '').replace(',', '.');
            } else {
                normalized = normalized.replace(/,/g, '');
            }
        } else if (hasComma) {
            const parts = normalized.split(',');
            if (parts.length === 2 && parts[1].length <= 2) {
                normalized = parts[0].replace(/\./g, '') + '.' + parts[1];
            } else {
                normalized = normalized.replace(/,/g, '');
            }
        } else {
            normalized = normalized.replace(/,/g, '');
        }

        const value = Number(normalized);
        return Number.isFinite(value) ? value : null;
    }

    function rackExportTimestamp() {
        const d = new Date();
        const pad = function (n) { return String(n).padStart(2, '0'); };
        return d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) + '_' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds());
    }

    function rackSanitizeFilePart(value) {
        const normalized = String(value || '').trim().replace(/\s+/g, '_');
        const cleaned = normalized.replace(/[^A-Za-z0-9_-]/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '');
        return cleaned !== '' ? cleaned : 'rack_planner';
    }

    function rackGetCurrentPlanName() {
        if (rackPlanNameInput && String(rackPlanNameInput.value || '').trim() !== '') {
            return String(rackPlanNameInput.value || '').trim();
        }
        const heading = document.querySelector('h1');
        if (!heading) {
            return 'rack_planner';
        }
        const headingText = String(heading.textContent || '').trim();
        if (headingText === '') {
            return 'rack_planner';
        }
        const separatorIndex = headingText.indexOf(':');
        if (separatorIndex >= 0) {
            const afterColon = headingText.slice(separatorIndex + 1).trim();
            if (afterColon !== '') {
                return afterColon;
            }
        }
        return headingText;
    }

    function rackBuildExportFilename(extension) {
        const safeName = rackSanitizeFilePart(rackGetCurrentPlanName());
        return safeName + '_' + rackExportTimestamp() + '.' + extension;
    }

    function rackDownloadBlob(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 3000);
    }

    async function rackCaptureScopeCanvas() {
        if (!rackExportScope) {
            throw new Error('Rack export scope not found.');
        }
        if (typeof window.html2canvas !== 'function') {
            throw new Error('html2canvas not available.');
        }
        return window.html2canvas(rackExportScope, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true
        });
    }

    async function rackExportAsImage() {
        const canvas = await rackCaptureScopeCanvas();
        const fileName = rackBuildExportFilename('png');
        if (canvas.toBlob) {
            await new Promise(function (resolve, reject) {
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        reject(new Error('Could not create image blob.'));
                        return;
                    }
                    rackDownloadBlob(blob, fileName);
                    resolve();
                }, 'image/png');
            });
            return;
        }
        const dataUrl = canvas.toDataURL('image/png');
        const a = document.createElement('a');
        a.href = dataUrl;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    async function rackExportAsPdf() {
        const canvas = await rackCaptureScopeCanvas();
        if (!window.jspdf || typeof window.jspdf.jsPDF !== 'function') {
            throw new Error('jsPDF not available.');
        }
        const imgData = canvas.toDataURL('image/png');
        const pdfWidth = 297;
        const pdfHeight = 210;
        const ratio = canvas.width / canvas.height;
        let renderWidth = pdfWidth - 20;
        let renderHeight = renderWidth / ratio;
        if (renderHeight > (pdfHeight - 20)) {
            renderHeight = pdfHeight - 20;
            renderWidth = renderHeight * ratio;
        }
        const x = (pdfWidth - renderWidth) / 2;
        const y = (pdfHeight - renderHeight) / 2;
        const doc = new window.jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
        doc.addImage(imgData, 'PNG', x, y, renderWidth, renderHeight);
        doc.save(rackBuildExportFilename('pdf'));
    }

    async function rackExportAsExcel() {
        const escapeHtml = function (value) {
            return String(value === undefined || value === null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        };

        const rowMap = {};
        const deviceMap = {};
        const rackRows = [];
        const unitCells = Array.from(document.querySelectorAll('.rack-visualizer-content .rack-visualizer-u'));
        unitCells.forEach(function (cell) {
            const unit = parseInt(String(cell.getAttribute('data-u') || ''), 10);
            if (!Number.isInteger(unit) || unit < 1) {
                return;
            }
            rowMap[unit] = {
                unit: unit,
                size: '',
                label: '',
                price: null,
                u_rowspan: 1,
                u_hide: false
            };

            const code = String(cell.getAttribute('data-device-code') || '').trim();
            if (code === '' || code === 'empty') {
                return;
            }

            const startU = parseInt(String(cell.getAttribute('data-device-start-u') || ''), 10);
            if (!Number.isInteger(startU) || startU < 1) {
                return;
            }

            const size = parseInt(String(cell.getAttribute('data-device-size') || ''), 10) === 2 ? 2 : 1;
            const label = String(cell.getAttribute('data-device-label') || '').trim();
            const rawPriceAttr = String(cell.getAttribute('data-device-price') || '').trim();
            const priceValue = rawPriceAttr !== '' && !Number.isNaN(Number(rawPriceAttr))
                ? Number(rawPriceAttr)
                : rackExtractPriceFromText(label);

            const deviceKey = String(startU) + '|' + code;
            if (Object.prototype.hasOwnProperty.call(deviceMap, deviceKey)) {
                return;
            }

            deviceMap[deviceKey] = {
                code: code,
                start_u: startU,
                size: size,
                label: label,
                price: Number.isFinite(priceValue) ? priceValue : null
            };
        });

        Object.keys(deviceMap).forEach(function (deviceKey) {
            const device = deviceMap[deviceKey];
            if (!device) {
                return;
            }

            const anchorUnit = Number(device.start_u) + Number(device.size) - 1;
            if (!Object.prototype.hasOwnProperty.call(rowMap, anchorUnit)) {
                return;
            }

            rowMap[anchorUnit].size = Number(device.size) === 2 ? 2 : 1;
            rowMap[anchorUnit].label = String(device.label || device.code || '');
            rowMap[anchorUnit].price = (device.price !== undefined && device.price !== null && Number.isFinite(Number(device.price)))
                ? Number(device.price)
                : null;
            rowMap[anchorUnit].u_rowspan = Number(device.size) === 2 ? 2 : 1;

            if (Number(device.size) === 2) {
                for (let u = Number(device.start_u); u < anchorUnit; u++) {
                    if (Object.prototype.hasOwnProperty.call(rowMap, u)) {
                        rowMap[u].u_hide = true;
                    }
                }
            }
        });

        Object.keys(rowMap).forEach(function (unitKey) {
            rackRows.push(rowMap[unitKey]);
        });

        rackRows.sort(function (a, b) { return b.unit - a.unit; });

        const rackTitleNode = document.querySelector('h1');
        const rackTitle = rackTitleNode ? String(rackTitleNode.textContent || '').trim() : 'Rack Export';
        const totalText = (document.getElementById('rackTotalAmount') || { textContent: '0.00' }).textContent || '0.00';
        const generatedAt = new Date().toLocaleString();

        const tableRowsHtml = rackRows.map(function (row) {
            const priceText = row.price !== null ? row.price.toFixed(2) : '';
            const rowSpanAttr = Number(row.u_rowspan) > 1 ? ' rowspan=\"' + escapeHtml(row.u_rowspan) + '\"' : '';
            const uCellHtml = row.u_hide ? '' : '<td' + rowSpanAttr + '>' + escapeHtml(row.unit) + '</td>';
            const sizeCellHtml = row.u_hide ? '' : '<td' + rowSpanAttr + '>' + escapeHtml(row.size) + '</td>';
            const labelCellHtml = row.u_hide ? '' : '<td' + rowSpanAttr + '>' + escapeHtml(row.label) + '</td>';
            const priceCellHtml = row.u_hide ? '' : '<td' + rowSpanAttr + ' style=\"text-align:right;\">' + escapeHtml(priceText) + '</td>';
            return '<tr>'
                + uCellHtml
                + sizeCellHtml
                + labelCellHtml
                + priceCellHtml
                + '</tr>';
        }).join('');

        const html = '<html><head><meta charset=\"utf-8\"></head><body>'
            + '<h3>' + escapeHtml(rackTitle) + '</h3>'
            + '<p><strong>Generated:</strong> ' + escapeHtml(generatedAt) + '</p>'
            + '<table border=\"1\" cellspacing=\"0\" cellpadding=\"4\">'
            + '<thead><tr><th>U</th><th>Size (RU)</th><th>Label</th><th>Price</th></tr></thead>'
            + '<tbody>' + tableRowsHtml + '</tbody>'
            + '</table>'
            + '<p><strong>TOTAL:</strong> ' + escapeHtml(totalText) + '</p>'
            + '</body></html>';
        const blob = new Blob(['\uFEFF', html], { type: 'application/vnd.ms-excel' });
        rackDownloadBlob(blob, rackBuildExportFilename('xls'));
    }

    async function runRackExport(handlerFn) {
        if (rackExportBusy) {
            return;
        }
        rackExportBusy = true;
        try {
            await handlerFn();
        } catch (error) {
            alert(error && error.message ? error.message : 'Export failed.');
        } finally {
            rackExportBusy = false;
        }
    }

    if (rackSaveImageBtn && rackExportPdfBtn && rackExportExcelBtn && rackExportScope) {
        rackSaveImageBtn.addEventListener('click', function () {
            runRackExport(rackExportAsImage);
        });
        rackExportPdfBtn.addEventListener('click', function () {
            runRackExport(rackExportAsPdf);
        });
        rackExportExcelBtn.addEventListener('click', function () {
            runRackExport(rackExportAsExcel);
        });
    }

    const rackUnitModal = document.getElementById('rackUnitModal');
    const rackUnitModalClose = document.getElementById('rackUnitModalClose');
    const rackUnitModalTitle = document.getElementById('rackUnitModalTitle');
    const rackUnitCells = Array.from(document.querySelectorAll('.rack-visualizer-content .rack-visualizer-u'));
    const unitTypeSelect = document.getElementById('unitTypeSelect');
    const rackEditPriceBtn = document.getElementById('rackEditPriceBtn');
    const rackSaveCatalogBtn = document.getElementById('rackSaveCatalogBtn');
    const placeholderMessageRow = document.getElementById('placeholderMessageRow');
    const placeholderMessageInput = document.getElementById('placeholderMessageInput');
    const placeholderApplyBtn = document.getElementById('placeholderApplyBtn');
    const rackPriceModal = document.getElementById('rackPriceModal');
    const rackPriceModalClose = document.getElementById('rackPriceModalClose');
    const rackPriceInput = document.getElementById('rackPriceInput');
    const rackPriceSkipBtn = document.getElementById('rackPriceSkipBtn');
    const rackPriceSaveBtn = document.getElementById('rackPriceSaveBtn');
    const rackDragTrash = document.getElementById('rackDragTrash');
    const layoutJsonInput = document.getElementById('layoutJsonInput');
    const rackPlanForm = document.querySelector('form.form-grid');
    const rackUnitsInput = rackPlanForm ? rackPlanForm.querySelector('input[name="rack_units"]') : null;
    const rackTotalAmount = document.getElementById('rackTotalAmount');
    let pendingPriceMeta = null;
    let activeAssignment = null;

    function getRackUnitsLimit() {
        const inputUnits = rackUnitsInput ? parseInt(rackUnitsInput.value, 10) : NaN;
        if (Number.isInteger(inputUnits) && inputUnits > 0) {
            return inputUnits;
        }
        return rackUnitCells.length;
    }

    function isPlaceholderCode(code) {
        const c = String(code || '');
        return c === 'ph' || c === 'ph_2';
    }

    function isEmptyCode(code) {
        return String(code || '') === 'empty';
    }

    function isCatalogCode(code) {
        return String(code || '').indexOf('catalog:') === 0;
    }

    function isEquipmentPickerCode(code) {
        const normalizedCode = String(code || '');
        return normalizedCode.indexOf('equipment:') === 0 || normalizedCode.indexOf('idf_unlinked:') === 0;
    }

    function canEditPriceForMeta(meta) {
        if (!meta) {
            return false;
        }
        return !isEmptyCode(meta.code) && !isPlaceholderCode(meta.code);
    }

    function updateModalActionButtons(meta) {
        if (rackEditPriceBtn) {
            rackEditPriceBtn.hidden = !canEditPriceForMeta(meta);
        }
        if (rackSaveCatalogBtn) {
            rackSaveCatalogBtn.hidden = !(meta && isCatalogCode(meta.code));
        }
    }

    function getRackDeviceImagePath(code) {
        const normalizedCode = String(code || '').trim();
        if (normalizedCode === '' || normalizedCode === 'empty') {
            return '';
        }
        if (isCatalogCode(normalizedCode) || isEquipmentPickerCode(normalizedCode)) {
            return '../../assets/catalog.svg';
        }

        const explicitMap = {
            pp24: 'pp24.svg',
            pp48: 'pp48.svg',
            ppfo24: 'ppfo24.svg',
            ppfo48: 'ppfo48.svg',
            sw24: 'sw24.svg',
            sw48: 'sw48.svg',
            bs: 'bs.svg',
            bs_2: 'bs_2.svg',
            ds: 'ds.svg',
            rt: 'rt.svg',
            tr_2: 'tr_2.svg',
            ph: 'ph.svg',
            ph_2: 'ph2.svg'
        };
        if (!Object.prototype.hasOwnProperty.call(explicitMap, normalizedCode)) {
            return '';
        }
        return '../../assets/' + explicitMap[normalizedCode];
    }

    function showPlaceholderMessageControls(show) {
        if (!placeholderMessageRow || !placeholderMessageInput || !placeholderApplyBtn) {
            return;
        }
        if (show) {
            placeholderMessageRow.classList.remove('is-hidden');
        } else {
            placeholderMessageRow.classList.add('is-hidden');
            placeholderMessageInput.value = '';
        }
    }

    function closeRackPriceModal(resetState) {
        if (!rackPriceModal) {
            return;
        }
        rackPriceModal.classList.remove('is-open');
        rackPriceModal.setAttribute('aria-hidden', 'true');
        if (rackPriceInput) {
            rackPriceInput.value = '';
        }
        if (resetState !== false) {
            pendingPriceMeta = null;
            if (unitTypeSelect) {
                unitTypeSelect.value = activeAssignment ? String(activeAssignment.code || '') : '';
            }
            updateModalActionButtons(getSelectedOptionMeta());
        }
    }

    function closeRackModal() {
        if (!rackUnitModal) {
            return;
        }
        closeRackPriceModal(true);
        showPlaceholderMessageControls(false);
        updateModalActionButtons(null);
        rackUnitModal.classList.remove('is-open');
        rackUnitModal.setAttribute('aria-hidden', 'true');
    }

    function getComponentMeta(code) {
        if (!code || typeof rackComponentCatalog !== 'object' || rackComponentCatalog === null) {
            return null;
        }
        if (!Object.prototype.hasOwnProperty.call(rackComponentCatalog, code)) {
            return null;
        }
        return rackComponentCatalog[code];
    }

    function inferCatalogSizeFromName(name) {
        return /\b2\s*-\s*ru\b|\b2\s*ru\b/i.test(String(name || '')) ? 2 : 1;
    }

    function getCombinedCodeMeta(code) {
        if (!code || typeof rackCombinedCodeMeta !== 'object' || rackCombinedCodeMeta === null) {
            return null;
        }
        if (!Object.prototype.hasOwnProperty.call(rackCombinedCodeMeta, code)) {
            return null;
        }

        const option = rackCombinedCodeMeta[code];
        if (!option || typeof option !== 'object') {
            return null;
        }

        const size = Number(option.size) === 2 ? 2 : inferCatalogSizeFromName(option.label || code);
        const priceValue = (option.price !== null && option.price !== undefined && option.price !== '') ? Number(option.price) : null;
        const displayLabel = String(option.label || code);

        return {
            label: displayLabel,
            size: size,
            price: Number.isFinite(priceValue) ? priceValue : null
        };
    }

    function getCatalogMeta(code) {
        if (!code || !Array.isArray(rackCatalogOptions)) {
            return null;
        }

        for (let i = 0; i < rackCatalogOptions.length; i++) {
            const option = rackCatalogOptions[i];
            if (!option || typeof option !== 'object') {
                continue;
            }
            if (String(option.code || '') !== code) {
                continue;
            }

            const size = Number(option.size) === 2 ? 2 : inferCatalogSizeFromName(option.label || option.model || '');
            const priceValue = (option.price_value !== null && option.price_value !== undefined && option.price_value !== '') ? Number(option.price_value) : null;
            const displayLabel = String(option.select_text || option.label || option.model || code);
            return {
                label: displayLabel,
                size: size,
                price: Number.isFinite(priceValue) ? priceValue : null
            };
        }

        return null;
    }

    function getAnyComponentMeta(code, rawDevice) {
        const staticMeta = getComponentMeta(code);
        if (staticMeta) {
            return {
                label: String(staticMeta.label || code),
                size: Number(staticMeta.size) === 2 ? 2 : 1
            };
        }

        const combinedMeta = getCombinedCodeMeta(code);
        if (combinedMeta) {
            return combinedMeta;
        }

        if (isCatalogCode(code) || isEquipmentPickerCode(code)) {
            const catalogMeta = getCatalogMeta(code);
            if (catalogMeta) {
                return catalogMeta;
            }

            const fallbackLabel = String((rawDevice && rawDevice.label) || code).trim();
            const fallbackSize = Number((rawDevice && rawDevice.size) || inferCatalogSizeFromName(fallbackLabel)) === 2 ? 2 : 1;
            const fallbackPrice = (rawDevice && rawDevice.price !== undefined && rawDevice.price !== null && rawDevice.price !== '' && !Number.isNaN(Number(rawDevice.price))) ? Number(rawDevice.price) : null;
            return {
                label: fallbackLabel === '' ? code : fallbackLabel,
                size: fallbackSize,
                price: fallbackPrice
            };
        }

        return null;
    }

    function normalizeLayout(layout) {
        const units = getRackUnitsLimit();
        const occupied = {};
        const devices = [];
        const rawDevices = layout && Array.isArray(layout.devices) ? layout.devices : [];

        rawDevices.forEach(function (rawDevice) {
            if (!rawDevice || typeof rawDevice !== 'object') {
                return;
            }

            const code = String(rawDevice.code || '').trim();
            const meta = getAnyComponentMeta(code, rawDevice);
            if (!meta) {
                return;
            }

            const size = Number(meta.size) === 2 ? 2 : 1;
            const startU = parseInt(rawDevice.start_u, 10);
            if (!Number.isInteger(startU) || startU < 1 || (startU + size - 1) > units) {
                return;
            }

            for (let u = startU; u < (startU + size); u++) {
                if (occupied[u]) {
                    return;
                }
            }

            for (let u = startU; u < (startU + size); u++) {
                occupied[u] = true;
            }

            let label = String(rawDevice.label || '').trim();
            if (label === '') {
                label = String(meta.label || code);
            }

            let normalizedPrice = null;
            if (!isEmptyCode(code)) {
                const parsedLabelPrice = rackExtractPriceFromText(label);
                normalizedPrice = Number.isFinite(parsedLabelPrice) ? parsedLabelPrice : null;
            }
            if (normalizedPrice === null) {
                normalizedPrice = (meta.price !== null && meta.price !== undefined && !Number.isNaN(Number(meta.price)))
                    ? Number(meta.price)
                    : ((rawDevice.price !== undefined && rawDevice.price !== null && rawDevice.price !== '' && !Number.isNaN(Number(rawDevice.price))))
                        ? Number(rawDevice.price)
                        : null;
            }

            devices.push({
                code: code,
                label: label,
                start_u: startU,
                size: size,
                price: normalizedPrice
            });
        });

        devices.sort(function (a, b) {
            return b.start_u - a.start_u;
        });

        return {
            version: 1,
            units: units,
            devices: devices
        };
    }

    function computeLayoutTotal(layout) {
        if (!layout || !Array.isArray(layout.devices)) {
            return 0;
        }
        let total = 0;
        layout.devices.forEach(function (device) {
            if (!device || device.price === undefined || device.price === null || device.price === '') {
                return;
            }
            const n = Number(device.price);
            if (Number.isFinite(n)) {
                total += n;
            }
        });
        return total;
    }

    function updateTotalAmount(layout) {
        if (!rackTotalAmount) {
            return;
        }
        rackTotalAmount.textContent = computeLayoutTotal(layout).toFixed(2);
    }

    function parseLayoutFromInput() {
        const fallback = { version: 1, units: getRackUnitsLimit(), devices: [] };
        if (!layoutJsonInput || String(layoutJsonInput.value || '').trim() === '') {
            return normalizeLayout(fallback);
        }

        try {
            const parsed = JSON.parse(layoutJsonInput.value);
            if (!parsed || typeof parsed !== 'object') {
                return normalizeLayout(fallback);
            }
            return normalizeLayout(parsed);
        } catch (error) {
            return normalizeLayout(fallback);
        }
    }

    function buildAssignments(layout) {
        const assignmentByUnit = {};
        if (!layout || !Array.isArray(layout.devices)) {
            return assignmentByUnit;
        }

        layout.devices.forEach(function (device) {
            const startU = Number(device.start_u);
            const size = Number(device.size) === 2 ? 2 : 1;
            for (let u = startU; u < (startU + size); u++) {
                assignmentByUnit[u] = {
                    code: String(device.code || ''),
                    label: String(device.label || ''),
                    start_u: startU,
                    size: size,
                    price: (device.price !== undefined && device.price !== null && device.price !== '' && !Number.isNaN(Number(device.price))) ? Number(device.price) : null
                };
            }
        });

        return assignmentByUnit;
    }

    function saveLayoutToInput(layout) {
        if (!layoutJsonInput) {
            return;
        }
        layoutJsonInput.value = JSON.stringify(layout);
    }

    function hasSelectOptionValue(selectEl, value) {
        if (!selectEl) {
            return false;
        }
        for (let i = 0; i < selectEl.options.length; i++) {
            if (String(selectEl.options[i].value || '') === String(value || '')) {
                return true;
            }
        }
        return false;
    }

    function ensureOptionExists(value, label, size, price) {
        if (!unitTypeSelect || !value || hasSelectOptionValue(unitTypeSelect, value)) {
            return;
        }

        const option = document.createElement('option');
        option.value = value;
        option.textContent = String(label || value);
        option.setAttribute('data-label', String(label || value));
        option.setAttribute('data-size', String(Number(size) === 2 ? 2 : 1));
        option.setAttribute('data-price', (price !== undefined && price !== null && !Number.isNaN(Number(price))) ? Number(price).toFixed(2) : '');
        option.setAttribute('data-source', 'layout');
        unitTypeSelect.appendChild(option);
    }

    function appendCatalogOptionsToSelect() {
        if (!unitTypeSelect || !Array.isArray(rackCatalogOptions) || rackCatalogOptions.length === 0) {
            return;
        }

        Array.from(unitTypeSelect.children).forEach(function (child) {
            if (child && child.tagName === 'OPTION' && String(child.value || '') === '__add_new__') {
                unitTypeSelect.removeChild(child);
            }
        });

        let catalogsGroup = unitTypeSelect.querySelector('optgroup[label="Catalog"]');
        if (!catalogsGroup) {
            catalogsGroup = document.createElement('optgroup');
            catalogsGroup.label = 'Catalog';
            unitTypeSelect.appendChild(catalogsGroup);
        }

        const existingCatalogQuickAdd = catalogsGroup.querySelector('option[value="__add_new__"]');
        if (existingCatalogQuickAdd) {
            existingCatalogQuickAdd.remove();
        }

        rackCatalogOptions.forEach(function (catalogOption) {
            if (!catalogOption || typeof catalogOption !== 'object') {
                return;
            }

            const optionValue = String(catalogOption.code || '');
            if (optionValue === '' || hasSelectOptionValue(unitTypeSelect, optionValue)) {
                return;
            }

            const optionLabel = String(catalogOption.label || catalogOption.model || optionValue);
            const optionSize = Number(catalogOption.size) === 2 ? 2 : inferCatalogSizeFromName(optionLabel);
            const optionText = String(catalogOption.select_text || optionLabel);
            const optionPrice = (catalogOption.price_value !== null && catalogOption.price_value !== undefined && catalogOption.price_value !== '') ? Number(catalogOption.price_value) : NaN;

            const optionEl = document.createElement('option');
            optionEl.value = optionValue;
            optionEl.textContent = optionText;
            optionEl.setAttribute('data-label', optionText);
            optionEl.setAttribute('data-size', String(optionSize));
            optionEl.setAttribute('data-price', Number.isFinite(optionPrice) ? optionPrice.toFixed(2) : '');
            optionEl.setAttribute('data-source', 'catalog');
            catalogsGroup.appendChild(optionEl);
        });

        const quickAddOption = document.createElement('option');
        quickAddOption.value = '__add_new__';
        quickAddOption.textContent = '\u2795';
        quickAddOption.setAttribute('data-source', 'catalog_add');
        catalogsGroup.appendChild(quickAddOption);
    }

    function appendEquipmentOptionsToSelect() {
        if (!unitTypeSelect || !Array.isArray(rackEquipmentPickerOptions) || rackEquipmentPickerOptions.length === 0) {
            return;
        }

        let equipmentGroup = unitTypeSelect.querySelector('optgroup[label="Equipment"]');
        if (!equipmentGroup) {
            equipmentGroup = document.createElement('optgroup');
            equipmentGroup.label = 'Equipment';
            unitTypeSelect.appendChild(equipmentGroup);
        }

        rackEquipmentPickerOptions.forEach(function (equipmentOption) {
            if (!equipmentOption || typeof equipmentOption !== 'object') {
                return;
            }

            const optionValue = String(equipmentOption.code || '');
            if (optionValue === '' || hasSelectOptionValue(unitTypeSelect, optionValue)) {
                return;
            }

            const optionLabel = String(equipmentOption.label || optionValue);
            const optionSize = Number(equipmentOption.size) === 2 ? 2 : inferCatalogSizeFromName(optionLabel);
            const optionText = String(equipmentOption.select_text || optionLabel);
            const optionPrice = (equipmentOption.price_value !== null && equipmentOption.price_value !== undefined && equipmentOption.price_value !== '') ? Number(equipmentOption.price_value) : NaN;

            const optionEl = document.createElement('option');
            optionEl.value = optionValue;
            optionEl.textContent = optionText;
            optionEl.setAttribute('data-label', optionText);
            optionEl.setAttribute('data-size', String(optionSize));
            optionEl.setAttribute('data-price', Number.isFinite(optionPrice) ? optionPrice.toFixed(2) : '');
            optionEl.setAttribute('data-source', 'equipment_picker');
            equipmentGroup.appendChild(optionEl);
        });
    }

    function getSelectedOptionMeta() {
        if (!unitTypeSelect) {
            return null;
        }

        const selectedOption = unitTypeSelect.options[unitTypeSelect.selectedIndex];
        if (!selectedOption) {
            return null;
        }

        const selectedCode = String(selectedOption.value || '');
        if (selectedCode === '') {
            return null;
        }

        const optionSize = parseInt(String(selectedOption.getAttribute('data-size') || ''), 10);
        const size = optionSize === 2 ? 2 : 1;
        let label = String(selectedOption.getAttribute('data-label') || '').trim();
        if (label === '') {
            label = String(selectedOption.textContent || selectedCode).trim();
        }
        const optionPriceRaw = String(selectedOption.getAttribute('data-price') || '').trim();
        const optionPriceNum = optionPriceRaw === '' ? null : Number(optionPriceRaw);

        return {
            code: selectedCode,
            label: label === '' ? selectedCode : label,
            size: size,
            price: Number.isFinite(optionPriceNum) ? optionPriceNum : null
        };
    }

    function renderLayout(layout) {
        const assignmentByUnit = buildAssignments(layout);

        rackUnitCells.forEach(function (cell) {
            const unit = parseInt(cell.getAttribute('data-u'), 10);
            const assignment = assignmentByUnit[unit];
            const labelEl = cell.querySelector('.rack-visualizer-u-label');
            cell.classList.remove('rack-drop-target');
            cell.setAttribute('draggable', 'false');
            cell.classList.remove('has-device-image');
            cell.classList.remove('has-device-image-catalog');
            cell.style.removeProperty('--rack-device-image');

            if (assignment) {
                cell.style.cursor = 'move';
                cell.classList.add('has-device');
                cell.classList.remove('has-device-anchor');
                cell.setAttribute('data-device-code', assignment.code);
                cell.setAttribute('data-device-label', assignment.label);
                cell.setAttribute('data-device-size', String(assignment.size));
                cell.setAttribute('data-device-start-u', String(assignment.start_u));
                cell.setAttribute('data-device-price', (assignment.price !== undefined && assignment.price !== null && !Number.isNaN(Number(assignment.price))) ? Number(assignment.price).toFixed(2) : '');
                cell.setAttribute('draggable', 'true');
                const anchorUnit = Number(assignment.start_u) + Number(assignment.size) - 1;
                if (unit === anchorUnit) {
                    cell.classList.add('has-device-anchor');
                    const deviceImagePath = getRackDeviceImagePath(assignment.code);
                    if (deviceImagePath !== '') {
                        cell.classList.add('has-device-image');
                        if (isCatalogCode(assignment.code)) {
                            cell.classList.add('has-device-image-catalog');
                        }
                        cell.style.setProperty('--rack-device-image', 'url("' + deviceImagePath + '")');
                    }
                    if (labelEl) {
                        labelEl.textContent = assignment.label;
                    }
                } else if (labelEl) {
                    labelEl.textContent = '';
                }
            } else {
                cell.style.cursor = 'pointer';
                cell.classList.remove('has-device');
                cell.classList.remove('has-device-anchor');
                cell.setAttribute('data-device-code', '');
                cell.setAttribute('data-device-label', '');
                cell.setAttribute('data-device-size', '');
                cell.setAttribute('data-device-start-u', '');
                cell.setAttribute('data-device-price', '');
                if (labelEl) {
                    labelEl.textContent = '';
                }
            }
        });

        saveLayoutToInput(layout);
        updateTotalAmount(layout);
    }

    let autoSaveInFlight = false;
    let autoSavePending = false;
    let lastAutoSavePayload = '';

    function autoSaveLayoutToDatabase(layout) {
        if (!rackPlanForm || !layoutJsonInput) {
            return;
        }

        const idInput = rackPlanForm.querySelector('input[name="id"]');
        const csrfInput = rackPlanForm.querySelector('input[name="csrf_token"]');
        const recordId = idInput ? parseInt(String(idInput.value || ''), 10) : 0;
        if (!Number.isInteger(recordId) || recordId <= 0) {
            return;
        }

        const payload = JSON.stringify(layout);
        if (payload === lastAutoSavePayload && !autoSavePending && !autoSaveInFlight) {
            return;
        }
        lastAutoSavePayload = payload;

        const submitOnce = function () {
            autoSaveInFlight = true;
            const formData = new FormData();
            formData.set('ajax_update_layout', '1');
            formData.set('id', String(recordId));
            formData.set('rack_units', String(getRackUnitsLimit()));
            formData.set('layout_json', layoutJsonInput.value);
            if (csrfInput) {
                formData.set('csrf_token', String(csrfInput.value || ''));
            }

            fetch(window.location.pathname + window.location.search, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                if (!result || typeof result !== 'object') {
                    return;
                }
                if (result.layout_json && layoutJsonInput) {
                    layoutJsonInput.value = String(result.layout_json);
                }
                if (result.total_amount !== undefined && result.total_amount !== null && rackTotalAmount) {
                    const totalNum = Number(result.total_amount);
                    if (Number.isFinite(totalNum)) {
                        rackTotalAmount.textContent = totalNum.toFixed(2);
                    }
                }
            }).catch(function () {
                // Keep the UI responsive even if auto-save fails; manual Save still works.
            }).finally(function () {
                autoSaveInFlight = false;
                if (autoSavePending) {
                    autoSavePending = false;
                    submitOnce();
                }
            });
        };

        if (autoSaveInFlight) {
            autoSavePending = true;
            return;
        }

        submitOnce();
    }

    if (rackUnitModal && rackUnitModalClose && unitTypeSelect && layoutJsonInput && rackUnitCells.length > 0) {
        let layoutState = parseLayoutFromInput();
        let activeUnit = null;
        let dragState = null;
        let ignoreNextClick = false;
        let pendingPlaceholderMeta = null;

        function applySelectedMeta(selectedMeta, customLabel) {
            const previousLayoutJson = JSON.stringify(layoutState);
            const normalizedCustomLabel = String(customLabel || '').trim();

            if (!Number.isInteger(activeUnit) || activeUnit < 1) {
                closeRackModal();
                return;
            }

            removeDeviceCoveringUnit(activeUnit);

            if (selectedMeta) {
                const size = Number(selectedMeta.size) === 2 ? 2 : 1;
                const rackLimit = getRackUnitsLimit();
                if ((activeUnit + size - 1) > rackLimit) {
                    alert('Not enough space for this ' + String(size) + '-RU component.');
                    unitTypeSelect.value = '';
                    layoutState = normalizeLayout(previousLayoutJson ? JSON.parse(previousLayoutJson) : { version: 1, units: rackLimit, devices: [] });
                    renderLayout(layoutState);
                    return;
                }

                if (!isRangeAvailable(activeUnit, size)) {
                    alert('Selected space overlaps another component.');
                    unitTypeSelect.value = '';
                    layoutState = normalizeLayout(previousLayoutJson ? JSON.parse(previousLayoutJson) : { version: 1, units: rackLimit, devices: [] });
                    renderLayout(layoutState);
                    return;
                }

                layoutState.devices.push({
                    code: String(selectedMeta.code),
                    label: normalizedCustomLabel !== '' ? normalizedCustomLabel : String(selectedMeta.label),
                    start_u: activeUnit,
                    size: size,
                    price: (function () {
                        const resolvedLabel = normalizedCustomLabel !== '' ? normalizedCustomLabel : String(selectedMeta.label || '');
                        if (isEmptyCode(selectedMeta.code)) {
                            return null;
                        }
                        const parsedLabelPrice = rackExtractPriceFromText(resolvedLabel);
                        if (Number.isFinite(parsedLabelPrice)) {
                            return parsedLabelPrice;
                        }
                        return (selectedMeta.price !== undefined && selectedMeta.price !== null && !Number.isNaN(Number(selectedMeta.price)))
                            ? Number(selectedMeta.price)
                            : null;
                    })()
                });
            }

            layoutState = normalizeLayout(layoutState);
            renderLayout(layoutState);
            autoSaveLayoutToDatabase(layoutState);
            closeRackModal();
        }

        function parsePriceFromPriceModalInput() {
            const rawInput = String(rackPriceInput ? rackPriceInput.value : '').trim();
            if (rawInput === '') {
                return { ok: true, value: null };
            }

            let normalized = rawInput
                .replace(/^(?:\u20AC|\$)\s*/i, '')
                .replace(/^(?:eur|usd)\s*/i, '')
                .trim();
            if (normalized === '') {
                return { ok: false, value: null };
            }

            const parsed = rackExtractPriceFromText(' ' + normalized);
            if (!Number.isFinite(parsed)) {
                return { ok: false, value: null };
            }
            if (parsed < 0) {
                return { ok: false, value: null };
            }

            if (rackPriceInput) {
                rackPriceInput.value = parsed.toFixed(2);
            }
            return { ok: true, value: parsed };
        }

        function stripTrailingPriceFromLabel(text) {
            const label = String(text || '').trim();
            if (label === '') {
                return '';
            }

            const match = label.match(/^(.*?)(?:\s*[-:]\s*)([+-]?\d[\d.,]*)\s*(?:\u20AC|\$|usd|eur)?\s*$/i);
            if (!match || match.length < 3) {
                return label;
            }

            const parsed = rackExtractPriceFromText(' ' + String(match[2] || ''));
            if (!Number.isFinite(parsed)) {
                return label;
            }

            return String(match[1] || '').trim();
        }

        function openRackPriceModalForMeta(selectedMeta, customLabelBase) {
            if (!selectedMeta || !rackPriceModal) {
                return;
            }

            const explicitBaseLabel = String(customLabelBase === undefined || customLabelBase === null ? '' : customLabelBase).trim();
            const resolvedBaseLabel = explicitBaseLabel !== '' ? explicitBaseLabel : String(selectedMeta.label || '');
            pendingPriceMeta = {
                code: String(selectedMeta.code || ''),
                label: resolvedBaseLabel,
                size: Number(selectedMeta.size) === 2 ? 2 : 1,
                price: selectedMeta.price
            };

            if (rackPriceInput) {
                let initialValue = '';
                const parsedFromBaseLabel = rackExtractPriceFromText(resolvedBaseLabel);
                if (Number.isFinite(parsedFromBaseLabel)) {
                    initialValue = parsedFromBaseLabel.toFixed(2);
                } else if (activeAssignment && String(activeAssignment.code || '') === String(selectedMeta.code || '')) {
                    const parsedFromCurrentLabel = rackExtractPriceFromText(String(activeAssignment.label || ''));
                    if (Number.isFinite(parsedFromCurrentLabel)) {
                        initialValue = parsedFromCurrentLabel.toFixed(2);
                    } else if (activeAssignment.price !== undefined && activeAssignment.price !== null && !Number.isNaN(Number(activeAssignment.price))) {
                        initialValue = Number(activeAssignment.price).toFixed(2);
                    }
                }
                rackPriceInput.value = initialValue;
                rackPriceInput.focus();
                rackPriceInput.select();
            }

            rackPriceModal.classList.add('is-open');
            rackPriceModal.setAttribute('aria-hidden', 'false');
        }

        function applyPendingPriceSelection(skipPrice) {
            if (!pendingPriceMeta) {
                return;
            }

            const selectedMeta = pendingPriceMeta;
            let customLabel = String(selectedMeta.label || '');
            if (!skipPrice) {
                const parsedPrice = parsePriceFromPriceModalInput();
                if (!parsedPrice.ok) {
                    alert('Please enter a valid price, or leave it empty.');
                    if (rackPriceInput) {
                        rackPriceInput.focus();
                    }
                    return;
                }
                if (parsedPrice.value !== null) {
                    customLabel = stripTrailingPriceFromLabel(customLabel);
                    customLabel = customLabel + ' - ' + parsedPrice.value.toFixed(2);
                }
            }

            closeRackPriceModal(false);
            applySelectedMeta(selectedMeta, customLabel);
            pendingPriceMeta = null;
        }

        function clearDragTargets() {
            rackUnitCells.forEach(function (cell) {
                cell.classList.remove('rack-drop-target');
            });
        }

        function setTrashVisibility(visible) {
            if (!rackDragTrash) {
                return;
            }
            if (visible) {
                rackDragTrash.classList.add('is-visible');
                rackDragTrash.setAttribute('aria-hidden', 'false');
            } else {
                rackDragTrash.classList.remove('is-visible');
                rackDragTrash.classList.remove('is-active');
                rackDragTrash.setAttribute('aria-hidden', 'true');
            }
        }

        function setTrashActive(active) {
            if (!rackDragTrash) {
                return;
            }
            if (active) {
                rackDragTrash.classList.add('is-active');
            } else {
                rackDragTrash.classList.remove('is-active');
            }
        }

        function rangesOverlap(startA, sizeA, startB, sizeB) {
            const endA = startA + sizeA - 1;
            const endB = startB + sizeB - 1;
            return startA <= endB && startB <= endA;
        }

        function moveDeviceToUnit(sourceStartU, targetStartU) {
            const sourceIndex = layoutState.devices.findIndex(function (device) {
                return Number(device.start_u) === Number(sourceStartU);
            });
            if (sourceIndex === -1) {
                return;
            }

            const sourceDevice = layoutState.devices[sourceIndex];
            const sourceSize = Number(sourceDevice.size) === 2 ? 2 : 1;
            if (!Number.isInteger(targetStartU) || targetStartU < 1) {
                return;
            }
            if (Number(sourceDevice.start_u) === targetStartU) {
                return;
            }

            const rackLimit = getRackUnitsLimit();
            if ((targetStartU + sourceSize - 1) > rackLimit) {
                alert('Not enough space for this ' + String(sourceSize) + '-RU component.');
                return;
            }
            const assignments = buildAssignments(layoutState);
            const targetAssignment = assignments[targetStartU] || null;

            if (targetAssignment && Number(targetAssignment.start_u) !== Number(sourceDevice.start_u)) {
                const targetDeviceStart = Number(targetAssignment.start_u);
                const targetIndex = layoutState.devices.findIndex(function (device) {
                    return Number(device.start_u) === targetDeviceStart;
                });
                if (targetIndex === -1 || targetIndex === sourceIndex) {
                    return;
                }

                const targetDevice = layoutState.devices[targetIndex];
                const targetSize = Number(targetDevice.size) === 2 ? 2 : 1;
                const swappedSourceStart = targetStartU;
                const swappedTargetStart = Number(sourceDevice.start_u);

                if ((swappedTargetStart + targetSize - 1) > rackLimit) {
                    alert('Not enough space to exchange these components.');
                    return;
                }

                if (rangesOverlap(swappedSourceStart, sourceSize, swappedTargetStart, targetSize)) {
                    alert('Cannot exchange components due to overlap.');
                    return;
                }

                for (let i = 0; i < layoutState.devices.length; i++) {
                    if (i === sourceIndex || i === targetIndex) {
                        continue;
                    }
                    const other = layoutState.devices[i];
                    const otherStart = Number(other.start_u);
                    const otherSize = Number(other.size) === 2 ? 2 : 1;
                    if (rangesOverlap(swappedSourceStart, sourceSize, otherStart, otherSize)
                        || rangesOverlap(swappedTargetStart, targetSize, otherStart, otherSize)) {
                        alert('Cannot exchange components due to space constraints.');
                        return;
                    }
                }

                sourceDevice.start_u = swappedSourceStart;
                targetDevice.start_u = swappedTargetStart;
                layoutState = normalizeLayout(layoutState);
                renderLayout(layoutState);
                autoSaveLayoutToDatabase(layoutState);
                return;
            }

            for (let i = 0; i < layoutState.devices.length; i++) {
                if (i === sourceIndex) {
                    continue;
                }
                const other = layoutState.devices[i];
                const otherStart = Number(other.start_u);
                const otherSize = Number(other.size) === 2 ? 2 : 1;
                if (rangesOverlap(targetStartU, sourceSize, otherStart, otherSize)) {
                    alert('Selected space overlaps another component.');
                    return;
                }
            }

            sourceDevice.start_u = targetStartU;
            layoutState = normalizeLayout(layoutState);
            renderLayout(layoutState);
            autoSaveLayoutToDatabase(layoutState);
        }

        function removeDeviceCoveringUnit(unit) {
            layoutState.devices = layoutState.devices.filter(function (device) {
                const startU = Number(device.start_u);
                const size = Number(device.size) === 2 ? 2 : 1;
                return !(unit >= startU && unit < (startU + size));
            });
        }

        function isRangeAvailable(startU, size) {
            const assignments = buildAssignments(layoutState);
            for (let u = startU; u < (startU + size); u++) {
                if (assignments[u]) {
                    return false;
                }
            }
            return true;
        }

        function openRackModalForUnit(unit) {
            const assignments = buildAssignments(layoutState);
            const assignment = assignments[unit] || null;
            activeUnit = assignment ? assignment.start_u : unit;
            activeAssignment = assignment ? {
                code: String(assignment.code || ''),
                label: String(assignment.label || ''),
                size: Number(assignment.size) === 2 ? 2 : 1,
                price: assignment.price
            } : null;
            pendingPlaceholderMeta = null;
            pendingPriceMeta = null;
            closeRackPriceModal(false);
            if (rackUnitModalTitle) {
                rackUnitModalTitle.textContent = 'Component ' + String(activeUnit);
            }
            if (assignment && (isCatalogCode(assignment.code) || isEquipmentPickerCode(assignment.code))) {
                appendCatalogOptionsToSelect();
                appendEquipmentOptionsToSelect();
            }
            if (assignment) {
                ensureOptionExists(String(assignment.code || ''), String(assignment.label || assignment.code || ''), Number(assignment.size || 1), assignment.price);
            }
            unitTypeSelect.value = assignment ? assignment.code : '';
            updateModalActionButtons(getSelectedOptionMeta());
            if (assignment && isPlaceholderCode(assignment.code)) {
                showPlaceholderMessageControls(true);
                pendingPlaceholderMeta = {
                    code: String(assignment.code),
                    label: String(assignment.label),
                    size: Number(assignment.size) === 2 ? 2 : 1,
                    price: assignment.price
                };
                if (placeholderMessageInput) {
                    placeholderMessageInput.value = String(assignment.label || '');
                    placeholderMessageInput.focus();
                    placeholderMessageInput.select();
                }
            } else {
                showPlaceholderMessageControls(false);
            }
            rackUnitModal.classList.add('is-open');
            rackUnitModal.setAttribute('aria-hidden', 'false');
        }

        renderLayout(layoutState);
        appendCatalogOptionsToSelect();
        appendEquipmentOptionsToSelect();

        rackUnitCells.forEach(function (cell) {
            cell.style.cursor = 'pointer';
            cell.addEventListener('click', function () {
                if (ignoreNextClick) {
                    ignoreNextClick = false;
                    return;
                }
                const clickedUnit = parseInt(cell.getAttribute('data-u'), 10);
                if (!Number.isInteger(clickedUnit) || clickedUnit < 1) {
                    return;
                }
                openRackModalForUnit(clickedUnit);
            });

            cell.addEventListener('dragstart', function (event) {
                if (!cell.classList.contains('has-device')) {
                    event.preventDefault();
                    return;
                }

                const sourceStartU = parseInt(String(cell.getAttribute('data-device-start-u') || ''), 10);
                const sourceSize = parseInt(String(cell.getAttribute('data-device-size') || ''), 10);
                if (!Number.isInteger(sourceStartU) || sourceStartU < 1) {
                    event.preventDefault();
                    return;
                }

                dragState = {
                    sourceStartU: sourceStartU,
                    sourceSize: sourceSize === 2 ? 2 : 1
                };
                setTrashVisibility(true);
                setTrashActive(false);

                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(sourceStartU));
                }
            });

            cell.addEventListener('dragover', function (event) {
                if (!dragState) {
                    return;
                }
                event.preventDefault();
                cell.classList.add('rack-drop-target');
                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'move';
                }
            });

            cell.addEventListener('dragleave', function () {
                cell.classList.remove('rack-drop-target');
            });

            cell.addEventListener('drop', function (event) {
                if (!dragState) {
                    return;
                }
                event.preventDefault();
                const targetUnit = parseInt(String(cell.getAttribute('data-u') || ''), 10);
                clearDragTargets();
                moveDeviceToUnit(dragState.sourceStartU, targetUnit);
                dragState = null;
                ignoreNextClick = true;
                setTimeout(function () { ignoreNextClick = false; }, 50);
            });

            cell.addEventListener('dragend', function () {
                dragState = null;
                clearDragTargets();
                setTrashVisibility(false);
            });
        });

        if (rackDragTrash) {
            rackDragTrash.addEventListener('dragover', function (event) {
                if (!dragState) {
                    return;
                }
                event.preventDefault();
                setTrashActive(true);
                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'move';
                }
            });

            rackDragTrash.addEventListener('dragleave', function () {
                setTrashActive(false);
            });

            rackDragTrash.addEventListener('drop', function (event) {
                if (!dragState) {
                    return;
                }
                event.preventDefault();
                removeDeviceCoveringUnit(dragState.sourceStartU);
                layoutState = normalizeLayout(layoutState);
                renderLayout(layoutState);
                autoSaveLayoutToDatabase(layoutState);
                dragState = null;
                clearDragTargets();
                setTrashVisibility(false);
                ignoreNextClick = true;
                setTimeout(function () { ignoreNextClick = false; }, 50);
            });
        }

        unitTypeSelect.addEventListener('change', function () {
            if (!Number.isInteger(activeUnit) || activeUnit < 1) {
                closeRackModal();
                return;
            }

            if (String(unitTypeSelect.value || '') === '__add_new__') {
                unitTypeSelect.value = '';
                window.open('../catalogs/create.php', '_blank');
                return;
            }

            const selectedMeta = getSelectedOptionMeta();
            updateModalActionButtons(selectedMeta);
            if (selectedMeta && isPlaceholderCode(selectedMeta.code)) {
                pendingPlaceholderMeta = {
                    code: String(selectedMeta.code),
                    label: String(selectedMeta.label || ''),
                    size: Number(selectedMeta.size) === 2 ? 2 : 1,
                    price: selectedMeta.price
                };
                showPlaceholderMessageControls(true);
                if (placeholderMessageInput) {
                    if (String(placeholderMessageInput.value || '').trim() === '') {
                        placeholderMessageInput.value = String(selectedMeta.label || '');
                    }
                    placeholderMessageInput.focus();
                    placeholderMessageInput.select();
                }
                return;
            }

            if (selectedMeta && !isCatalogCode(selectedMeta.code) && !isEmptyCode(selectedMeta.code)) {
                pendingPlaceholderMeta = null;
                showPlaceholderMessageControls(false);
                openRackPriceModalForMeta(selectedMeta);
                return;
            }

            if (selectedMeta && isCatalogCode(selectedMeta.code)) {
                pendingPlaceholderMeta = null;
                showPlaceholderMessageControls(false);
                return;
            }

            pendingPlaceholderMeta = null;
            showPlaceholderMessageControls(false);
            applySelectedMeta(selectedMeta, '');
        });

        if (rackEditPriceBtn) {
            rackEditPriceBtn.addEventListener('click', function () {
                const selectedMeta = getSelectedOptionMeta();
                if (!canEditPriceForMeta(selectedMeta)) {
                    return;
                }
                pendingPlaceholderMeta = null;
                showPlaceholderMessageControls(false);
                openRackPriceModalForMeta(selectedMeta);
            });
        }

        if (rackSaveCatalogBtn) {
            rackSaveCatalogBtn.addEventListener('click', function () {
                const selectedMeta = getSelectedOptionMeta();
                if (!selectedMeta || !isCatalogCode(selectedMeta.code)) {
                    return;
                }
                pendingPlaceholderMeta = null;
                showPlaceholderMessageControls(false);
                applySelectedMeta(selectedMeta, '');
            });
        }

        function applyPendingPlaceholderSelection() {
            const selectedMeta = pendingPlaceholderMeta && isPlaceholderCode(pendingPlaceholderMeta.code)
                ? pendingPlaceholderMeta
                : getSelectedOptionMeta();
            if (!selectedMeta || !isPlaceholderCode(selectedMeta.code)) {
                return;
            }

            const typedMessage = String(placeholderMessageInput ? placeholderMessageInput.value : '').trim();
            if (typedMessage === '') {
                alert('Please write a placeholder message.');
                if (placeholderMessageInput) {
                    placeholderMessageInput.focus();
                }
                return;
            }

            showPlaceholderMessageControls(false);
            openRackPriceModalForMeta(selectedMeta, typedMessage);
            pendingPlaceholderMeta = null;
        }

        if (placeholderApplyBtn) {
            placeholderApplyBtn.addEventListener('click', function () {
                applyPendingPlaceholderSelection();
            });
        }

        if (placeholderMessageInput) {
            placeholderMessageInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    applyPendingPlaceholderSelection();
                }
            });
        }

        if (rackPriceSkipBtn) {
            rackPriceSkipBtn.addEventListener('click', function () {
                applyPendingPriceSelection(true);
            });
        }

        if (rackPriceSaveBtn) {
            rackPriceSaveBtn.addEventListener('click', function () {
                applyPendingPriceSelection(false);
            });
        }

        if (rackPriceInput) {
            rackPriceInput.addEventListener('blur', function () {
                const parsedPrice = parsePriceFromPriceModalInput();
                if (parsedPrice.ok && parsedPrice.value !== null) {
                    rackPriceInput.value = parsedPrice.value.toFixed(2);
                    return;
                }
                rackPriceInput.value = String(rackPriceInput.value || '').replace(/,/g, '.');
            });

            rackPriceInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    applyPendingPriceSelection(false);
                }
            });
        }

        if (rackPriceModalClose) {
            rackPriceModalClose.addEventListener('click', function () {
                closeRackPriceModal(true);
            });
        }

        if (rackPriceModal) {
            rackPriceModal.addEventListener('click', function (event) {
                if (event.target === rackPriceModal) {
                    closeRackPriceModal(true);
                }
            });
        }

        rackUnitModalClose.addEventListener('click', function () {
            closeRackModal();
        });

        rackUnitModal.addEventListener('click', function (event) {
            if (event.target === rackUnitModal) {
                closeRackModal();
            }
        });

        if (rackPlanForm) {
            rackPlanForm.addEventListener('submit', function () {
                layoutState = normalizeLayout(layoutState);
                saveLayoutToInput(layoutState);
            });
        }
    }
})();
</script>
<?php require_once ROOT_PATH . 'includes/itm_qr_share_modal.php'; ?>
</body>
</html>
