<?php
require_once 'config/config.php';
require_once ROOT_PATH . 'includes/itm_vault_master_key.php';
require_once ROOT_PATH . 'includes/employee_profile_photo.php';

/*
SELECT COUNT(assigned_to_employee_id) FROM `events` WHERE `assigned_to_employee_id` = 1 AND `company_id` = 1;
*/
/**
 * user-config.php - Employee Profile & Preferences
 *
 * Scoped to the logged-in employee.
 * Personal stat cards live on dashboard.php.
 * Two-column layout:
 *   - Left: Profile Summary, Progress, Org Chart Path.
 *   - Right: Profile Editing, Security, Sidebar Prefs, Activity, etc.
 */

// Auth check
if (!isset($_SESSION['employee_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$user_id = (int)$_SESSION['employee_id'];
$company_id = (int)($_SESSION['company_id'] ?? 0);
$csrfToken = itm_get_csrf_token();

/**
 * Why: employees.theme is light|dark only; anything else falls back to light.
 */
$profile_normalize_theme = static function ($theme) {
    return (strtolower(trim((string)$theme)) === 'dark') ? 'dark' : 'light';
};

// --- 1. GATHER ALL REQUIRED DATA ---

// Fetch current employee profile data
$stmt = mysqli_prepare($conn, '
    SELECT e.*, ep.name AS position_name, d.name AS department_name, es.name AS status_name
    FROM employees e
    LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN employee_statuses es ON e.employment_status_id = es.id
    WHERE e.id = ?
');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$current_user) {
    die('User not found.');
}

// Why: profile self-updates must use the employee home company_id — session
// company_id is the active tenant switcher and often differs for multi-company admins.
$home_company_id = (int)($current_user['company_id'] ?? 0);

// Ensure company_id is set from user record if missing in session
if ($company_id <= 0) {
    $company_id = $home_company_id;
    $_SESSION['company_id'] = $company_id;
}


//echo "User: $user_id<br>";
//echo "Comp $company_id";






//var_dump($user_id, $company_id);

// Optimized by Bolt ⚡: Avoid redundant database queries by fetching properties directly from the existing $current_user array.
$workstation_mode_id = (int)($current_user['workstation_mode_id'] ?? 0);
$assignment_type_id = (int)($current_user['assignment_type_id'] ?? 0);

if ($workstation_mode_id > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT mode_name
        FROM workstation_modes
        WHERE id = ?
        AND company_id = ?
    ");

    mysqli_stmt_bind_param($stmt, "ii", $workstation_mode_id, $company_id);
    mysqli_stmt_execute($stmt);

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $mode_name = $row['mode_name'] ?? '';

    mysqli_stmt_close($stmt);

$_SESSION['mode_name'] = $mode_name;
//echo $_SESSION['mode_name'];
}

if ($assignment_type_id > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT name
        FROM assignment_types
        WHERE id = ?
        AND company_id = ?
    ");

    mysqli_stmt_bind_param($stmt, "ii", $assignment_type_id, $company_id);
    mysqli_stmt_execute($stmt);

    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $assignment_types = $row['name'] ?? '';

    mysqli_stmt_close($stmt);

$_SESSION['assignment_types'] = $assignment_types;
//echo $_SESSION['assignment_types'];
}

// Why: Profile System Access Overview still needs the employee_system_access row (dashboard owns auto-insert).
$system_access_overview = [];
$systemAccessColumns = [];
$systemAccessRes = mysqli_query($conn, 'DESCRIBE employee_system_access');
if ($systemAccessRes) {
    while ($systemAccessRow = mysqli_fetch_assoc($systemAccessRes)) {
        $systemAccessColumns[] = $systemAccessRow['Field'];
    }
}
$systemAccessFixed = [
    'id', 'company_id', 'employee_id', 'active',
    'changed_at', 'created_at', 'updated_at', 'deleted_at',
    'created_by', 'updated_by', 'deleted_by',
];
$systemAccessSelectList = implode(', ', array_map(static function ($c) {
    return '`' . str_replace('`', '``', (string)$c) . '`';
}, $systemAccessColumns));
if ($systemAccessSelectList !== '') {
    $systemAccessStmt = mysqli_prepare(
        $conn,
        'SELECT ' . $systemAccessSelectList . ' FROM employee_system_access WHERE employee_id = ? AND company_id = ?'
    );
    if ($systemAccessStmt) {
        mysqli_stmt_bind_param($systemAccessStmt, 'ii', $user_id, $company_id);
        if (mysqli_stmt_execute($systemAccessStmt)) {
            $systemAccessResult = mysqli_stmt_get_result($systemAccessStmt);
            while ($systemAccessResult && ($systemAccessDataRow = mysqli_fetch_assoc($systemAccessResult))) {
                $system_access_overview[] = $systemAccessDataRow;
            }
        }
        mysqli_stmt_close($systemAccessStmt);
    }
}


$sql = "
    SELECT
        e.*,
        et.name AS type_name,
        m.name AS manufacturer_name,
        ls.name AS status_name
    FROM equipment e
    JOIN equipment_types et ON e.equipment_type_id = et.id
    LEFT JOIN manufacturers m ON e.manufacturer_id = m.id
    LEFT JOIN equipment_statuses ls ON e.status_id = ls.id
    WHERE e.assigned_to_employee_id = ?
      AND e.company_id = ?
      AND et.name = 'Workstation'
    LIMIT 1
";



$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "<span style='color:red'>PREPARE FAILED: " . mysqli_error($conn) . "</span><br>";
}


if (!mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id)) {
    echo "<span style='color:red'>BIND FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

if (!mysqli_stmt_execute($stmt)) {
    echo "<span style='color:red'>EXECUTE FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}



$meta = mysqli_stmt_result_metadata($stmt);
$bindVars = [];
$row = [];



while ($field = mysqli_fetch_field($meta)) {

    $bindVars[] = &$row[$field->name];
}

call_user_func_array([$stmt, 'bind_result'], $bindVars);

$workstation = null;

if (mysqli_stmt_fetch($stmt)) {
    $workstation = $row;
}

mysqli_stmt_close($stmt);




// Direct Reports
$stmt = mysqli_prepare($conn, "
    SELECT e.id, e.display_name, e.first_name, e.last_name, e.photo, ep.name as position
    FROM employees e
    LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id
    WHERE e.reports_to = ? AND e.company_id = ? AND e.employment_status_id IN (SELECT id FROM employee_statuses WHERE name = 'Active' AND company_id = ?)
");
mysqli_stmt_bind_param($stmt, 'iii', $user_id, $company_id, $company_id);
mysqli_stmt_execute($stmt);
$direct_reports = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Reporting Chain
$reporting_chain = [];
$curr_id = $user_id;
while ($curr_id) {
    $stmt = mysqli_prepare($conn, "SELECT id, display_name, first_name, last_name, photo, reports_to, employee_position_id FROM employees WHERE id = ? AND on_orgchart = 1 AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $curr_id, $company_id);
    mysqli_stmt_execute($stmt);
    $member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($member) {
        $reporting_chain[] = $member;
        $curr_id = $member['reports_to'];
        if ($curr_id == $member['id']) break;
    } else break;
}



$sql = "
    SELECT
        'audit' AS type,
        table_name,
        action,
        created_at
    FROM audit_logs
    WHERE employee_id = ?
      AND company_id = ?
    ORDER BY created_at DESC
    LIMIT 10
";



$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "<span style='color:red'>PREPARE FAILED: " . mysqli_error($conn) . "</span><br>";
}



if (!mysqli_stmt_bind_param($stmt, 'ii', $user_id, $company_id)) {
    echo "<span style='color:red'>BIND FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

if (!mysqli_stmt_execute($stmt)) {
    echo "<span style='color:red'>EXECUTE FAILED: " . mysqli_stmt_error($stmt) . "</span><br>";
}

/* BIND DINÂMICO DOS RESULTADOS */
$meta = mysqli_stmt_result_metadata($stmt);
$bindVars = [];
$row = [];



while ($field = mysqli_fetch_field($meta)) {
    $bindVars[] = &$row[$field->name];
}

call_user_func_array([$stmt, 'bind_result'], $bindVars);

$activity_list = [];

while (mysqli_stmt_fetch($stmt)) {
    $activity_list[] = $row;
}

mysqli_stmt_close($stmt);




$stmt = mysqli_prepare($conn, "SELECT 'login' as type, attempt_source, attempt_type, created_at FROM attempts WHERE employee_id = ? ORDER BY created_at DESC LIMIT 10");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $r) $activity_list[] = $r;
mysqli_stmt_close($stmt);

usort($activity_list, function($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
$activity_list = array_slice($activity_list, 0, 10);

// --- 2. HANDLE POST UPDATES ---
$message = '';
$message_type = 'info';
$message_action = '';
// Why: after a successful vault key save, show the plaintext key once in the overlay (same request only — never stored server-side).
$vault_key_otd_plaintext = '';
// Why: after a successful theme save, sync employees.theme into localStorage for js/theme.js.
$syncThemeToClient = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $theme = $profile_normalize_theme($_POST['theme'] ?? 'light');
        $ec_name = trim($_POST['emergency_contact_name'] ?? '');
        $ec_rel = trim($_POST['emergency_contact_relationship'] ?? '');
        $ec_phone = trim($_POST['emergency_contact_phone'] ?? '');
        // Why: accept type=date (Y-m-d) or dd/mm/yyyy; empty clears birthday.
        $birthday = itm_parse_date_input($_POST['birthday'] ?? '');
        $hide_year = !empty($_POST['hide_year']) ? 1 : 0;

        $sql = 'UPDATE employees SET work_email = ?, mobile_phone = ?, theme = ?, emergency_contact_name = ?, emergency_contact_relationship = ?, emergency_contact_phone = ?, birthday = ?, hide_year = ? WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $message = 'Error preparing profile update.';
            $message_type = 'error';
        } else {
            mysqli_stmt_bind_param(
                $stmt,
                'sssssssiii',
                $email,
                $phone,
                $theme,
                $ec_name,
                $ec_rel,
                $ec_phone,
                $birthday,
                $hide_year,
                $user_id,
                $home_company_id
            );
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Profile updated successfully!';
                $message_type = 'success';
                $syncThemeToClient = true;
                $_SESSION['ui_theme'] = $theme;
                $stmt_refresh = mysqli_prepare($conn, 'SELECT * FROM employees WHERE id = ? AND company_id = ?');
                if ($stmt_refresh) {
                    mysqli_stmt_bind_param($stmt_refresh, 'ii', $user_id, $home_company_id);
                    mysqli_stmt_execute($stmt_refresh);
                    $updated_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_refresh));
                    mysqli_stmt_close($stmt_refresh);
                    if (is_array($updated_user)) {
                        itm_log_audit($conn, 'employees', $user_id, 'UPDATE', $current_user, $updated_user);
                    }
                }
            } else {
                $message = 'Error updating profile.';
                $message_type = 'error';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action === 'change_password') {
        $curr_pw = $_POST['current_password'] ?? '';
        $new_pw = $_POST['new_password'] ?? '';
        $confirm_pw = $_POST['confirm_password'] ?? '';
        if (password_verify($curr_pw, $current_user['password'])) {
            if ($new_pw === $confirm_pw) {
                $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, "UPDATE employees SET password = ? WHERE id = ? AND company_id = ?");
                mysqli_stmt_bind_param($stmt, 'sii', $hash, $user_id, $home_company_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                itm_log_audit($conn, 'employees', $user_id, 'UPDATE', ['action'=>'password_change'], ['action'=>'password_change_success']);
                $message = 'Password updated!';
                $message_type = 'success';
            } else { $message = 'Passwords do not match.'; $message_type = 'error'; }
        } else { $message = 'Current password incorrect.'; $message_type = 'error'; }
    } elseif ($action === 'vault_key_change') {
        $message_action = 'vault_key_change';
        $curr_pw = $_POST['current_password'] ?? '';
        if (password_verify($curr_pw, $current_user['password'])) {
            $totpGate = itm_totp_require_valid_code_or_error($current_user, $_POST['totp_code'] ?? '');
            if (!$totpGate['ok']) {
                $message = $totpGate['error'];
                $message_type = 'error';
            } else {
            $new_vk = $_POST['new_master_key'] ?? '';
            $confirm_vk = $_POST['confirm_master_key'] ?? '';
            if ($new_vk === $confirm_vk) {
                $old_vk_verify = $_POST['old_master_key_verify'] ?? '';
                $is_first = empty($current_user['vault_key_hash']);
                if ($is_first || password_verify($old_vk_verify, $current_user['vault_key_hash'])) {
                    mysqli_begin_transaction($conn);
                    try {
                        if (!$is_first) {
                            $res = itm_vault_reencrypt_password_entries($conn, $user_id, hash('sha256', $old_vk_verify), hash('sha256', $new_vk));
                            if (!$res['ok']) throw new Exception($res['message']);
                            $resBkm = itm_vault_reencrypt_bookmark_urls($conn, $user_id, hash('sha256', $old_vk_verify), hash('sha256', $new_vk));
                            if (!$resBkm['ok']) throw new Exception($resBkm['message']);
                            $resNotes = itm_vault_reencrypt_notes($conn, $user_id, hash('sha256', $old_vk_verify), hash('sha256', $new_vk));
                            if (!$resNotes['ok']) throw new Exception($resNotes['message']);
                            $resEvents = itm_vault_reencrypt_events($conn, $user_id, hash('sha256', $old_vk_verify), hash('sha256', $new_vk));
                            $resPrivateContacts = itm_vault_reencrypt_private_contacts($conn, $user_id, hash('sha256', $old_vk_verify), hash('sha256', $new_vk));
                            $resTodo = itm_vault_reencrypt_todo($conn, $user_id, hash('sha256', $old_vk_verify), hash('sha256', $new_vk));
                            if (!$resEvents['ok']) throw new Exception($resEvents['message']);
                            if (!$resPrivateContacts['ok']) throw new Exception($resPrivateContacts['message']);
                            if (!$resTodo['ok']) throw new Exception($resTodo['message']);
                        }
                        $vk_hash = password_hash($new_vk, PASSWORD_DEFAULT);
                        $stmt = mysqli_prepare($conn, "UPDATE employees SET vault_key_hash = ? WHERE id = ? AND company_id = ?");
                        mysqli_stmt_bind_param($stmt, 'sii', $vk_hash, $user_id, $home_company_id);
                        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
                        mysqli_commit($conn);
                        itm_log_audit($conn, 'employees', $user_id, 'UPDATE', ['action'=>'vault_key_change'], ['action'=>'vault_key_change_success']);
                        if (isset($_SESSION['vault_key'])) $_SESSION['vault_key'] = hash('sha256', $new_vk);

                        // Send informational notification email to employee (no plaintext secrets in transit)
                        $emailTarget = !empty($current_user['work_email']) ? $current_user['work_email'] : ($current_user['personal_email'] ?? '');
                        if ($emailTarget !== '' && filter_var($emailTarget, FILTER_VALIDATE_EMAIL)) {
                            $is_create = $is_first;
                            $emailSubject = $is_create ? 'Vault Key Created' : 'Vault Key Updated';
                            $emailSubtitle = $is_create ? 'Your Vault Key is ready' : 'Your Vault Key has been changed';
                            $emailText = $is_create
                                ? '<p>Your private Vault Key has been successfully created.</p><p>You can now securely store your passwords, notes, private bookmarks, and personal contacts.</p>'
                                : '<p>Your private Vault Key has been successfully updated.</p><p>Your private data has been automatically re-encrypted with your new key.</p>';

                            require_once ROOT_PATH . 'includes/itm_email.php';
                            itm_send_email($emailTarget, $emailSubject, $emailText, $home_company_id, [
                                'email_template' => [
                                    'subtitle' => $emailSubtitle,
                                    'button_text' => 'Go to Dashboard',
                                    'button_url' => BASE_URL . 'dashboard.php',
                                ],
                            ]);
                        }

                        $vault_key_otd_plaintext = $new_vk;
                        $message = 'Vault key saved successfully.';
                        $message_type = 'success';
                        $message_action = 'vault_key_change';
                    } catch (Exception $e) { mysqli_rollback($conn); $message = $e->getMessage(); $message_type = 'error'; }
                } else { $message = 'Old Vault Key incorrect.'; $message_type = 'error'; }
            } else { $message = 'Vault keys do not match.'; $message_type = 'error'; }
            }
        } else { $message = 'System password incorrect.'; $message_type = 'error'; }
    } elseif ($action === 'totp_setup_start') {
        $curr_pw = $_POST['current_password'] ?? '';
        if (!password_verify($curr_pw, $current_user['password'])) {
            $message = 'System password incorrect.';
            $message_type = 'error';
        } elseif (itm_totp_employee_has_enabled($current_user)) {
            $message = 'Two-factor authentication is already enabled.';
            $message_type = 'error';
        } else {
            $_SESSION['totp_setup_secret'] = itm_totp_create_setup_secret();
            $message = 'Scan the QR code with your authenticator app, then enter the 6-digit code to confirm.';
            $message_type = 'success';
        }
    } elseif ($action === 'totp_setup_confirm') {
        $setupSecret = (string)($_SESSION['totp_setup_secret'] ?? '');
        if ($setupSecret === '') {
            $message = '2FA setup session expired. Start setup again.';
            $message_type = 'error';
        } elseif (!itm_totp_verify_plain_secret($setupSecret, $_POST['totp_code'] ?? '')) {
            $message = 'Incorrect authenticator code. Try again.';
            $message_type = 'error';
        } else {
            $encryptedSecret = itm_totp_encrypt_secret($setupSecret);
            $enabled = 1;
            $stmt = mysqli_prepare($conn, 'UPDATE employees SET totp_secret = ?, totp_enabled = ? WHERE id = ? AND company_id = ?');
            mysqli_stmt_bind_param($stmt, 'siii', $encryptedSecret, $enabled, $user_id, $home_company_id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                unset($_SESSION['totp_setup_secret']);
                itm_log_audit($conn, 'employees', $user_id, 'UPDATE', ['action' => 'totp_off'], ['action' => 'totp_enable']);
                $message = 'Two-factor authentication enabled.';
                $message_type = 'success';
            } else {
                mysqli_stmt_close($stmt);
                $message = 'Unable to save 2FA settings.';
                $message_type = 'error';
            }
        }
    } elseif ($action === 'totp_disable') {
        $curr_pw = $_POST['current_password'] ?? '';
        if (!password_verify($curr_pw, $current_user['password'])) {
            $message = 'System password incorrect.';
            $message_type = 'error';
        } else {
            $totpGate = itm_totp_require_valid_code_or_error($current_user, $_POST['totp_code'] ?? '');
            if (!$totpGate['ok']) {
                $message = $totpGate['error'];
                $message_type = 'error';
            } else {
                $stmt = mysqli_prepare($conn, 'UPDATE employees SET totp_secret = NULL, totp_enabled = 0 WHERE id = ? AND company_id = ?');
                mysqli_stmt_bind_param($stmt, 'ii', $user_id, $home_company_id);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    unset($_SESSION['totp_setup_secret']);
                    itm_log_audit($conn, 'employees', $user_id, 'UPDATE', ['action' => 'totp_enable'], ['action' => 'totp_disable']);
                    $message = 'Two-factor authentication disabled.';
                    $message_type = 'success';
                } else {
                    mysqli_stmt_close($stmt);
                    $message = 'Unable to disable 2FA.';
                    $message_type = 'error';
                }
            }
        }
    } elseif ($action === 'totp_setup_cancel') {
        unset($_SESSION['totp_setup_secret']);
        $message = '2FA setup cancelled.';
        $message_type = 'info';
    } elseif ($action === 'update_sidebar') {
        $items = is_array($_POST['sidebar_items'] ?? null) ? $_POST['sidebar_items'] : [];
        if (itm_user_config_save_personalized_sidebar_items($conn, $company_id, $user_id, $items)) {
            $ui_config = itm_get_ui_configuration($conn, $company_id, $user_id);
            itm_log_audit($conn, 'employee_sidebar_preferences', $user_id, 'UPDATE', ['action' => 'sidebar_preferences_change'], ['action' => 'sidebar_preferences_change_success']);
            $message = 'Sidebar updated!';
            $message_type = 'success';
        } else {
            $message = 'Unable to update sidebar preferences.';
            $message_type = 'error';
        }
    } elseif ($action === 'upload_photo') {
        if (isset($_FILES['photo'])) {
            $res = emp_profile_photo_store_upload($home_company_id, $current_user, $_FILES['photo']);
            if ($res['ok']) {
                $stmt = mysqli_prepare($conn, "UPDATE employees SET photo = ? WHERE id = ? AND company_id = ?");
                mysqli_stmt_bind_param($stmt, 'sii', $res['filename'], $user_id, $home_company_id);
                mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
                itm_log_audit($conn, 'employees', $user_id, 'UPDATE', ['action'=>'photo_upload'], ['action'=>'photo_upload_success']);
                $message = 'Photo updated!'; $message_type = 'success';
            } else { $message = $res['error']; $message_type = 'error'; }
        }
    }
    if ($message !== '') {
        $message_action = (string)$action;
    }
    // Reload user data
    $stmt = mysqli_prepare($conn, 'SELECT e.*, ep.name AS position_name, d.name AS department_name, es.name AS status_name FROM employees e LEFT JOIN employee_positions ep ON e.employee_position_id = ep.id LEFT JOIN departments d ON e.department_id = d.id LEFT JOIN employee_statuses es ON e.employment_status_id = es.id WHERE e.id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt); $current_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
}

// Profile Completeness
$pc_fields = ['photo', 'work_email', 'mobile_phone', 'emergency_contact_name', 'emergency_contact_phone', 'vault_key_hash', 'birthday'];
$pc_filled = 0;
foreach ($pc_fields as $f) if (!empty($current_user[$f])) $pc_filled++;
$pc_percent = round(($pc_filled / count($pc_fields)) * 100);

$messageClass = ($message_type === 'success') ? 'crud_success' : (($message_type === 'error') ? 'crud_error' : '');
$user_config_render_flash = static function ($forAction = null) use ($message, $messageClass, $message_action) {
    if ($message === '') {
        return;
    }
    if ($forAction !== null && $message_action !== $forAction) {
        return;
    }
    $extraClass = $forAction !== null ? ' user-config-section-flash' : '';
    echo '<div class="' . sanitize($messageClass . $extraClass) . '">' . sanitize($message) . '</div>';
};
$profileTheme = $profile_normalize_theme($current_user['theme'] ?? ($_SESSION['ui_theme'] ?? 'light'));
$_SESSION['ui_theme'] = $profileTheme;
$totpEnabled = itm_totp_employee_has_enabled($current_user);
$totpSetupSecret = (string)($_SESSION['totp_setup_secret'] ?? '');
$totpSetupPending = $totpSetupSecret !== '';
$totpAccountLabel = trim((string)($current_user['work_email'] ?? ''));
if ($totpAccountLabel === '') {
    $totpAccountLabel = trim((string)($current_user['username'] ?? 'Employee'));
}
$totpIssuer = defined('APP_NAME') ? (string)APP_NAME : 'IT Management';
$totpQrUrl = $totpSetupPending
    ? itm_totp_build_qr_image_url($totpAccountLabel, $totpSetupSecret, $totpIssuer)
    : '';
$ui_config = itm_get_ui_configuration($conn, $company_id, $user_id);
$user_config_sidebar_ui = $ui_config;
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo sanitize($profileTheme); ?>">
<head>
    <meta charset="UTF-8">
    <title>Profile - <?php echo sanitize($current_user['display_name'] ?: $current_user['username']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script>
    // Why: apply employees.theme before paint; theme.js later reads localStorage.
    (function () {
        var theme = <?php echo json_encode($profileTheme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        window.ITM_PREFERRED_THEME = theme;
        try { localStorage.setItem('theme', theme); } catch (e) {}
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
    <style>
        .layout-2col { display: grid; grid-template-columns: 280px 1fr; gap: 20px; }
        .stat-lbl { font-size: 12px; color: var(--text-secondary); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; }
        .stat-card { background: var(--bg-primary); padding: 12px; border-radius: 8px; border: 1px solid var(--border); }
        .col-left { display: flex; flex-direction: column; gap: 20px; }
        .col-right { display: flex; flex-direction: column; gap: 20px; }
        /* Why: Fixed 280px sidebar collapses into a single column on phones/tablets. */
        @media (max-width: 768px) {
            .layout-2col { grid-template-columns: 1fr; }
        }
        .profile-pic { width: 120px; height: 120px; border-radius: 50%; border: 4px solid var(--bg-secondary); margin: 0 auto; overflow: hidden; position: relative; cursor: pointer; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; }
        .profile-pic img { width: 100%; height: 100%; object-fit: cover; }
        .profile-pic .overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.4); color: #fff; display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.2s; font-size: 12px; }
        .profile-pic:hover .overlay { opacity: 1; }
        .org-path { position: relative; padding-left: 20px; border-left: 2px solid var(--border); list-style: none; margin: 0; font-size: 13px; }
        .org-path li { margin-bottom: 10px; position: relative; }
        .org-path li::before { content: ''; position: absolute; left: -26px; top: 6px; width: 10px; height: 10px; border-radius: 50%; background: var(--bg-primary); border: 2px solid var(--accent); }
        .progress { height: 6px; background: var(--bg-tertiary); border-radius: 3px; overflow: hidden; margin: 5px 0; }
        .progress-bar { height: 100%; background: var(--success); }
        .access-item { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; margin: 2px; border: 1px solid var(--border); }
        .access-on { background: rgba(46, 160, 67, 0.15); color: var(--success); }
        .access-off { background: rgba(248, 81, 73, 0.12); color: var(--danger); opacity: 0.85; }
        .timeline { list-style: none; padding: 0; font-size: 13px; }
        .timeline-item { padding-bottom: 12px; border-left: 2px solid var(--border); padding-left: 15px; position: relative; color: var(--text-primary); }
        .timeline-item::after { content: ''; position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: var(--accent); }
        .user-config-section-flash { margin: 0 0 12px; }
    </style>
</head>
<body>
<div class="container">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'includes/header.php'; ?>
        <div class="content">
            <?php $user_config_render_flash(); ?>
            <div style="margin-top:20px;"><a class="btn" href="dashboard.php" title="Back">🔙</a></div><br><br>
            <div class="layout-2col">
                    <!-- LEFT COLUMN -->
                    <div class="col-left">
                        <div class="card" style="text-align:center;">
                            <div class="profile-pic" onclick="document.getElementById('file-photo').click()">
                                <?php $purl = emp_profile_photo_url($current_user); ?>
                                <?php if ($purl): ?><img src="<?php echo $purl; ?>" alt="Profile"><?php else: ?><h1>📷</h1><?php endif; ?>
                                <div class="overlay">Upload</div>
                            </div>
                            <form method="POST" enctype="multipart/form-data" id="form-photo" style="display:none;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="upload_photo">
                                <input type="file" name="photo" id="file-photo" onchange="this.form.submit()">
                            </form>
                            <h3 style="margin:10px 0 5px;"><?php echo sanitize($current_user['display_name'] ?: $current_user['first_name'].' '.$current_user['last_name']); ?></h3>
                            <div style="font-size:13px; color:#586069; margin-bottom:10px;"><?php echo sanitize($current_user['position_name']); ?></div>
                            <span class="badge badge-success"><?php echo sanitize($current_user['status_name']); ?></span>

                            <div style="margin-top:20px; text-align:left;">
                                <div class="stat-lbl">Profile Completeness</div>
                                <div class="progress"><div class="progress-bar" style="width:<?php echo $pc_percent; ?>%;"></div></div>
                                <div style="font-size:11px; text-align:right;"><?php echo $pc_percent; ?>%</div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>Org Chart Path</strong></div>
                            <ul class="org-path">
                                <?php foreach (array_reverse($reporting_chain) as $m): ?>
                                    <li>
                                        <strong><?php echo sanitize($m['display_name'] ?: $m['first_name']); ?></strong><br>
                                        <small style="color:#586069;"><?php echo sanitize($m['id'] == $user_id ? 'You' : 'Manager'); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="col-right">
                        <div class="card">
                            <div class="card-header"><strong title="Edit Profile">✏️</strong></div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="form-row">
                                    <div class="form-group"><label>Full Name</label><input type="text" value="<?php echo sanitize(trim(($current_user['first_name'] ?? '') . ' ' . ($current_user['last_name'] ?? ''))); ?>" readonly title="Name is managed in Employees"></div>
                                    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize((string)($current_user['work_email'] ?? '')); ?>" required></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo sanitize((string)($current_user['mobile_phone'] ?? '')); ?>"></div>
                                    <div class="form-group"><label>Theme</label><select name="theme"><option value="light" <?php if ($profileTheme === 'light') echo 'selected'; ?>>Light</option><option value="dark" <?php if ($profileTheme === 'dark') echo 'selected'; ?>>Dark</option></select></div>
                                </div>
                                <div class="form-row">
                                    <?php
                                    $profileBirthday = (string)($current_user['birthday'] ?? '');
                                    if ($profileBirthday === '0000-00-00') {
                                        $profileBirthday = '';
                                    }
                                    $profileHideYear = ((int)($current_user['hide_year'] ?? 0) === 1);
                                    ?>
                                    <div class="form-group">
                                        <label>Birthday</label>
                                        <input type="date" name="birthday" value="<?php echo sanitize($profileBirthday); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Hide Year</label>
                                        <label class="itm-checkbox-control">
                                            <input type="checkbox" name="hide_year" value="1" <?php echo $profileHideYear ? 'checked' : ''; ?>>
                                            <span>Hide Year <span class="itm-check-indicator" aria-hidden="true"><?php echo $profileHideYear ? '✅' : '❌'; ?></span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="card-header" style="background:none; border:none; padding:10px 0;"><strong>Emergency Contact</strong></div>
                                <div class="form-row">
                                    <div class="form-group"><label>Name</label><input type="text" name="emergency_contact_name" value="<?php echo sanitize((string)($current_user['emergency_contact_name'] ?? '')); ?>"></div>
                                    <div class="form-group"><label>Relationship</label><input type="text" name="emergency_contact_relationship" value="<?php echo sanitize((string)($current_user['emergency_contact_relationship'] ?? '')); ?>"></div>
                                    <div class="form-group"><label>Phone</label><input type="text" name="emergency_contact_phone" value="<?php echo sanitize((string)($current_user['emergency_contact_phone'] ?? '')); ?>"></div>
                                </div>
                                <button type="submit" class="btn btn-primary" title="Save">💾</button>
                            </form>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>🌐 System Access Overview</strong></div>
                            <div>
<?php



// Linha única da tabela
$sa = $system_access_overview ? $system_access_overview[0] : [];
//print_r($sa);
// Colunas que NÃO devem aparecer
// Why: hide identity + audit/soft-delete meta; only system flag columns render as ✅/❌.
$exclude = [
    'id', 'company_id', 'employee_id', 'active',
    'changed_at', 'created_at', 'updated_at', 'deleted_at',
    'created_by', 'updated_by', 'deleted_by',
];

// Descobrir automaticamente todos os campos de acesso
$access_fields = array_diff(array_keys($sa), $exclude);

foreach ($access_fields as $f):
    // Label automático: transforma "micros_emc" → "Micros Emc"
    $label = ucwords(str_replace('_', ' ', $f));

    // ON se for 1 ou string não vazia
    $on = (!empty($sa[$f]) && $sa[$f] != "0");
?>
    <span class="access-item <?php echo $on ? 'access-on' : 'access-off'; ?>">
        <?php echo $on ? '✅' : '❌'; ?> <?php echo $label; ?>
    </span>
<?php endforeach; ?>

                            </div>
                        </div>

<?php if (!empty($workstation)): ?>
<div class="card">
    <div class="card-header"><strong>💻 My Hardware Details</strong></div>
    <div style="font-size:13px;">
        <div class="form-row">
            <div class="form-group">
                <label>Model</label>
                <div><strong><?php echo sanitize($workstation['manufacturer_name'].' '.$workstation['model']); ?></strong></div>
            </div>
            <div class="form-group">
                <label>Hostname</label>
                <div><code><?php echo sanitize($workstation['hostname']); ?></code></div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Serial Number</label>
                <div><?php echo sanitize($workstation['serial_number']); ?></div>
            </div>
            <div class="form-group">
                <label>Processor</label>
                <div><?php echo sanitize($workstation['workstation_processor'] ?: '—'); ?></div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Storage</label>
                <div><?php echo sanitize($workstation['workstation_storage'] ?: '—'); ?></div>
            </div>
            <div class="form-group">
                <label>RAM</label>
                <div>
                    <?php
                    if (!empty($workstation['workstation_ram_id'])) {
                        $stmt_ram = mysqli_prepare($conn, "SELECT name FROM workstation_ram WHERE id = ?");
                        mysqli_stmt_bind_param($stmt_ram, "i", $workstation['workstation_ram_id']);
                        mysqli_stmt_execute($stmt_ram);
                        $ram_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ram));
                        echo sanitize($ram_row['name'] ?? '—');
                        mysqli_stmt_close($stmt_ram);
                    } else {
                        echo '—';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>

<?php endif; ?>


<div class="card">
    <div class="card-header"><strong>💻 Workstation mode</strong></div>
    <div style="font-size:13px;">
        <div class="form-row">
            <div class="form-group">
                <label>💻 Workstation Mode:</label>
                <div><strong><?php echo isset($mode_name) ? htmlspecialchars($assignment_types) : ' — '; ?></strong></div>
            </div>
            <div class="form-group">
                <label>👥 Assignment Type: </label>
                <div><strong><?php echo isset($assignment_types) ? htmlspecialchars($assignment_types) : ' — '; ?></strong></div>
            </div>
        </div>
	</div>
</div>


                        <div class="form-row">
                            <div class="card">
                                <div class="card-header"><strong>🔑 Change Password</strong></div>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="change_password">
                                    <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
                                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
                                    <div class="form-group"><label>Confirm</label><input type="password" name="confirm_password" required></div>
                                    <?php $user_config_render_flash('change_password'); ?>
                                    <button type="submit" class="btn btn-primary" title="Save">💾</button>
                                </form>
                            </div>
                            <div class="card" id="vault-security">
                                <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong>🔐 Vault Security</strong>
                                    <button type="button" class="btn btn-sm" onclick="itmGenerateVaultKey()" title="Generate Secure High-Entropy Key">🔑</button>
                                </div>
                                <p style="margin:0 0 12px; font-size:13px;">
                                    2FA status:
                                    <?php if ($totpEnabled): ?>
                                        <span class="badge badge-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Disabled</span>
                                    <?php endif; ?>
                                </p>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="vault_key_change">
                                    <div class="form-group"><label>System Password</label><input type="password" name="current_password" required></div>
                                    <?php if($current_user['vault_key_hash']):?>
                                        <div class="form-group">
                                            <label>Current Master Key</label>
                                            <div style="display:flex; gap:8px;">
                                                <input type="password" name="old_master_key_verify" required style="flex:1;">
                                                <button class="btn btn-sm" type="button" onclick="itmTogglePassword(this)" title="Toggle Visibility">👁️</button>
                                            </div>
                                        </div>
                                    <?php endif;?>
                                    <div class="form-group">
                                        <label>New Master Key</label>
                                        <div style="display:flex; gap:8px;">
                                            <input type="password" name="new_master_key" required style="flex:1;">
                                            <button class="btn btn-sm" type="button" onclick="itmTogglePassword(this)" title="Toggle Visibility">👁️</button>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Confirm</label>
                                        <div style="display:flex; gap:8px;">
                                            <input type="password" name="confirm_master_key" required style="flex:1;">
                                            <button class="btn btn-sm" type="button" onclick="itmTogglePassword(this)" title="Toggle Visibility">👁️</button>
                                        </div>
                                    </div>
                                    <?php if ($totpEnabled): ?>
                                    <div class="form-group">
                                        <label>Authenticator Code</label>
                                        <input type="text" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                                    </div>
                                    <?php endif; ?>
                                    <?php $user_config_render_flash('vault_key_change'); ?>
                                    <button type="submit" class="btn btn-primary" title="Save">💾</button>
                                </form>

                                <hr style="margin:20px 0; border:none; border-top:1px solid var(--border);">

                                <?php if ($totpSetupPending): ?>
                                    <div style="text-align:center; margin-bottom:16px;">
                                        <p style="font-size:13px; margin-bottom:12px;">Scan this QR code with Google Authenticator, Authy, or another TOTP app.</p>
                                        <img src="<?php echo sanitize($totpQrUrl); ?>" alt="TOTP QR code" width="200" height="200" style="border:1px solid var(--border); border-radius:8px;">
                                        <p style="font-size:12px; color:var(--text-secondary); margin-top:8px;">Manual key: <code><?php echo sanitize($totpSetupSecret); ?></code></p>
                                    </div>
                                    <form method="POST" style="margin-bottom:12px;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="totp_setup_confirm">
                                        <div class="form-group">
                                            <label>6-digit code</label>
                                            <input type="text" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                                        </div>
                                        <?php $user_config_render_flash('totp_setup_confirm'); ?>
                                        <button type="submit" class="btn btn-primary" title="Confirm 2FA">💾</button>
                                    </form>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="totp_setup_cancel">
                                        <button type="submit" class="btn btn-sm" title="Cancel">🔙</button>
                                    </form>
                                <?php elseif (!$totpEnabled): ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="totp_setup_start">
                                        <div class="form-group">
                                            <label>Enable 2FA — System Password</label>
                                            <input type="password" name="current_password" required>
                                        </div>
                                        <?php $user_config_render_flash('totp_setup_start'); ?>
                                        <button type="submit" class="btn btn-success btn-sm" title="Start 2FA setup">➕</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="action" value="totp_disable">
                                        <div class="form-group">
                                            <label>Disable 2FA — System Password</label>
                                            <input type="password" name="current_password" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Authenticator Code</label>
                                            <input type="text" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                                        </div>
                                        <?php $user_config_render_flash('totp_disable'); ?>
                                        <button type="submit" class="btn btn-danger btn-sm" title="Disable 2FA">🗑️</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if($direct_reports): ?>
                        <div class="card">
                            <div class="card-header"><strong>👥 Direct Reports</strong></div>
                            <div class="stats-grid">
                                <?php foreach($direct_reports as $dr): ?>
                                    <div class="stat-card" style="text-align:center;">
                                        <div style="width:40px;height:40px;border-radius:50%;margin:0 auto 8px;overflow:hidden;background:#eee;">
                                            <?php if($dr['photo']):?><img src="<?php echo emp_profile_photo_url($dr);?>" style="width:100%;height:100%;object-fit:cover;"><?php else:?>👤<?php endif;?>
                                        </div>
                                        <div style="font-size:12px;font-weight:700;"><?php echo sanitize($dr['display_name']?:$dr['first_name']);?></div>
                                        <div style="font-size:10px;color:#586069;"><?php echo sanitize($dr['position']);?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header"><strong>📑 Personalized Sidebar</strong></div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>"><input type="hidden" name="action" value="update_sidebar">
                                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:10px;">
                                    <?php foreach (itm_sidebar_item_catalog() as $id => $item):
                                        if ($id === 'dashboard_link') {
                                            continue;
                                        }
                                        if (!itm_sidebar_item_passes_access_gate($id, $conn, $company_id)) {
                                            continue;
                                        }
                                        $sidebarItem = is_array($item) ? $item : [];
                                        $sidebarItem['id'] = $id;
                                        $sidebarItemChecked = itm_sidebar_item_effective_visible($sidebarItem, $user_config_sidebar_ui, $conn, $company_id, $user_id);
                                        // Why: Open module in a new tab from the prefs grid without underline chrome.
                                        $sidebarItemHref = !empty($item['href']) ? (string)$item['href'] : ('modules/' . $id . '/');
                                        $sidebarItem = is_array($item) ? $item : [];
                                        $sidebarItem['id'] = $id;
                                        $sidebarItemChecked = itm_sidebar_item_effective_visible($sidebarItem, $user_config_sidebar_ui, $conn, $company_id, $user_id);
                                    ?>
                                        <label class="itm-checkbox-control">
                                            <input type="checkbox" name="sidebar_items[]" value="<?php echo sanitize($id); ?>"<?php echo $sidebarItemChecked ? ' checked' : ''; ?>>
                                            <span><a class="itm-user-config-sidebar-link" href="<?php echo sanitize($sidebarItemHref); ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($item['label']); ?></a></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="btn btn-primary" style="margin-top:15px;">💾</button>
                            </form>
                        </div>

                        <div class="card">
                            <div class="card-header"><strong>🕒 Recent Activity</strong></div>
                            <ul class="timeline">
                                <?php foreach($activity_list as $act): ?>
                                    <li class="timeline-item">
                                        <?php if($act['type']=='audit'):
                                            // Why: Match Personalized Sidebar — open modules/{table}/ in a new tab without blue underline chrome.
                                            $activityTable = (string)($act['table_name'] ?? '');
                                            $activityModuleHref = '';
                                            if ($activityTable !== '' && function_exists('itm_is_safe_identifier') && itm_is_safe_identifier($activityTable)) {
                                                foreach (itm_sidebar_item_catalog() as $catalogId => $catalogItem) {
                                                    if ($catalogId === $activityTable || (($catalogItem['match_dir'] ?? '') === $activityTable)) {
                                                        if (!empty($catalogItem['href'])) {
                                                            $activityModuleHref = (string)$catalogItem['href'];
                                                        }
                                                        break;
                                                    }
                                                }
                                                if ($activityModuleHref === '') {
                                                    $activityModuleHref = 'modules/' . $activityTable . '/';
                                                }
                                            }
                                        ?>
                                            <strong><?php echo sanitize($act['action']);?></strong> in <?php
                                            if ($activityModuleHref !== ''):
                                            ?><a class="itm-user-config-sidebar-link" href="<?php echo sanitize($activityModuleHref); ?>" target="_blank" rel="noopener noreferrer"><?php echo sanitize($activityTable); ?></a><?php
                                            else:
                                                echo sanitize($activityTable);
                                            endif;
                                            ?>
                                        <?php else:?><strong>Login</strong> <?php echo sanitize($act['attempt_type']);?> (<?php echo sanitize($act['attempt_source']);?>)<?php endif;?>
                                        <div style="color:#586069; font-size:11px;"><?php echo date('d M Y, H:i', strtotime($act['created_at']));?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;"><a class="btn" href="dashboard.php">🔙</a></div>
        </div>
    </div>
</div>
<!-- One-Time Display overlay for generated high-entropy master key -->
<div id="itm-vault-one-time-display" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:var(--bg-primary); border:1px solid var(--border); border-radius:12px; max-width:480px; width:90%; padding:30px; text-align:center; box-shadow:var(--shadow-lg); color:var(--text-primary);">
        <div style="font-size:48px; margin-bottom:16px;">🔑</div>
        <h2 style="margin-bottom:12px;">Secure One-Time Display</h2>
        <p id="itm-vault-otd-message" style="font-size:14px; margin-bottom:20px; color:var(--text-secondary); line-height:1.5;"></p>
        <div style="display:flex; gap:8px; margin-bottom:24px;">
            <input type="text" id="itm-generated-key-field" readonly class="form-control" style="font-family:monospace; font-size:16px; text-align:center; font-weight:bold; background:var(--bg-secondary); color:var(--text-primary); border:2px solid var(--accent); padding:10px; width:100%;">
            <button class="btn btn-sm" type="button" onclick="itmCopyGeneratedKey()" title="Copy Key" style="font-size:16px;">🗐</button>
        </div>
        <button type="button" class="btn btn-primary" style="width:100%; padding:12px; font-weight:bold;" onclick="itmCloseOneTimeDisplay()" title="Next">➡️</button>
    </div>
</div>

<script src="js/theme.js"></script>
<script>
(function () {
    // Why: re-assert DB/session theme after theme.js load (header may also load theme.js).
    var theme = window.ITM_PREFERRED_THEME || <?php echo json_encode($profileTheme, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    try { localStorage.setItem('theme', theme); } catch (e) {}
    document.documentElement.setAttribute('data-theme', theme);
    if (typeof updateThemeButton === 'function') { updateThemeButton(); }
})();
(function () {
    // Why: keep Hide Year indicator aligned with checkbox without waiting for reload.
    var hideYear = document.querySelector('input[name="hide_year"]');
    if (!hideYear) return;
    hideYear.addEventListener('change', function () {
        var indicator = hideYear.parentNode ? hideYear.parentNode.querySelector('.itm-check-indicator') : null;
        if (indicator) {
            indicator.textContent = hideYear.checked ? '✅' : '❌';
        }
    });
})();
    const pic = document.querySelector('.profile-pic');
    pic.addEventListener('dragover', e=>{ e.preventDefault(); pic.style.borderColor='#0366d6'; });
    pic.addEventListener('dragleave', ()=>{ pic.style.borderColor='#f6f8fa'; });
    pic.addEventListener('drop', e=>{ e.preventDefault();
        if(e.dataTransfer.files.length){
            document.getElementById('file-photo').files = e.dataTransfer.files;
            document.getElementById('form-photo').submit();
        }
    });

window.itmTogglePassword = function(btn) {
    const input = btn.parentNode.querySelector('input');
    if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
    }
};

window.itmVaultOtdMessages = {
    generated: 'Your generated Vault Key is displayed below.<br><strong style="color:#d93025;">CRITICAL:</strong> Save this key immediately in a secure location (such as a password manager or physical backup). Once you continue, this overlay field is cleared and the form fields are masked again. The key remains in the New/Confirm fields until you save or leave the page — save it externally before continuing.',
    saved: 'Your Vault Key was saved successfully. This is your only chance to copy it from this screen.<br><strong style="color:#d93025;">CRITICAL:</strong> Store it in a secure location (password manager or physical backup). The server cannot recover this key if you lose it. Once you continue, this display is cleared permanently.'
};

window.itmShowVaultKeyOneTimeDisplay = function(key, mode) {
    const keyField = document.getElementById('itm-generated-key-field');
    const modal = document.getElementById('itm-vault-one-time-display');
    const messageEl = document.getElementById('itm-vault-otd-message');
    if (!keyField || !modal) {
        return;
    }
    const displayMode = (mode === 'saved') ? 'saved' : 'generated';
    keyField.value = key;
    modal.setAttribute('data-itm-otd-mode', displayMode);
    if (messageEl) {
        messageEl.innerHTML = window.itmVaultOtdMessages[displayMode] || window.itmVaultOtdMessages.generated;
    }
    modal.style.display = 'flex';
};

window.itmGenerateVaultKey = function() {
    // Why: rejection sampling avoids modulo bias when mapping random bytes to charset indices.
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()-_=+';
    const charsetLimit = Math.floor(0x100000000 / chars.length) * chars.length;
    let key = '';
    for (let i = 0; i < 24; i++) {
        let randomValue;
        do {
            const buf = new Uint32Array(1);
            window.crypto.getRandomValues(buf);
            randomValue = buf[0];
        } while (randomValue >= charsetLimit);
        key += chars.charAt(randomValue % chars.length);
    }

    const newKeyInput = document.querySelector('input[name="new_master_key"]');
    const confirmKeyInput = document.querySelector('input[name="confirm_master_key"]');
    if (newKeyInput) { newKeyInput.value = key; newKeyInput.type = 'text'; }
    if (confirmKeyInput) { confirmKeyInput.value = key; confirmKeyInput.type = 'text'; }

    window.itmShowVaultKeyOneTimeDisplay(key, 'generated');
};

window.itmCopyGeneratedKey = function() {
    const keyField = document.getElementById('itm-generated-key-field');
    if (keyField) {
        const key = keyField.value;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(key).then(() => {
                alert('Vault Key copied successfully! Save it securely now.');
            }).catch(() => {
                // Fallback to execCommand
                keyField.select();
                document.execCommand('copy');
                alert('Vault Key copied successfully! Save it securely now.');
            });
        } else {
            keyField.select();
            document.execCommand('copy');
            alert('Vault Key copied successfully! Save it securely now.');
        }
    }
};

window.itmCloseOneTimeDisplay = function() {
    const keyField = document.getElementById('itm-generated-key-field');
    const modal = document.getElementById('itm-vault-one-time-display');
    const displayMode = modal ? modal.getAttribute('data-itm-otd-mode') : 'generated';

    if (keyField) {
        keyField.value = '';
    }

    const newKeyInput = document.querySelector('input[name="new_master_key"]');
    const confirmKeyInput = document.querySelector('input[name="confirm_master_key"]');
    if (displayMode === 'saved') {
        // Why: after save the hash is persisted — wipe any residual plaintext from the form.
        if (newKeyInput) { newKeyInput.value = ''; newKeyInput.type = 'password'; }
        if (confirmKeyInput) { confirmKeyInput.value = ''; confirmKeyInput.type = 'password'; }
        const oldKeyInput = document.querySelector('input[name="old_master_key_verify"]');
        if (oldKeyInput) { oldKeyInput.value = ''; oldKeyInput.type = 'password'; }
        const sysPwInput = document.querySelector('#vault-security input[name="current_password"]');
        if (sysPwInput) { sysPwInput.value = ''; }
    } else {
        if (newKeyInput) { newKeyInput.type = 'password'; }
        if (confirmKeyInput) { confirmKeyInput.type = 'password'; }
    }

    if (modal) {
        modal.style.display = 'none';
        modal.removeAttribute('data-itm-otd-mode');
    }
};

<?php if ($vault_key_otd_plaintext !== ''): ?>
document.addEventListener('DOMContentLoaded', function () {
    window.itmShowVaultKeyOneTimeDisplay(<?php echo json_encode($vault_key_otd_plaintext, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>, 'saved');
});
<?php endif; ?>
</script>
</body>
</html>
