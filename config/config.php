<?php
/**
 * Global Configuration File
 * 
 * Defines database credentials, application settings, and file paths.
 * Handles database connection, session management, audit logging,
 * and global security middleware (authentication & CSRF).
 */

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

// --- Path Calculation Logic ---
// Automatically determines the base URL and filesystem paths regardless of the deployment subdirectory
$itm_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$itm_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$itm_documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$itm_projectRoot = realpath(dirname(__DIR__));
$itm_basePath = '';

if ($itm_documentRoot && $itm_projectRoot && strpos($itm_projectRoot, $itm_documentRoot) === 0) {
    $itm_basePath = str_replace('\\', '/', substr($itm_projectRoot, strlen($itm_documentRoot)));
} else {
    $itm_scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $itm_modulesPos = strpos($itm_scriptName, '/modules/');
    if ($itm_modulesPos !== false) {
        $itm_basePath = substr($itm_scriptName, 0, $itm_modulesPos);
    } else {
        $itm_basePath = str_replace('\\', '/', dirname($itm_scriptName));
    }
}

$itm_basePath = '/' . trim((string)$itm_basePath, '/');
if ($itm_basePath === '/') {
    $itm_basePath = '';
}

define('BASE_URL', $itm_scheme . '://' . $itm_host . ($itm_basePath !== '' ? $itm_basePath . '/' : '/'));
define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
define('UPLOAD_PATH', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH . 'images'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('UPLOAD_URL', BASE_URL . 'images/');
define('TICKET_UPLOAD_PATH', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH . 'tickets_photos'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('TICKET_UPLOAD_URL', BASE_URL . 'tickets_photos/');
define('BACKUP_PATH', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH . 'backups'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('BACKUP_URL', BASE_URL . 'backups/');

// Upload Restrictions
define('MAX_FILE_SIZE', 5242880); // 5MB limit
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Ensure required upload and backup directories exist
if (!is_dir(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0775, true);
}
if (!is_dir(TICKET_UPLOAD_PATH)) {
    @mkdir(TICKET_UPLOAD_PATH, 0775, true);
}
if (!is_dir(BACKUP_PATH)) {
    @mkdir(BACKUP_PATH, 0775, true);
}

// Load secondary configuration and library files
require_once ROOT_PATH . 'includes/ui_config.php';
require_once ROOT_PATH . 'includes/audit_functions.php';

// Establish Database Connection
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die(json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]));
}

mysqli_set_charset($conn, "utf8mb4");

// Initialize Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Audit Logging Setup ---
// Capture user context for database-level audit triggers
$itmAuditUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$itmAuditCompanyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
$itmAuditUsername = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '';
$itmAuditEmail = isset($_SESSION['email']) ? (string)$_SESSION['email'] : '';
$itmAuditIp = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
$itmAuditUserAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

// Set MySQL session variables for auditing
mysqli_query($conn, 'SET @app_user_id = ' . ($itmAuditUserId === null ? 'NULL' : (string)$itmAuditUserId));
mysqli_query($conn, 'SET @app_company_id = ' . (string)$itmAuditCompanyId);
mysqli_query($conn, "SET @app_username = '" . mysqli_real_escape_string($conn, $itmAuditUsername) . "'");
mysqli_query($conn, "SET @app_email = '" . mysqli_real_escape_string($conn, $itmAuditEmail) . "'");
mysqli_query($conn, "SET @app_ip_address = '" . mysqli_real_escape_string($conn, $itmAuditIp) . "'");
mysqli_query($conn, "SET @app_user_agent = '" . mysqli_real_escape_string($conn, $itmAuditUserAgent) . "'");

// --- Global Access Control ---
$current_file = basename($_SERVER['PHP_SELF']);

// Redirect to login if session is missing, excluding auth pages
if (!isset($_SESSION['user_id']) && !in_array($current_file, ['login.php', 'register.php', 'forgot-password.php', 'reset-password.php', 'logout.php'], true)) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Restrict users in read-only mode to the user-config page
$isReadOnlyUserConfig = !empty($_SESSION['read_only_user_config']);
if ($isReadOnlyUserConfig && !in_array($current_file, ['user-config.php', 'logout.php'], true)) {
    header('Location: ' . BASE_URL . 'user-config.php');
    exit();
}

// Ensure a company is selected before accessing protected modules
if (isset($_SESSION['user_id']) && !isset($_SESSION['company_id']) && !$isReadOnlyUserConfig && !in_array($current_file, ['index.php', 'logout.php'], true)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$company_id = $_SESSION['company_id'] ?? 0;

// Load UI preferences and fall back safely if configuration bootstrap fails.
try {
    $ui_config = itm_get_ui_configuration($conn, $company_id);
} catch (Throwable $t) {
    $ui_config = function_exists('itm_ui_config_defaults') ? itm_ui_config_defaults() : [];
    error_log('UI configuration bootstrap failed: ' . $t->getMessage());
}

// Set runtime error reporting from configuration.
if (($ui_config['enable_all_error_reporting'] ?? 1) === 1) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', ROOT_PATH . 'error_log.txt');
}

// --- Global Helper Functions ---

/**
 * Sanitizes data for safe HTML output
 */
if (!function_exists('sanitize')) {
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
}

/**
 * Escapes data for safe use in SQL queries (deprecated in favor of prepared statements)
 */
if (!function_exists('escape_sql')) {
    function escape_sql($data, $conn) {
        return mysqli_real_escape_string($conn, $data);
    }
}

/**
 * Runs a MySQL query with basic error trapping
 */
if (!function_exists('itm_run_query')) {
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
}

/**
 * Translates MySQL error codes into user-friendly messages
 */
if (!function_exists('itm_format_db_constraint_error')) {
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
    }

/**
 * Validates that a string is a safe SQL identifier (table/column name)
 */
if (!function_exists('itm_is_safe_identifier')) {
    function itm_is_safe_identifier($name) {
        return is_string($name) && preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;
    }
}

/**
 * Checks if a table has a specific column
 */
if (!function_exists('itm_table_has_column')) {
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

        $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            $cache[$key] = false;
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $cache[$key] = ($res && mysqli_num_rows($res) > 0);
        mysqli_stmt_close($stmt);
        return $cache[$key];
    }
}

/**
 * Finds all records in other tables that reference a specific record
 */
if (!function_exists('itm_find_record_usage')) {
    function itm_find_record_usage($conn, $table, $pkColumn, $pkValue, $companyId = 0) {
        if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($pkColumn)) {
            return [];
        }

        $sql = "SELECT kcu.TABLE_NAME AS source_table, kcu.COLUMN_NAME AS source_column
FROM information_schema.KEY_COLUMN_USAGE kcu
WHERE kcu.TABLE_SCHEMA = DATABASE()
  AND kcu.REFERENCED_TABLE_NAME = ?
  AND kcu.REFERENCED_COLUMN_NAME = ?";

        $usage = [];
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'ss', $table, $pkColumn);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
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
        mysqli_stmt_close($stmt);

        return $usage;
    }
}

/**
 * Formats a list of record usages into a descriptive error message
 */
if (!function_exists('itm_format_record_usage_error')) {
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
}

/**
 * Validates if a record can be safely deleted without breaking referential integrity
 */
if (!function_exists('itm_can_delete_record')) {
    function itm_can_delete_record($conn, $table, $pkColumn, $pkValue, $companyId = 0, &$error = '') {
        $error = '';
        $usage = itm_find_record_usage($conn, $table, $pkColumn, $pkValue, $companyId);
        if (!empty($usage)) {
            $error = itm_format_record_usage_error($table, $usage);
            return false;
        }

        return true;
    }
}

/**
 * Validates a CSRF token against the session
 */
if (!function_exists('itm_validate_csrf_token')) {
    function itm_validate_csrf_token($token) {
        $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
        $token = (string)$token;
        return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
    }
}

/**
 * Enforces CSRF protection for POST requests
 */
if (!function_exists('itm_require_post_csrf')) {
    function itm_require_post_csrf() {
        if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }
    }
}

/**
 * Generates or retrieves the current CSRF token from the session
 */
if (!function_exists('itm_get_csrf_token')) {
    function itm_get_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }
}

/**
 * Retrieves a company name by its ID
 */
if (!function_exists('get_company_name')) {
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
}
?>
