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
define('DB_PASS', 'itmanagement');
define('DB_NAME', 'itmanagement');

// Application Settings
define('APP_NAME', 'IT Management System');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'production'); // development or production
define('MAILERLITE_API_KEY', 'YOUR_MAILERLITE_API_KEY_HERE');
define('MAILERLITE_URL', 'https://connect.mailerlite.com/api/emails/single');

// PHP compatibility polyfills for older hosting environments.
// Why: Some shared hosts still run PHP < 8.0 where native str_* helpers do not exist.
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return strpos((string)$haystack, (string)$needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return strncmp((string)$haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        $needleLength = strlen($needle);
        if ($needleLength > strlen($haystack)) {
            return false;
        }
        return substr($haystack, -$needleLength) === $needle;
    }
}

// --- Path Calculation Logic ---
// Why: Host headers can be attacker-controlled in some deployments, so we prefer a canonical URL.
// Set ITM_APP_URL in the environment (for example: https://itm.example.com/app/) to avoid Host-header poisoning.
$itm_envBaseUrl = trim((string)getenv('ITM_APP_URL'));
$itm_allowedHostsRaw = trim((string)getenv('ITM_ALLOWED_HOSTS'));
$itm_allowedHosts = [];
if ($itm_allowedHostsRaw !== '') {
    $itm_allowedHosts = array_values(array_filter(array_map('trim', explode(',', $itm_allowedHostsRaw)), 'strlen'));
}

// Automatically determines the base URL and filesystem paths regardless of deployment subdirectory.
// Why: Some Apache/Nginx deployments terminate TLS at a reverse proxy and forward requests over HTTP,
// which can leave HTTPS unset in PHP and generate insecure form actions if we do not trust validated proxy hints.
$itm_forwardedProtoRaw = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
$itm_forwardedProtoParts = array_map('trim', explode(',', strtolower($itm_forwardedProtoRaw)));
$itm_forwardedProto = $itm_forwardedProtoParts[0] ?? '';
$itm_httpsFlag = strtolower((string)($_SERVER['HTTPS'] ?? ''));
$itm_requestScheme = strtolower((string)($_SERVER['REQUEST_SCHEME'] ?? ''));
$itm_forwardedSsl = strtolower((string)($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
$itm_serverPort = (string)($_SERVER['SERVER_PORT'] ?? '');

$itm_scheme = 'http';
if (
    $itm_httpsFlag === 'on'
    || $itm_httpsFlag === '1'
    || $itm_requestScheme === 'https'
    || $itm_forwardedProto === 'https'
    || $itm_forwardedSsl === 'on'
    || $itm_serverPort === '443'
) {
    $itm_scheme = 'https';
}
$itm_hostHeader = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$itm_hostParts = explode(':', $itm_hostHeader, 2);
$itm_host = strtolower(trim($itm_hostParts[0]));
if ($itm_host === '' || preg_match('/^[a-z0-9.-]+$/', $itm_host) !== 1) {
    $itm_host = 'localhost';
}

if (!empty($itm_allowedHosts)) {
    $itm_allowedHosts = array_map(static function ($host) {
        $hostValue = strtolower((string)$host);
        if (strpos($hostValue, ':') !== false) {
            $hostSegments = explode(':', $hostValue, 2);
            $hostValue = $hostSegments[0];
        }
        return trim($hostValue);
    }, $itm_allowedHosts);
    if (!in_array($itm_host, $itm_allowedHosts, true)) {
        $itm_host = $itm_allowedHosts[0];
    }
}

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

if ($itm_envBaseUrl !== '') {
    $itm_envParts = @parse_url($itm_envBaseUrl);
    $itm_envScheme = strtolower((string)($itm_envParts['scheme'] ?? ''));
    $itm_envHost = strtolower((string)($itm_envParts['host'] ?? ''));
    if (($itm_envScheme === 'http' || $itm_envScheme === 'https') && $itm_envHost !== '') {
        $itm_envPath = trim((string)($itm_envParts['path'] ?? ''), '/');
        $itm_envPort = isset($itm_envParts['port']) ? ':' . (int)$itm_envParts['port'] : '';
        $itm_baseUrl = $itm_envScheme . '://' . $itm_envHost . $itm_envPort . ($itm_envPath !== '' ? '/' . $itm_envPath . '/' : '/');
    } else {
        $itm_baseUrl = $itm_scheme . '://' . $itm_host . ($itm_basePath !== '' ? $itm_basePath . '/' : '/');
    }
} else {
    $itm_baseUrl = $itm_scheme . '://' . $itm_host . ($itm_basePath !== '' ? $itm_basePath . '/' : '/');
}

define('BASE_URL', $itm_baseUrl);
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
require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
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
$itmAuditCompanyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : null;
if ($itmAuditCompanyId !== null && $itmAuditCompanyId <= 0) {
    $itmAuditCompanyId = null;
}
$itmAuditUsername = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '';
$itmAuditEmail = isset($_SESSION['email']) ? (string)$_SESSION['email'] : '';
$itmAuditIp = function_exists('itm_get_client_ip_address') ? itm_get_client_ip_address() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
$itmAuditUserAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

// Set MySQL session variables for auditing
mysqli_query($conn, 'SET @app_user_id = ' . ($itmAuditUserId === null ? 'NULL' : (string)$itmAuditUserId));
mysqli_query($conn, 'SET @app_company_id = ' . ($itmAuditCompanyId === null ? 'NULL' : (string)$itmAuditCompanyId));
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

$app_name = itm_ui_config_app_name($ui_config);
$favicon_url = itm_ui_config_favicon_url($ui_config);

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

        $auditMeta = null;
        $auditOldValues = null;
        $auditRecordId = 0;
        $auditCompanyId = (int)($_SESSION['company_id'] ?? 0);

        if (function_exists('itm_parse_audit_sql')) {
            $parsedMeta = itm_parse_audit_sql($sql);
            if (is_array($parsedMeta)) {
                $parsedTable = (string)($parsedMeta['table'] ?? '');
                if ($parsedTable !== '') {
                    $auditMeta = $parsedMeta;
                    $auditRecordId = (int)($auditMeta['record_id'] ?? 0);

                    if (
                        in_array((string)($auditMeta['action'] ?? ''), ['UPDATE', 'DELETE'], true)
                        && $auditRecordId > 0
                        && function_exists('itm_fetch_audit_record')
                    ) {
                        $auditOldValues = itm_fetch_audit_record($conn, $parsedTable, $auditRecordId, $auditCompanyId);
                    }
                }
            }
        }

        try {
            $result = mysqli_query($conn, $sql);
            if ($result === false) {
                $errorCode = (int)mysqli_errno($conn);
                $errorMessage = (string)mysqli_error($conn);
            } elseif (is_array($auditMeta) && function_exists('itm_log_audit') && function_exists('itm_fetch_audit_record')) {
                $auditAction = (string)($auditMeta['action'] ?? '');
                $auditTable = (string)($auditMeta['table'] ?? '');
                if ($auditAction !== '' && $auditTable !== '') {
                    if ($auditAction === 'INSERT') {
                        $auditRecordId = (int)mysqli_insert_id($conn);
                    }

                    if ($auditRecordId > 0) {
                        $auditNewValues = null;
                        if ($auditAction !== 'DELETE') {
                            $auditNewValues = itm_fetch_audit_record($conn, $auditTable, $auditRecordId, $auditCompanyId);
                        }
                        itm_log_audit($conn, $auditTable, $auditRecordId, $auditAction, $auditOldValues, $auditNewValues);
                    }
                }
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
                $referenceDetails = '';
                $fallbackText = (string)$fallbackMessage;

                if ($fallbackText !== '') {
                    $childTable = '';
                    $childColumn = '';

                    if (preg_match('/foreign key constraint fails \(`[^`]+`\.`([^`]+)`/i', $fallbackText, $tableMatch)) {
                        $childTable = (string)($tableMatch[1] ?? '');
                    }

                    if (preg_match('/FOREIGN KEY \(`([^`]+)`\)/i', $fallbackText, $columnMatch)) {
                        $childColumn = (string)($columnMatch[1] ?? '');
                    }

                    if ($childTable !== '' && $childColumn !== '') {
                        $referenceDetails = ' Referenced by table "' . $childTable . '" via column "' . $childColumn . '".';
                    } elseif ($childTable !== '') {
                        $referenceDetails = ' Referenced by table "' . $childTable . '".';
                    }
                }

                return 'This record cannot be deleted because other records still reference it.' . $referenceDetails . ' Remove or reassign the related records first.';
            case 1452:
                return 'The selected related record is no longer available for your company. It may have been deleted or moved. Please refresh the page and select a different value.';
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
 * Splits a SQL value list while preserving quoted segments.
 */
if (!function_exists('itm_split_sql_csv')) {
    function itm_split_sql_csv($rawValues) {
        $values = [];
        $buffer = '';
        $inQuote = false;
        $length = strlen((string)$rawValues);

        for ($i = 0; $i < $length; $i++) {
            $char = $rawValues[$i];
            $prev = $i > 0 ? $rawValues[$i - 1] : '';
            if ($char === "'" && $prev !== '\\') {
                $inQuote = !$inQuote;
                $buffer .= $char;
                continue;
            }

            if ($char === ',' && !$inQuote) {
                $values[] = trim($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $values[] = trim($buffer);
        }

        return $values;
    }
}

/**
 * Splits a SQL VALUES block into row tuples while preserving quoted segments.
 */
if (!function_exists('itm_split_sql_value_tuples')) {
    function itm_split_sql_value_tuples($rawValuesBlock) {
        $tuples = [];
        $buffer = '';
        $inQuote = false;
        $depth = 0;
        $length = strlen((string)$rawValuesBlock);

        for ($i = 0; $i < $length; $i++) {
            $char = $rawValuesBlock[$i];
            $prev = $i > 0 ? $rawValuesBlock[$i - 1] : '';
            if ($char === "'" && $prev !== '\\') {
                $inQuote = !$inQuote;
            }

            if (!$inQuote && $char === '(') {
                if ($depth === 0) {
                    $buffer = '';
                } else {
                    $buffer .= $char;
                }
                $depth++;
                continue;
            }

            if (!$inQuote && $char === ')') {
                if ($depth > 0) {
                    $depth--;
                    if ($depth === 0) {
                        $tuples[] = trim($buffer);
                        $buffer = '';
                        continue;
                    }
                }
            }

            if ($depth > 0) {
                $buffer .= $char;
            }
        }

        return $tuples;
    }
}

/**
 * Extracts INSERT statements from database.sql, including multiline VALUES blocks.
 */
if (!function_exists('itm_parse_database_sql_inserts')) {
    function itm_parse_database_sql_inserts($sqlText, $tableFilter = null) {
        $parsed = [];
        $source = (string)$sqlText;
        $tableFilter = $tableFilter !== null ? (string)$tableFilter : null;

        if ($source === '') {
            return $parsed;
        }

        $pattern = '/INSERT\\s+INTO\\s+`([^`]+)`\\s*\\((.*?)\\)\\s*VALUES\\s*(.+?);/is';
        if (!preg_match_all($pattern, $source, $matches, PREG_SET_ORDER)) {
            return $parsed;
        }

        foreach ($matches as $match) {
            $tableName = (string)($match[1] ?? '');
            if ($tableName === '' || !itm_is_safe_identifier($tableName)) {
                continue;
            }
            if ($tableFilter !== null && $tableName !== $tableFilter) {
                continue;
            }

            $rawColumns = array_map('trim', explode(',', (string)($match[2] ?? '')));
            $tuples = itm_split_sql_value_tuples((string)($match[3] ?? ''));
            if (empty($tuples)) {
                continue;
            }

            if (!isset($parsed[$tableName])) {
                $parsed[$tableName] = [];
            }

            foreach ($tuples as $tuple) {
                $rawValues = itm_split_sql_csv($tuple);
                if (count($rawColumns) !== count($rawValues)) {
                    continue;
                }

                $parsed[$tableName][] = [
                    'columns' => $rawColumns,
                    'values' => $rawValues,
                ];
            }
        }

        return $parsed;
    }
}

/**
 * Inserts sample rows for a module table from database.sql when empty.
 */
if (!function_exists('itm_seed_table_from_database_sql')) {
    function itm_seed_table_from_database_sql($conn, $tableName, $companyId, &$error = '') {
        $error = '';
        $tableName = (string)$tableName;
        $companyId = (int)$companyId;

        if (!itm_is_safe_identifier($tableName)) {
            $error = 'Invalid table selected for sample data.';
            return 0;
        }

        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        $sqlPath = ROOT_PATH . 'database.sql';
        if (!is_file($sqlPath)) {
            $error = 'Sample source file database.sql was not found.';
            return 0;
        }

        $sqlBody = @file_get_contents($sqlPath);
        if ($sqlBody === false) {
            $error = 'Unable to read sample source file.';
            return 0;
        }

        $parsedInserts = itm_parse_database_sql_inserts($sqlBody, $tableName);
        $tableRows = $parsedInserts[$tableName] ?? [];
        if (empty($tableRows)) {
            $error = 'No sample rows found in database.sql for this module.';
            return 0;
        }

        $sourceHasCompanyRows = false;
        $sourceHasRequestedCompanyRows = false;
        foreach ($tableRows as $rowEntry) {
            $rawColumns = $rowEntry['columns'] ?? [];
            $rawValues = $rowEntry['values'] ?? [];
            foreach ($rawColumns as $index => $columnToken) {
                $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                if ($columnName !== 'company_id') {
                    continue;
                }
                $sourceHasCompanyRows = true;
                $rawCompanyToken = trim((string)($rawValues[$index] ?? ''));
                if ($rawCompanyToken === '' || strtoupper($rawCompanyToken) === 'NULL') {
                    continue;
                }
                $rawCompanyToken = trim($rawCompanyToken, "'\"");
                if ((int)$rawCompanyToken === $companyId) {
                    $sourceHasRequestedCompanyRows = true;
                }
                break;
            }
        }

        $insertCount = 0;
        foreach ($tableRows as $rowEntry) {
            $rawColumns = $rowEntry['columns'] ?? [];
            $rawValues = $rowEntry['values'] ?? [];

            // Why: When database.sql already includes per-company samples, seed only rows
            // for the active company to avoid global-unique collisions on tenant-specific tables.
            if ($sourceHasCompanyRows && $sourceHasRequestedCompanyRows) {
                $rowCompanyId = null;
                foreach ($rawColumns as $index => $columnToken) {
                    $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                    if ($columnName !== 'company_id') {
                        continue;
                    }
                    $rawCompanyToken = trim((string)($rawValues[$index] ?? ''));
                    if ($rawCompanyToken !== '' && strtoupper($rawCompanyToken) !== 'NULL') {
                        $rawCompanyToken = trim($rawCompanyToken, "'\"");
                        $rowCompanyId = (int)$rawCompanyToken;
                    }
                    break;
                }
                if ($rowCompanyId !== $companyId) {
                    continue;
                }
            }

            $targetColumns = [];
            $targetValues = [];
            foreach ($rawColumns as $index => $columnToken) {
                $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
                    continue;
                }

                if ($columnName === 'id') {
                    continue;
                }

                if ($columnName === 'company_id') {
                    $targetColumns[] = '`company_id`';
                    $targetValues[] = (string)$companyId;
                    continue;
                }

                $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                $targetValues[] = (string)$rawValues[$index];
            }

            if (empty($targetColumns)) {
                continue;
            }

            $insertSql = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . implode(',', $targetColumns) . ') VALUES (' . implode(',', $targetValues) . ')';
            $dbErrorCode = 0;
            $dbErrorMessage = '';
            if (!itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                $error = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
                return $insertCount;
            }
            $insertCount++;
        }

        if ($insertCount === 0) {
            $error = 'No sample rows found in database.sql for this module.';
        }

        return $insertCount;
    }
}

/**
 * Seeds all table samples from database.sql while keeping inserts idempotent.
 */
if (!function_exists('itm_seed_all_tables_from_database_sql')) {
    function itm_seed_all_tables_from_database_sql($conn, $companyId, &$error = '', &$seedReport = []) {
        $error = '';
        $seedReport = [
            'inserted_tables' => [],
            'skipped_tables' => [],
            'failed_tables' => [],
        ];
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            $error = 'A company must be selected before adding sample data.';
            return 0;
        }

        $sqlPath = ROOT_PATH . 'database.sql';
        if (!is_file($sqlPath)) {
            $error = 'Sample source file database.sql was not found.';
            return 0;
        }

        $sqlBody = @file_get_contents($sqlPath);
        if ($sqlBody === false) {
            $error = 'Unable to read sample source file.';
            return 0;
        }

        $insertRowsByTable = itm_parse_database_sql_inserts($sqlBody);

        if (empty($insertRowsByTable)) {
            $error = 'No INSERT sample data found in database.sql.';
            return 0;
        }

        $insertCount = 0;
        foreach ($insertRowsByTable as $tableName => $insertRows) {
            $tableExistsRes = mysqli_query(
                $conn,
                "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tableName) . "'"
            );
            if (!$tableExistsRes || mysqli_num_rows($tableExistsRes) === 0) {
                $seedReport['skipped_tables'][] = $tableName . ' (table does not exist)';
                continue;
            }

            $hasCompanyId = itm_table_has_column($conn, $tableName, 'company_id');
            $rowCount = 0;
            if ($hasCompanyId) {
                $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total_count FROM `' . str_replace('`', '``', $tableName) . '` WHERE company_id = ?');
                if (!$countStmt) {
                    continue;
                }
                mysqli_stmt_bind_param($countStmt, 'i', $companyId);
                mysqli_stmt_execute($countStmt);
                $countResult = mysqli_stmt_get_result($countStmt);
                $countRow = $countResult ? mysqli_fetch_assoc($countResult) : null;
                $rowCount = isset($countRow['total_count']) ? (int)$countRow['total_count'] : 0;
                mysqli_stmt_close($countStmt);
            } else {
                $countRes = mysqli_query($conn, 'SELECT COUNT(*) AS total_count FROM `' . str_replace('`', '``', $tableName) . '`');
                $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
                $rowCount = isset($countRow['total_count']) ? (int)$countRow['total_count'] : 0;
            }

            // Why: Keep the bulk seeding safe to run repeatedly by only touching empty targets.
            if ($rowCount > 0) {
                $seedReport['skipped_tables'][] = $tableName . ' (already has data)';
                continue;
            }

            $tableInsertCount = 0;
            $tableFailed = false;
            foreach ($insertRows as $rowEntry) {
                $rawColumns = $rowEntry['columns'] ?? [];
                $rawValues = $rowEntry['values'] ?? [];

                $sourceRowCompanyId = null;
                foreach ($rawColumns as $index => $columnToken) {
                    $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                    if ($columnName !== 'company_id') {
                        continue;
                    }
                    $rawCompanyToken = trim((string)($rawValues[$index] ?? ''));
                    if ($rawCompanyToken !== '' && strtoupper($rawCompanyToken) !== 'NULL') {
                        $rawCompanyToken = trim($rawCompanyToken, "'\"");
                        $sourceRowCompanyId = (int)$rawCompanyToken;
                    }
                    break;
                }
                if ($sourceRowCompanyId !== null && $sourceRowCompanyId !== $companyId) {
                    continue;
                }

                $targetColumns = [];
                $targetValues = [];
                foreach ($rawColumns as $index => $columnToken) {
                    $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                    if ($columnName === '' || !itm_is_safe_identifier($columnName)) {
                        continue;
                    }
                    if ($columnName === 'id') {
                        continue;
                    }
                    if ($columnName === 'company_id') {
                        $targetColumns[] = '`company_id`';
                        $targetValues[] = (string)$companyId;
                        continue;
                    }
                    $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                    $targetValues[] = (string)$rawValues[$index];
                }

                if (empty($targetColumns)) {
                    continue;
                }

                $insertSql = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . implode(',', $targetColumns) . ') VALUES (' . implode(',', $targetValues) . ')';
                $dbErrorCode = 0;
                $dbErrorMessage = '';
                if (!itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                    $seedReport['failed_tables'][] = $tableName . ' (' . itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage) . ')';
                    $tableFailed = true;
                    break;
                }
                $insertCount++;
                $tableInsertCount++;
            }

            if ($tableFailed) {
                if ($tableInsertCount > 0) {
                    $seedReport['inserted_tables'][] = $tableName . ' (' . $tableInsertCount . ' rows)';
                }
                continue;
            }

            if ($tableInsertCount > 0) {
                $seedReport['inserted_tables'][] = $tableName . ' (' . $tableInsertCount . ' rows)';
            } else {
                $seedReport['skipped_tables'][] = $tableName . ' (no valid sample rows)';
            }
        }

        if ($insertCount === 0) {
            $notImportedTables = array_merge($seedReport['skipped_tables'], $seedReport['failed_tables']);
            $error = 'No sample rows were inserted. Not imported tables: ' . implode(', ', $notImportedTables) . '.';
        } elseif (!empty($seedReport['failed_tables'])) {
            $error = 'Some sample data could not be imported: ' . implode(', ', $seedReport['failed_tables']) . '.';
        }

        return $insertCount;
    }
}

/**
 * Handles JSON import requests from table-tools.js and writes rows directly to a table.
 */
if (!function_exists('itm_handle_json_table_import')) {
    function itm_handle_json_table_import($conn, $tableName, $companyId = 0) {
        if ((string)($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (strpos($contentType, 'application/json') === false) {
            return false;
        }

        $rawBody = file_get_contents('php://input');
        $jsonBody = json_decode((string)$rawBody, true);
        if (!is_array($jsonBody) || !isset($jsonBody['import_excel_rows'])) {
            return false;
        }

        header('Content-Type: application/json');

        $tableName = trim((string)$tableName);
        if ($tableName === '' || !itm_is_safe_identifier($tableName)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid import table.']);
            exit;
        }

        $requestToken = (string)($jsonBody['csrf_token'] ?? '');
        if (!itm_validate_csrf_token($requestToken)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']);
            exit;
        }

        $columns = [];
        $describeSql = 'DESCRIBE `' . str_replace('`', '``', $tableName) . '`';
        $describeResult = mysqli_query($conn, $describeSql);
        while ($describeResult && ($column = mysqli_fetch_assoc($describeResult))) {
            $fieldName = (string)($column['Field'] ?? '');
            if ($fieldName === '' || $fieldName === 'id' || $fieldName === 'created_at' || $fieldName === 'updated_at') {
                continue;
            }

            $columns[$fieldName] = [
                'field' => $fieldName,
                'type' => strtolower((string)($column['Type'] ?? '')),
            ];
        }

        if (empty($columns)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Import columns were not found.']);
            exit;
        }

        $hasCompanyColumn = isset($columns['company_id']);
        $companyId = (int)$companyId;
        if ($hasCompanyColumn && $companyId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Import requires an active company.']);
            exit;
        }

        $importRows = $jsonBody['import_excel_rows'];
        if (!is_array($importRows) || count($importRows) < 2) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'The uploaded file has no data rows.']);
            exit;
        }

        $headerRow = array_map('trim', array_map('strval', (array)($importRows[0] ?? [])));
        $headerMap = [];
        foreach ($columns as $fieldName => $meta) {
            $normalizedField = strtolower(str_replace('_', ' ', $fieldName));
            $headerMap[$normalizedField] = $fieldName;
            $headerMap[strtolower(ucwords($normalizedField))] = $fieldName;
        }

        $importColumnFields = [];
        foreach ($headerRow as $headerValue) {
            $normalizedHeader = strtolower(trim(preg_replace('/\s+/', ' ', (string)$headerValue)));
            $importColumnFields[] = $headerMap[$normalizedHeader] ?? null;
        }

        $targetFields = array_keys($columns);
        $insertedRows = 0;
        $failedRows = 0;

        for ($rowIndex = 1; $rowIndex < count($importRows); $rowIndex++) {
            $sourceRow = (array)$importRows[$rowIndex];
            $hasValues = false;
            foreach ($sourceRow as $cellValue) {
                if (trim((string)$cellValue) !== '') {
                    $hasValues = true;
                    break;
                }
            }
            if (!$hasValues) {
                continue;
            }

            $rowValues = [];
            foreach ($targetFields as $fieldName) {
                $rowValues[$fieldName] = 'NULL';
            }

            foreach ($importColumnFields as $index => $fieldName) {
                if ($fieldName === null || !isset($columns[$fieldName])) {
                    continue;
                }
                if ($fieldName === 'company_id') {
                    continue;
                }

                $rawValue = trim((string)($sourceRow[$index] ?? ''));
                if ($rawValue === '' || $rawValue === '—') {
                    continue;
                }

                $columnType = (string)$columns[$fieldName]['type'];
                if (preg_match('/^tinyint(\(\d+\))?/i', $columnType)) {
                    $normalizedBool = strtolower($rawValue);
                    if (in_array($normalizedBool, ['1', 'active', 'yes', 'true', 'on', '✅'], true)) {
                        $rowValues[$fieldName] = '1';
                    } elseif (in_array($normalizedBool, ['0', 'inactive', 'no', 'false', 'off', '❌'], true)) {
                        $rowValues[$fieldName] = '0';
                    }
                    continue;
                }

                if (preg_match('/\b(int|decimal|float|double)\b/i', $columnType)) {
                    if (is_numeric($rawValue)) {
                        $rowValues[$fieldName] = (string)$rawValue;
                    }
                    continue;
                }

                $rowValues[$fieldName] = "'" . mysqli_real_escape_string($conn, $rawValue) . "'";
            }

            if ($hasCompanyColumn) {
                $rowValues['company_id'] = (string)$companyId;
            }

            $insertFields = [];
            $insertValues = [];
            foreach ($targetFields as $fieldName) {
                $insertFields[] = '`' . str_replace('`', '``', $fieldName) . '`';
                $insertValues[] = $rowValues[$fieldName] ?? 'NULL';
            }

            $insertSql = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . implode(',', $insertFields) . ') VALUES (' . implode(',', $insertValues) . ')';
            $dbErrorCode = 0;
            $dbErrorMessage = '';
            if (itm_run_query($conn, $insertSql, $dbErrorCode, $dbErrorMessage)) {
                $insertedRows++;
            } else {
                $failedRows++;
            }
        }

        echo json_encode([
            'ok' => true,
            'inserted' => $insertedRows,
            'failed' => $failedRows,
        ]);
        exit;
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
