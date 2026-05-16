<?php
/**
 * Inventory Module - Create/Edit
 * 
 * Handles both the addition of new inventory items and the updating of existing ones.
 * Supports:
 * - Dynamic category selection with inline "Add New" capability via JS.
 * - Minimum stock threshold tracking (quantity_minimum).
 * - Price tracking in EUR.
 * - Activation toggles.
 */

require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';

// Default values for a fresh record.
$data = [
    'name' => '',
    'item_code' => '',
    'serial' => '',
    'storage_date' => '',
    'comments' => '',
    'category_id' => '',
    'location_id' => '',
    'manufacturer_id' => '',
    'supplier_id' => '',
    'quantity_on_hand' => 0,
    'quantity_minimum' => 5,
    'price_eur' => '',
    'last_user_id' => '',
    'last_user_manual' => '',
    'active' => 1,
    'created_at' => '',
    'updated_at' => '',
];
$csrfToken = itm_get_csrf_token();

// Load existing data if we are in Edit mode.
if ($is_edit) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM inventory_items WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $q = mysqli_stmt_get_result($stmt);
        if ($q && mysqli_num_rows($q) === 1) {
            $data = mysqli_fetch_assoc($q);
        } else {
            $error = 'Item not found.';
            $is_edit = false;
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    
    // Sanitize basic string inputs.
    $name = escape_sql($_POST['name'] ?? '', $conn);
    $item_code = escape_sql($_POST['item_code'] ?? '', $conn);
    $serial = escape_sql($_POST['serial'] ?? '', $conn);
    $storage_date_post = trim((string)($_POST['storage_date'] ?? ''));
    $storage_date = $storage_date_post !== '' ? escape_sql($storage_date_post, $conn) : '';
    $comments = escape_sql($_POST['comments'] ?? '', $conn);

    // Normalize category selection.
    $category_post = $_POST['category_id'] ?? 0;
    if ($category_post === '__add_new__') { $category_post = 0; }
    $category_id = (int)$category_post;
    $category_sql = $category_id ?: 'NULL';
    $location_post = $_POST['location_id'] ?? 0;
    if ($location_post === '__add_new__') { $location_post = 0; }
    $location_id = (int)$location_post;
    $location_sql = $location_id ?: 'NULL';
    $manufacturer_post = $_POST['manufacturer_id'] ?? 0;
    if ($manufacturer_post === '__add_new__') { $manufacturer_post = 0; }
    $manufacturer_id = (int)$manufacturer_post;
    $manufacturer_sql = $manufacturer_id ?: 'NULL';
    $supplier_post = $_POST['supplier_id'] ?? 0;
    if ($supplier_post === '__add_new__') { $supplier_post = 0; }
    $supplier_id = (int)$supplier_post;
    $supplier_sql = $supplier_id ?: 'NULL';
    $last_user_post = $_POST['last_user_id'] ?? 0;
    if ($last_user_post === '__add_new__') { $last_user_post = 0; }
    $last_user_id = (int)$last_user_post;
    $last_user_sql = $last_user_id ?: 'NULL';
    $last_user_manual_post = trim((string)($_POST['last_user_manual'] ?? ''));
    $last_user_manual = $last_user_manual_post !== '' ? escape_sql($last_user_manual_post, $conn) : '';
    $last_user_manual_sql = $last_user_manual !== '' ? "'$last_user_manual'" : 'NULL';

    // Parse numeric inputs.
    $quantity_on_hand = (int)($_POST['quantity_on_hand'] ?? 0);
    $quantity_minimum = (int)($_POST['quantity_minimum'] ?? 5);
    $price_eur = (float)($_POST['price_eur'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    if (!$name) {
        $error = 'Item name is required.';
    } else {
        // Construct SQL query based on mode (Insert vs Update).
        if ($is_edit) {
            $sql = "UPDATE inventory_items
                    SET name='$name',
                        item_code='$item_code',
                        serial='$serial',
                        storage_date=" . ($storage_date !== '' ? "'$storage_date'" : "NULL") . ",
                        comments='$comments',
                        category_id=$category_sql,
                        location_id=$location_sql,
                        manufacturer_id=$manufacturer_sql,
                        supplier_id=$supplier_sql,
                        quantity_on_hand=$quantity_on_hand,
                        quantity_minimum=$quantity_minimum,
                        price_eur=$price_eur,
                        last_user_id=$last_user_sql,
                        last_user_manual=$last_user_manual_sql,
                        active=$active
                    WHERE id=$id AND company_id=$company_id";
        } else {
            $sql = "INSERT INTO inventory_items
                    (company_id,name,item_code,serial,storage_date,comments,category_id,location_id,manufacturer_id,supplier_id,quantity_on_hand,quantity_minimum,price_eur,last_user_id,last_user_manual,active)
                    VALUES
                    ($company_id,'$name','$item_code','$serial'," . ($storage_date !== '' ? "'$storage_date'" : "NULL") . ",'$comments',$category_sql,$location_sql,$manufacturer_sql,$supplier_sql,$quantity_on_hand,$quantity_minimum,$price_eur,$last_user_sql,$last_user_manual_sql,$active)";
        }

        $dbErrorCode = 0;
        $dbErrorMessage = '';
        // Execute and redirect on success.
        if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
            header('Location: index.php');
            exit;
        }
        // Handle database-level errors (like unique constraint violations).
        $error = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
    }
}

// Fetch active categories for the dropdown.
$categories = mysqli_query($conn, "SELECT id,name FROM inventory_categories WHERE company_id=$company_id AND active=1 ORDER BY name");
$locations = mysqli_query($conn, "SELECT id,name FROM it_locations WHERE company_id=$company_id AND active=1 ORDER BY name");
$manufacturers = mysqli_query($conn, "SELECT id,name FROM manufacturers WHERE company_id=$company_id AND active=1 ORDER BY name");
$suppliers = mysqli_query($conn, "SELECT id,name FROM suppliers WHERE company_id=$company_id AND active=1 ORDER BY name");
$lastUsers = [];
$lastUsersResult = mysqli_query($conn, "SELECT id, COALESCE(NULLIF(display_name, ''), CONCAT(first_name, ' ', last_name)) AS display_name FROM employees WHERE company_id=$company_id ORDER BY display_name");
if ($lastUsersResult) {
    while ($lastUserRow = mysqli_fetch_assoc($lastUsersResult)) {
        $lastUsers[] = $lastUserRow;
    }
}

$selectedLastUserId = (int)($data['last_user_id'] ?? 0);
if ($selectedLastUserId > 0) {
    $hasSelectedLastUser = false;
    foreach ($lastUsers as $lastUserOption) {
        if ((int)($lastUserOption['id'] ?? 0) === $selectedLastUserId) {
            $hasSelectedLastUser = true;
            break;
        }
    }

    if (!$hasSelectedLastUser) {
        $lastUserFallbackStmt = mysqli_prepare($conn, "SELECT id, COALESCE(NULLIF(display_name, ''), CONCAT(first_name, ' ', last_name)) AS display_name FROM employees WHERE id = ? LIMIT 1");
        if ($lastUserFallbackStmt) {
            mysqli_stmt_bind_param($lastUserFallbackStmt, 'i', $selectedLastUserId);
            mysqli_stmt_execute($lastUserFallbackStmt);
            $lastUserFallbackResult = mysqli_stmt_get_result($lastUserFallbackStmt);
            if ($lastUserFallbackResult && ($lastUserFallbackRow = mysqli_fetch_assoc($lastUserFallbackResult))) {
                $lastUsers[] = $lastUserFallbackRow;
            }
            mysqli_stmt_close($lastUserFallbackStmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Inventory Item</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1><?php echo $is_edit ? '✏️ Edit' : '➕ Add'; ?> Inventory Item</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Name *</label>
                            <input required name="name" value="<?php echo sanitize((string)$data['name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Item Code</label>
                            <input name="item_code" value="<?php echo sanitize((string)$data['item_code']); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Serial</label>
                            <input name="serial" value="<?php echo sanitize((string)($data['serial'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Storage Date</label>
                            <input type="date" name="storage_date" value="<?php echo sanitize((string)($data['storage_date'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <!-- data-addable-select="1" enables the JS inline addition of new options -->
                            <select name="category_id"
                                    data-addable-select="1"
                                    data-add-table="inventory_categories"
                                    data-add-id-col="id"
                                    data-add-label-col="name"
                                    data-add-company-scoped="1"
                                    data-add-friendly="inventory category">
                                <option value="">-- None --</option>
                                <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (string)$data['category_id'] === (string)$c['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize((string)$c['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <select name="location_id"
                                    data-addable-select="1"
                                    data-add-table="it_locations"
                                    data-add-id-col="id"
                                    data-add-label-col="name"
                                    data-add-company-scoped="1"
                                    data-add-friendly="location">
                                <option value="">-- None --</option>
                                <?php while ($loc = mysqli_fetch_assoc($locations)): ?>
                                    <option value="<?php echo (int)$loc['id']; ?>" <?php echo (string)($data['location_id'] ?? '') === (string)$loc['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize((string)$loc['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Manufacturer</label>
                            <select name="manufacturer_id"
                                    data-addable-select="1"
                                    data-add-table="manufacturers"
                                    data-add-id-col="id"
                                    data-add-label-col="name"
                                    data-add-company-scoped="1"
                                    data-add-friendly="manufacturer">
                                <option value="">-- None --</option>
                                <?php while ($manufacturer = mysqli_fetch_assoc($manufacturers)): ?>
                                    <option value="<?php echo (int)$manufacturer['id']; ?>" <?php echo (string)($data['manufacturer_id'] ?? '') === (string)$manufacturer['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize((string)$manufacturer['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Supplier</label>
                            <select name="supplier_id"
                                    data-addable-select="1"
                                    data-add-table="suppliers"
                                    data-add-id-col="id"
                                    data-add-label-col="name"
                                    data-add-company-scoped="1"
                                    data-add-friendly="supplier">
                                <option value="">-- None --</option>
                                <?php while ($supplier = mysqli_fetch_assoc($suppliers)): ?>
                                    <option value="<?php echo (int)$supplier['id']; ?>" <?php echo (string)($data['supplier_id'] ?? '') === (string)$supplier['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize((string)$supplier['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Quantity On Hand</label>
                            <input type="number" name="quantity_on_hand" value="<?php echo (int)$data['quantity_on_hand']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Minimum Quantity</label>
                            <input type="number" name="quantity_minimum" value="<?php echo (int)$data['quantity_minimum']; ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Price (€)</label>
                            <input type="number" step="0.01" min="0" name="price_eur" value="<?php echo sanitize((string)($data['price_eur'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Last User</label>
                            <select name="last_user_id">
                                <option value="">-- None --</option>
                                <?php foreach ($lastUsers as $lastUser): ?>
                                    <option value="<?php echo (int)$lastUser['id']; ?>" <?php echo (string)($data['last_user_id'] ?? '') === (string)$lastUser['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize((string)$lastUser['display_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Last User (Manual)</label>
                            <input type="text" maxlength="100" name="last_user_manual" value="<?php echo sanitize((string)($data['last_user_manual'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="role-flag-option">
                                <input type="checkbox" name="active" <?php echo (int)$data['active'] === 1 ? 'checked' : ''; ?>> 
                                <span>Active</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Comments</label>
                        <textarea name="comments" rows="4"><?php echo sanitize((string)($data['comments'] ?? '')); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Created At</label>
                            <input value="<?php echo sanitize((string)($data['created_at'] ?? '')); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label>Updated At</label>
                            <input value="<?php echo sanitize((string)($data['updated_at'] ?? '')); ?>" disabled>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/select-add-option.js"></script>
</body>
</html>
