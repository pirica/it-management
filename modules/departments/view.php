<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$item = null;

if ($id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT id, name, description, active FROM departments WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
            $item = mysqli_fetch_assoc($result);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Department</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>🔎 Department Details</h1>
            <div class="card">
                <?php if (!$item): ?>
                    <div class="alert alert-danger">Department not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">ID</th><td><?php echo (int)$item['id']; ?></td></tr>
                        <tr><th>Name</th><td><?php echo sanitize($item['name']); ?></td></tr>
                        <tr><th>Description</th><td><?php echo sanitize((string)($item['description'] ?? '')); ?></td></tr>
                        <tr><th>Status</th><td><?php echo (int)$item['active'] === 1 ? '✅ Active' : '❌ Inactive'; ?></td></tr>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:16px;">
                    <a class="btn" href="index.php">Back</a>
                    <?php if ($item): ?>
                        <a class="btn btn-primary" href="edit.php?id=<?php echo (int)$item['id']; ?>">✏️ Edit</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
