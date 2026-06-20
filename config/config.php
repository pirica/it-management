<?php
/**
 * Global Configuration File
 * 
 * Defines database credentials, application settings, and file paths.
 * Handles database connection, session management, audit logging,
 * and global security middleware (authentication & CSRF).
 */

// Why: Many entry files use `require` (not `require_once`); skip re-execution when already loaded.
if (defined('ITM_CONFIG_LOADED')) {
    if (function_exists('itm_resolve_active_company_id')) {
        global $company_id;
        $company_id = itm_resolve_active_company_id(isset($company_id) ? (int)$company_id : 0);
    }
    return;
}
define('ITM_CONFIG_LOADED', true);

/**
 * Why: Local `.env` keeps API keys out of git while remaining optional for Laragon/dev installs.
 */
if (!function_exists('itm_load_dotenv_file')) {
function itm_load_dotenv_file($path)
{
    if (!is_string($path) || $path === '' || !is_readable($path)) {
        return;
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }
        $name = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        if ($name === '') {
            continue;
        }
        if (
            (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"')
            || (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'")
        ) {
            $value = substr($value, 1, -1);
        }
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}
}

itm_load_dotenv_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'itmanagement');
define('DB_NAME', 'itmanagement');

// System Status cache fallback (admin-only module reads before company selection).
if (!defined('ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID')) {
    define('ITM_SYSTEM_STATUS_CACHE_GLOBAL_COMPANY_ID', 1);
}
$itmSystemStatusDisableFallback = getenv('SYSTEM_STATUS_DISABLE_TENANT_FALLBACK');
if (!defined('SYSTEM_STATUS_DISABLE_TENANT_FALLBACK')) {
    define(
        'SYSTEM_STATUS_DISABLE_TENANT_FALLBACK',
        filter_var(
            $itmSystemStatusDisableFallback !== false ? $itmSystemStatusDisableFallback : '0',
            FILTER_VALIDATE_BOOLEAN
        )
    );
}

// Application Settings
define('APP_NAME', 'IT Management System');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'production'); // development or production
define('MAILERLITE_API_KEY', 'YOUR_MAILERLITE_API_KEY_HERE');
define('MAILERLITE_URL', 'https://connect.mailerlite.com/api/emails/single');

// IP2WHOIS hosted-domains lookup (Network Discovery on IP Subnets). Set IP2WHOIS_API_KEY in `.env` or server env.
$itm_ip2whois_api_key = trim((string)getenv('IP2WHOIS_API_KEY'));
if ($itm_ip2whois_api_key === '') {
    $itm_ip2whois_api_key = trim((string)getenv('ITM_IP2WHOIS_API_KEY'));
}
define('IP2WHOIS_API_KEY', $itm_ip2whois_api_key);
define('IP2WHOIS_DOMAINS_URL', 'https://domains.ip2whois.com/domains');

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
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__FILE__)) . '/');
}
define('UPLOAD_PATH', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH . 'images'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('UPLOAD_URL', BASE_URL . 'images/');
define('TICKET_UPLOAD_PATH', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH . 'tickets_photos'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('TICKET_UPLOAD_URL', BASE_URL . 'tickets_photos/');
define('BACKUP_PATH', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH . 'backups'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('DUPLICATE_BACKUP_PATH', dirname(rtrim(ROOT_PATH, '/\\')) . DIRECTORY_SEPARATOR);
define('BACKUP_URL', BASE_URL . 'backups/');
define('FLOOR_PLAN_UPLOAD_PATH', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH . 'floor_plans'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('FLOOR_PLAN_UPLOAD_URL', BASE_URL . 'floor_plans/');
define('FLOOR_PLAN_MAX_FILE_SIZE', 20971520);
define('FLOOR_PLAN_ALLOWED_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'application/pdf',
    'application/acad',
    'application/x-acad',
    'application/autocad',
    'application/x-autocad',
    'image/vnd.dwg',
    'application/dxf',
    'application/x-dxf',
    'application/octet-stream',
]);
define('FLOOR_PLAN_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'dwg', 'dxf', 'dwf', 'dws']);
define('FLOOR_PLAN_CAD_EXTENSIONS', ['dwg', 'dxf', 'dwf', 'dws']);

// Upload Restrictions
define('MAX_FILE_SIZE', 5242880); // 5MB limit
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Load helpers needed before upload directory bootstrap
require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
require_once ROOT_PATH . 'includes/itm_date_format.php';
require_once ROOT_PATH . 'includes/ui_alert_helpers.php';
require_once ROOT_PATH . 'includes/fk_dropdown_helpers.php';
require_once ROOT_PATH . 'includes/employee_dropdown_helpers.php';
require_once ROOT_PATH . 'includes/itm_ui_action_labels.php';

// Ensure required upload and backup directories exist (writable, non-executable over HTTP)
itm_ensure_upload_directory(UPLOAD_PATH, 'upload');
itm_ensure_upload_directory(TICKET_UPLOAD_PATH, 'upload');
itm_ensure_upload_directory(BACKUP_PATH, 'deny_all');
itm_ensure_upload_directory(FLOOR_PLAN_UPLOAD_PATH, 'upload');
itm_ensure_upload_directory(itm_files_storage_root(), 'deny_http');

// Load secondary configuration and library files
require_once ROOT_PATH . 'includes/ui_config.php';
require_once ROOT_PATH . 'includes/itm_api_rate_limit.php';
require_once ROOT_PATH . 'includes/itm_login_attempt_identifier.php';
require_once ROOT_PATH . 'includes/itm_password_reset.php';
require_once ROOT_PATH . 'includes/itm_explorer_paths.php';
require_once ROOT_PATH . 'includes/audit_functions.php';
require_once ROOT_PATH . 'includes/itm_company_module_access.php';
require_once ROOT_PATH . 'includes/itm_email.php';
require_once ROOT_PATH . 'includes/itm_role_module_permissions.php';
require_once ROOT_PATH . 'includes/equipment_poe_helpers.php';

// Establish Database Connection
if (!function_exists('mysqli_connect')) {
    $itmMysqliMissingMessage = 'MySQLi extension is not loaded in this PHP build ('
        . PHP_VERSION
        . '). Use Laragon PHP 7.4 for CLI scripts, for example: '
        . 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe scripts\\<script>.php';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $itmMysqliMissingMessage . PHP_EOL);
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['error' => $itmMysqliMissingMessage]));
}
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}
$conn = false;
$itmSkipDbConn = (defined('ITM_CLI_SCRIPT') && ITM_CLI_SCRIPT && getenv('ITM_SKIP_DB_TESTS') === '1');

if (!$itmSkipDbConn) {
    $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if (!$conn && DB_HOST === 'localhost') {
        // Why: Some local stacks disable the MySQL socket path used by localhost but still accept TCP on 127.0.0.1.
        $conn = @mysqli_connect('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    }

    if (!$conn) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]));
    }
}

if ($conn) {
    mysqli_set_charset($conn, "utf8mb4");
}

// Initialize Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Audit Logging Setup ---
// Capture user context for database-level audit triggers
$itmAuditUserId = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : null;
$itmAuditCompanyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : null;
if ($itmAuditCompanyId !== null && $itmAuditCompanyId <= 0) {
    $itmAuditCompanyId = null;
}
$itmAuditUsername = isset($_SESSION['username']) ? (string)$_SESSION['username'] : '';
$itmAuditEmail = isset($_SESSION['email']) ? (string)$_SESSION['email'] : '';
$itmAuditIp = function_exists('itm_get_client_ip_address') ? itm_get_client_ip_address() : (string)($_SERVER['REMOTE_ADDR'] ?? '');
$itmAuditUserAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

// Set MySQL session variables for auditing
if ($conn) {
    mysqli_query($conn, 'SET @app_employee_id = ' . ($itmAuditUserId === null ? 'NULL' : (string)$itmAuditUserId));
    mysqli_query($conn, 'SET @app_company_id = ' . ($itmAuditCompanyId === null ? 'NULL' : (string)$itmAuditCompanyId));
    mysqli_query($conn, "SET @app_username = '" . mysqli_real_escape_string($conn, $itmAuditUsername) . "'");
    mysqli_query($conn, "SET @app_email = '" . mysqli_real_escape_string($conn, $itmAuditEmail) . "'");
    mysqli_query($conn, "SET @app_ip_address = '" . mysqli_real_escape_string($conn, $itmAuditIp) . "'");
    mysqli_query($conn, "SET @app_user_agent = '" . mysqli_real_escape_string($conn, $itmAuditUserAgent) . "'");
}

// --- Global Access Control ---
$current_file = basename($_SERVER['PHP_SELF']);
// Why: CLI maintenance scripts define ITM_CLI_SCRIPT before config so they can use $conn without a web session.
$itmSkipWebAuth = PHP_SAPI === 'cli' && defined('ITM_CLI_SCRIPT') && ITM_CLI_SCRIPT;

// Why: scripts/api.php?rate_limit=1 validates X-API-Key / api_key itself and must not redirect to login HTML.
if (defined('ITM_API_RATE_LIMIT_PROBE') && ITM_API_RATE_LIMIT_PROBE) {
    $itmSkipWebAuth = true;
}

// Why: Read-only aggregate diagnostics may run without a session when explicitly allowlisted.
if (
    !$itmSkipWebAuth
    && PHP_SAPI !== 'cli'
    && defined('ITM_SCRIPT_NO_AUTH')
    && ITM_SCRIPT_NO_AUTH
) {
    $itmNoAuthScripts = [
        'count_db_tables.php',
    ];
    $itmNoAuthScript = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? ''));

    if (in_array($itmNoAuthScript, $itmNoAuthScripts, true)) {
        $itmSkipWebAuth = true;
    }
}

// Browser: never bypass auth for every ITM_CLI_SCRIPT file — only an explicit allowlist (HTTP QA runner).
if (
    !$itmSkipWebAuth
    && PHP_SAPI !== 'cli'
    && defined('ITM_CLI_SCRIPT')
    && ITM_CLI_SCRIPT
) {
    $itmBrowserMaintenanceAuthAllowlist = [
        'module_browser_qa_runner.php',
        'run_tests.php',
    ];
    $itmMaintenanceScript = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? ''));

    if (in_array($itmMaintenanceScript, $itmBrowserMaintenanceAuthAllowlist, true)) {
        $itmIsLocalhost = (($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1' || ($_SERVER['REMOTE_ADDR'] ?? '') === '::1');
        $itmMaintToken = trim((string)getenv('ITM_MAINTENANCE_TOKEN'));
        $itmProvidedToken = $_GET['token'] ?? $_SERVER['HTTP_X_ITM_MAINTENANCE_TOKEN'] ?? '';

        if ($itmIsLocalhost || ($itmMaintToken !== '' && hash_equals($itmMaintToken, (string)$itmProvidedToken))) {
            $itmSkipWebAuth = true;
        }
    }
}

// Redirect to login if session is missing, excluding auth pages
if (
    !$itmSkipWebAuth
    && !isset($_SESSION['employee_id'])
    && !in_array($current_file, ['login.php', 'register.php', 'forgot-password.php', 'reset-password.php', 'logout.php'], true)
) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Restrict users in read-only mode to the user-config page
$isReadOnlyUserConfig = !empty($_SESSION['read_only_user_config']);
if (
    !$itmSkipWebAuth
    && $isReadOnlyUserConfig
    && !in_array($current_file, ['user-config.php', 'logout.php'], true)
) {
    header('Location: ' . BASE_URL . 'user-config.php');
    exit();
}

// Ensure a company is selected before accessing protected modules
if (
    !$itmSkipWebAuth
    && isset($_SESSION['employee_id'])
    && !isset($_SESSION['company_id'])
    && !$isReadOnlyUserConfig
    && !in_array($current_file, ['index.php', 'logout.php'], true)
) {
    header('Location: ' . BASE_URL . 'index.php');
    exit();
}

$company_id = itm_resolve_active_company_id((int)($_SESSION['company_id'] ?? 0));

if ($company_id > 0 && isset($conn) && $conn instanceof mysqli) {
    $itmIpamHelpersPath = ROOT_PATH . 'includes/ipam_helpers.php';
    if (is_file($itmIpamHelpersPath)) {
        require_once $itmIpamHelpersPath;
        try {
            itm_ipam_ensure_legacy_vlan_subnets_migrated($conn, (int)$company_id);
        } catch (Throwable $itmIpamMigrateError) {
            error_log('IPAM VLAN subnet migration skipped: ' . $itmIpamMigrateError->getMessage());
        }
        if (!isset($_SESSION['itm_ipam_equipment_link_backfill_done'])) {
            try {
                itm_ipam_backfill_equipment_links_from_ip_address($conn, (int)$company_id);
                $_SESSION['itm_ipam_equipment_link_backfill_done'] = 1;
            } catch (Throwable $itmIpamBackfillError) {
                error_log('IPAM equipment link backfill skipped: ' . $itmIpamBackfillError->getMessage());
            }
        }
    }
}

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
                return false;
            }

            // Why: Mutation result values must be captured before audit triggers run, as
            // subsequent logging INSERTs overwrite mysqli_affected_rows() for the session.
            $mutationResult = null;
            $auditAction = (string)($auditMeta['action'] ?? '');
            if ($auditAction !== '') {
                $mutationResult = (int)mysqli_affected_rows($conn);
            }

            if (is_array($auditMeta) && function_exists('itm_log_audit') && function_exists('itm_fetch_audit_record')) {
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

            // Why: For mutations, return the captured affected rows count (which can be 0 for matched but unchanged rows).
            // For SELECT/SHOW/other queries, return the mysqli_result object or true.
            return ($mutationResult !== null) ? $mutationResult : $result;
        } catch (Throwable $t) {
            $errorCode = (int)$t->getCode();
            $errorMessage = (string)$t->getMessage();
            return false;
        }
    }
}

/**
 * Extracts a column name from common MySQL error message text.
 */
if (!function_exists('itm_mysql_error_extract_column')) {
    function itm_mysql_error_extract_column($message) {
        $text = (string)$message;
        if ($text === '') {
            return '';
        }

        $patterns = [
            "/Column '([^']+)' cannot be null/i",
            "/Field '([^']+)' doesn't have a default value/i",
            "/Data too long for column '([^']+)'/i",
            "/Out of range value for column '([^']+)'/i",
            "/Incorrect .+ value: '.+' for column '([^']+)'/i",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return (string)($match[1] ?? '');
            }
        }

        return '';
    }
}

/**
 * Builds a required-field message from a column name.
 */
if (!function_exists('itm_format_required_field_error')) {
    function itm_format_required_field_error($column) {
        $label = function_exists('itm_humanize_field_name')
            ? itm_humanize_field_name($column)
            : ucwords(str_replace('_', ' ', (string)$column));

        if ($label === '') {
            return 'A required field is missing. Please complete all required fields and try again.';
        }

        if (function_exists('itm_field_looks_like_fk_select') && itm_field_looks_like_fk_select($column)) {
            return 'Please select a value for ' . $label . '.';
        }

        return 'Please enter a value for ' . $label . '.';
    }
}

/**
 * Parses MySQL error codes/messages into user-facing text (non-FK specialized cases).
 */
if (!function_exists('itm_parse_mysql_error_message')) {
    function itm_parse_mysql_error_message($errorCode, $message) {
        $code = (int)$errorCode;
        $text = (string)$message;
        $column = itm_mysql_error_extract_column($text);

        if ($code === 1048 || $code === 1364
            || stripos($text, 'cannot be null') !== false
            || stripos($text, "doesn't have a default value") !== false) {
            if ($column !== '') {
                return itm_format_required_field_error($column);
            }
            return 'A required field is missing. Please complete all required fields and try again.';
        }

        if ($code === 1406 || stripos($text, 'Data too long for column') !== false) {
            $label = $column !== '' && function_exists('itm_humanize_field_name')
                ? itm_humanize_field_name($column)
                : 'This field';
            return $label . ' is too long. Shorten the value and try again.';
        }

        if ($code === 1264 || stripos($text, 'Out of range value for column') !== false) {
            $label = $column !== '' && function_exists('itm_humanize_field_name')
                ? itm_humanize_field_name($column)
                : 'This field';
            return $label . ' is out of the allowed range.';
        }

        if ($code === 1292 || stripos($text, 'Incorrect datetime value') !== false
            || stripos($text, 'Incorrect date value') !== false) {
            $label = $column !== '' && function_exists('itm_humanize_field_name')
                ? itm_humanize_field_name($column)
                : 'Date';
            return $label . ' has an invalid date or time. Check the format and try again.';
        }

        return '';
    }
}

/**
 * Translates MySQL error codes into user-friendly messages
 */
if (!function_exists('itm_format_db_constraint_error')) {
    function itm_format_db_constraint_error($errorCode, $fallbackMessage = '') {
        $fallbackText = (string)$fallbackMessage;

        if ($fallbackText !== '' && stripos($fallbackText, 'Database error:') === 0) {
            $fallbackText = trim(substr($fallbackText, strlen('Database error:')));
        }

        switch ((int)$errorCode) {
            case 1451:
                $referenceDetails = '';

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
                $parsed = itm_parse_mysql_error_message($errorCode, $fallbackText);
                if ($parsed !== '') {
                    return $parsed;
                }

                if ($fallbackText !== '' && function_exists('itm_parse_mysql_error_message')) {
                    $parsedFromMessage = itm_parse_mysql_error_message(0, $fallbackText);
                    if ($parsedFromMessage !== '') {
                        return $parsedFromMessage;
                    }
                }

                if ($fallbackText !== '') {
                    if (defined('ROOT_PATH')) {
                        @error_log('[ITM DB] ' . $fallbackText . PHP_EOL, 3, ROOT_PATH . 'error_log.txt');
                    }
                    return 'We could not save your changes. Error: ' . $fallbackText;
                }

                return 'We could not save your changes. Review the required fields and try again.';
        }
    }
}

if (!function_exists('itm_format_db_error')) {
    function itm_format_db_error($errorCode, $fallbackMessage = '') {
        return itm_format_db_constraint_error($errorCode, $fallbackMessage);
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

        $supplementalRelations = itm_find_record_usage_supplemental_relations($table, $pkColumn);
        foreach ($supplementalRelations as $relation) {
            $sourceTable = (string)($relation['table'] ?? '');
            $sourceColumn = (string)($relation['column'] ?? '');
            if (!itm_is_safe_identifier($sourceTable) || !itm_is_safe_identifier($sourceColumn)) {
                continue;
            }

            $dedupeKey = $sourceTable . '.' . $sourceColumn;
            $alreadyListed = false;
            foreach ($usage as $usageRow) {
                if (($usageRow['table'] ?? '') === $sourceTable && ($usageRow['column'] ?? '') === $sourceColumn) {
                    $alreadyListed = true;
                    break;
                }
            }
            if ($alreadyListed) {
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
}

/**
 * Relation-like inbound references when FK metadata is missing from information_schema.
 *
 * @return array<int, array{table:string,column:string}>
 */
if (!function_exists('itm_find_record_usage_supplemental_relations')) {
    function itm_find_record_usage_supplemental_relations($table, $pkColumn) {
        if ($table === 'equipment' && $pkColumn === 'id') {
            return [
                ['table' => 'tickets', 'column' => 'asset_id'],
            ];
        }

        return [];
    }
}

/**
 * Human-readable label for a blocking inbound reference row.
 */
if (!function_exists('itm_format_record_usage_source_label')) {
    function itm_format_record_usage_source_label($sourceTable, $sourceColumn) {
        if ($sourceTable === 'tickets' && $sourceColumn === 'asset_id') {
            return 'ticket Related Asset link(s)';
        }

        return (string)$sourceTable;
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
            $parts[] = itm_format_record_usage_source_label(
                (string)($row['table'] ?? 'unknown'),
                (string)($row['column'] ?? '')
            ) . ' (' . (int)($row['count'] ?? 0) . ')';
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
 * Outbound foreign keys for a table (cached per request).
 *
 * @return array<string, array<string, string>>
 */
if (!function_exists('itm_table_outbound_fk_map')) {
    function itm_table_outbound_fk_map($conn, $tableName) {
        static $cache = [];
        $tableName = (string)$tableName;
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        $cache[$tableName] = [];
        if (!itm_is_safe_identifier($tableName)) {
            return $cache[$tableName];
        }

        $tableEsc = mysqli_real_escape_string($conn, $tableName);
        $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = '{$tableEsc}'
              AND REFERENCED_TABLE_NAME IS NOT NULL";
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $columnName = (string)($row['COLUMN_NAME'] ?? '');
            if ($columnName !== '') {
                $cache[$tableName][$columnName] = $row;
            }
        }

        return $cache[$tableName];
    }
}

/**
 * Lookup one database.sql INSERT row by primary key id (sample FK remap when live anchor is gone).
 *
 * @return array<string, string>|null
 */
if (!function_exists('itm_seed_fk_anchor_row_from_database_sql')) {
    function itm_seed_fk_anchor_row_from_database_sql(string $refTable, int $storedFkId): ?array
    {
        static $parsedByTable = null;
        if ($storedFkId <= 0 || !itm_is_safe_identifier($refTable)) {
            return null;
        }

        if ($parsedByTable === null) {
            $parsedByTable = [];
            $sqlPath = ROOT_PATH . 'database.sql';
            if (is_file($sqlPath)) {
                $sqlBody = @file_get_contents($sqlPath);
                if ($sqlBody !== false && function_exists('itm_parse_database_sql_inserts')) {
                    $parsedByTable = itm_parse_database_sql_inserts($sqlBody);
                }
            }
        }

        foreach ($parsedByTable[$refTable] ?? [] as $rowEntry) {
            $rawColumns = $rowEntry['columns'] ?? [];
            $rawValues = $rowEntry['values'] ?? [];
            $assoc = [];
            foreach ($rawColumns as $index => $columnToken) {
                $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
                if ($columnName === '') {
                    continue;
                }
                $assoc[$columnName] = trim((string)($rawValues[$index] ?? ''), "'\"");
            }
            if ((int)($assoc['id'] ?? 0) === $storedFkId) {
                return $assoc;
            }
        }

        return null;
    }
}

/**
 * Match a tenant FK row using database.sql business keys (name/code/level, etc.).
 */
if (!function_exists('itm_seed_match_tenant_fk_by_business_keys')) {
    function itm_seed_match_tenant_fk_by_business_keys(mysqli $conn, string $refTable, int $companyId, array $anchorRow): int
    {
        if ($companyId <= 0 || !itm_is_safe_identifier($refTable) || $anchorRow === []) {
            return 0;
        }

        $detectFkLib = ROOT_PATH . 'includes/detect_fk_dropdown_ui_risk_lib.php';
        if (is_file($detectFkLib)) {
            require_once $detectFkLib;
        }

        if (!function_exists('itm_fk_table_column_names') || !function_exists('itm_detect_fk_business_key_columns')) {
            return 0;
        }

        $refColumns = itm_fk_table_column_names($conn, $refTable);
        if (!in_array('company_id', $refColumns, true)) {
            return 0;
        }

        $businessKeys = itm_detect_fk_business_key_columns($refTable, $refColumns);
        if ($businessKeys === []) {
            return 0;
        }

        $whereParts = ['company_id = ' . (int)$companyId];
        foreach ($businessKeys as $keyColumn) {
            if (!itm_is_safe_identifier($keyColumn)) {
                continue;
            }
            $keyValue = isset($anchorRow[$keyColumn]) ? (string)$anchorRow[$keyColumn] : '';
            if ($keyValue === '') {
                $whereParts[] = '(`' . $keyColumn . "` = '' OR `" . $keyColumn . '` IS NULL)';
            } else {
                $whereParts[] = '`' . $keyColumn . "` = '" . mysqli_real_escape_string($conn, $keyValue) . "'";
            }
        }

        $matchSql = 'SELECT id FROM `' . $refTable . '` WHERE ' . implode(' AND ', $whereParts) . ' ORDER BY id ASC LIMIT 1';
        $matchRes = mysqli_query($conn, $matchSql);
        $matchRow = ($matchRes) ? mysqli_fetch_assoc($matchRes) : null;

        return is_array($matchRow) ? (int)($matchRow['id'] ?? 0) : 0;
    }
}

/**
 * Resolves a database.sql FK id for sample seed when anchor rows are missing for the tenant.
 * Why: itm_fk_resolve_company_equivalent_id() must keep stale ids for edit UI; seed may substitute.
 */
if (!function_exists('itm_seed_resolve_fk_from_database_sql')) {
    function itm_seed_resolve_fk_from_database_sql(mysqli $conn, array $fkMeta, int $companyId, int $storedFkId): int
    {
        if ($storedFkId <= 0 || $companyId <= 0) {
            return $storedFkId;
        }

        $resolvedFkId = itm_fk_resolve_company_equivalent_id($conn, $fkMeta, $companyId, $storedFkId);
        $refTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
        $refColumn = (string)($fkMeta['REFERENCED_COLUMN_NAME'] ?? 'id');
        if ($refTable === '' || !itm_is_safe_identifier($refTable) || !itm_is_safe_identifier($refColumn)) {
            return $resolvedFkId;
        }

        if (!function_exists('itm_fk_table_column_names')
            || !in_array('company_id', itm_fk_table_column_names($conn, $refTable), true)) {
            return $resolvedFkId;
        }

        $checkSql = 'SELECT `' . $refColumn . '` FROM `' . $refTable . '` WHERE `'
            . $refColumn . '`=' . (int)$resolvedFkId . ' AND company_id=' . (int)$companyId . ' LIMIT 1';
        $checkRes = mysqli_query($conn, $checkSql);
        if ($checkRes && mysqli_num_rows($checkRes) > 0) {
            return $resolvedFkId;
        }

        if (function_exists('itm_seed_fk_anchor_row_from_database_sql')
            && function_exists('itm_seed_match_tenant_fk_by_business_keys')) {
            $anchorRow = itm_seed_fk_anchor_row_from_database_sql($refTable, $storedFkId);
            if (is_array($anchorRow)) {
                $matchedId = itm_seed_match_tenant_fk_by_business_keys($conn, $refTable, $companyId, $anchorRow);
                if ($matchedId > 0) {
                    return $matchedId;
                }
            }
        }

        if (!function_exists('itm_first_tenant_row_id')) {
            return 0;
        }

        $fallbackId = itm_first_tenant_row_id($conn, $refTable, $companyId);
        if ($fallbackId > 0) {
            return $fallbackId;
        }

        return 0;
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
            if ($tableName === 'employee_sidebar_preferences'
                && function_exists('itm_seed_default_employee_sidebar_preferences_for_company')) {
                return itm_seed_default_employee_sidebar_preferences_for_company($conn, $companyId, 1, $error);
            }
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

        if ($sourceHasCompanyRows && !$sourceHasRequestedCompanyRows) {
            $error = 'No sample rows found in database.sql for this company.';
            return 0;
        }

        // Why: Multi-tenant tables must never receive every INSERT row with only company_id rewritten.
        if (!$sourceHasCompanyRows && itm_table_has_column($conn, $tableName, 'company_id')) {
            $error = 'No sample rows found in database.sql for this company.';
            return 0;
        }

        $tableFkMap = itm_table_outbound_fk_map($conn, $tableName);

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

                $valueToken = (string)$rawValues[$index];
                if (isset($tableFkMap[$columnName]) && function_exists('itm_seed_resolve_fk_from_database_sql')) {
                    $rawFkToken = trim($valueToken);
                    if ($rawFkToken !== '' && strtoupper($rawFkToken) !== 'NULL') {
                        $rawFkToken = trim($rawFkToken, "'\"");
                        $storedFkId = (int)$rawFkToken;
                        if ($storedFkId > 0) {
                            $resolvedFkId = itm_seed_resolve_fk_from_database_sql(
                                $conn,
                                $tableFkMap[$columnName],
                                $companyId,
                                $storedFkId
                            );
                            if ($resolvedFkId > 0) {
                                $valueToken = (string)(int)$resolvedFkId;
                            } elseif (function_exists('itm_table_column_is_nullable')
                                && itm_table_column_is_nullable($conn, $tableName, $columnName)) {
                                $valueToken = 'NULL';
                            }
                        }
                    }
                }

                $targetColumns[] = '`' . str_replace('`', '``', $columnName) . '`';
                $targetValues[] = $valueToken;
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
 * Removes catalog rows whose FK parents belong to a different company (legacy sample-data copies).
 */
if (!function_exists('itm_cleanup_catalogs_cross_tenant_fk_rows')) {
    function itm_cleanup_catalogs_cross_tenant_fk_rows($conn) {
        $deleted = 0;
        $statements = [
            'DELETE c FROM `catalogs` c INNER JOIN `equipment_types` et ON et.id = c.equipment_type_id WHERE c.company_id > 0 AND et.company_id > 0 AND c.company_id <> et.company_id',
            'DELETE c FROM `catalogs` c INNER JOIN `manufacturers` m ON m.id = c.manufacturer_id WHERE c.manufacturer_id IS NOT NULL AND c.company_id > 0 AND m.company_id > 0 AND c.company_id <> m.company_id',
            'DELETE c FROM `catalogs` c INNER JOIN `suppliers` s ON s.id = c.supplier_id WHERE c.supplier_id IS NOT NULL AND c.company_id > 0 AND s.company_id > 0 AND c.company_id <> s.company_id',
        ];
        foreach ($statements as $sql) {
            if (itm_run_query($conn, $sql)) {
                $deleted += (int)mysqli_affected_rows($conn);
            }
        }

        return $deleted;
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

        $insertCount = 0;
        foreach (itm_parse_database_sql_inserts((string)@file_get_contents($sqlPath)) as $tableName => $insertRows) {
            unset($insertRows);
            if (!itm_is_safe_identifier($tableName)) {
                continue;
            }

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

            $tableError = '';
            $tableInsertCount = itm_seed_table_from_database_sql($conn, $tableName, $companyId, $tableError);
            if ($tableInsertCount > 0) {
                $insertCount += $tableInsertCount;
                $seedReport['inserted_tables'][] = $tableName . ' (' . $tableInsertCount . ' rows)';
            } elseif ($tableError !== '') {
                $seedReport['failed_tables'][] = $tableName . ' (' . $tableError . ')';
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
    function itm_handle_json_table_import($conn, $tableName, $companyId = 0, ?array $jsonBodyOverride = null, $returnInsteadOfExit = false) {
        if ((string)($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        $rawBody = (string)@file_get_contents('php://input');
        $bodyMentionsImportRows = ($rawBody !== '' && strpos($rawBody, '"import_excel_rows"') !== false);

        if ($jsonBodyOverride === null && strpos($contentType, 'application/json') === false && !$bodyMentionsImportRows) {
            return false;
        }
        if ($jsonBodyOverride !== null && is_array($jsonBodyOverride)) {
            $jsonBody = $jsonBodyOverride;
        } else {
            $jsonBody = json_decode((string)$rawBody, true);
        }
        if (!is_array($jsonBody)) {
            if ($bodyMentionsImportRows) {
                if (!$returnInsteadOfExit) {
                    header('Content-Type: application/json');
                    http_response_code(400);
                }
                $err = ['ok' => false, 'error' => 'Invalid JSON payload.'];
                if ($returnInsteadOfExit) return $err;
                echo json_encode($err);
                exit;
            }
            return false;
        }
        if (!isset($jsonBody['import_excel_rows'])) {
            return false;
        }

        if (!$returnInsteadOfExit) {
            header('Content-Type: application/json');
        }

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
                'null' => strtoupper((string)($column['Null'] ?? '')) === 'YES',
                'default' => $column['Default'] ?? null,
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

        // Why: Sensitive tables must only be imported by administrators.
        $sensitiveTables = ['companies', 'employees', 'employee_roles', 'access_levels', 'role_module_permissions'];
        if (in_array($tableName, $sensitiveTables, true)) {
            if (!itm_is_admin($conn, $_SESSION['employee_id'] ?? 0)) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Unauthorized: Only administrators can import data for this module.']);
                exit;
            }
        }

        $importRows = $jsonBody['import_excel_rows'];
        if (!is_array($importRows) || count($importRows) < 2) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'The uploaded file has no data rows.']);
            exit;
        }

        $headerRow = array_map('trim', array_map('strval', (array)($importRows[0] ?? [])));
        $headerMap = [
            'id' => 'id',
            'id▼' => 'id',
            'id▲' => 'id',
            'duplicate' => 'duplicate', // usually skipped
        ];
        foreach ($columns as $fieldName => $meta) {
            $normalizedField = strtolower(str_replace('_', ' ', $fieldName));
            $headerMap[$normalizedField] = $fieldName;
            $headerMap[strtolower(ucwords($normalizedField))] = $fieldName;
            if (substr($fieldName, -3) === '_id') {
                $stem = strtolower(str_replace('_', ' ', substr($fieldName, 0, -3)));
                $headerMap[$stem] = $fieldName;
                $headerMap[$stem . ' name'] = $fieldName;
                $headerMap[$stem . ' id'] = $fieldName;
            }
            if ($fieldName === 'equipment_type_id') {
                $headerMap['type'] = $fieldName;
            }
        }

        // Module-specific overrides for common industry/legacy headers
        if ($tableName === 'employees') {
            $headerMap['hilton id'] = 'external_id';
            $headerMap['user name'] = 'username';
            $headerMap['position title'] = 'employee_position_id';
            $headerMap['title'] = 'employee_position_id';
            $headerMap['email'] = 'work_email';
            $headerMap['employee status'] = 'raw_status_code';
            $headerMap['status'] = 'raw_status_code';
            $headerMap['on orgchart'] = 'on_orgchart';
            $headerMap['on org chart'] = 'on_orgchart';
            $headerMap['department name'] = 'department_id';
        }

        $fkMap = [];
        $fkSql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                  FROM information_schema.KEY_COLUMN_USAGE
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '" . mysqli_real_escape_string($conn, $tableName) . "'
                    AND REFERENCED_TABLE_NAME IS NOT NULL";
        $fkResult = mysqli_query($conn, $fkSql);
        while ($fkResult && ($fkRow = mysqli_fetch_assoc($fkResult))) {
            $columnName = (string)($fkRow['COLUMN_NAME'] ?? '');
            if ($columnName !== '' && isset($columns[$columnName])) {
                $fkMap[$columnName] = $fkRow;
            }
        }

        $tableColumnsCache = [];
        $tableHasColumn = static function (string $table, string $column) use ($conn, &$tableColumnsCache): bool {
            if ($table === '' || $column === '' || !itm_is_safe_identifier($table) || !itm_is_safe_identifier($column)) {
                return false;
            }
            if (!isset($tableColumnsCache[$table])) {
                $tableColumnsCache[$table] = [];
                $res = mysqli_query($conn, 'SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $field = (string)($row['Field'] ?? '');
                    if ($field !== '') {
                        $tableColumnsCache[$table][$field] = true;
                    }
                }
            }
            return !empty($tableColumnsCache[$table][$column]);
        };

        $importColumnFields = [];
        $idIndex = -1;
        foreach ($headerRow as $idx => $headerValue) {
            $normalizedHeader = strtolower(trim(preg_replace('/\s+/', ' ', (string)$headerValue)));
            $field = $headerMap[$normalizedHeader] ?? null;
            $importColumnFields[] = $field;
            if ($field === 'id') {
                $idIndex = $idx;
            }
        }

        $targetFields = array_keys($columns);
        $insertedRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;
        $failedRows = 0;
        $importErrors = [];

        // We need to resolve departments FIRST if we are in the employees module,
        // because position creation depends on the resolved department_id.
        $deptIndex = -1;
        $geralDeptId = 0;
        if ($tableName === 'employees') {
            foreach ($importColumnFields as $index => $fieldName) {
                if ($fieldName === 'department_id') {
                    $deptIndex = $index;
                    break;
                }
            }

            // Ensure "Geral" department exists for this company
            $geralNameEsc = mysqli_real_escape_string($conn, 'Geral');
            $geralCodeEsc = mysqli_real_escape_string($conn, 'GER');
            $deptCheck = mysqli_query($conn, "SELECT id FROM departments WHERE company_id=" . (int)$companyId . " AND (name='Geral' OR code='GER') LIMIT 1");
            if ($deptCheck && mysqli_num_rows($deptCheck) > 0) {
                $geralDeptId = (int)mysqli_fetch_assoc($deptCheck)['id'];
            } else {
                if (mysqli_query($conn, "INSERT INTO departments (company_id, name, code, active) VALUES (" . (int)$companyId . ", '{$geralNameEsc}', '{$geralCodeEsc}', 1)")) {
                    $geralDeptId = (int)mysqli_insert_id($conn);
                }
            }
        }

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
            $rowValidationError = '';
            $providedFields = [];

            // Employee module: Pre-resolve department if present
            if ($tableName === 'employees') {
                $rowValues['department_id'] = (string)$geralDeptId;
                $deptValue = ($deptIndex >= 0) ? trim((string)($sourceRow[$deptIndex] ?? '')) : '';
                if ($deptValue !== '' && $deptValue !== '—' && strcasecmp($deptValue, 'null') !== 0) {
                    $providedFields[] = 'department_id';
                    $depNameEsc = mysqli_real_escape_string($conn, $deptValue);
                    $depSql = "SELECT id FROM departments WHERE company_id=" . (int)$companyId . " AND name='" . $depNameEsc . "' LIMIT 1";
                    $depRes = mysqli_query($conn, $depSql);
                    if ($depRes && mysqli_num_rows($depRes) === 1) {
                        $rowValues['department_id'] = (string)mysqli_fetch_assoc($depRes)['id'];
                    } else {
                        if (mysqli_query($conn, "INSERT INTO departments (company_id, name, active) VALUES (" . (int)$companyId . ", '" . $depNameEsc . "', 1)")) {
                            $rowValues['department_id'] = (string)mysqli_insert_id($conn);
                        }
                    }
                    if (($rowValues['department_id'] ?? 'NULL') !== 'NULL') {
                        $providedFields[] = 'department_id';
                    }
                }
            }

            foreach ($importColumnFields as $index => $fieldName) {
                if ($fieldName === null || !isset($columns[$fieldName])) {
                    continue;
                }
                if ($fieldName === 'company_id') {
                    continue;
                }

                // If we already handled department_id for employees, skip it here
                if ($tableName === 'employees' && $fieldName === 'department_id') {
                    continue;
                }

                $rawValue = trim((string)($sourceRow[$index] ?? ''));
                if ($rawValue === '' || $rawValue === '—' || strcasecmp($rawValue, 'null') === 0) {
                    continue;
                }

                $columnType = (string)$columns[$fieldName]['type'];

                // Employees module: classification of emails
                if ($tableName === 'employees' && $fieldName === 'work_email') {
                    $personalDomains = [
                        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com',
                        'aol.com', 'msn.com', 'live.com', 'me.com', 'yandex.com', 'mail.ru'
                    ];
                    $domain = strtolower(substr(strrchr((string)$rawValue, "@"), 1));
                    if (in_array($domain, $personalDomains, true)) {
                        $rowValues['personal_email'] = "'" . mysqli_real_escape_string($conn, $rawValue) . "'";
                        $providedFields[] = 'personal_email';
                        continue;
                    }
                }

                if (preg_match('/^tinyint(\(\d+\))?/i', $columnType)) {
                    $normalizedBool = strtolower($rawValue);
                    if (in_array($normalizedBool, ['1', 'active', 'yes', 'true', 'on', '✅'], true)) {
                        $rowValues[$fieldName] = '1';
                    } elseif (in_array($normalizedBool, ['0', 'inactive', 'no', 'false', 'off', '❌'], true)) {
                        $rowValues[$fieldName] = '0';
                    } elseif (is_numeric($rawValue)) {
                        $rowValues[$fieldName] = (string)(int)$rawValue;
                    } elseif ($rowValidationError === '') {
                        $rowValidationError = 'Invalid boolean value for ' . $fieldName;
                    }
                    $providedFields[] = $fieldName;
                    continue;
                }

                if (isset($fkMap[$fieldName])) {
                    $fk = $fkMap[$fieldName];
                    $refTable = (string)($fk['REFERENCED_TABLE_NAME'] ?? '');
                    $refColumn = (string)($fk['REFERENCED_COLUMN_NAME'] ?? 'id');
                    $resolvedFkId = 0;
                    $rawEscaped = mysqli_real_escape_string($conn, $rawValue);

                    if (ctype_digit($rawValue)) {
                        $resolvedFkId = (int)$rawValue;
                    }

                    if ($resolvedFkId <= 0 && $refTable !== '' && itm_is_safe_identifier($refTable) && itm_is_safe_identifier($refColumn)) {
                        $refColumns = function_exists('itm_fk_table_column_names')
                            ? itm_fk_table_column_names($conn, $refTable)
                            : [];
                        $hasCompanyScope = $companyId > 0 && in_array('company_id', $refColumns, true);

                        $candidateColumns = [];
                        if (function_exists('itm_detect_fk_business_key_columns')) {
                            $candidateColumns = itm_detect_fk_business_key_columns($refTable, $refColumns);
                        }
                        if (function_exists('itm_fk_label_column_for_table')) {
                            $candidateColumns[] = itm_fk_label_column_for_table($refColumns);
                        }
                        foreach ([
                            'name', 'title', 'display_name', 'employee_code', 'external_id', 'username',
                            'account_name', 'account_code', 'code', 'description', 'email',
                            'mode_name', 'stage', 'status', 'approver_type_description', 'invitation_code'
                        ] as $commonLabelColumn) {
                            $candidateColumns[] = $commonLabelColumn;
                        }
                        $candidateColumns = array_values(array_unique(array_filter($candidateColumns, function ($column) use ($refColumns) {
                            return is_string($column) && $column !== '' && in_array($column, $refColumns, true);
                        })));

                        foreach ($candidateColumns as $matchColumn) {
                            $matchSql = 'SELECT `' . str_replace('`', '``', $refColumn) . '` AS id FROM `'
                                . str_replace('`', '``', $refTable) . '` WHERE `'
                                . str_replace('`', '``', $matchColumn) . "` = '" . $rawEscaped . "'";
                            if ($hasCompanyScope) {
                                $matchSql .= ' AND company_id=' . $companyId;
                            }
                            $matchSql .= ' ORDER BY `' . str_replace('`', '``', $refColumn) . '` ASC LIMIT 2';
                            $matchRes = mysqli_query($conn, $matchSql);
                            if (!$matchRes) {
                                continue;
                            }
                            $matchCount = mysqli_num_rows($matchRes);
                            if ($matchCount === 1) {
                                $matchRow = mysqli_fetch_assoc($matchRes);
                                $resolvedFkId = (int)($matchRow['id'] ?? 0);
                                break;
                            }
                        }

                        if ($resolvedFkId <= 0
                            && in_array('first_name', $refColumns, true)
                            && in_array('last_name', $refColumns, true)
                            && strpos($rawValue, ' ') !== false) {
                            $nameSql = 'SELECT `' . str_replace('`', '``', $refColumn) . '` AS id FROM `'
                                . str_replace('`', '``', $refTable) . '` WHERE TRIM(CONCAT(COALESCE(`first_name`, \'\'), \' \', COALESCE(`last_name`, \'\'))) = \''
                                . $rawEscaped . '\'';
                            if ($hasCompanyScope) {
                                $nameSql .= ' AND company_id=' . $companyId;
                            }
                            $nameSql .= ' ORDER BY `' . str_replace('`', '``', $refColumn) . '` ASC LIMIT 2';
                            $nameRes = mysqli_query($conn, $nameSql);
                            if ($nameRes && mysqli_num_rows($nameRes) === 1) {
                                $nameRow = mysqli_fetch_assoc($nameRes);
                                $resolvedFkId = (int)($nameRow['id'] ?? 0);
                            }
                        }

                        // Do not map unresolved non-numeric FK values to an arbitrary row.
                    }

                    if ($resolvedFkId > 0) {
                        $rowValues[$fieldName] = (string)$resolvedFkId;

                        // Employees module special case: link position to department
                        if ($tableName === 'employees' && $fieldName === 'employee_position_id' && (int)$resolvedFkId > 0) {
                            $deptId = (string)($rowValues['department_id'] ?? 'NULL');
                            if ($deptId !== 'NULL' && (int)$deptId > 0) {
                                mysqli_query($conn, "UPDATE employee_positions SET department_id=" . (int)$deptId . " WHERE id=" . (int)$resolvedFkId . " AND (department_id IS NULL OR department_id = 0)");
                            }
                        }
                    } else if ($tableName === 'employees' && $fieldName === 'employee_position_id' && !empty($rawValue)) {
                        // Employees module: auto-create position if it doesn't exist
                        $posNameEsc = mysqli_real_escape_string($conn, $rawValue);
                        $deptId = (string)($rowValues['department_id'] ?? 'NULL');
                        if (mysqli_query($conn, "INSERT INTO employee_positions (company_id, name, department_id) VALUES (" . (int)$companyId . ", '" . $posNameEsc . "', " . $deptId . ")")) {
                            $rowValues[$fieldName] = (string)mysqli_insert_id($conn);
                        }
                    } else if ($tableName === 'employees' && $fieldName === 'department_id' && !empty($rawValue)) {
                        // Employees module: auto-create department if it doesn't exist
                        $depNameEsc = mysqli_real_escape_string($conn, $rawValue);
                        if (mysqli_query($conn, "INSERT INTO departments (company_id, name, active) VALUES (" . (int)$companyId . ", '" . $depNameEsc . "', 1)")) {
                            $rowValues[$fieldName] = (string)mysqli_insert_id($conn);
                        }
                    }
                    $providedFields[] = $fieldName;
                    continue;
                }

                if (preg_match('/\b(int|decimal|float|double)\b/i', $columnType)) {
                    if (is_numeric($rawValue)) {
                        $rowValues[$fieldName] = (string)$rawValue;
                    } elseif ($rowValidationError === '') {
                        $rowValidationError = 'Invalid numeric value for ' . $fieldName;
                    }
                    $providedFields[] = $fieldName;
                    continue;
                }

                if (preg_match('/\bdatetime\b|\btimestamp\b|\bdate\b/i', $columnType)) {
                    $dateText = trim((string)$rawValue);
                    if ($dateText === '') {
                        continue;
                    }
                    $normalizedDate = itm_normalize_sql_date_literal($rawValue, $columnType);
                    if ($normalizedDate === null) {
                        if ($rowValidationError === '') {
                            $rowValidationError = 'Invalid date value for ' . $fieldName;
                        }
                    } else {
                        $rowValues[$fieldName] = "'" . mysqli_real_escape_string($conn, $normalizedDate) . "'";
                        $providedFields[] = $fieldName;
                    }
                    continue;
                }

                if (preg_match('/\benum\b/i', $columnType) && preg_match('/^enum\((.+)\)$/i', $columnType, $enumWrapper)) {
                    $enumText = trim((string)$rawValue);
                    if ($enumText !== '') {
                        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $enumWrapper[1], $enumParts);
                        $allowedEnum = $enumParts[1] ?? [];
                        if (!in_array($enumText, $allowedEnum, true)) {
                            if ($rowValidationError === '') {
                                $rowValidationError = 'Invalid enum value for ' . $fieldName;
                            }
                        } else {
                            $rowValues[$fieldName] = "'" . mysqli_real_escape_string($conn, $enumText) . "'";
                            $providedFields[] = $fieldName;
                        }
                    }
                    continue;
                }

                $normalizedDate = itm_normalize_sql_date_literal($rawValue, $columnType);
                if ($normalizedDate !== null) {
                    $rowValues[$fieldName] = "'" . mysqli_real_escape_string($conn, $normalizedDate) . "'";
                    $providedFields[] = $fieldName;
                    continue;
                }

                if (preg_match('/^(?:var)?char\((\d+)\)/i', $columnType, $lenMatch)) {
                    $maxLen = (int)($lenMatch[1] ?? 0);
                    if ($maxLen > 0 && strlen($rawValue) > $maxLen) {
                        $rawValue = substr($rawValue, 0, $maxLen);
                    }
                }

                $rowValues[$fieldName] = "'" . mysqli_real_escape_string($conn, $rawValue) . "'";
                $providedFields[] = $fieldName;
            }

            // Why: UPDATE only touches import columns with resolved non-NULL SQL literals; dedupe tracking list.
            $providedFields = array_values(array_unique(array_filter(
                $providedFields,
                static function ($fieldName) use ($rowValues) {
                    return isset($rowValues[$fieldName]) && $rowValues[$fieldName] !== 'NULL';
                }
            )));

            if ($rowValidationError !== '') {
                $failedRows++;
                if (count($importErrors) < 5) {
                    $importErrors[] = 'row ' . ($rowIndex + 1) . ': ' . $rowValidationError;
                }
                continue;
            }

            if ($hasCompanyColumn) {
                $rowValues['company_id'] = (string)$companyId;
            }

            foreach ($targetFields as $fieldName) {
                if (($rowValues[$fieldName] ?? 'NULL') !== 'NULL') {
                    continue;
                }
                $meta = $columns[$fieldName] ?? null;
                if (!$meta || !empty($meta['null'])) {
                    continue;
                }

                $default = $meta['default'] ?? null;
                $columnType = (string)($meta['type'] ?? '');
                if ($default !== null && $default !== '') {
                    if (preg_match('/\b(int|decimal|float|double)\b/i', $columnType)) {
                        $rowValues[$fieldName] = is_numeric((string)$default) ? (string)$default : '0';
                    } elseif (preg_match('/^tinyint(\(\d+\))?/i', $columnType)) {
                        $rowValues[$fieldName] = in_array(strtolower((string)$default), ['1', 'true'], true) ? '1' : '0';
                    } else {
                        $rowValues[$fieldName] = "'" . mysqli_real_escape_string($conn, (string)$default) . "'";
                    }
                    continue;
                }

                if (preg_match('/\b(int|tinyint|smallint|mediumint|bigint|decimal|float|double)\b/i', $columnType)) {
                    $rowValues[$fieldName] = '0';
                    continue;
                }

                if ($fieldName === 'name' || substr($fieldName, -5) === '_name') {
                    $rowValues[$fieldName] = "'" . mysqli_real_escape_string($conn, 'Import ' . $rowIndex . '-' . date('YmdHis')) . "'";
                }
            }

            $rowId = 0;
            if ($idIndex >= 0) {
                $rowId = (int)trim((string)($sourceRow[$idIndex] ?? ''));
            }

            $existingId = 0;
            if ($rowId > 0) {
                $checkSql = "SELECT id FROM `" . str_replace('`', '``', $tableName) . "` WHERE id = " . $rowId;
                if ($hasCompanyColumn) {
                    $checkSql .= " AND company_id = " . (int)$companyId;
                }
                $checkRes = mysqli_query($conn, $checkSql);
                if ($checkRes && mysqli_num_rows($checkRes) > 0) {
                    $existingId = $rowId;
                }
            }

            if ($existingId > 0) {
                $finalUpdateFields = array_values(array_filter(
                    $targetFields,
                    static function ($fieldName) use ($providedFields, $rowValues) {
                        if ($fieldName === 'company_id') {
                            return false;
                        }
                        return in_array($fieldName, $providedFields, true)
                            && isset($rowValues[$fieldName])
                            && $rowValues[$fieldName] !== 'NULL';
                    }
                ));

                if (empty($finalUpdateFields)) {
                    $skippedRows++;
                    continue;
                }

                $updateParts = [];
                foreach ($finalUpdateFields as $fieldName) {
                    $updateParts[] = '`' . str_replace('`', '``', $fieldName) . '` = ' . ($rowValues[$fieldName] ?? 'NULL');
                }

                $updateSql = "UPDATE `" . str_replace('`', '``', $tableName) . "` SET " . implode(', ', $updateParts)
                    . " WHERE id = " . (int)$existingId
                    . ($hasCompanyColumn ? " AND company_id = " . (int)$companyId : '');
                $dbErrorCode = 0;
                $dbErrorMessage = '';
                if (itm_run_query($conn, $updateSql, $dbErrorCode, $dbErrorMessage) !== false) {
                    $updatedRows++;
                } else {
                    $failedRows++;
                    if (count($importErrors) < 5) {
                        $importErrors[] = 'row ' . ($rowIndex + 1) . ': ' . (string)$dbErrorMessage;
                    }
                }
            } else {
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
                    if (count($importErrors) < 5) {
                        $importErrors[] = 'row ' . ($rowIndex + 1) . ': ' . (string)$dbErrorMessage;
                    }
                }
            }
        }

        $response = [
            'ok' => ($failedRows === 0 && ($insertedRows > 0 || $updatedRows > 0 || $skippedRows > 0)),
            'inserted' => $insertedRows,
            'updated' => $updatedRows,
            'skipped' => $skippedRows,
            'failed' => $failedRows,
        ];
        if (!empty($importErrors)) {
            $response['errors'] = $importErrors;
            if ($insertedRows === 0 && $updatedRows === 0) {
                $response['message'] = $importErrors[0];
            }
        }

        if ($returnInsteadOfExit) {
            return $response;
        }

        if (!$response['ok']) {
            http_response_code(400);
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/**
 * Checks if a user has administrative privileges.
 *
 * Why: Used frequently for access control (e.g. sidebar); uses static cache to minimize DB queries.
 *
 * @param mysqli $conn
 * @param int $employeeId
 * @return bool
 */
if (!function_exists('itm_is_admin')) {
    function itm_is_admin($conn, $employeeId) {
        static $cache = [];
        $employeeId = (int)$employeeId;

        if (!$conn instanceof mysqli || $employeeId <= 0) {
            return false;
        }

        $cacheKey = $employeeId . ':' . spl_object_hash($conn);
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $sql = 'SELECT 1
            FROM `employees` u
            LEFT JOIN `employee_roles` ur ON ur.id = u.role_id
            WHERE u.id = ? AND (LOWER(COALESCE(ur.name, "")) = "admin" OR LOWER(COALESCE(u.username, "")) = "admin")
            LIMIT 1';

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $isAdmin = $res && mysqli_num_rows($res) > 0;
        mysqli_stmt_close($stmt);

        $cache[$cacheKey] = $isAdmin;
        return $isAdmin;
    }
}

// Why: Enforcement needs itm_is_admin() for system-module admin bypass; run after the helper exists.
if (
    !$itmSkipWebAuth
    && ($company_id ?? 0) > 0
    && isset($conn)
    && $conn instanceof mysqli
    && function_exists('itm_enforce_module_access_or_exit')
) {
    itm_enforce_module_access_or_exit($conn);
}

/**
 * Why: Sensitive modules must block non-admins on POST with 403, not rely on redirect alone.
 *
 * @param mysqli $conn
 * @param int $employeeId
 * @return void
 */
if (!function_exists('itm_require_admin')) {
    function itm_require_admin($conn, $employeeId) {
        if (itm_is_admin($conn, (int)$employeeId)) {
            return;
        }

        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            http_response_code(403);
            echo 'Forbidden: administrator access required.';
            exit;
        }

        header('Location: ' . BASE_URL . 'dashboard.php');
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

/**
 * Encrypts a string using AES-256-CBC.
 */
if (!function_exists('itm_encrypt')) {
    function itm_encrypt($data, $key) {
        $cipher = 'aes-256-cbc';
        $iv_len = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($iv_len);
        $encrypted = openssl_encrypt($data, $cipher, $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
}

/**
 * Decrypts a string using AES-256-CBC.
 */
if (!function_exists('itm_decrypt')) {
    function itm_decrypt($data, $key) {
        $cipher = 'aes-256-cbc';
        $data = base64_decode($data);
        $iv_len = openssl_cipher_iv_length($cipher);
        $iv = substr($data, 0, $iv_len);
        $encrypted = substr($data, $iv_len);
        return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
    }
}
?>
