<?php
/**
 * Inventory Module - View
 * 
 * Provides a detailed summary of a single inventory record.
 * Displays stock levels, pricing, and system metadata.
 */

require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$item = null;
if ($id > 0) {
    // Fetch the item record and ensure it belongs to the active company context.
    $stmt = mysqli_prepare($conn, 'SELECT * FROM inventory_items WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $query = mysqli_stmt_get_result($stmt);
        if ($query && mysqli_num_rows($query) === 1) {
            $item = mysqli_fetch_assoc($query);
        }
        mysqli_stmt_close($stmt);
    }
}

// Display labels for the dynamic table generation below.
$labels = [
    'name' => 'Name',
    'item_code' => 'Item Code',
    'serial' => 'Serial',
    'category_id' => 'Category ID',
    'quantity_on_hand' => 'Quantity On Hand',
    'quantity_minimum' => 'Minimum Quantity',
    'price_eur' => 'Price (€)',
    'comments' => 'Comments',
    'active' => 'Status',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Inventory</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>🔎 View Inventory Record</h1>
            <div class="card">
                <?php if (!$item): ?>
                    <div class="alert alert-danger">Record not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <?php foreach ($item as $field => $value): ?>
                            <!-- Skip internal database IDs -->
                            <?php if ($field === 'id' || $field === 'company_id'): continue; endif; ?>
                            <tr>
                                <th style="width:220px;">
                                    <?php echo sanitize($labels[$field] ?? ucwords(str_replace('_', ' ', (string)$field))); ?>
                                </th>
                                <td>
                                    <?php if ($field === 'price_eur'): ?>
                                        €<?php echo number_format((float)$value, 2); ?>
                                    <?php elseif ($field === 'active'): ?>
                                        <?php echo ((int)$value === 1) ? 'Active' : 'Inactive'; ?>
                                    <?php else: ?>
                                        <?php echo sanitize((string)($value ?? '')); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn">🔙</a>
                    <?php if ($item): ?>
                        <a href="edit.php?id=<?php echo (int)$item['id']; ?>" class="btn btn-primary">✏️</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
