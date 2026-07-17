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

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id FROM employee_statuses WHERE company_id = ? AND name = 'Active' LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (is_array($row) && (int)$row['id'] > 0) {
                return (int)$row['id'];
            }
        }

        $fallback = mysqli_prepare($conn, 'SELECT id FROM employee_statuses WHERE company_id = ? LIMIT 1');
        if (!$fallback) {
            return 0;
        }

        mysqli_stmt_bind_param($fallback, 'i', $companyId);
        mysqli_stmt_execute($fallback);
        $res = mysqli_stmt_get_result($fallback);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($fallback);

        return is_array($row) ? (int)$row['id'] : 0;
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

        if ($backup !== null && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = $backup;
            itm_script_sync_audit_session_from_php_session($conn);
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

        if (!function_exists('itm_script_test_employee_create')) {
            require_once __DIR__ . '/itm_script_test_employee.php';
        }

        $asAdmin = function_exists('itm_is_admin') && itm_is_admin($conn, $realEmployeeId);
        $roleId = 0;
        $roleSql = $asAdmin
            ? "SELECT id FROM employee_roles WHERE company_id = ? AND LOWER(name) = 'admin' LIMIT 1"
            : "SELECT id FROM employee_roles WHERE company_id = ? AND LOWER(name) != 'admin' ORDER BY id ASC LIMIT 1";
        $roleStmt = mysqli_prepare($conn, $roleSql);
        if ($roleStmt) {
            mysqli_stmt_bind_param($roleStmt, 'i', $companyId);
            mysqli_stmt_execute($roleStmt);
            $roleRes = mysqli_stmt_get_result($roleStmt);
            $roleRow = $roleRes ? mysqli_fetch_assoc($roleRes) : null;
            mysqli_stmt_close($roleStmt);
            $roleId = is_array($roleRow) ? (int)$roleRow['id'] : 0;
        }

        $accessLevelId = 0;
        $accessStmt = mysqli_prepare($conn, 'SELECT id FROM access_levels WHERE company_id = ? ORDER BY id ASC LIMIT 1');
        if ($accessStmt) {
            mysqli_stmt_bind_param($accessStmt, 'i', $companyId);
            mysqli_stmt_execute($accessStmt);
            $accessRes = mysqli_stmt_get_result($accessStmt);
            $accessRow = $accessRes ? mysqli_fetch_assoc($accessRes) : null;
            mysqli_stmt_close($accessStmt);
            $accessLevelId = is_array($accessRow) ? (int)$accessRow['id'] : 0;
        }

        $employmentStatusId = itm_script_resolve_employment_status_id_for_company($conn, $companyId);
        $scriptSlug = preg_replace('/\.php$/', '', $basename);
        if ($scriptSlug === '') {
            $scriptSlug = 'script';
        }

        $testUser = itm_script_test_employee_create($conn, $companyId, [
            'script_slug' => $scriptSlug,
            'role_id' => $roleId > 0 ? $roleId : 2,
            'access_level_id' => $accessLevelId > 0 ? $accessLevelId : 2,
            'employment_status_id' => $employmentStatusId > 0 ? $employmentStatusId : 1,
            'last_name' => $asAdmin ? 'TestAdmin' : 'TestEmployee',
        ]);
        if (!is_array($testUser) || (int)($testUser['id'] ?? 0) <= 0) {
            return;
        }

        $testEmployeeId = (int)$testUser['id'];
        $grantStmt = mysqli_prepare(
            $conn,
            'INSERT IGNORE INTO employee_companies (employee_id, company_id, active) VALUES (?, ?, 1)'
        );
        if ($grantStmt) {
            mysqli_stmt_bind_param($grantStmt, 'ii', $testEmployeeId, $companyId);
            mysqli_stmt_execute($grantStmt);
            mysqli_stmt_close($grantStmt);
        }

        $GLOBALS['itm_script_browser_session_backup'] = $backup;
        $GLOBALS['itm_script_browser_isolated_employee_id'] = $testEmployeeId;
        $GLOBALS['itm_script_browser_isolated_conn'] = $conn;

        $_SESSION = [
            'company_id' => $companyId,
            'employee_id' => $testEmployeeId,
            'username' => (string)$testUser['username'],
            'itm_script_browser_isolated' => 1,
        ];

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
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return $callback();
        }

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
