<?php
/**
 * User Configuration Page
 * 
 * Allows users to update their email address and change their password.
 * Implements a strict "current password" verification for all changes.
 * Supports a "Read-Only" mode for users whose employee status is not 'Active'.
 */

session_start();
require 'config/config.php';

// Security: Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$user_id;
$csrfToken = itm_get_csrf_token();
// Read-only mode is active if the user doesn't have a corresponding 'Active' employee record
$isReadOnlyMode = !empty($_SESSION['read_only_user_config']);
$message = '';
$message_type = ''; // success, error, info

// 1. Fetch current user data to populate the form
$stmt_init = mysqli_prepare($conn, 'SELECT username, email FROM users WHERE id = ? LIMIT 1');
$current_user = ['username' => '', 'email' => ''];
if ($stmt_init) {
    mysqli_stmt_bind_param($stmt_init, 'i', $user_id);
    mysqli_stmt_execute($stmt_init);
    $res_init = mysqli_stmt_get_result($stmt_init);
    $current_user = mysqli_fetch_assoc($res_init) ?: ['username' => '', 'email' => ''];
    mysqli_stmt_close($stmt_init);
}

// Build a personalized quick link with no text decoration, matching dashboard language.
$userConfigDisplayName = trim((string)($current_user['username'] ?? ''));
$userConfigEmail = trim((string)($current_user['email'] ?? ''));
$userConfigWelcomeMessage = 'Welcome to DataCenter Plus';
if ($userConfigDisplayName !== '' && $userConfigEmail !== '') {
    $userConfigWelcomeMessage .= ', ' . $userConfigDisplayName . ' (' . $userConfigEmail . ')';
} elseif ($userConfigDisplayName !== '') {
    $userConfigWelcomeMessage .= ', ' . $userConfigDisplayName;
} elseif ($userConfigEmail !== '') {
    $userConfigWelcomeMessage .= ' - (' . $userConfigEmail . ')';
}

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isReadOnlyMode) {
    itm_require_post_csrf();
    $new_email = trim((string)($_POST['email'] ?? ''));
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');
    $current_password_input = (string)($_POST['current_password_verify'] ?? '');

    // --- VERIFICATION STEP 1: Authenticate with Current Password ---
    // Every change requires the user to re-verify their identity
    $stmt_auth = mysqli_prepare($conn, 'SELECT password FROM users WHERE id = ? LIMIT 1');
    $user_data = null;
    if ($stmt_auth) {
        mysqli_stmt_bind_param($stmt_auth, 'i', $user_id);
        mysqli_stmt_execute($stmt_auth);
        $res_auth = mysqli_stmt_get_result($stmt_auth);
        $user_data = mysqli_fetch_assoc($res_auth);
        mysqli_stmt_close($stmt_auth);
    }

    if ($user_data && password_verify($current_password_input, (string)$user_data['password'])) {
        $update_fields = [];
        $types = '';
        $params = [];

        // --- VERIFICATION STEP 2: Process Email Change ---
        if ($new_email !== '' && $new_email !== (string)$current_user['email']) {
            // Ensure the new email is unique across all users
            $stmt_check = mysqli_prepare($conn, 'SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            if ($stmt_check) {
                mysqli_stmt_bind_param($stmt_check, 'si', $new_email, $user_id);
                mysqli_stmt_execute($stmt_check);
                mysqli_stmt_store_result($stmt_check);

                if (mysqli_stmt_num_rows($stmt_check) > 0) {
                    $message = 'Error: This email is already registered to another account.';
                    $message_type = 'error';
                } else {
                    $update_fields[] = 'email = ?';
                    $params[] = $new_email;
                    $types .= 's';
                }
                mysqli_stmt_close($stmt_check);
            }
        }

        // --- VERIFICATION STEP 3: Process Password Change ---
        if ($new_password !== '') {
            if ($new_password === $confirm_password) {
                // Securely hash the new password
                $update_fields[] = 'password = ?';
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                $types .= 's';
            } else {
                $message = 'Error: New passwords do not match.';
                $message_type = 'error';
            }
        }

        // --- FINAL STEP: Execute database update if there are changes ---
        if ($message === '' && !empty($update_fields)) {
            $sql_update = 'UPDATE users SET ' . implode(', ', $update_fields) . ' WHERE id = ?';
            $types .= 'i';
            $params[] = $user_id;

            $stmt_final = mysqli_prepare($conn, $sql_update);
            if ($stmt_final) {
                mysqli_stmt_bind_param($stmt_final, $types, ...$params);

                if (mysqli_stmt_execute($stmt_final)) {
                    $message = 'Profile updated successfully!';
                    $message_type = 'success';
                    if ($new_email !== '') {
                        $current_user['email'] = $new_email;
                    }
                } else {
                    $message = 'Database error. Please try again.';
                    $message_type = 'error';
                }
                mysqli_stmt_close($stmt_final);
            } else {
                $message = 'Database error. Please try again.';
                $message_type = 'error';
            }
        } elseif ($message === '' && empty($update_fields)) {
            $message = 'No changes were made.';
            $message_type = 'info';
        }
    } else {
        $message = 'Error: Current password incorrect. Changes not saved.';
        $message_type = 'error';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $isReadOnlyMode) {
    // Prevent POST processing if user is restricted to read-only
    $message = 'Read-only mode is enabled. Account changes are not available.';
    $message_type = 'info';
}

// Set CSS classes for user feedback messages
$messageClass = '';
if ($message_type === 'success') {
    $messageClass = 'crud_success';
} elseif ($message_type === 'error') {
    $messageClass = 'crud_error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Settings - <?php echo sanitize($app_name ?? itm_ui_config_app_name()); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <?php if ($isReadOnlyMode): ?>
    <style>
        /* Specific styling for the read-only restriction view */
        .readonly-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: var(--bg);
        }
        .readonly-card {
            width: 100%;
            max-width: 560px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
        }
        .readonly-actions {
            margin-top: 16px;
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
    <?php endif; ?>
</head>
<body>
    <?php if ($isReadOnlyMode): ?>
    <!-- Restriction Notice for Inactive Employee Accounts -->
    <div class="readonly-wrap">
        <div class="readonly-card">
            <h1>👤 User Configuration</h1>
            <p style="margin-top:10px;">
                <a href="dashboard.php" style="text-decoration:none; color:inherit;"><?php echo sanitize($userConfigWelcomeMessage); ?></a>
            </p>
            <p style="margin-top:10px; color: var(--text-secondary);">
                Read-Only mode: your login email does not match any active employee with <strong>Active</strong> employment status.
            </p>
            <?php if ($message !== ''): ?>
                <div class="<?php echo $messageClass !== '' ? $messageClass : ''; ?>" style="<?php echo $message_type === 'info' ? 'background: #e8f1ff; border:1px solid #b6d4fe; color:#084298; padding:10px; border-radius:8px; margin-top:16px;' : ''; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <div class="readonly-actions">
                <button type="button" onclick="toggleTheme()" class="btn btn-sm" title="Toggle Dark/Light Mode">🌙 Dark / White</button>
                <form method="POST" action="logout.php" style="display:inline; margin:0;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <button type="submit" class="btn btn-sm">🚪 Logout</button>
                </form>
            </div>
        </div>
    </div>
    <script src="js/theme.js"></script>
    <?php else: ?>
    <!-- Full User Settings Form for Active Accounts -->
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/header.php'; ?>

            <div class="content">
                <h1>👤 User Settings</h1>
                <p style="margin: 0 0 10px 0;">
                    <a href="dashboard.php" style="text-decoration:none; color:inherit;"><?php echo sanitize($userConfigWelcomeMessage); ?></a>
                </p>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">Manage your email and password.</p>

                <?php if ($message !== ''): ?>
                    <div class="<?php echo $messageClass !== '' ? $messageClass : ''; ?>" style="<?php echo $message_type === 'info' ? 'background: #e8f1ff; border:1px solid #b6d4fe; color:#084298; padding:10px; border-radius:8px;' : ''; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div class="card" style="max-width: 720px;">
                    <div class="card-header">
                        <h2>Account Configuration (ID: <?php echo (int)$user_id; ?>)</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <fieldset>
                                <legend>Account Information</legend>
                                <p>
                                    <label for="email">Email Address:</label><br>
                                    <input id="email" type="email" name="email" value="<?php echo htmlspecialchars((string)$current_user['email']); ?>" required style="width:100%; max-width:460px;">
                                </p>
                            </fieldset>

                            <fieldset>
                                <legend>Security (Leave blank to keep current password)</legend>
                                <p>
                                    <label for="new_password">New Password:</label><br>
                                    <input id="new_password" type="password" name="new_password" style="width:100%; max-width:460px;">
                                </p>
                                <p>
                                    <label for="confirm_password">Confirm New Password:</label><br>
                                    <input id="confirm_password" type="password" name="confirm_password" style="width:100%; max-width:460px;">
                                </p>
                            </fieldset>

                            <fieldset style="background-color: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; padding: 12px;">
                                <legend>Confirm Changes</legend>
                                <p>
                                    <label for="current_password_verify"><strong>Current Password (Required to save any changes):</strong></label><br>
                                    <input id="current_password_verify" type="password" name="current_password_verify" required style="width:100%; max-width:460px;">
                                </p>
                                <button class="btn btn-primary" type="submit">💾</button>
                            </fieldset>
                        </form>
                    </div>
                </div>

                <p style="margin-top: 20px;">
                    <a class="btn" href="dashboard.php">🔙 Dashboard</a>
                    <form method="POST" action="logout.php" style="display:inline; margin:0;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <button type="submit" class="btn">Logout</button>
                    </form>
                </p>
            </div>
        </div>
    </div>

    <script src="js/theme.js"></script>
    <script src="js/script.js"></script>
    <?php endif; ?>
</body>
</html>
