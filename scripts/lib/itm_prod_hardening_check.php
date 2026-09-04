<?php
/**
 * Production go-live hardening probes (shared by check_prod_hardening.php).
 */

declare(strict_types=1);

if (!function_exists('itm_prod_hardening_enforce_failures')) {
    /**
     * When false, unsafe findings print as [WARN] and do not increment failure count.
     */
    function itm_prod_hardening_enforce_failures(bool $forceEnforce = false): bool
    {
        if ($forceEnforce) {
            return true;
        }

        return defined('APP_ENV') && APP_ENV === 'production';
    }
}

if (!function_exists('itm_prod_hardening_seed_admin_usernames')) {
    /**
     * @return string[]
     */
    function itm_prod_hardening_seed_admin_usernames(): array
    {
        return ['Admin', 'Admin2', 'Admin3', 'Admin4', 'Admin5'];
    }
}

if (!function_exists('itm_prod_hardening_seed_demo_usernames')) {
    /**
     * @return string[]
     */
    function itm_prod_hardening_seed_demo_usernames(): array
    {
        return ['demo1', 'demo2', 'demo3', 'demo4', 'demo5'];
    }
}

if (!function_exists('itm_prod_hardening_truthy_env')) {
    function itm_prod_hardening_truthy_env(string $name): bool
    {
        $raw = getenv($name);
        if ($raw === false) {
            return false;
        }

        return in_array(strtolower(trim((string)$raw)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('itm_prod_hardening_is_loopback_ip_or_cidr')) {
    function itm_prod_hardening_is_loopback_ip_or_cidr(string $entry): bool
    {
        $entry = trim($entry);
        if ($entry === '127.0.0.1' || $entry === '::1') {
            return true;
        }

        if (preg_match('/^127\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/', $entry) === 1) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_prod_hardening_resolve_probe_base_url')) {
    function itm_prod_hardening_resolve_probe_base_url(): string
    {
        $override = trim((string)getenv('ITM_PROD_HARDENING_BASE_URL'));
        if ($override !== '') {
            return rtrim($override, '/') . '/';
        }

        if (defined('BASE_URL')) {
            return (string)BASE_URL;
        }

        return '';
    }
}

if (!function_exists('itm_prod_hardening_http_get')) {
    /**
     * @return array{ok:bool,status:int,body:string,error:string}
     */
    function itm_prod_hardening_http_get(string $url, int $timeoutSeconds = 8): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
                'header' => "User-Agent: ITM-Prod-Hardening-Check\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $m)) {
            $status = (int)$m[1];
        }

        if ($body === false) {
            return [
                'ok' => false,
                'status' => $status,
                'body' => '',
                'error' => 'HTTP request failed',
            ];
        }

        return [
            'ok' => true,
            'status' => $status,
            'body' => (string)$body,
            'error' => '',
        ];
    }
}

if (!function_exists('itm_prod_hardening_check_env_dev_flags')) {
    /**
     * @return string[] failure messages
     */
    function itm_prod_hardening_check_env_dev_flags(): array
    {
        $failures = [];

        if (itm_prod_hardening_truthy_env('ITM_SKIP_FORCE_PASSWORD_CHANGE')) {
            $failures[] = 'ITM_SKIP_FORCE_PASSWORD_CHANGE is enabled — first-login password gate is bypassed';
        }

        if (itm_prod_hardening_truthy_env('ITM_DEV')) {
            $failures[] = 'ITM_DEV is enabled in the production profile';
        }

        $appEnv = defined('APP_ENV') ? (string)APP_ENV : trim((string)getenv('APP_ENV'));
        if ($appEnv !== '' && $appEnv !== 'production') {
            $failures[] = 'APP_ENV is not production (' . $appEnv . ') while running production hardening audit';
        }

        return $failures;
    }
}

if (!function_exists('itm_prod_hardening_check_no_auth_env_overrides')) {
    /**
     * @return string[] failure messages
     */
    function itm_prod_hardening_check_no_auth_env_overrides(): array
    {
        $failures = [];

        if (function_exists('itm_script_no_auth_allowed_hosts_from_env')) {
            $hosts = itm_script_no_auth_allowed_hosts_from_env();
            if ($hosts !== []) {
                $failures[] = 'ITM_SCRIPT_NO_AUTH_ALLOWED_HOSTS is set (' . implode(', ', $hosts) . ') — no-auth scripts may skip login from those hosts';
            }
        } else {
            $rawHosts = trim((string)getenv('ITM_SCRIPT_NO_AUTH_ALLOWED_HOSTS'));
            if ($rawHosts !== '') {
                $failures[] = 'ITM_SCRIPT_NO_AUTH_ALLOWED_HOSTS is set — no-auth scripts may skip login from custom hosts';
            }
        }

        $ipEntries = function_exists('itm_script_no_auth_allowed_ips_from_env')
            ? itm_script_no_auth_allowed_ips_from_env()
            : array_values(array_filter(array_map('trim', explode(',', (string)getenv('ITM_SCRIPT_NO_AUTH_ALLOWED_IPS'))), 'strlen'));

        foreach ($ipEntries as $entry) {
            if (!itm_prod_hardening_is_loopback_ip_or_cidr($entry)) {
                $failures[] = 'ITM_SCRIPT_NO_AUTH_ALLOWED_IPS includes non-loopback entry: ' . $entry;
            }
        }

        return $failures;
    }
}

if (!function_exists('itm_prod_hardening_check_error_log_web_root')) {
    /**
     * @return string[] failure messages
     */
    function itm_prod_hardening_check_error_log_web_root(string $rootPath): array
    {
        $failures = [];
        $logPath = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'error_log.txt';
        if (is_file($logPath)) {
            $failures[] = 'error_log.txt exists under the application root (' . $logPath . ') — move logging outside the web docroot';
        }

        return $failures;
    }
}

if (!function_exists('itm_prod_hardening_check_display_errors_setting')) {
    /**
     * @return string[] failure messages
     */
    function itm_prod_hardening_check_display_errors_setting($conn): array
    {
        $failures = [];

        if (!($conn instanceof mysqli)) {
            return ['Database connection required to verify ui_configuration.enable_all_error_reporting'];
        }

        $sql = 'SELECT COUNT(*) AS cnt FROM ui_configuration WHERE enable_all_error_reporting = 1';
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return ['Could not query ui_configuration.enable_all_error_reporting'];
        }

        $row = mysqli_fetch_assoc($res);
        mysqli_free_result($res);
        $count = (int)($row['cnt'] ?? 0);
        if ($count > 0) {
            $failures[] = $count . ' ui_configuration row(s) still have enable_all_error_reporting = 1 (browser error display + web-root error_log.txt)';
        }

        if (ini_get('display_errors') === '1' || filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN)) {
            $failures[] = 'PHP display_errors is enabled for this request';
        }

        return $failures;
    }
}

if (!function_exists('itm_prod_hardening_check_seed_passwords')) {
    /**
     * @return string[] failure messages
     */
    function itm_prod_hardening_check_seed_passwords($conn): array
    {
        $failures = [];

        if (!($conn instanceof mysqli)) {
            return ['Database connection required to verify seed admin/demo passwords'];
        }

        $usernames = array_merge(
            itm_prod_hardening_seed_admin_usernames(),
            itm_prod_hardening_seed_demo_usernames()
        );
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $types = str_repeat('s', count($usernames));
        $sql = 'SELECT username, password FROM employees WHERE deleted_at IS NULL AND username IN (' . $placeholders . ')';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['Could not prepare seed password audit query'];
        }

        mysqli_stmt_bind_param($stmt, $types, ...$usernames);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            mysqli_stmt_close($stmt);
            return ['Could not read employees for seed password audit'];
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $username = (string)($row['username'] ?? '');
            $hash = (string)($row['password'] ?? '');
            if ($username === '' || $hash === '') {
                continue;
            }

            $candidate = in_array($username, itm_prod_hardening_seed_demo_usernames(), true)
                ? $username
                : 'Admin';

            if (password_verify($candidate, $hash)) {
                $failures[] = 'Employee "' . $username . '" still accepts the canonical seed password';
            }
        }
        mysqli_free_result($result);
        mysqli_stmt_close($stmt);

        return $failures;
    }
}

if (!function_exists('itm_prod_hardening_check_bypass_login_http')) {
    /**
     * @return array{failures:string[],skipped:bool,skip_reason:string}
     */
    function itm_prod_hardening_check_bypass_login_http(string $baseUrl): array
    {
        $baseUrl = trim($baseUrl);
        if ($baseUrl === '') {
            return [
                'failures' => [],
                'skipped' => true,
                'skip_reason' => 'Set ITM_PROD_HARDENING_BASE_URL (or BASE_URL) to HTTP-probe scripts/bypass_login.php',
            ];
        }

        $url = rtrim($baseUrl, '/') . '/scripts/bypass_login.php';
        $response = itm_prod_hardening_http_get($url);
        if (!$response['ok']) {
            return [
                'failures' => [],
                'skipped' => true,
                'skip_reason' => 'HTTP probe skipped — could not reach ' . $url . ' (' . $response['error'] . ')',
            ];
        }

        $failures = [];
        $body = $response['body'];
        $status = $response['status'];

        if (preg_match('/sess_[a-z0-9]+/i', $body) === 1) {
            $failures[] = 'scripts/bypass_login.php HTTP response appears to expose a session id';
        }

        if ($status >= 200 && $status < 400 && stripos($body, 'CLI only') === false) {
            $failures[] = 'scripts/bypass_login.php is web-reachable (HTTP ' . $status . ') without the CLI-only guard page';
        } elseif ($status >= 200 && $status < 400) {
            $failures[] = 'scripts/bypass_login.php is web-reachable over HTTP (HTTP ' . $status . ') — block or remove before production';
        }

        return [
            'failures' => $failures,
            'skipped' => false,
            'skip_reason' => '',
        ];
    }
}
