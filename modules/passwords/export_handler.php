<?php
require_once '../../config/config.php';

if (!isset($_SESSION['employee_id'])) {
    die("Unauthorized");
}

if (empty($_SESSION['vault_key'])) {
    die("Vault locked");
}

if (!itm_validate_csrf_token($_GET['csrf_token'] ?? '')) {
    die("Invalid CSRF token");
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) { die('Connection failed'); }

$user_id = (int)$_SESSION['employee_id'];
$folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : 0;
$format = (string)($_GET['format'] ?? 'csv');

$sql = "SELECT * FROM password_entries WHERE employee_id = ?";
if ($folder_id > 0) {
    $sql .= " AND folder_name = ?";
}
$sql .= " ORDER BY account ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($folder_id > 0) {
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $folder_id);
} else {
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
}

mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$entries = [];
while ($row = mysqli_fetch_assoc($res)) {
    $row['password'] = itm_decrypt($row['password'], $_SESSION['vault_key']);
    $entries[] = [
        'Account' => $row['account'],
        'Login Name' => $row['login_name'],
        'Password' => $row['password'],
        'Web Site' => $row['website'],
        'Comments' => $row['comments']
    ];
}
mysqli_stmt_close($stmt);
mysqli_close($conn);

$filename = "passwords_export_" . date('Y-m-d_His');

switch ($format) {
    case 'csv':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Account', 'Login Name', 'Password', 'Web Site', 'Comments']);
        foreach ($entries as $e) fputcsv($output, $e);
        fclose($output);
        break;

    case 'txt':
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
        foreach ($entries as $e) {
            echo "Account: " . $e['Account'] . "\n";
            echo "Login: " . $e['Login Name'] . "\n";
            echo "Password: " . $e['Password'] . "\n";
            echo "Website: " . $e['Web Site'] . "\n";
            echo "Comments: " . $e['Comments'] . "\n";
            echo "----------------------------------------\n";
        }
        break;

    case 'xlsx':
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        echo "Account\tLogin Name\tPassword\tWeb Site\tComments\n";
        foreach ($entries as $e) {
            echo implode("\t", array_values($e)) . "\n";
        }
        break;

    case 'pdf':
        header('Content-Type: text/html');
        echo "<html><head><style>table { width: 100%; border-collapse: collapse; } th, td { border: 1px solid black; padding: 5px; text-align: left; } body { font-family: sans-serif; }</style></head><body>";
        echo "<h1>Passwords Export</h1>";
        echo "<table><thead><tr><th>Account</th><th>Login</th><th>Password</th><th>Website</th><th>Comments</th></tr></thead><tbody>";
        foreach ($entries as $e) {
            echo "<tr><td>" . htmlspecialchars($e['Account']) . "</td><td>" . htmlspecialchars($e['Login Name']) . "</td><td>" . htmlspecialchars($e['Password']) . "</td><td>" . htmlspecialchars($e['Web Site']) . "</td><td>" . htmlspecialchars($e['Comments']) . "</td></tr>";
        }
        echo "</tbody></table>";
        echo "<script>window.print();</script></body></html>";
        break;
}
