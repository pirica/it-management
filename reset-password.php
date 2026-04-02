<?php
include('config/config.php');
$token = $_GET['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token !== '') {
    $new_password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);

    $stmt = mysqli_prepare($conn, 'UPDATE users SET password = ?, reset_token = NULL WHERE reset_token = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $new_password, $token);
        if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
            $success = true;
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en"><body>
<h2>New Password</h2>
<?php if (!empty($success)): ?><p>Password updated! <a href="login.php">Login</a></p><?php endif; ?>
<form method="POST">
    <input type="password" name="password" placeholder="New Password" required><br><br>
    <button type="submit">Update</button>
</form>
</body></html>
