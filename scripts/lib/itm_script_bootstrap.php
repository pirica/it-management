<?php
/**
 * Global entry contract for scripts/* maintenance and regression tools.
 *
 * Why: CLI regressions must use disposable test-user sessions (never the signed-in Admin
 * browser session). Browser access to scripts/* is the default — individual scripts gate
 * CLI-only or Admin-only behaviour. MBQA runner and PHPUnit browser menu may skip web auth
 * on localhost or with ITM_MAINTENANCE_TOKEN.
 */

if (!function_exists('itm_script_is_cli')) {
    function itm_script_is_cli()
    {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }
}

if (!function_exists('itm_script_running_under_scripts_dir')) {
    function itm_script_running_under_scripts_dir()
    {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if ($script === '') {
            return false;
        }

        return preg_match('#/scripts/[^/]+\.php$#', $script) === 1;
    }
}

if (!function_exists('itm_script_browser_no_auth_script_basenames')) {
    /**
     * Read-only browser scripts that skip employee login when ITM_SCRIPT_NO_AUTH is set
     * and the client passes itm_script_browser_no_auth_client_allowed() (loopback, host/IP allowlist, or maintenance token).
     *
     * @return string[]
     */
    function itm_script_browser_no_auth_script_basenames()
    {
        return [
            'count_db_tables.php',
            'test_chatbot.php',
            'openapi.php',
        ];
    }
}

if (!function_exists('itm_script_no_auth_builtin_allowed_hosts')) {
    /**
     * Default HTTP Host values for no-auth browser scripts (merged with ITM_SCRIPT_NO_AUTH_ALLOWED_HOSTS).
     *
     * @return string[]
     */
    function itm_script_no_auth_builtin_allowed_hosts()
    {
        return [
            'localhost',
            '127.0.0.1',
            'myhome.dynip.sapo.pt',
        ];
    }
}

if (!function_exists('itm_script_no_auth_builtin_allowed_ips')) {
    /**
     * Default client IPs for no-auth browser scripts (merged with ITM_SCRIPT_NO_AUTH_ALLOWED_IPS).
     *
     * @return string[]
     */
    function itm_script_no_auth_builtin_allowed_ips()
    {
        return [
            '127.0.0.1',
        ];
    }
}

if (!function_exists('itm_script_no_auth_allowed_hosts_from_env')) {
    /**
     * Extra HTTP Host values from ITM_SCRIPT_NO_AUTH_ALLOWED_HOSTS (comma-separated).
     *
     * @return string[]
     */
    function itm_script_no_auth_allowed_hosts_from_env()
    {
        $raw = trim((string)getenv('ITM_SCRIPT_NO_AUTH_ALLOWED_HOSTS'));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
    }
}

if (!function_exists('itm_script_no_auth_allowed_hosts_resolved')) {
    /**
     * Built-in + env HTTP Host allowlist for ITM_SCRIPT_NO_AUTH browser scripts.
     *
     * @return string[]
     */
    function itm_script_no_auth_allowed_hosts_resolved()
    {
        $hosts = array_merge(
            itm_script_no_auth_builtin_allowed_hosts(),
            itm_script_no_auth_allowed_hosts_from_env()
        );
        $normalized = [];
        foreach ($hosts as $host) {
            $host = strtolower(trim((string)$host));
            if ($host === '') {
                continue;
            }
            if (strpos($host, ':') !== false) {
                $hostParts = explode(':', $host, 2);
                $host = trim((string)($hostParts[0] ?? ''));
            }
            if ($host !== '' && !in_array($host, $normalized, true)) {
                $normalized[] = $host;
            }
        }

        return $normalized;
    }
}

if (!function_exists('itm_script_request_host_for_no_auth_allowlist')) {
    function itm_script_request_host_for_no_auth_allowlist()
    {
        $hostHeader = (string)($_SERVER['HTTP_HOST'] ?? '');
        $hostParts = explode(':', $hostHeader, 2);
        $host = strtolower(trim($hostParts[0]));

        return $host;
    }
}

if (!function_exists('itm_script_request_host_matches_no_auth_allowlist')) {
    function itm_script_request_host_matches_no_auth_allowlist()
    {
        $host = itm_script_request_host_for_no_auth_allowlist();
        if ($host === '') {
            return false;
        }

        foreach (itm_script_no_auth_allowed_hosts_resolved() as $allowedHost) {
            if (hash_equals($allowedHost, $host)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_script_no_auth_allowed_ips_from_env')) {
    /**
     * Extra client IPs/CIDRs from ITM_SCRIPT_NO_AUTH_ALLOWED_IPS (comma-separated).
     *
     * @return string[]
     */
    function itm_script_no_auth_allowed_ips_from_env()
    {
        $raw = trim((string)getenv('ITM_SCRIPT_NO_AUTH_ALLOWED_IPS'));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
    }
}

if (!function_exists('itm_script_no_auth_allowed_ips_resolved')) {
    /**
     * Built-in + env client IP allowlist for ITM_SCRIPT_NO_AUTH browser scripts.
     *
     * @return string[]
     */
    function itm_script_no_auth_allowed_ips_resolved()
    {
        $ips = array_merge(
            itm_script_no_auth_builtin_allowed_ips(),
            itm_script_no_auth_allowed_ips_from_env()
        );
        $normalized = [];
        foreach ($ips as $ip) {
            $ip = trim((string)$ip);
            if ($ip !== '' && !in_array($ip, $normalized, true)) {
                $normalized[] = $ip;
            }
        }

        return $normalized;
    }
}

if (!function_exists('itm_script_client_ip_is_loopback')) {
    function itm_script_client_ip_is_loopback($ip)
    {
        $ip = strtolower(trim((string)$ip));
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        return str_starts_with($ip, '::ffff:127.0.0.1');
    }
}

if (!function_exists('itm_script_client_ip_matches_allowlist_entry')) {
    /**
     * Exact IPv4/IPv6 match or IPv4 CIDR (e.g. 10.0.0.0/8).
     */
    function itm_script_client_ip_matches_allowlist_entry($clientIp, $allowedEntry)
    {
        $clientIp = trim((string)$clientIp);
        $allowedEntry = trim((string)$allowedEntry);
        if ($clientIp === '' || $allowedEntry === '') {
            return false;
        }

        if (strpos($allowedEntry, '/') === false) {
            return hash_equals($allowedEntry, $clientIp);
        }

        if (filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $parts = explode('/', $allowedEntry, 2);
        $subnet = trim((string)($parts[0] ?? ''));
        $maskBits = (int)trim((string)($parts[1] ?? ''));
        if ($subnet === '' || $maskBits < 0 || $maskBits > 32) {
            return false;
        }
        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $clientLong = ip2long($clientIp);
        $subnetLong = ip2long($subnet);
        if ($clientLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = $maskBits === 0 ? 0 : (-1 << (32 - $maskBits));

        return ($clientLong & $maskLong) === ($subnetLong & $maskLong);
    }
}

if (!function_exists('itm_script_client_ip_matches_no_auth_allowlist')) {
    /**
     * @param string[] $allowedEntries
     */
    function itm_script_client_ip_matches_no_auth_allowlist($clientIp, array $allowedEntries)
    {
        foreach ($allowedEntries as $allowedEntry) {
            if (itm_script_client_ip_matches_allowlist_entry($clientIp, $allowedEntry)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_script_maintenance_token_request_is_valid')) {
    /**
     * Reverse-proxy or monitor auth via shared ITM_MAINTENANCE_TOKEN (?token= or X-ITM-Maintenance-Token).
     */
    function itm_script_maintenance_token_request_is_valid()
    {
        $maintToken = trim((string)getenv('ITM_MAINTENANCE_TOKEN'));
        if ($maintToken === '') {
            return false;
        }

        $providedToken = (string)($_GET['token'] ?? $_SERVER['HTTP_X_ITM_MAINTENANCE_TOKEN'] ?? '');

        return $providedToken !== '' && hash_equals($maintToken, $providedToken);
    }
}

if (!function_exists('itm_script_browser_no_auth_client_allowed')) {
    /**
     * True when a no-auth browser script may skip employee login (loopback, host/IP allowlist, or maintenance token).
     */
    function itm_script_browser_no_auth_client_allowed()
    {
        if (itm_script_is_cli()) {
            return true;
        }

        $clientIp = function_exists('itm_get_client_ip_address')
            ? trim((string)itm_get_client_ip_address())
            : trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));

        if (itm_script_client_ip_is_loopback($clientIp)) {
            return true;
        }

        if (itm_script_maintenance_token_request_is_valid()) {
            return true;
        }

        if (itm_script_request_host_matches_no_auth_allowlist()) {
            return true;
        }

        return itm_script_client_ip_matches_no_auth_allowlist(
            $clientIp,
            itm_script_no_auth_allowed_ips_resolved()
        );
    }
}

if (!function_exists('itm_script_browser_skip_web_auth_allowlist')) {
    /**
     * scripts/* that may skip normal web auth in the browser (localhost or ITM_MAINTENANCE_TOKEN).
     *
     * @return string[]
     */
    function itm_script_browser_skip_web_auth_allowlist()
    {
        return [
            'module_browser_qa_runner.php',
            'run_tests.php',
        ];
    }
}

if (!function_exists('itm_script_browser_maintenance_skip_admin_basenames')) {
    /**
     * Subset of the maintenance allowlist that may skip the Admin browser gate when bypass applies.
     * MBQA runner still requires Administrator even on localhost / maintenance token.
     *
     * @return string[]
     */
    function itm_script_browser_maintenance_skip_admin_basenames()
    {
        return [
            'run_tests.php',
        ];
    }
}

if (!function_exists('itm_script_browser_maintenance_skip_web_auth_applies')) {
    /**
     * True when an allowlisted ITM_CLI_SCRIPT may skip employee login in the browser.
     */
    function itm_script_browser_maintenance_skip_web_auth_applies()
    {
        if (itm_script_is_cli()) {
            return false;
        }

        if (!defined('ITM_CLI_SCRIPT') || !ITM_CLI_SCRIPT) {
            return false;
        }

        $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
        if (!in_array($script, itm_script_browser_skip_web_auth_allowlist(), true)) {
            return false;
        }

        $isLocalhost = (($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1'
            || ($_SERVER['REMOTE_ADDR'] ?? '') === '::1');
        $maintToken = trim((string)getenv('ITM_MAINTENANCE_TOKEN'));
        $providedToken = (string)($_GET['token'] ?? $_SERVER['HTTP_X_ITM_MAINTENANCE_TOKEN'] ?? '');

        return $isLocalhost
            || ($maintToken !== '' && hash_equals($maintToken, $providedToken));
    }
}

if (!function_exists('itm_script_browser_maintenance_skip_admin_applies')) {
    /**
     * True when maintenance bypass may skip the Admin browser gate (run_tests.php only).
     */
    function itm_script_browser_maintenance_skip_admin_applies()
    {
        if (!itm_script_browser_maintenance_skip_web_auth_applies()) {
            return false;
        }

        $script = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? ''));

        return in_array($script, itm_script_browser_maintenance_skip_admin_basenames(), true);
    }
}

if (!function_exists('itm_script_browser_maintenance_auth_bypass_applies')) {
    /**
     * @deprecated Use itm_script_browser_maintenance_skip_web_auth_applies()
     */
    function itm_script_browser_maintenance_auth_bypass_applies()
    {
        return itm_script_browser_maintenance_skip_web_auth_applies();
    }
}

if (!function_exists('itm_script_browser_cli_maintenance_allowlist')) {
    /**
     * @deprecated Use itm_script_browser_skip_web_auth_allowlist()
     * @return string[]
     */
    function itm_script_browser_cli_maintenance_allowlist()
    {
        return itm_script_browser_skip_web_auth_allowlist();
    }
}

if (!function_exists('itm_script_browser_isolation_exempt_basenames')) {
    /**
     * scripts/* that keep the signed-in browser session (catalog, API docs, MBQA runners).
     *
     * @return string[]
     */
    function itm_script_browser_isolation_exempt_basenames()
    {
        return [
            'scripts.php',
            'api.php',
            'fast_create_acc.php',
            'fast_create_acc_browser.php',
            'module_browser_qa_runner.php',
            'run_tests.php',
        ];
    }
}

if (!function_exists('itm_script_get_browser_authorization_employee_id')) {
    /**
     * Real signed-in employee id for browser authorization (before test-session swap).
     */
    function itm_script_get_browser_authorization_employee_id()
    {
        if (isset($GLOBALS['itm_script_browser_session_backup']) && is_array($GLOBALS['itm_script_browser_session_backup'])) {
            return (int)($GLOBALS['itm_script_browser_session_backup']['employee_id'] ?? 0);
        }

        return (int)($_SESSION['employee_id'] ?? 0);
    }
}

if (!function_exists('itm_script_sync_audit_session_from_php_session')) {
    function itm_script_sync_audit_session_from_php_session($conn)
    {
        if (!($conn instanceof mysqli)) {
            return;
        }

        $employeeId = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : null;
        if ($employeeId !== null && $employeeId <= 0) {
            $employeeId = null;
        }
        $companyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : null;
        if ($companyId !== null && $companyId <= 0) {
            $companyId = null;
        }
        $username = (string)($_SESSION['username'] ?? '');
        $email = (string)($_SESSION['email'] ?? '');

        mysqli_query($conn, 'SET @app_employee_id = ' . ($employeeId === null ? 'NULL' : (string)$employeeId));
        mysqli_query($conn, 'SET @app_company_id = ' . ($companyId === null ? 'NULL' : (string)$companyId));
        mysqli_query($conn, "SET @app_username = '" . mysqli_real_escape_string($conn, $username) . "'");
        mysqli_query($conn, "SET @app_email = '" . mysqli_real_escape_string($conn, $email) . "'");
    }
}

if (!function_exists('itm_script_resolve_employment_status_id_for_company')) {
    function itm_script_resolve_employment_status_id_for_company($conn, $companyId)
    {
        if (!($conn instanceof mysqli)) {
            return 0;
        }

        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return 0;
        }

        if (!function_exists('itm_script_test_employee_resolve_employment_status_id')) {
            require_once __DIR__ . '/itm_script_test_employee.php';
        }

        return itm_script_test_employee_resolve_employment_status_id($conn, $companyId);
    }
}

if (!function_exists('itm_script_sync_csrf_to_browser_session_backup')) {
    /**
     * Keep pre-swap backup aligned when csrf_token is minted in an isolated scripts/* session.
     */
    function itm_script_sync_csrf_to_browser_session_backup($csrfToken)
    {
        if (!isset($GLOBALS['itm_script_browser_session_backup']) || !is_array($GLOBALS['itm_script_browser_session_backup'])) {
            return;
        }

        $csrfToken = trim((string)$csrfToken);
        if ($csrfToken === '') {
            return;
        }

        $GLOBALS['itm_script_browser_session_backup']['csrf_token'] = $csrfToken;
    }
}

if (!function_exists('itm_script_finish_browser_isolated_session')) {
    function itm_script_finish_browser_isolated_session()
    {
        $conn = isset($GLOBALS['itm_script_browser_isolated_conn']) && $GLOBALS['itm_script_browser_isolated_conn'] instanceof mysqli
            ? $GLOBALS['itm_script_browser_isolated_conn']
            : null;
        $testEmployeeId = (int)($GLOBALS['itm_script_browser_isolated_employee_id'] ?? 0);
        $backup = isset($GLOBALS['itm_script_browser_session_backup']) && is_array($GLOBALS['itm_script_browser_session_backup'])
            ? $GLOBALS['itm_script_browser_session_backup']
            : null;

        if ($testEmployeeId > 0 && $conn instanceof mysqli) {
            if (!function_exists('itm_script_test_employee_delete')) {
                require_once __DIR__ . '/itm_script_test_employee.php';
            }
            itm_script_test_employee_delete($conn, $testEmployeeId);
        }

        if ($backup !== null) {
            // Why: POST forms render csrf_token during the isolated request; merge back so the next request validates.
            $isolatedCsrfToken = trim((string)($_SESSION['csrf_token'] ?? ''));
            if ($isolatedCsrfToken === '' && isset($backup['csrf_token'])) {
                $isolatedCsrfToken = trim((string)$backup['csrf_token']);
            }
            if ($isolatedCsrfToken !== '') {
                $backup['csrf_token'] = $isolatedCsrfToken;
            }
            $_SESSION = $backup;
            if (session_status() === PHP_SESSION_ACTIVE && $conn instanceof mysqli) {
                itm_script_sync_audit_session_from_php_session($conn);
            }
        }

        unset(
            $GLOBALS['itm_script_browser_session_backup'],
            $GLOBALS['itm_script_browser_isolated_employee_id'],
            $GLOBALS['itm_script_browser_isolated_conn'],
            $GLOBALS['itm_script_browser_isolation_shutdown_registered']
        );
    }
}

if (!function_exists('itm_script_begin_browser_isolated_session')) {
    /**
     * Browser scripts/* run under a disposable test employee — never the signed-in Admin cookie.
     * Authorization uses the real session stored in $GLOBALS before swap; shutdown restores it.
     */
    function itm_script_begin_browser_isolated_session($conn, $skipWebAuth = false)
    {
        if ($skipWebAuth || itm_script_is_cli() || !itm_script_running_under_scripts_dir()) {
            return;
        }

        $basename = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if ($basename !== '' && in_array($basename, itm_script_browser_isolation_exempt_basenames(), true)) {
            return;
        }
        if (!empty($_SESSION['itm_script_browser_isolated']) || itm_script_is_disposable_test_session()) {
            return;
        }
        if (!isset($_SESSION['employee_id']) || !($conn instanceof mysqli)) {
            return;
        }

        $backup = $_SESSION;
        $realEmployeeId = (int)($backup['employee_id'] ?? 0);
        $companyId = (int)($backup['company_id'] ?? 0);
        if ($realEmployeeId <= 0 || $companyId <= 0) {
            return;
        }

        if (!function_exists('itm_script_test_employee_create_session_actor')) {
            require_once __DIR__ . '/itm_script_test_employee.php';
        }

        $asAdmin = function_exists('itm_is_admin') && itm_is_admin($conn, $realEmployeeId);
        $scriptSlug = preg_replace('/\.php$/', '', $basename);
        if ($scriptSlug === '') {
            $scriptSlug = 'script';
        }

        $testUser = itm_script_test_employee_create_session_actor($conn, $companyId, [
            'as_admin' => $asAdmin,
            'script_slug' => $scriptSlug,
        ]);
        if (!is_array($testUser) || (int)($testUser['id'] ?? 0) <= 0) {
            return;
        }

        $testEmployeeId = (int)$testUser['id'];

        $GLOBALS['itm_script_browser_session_backup'] = $backup;
        $GLOBALS['itm_script_browser_isolated_employee_id'] = $testEmployeeId;
        $GLOBALS['itm_script_browser_isolated_conn'] = $conn;

        $isolatedSession = [
            'company_id' => $companyId,
            'employee_id' => $testEmployeeId,
            'username' => (string)$testUser['username'],
            'itm_script_browser_isolated' => 1,
        ];
        $backupCsrfToken = trim((string)($backup['csrf_token'] ?? ''));
        if ($backupCsrfToken !== '') {
            $isolatedSession['csrf_token'] = $backupCsrfToken;
        }
        $_SESSION = $isolatedSession;

        if (function_exists('itm_script_test_employee_set_audit_context')) {
            itm_script_test_employee_set_audit_context($conn, $testEmployeeId, (string)$testUser['username'], $companyId);
        } else {
            itm_script_sync_audit_session_from_php_session($conn);
        }

        if (empty($GLOBALS['itm_script_browser_isolation_shutdown_registered'])) {
            $GLOBALS['itm_script_browser_isolation_shutdown_registered'] = true;
            register_shutdown_function('itm_script_finish_browser_isolated_session');
        }
    }
}

if (!function_exists('itm_script_session_or_authorization_is_admin')) {
  /**
   * True when the active disposable session or pre-swap authorization employee is Admin.
   */
  function itm_script_session_or_authorization_is_admin($conn)
  {
      if (!($conn instanceof mysqli)) {
          return false;
      }

      $sessionEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
      if ($sessionEmployeeId > 0 && function_exists('itm_is_admin') && itm_is_admin($conn, $sessionEmployeeId)) {
          return true;
      }

      $authEmployeeId = itm_script_get_browser_authorization_employee_id();

      return $authEmployeeId > 0 && function_exists('itm_is_admin') && itm_is_admin($conn, $authEmployeeId);
  }
}

if (!function_exists('itm_script_require_admin_script_or_exit')) {
    /**
     * Admin gate for scripts/* — accepts disposable test Admin session or pre-swap authorization employee.
     */
    function itm_script_require_admin_script_or_exit($conn, $plainMessage = 'Forbidden: administrator access required.')
    {
        if (itm_script_is_cli()) {
            return;
        }

        if (itm_script_session_or_authorization_is_admin($conn)) {
            return;
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo (string)$plainMessage;
        exit;
    }
}

if (!function_exists('itm_script_require_admin_browser_or_exit')) {
    /**
     * Browser-only Administrator gate — checks the real signed-in user, not the disposable test session.
     */
    function itm_script_require_admin_browser_or_exit($conn)
    {
        if (itm_script_is_cli()) {
            return;
        }

        $employeeId = itm_script_get_browser_authorization_employee_id();
        $mysqliConn = ($conn instanceof mysqli) ? $conn : null;
        if (function_exists('itm_is_admin') && itm_is_admin($mysqliConn, $employeeId)) {
            return;
        }

        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        $dashboardUrl = htmlspecialchars((string)(defined('BASE_URL') ? BASE_URL : '/') . 'dashboard.php', ENT_QUOTES, 'UTF-8');
        $loginUrl = htmlspecialchars((string)(defined('BASE_URL') ? BASE_URL : '/') . 'login.php', ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Access denied</title></head><body>';
        echo '<p>Administrator login required to run this script in the browser.</p>';
        echo '<p><a href="' . $dashboardUrl . '">Return to dashboard</a> · ';
        echo '<a href="' . $loginUrl . '">Sign in</a></p>';
        echo '</body></html>';
        exit;
    }
}

if (!function_exists('itm_script_is_disposable_test_session')) {
    /**
     * True when the active PHP session carries a script/ apitest disposable employee identity.
     */
    function itm_script_is_disposable_test_session()
    {
        if (!isset($_SESSION['employee_id'])) {
            return false;
        }

        $employeeId = (int)$_SESSION['employee_id'];
        $username = strtolower(trim((string)($_SESSION['username'] ?? '')));

        if ($employeeId >= 999901 && $employeeId <= 999999) {
            return true;
        }
        if (strpos($username, 'apitest-user') === 0) {
            return true;
        }
        if (function_exists('itm_script_test_employee_is_disposable') && itm_script_test_employee_is_disposable($username)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_script_reject_disposable_test_web_session_or_exit')) {
    /**
     * Disposable test sessions must not browse the app — clear cookie and return to login.
     */
    function itm_script_reject_disposable_test_web_session_or_exit($currentFile, $skipWebAuth)
    {
        if ($skipWebAuth || itm_script_is_cli()) {
            return;
        }
        if (!itm_script_is_disposable_test_session()) {
            return;
        }
        if (in_array((string)$currentFile, ['logout.php', 'login.php'], true)) {
            return;
        }
        // Why: Disposable script-test sessions may only execute scripts/*.php in the browser.
        if (function_exists('itm_script_running_under_scripts_dir') && itm_script_running_under_scripts_dir()) {
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();

        $loginUrl = defined('BASE_URL') ? (BASE_URL . 'login.php') : '/login.php';
        header('Location: ' . $loginUrl);
        exit();
    }
}

if (!function_exists('itm_script_prepare_cli_entry')) {
    /**
     * CLI-only guard + ITM_CLI_SCRIPT define. Caller must require config.php at file scope next.
     */
    function itm_script_prepare_cli_entry($scriptBasename = '')
    {
        $scriptBasename = (string)$scriptBasename;
        if ($scriptBasename === '') {
            $scriptBasename = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
        }

        if (!itm_script_is_cli()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo ($scriptBasename !== '' ? $scriptBasename : 'script') . " is CLI-only. Run from the repository root:\n";
            echo 'php scripts/' . ($scriptBasename !== '' ? $scriptBasename : '<script>.php') . "\n";
            exit(1);
        }

        if (!defined('ITM_CLI_SCRIPT')) {
            define('ITM_CLI_SCRIPT', true);
        }
    }
}

if (!function_exists('itm_script_with_test_session_context')) {
    /**
     * Run a callback with a disposable test-user session; restores prior $_SESSION after.
     * Never reuses the signed-in Admin browser identity.
     */
    function itm_script_with_test_session_context($companyId, $employeeId, $username, callable $callback)
    {
        // Why: PHPUnit and CLI probes set $_SESSION without session_start(); still swap/restore.
        $backup = $_SESSION;
        $_SESSION = [
            'company_id' => (int)$companyId,
            'employee_id' => (int)$employeeId,
            'username' => (string)$username,
        ];

        try {
            return $callback();
        } finally {
            $_SESSION = $backup;
        }
    }
}

if (!function_exists('itm_script_publish_isolated_http_session')) {
    /**
     * Writes a throwaway PHPSESSID file for HTTP probes (curl Cookie header).
     * Does not mutate the caller's active session.
     */
    function itm_script_publish_isolated_http_session($companyId, $employeeId, $username)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $username = trim((string)$username);
        if ($companyId <= 0 || $employeeId <= 0 || $username === '') {
            return '';
        }

        $sessionName = session_name();
        if ($sessionName === '') {
            $sessionName = 'PHPSESSID';
        }

        $restoreSessionId = '';
        if (session_status() === PHP_SESSION_ACTIVE) {
            $restoreSessionId = session_id();
            session_write_close();
        }

        $probeSessionId = bin2hex(random_bytes(16));
        session_name($sessionName);
        session_id($probeSessionId);
        session_start();
        $_SESSION = [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'username' => $username,
        ];
        session_write_close();

        $savePath = (string)ini_get('session.save_path');
        if ($savePath !== '') {
            $sessionFile = rtrim($savePath, '/\\') . '/sess_' . $probeSessionId;
            if (is_file($sessionFile)) {
                @chmod($sessionFile, 0644);
            }
        }

        if ($restoreSessionId !== '' && !headers_sent()) {
            session_name($sessionName);
            session_id($restoreSessionId);
            session_start();
        }

        return $probeSessionId;
    }
}
