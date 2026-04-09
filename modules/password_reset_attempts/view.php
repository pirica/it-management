<?php
/**
 * Password Reset Attempts - View single record.
 * Why: allow quick inspection of reset-throttle entries for current company users.
 */
require '../../config/config.php';

$recordId = (int)($_GET['id'] ?? 0);
$scopeSql = '(u.company_id = ? OR (pra.user_id IS NULL AND EXISTS (SELECT 1 FROM users ux WHERE ux.company_id = ? AND ux.email = pra.email)))';

$sql = "SELECT pra.*, COALESCE(u.username, '') AS username FROM password_reset_attempts pra LEFT JOIN users u ON u.id = pra.user_id WHERE pra.id = ? AND {$scopeSql} LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
$record = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iii', $recordId, $company_id, $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        $record = mysqli_fetch_assoc($result);
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Password Reset Attempt</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>👁️ View Password Reset Attempt</h1>
            <?php if (!$record): ?>
                <div class="alert alert-danger">Record not found.</div>
            <?php else: ?>
                <div class="card">
                    <p><strong>ID:</strong> <?= (int)$record['id'] ?></p>
                    <p><strong>User:</strong> <?= sanitize($record['username'] ?: ($record['email'] ?: 'N/A')) ?></p>
                    <p><strong>Email:</strong> <?= sanitize((string)$record['email']) ?></p>
                    <p><strong>Attempt Type:</strong> <?= sanitize((string)$record['attempt_type']) ?></p>
                    <p><strong>IP Address:</strong> <?= sanitize((string)$record['ip_address']) ?></p>
                    <p><strong>Created At:</strong> <?= sanitize((string)$record['created_at']) ?></p>
                </div>
            <?php endif; ?>
            <div style="margin-top:10px;">
                <a class="btn" href="index.php">🔙 Back</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
