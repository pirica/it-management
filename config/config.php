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
define('MAILERLITE_API_KEY', 'YOUR_MAILERLITE_API_KEY_HERE');
define('MAILERLITE_URL', 'https://connect.mailerlite.com/api/emails/single');

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

// Redirect if not logged in (except auth/public pages)
$current_file = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && !in_array($current_file, ['login.php', 'register.php', 'forgot-password.php', 'reset-password.php', 'logout.php'], true)) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

if (isset($_SESSION['user_id']) && !isset($_SESSION['company_id']) && !in_array($current_file, ['index.php', 'logout.php'], true)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$company_id = $_SESSION['company_id'] ?? 0;

$ui_config = itm_get_ui_configuration($conn, $company_id);
if (($ui_config['enable_all_error_reporting'] ?? 1) === 1) {
    //Enable all error reporting
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', ROOT_PATH . 'error_log.txt');
}

// Helper Functions
function sanitize($data) {
    if ($data === null) {
        return '';
    }

    if (!is_string($data)) {
        if (is_scalar($data)) {
            $data = (string)$data;
        } else {
            return '';
        }
    }

    return htmlspecialchars(stripslashes($data), ENT_QUOTES, 'UTF-8');
}

function escape_sql($data, $conn) {
    return mysqli_real_escape_string($conn, $data);
}


function itm_run_query($conn, $sql, &$errorCode = null, &$errorMessage = null) {
    $errorCode = null;
    $errorMessage = null;

    try {
        $result = mysqli_query($conn, $sql);
        if ($result === false) {
            $errorCode = (int)mysqli_errno($conn);
            $errorMessage = (string)mysqli_error($conn);
        }
        return $result;
    } catch (Throwable $t) {
        $errorCode = (int)$t->getCode();
        $errorMessage = (string)$t->getMessage();
        return false;
    }
}

function itm_format_db_constraint_error($errorCode, $fallbackMessage = '') {
    switch ((int)$errorCode) {
        case 1451:
            return 'This record cannot be deleted because other records still reference it. Remove or reassign the related records first.';
        case 1452:
            return 'The selected related record does not exist anymore. Refresh the page and choose a valid value.';
        case 1062:
            return 'A record with the same unique value already exists. Use a different value.';
        default:
            if ($fallbackMessage !== '') {
                return 'Database error: ' . $fallbackMessage;
            }
            return 'A database error occurred. Please try again.';
    }
}


function itm_is_safe_identifier($name) {
    return is_string($name) && preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;
}

function itm_table_has_column($conn, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($column)) {
        $cache[$key] = false;
        return false;
    }

    $tableEsc = mysqli_real_escape_string($conn, $table);
    $columnEsc = mysqli_real_escape_string($conn, $column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$tableEsc}' AND COLUMN_NAME = '{$columnEsc}' LIMIT 1";
    $res = mysqli_query($conn, $sql);
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

function itm_find_record_usage($conn, $table, $pkColumn, $pkValue, $companyId = 0) {
    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($pkColumn)) {
        return [];
    }

    $tableEsc = mysqli_real_escape_string($conn, $table);
    $pkEsc = mysqli_real_escape_string($conn, $pkColumn);

    $sql = "SELECT kcu.TABLE_NAME AS source_table, kcu.COLUMN_NAME AS source_column
"
        . "FROM information_schema.KEY_COLUMN_USAGE kcu
"
        . "WHERE kcu.TABLE_SCHEMA = DATABASE()
"
        . "  AND kcu.REFERENCED_TABLE_NAME = '{$tableEsc}'
"
        . "  AND kcu.REFERENCED_COLUMN_NAME = '{$pkEsc}'";

    $usage = [];
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $sourceTable = (string)($row['source_table'] ?? '');
        $sourceColumn = (string)($row['source_column'] ?? '');
        if (!itm_is_safe_identifier($sourceTable) || !itm_is_safe_identifier($sourceColumn)) {
            continue;
        }

        $where = '`' . str_replace('`', '``', $sourceColumn) . '`=' . (int)$pkValue;
        if ((int)$companyId > 0 && itm_table_has_column($conn, $sourceTable, 'company_id')) {
            $where .= ' AND `company_id`=' . (int)$companyId;
        }

        $countSql = 'SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $sourceTable) . '` WHERE ' . $where;
        $countRes = mysqli_query($conn, $countSql);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        $count = (int)($countRow['c'] ?? 0);
        if ($count > 0) {
            $usage[] = [
                'table' => $sourceTable,
                'column' => $sourceColumn,
                'count' => $count,
            ];
        }
    }

    return $usage;
}

function itm_format_record_usage_error($table, $usage) {
    $tableLabel = ucwords(str_replace('_', ' ', (string)$table));
    if (empty($usage)) {
        return $tableLabel . ' cannot be deleted because it is currently in use.';
    }

    $parts = [];
    foreach ($usage as $row) {
        $parts[] = ($row['table'] ?? 'unknown') . ' (' . (int)($row['count'] ?? 0) . ')';
    }

    return $tableLabel . ' cannot be deleted because it is currently in use by: ' . implode(', ', $parts) . '.';
}

function itm_can_delete_record($conn, $table, $pkColumn, $pkValue, $companyId = 0, &$error = '') {
    $error = '';
    $usage = itm_find_record_usage($conn, $table, $pkColumn, $pkValue, $companyId);
    if (!empty($usage)) {
        $error = itm_format_record_usage_error($table, $usage);
        return false;
    }

    return true;
}


function itm_validate_csrf_token($token) {
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $token = (string)$token;
    return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
}

function itm_require_post_csrf() {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function itm_get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function get_company_name($company_id, $conn) {
    $sql = 'SELECT company FROM companies WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 'Unknown';
    }
    $companyId = (int)$company_id;
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 'Unknown';
    }
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    if ($row) {
        return $row['company'] ?? 'Unknown';
    }
    return 'Unknown';
}
?>
