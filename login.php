<?php
session_start();
include('config/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginIdentifier = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, password FROM users WHERE active = 1 AND (LOWER(email) = LOWER(?) OR LOWER(username) = LOWER(?)) LIMIT 1'
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $loginIdentifier, $loginIdentifier);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        $storedPassword = (string)($user['password'] ?? '');
        $passwordMatches = $user && (
            password_verify($password, $storedPassword)
            || hash_equals($storedPassword, $password)
        );

        if ($passwordMatches) {
            $_SESSION['user_id'] = (int)$user['id'];
            unset($_SESSION['company_id'], $_SESSION['company_name']);
            header('Location: index.php');
            exit();
        }
    }

    $error = 'Invalid credentials.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Login</title></head>
<body>
<h2>Login</h2>
<?php if (isset($error)): ?><p style="color:red;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
<form method="POST">
    <input type="text" name="email" placeholder="Email or Admin" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <button type="submit">Login</button>
</form>
<a href="forgot-password.php">Forgot Password?</a>
</body>
</html>
