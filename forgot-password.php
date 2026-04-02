<?php
include('config/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $token = bin2hex(random_bytes(20));

    $stmt = mysqli_prepare($conn, 'UPDATE users SET reset_token = ? WHERE email = ?');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $token, $email);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $link = BASE_URL . 'reset-password.php?token=' . urlencode($token);
            $payload = json_encode([
                'from' => 'verified@yourdomain.com',
                'to' => $email,
                'subject' => 'Reset Your Password',
                'html' => "Click here to reset: <a href='$link'>$link</a>",
            ]);

            $ch = curl_init(MAILERLITE_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . MAILERLITE_API_KEY,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }

        mysqli_stmt_close($stmt);
    }

    $message = 'If your email exists, a reset link has been sent.';
}
?>
<!DOCTYPE html>
<html lang="en"><body>
<h2>Forgot Password</h2>
<?php if (isset($message)): ?><p style="color:green;"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="POST">
    <input type="email" name="email" placeholder="Email" required><br><br>
    <button type="submit">Send Email</button>
</form>
</body></html>
