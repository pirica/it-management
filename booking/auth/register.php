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
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    if ($email === '' || $password === '' || $fullName === '') {
        $error = hb_portal_ui_copy('portal_ui_auth_all_fields_required', [], $settings);
    } else {
        $customerId = itm_hotel_booking_ensure_customer_for_portal($conn, $company_id, $email, $fullName, $phone);
        if (!$customerId) {
            $error = hb_portal_ui_copy('portal_ui_auth_register_customer_failed', [], $settings);
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = mysqli_prepare($conn, 'INSERT INTO hotel_booking_portal_users (company_id, customer_id, email, password_hash, active, created_at) VALUES (?, ?, ?, ?, 1, NOW())');
            if ($ins) {
                mysqli_stmt_bind_param($ins, 'iiss', $company_id, $customerId, $email, $hash);
                if (mysqli_stmt_execute($ins)) {
                    mysqli_stmt_close($ins);
                    header('Location: ' . APPURL . '/auth/login.php');
                    exit;
                }
                mysqli_stmt_close($ins);
            }
            $error = hb_portal_ui_copy('portal_ui_auth_email_registered', [], $settings);
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title><?php echo hb_portal_ui_copy_esc('portal_ui_auth_register_title', [], $settings); ?></title><link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css"></head>
<body class="hb-public">
<div class="auth-card">
<h1><?php echo hb_portal_ui_copy_esc('portal_ui_auth_register_title', [], $settings); ?></h1>
<?php if ($error): ?><p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<label><?php echo hb_portal_ui_copy_esc('portal_ui_auth_full_name_label', [], $settings); ?></label><input type="text" name="full_name" required>
<label><?php echo hb_portal_ui_copy_esc('portal_ui_auth_email_label', [], $settings); ?></label><input type="email" name="email" required>
<label><?php echo hb_portal_ui_copy_esc('portal_ui_auth_phone_label', [], $settings); ?></label><input type="text" name="phone">
<label><?php echo hb_portal_ui_copy_esc('portal_ui_auth_password_label', [], $settings); ?></label><input type="password" name="password" required>
<button type="submit" class="hb-btn hb-btn-primary" title="<?php echo hb_portal_ui_copy_esc('portal_ui_auth_register_title', [], $settings); ?>"><?php echo hb_portal_ui_copy_esc('portal_ui_auth_register_title', [], $settings); ?></button>
</form>
<p><a href="<?php echo APPURL; ?>/auth/login.php"><?php echo hb_portal_ui_copy_esc('portal_ui_auth_sign_in_link', [], $settings); ?></a></p>
</div>
</body></html>
