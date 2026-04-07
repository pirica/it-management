<?php
/**
 * Master CRUD Controller Template
 * 
 * Provides a standardized logic for Create, Read, Update, and Delete operations
 * for simple lookup tables (e.g., categories, statuses).
 * Includes CSRF protection, audit logging, search, and basic UI rendering.
 */

require '../../config/config.php';

// Configuration: $crud_table and $crud_title should be defined before including this file
$crud_table = $crud_table ?? '';
$crud_title = $crud_title ?? '';
$pk = 'id';

if (!$crud_table) {
    die("Table not specified");
}

// Route parameters
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$errors = [];
$success = '';

// Global CSRF enforcement for all state-changing operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
}

// --- DELETE Action Handler ---
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch old record state for audit logging
    $stmt = mysqli_prepare($conn, "SELECT * FROM `$crud_table` WHERE $pk = ? AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $old_values = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($old_values) {
        $stmt = mysqli_prepare($conn, "DELETE FROM `$crud_table` WHERE $pk = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        if (mysqli_stmt_execute($stmt)) {
            // Log the deletion to the audit trail
            itm_log_audit($conn, $crud_table, $id, 'DELETE', $old_values);
            header("Location: index.php?success=deleted");
            exit;
        } else {
            $errors[] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
        }
        mysqli_stmt_close($stmt);
    } else {
        $errors[] = "Record not found.";
    }
}

// --- CREATE / EDIT Action Handler ---
if (in_array($action, ['create', 'edit']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        $errors[] = "Name is required.";
    }

    if (empty($errors)) {
        if ($action === 'edit') {
            // Fetch old record state before updating
            $stmt = mysqli_prepare($conn, "SELECT * FROM `$crud_table` WHERE $pk = ? AND company_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
            mysqli_stmt_execute($stmt);
            $old_values = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE `$crud_table` SET name = ? WHERE $pk = ? AND company_id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $name, $id, $company_id);
            if (mysqli_stmt_execute($stmt)) {
                // Log the update to the audit trail
                itm_log_audit($conn, $crud_table, $id, 'UPDATE', $old_values, ['name' => $name]);
                header("Location: index.php?success=updated");
                exit;
            } else {
                $errors[] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        } else {
            // Handle new record insertion
            $stmt = mysqli_prepare($conn, "INSERT INTO `$crud_table` (company_id, name) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'is', $company_id, $name);
            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                // Log the insertion to the audit trail
                itm_log_audit($conn, $crud_table, $new_id, 'INSERT', null, ['company_id' => $company_id, 'name' => $name]);
                header("Location: index.php?success=created");
                exit;
            } else {
                $errors[] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Display flash messages from URL parameters
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') $success = "$crud_title created successfully.";
    if ($_GET['success'] === 'updated') $success = "$crud_title updated successfully.";
    if ($_GET['success'] === 'deleted') $success = "$crud_title deleted successfully.";
}

// --- Fetch Single Record for View/Edit ---
$data = ['name' => ''];
if (($action === 'edit' || $action === 'view') && $id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM `$crud_table` WHERE $pk = ? AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $data = $row;
    } else {
        $errors[] = "Record not found.";
        $action = 'list';
    }
    mysqli_stmt_close($stmt);
}

// --- Fetch List of Records with Search Filtering ---
$search = $_GET['search'] ?? '';
$where = " WHERE company_id = ?";
$params = [$company_id];
$types = "i";
if ($search) {
    $where .= " AND name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

$stmt = mysqli_prepare($conn, "SELECT * FROM `$crud_table` $where ORDER BY name ASC");
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$items = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

$csrfToken = itm_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $crud_title; ?> - IT Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <!-- Global Feedback Messages -->
            <?php if ($errors): ?>
                <?php foreach ($errors as $err): ?>
                    <div class="alert alert-danger"><?php echo sanitize($err); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo sanitize($success); ?></div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <!-- List View Container -->
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h1><?php echo $crud_title; ?></h1>
                    <a href="?action=create" class="btn btn-primary">➕ Add New</a>
                </div>
                
                <div class="card" style="margin-bottom:16px;">
                    <form method="GET" style="display:flex;gap:10px;align-items:flex-end;">
                        <div class="form-group" style="margin:0;flex:1;">
                            <label>Search</label>
                            <input type="text" name="search" value="<?php echo sanitize($search); ?>" placeholder="Search by name...">
                        </div>
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="index.php" class="btn">Clear</a>
                    </form>
                </div>

                <div class="card">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th class="itm-actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = mysqli_fetch_assoc($items)): ?>
                                <tr>
                                    <td><?php echo sanitize($item['name']); ?></td>
                                    <td class="itm-actions-cell">
                                        <div class="itm-actions-wrap">
                                            <a href="?action=view&id=<?php echo $item['id']; ?>" class="btn btn-sm">👁️</a>
                                            <a href="?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm">✏️</a>
                                            <form method="POST" action="?action=delete&id=<?php echo $item['id']; ?>" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif (in_array($action, ['create', 'edit'])): ?>
                <!-- Form View Container -->
                <h1><?php echo ($action === 'edit' ? '✏️ Edit' : '➕ Add'); ?> <?php echo $crud_title; ?></h1>
                <div class="card">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" name="name" value="<?php echo sanitize($data['name']); ?>" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">💾 Save</button>
                            <a href="index.php" class="btn">✖️ Cancel</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($action === 'view'): ?>
                <!-- Detailed Record View Container -->
                <h1>👁️ View <?php echo $crud_title; ?></h1>
                <div class="card">
                    <table class="detail-table">
                        <tr><th>Name:</th><td><?php echo sanitize($data['name']); ?></td></tr>
                    </table>
                    <div class="form-actions" style="margin-top:20px;">
                        <a href="?action=edit&id=<?php echo $id; ?>" class="btn btn-primary">✏️ Edit</a>
                        <a href="index.php" class="btn">⬅️ Back</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
