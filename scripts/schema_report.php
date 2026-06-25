<?php
// ------------------------------------------------------------
// Load .env
// ------------------------------------------------------------
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

// ------------------------------------------------------------
// MySQL connection using .env
// ------------------------------------------------------------
$dbHost = getenv('DB_HOST');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');

$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

if (!$conn) {
    die("<h1 style='color:red'>Database connection failed: " . mysqli_connect_error() . "</h1>");
}

// ------------------------------------------------------------
// Ensure $errors and $warnings exist
// ------------------------------------------------------------
$errors   = $errors   ?? [];
$warnings = $warnings ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Database Schema Report</title>
<style>
    body { font-family: Arial, sans-serif; background: #f4f6f9; margin: 0; padding: 0; color: #333; }
    header { background: #2c3e50; color: white; padding: 25px; text-align: center; font-size: 28px; font-weight: bold; }
    .container { width: 90%; margin: 30px auto; }
    h2 { margin-top: 40px; color: #2c3e50; border-left: 6px solid #3498db; padding-left: 10px; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
    th { background: #34495e; color: white; padding: 12px; text-align: left; }
    td { padding: 10px; border-bottom: 1px solid #eee; }
    tr:nth-child(even) { background: #f9f9f9; }
    .ok { color: #27ae60; font-weight: bold; }
    .warn { color: #e67e22; font-weight: bold; }
    .error { color: #c0392b; font-weight: bold; }
    .meta-box { background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); margin-top: 20px; }
</style>
</head>
<body>

<header>📊 Database Schema Validation Report</header>

<div class="container">

    <h2>Schema Summary</h2>
    <div class="meta-box">
        <p><strong>File:</strong> <?= __FILE__ ?></p>
        <p><strong>Database:</strong> <?= mysqli_fetch_row(mysqli_query($conn, "SELECT DATABASE()"))[0] ?></p>
        <p><strong>Host:</strong> <?= mysqli_get_host_info($conn) ?></p>
        <p><strong>Generated At:</strong> <?= date("Y-m-d H:i:s") ?></p>
    </div>

    <h2>Errors</h2>
    <table>
        <tr><th>Status</th><th>Description</th></tr>
        <?php if (empty($errors)): ?>
            <tr><td class="ok">✔ OK</td><td>No errors found</td></tr>
        <?php else: foreach ($errors as $e): ?>
            <tr><td class="error">✖ ERROR</td><td><?= $e ?></td></tr>
        <?php endforeach; endif; ?>
    </table>

    <h2>Warnings</h2>
    <table>
        <tr><th>Status</th><th>Description</th></tr>
        <?php if (empty($warnings)): ?>
            <tr><td class="ok">✔ OK</td><td>No warnings found</td></tr>
        <?php else: foreach ($warnings as $w): ?>
            <tr><td class="warn">⚠ WARNING</td><td><?= $w ?></td></tr>
        <?php endforeach; endif; ?>
    </table>

</div>

</body>
</html>
