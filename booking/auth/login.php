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
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>Sign in</title><link rel="stylesheet" href="<?php echo APPURL; ?>/css/hotel-booking-modern.css"></head>
<body class="hb-public">
<div class="auth-card">
<h1>Sign in</h1>
<?php if ($error): ?><p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(itm_get_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<label>Email</label><input type="email" name="email" required>
<label>Password</label><input type="password" name="password" required>
<button type="submit" class="hb-btn hb-btn-primary" title="Sign in">Sign in</button>
</form>
<p><a href="<?php echo APPURL; ?>/auth/register.php">Register</a> · <a href="<?php echo APPURL; ?>/">Home</a></p>
</div>
</body></html>
