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
define('UPLOAD_PATH', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH . 'equipment'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('UPLOAD_URL', BASE_URL . 'equipment/');
define('BACKUP_PATH', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH . 'backups'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('BACKUP_URL', BASE_URL . 'backups/');

// Upload Settings
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

if (!is_dir(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0775, true);
}

if (!is_dir(BACKUP_PATH)) {
    @mkdir(BACKUP_PATH, 0775, true);
}

require_once ROOT_PATH . 'includes/ui_config.php';
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

$ui_config = itm_get_ui_configuration($conn, $company_id);

// Helper Functions
function sanitize($data) {
    return htmlspecialchars(stripslashes($data), ENT_QUOTES, 'UTF-8');
}

function escape_sql($data, $conn) {
    return mysqli_real_escape_string($conn, $data);
}

function itm_get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
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
