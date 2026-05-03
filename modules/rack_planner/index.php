<?php
/**
 * Rack Planner Module
 *
 * Standard CRUD for Rack Planner with custom visualization.
 */

require '../../config/config.php';

$crud_table = 'rack_planner';
$crud_title = 'Rack Planner';
$crud_action = $crud_action ?? 'index';

// Handle Excel/CSV database import requests from table-tools.js.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true)) {
    $itm_content_type = isset($_SERVER['CONTENT_TYPE']) ? strtolower(trim((string) $_SERVER['CONTENT_TYPE'])) : '';
    if (strpos($itm_content_type, 'application/json') === 0) {
        $itm_raw_body = file_get_contents('php://input');
        $itm_payload = json_decode($itm_raw_body, true);

        if (is_array($itm_payload) && isset($itm_payload['import_excel_rows'])) {
            itm_handle_json_table_import($conn, $crud_table, (int)($company_id ?? 0));
            exit;
        }
    }
}

if (!isset($_SESSION['company_id'])) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$errors = [];
$success_msg = '';

if (isset($_SESSION['crud_error'])) {
    $errors[] = $_SESSION['crud_error'];
    unset($_SESSION['crud_error']);
}
if (isset($_SESSION['crud_success'])) {
    $success_msg = $_SESSION['crud_success'];
    unset($_SESSION['crud_success']);
}

// Handle Delete
if ($crud_action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    // Handle Clear Table Bulk Action
    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'clear_table') {
        $stmt = mysqli_prepare($conn, "DELETE FROM rack_planner WHERE company_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_success'] = 'Table cleared.';
        } else {
            $_SESSION['crud_error'] = 'Error clearing table.';
        }
        mysqli_stmt_close($stmt);
        header('Location: index.php');
        exit;
    }

    // Handle Bulk Delete
    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = mysqli_prepare($conn, "DELETE FROM rack_planner WHERE id IN ($placeholders) AND company_id = ?");
            $types = str_repeat('i', count($ids)) . 'i';
            $params = array_map('intval', $ids);
            $params[] = $company_id;
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['crud_success'] = 'Selected plans deleted.';
            } else {
                $_SESSION['crud_error'] = 'Error deleting plans.';
            }
            mysqli_stmt_close($stmt);
        }
        header('Location: index.php');
        exit;
    }

    // Single Delete
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM rack_planner WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_success'] = 'Rack plan deleted.';
        } else {
            $_SESSION['crud_error'] = 'Error deleting rack plan.';
        }
        mysqli_stmt_close($stmt);
    }
    header('Location: index.php');
    exit;
}

// Handle Save (Create/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'])) {
    itm_require_post_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $rack_units = (int)($_POST['rack_units'] ?? 42);
    $notes = trim((string)($_POST['notes'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;
    $layout_json = $_POST['layout_json'] ?? '{"version":1,"units":42,"devices":[]}';

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            $stmt = mysqli_prepare($conn, "INSERT INTO rack_planner (company_id, name, rack_units, layout_json, notes, active) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'isissi', $company_id, $name, $rack_units, $layout_json, $notes, $active);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE rack_planner SET name = ?, rack_units = ?, layout_json = ?, notes = ?, active = ? WHERE id = ? AND company_id = ?");
            mysqli_stmt_bind_param($stmt, 'sissiii', $name, $rack_units, $layout_json, $notes, $active, $id, $company_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_success'] = 'Rack plan saved.';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Error saving rack plan: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Add Sample Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($crud_action === 'index' || $crud_action === 'list_all') && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();
    $name = 'Core Rack A';
    $units = 42;
    $json = '{"version":1,"units":42,"devices":[]}';
    $notes = 'Sample empty rack plan.';
    $active = 1;
    $stmt = mysqli_prepare($conn, "INSERT INTO rack_planner (company_id, name, rack_units, layout_json, notes, active) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isissi', $company_id, $name, $units, $json, $notes, $active);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: index.php');
    exit;
}

// Data Fetching
$data = ['id' => 0, 'name' => '', 'rack_units' => 42, 'layout_json' => '{"version":1,"units":42,"devices":[]}', 'notes' => '', 'active' => 1];
if (in_array($crud_action, ['edit', 'view'])) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM rack_planner WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $data = $row;
        } else {
            $_SESSION['crud_error'] = 'Rack plan not found.';
            header('Location: index.php');
            exit;
        }
        mysqli_stmt_close($stmt);
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$sort = $_GET['sort'] ?? 'id';
$dir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$ui_config = itm_get_ui_configuration($conn, $company_id);
$perPage = itm_resolve_records_per_page($ui_config);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($crud_title); ?></title>
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
        }
        .rack-visualizer-top {
            width: 600px;
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
            width: 600px;
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
            width: 18px;
            background: #e6e6e6;
            border: 1px solid #d0d0d0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px 0;
            gap: 4px;
        }
        .rack-visualizer-rail-left { left: 55px; }
        .rack-visualizer-rail-right { right: 55px; }
        .rack-visualizer-hole {
            width: 8px;
            height: 8px;
            background: #bfbfbf;
            border-radius: 1px;
            box-shadow: inset 1px 1px 2px rgba(0,0,0,0.1);
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
        }
        .rack-visualizer-u:last-child {
            border-bottom: none;
        }
        .rack-visualizer-u::before {
            content: attr(data-u);
            position: absolute;
            left: -40px;
            color: #999;
        }
        .rack-visualizer-base {
            width: 600px;
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
            width: 520px;
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
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): echo sanitize($error) . '<br>'; endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?php echo sanitize($success_msg); ?></div>
            <?php endif; ?>

            <?php if ($crud_action === 'index' || $crud_action === 'list_all'): ?>
                <div class="rack-planner-header">
                    <h1>Rack Planner</h1>
                    <a href="create.php" class="btn btn-primary">➕</a>
                </div>

                <?php
                $whereClause = " WHERE company_id = ?";
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
                $allowedSort = ['id', 'name', 'rack_units', 'active'];
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
                ?>

                <div class="card" style="margin-bottom:16px;">
                    <form id="bulk-delete-form" method="POST" action="delete.php" style="display:flex;gap:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <button type="submit" name="bulk_action" value="bulk_delete" class="btn btn-sm btn-danger" id="bulk-delete-toggle">Select to Delete</button>
                        <?php if ($totalRows >= $perPage): ?>
                            <button type="submit" name="bulk_action" value="clear_table" class="btn btn-sm btn-danger" onclick="return confirm('Clear all records in this table? This cannot be undone.');">Clear Table</button>
                        <?php endif; ?>
                    </form>
                </div>

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
                            <a href="index.php" class="btn">🔙</a>
                        </div>
                    </form>
                </div>

                <div class="card" style="overflow:auto;">
                    <table data-itm-db-import-endpoint="index.php">
                        <thead>
                            <tr>
                                <th style="width:36px;"><input type="checkbox" id="select-all-rows"></th>
                                <th><a href="?sort=name&dir=<?php echo ($sort === 'name' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">Name <?php echo ($sort === 'name') ? ($dir === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th><a href="?sort=rack_units&dir=<?php echo ($sort === 'rack_units' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">Units <?php echo ($sort === 'rack_units') ? ($dir === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th>Notes</th>
                                <th><a href="?sort=active&dir=<?php echo ($sort === 'active' && $dir === 'ASC') ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search); ?>">Active <?php echo ($sort === 'active') ? ($dir === 'ASC' ? '▲' : '▼') : ''; ?></a></th>
                                <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "SELECT * FROM rack_planner $whereClause ORDER BY $sort $dir LIMIT ?, ?";
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
                                    <td><input type="checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>" form="bulk-delete-form"></td>
                                    <td><?php echo sanitize($row['name']); ?></td>
                                    <td><?php echo (int)$row['rack_units']; ?> U</td>
                                    <td><?php echo sanitize($row['notes']); ?></td>
                                    <td>
                                        <?php if ($row['active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
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
                                <tr><td colspan="6" style="text-align:center;">No rack plans found.</td></tr>
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
                                <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm">Previous</a>
                            <?php endif; ?>
                            <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-sm">Next</a>
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

                    <div class="form-group">
                        <label class="itm-checkbox-control">
                            <input type="checkbox" name="active" value="1" <?php echo $data['active'] ? 'checked' : ''; ?>>
                            <span>Active</span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Save</button>
                        <a href="index.php" class="btn">Cancel</a>
                    </div>
                </form>

                <div class="rack-visualizer-container">
                    <div class="rack-visualizer-top"></div>
                    <div class="rack-visualizer-frame">
                        <div class="rack-visualizer-rail rack-visualizer-rail-left">
                            <?php for($i=0; $i<$data['rack_units']*3; $i++): ?>
                                <div class="rack-visualizer-hole"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="rack-visualizer-rail rack-visualizer-rail-right">
                            <?php for($i=0; $i<$data['rack_units']*3; $i++): ?>
                                <div class="rack-visualizer-hole"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="rack-visualizer-content">
                            <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                <div class="rack-visualizer-u" data-u="<?php echo $u; ?>"></div>
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
                                    <option value="">- Empty -</option>
                                    <optgroup label="Patch Box">
                                        <option value="pb"> Cat.6a</option>
                                        <option value="pbfo">Fiber Optic</option>
                                        <option value="pbcyo">Configure Your Own</option>
                                    </optgroup>
                                    <optgroup label="Patchpanel">
                                        <option value="pp24">24-Port Patchpanel Cat.6a</option>
                                        <option value="pp48">48-Port Patchpanel Cat.6a</option>
                                        <option value="ppfo24">24-Port Patchpanel Fiber Optic</option>
                                        <option value="ppfo48">48-Port Patchpanel Fiber Optic</option>
                                    </optgroup>
                                    <optgroup label="Switch">
                                        <option value="sw24">24-Port Switch</option>
                                        <option value="sw48">48-Port Switch</option>
                                    </optgroup>
                                    <optgroup label="Server">
                                        <option value="bs">1-RU Blade Server</option>
                                        <option value="bs_2">2-RU Blade Server</option>
                                    </optgroup>
                                    <optgroup label="Other devices">
                                        <option value="ds">1-RU Data Storage</option>
                                        <option value="rt">1-RU Router</option>
                                        <option value="tr_2">2-RU Rack Tray</option>
                                        <option value="ph">1-RU Placeholder</option>
                                        <option value="ph_2">2-RU Placeholder</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($crud_action === 'view'): ?>
                <div class="rack-planner-header">
                    <h1>View Rack Plan: <?php echo sanitize($data['name']); ?></h1>
                    <div>
                        <a href="edit.php?id=<?php echo $data['id']; ?>" class="btn btn-primary">✏️ Edit</a>
                        <a href="index.php" class="btn">🔙 Back</a>
                    </div>
                </div>

                <div class="card">
                    <p><strong>Units:</strong> <?php echo (int)$data['rack_units']; ?> U</p>
                    <p><strong>Notes:</strong> <?php echo sanitize($data['notes']); ?></p>
                </div>

                <div class="rack-visualizer-container">
                    <div class="rack-visualizer-top"></div>
                    <div class="rack-visualizer-frame">
                        <div class="rack-visualizer-rail rack-visualizer-rail-left">
                            <?php for($i=0; $i<$data['rack_units']*3; $i++): ?>
                                <div class="rack-visualizer-hole"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="rack-visualizer-rail rack-visualizer-rail-right">
                            <?php for($i=0; $i<$data['rack_units']*3; $i++): ?>
                                <div class="rack-visualizer-hole"></div>
                            <?php endfor; ?>
                        </div>
                        <div class="rack-visualizer-content">
                            <?php for($u=$data['rack_units']; $u>=1; $u--): ?>
                                <div class="rack-visualizer-u" data-u="<?php echo $u; ?>"></div>
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
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/table-tools.js"></script>
<script>
(function () {
    const selectAllRows = document.getElementById('select-all-rows');
    const bulkDeleteForm = document.getElementById('bulk-delete-form');
    const toggleButton = document.getElementById('bulk-delete-toggle');
    const rowCheckboxes = document.querySelectorAll('input[name="ids[]"][form="bulk-delete-form"]');
    const deleteCells = Array.from(rowCheckboxes).map(function (checkbox) { return checkbox.closest('td'); });
    const selectAllHeaderCell = selectAllRows ? selectAllRows.closest('th') : null;
    let selectionMode = false;

    function setSelectionVisibility(visible) {
        if (selectAllHeaderCell) {
            selectAllHeaderCell.style.display = visible ? '' : 'none';
        }
        deleteCells.forEach(function (cell) {
            cell.style.display = visible ? '' : 'none';
        });
    }

    if (selectAllRows) {
        selectAllRows.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) {
                checkbox.checked = selectAllRows.checked;
            });
        });
    }

    if (bulkDeleteForm && toggleButton) {
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

            const anySelected = Array.from(rowCheckboxes).some(function (checkbox) { return checkbox.checked; });
            if (!anySelected) {
                event.preventDefault();
                alert('Please select at least one record to delete.');
                return;
            }

            if (!confirm('Delete selected records?')) {
                event.preventDefault();
            }
        });
    }

    const rackUnitModal = document.getElementById('rackUnitModal');
    const rackUnitModalClose = document.getElementById('rackUnitModalClose');
    const rackUnitModalTitle = document.getElementById('rackUnitModalTitle');
    const rackUnitCells = document.querySelectorAll('.rack-visualizer-content .rack-visualizer-u');

    if (rackUnitModal && rackUnitModalClose && rackUnitCells.length > 0) {
        rackUnitCells.forEach(function (cell) {
            cell.style.cursor = 'pointer';
            cell.addEventListener('click', function () {
                const currentUnit = cell.getAttribute('data-u') || '';
                rackUnitModalTitle.textContent = currentUnit !== '' ? ('Component ' + currentUnit) : 'Component';
                rackUnitModal.classList.add('is-open');
                rackUnitModal.setAttribute('aria-hidden', 'false');
            });
        });

        rackUnitModalClose.addEventListener('click', function () {
            rackUnitModal.classList.remove('is-open');
            rackUnitModal.setAttribute('aria-hidden', 'true');
        });

        rackUnitModal.addEventListener('click', function (event) {
            if (event.target === rackUnitModal) {
                rackUnitModal.classList.remove('is-open');
                rackUnitModal.setAttribute('aria-hidden', 'true');
            }
        });
    }
})();
</script>
</body>
</html>
