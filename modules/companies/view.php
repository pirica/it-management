<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$item = null;
$error = '';
$notice = '';

if ($id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM companies WHERE id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
            $item = mysqli_fetch_assoc($result);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = 'Failed to load company.';
    }
}

if ($item === null && $error === '') {
    $fallbackResult = mysqli_query($conn, 'SELECT * FROM companies ORDER BY id ASC LIMIT 1');
    if ($fallbackResult && mysqli_num_rows($fallbackResult) === 1) {
        $item = mysqli_fetch_assoc($fallbackResult);
        $notice = $id > 0 ? 'Company not found. Showing the first available company record.' : 'No company id provided. Showing the first available company record.';
    } else {
        $error = 'No company records found.';
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
                    <?php if ($notice !== ''): ?>
                        <div class="alert alert-warning" style="margin-bottom:12px;"><?php echo sanitize($notice); ?></div>
                    <?php endif; ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">ID</th><td><?php echo (int)$item['id']; ?></td></tr>
                        <tr><th>Company</th><td><?php echo sanitize((string)($item['company'] ?? '')); ?></td></tr>
                        <tr><th>InCode</th><td><?php echo sanitize((string)($item['incode'] ?? '')); ?></td></tr>
                        <tr><th>City</th><td><?php echo sanitize((string)($item['city'] ?? '')); ?></td></tr>
                        <tr><th>Country</th><td><?php echo sanitize((string)($item['country'] ?? '')); ?></td></tr>
                        <tr><th>Phone</th><td><?php echo sanitize((string)($item['phone'] ?? '')); ?></td></tr>
                        <tr><th>Email</th><td><?php echo sanitize((string)($item['email'] ?? '')); ?></td></tr>
                        <tr><th>Website</th><td><?php echo sanitize((string)($item['website'] ?? '')); ?></td></tr>
                        <tr><th>VAT</th><td><?php echo sanitize((string)($item['vat'] ?? '')); ?></td></tr>
                        <tr><th>Comments</th><td><?php echo nl2br(sanitize((string)($item['comments'] ?? ''))); ?></td></tr>
                        <tr><th>Status</th><td><?php echo (int)($item['active'] ?? 0) === 1 ? 'Active' : 'Inactive'; ?></td></tr>
                        <tr><th>Created</th><td><?php echo sanitize((string)($item['created_at'] ?? '')); ?></td></tr>
                        <tr><th>Updated</th><td><?php echo sanitize((string)($item['updated_at'] ?? '')); ?></td></tr>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn">Back</a>
                    <?php if ($item !== null): ?>
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
