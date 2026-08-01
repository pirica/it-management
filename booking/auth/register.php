<?php
require __DIR__ . '/../bootstrap.php';
$company_id = hb_public_company_id($conn);
if (hb_portal_logged_in()) {
    header('Location: ' . APPURL . '/');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    if ($email === '' || $password === '' || $fullName === '') {
        $error = 'All fields required.';
    } else {
        $customerId = itm_hotel_booking_ensure_customer_for_portal($conn, $company_id, $email, $fullName, $phone);
        if (!$customerId) {
            $error = 'Could not create customer.';
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
            $error = 'Email may already be registered.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Register</title><link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css"></head>
<body class="hb-public">
<div class="auth-card">
<h1>Register</h1>
<?php if ($error): ?><p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<label>Full name</label><input type="text" name="full_name" required>
<label>Email</label><input type="email" name="email" required>
<label>Phone</label><input type="text" name="phone">
<label>Password</label><input type="password" name="password" required>
<button type="submit" class="hb-btn hb-btn-primary" title="Register">Register</button>
</form>
<p><a href="<?php echo APPURL; ?>/auth/login.php">Sign in</a></p>
</div>
</body></html>
