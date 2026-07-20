<?php
/**
 * Passwords Module - View
 *
 * Read-only password entry detail (employee-scoped, vault-unlocked).
 */

require_once '../../config/config.php';

if (!isset($_SESSION['employee_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = (int)$_SESSION['employee_id'];
$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$id = (int)($_GET['id'] ?? 0);
$error = '';
$data = null;
$folderLabel = '—';

$user_stmt = mysqli_prepare($conn, 'SELECT vault_key_hash FROM employees WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
mysqli_stmt_execute($user_stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));
mysqli_stmt_close($user_stmt);
$has_vault_configured = !empty($user_data['vault_key_hash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_key'])) {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    if (!$has_vault_configured) {
        header('Location: ../../user-config.php#vault-security');
        exit;
    }
    if (password_verify((string)$_POST['master_key'], (string)$user_data['vault_key_hash'])) {
        $_SESSION['vault_key'] = hash('sha256', (string)$_POST['master_key']);
        header('Location: view.php?id=' . $id);
        exit;
    }
    $error = 'Incorrect Master Key.';
}

$vaultUnlocked = !empty($_SESSION['vault_key']);

if ($id > 0 && $vaultUnlocked) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM password_entries WHERE id = ? AND employee_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $user_id);
        mysqli_stmt_execute($stmt);
        $data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
    if (is_array($data)) {
        $data['password_plain'] = itm_decrypt((string)($data['password'] ?? ''), (string)$_SESSION['vault_key']);
        if (!empty($data['folder_id'])) {
            $folderStmt = mysqli_prepare($conn, 'SELECT name FROM password_folders WHERE id = ? AND employee_id = ? LIMIT 1');
            if ($folderStmt) {
                $folderId = (int)$data['folder_id'];
                mysqli_stmt_bind_param($folderStmt, 'ii', $folderId, $user_id);
                mysqli_stmt_execute($folderStmt);
                $folderRow = mysqli_fetch_assoc(mysqli_stmt_get_result($folderStmt));
                mysqli_stmt_close($folderStmt);
                if (is_array($folderRow) && trim((string)($folderRow['name'] ?? '')) !== '') {
                    $folderLabel = (string)$folderRow['name'];
                }
            }
        }
    } elseif ($error === '') {
        $error = 'Password entry not found.';
    }
} elseif ($id > 0 && !$vaultUnlocked && $error === '') {
    $error = '';
}

$crud_title = 'View Password';
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .pwd-inline-field {
            display: inline-flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 8px;
            max-width: 100%;
        }
        .pwd-inline-field input.form-control {
            flex: 1 1 auto;
            min-width: 120px;
            max-width: 280px;
        }
        .pwd-inline-field .btn { flex-shrink: 0; white-space: nowrap; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="View password entry">🔎</h1>
            <div class="card" style="max-width: 900px;">
                <?php if (!$vaultUnlocked): ?>
                    <div style="max-width: 400px; margin: 40px auto; text-align: center;">
                        <div style="font-size: 48px; margin-bottom: 16px;">🔒</div>
                        <h2>Vault Locked</h2>
                        <p>Enter your master key to view this password entry.</p>
                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <div class="form-group">
                                <input type="password" name="master_key" class="form-control" placeholder="Master Key" required autofocus style="text-align: center;">
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Unlock Vault</button>
                        </form>
                        <div style="margin-top: 20px;">
                            <a href="index.php" class="btn btn-sm" title="Back">🔙</a>
                        </div>
                    </div>
                <?php elseif (!is_array($data)): ?>
                    <?php echo itm_render_alert_errors($error !== '' ? [$error] : ['Password entry not found.']); ?>
                    <div style="margin-top: 16px;"><a href="index.php" class="btn" title="Back">🔙</a></div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">Account</th><td><?php echo sanitize((string)($data['account'] ?? '')); ?></td></tr>
                        <tr><th>Login Name</th><td><?php echo sanitize((string)($data['login_name'] ?? '')); ?></td></tr>
                        <tr><th>Password</th><td>
                            <div class="pwd-inline-field">
                                <input type="password" id="view-password" class="form-control" value="<?php echo sanitize((string)($data['password_plain'] ?? '')); ?>" readonly>
                                <button type="button" class="btn btn-sm" onclick="togglePasswordVisibility('view-password')" title="Toggle visibility">👁️</button>
                                <button type="button" class="btn btn-sm" onclick="copyViewPassword()" title="Copy">🗐</button>
                            </div>
                        </td></tr>
                        <tr><th>Website</th><td>
                            <?php if (!empty($data['website'])): ?>
                                <a href="<?php echo sanitize((string)$data['website']); ?>" target="_blank" rel="nofollow noreferrer noopener"><?php echo sanitize((string)$data['website']); ?></a>
                            <?php else: ?>—<?php endif; ?>
                        </td></tr>
                        <tr><th>Folder</th><td><?php echo sanitize($folderLabel); ?></td></tr>
                        <tr><th>Comments</th><td><?php echo nl2br(sanitize((string)($data['comments'] ?? ''))); ?></td></tr>
                        <tr><th>Active</th><td><?php echo ((int)($data['active'] ?? 0) === 1) ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td></tr>
                        <?php if (function_exists('itm_crud_render_view_audit_meta_rows')): ?>
                            <?php itm_crud_render_view_audit_meta_rows($conn, $company_id, $data); ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:20px;align-items:center;">
                        <a href="index.php" class="btn" title="Back">🔙</a>
                        <button type="button" class="btn btn-sm" onclick="itmOpenQrShareModal('ajax_handler.php', <?php echo (int)$data['id']; ?>, { action: 'create_share_session' })" title="Share to device">📱</button>
                        <button type="button" class="btn btn-sm" onclick="itmOpenWhatsAppShare('ajax_handler.php', <?php echo (int)$data['id']; ?>, { action: 'create_share_session' }, 'password')" title="Share on WhatsApp"><img src="../../images/whatsapp.svg" alt="" width="16" height="16" style="display:block;"></button>
                        <button type="button" class="btn btn-sm" onclick="itmOpenOutlookShare('ajax_handler.php', <?php echo (int)$data['id']; ?>, { action: 'create_share_session' }, 'password')" title="Share on Outlook">📨</button>
                        <a href="index.php?edit_entry=<?php echo (int)$data['id']; ?>" class="btn btn-sm btn-primary" title="Edit">✏️</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
function togglePasswordVisibility(id) {
    const el = document.getElementById(id);
    if (el) { el.type = el.type === 'password' ? 'text' : 'password'; }
}
function copyViewPassword() {
    const el = document.getElementById('view-password');
    if (!el) return;
    el.type = 'text';
    el.select();
    document.execCommand('copy');
    el.type = 'password';
    alert('Copied!');
}
</script>
<?php require_once ROOT_PATH . 'includes/itm_qr_share_modal.php'; ?>
<script>window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;</script>
<script src="../../js/theme.js"></script>
</body>
</html>
