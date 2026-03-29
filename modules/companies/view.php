<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$item = null;
if ($id > 0) {
    $query = mysqli_query($conn, "SELECT * FROM companies WHERE id = $id LIMIT 1");
    if ($query && mysqli_num_rows($query) === 1) {
        $item = mysqli_fetch_assoc($query);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Companies</title>
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
                <?php if (!$item): ?>
                    <div class="alert alert-danger">Record not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                            <tr><th style="width:220px;">Information</th><td></td></tr>
                            <tr><th>Company</th><td><?php echo sanitize((string)($item['company'] ?? '')); ?></td></tr>
                            <tr><th>InCode</th><td><?php echo sanitize((string)($item['incode'] ?? '')); ?></td></tr>
                            <tr><th>Location</th><td><?php echo sanitize(trim((string)($item['city'] ?? '') . ', ' . (string)($item['country'] ?? ''), ', ')); ?></td></tr>
                            <tr><th>Phone</th><td><?php echo sanitize((string)($item['phone'] ?? '')); ?></td></tr>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn">Back</a>
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
