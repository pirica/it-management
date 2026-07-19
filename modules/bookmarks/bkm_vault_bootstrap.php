<?php
/**
 * Vault unlock/lock handling for bookmarks (private URLs encrypted at rest).
 */

/**
 * @return array{configured:bool,unlocked:bool,error:string}
 */
function bkm_handle_vault_requests($conn, $user_id)
{
    $user_id = (int)$user_id;
    $error = '';

    $stmt = mysqli_prepare($conn, 'SELECT vault_key_hash FROM employees WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $userRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $configured = !empty($userRow['vault_key_hash']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_key'])) {
        if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }

        if (!$configured) {
            header('Location: ../../user-config.php#vault-security');
            exit;
        }

        if (password_verify((string)$_POST['master_key'], (string)$userRow['vault_key_hash'])) {
            $_SESSION['vault_key'] = hash('sha256', (string)$_POST['master_key']);
            $redirect = (string)($_POST['bkm_vault_redirect'] ?? 'index.php');
            if ($redirect === '' || strpos($redirect, '://') !== false || $redirect[0] === '/') {
                $redirect = 'index.php';
            }
            header('Location: ' . $redirect);
            exit;
        }

        $error = 'Incorrect Master Key.';
    }

    if (isset($_GET['action']) && (string)$_GET['action'] === 'lock') {
        unset($_SESSION['vault_key']);
        header('Location: index.php');
        exit;
    }

    return [
        'configured' => $configured,
        'unlocked' => !empty($_SESSION['vault_key']),
        'error' => $error,
    ];
}

function bkm_render_vault_lock_screen($csrfToken, array $vaultState, $redirectTarget = 'index.php')
{
    $hasVault = !empty($vaultState['configured']);
    $error = (string)($vaultState['error'] ?? '');
    ?>
    <div style="max-width: 400px; margin: 80px auto; text-align: center;" class="card">
        <div style="font-size: 48px; margin-bottom: 16px;">🔒</div>
        <h2>Vault Locked</h2>
        <p>Enter your master key to access private bookmark URLs.</p>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <input type="hidden" name="bkm_vault_redirect" value="<?php echo sanitize($redirectTarget); ?>">
            <div class="form-group">
                <input type="password" name="master_key" class="form-control" placeholder="Master Key" required autofocus style="text-align: center;">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Unlock Vault</button>
        </form>
        <div style="margin-top: 20px; border-top: 1px solid var(--border); padding-top: 15px; display: flex; flex-direction: column; gap: 8px;">
            <?php if (!$hasVault): ?>
                <a href="../../user-config.php#vault-security" class="btn btn-success btn-sm">Create Vault Key</a>
            <?php endif; ?>
            <a href="../../user-config.php#vault-security" class="btn btn-sm">Change Master Key</a>
        </div>
    </div>
    <?php
}
