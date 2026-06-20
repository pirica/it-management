<?php
require_once 'config/config.php';
require_once ROOT_PATH . 'includes/itm_vault_master_key.php';

// Auth check
if (!isset($_SESSION['employee_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit ;
}

$user_id = (int)$_SESSION['employee_id'];
$csrfToken = itm_get_csrf_token();

// Fetch current employee login profile
$stmt = mysqli_prepare($conn, 'SELECT * FROM employees WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$current_user) {
    die('User not found.');
}

$userConfigWelcomeMessage = 'Welcome, ' . htmlspecialchars($current_user['username']);

$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $current_password_verify = (string)($_POST['current_password_verify'] ?? '');
    
    // Verify current password
    if (password_verify($current_password_verify, $current_user['password'])) {
        $update_fields = [];
        $types = '';
        $params = [];

        // Email update
        $new_email = trim((string)($_POST['email'] ?? ''));
        $currentEmail = trim((string)($current_user['work_email'] ?? $current_user['personal_email'] ?? ''));
        if ($new_email !== '' && strcasecmp($new_email, $currentEmail) !== 0) {
            $update_fields[] = 'work_email = ?';
            $params[] = $new_email;
            $types .= 's';
        }

        // System Password update
        $new_password = (string)($_POST['new_password'] ?? '');
        if ($new_password !== '') {
            $confirm_password = (string)($_POST['confirm_password'] ?? '');
            if ($new_password === $confirm_password) {
                $update_fields[] = 'password = ?';
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                $types .= 's';
            } else {
                $message = 'Error: New passwords do not match.';
                $message_type = 'error';
            }
        }

        // VAULT MASTER KEY LOGIC
        $new_master_key = (string)($_POST['new_master_key'] ?? '');
        if ($new_master_key !== '' && $message === '') {
            $confirm_master_key = (string)($_POST['confirm_master_key'] ?? '');
            if ($new_master_key !== $confirm_master_key) {
                $message = 'Error: New Master Keys do not match.';
                $message_type = 'error';
            } else {
                $old_master_key_verify = (string)($_POST['old_master_key_verify'] ?? '');
                $is_first_time = empty($current_user['vault_key_hash']);
                
                $can_proceed = false;
                if ($is_first_time) {
                    $can_proceed = true;
                } else {
                    if (password_verify($old_master_key_verify, $current_user['vault_key_hash'])) {
                        $can_proceed = true;
                    } else {
                        $message = 'Error: Current Master Key is incorrect.';
                        $message_type = 'error';
                    }
                }
                
                if ($can_proceed) {
                    // If not first time, we need to re-encrypt existing entries
                    $transaction_started = false;
                    $re_encryption_failed = false;
                    $pending_vault_session_key = null;

                    if (!$is_first_time) {
                        $old_key_session = hash('sha256', $old_master_key_verify);
                        $new_key_session = hash('sha256', $new_master_key);

                        mysqli_begin_transaction($conn);
                        $transaction_started = true;

                        $reencrypt_result = itm_vault_reencrypt_password_entries($conn, $user_id, $old_key_session, $new_key_session);
                        if (empty($reencrypt_result['ok'])) {
                            $re_encryption_failed = true;
                            mysqli_rollback($conn);
                            $transaction_started = false;
                            $message = 'Error: ' . ($reencrypt_result['message'] !== '' ? $reencrypt_result['message'] : 'Failed to re-encrypt vault entries. Please try again.');
                            $message_type = 'error';
                        }
                    }

                    if (!$re_encryption_failed) {
                        $update_fields[] = 'vault_key_hash = ?';
                        $params[] = password_hash($new_master_key, PASSWORD_DEFAULT);
                        $types .= 's';

                        if (isset($_SESSION['vault_key'])) {
                            $pending_vault_session_key = hash('sha256', $new_master_key);
                        }
                    }
                }
            }
        }

        // Execute database update
        if ($message === '' && !empty($update_fields)) {
            $sql_update = 'UPDATE employees SET ' . implode(', ', $update_fields) . ' WHERE id = ?';
            $types .= 'i';
            $params[] = $user_id;

            $stmt_final = mysqli_prepare($conn, $sql_update);
            if ($stmt_final) {
                mysqli_stmt_bind_param($stmt_final, $types, ...$params);
                if (mysqli_stmt_execute($stmt_final)) {
                    if (isset($transaction_started) && $transaction_started) {
                        mysqli_commit($conn);
                    }
                    if (isset($pending_vault_session_key) && $pending_vault_session_key !== null && $pending_vault_session_key !== '') {
                        $_SESSION['vault_key'] = $pending_vault_session_key;
                    }
                    $message = 'Profile updated successfully!';
                    $message_type = 'success';
                    // Fetch updated data for display
                    $stmt_ref = mysqli_prepare($conn, 'SELECT * FROM employees WHERE id = ?');
                    mysqli_stmt_bind_param($stmt_ref, 'i', $user_id);
                    mysqli_stmt_execute($stmt_ref);
                    $current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ref));
                    mysqli_stmt_close($stmt_ref);
                } else {
                    if (isset($transaction_started) && $transaction_started) {
                        mysqli_rollback($conn);
                    }
                    $message = 'Database error. Please try again.';
                    $message_type = 'error';
                }
                mysqli_stmt_close($stmt_final);
            } else {
                if (isset($transaction_started) && $transaction_started) {
                    mysqli_rollback($conn);
                }
                $message = 'Database error. Please try again.';
                $message_type = 'error';
            }
        } elseif ($message === '' && empty($update_fields)) {
            $message = 'No changes were made.';
            $message_type = 'info';
        }
    } else {
        $message = 'Error: Current System Password incorrect. Changes not saved.';
        $message_type = 'error';
    }
}

$messageClass = ($message_type === 'success') ? 'crud_success' : (($message_type === 'error') ? 'crud_error' : '');
$messageStyle = ($message_type === 'info') ? 'background: #e8f1ff; border:1px solid #b6d4fe; color:#084298; padding:10px; border-radius:8px;' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            <div class="content">
                <h1>👤 User Settings</h1>
                <p><?php echo $userConfigWelcomeMessage; ?></p>

                <?php if ($message !== ''): ?>
                    <div class="<?php echo $messageClass; ?>" style="<?php echo $messageStyle; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="card" style="max-width: 760px;">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <div class="card">
                            <div class="card-header"><strong>Account Information</strong></div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars((string)($current_user['work_email'] ?? $current_user['personal_email'] ?? '')); ?>" required>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>System Security</strong></div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>New System Password</label>
                                    <input type="password" name="new_password">
                                    <div class="form-hint">Leave blank to keep current.</div>
                                </div>
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="confirm_password">
                                </div>
                            </div>
                        </div>

                        <div class="card" id="vault-security">
                            <div class="card-header"><strong>Vault Security (Passwords Module)</strong></div>
                            <?php if (!empty($current_user['vault_key_hash'])): ?>
                                <div class="form-group">
                                    <label>Current Master Key (required to change)</label>
                                    <input type="password" name="old_master_key_verify">
                                </div>
                            <?php endif; ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label><?php echo empty($current_user['vault_key_hash']) ? 'Set Master Key' : 'New Master Key'; ?></label>
                                    <input type="password" name="new_master_key">
                                    <div class="form-hint">Leave blank to keep current.</div>
                                </div>
                                <div class="form-group">
                                    <label>Confirm Master Key</label>
                                    <input type="password" name="confirm_master_key">
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>Confirm Changes</strong></div>
                            <div class="form-group">
                                <label>Current System Password (required to save any changes)</label>
                                <input type="password" name="current_password_verify" required>
                            </div>
                            <button class="btn btn-primary" type="submit" title="Save">💾</button>
                        </div>
                    </form>
                </div>
                
                <div style="margin-top: 20px;">
                    <a class="btn" href="dashboard.php">🔙 Dashboard</a>
                </div>
            </div>
        </div>
    </div>
    <script src="js/theme.js"></script>
</body>
</html>