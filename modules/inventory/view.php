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
    // Fetch the item record with related labels so FK values stay human-readable.
    $stmt = mysqli_prepare(
        $conn,
        'SELECT i.*,
                c.name AS category_name,
                m.name AS manufacturer_name,
                l.name AS location_name,
                s.name AS supplier_name,
                COALESCE(
                    NULLIF(TRIM(CONCAT(COALESCE(e_scoped.first_name, \'\'), \' \', COALESCE(e_scoped.last_name, \'\'))), \'\'),
                    NULLIF(TRIM(COALESCE(e_scoped.username, \'\')), \'\'),
                    NULLIF(TRIM(CONCAT(COALESCE(e_fallback.first_name, \'\'), \' \', COALESCE(e_fallback.last_name, \'\'))), \'\'),
                    NULLIF(TRIM(COALESCE(e_fallback.username, \'\')), \'\')
                ) AS last_user_label
         FROM inventory_items i
         LEFT JOIN inventory_categories c ON c.id = i.category_id
         LEFT JOIN manufacturers m ON m.id = i.manufacturer_id
         LEFT JOIN it_locations l ON l.id = i.location_id
         LEFT JOIN suppliers s ON s.id = i.supplier_id
         LEFT JOIN employees e_scoped ON e_scoped.id = i.last_user_id AND e_scoped.company_id = i.company_id
         LEFT JOIN employees e_fallback ON e_fallback.id = i.last_user_id
         WHERE i.id = ? AND i.company_id = ?
         LIMIT 1'
    );
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
    'storage_date' => 'Storage Date',
    'category_id' => 'Category',
    'manufacturer_id' => 'Manufacturer',
    'location_id' => 'Location',
    'supplier_id' => 'Supplier',
    'quantity_on_hand' => 'Quantity On Hand',
    'quantity_minimum' => 'Minimum Quantity',
    'price_eur' => 'Price (€)',
    'last_user_id' => 'Last User',
    'last_user_manual' => 'Last User (Manual)',
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
                            <?php if (in_array($field, ['category_name', 'manufacturer_name', 'location_name', 'supplier_name'], true)): continue; endif; ?>
                            <tr>
                                <th style="width:220px;">
                                    <?php echo sanitize($labels[$field] ?? ucwords(str_replace('_', ' ', (string)$field))); ?>
                                </th>
                                <td>
                                    <?php if ($field === 'price_eur'): ?>
                                        €<?php echo number_format((float)$value, 2); ?>
                                    <?php elseif ($field === 'active'): ?>
                                        <?php echo ((int)$value === 1) ? 'Active' : 'Inactive'; ?>
                                    <?php elseif ($field === 'category_id'): ?>
                                        <?php echo sanitize((string)($item['category_name'] ?? '')); ?>
                                    <?php elseif ($field === 'manufacturer_id'): ?>
                                        <?php echo sanitize((string)($item['manufacturer_name'] ?? '')); ?>
                                    <?php elseif ($field === 'location_id'): ?>
                                        <?php echo sanitize((string)($item['location_name'] ?? '')); ?>
                                    <?php elseif ($field === 'supplier_id'): ?>
                                        <?php echo sanitize((string)($item['supplier_name'] ?? '')); ?>
                                    <?php elseif ($field === 'last_user_id'): ?>
                                        <?php
                                        $lastUserLabel = trim((string)($item['last_user_label'] ?? ''));
                                        $lastUserManual = trim((string)($item['last_user_manual'] ?? ''));
                                        echo sanitize($lastUserLabel !== '' ? $lastUserLabel : ($lastUserManual !== '' ? $lastUserManual : '-'));
                                        ?>
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
