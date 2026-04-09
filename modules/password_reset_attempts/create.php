<?php
/**
 * Password Reset Attempts - Create/Edit form.
 * Why: keep operational support access to reset-attempt records while preserving
 * tenant boundaries through users.company_id and preventing accidental user changes.
 */
require '../../config/config.php';

$recordId = (int)($_GET['id'] ?? 0);
$isEdit = $recordId > 0;
$error = '';

$data = [
    'user_id' => null,
    'user_label' => '',
    'email' => '',
    'attempt_type' => 'request',
    'ip_address' => '',
];

$scopeSql = '(u.company_id = ? OR (pra.user_id IS NULL AND EXISTS (SELECT 1 FROM users ux WHERE ux.company_id = ? AND ux.email = pra.email)))';

if ($isEdit) {
    $sql = "SELECT pra.*, COALESCE(u.username, '') AS username FROM password_reset_attempts pra LEFT JOIN users u ON u.id = pra.user_id WHERE pra.id = ? AND {$scopeSql} LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iii', $recordId, $company_id, $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $data['user_id'] = $row['user_id'] !== null ? (int)$row['user_id'] : null;
            $data['user_label'] = (string)($row['username'] ?: $row['email'] ?: 'N/A');
            $data['email'] = (string)($row['email'] ?? '');
            $data['attempt_type'] = (string)($row['attempt_type'] ?? 'request');
            $data['ip_address'] = (string)($row['ip_address'] ?? '');
        } else {
            $error = 'Record not found or outside your company scope.';
            $isEdit = false;
        }
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $email = trim((string)($_POST['email'] ?? ''));
    $attemptType = (string)($_POST['attempt_type'] ?? 'request');
    $ipAddress = trim((string)($_POST['ip_address'] ?? ''));

    if (!in_array($attemptType, ['request', 'reset'], true)) {
        $error = 'Invalid attempt type.';
    } elseif ($ipAddress === '') {
        $error = 'IP address is required.';
    } elseif (strlen($ipAddress) > 45) {
        $error = 'IP address is too long.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email must be a valid email address.';
    } else {
        if ($isEdit) {
            $updateSql = "UPDATE password_reset_attempts pra LEFT JOIN users u ON u.id = pra.user_id SET pra.email = ?, pra.attempt_type = ?, pra.ip_address = ? WHERE pra.id = ? AND {$scopeSql}";
            $stmt = mysqli_prepare($conn, $updateSql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssiii', $email, $attemptType, $ipAddress, $recordId, $company_id, $company_id);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: index.php');
                    exit;
                }
                $error = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
                mysqli_stmt_close($stmt);
            } else {
                $error = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
            }
        } else {
            $userId = null;
            if ($email !== '') {
                $userLookup = mysqli_prepare($conn, 'SELECT id, username FROM users WHERE company_id = ? AND email = ? LIMIT 1');
                if ($userLookup) {
                    mysqli_stmt_bind_param($userLookup, 'is', $company_id, $email);
                    mysqli_stmt_execute($userLookup);
                    $userResult = mysqli_stmt_get_result($userLookup);
                    if ($userResult && ($userRow = mysqli_fetch_assoc($userResult))) {
                        $userId = (int)$userRow['id'];
                        $data['user_label'] = (string)$userRow['username'];
                    }
                    mysqli_stmt_close($userLookup);
                }
            }

            $insertSql = 'INSERT INTO password_reset_attempts (user_id, email, attempt_type, ip_address) VALUES (?, ?, ?, ?)';
            $stmt = mysqli_prepare($conn, $insertSql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'isss', $userId, $email, $attemptType, $ipAddress);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header('Location: index.php');
                    exit;
                }
                $error = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
                mysqli_stmt_close($stmt);
            } else {
                $error = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
            }
        }

        $data['email'] = $email;
        $data['attempt_type'] = $attemptType;
        $data['ip_address'] = $ipAddress;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit' : 'Add' ?> Password Reset Attempt</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1><?= $isEdit ? '✏️ Edit' : '➕ Add' ?> Password Reset Attempt</h1>
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?= sanitize($error) ?></div>
            <?php endif; ?>
            <div class="card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= sanitize(itm_get_csrf_token()) ?>">

                    <?php if ($isEdit): ?>
                        <div class="form-group">
                            <label>User</label>
                            <input type="text" value="<?= sanitize($data['user_label']) ?>" readonly>
                            <small class="text-muted">User is shown as plain text and cannot be edited.</small>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= sanitize($data['email']) ?>" maxlength="120">
                    </div>

                    <div class="form-group">
                        <label>Attempt Type *</label>
                        <select name="attempt_type" required>
                            <option value="request" <?= $data['attempt_type'] === 'request' ? 'selected' : '' ?>>request</option>
                            <option value="reset" <?= $data['attempt_type'] === 'reset' ? 'selected' : '' ?>>reset</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>IP Address *</label>
                        <input type="text" name="ip_address" value="<?= sanitize($data['ip_address']) ?>" maxlength="45" required>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-primary" type="submit">💾 Save</button>
                        <a class="btn" href="index.php">🔙 Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
