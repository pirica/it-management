<?php
/**
 * Unified vault unlock/lock handling for vault-gated modules.
 */

if (!function_exists('itm_vault_fetch_employee_security_row')) {
  /**
   * @return array<string,mixed>|null
   */
  function itm_vault_fetch_employee_security_row($conn, $user_id)
  {
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
      return null;
    }

    $stmt = mysqli_prepare(
      $conn,
      'SELECT vault_key_hash, totp_secret, totp_enabled FROM employees WHERE id = ? LIMIT 1'
    );
    if (!$stmt) {
      return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return is_array($row) ? $row : null;
  }
}

if (!function_exists('itm_vault_is_configured')) {
  function itm_vault_is_configured(array $employeeRow)
  {
    return !empty($employeeRow['vault_key_hash']);
  }
}

if (!function_exists('itm_vault_try_unlock_from_post')) {
  /**
   * @return array{ok:bool,error:string}
   */
  function itm_vault_try_unlock_from_post(array $employeeRow, array $post)
  {
    if (!itm_vault_is_configured($employeeRow)) {
      return ['ok' => false, 'error' => ''];
    }

    $masterKey = (string)($post['master_key'] ?? '');
    if ($masterKey === '') {
      return ['ok' => false, 'error' => 'Master key is required.'];
    }

    if (!password_verify($masterKey, (string)$employeeRow['vault_key_hash'])) {
      return ['ok' => false, 'error' => 'Incorrect Master Key.'];
    }

    $totpCheck = itm_totp_require_valid_code_or_error($employeeRow, $post['totp_code'] ?? '');
    if (!$totpCheck['ok']) {
      return ['ok' => false, 'error' => $totpCheck['error']];
    }

    $_SESSION['vault_key'] = hash('sha256', $masterKey);

    return ['ok' => true, 'error' => ''];
  }
}

if (!function_exists('itm_vault_handle_unlock_requests')) {
  /**
   * @return array{configured:bool,unlocked:bool,error:string,totp_required:bool}
   */
  function itm_vault_handle_unlock_requests($conn, $user_id, $redirectFieldName, $defaultRedirect = 'index.php', $lockRedirect = 'index.php')
  {
    $user_id = (int)$user_id;
    $error = '';
    $employeeRow = itm_vault_fetch_employee_security_row($conn, $user_id);
    if (!is_array($employeeRow)) {
      $employeeRow = ['vault_key_hash' => null, 'totp_secret' => null, 'totp_enabled' => 0];
    }

    $configured = itm_vault_is_configured($employeeRow);
    $totpRequired = itm_totp_employee_has_enabled($employeeRow);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['master_key'])) {
      if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
      }

      if (!$configured) {
        header('Location: ../../user-config.php#vault-security');
        exit;
      }

      $unlock = itm_vault_try_unlock_from_post($employeeRow, $_POST);
      if ($unlock['ok']) {
        $redirect = (string)($_POST[$redirectFieldName] ?? $defaultRedirect);
        if ($redirect === '' || strpos($redirect, '://') !== false || $redirect[0] === '/') {
          $redirect = $defaultRedirect;
        }
        header('Location: ' . $redirect);
        exit;
      }

      $error = $unlock['error'] !== '' ? $unlock['error'] : 'Incorrect Master Key.';
    }

    if (isset($_GET['action']) && (string)$_GET['action'] === 'lock') {
      unset($_SESSION['vault_key']);
      header('Location: ' . $lockRedirect);
      exit;
    }

    return [
      'configured' => $configured,
      'unlocked' => !empty($_SESSION['vault_key']),
      'error' => $error,
      'totp_required' => $totpRequired,
    ];
  }
}

if (!function_exists('itm_vault_render_lock_screen')) {
  function itm_vault_render_lock_screen(
    $csrfToken,
    array $vaultState,
    $description,
    $redirectFieldName,
    $redirectTarget = 'index.php',
    array $footerLinks = null
  ) {
    $hasVault = !empty($vaultState['configured']);
    $error = (string)($vaultState['error'] ?? '');
    $totpRequired = !empty($vaultState['totp_required']);
    ?>
    <div style="max-width: 400px; margin: 80px auto; text-align: center;" class="card">
        <div style="font-size: 48px; margin-bottom: 16px;">🔒</div>
        <h2>Vault Locked</h2>
        <p><?php echo sanitize((string)$description); ?></p>
        <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <input type="hidden" name="<?php echo sanitize($redirectFieldName); ?>" value="<?php echo sanitize($redirectTarget); ?>">
            <div class="form-group">
                <input type="password" name="master_key" class="form-control" placeholder="Master Key" required autofocus style="text-align: center;">
            </div>
            <?php if ($totpRequired): ?>
            <div class="form-group">
                <input type="text" name="totp_code" class="form-control" placeholder="6-digit authenticator code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" style="text-align: center;">
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary" style="width: 100%;" title="Unlock vault">Unlock Vault</button>
        </form>
        <div style="margin-top: 20px; border-top: 1px solid var(--border); padding-top: 15px; display: flex; flex-direction: column; gap: 8px;">
            <?php if (!$hasVault): ?>
                <a href="../../user-config.php#vault-security" class="btn btn-success btn-sm" title="Create vault key">Create Vault Key</a>
            <?php endif; ?>
            <?php if (is_array($footerLinks)): ?>
                <?php foreach ($footerLinks as $link): ?>
                    <a href="<?php echo sanitize((string)($link['href'] ?? '#')); ?>" class="btn btn-sm" title="<?php echo sanitize((string)($link['title'] ?? '')); ?>"><?php echo sanitize((string)($link['label'] ?? '')); ?></a>
                <?php endforeach; ?>
            <?php else: ?>
                <a href="../../user-config.php#vault-security" class="btn btn-sm" title="Change master key">Change Master Key</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
  }
}
