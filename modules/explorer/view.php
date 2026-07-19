<?php
/**
 * Explorer module — read-only metadata view for explorer index rows.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);
$row = null;

if ($id > 0 && $company_id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM explorer WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) === 1) {
            $row = mysqli_fetch_assoc($res);
        }
        mysqli_stmt_close($stmt);
    }
}

$crud_title = 'Explorer item';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($crud_title); ?> - <?php echo sanitize($app_name ?? 'IT Management'); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="View explorer item">🔎</h1>
            <div class="card">
                <?php if (!$row): ?>
                    <div class="alert alert-danger">Record not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">Path</th><td><?php echo sanitize((string)($row['folder_path'] ?? '')); ?></td></tr>
                        <tr><th>File</th><td><?php echo sanitize((string)($row['file_name'] ?? '')); ?></td></tr>
                        <tr><th>Type</th><td><?php echo sanitize((string)($row['file_type'] ?? '')); ?></td></tr>
                        <?php itm_crud_render_view_audit_meta_rows($conn, (int)$company_id, $row); ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
