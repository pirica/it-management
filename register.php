<?php
include('config/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $company_id = (int)($_POST['company_id'] ?? 0);

    $stmt = mysqli_prepare($conn, 'INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES (?, ?, ?, ?, (SELECT id FROM user_roles WHERE company_id = ? AND name = "User" LIMIT 1), (SELECT id FROM access_levels WHERE company_id = ? AND name = "Limited" LIMIT 1), 1)');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'isssii', $company_id, $username, $email, $password, $company_id, $company_id);
        if (mysqli_stmt_execute($stmt)) {
            $user_id = (int)mysqli_insert_id($conn);
            $uc = mysqli_prepare($conn, 'INSERT INTO user_companies (user_id, company_id, granted_by_user_id) VALUES (?, ?, NULL)');
            if ($uc) {
                mysqli_stmt_bind_param($uc, 'ii', $user_id, $company_id);
                mysqli_stmt_execute($uc);
                mysqli_stmt_close($uc);
            }
            $success = 'Registration successful! You can login now.';
        } else {
            $error = 'Registration failed. Email/username may already exist.';
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en"><body>
<h2>Register</h2>
<?php if (isset($success)): ?><p style="color:green;"><?php echo htmlspecialchars($success); ?></p><?php endif; ?>
<?php if (isset($error)): ?><p style="color:red;"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
<form method="POST">
    <input type="number" name="company_id" placeholder="Company ID" required><br><br>
    <input type="text" name="username" placeholder="Username" required><br><br>
    <input type="email" name="email" placeholder="Email" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <button type="submit">Register</button>
</form>
</body></html>
