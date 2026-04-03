<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';
$data = [
    'name' => '',
    'item_code' => '',
    'serial' => '',
    'comments' => '',
    'category_id' => '',
    'quantity_on_hand' => 0,
    'quantity_minimum' => 5,
    'price_eur' => '',
    'active' => 1,
];
$csrfToken = itm_get_csrf_token();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $name = escape_sql($_POST['name'] ?? '', $conn);
    $item_code = escape_sql($_POST['item_code'] ?? '', $conn);
    $serial = escape_sql($_POST['serial'] ?? '', $conn);
    $comments = escape_sql($_POST['comments'] ?? '', $conn);

    $category_post = $_POST['category_id'] ?? 0;
    if ($category_post === '__add_new__') {
        $category_post = 0;
    }

    $category_id = (int)$category_post;
    $category_sql = $category_id ?: 'NULL';
    $quantity_on_hand = (int)($_POST['quantity_on_hand'] ?? 0);
    $quantity_minimum = (int)($_POST['quantity_minimum'] ?? 5);
    $price_eur = (float)($_POST['price_eur'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    if (!$name) {
        $error = 'Item name is required.';
    } else {
        if ($is_edit) {
            $sql = "UPDATE inventory_items
                    SET name='$name',
                        item_code='$item_code',
                        serial='$serial',
                        comments='$comments',
                        category_id=$category_sql,
                        quantity_on_hand=$quantity_on_hand,
                        quantity_minimum=$quantity_minimum,
                        price_eur=$price_eur,
                        active=$active
                    WHERE id=$id AND company_id=$company_id";
        } else {
            $sql = "INSERT INTO inventory_items
                    (company_id,name,item_code,serial,comments,category_id,quantity_on_hand,quantity_minimum,price_eur,active)
                    VALUES
                    ($company_id,'$name','$item_code','$serial','$comments',$category_sql,$quantity_on_hand,$quantity_minimum,$price_eur,$active)";
        }

        $dbErrorCode = 0;
        $dbErrorMessage = '';
        if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
            header('Location: index.php');
            exit;
        }
        $error = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
    }
}

$categories = mysqli_query($conn, "SELECT id,name FROM inventory_categories WHERE company_id=$company_id AND active=1 ORDER BY name");
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
                            <label>Category</label>
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
                            <label class="role-flag-option"><input type="checkbox" name="active" <?php echo (int)$data['active'] === 1 ? 'checked' : ''; ?>> <span>Active</span></label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Comments</label>
                        <textarea name="comments" rows="4"><?php echo sanitize((string)($data['comments'] ?? '')); ?></textarea>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a class="btn" href="index.php">✖️</a>
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
