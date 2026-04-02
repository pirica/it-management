<?php
session_start();
include('config/config.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    $stmt = mysqli_prepare($conn, 'SELECT password FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($current_password, (string)$user['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ?');
            if ($update) {
                mysqli_stmt_bind_param($update, 'si', $hashed, $user_id);
                mysqli_stmt_execute($update);
                mysqli_stmt_close($update);
                $message = 'Password updated!';
            }
        } else {
            $error = 'Wrong password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en"><body>
<h2>Change Password</h2>
<?php if (isset($message)): ?><p style="color:green;"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p style="color:red;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
<form method="POST">
    <input type="password" name="current_password" placeholder="Current" required><br>
    <input type="password" name="new_password" placeholder="New" required><br>
    <button type="submit">Update</button>
</form>
</body></html>
