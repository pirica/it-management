<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'usbw');
define('DB_NAME', 'itmanagement');

// Application Settings
define('APP_NAME', 'IT Management System');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'production'); // development or production

// Paths
define('BASE_URL', 'http://localhost:8080/it-management/');
define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
define('UPLOAD_PATH', ROOT_PATH . 'equipment/');
define('UPLOAD_URL', BASE_URL . 'equipment/');

// Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Database Connection
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die(json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]));
}

mysqli_set_charset($conn, "utf8mb4");

// Session Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in (except on index and logout)
$current_file = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['company_id']) && $current_file !== 'index.php' && $current_file !== 'logout.php') {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$company_id = $_SESSION['company_id'] ?? 0;

// Helper Functions
function sanitize($data) {
    return htmlspecialchars(stripslashes($data), ENT_QUOTES, 'UTF-8');
}

function escape_sql($data, $conn) {
    return mysqli_real_escape_string($conn, $data);
}

function get_company_name($company_id, $conn) {
    $result = mysqli_query($conn, "SELECT name FROM companies WHERE id = $company_id");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        return $row['name'] ?? 'Unknown';
    }
    return 'Unknown';
}
?>
