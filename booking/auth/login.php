<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../includes/portal_chrome.php';
$company_id = hb_public_company_id($conn);
$settings = itm_hotel_booking_settings_row($conn, $company_id) ?: [];
hb_portal_bind_money_settings($settings);
if (hb_portal_logged_in()) {
    header('Location: ' . APPURL . '/');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_try_post_csrf()) {
        $error = hb_portal_ui_copy('portal_ui_auth_invalid_csrf', [], $settings);
    } else {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $stmt = mysqli_prepare($conn, 'SELECT pu.id, pu.customer_id, pu.password_hash FROM hotel_booking_portal_users pu WHERE pu.company_id = ? AND pu.email = ? AND pu.deleted_at IS NULL LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $company_id, $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($row && password_verify($password, $row['password_hash'])) {
            $_SESSION['hotel_booking_customer_id'] = (int) $row['customer_id'];
            $_SESSION['hotel_booking_portal_user_id'] = (int) $row['id'];
            $_SESSION['company_id'] = $company_id;
            header('Location: ' . APPURL . '/');
            exit;
        }
    }
    $error = hb_portal_ui_copy('portal_ui_auth_invalid_credentials', [], $settings);
    }
}
$pageTitle = hb_portal_ui_copy('portal_ui_auth_sign_in_title', [], $settings);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title><?php echo hb_portal_ui_copy_esc('portal_ui_auth_sign_in_title', [], $settings); ?></title><link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css"></head>
<body class="hb-public">
<div class="auth-card">
<h1><?php echo hb_portal_ui_copy_esc('portal_ui_auth_sign_in_title', [], $settings); ?></h1>
<?php if ($error): ?><p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<label><?php echo hb_portal_ui_copy_esc('portal_ui_auth_email_label', [], $settings); ?></label><input type="email" name="email" required>
<label><?php echo hb_portal_ui_copy_esc('portal_ui_auth_password_label', [], $settings); ?></label><input type="password" name="password" required>
<button type="submit" class="hb-btn hb-btn-primary" title="<?php echo hb_portal_ui_copy_esc('portal_ui_auth_sign_in_button', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_auth_sign_in_button', [], $settings); ?></button>
</form>
<p><a href="<?php echo APPURL; ?>/auth/register.php"><?php echo hb_portal_ui_copy_esc('portal_ui_auth_register_link', [], $settings); ?></a> · <a href="<?php echo APPURL; ?>/"><?php echo hb_portal_ui_copy_esc('portal_ui_auth_home_link', [], $settings); ?></a></p>
</div>
</body></html>
