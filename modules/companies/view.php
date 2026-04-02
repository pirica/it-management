<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$item = null;
$itemNormalized = null;
$error = '';

/**
 * Get value from row with case-insensitive key support and optional aliases.
 *
 * @param array<string,mixed>|null $row
 * @param string[] $keys
 */
function itm_company_view_value(?array $row, array $keys, string $default = ''): string
{
    if ($row === null) {
        return $default;
    }

    foreach ($keys as $key) {
        $lookup = strtolower($key);
        if (array_key_exists($lookup, $row) && $row[$lookup] !== null) {
            return (string)$row[$lookup];
        }
    }

    return $default;
}

if ($id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM companies WHERE id = ? AND id > 0 LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
            $item = mysqli_fetch_assoc($result);
            if (is_array($item)) {
                $itemNormalized = [];
                foreach ($item as $key => $value) {
                    if (is_string($key)) {
                        $itemNormalized[strtolower($key)] = $value;
                    }
                }
            }
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = 'Failed to load company.';
    }
}

if ($item === null && $error === '') {
    if ($id > 0) {
        $error = 'Company not found for ID ' . $id . '.';
    } else {
        $error = 'Invalid company id.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Company</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>🔎 Company Information</h1>
            <div class="card">
                <?php if ($item === null): ?>
                    <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">ID</th><td><?php echo (int)itm_company_view_value($itemNormalized, ['id']); ?></td></tr>
                        <tr><th>Company</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['company'])); ?></td></tr>
                        <tr><th>InCode</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['incode'])); ?></td></tr>
                        <tr><th>City</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['city'])); ?></td></tr>
                        <tr><th>Country</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['country'])); ?></td></tr>
                        <tr><th>Phone</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['phone'])); ?></td></tr>
                        <tr><th>Email</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['email'])); ?></td></tr>
                        <tr><th>Website</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['website'])); ?></td></tr>
                        <tr><th>VAT</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['vat'])); ?></td></tr>
                        <tr><th>Comments</th><td><?php echo nl2br(sanitize(itm_company_view_value($itemNormalized, ['comments']))); ?></td></tr>
                        <?php $activeValue = (int)itm_company_view_value($itemNormalized, ['active', 'status'], '0'); ?>
                        <tr><th>Status</th><td><?php echo $activeValue === 1 ? 'Active' : 'Inactive'; ?></td></tr>
                        <tr><th>Created</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['created_at', 'created'])); ?></td></tr>
                        <tr><th>Updated</th><td><?php echo sanitize(itm_company_view_value($itemNormalized, ['updated_at', 'updated'])); ?></td></tr>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn">Back</a>
                    <?php if ($item !== null): ?>
                        <a href="edit.php?id=<?php echo (int)itm_company_view_value($itemNormalized, ['id']); ?>" class="btn btn-primary">✏️</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
