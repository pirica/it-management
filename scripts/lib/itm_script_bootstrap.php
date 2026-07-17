<?php
/**
 * Global entry contract for scripts/* maintenance and regression tools.
 *
 * Why: CLI regressions must use disposable test-user sessions (never the signed-in Admin
 * browser session). Scripts that define ITM_CLI_SCRIPT are CLI-only in the browser unless
 * explicitly allowlisted (MBQA runner, PHPUnit browser menu).
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

if (!function_exists('itm_script_browser_cli_maintenance_allowlist')) {
    /**
     * @return string[]
     */
    function itm_script_browser_cli_maintenance_allowlist()
    {
        return [
            'module_browser_qa_runner.php',
            'run_tests.php',
        ];
    }
}

if (!function_exists('itm_script_enforce_cli_maintenance_entry_or_exit')) {
    /**
     * Block browser execution of scripts/* files that define ITM_CLI_SCRIPT (except allowlist).
     */
    function itm_script_enforce_cli_maintenance_entry_or_exit()
    {
        if (itm_script_is_cli()) {
            return;
        }
        if (!defined('ITM_CLI_SCRIPT') || !ITM_CLI_SCRIPT) {
            return;
        }
        if (!itm_script_running_under_scripts_dir()) {
            return;
        }

        $basename = basename((string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
        if (in_array($basename, itm_script_browser_cli_maintenance_allowlist(), true)) {
            return;
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo $basename . " is CLI-only. Run from the repository root:\n";
        echo "php scripts/" . $basename . "\n";
        exit(1);
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
