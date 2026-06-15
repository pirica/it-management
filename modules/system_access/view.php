<?php
/**
 * System Access Module - View
 * 
 * Provides a detailed summary of a system access type.
 */

require '../../config/config.php';
require '../../includes/employee_system_access.php';

// Lazy schema setup.
esa_ensure_table($conn);

$id = (int)($_GET['id'] ?? 0);
$item = null;
if ($id > 0) {
    // Fetch record scoped by company.
    $stmt = mysqli_prepare($conn, 'SELECT * FROM system_access WHERE id = ? AND company_id = ? LIMIT 1');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View System Access</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>🔎 View System Access Record</h1>
            <div class="card">
                <?php if (!$item): ?>
                    <div class="alert alert-danger">Record not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr>
                            <th>Code</th>
                            <td><?php echo sanitize((string)($item['code'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th>Name</th>
                            <td><?php echo sanitize((string)($item['name'] ?? '')); ?></td>
                        </tr>
                        <tr>
                            <th>Active</th>
                            <td><?php echo (int)($item['active'] ?? 0) === 1 ? '✅' : '❌'; ?></td>
                        </tr>
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
