<?php
/**
 * Helpers for detecting SQL-quoted form values after failed CRUD saves.
 *
 * Why: Legacy POST handlers stored mysqli-escaped SQL literals in $data and
 * re-rendered them in value="..." attributes (e.g. value="'USA'").
 */

if (!function_exists('itm_form_failed_save_test_probe')) {
    function itm_form_failed_save_test_probe(): string
    {
        return 'ITM_QUOTE_PROBE_USA';
    }
}

if (!function_exists('itm_form_failed_save_test_protected_modules')) {
    /**
     * @return array<int, string>
     */
    function itm_form_failed_save_test_protected_modules(): array
    {
        return [
            'equipment',
            'idfs',
            'idf_links',
            'idf_positions',
            'idf_ports',
            'audit_logs',
            'employees',
            'settings',
            'employee_companies',
            'employee_system_access',
            'cable_colors',
            'ui_configuration',
        ];
    }
}

if (!function_exists('itm_form_failed_save_test_discover_modules')) {
    /**
     * @return array<int, string>
     */
    function itm_form_failed_save_test_discover_modules(string $modulesRoot): array
    {
        $modules = [];
        $pattern = rtrim($modulesRoot, '/\\') . '/*/create.php';
        foreach (glob($pattern) ?: [] as $createPath) {
            $module = basename(dirname($createPath));
            if ($module !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $module)) {
                $modules[] = $module;
            }
        }
        sort($modules);

        return $modules;
    }
}

if (!function_exists('itm_form_failed_save_test_is_protected_module')) {
    function itm_form_failed_save_test_is_protected_module(string $module): bool
    {
        return in_array($module, itm_form_failed_save_test_protected_modules(), true);
    }
}

if (!function_exists('itm_form_failed_save_test_resolve_modules')) {
    /**
     * @param array{module_filter?: string, limit?: int} $options
     * @return array{modules: array<int, string>}
     */
    function itm_form_failed_save_test_resolve_modules(string $modulesRoot, array $options = []): array
    {
        $moduleFilter = isset($options['module_filter']) ? trim((string) $options['module_filter']) : '';
        $limit = isset($options['limit']) ? max(0, (int) $options['limit']) : 0;

        $modules = itm_form_failed_save_test_discover_modules($modulesRoot);
        if ($moduleFilter !== '') {
            $modules = array_values(array_filter($modules, static function ($m) use ($moduleFilter) {
                return $m === $moduleFilter;
            }));
        }
        if ($limit > 0) {
            $modules = array_slice($modules, 0, $limit);
        }

        return ['modules' => $modules];
    }
}

if (!function_exists('itm_form_failed_save_test_scan_file')) {
    /**
     * @return array{status: string, notes: string}
     */
    function itm_form_failed_save_test_scan_file(string $filePath): array
    {
        if (!is_readable($filePath)) {
            return ['status' => 'skip', 'notes' => 'File not readable'];
        }

        $content = (string) file_get_contents($filePath);
        if ($content === '') {
            return ['status' => 'skip', 'notes' => 'Empty file'];
        }

        $storesSqlLiteral = (bool) preg_match(
            '/\$data\[\$name\]\s*=\s*["\']\'["\']\s*\.\s*mysqli_real_escape_string\s*\(/',
            $content
        );
        $hasDisplayHelper = strpos($content, 'cr_form_display_value') !== false
            || strpos($content, 'itm_cr_form_display_value') !== false;
        $usesSqlValues = strpos($content, '$sqlValues') !== false;
        $oldDisplayPattern = (bool) preg_match(
            '/\$displayVal\s*=\s*\(\$val\s*===\s*[\'"]NULL[\'"]\)/',
            $content
        );

        if (!$storesSqlLiteral && !$oldDisplayPattern) {
            return ['status' => 'ok', 'notes' => 'No legacy POST/display pattern in this file'];
        }

        if ($storesSqlLiteral && (!$hasDisplayHelper || !$usesSqlValues)) {
            return [
                'status' => 'fail',
                'notes' => 'Stores SQL literals in $data without $sqlValues + cr_form_display_value',
            ];
        }

        if ($oldDisplayPattern && !$hasDisplayHelper) {
            return [
                'status' => 'fail',
                'notes' => 'Uses legacy $displayVal from $data without cr_form_display_value',
            ];
        }

        return ['status' => 'ok', 'notes' => 'Legacy pattern present but mitigated'];
    }
}

if (!function_exists('itm_form_failed_save_test_static_module')) {
    /**
     * @return array{
     *     module: string,
     *     protected: bool,
     *     status: string,
     *     files: array<int, array{file: string, status: string, notes: string}>
     * }
     */
    function itm_form_failed_save_test_static_module(string $modulesRoot, string $module): array
    {
        $moduleDir = rtrim($modulesRoot, '/\\') . '/' . $module;
        $files = [];
        $worst = 'ok';

        foreach (['index.php', 'create.php', 'edit.php', 'view.php', 'delete.php', 'list_all.php'] as $entry) {
            $path = $moduleDir . '/' . $entry;
            if (!is_file($path)) {
                continue;
            }
            $scan = itm_form_failed_save_test_scan_file($path);
            $files[] = ['file' => $entry, 'status' => $scan['status'], 'notes' => $scan['notes']];
            if ($scan['status'] === 'fail') {
                $worst = 'fail';
            } elseif ($scan['status'] === 'skip' && $worst === 'ok') {
                $worst = 'skip';
            }
        }

        if (!$files) {
            $worst = 'skip';
        }

        return [
            'module' => $module,
            'protected' => in_array($module, itm_form_failed_save_test_protected_modules(), true),
            'status' => $worst,
            'files' => $files,
        ];
    }
}

if (!function_exists('itm_form_failed_save_test_request_base_url')) {
    function itm_form_failed_save_test_request_base_url(): string
    {
        $env = getenv('ITM_TEST_BASE_URL');
        if (is_string($env) && $env !== '') {
            return rtrim($env, '/') . '/';
        }

        if (PHP_SAPI === 'cli') {
            return 'http://localhost/it-management/';
        }

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/scripts/test_form_failed_save_display.php'));
        $appRoot = dirname(dirname($scriptName));
        if ($appRoot === '/' || $appRoot === '\\' || $appRoot === '.') {
            $appRoot = '';
        }

        return $scheme . '://' . $host . $appRoot . '/';
    }
}

if (!function_exists('itm_form_failed_save_test_cookie_header')) {
    function itm_form_failed_save_test_cookie_header(): string
    {
        $env = getenv('ITM_TEST_COOKIE');
        if (is_string($env) && $env !== '') {
            return $env;
        }

        if (PHP_SAPI === 'cli' || empty($_COOKIE)) {
            return '';
        }

        $parts = [];
        foreach ($_COOKIE as $name => $value) {
            if (!is_string($name)) {
                continue;
            }
            $parts[] = $name . '=' . rawurlencode((string) $value);
        }

        return implode('; ', $parts);
    }
}

if (!function_exists('itm_form_failed_save_test_http')) {
    /**
     * @param array<string, string> $postFields
     * @return array{ok: bool, status: int, body: string, error: string}
     */
    function itm_form_failed_save_test_http(string $method, string $url, array $postFields, string $cookieHeader): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'curl_init failed'];
            }

            $headers = ['Accept: text/html'];
            if ($cookieHeader !== '') {
                $headers[] = 'Cookie: ' . $cookieHeader;
            }

            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
            ];

            if (strtoupper($method) === 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = http_build_query($postFields);
            }

            curl_setopt_array($ch, $options);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                return ['ok' => false, 'status' => $status, 'body' => '', 'error' => $error !== '' ? $error : 'curl_exec failed'];
            }

            return ['ok' => true, 'status' => $status, 'body' => (string) $body, 'error' => ''];
        }

        $headerLines = "Accept: text/html\r\n";
        if ($cookieHeader !== '') {
            $headerLines .= 'Cookie: ' . $cookieHeader . "\r\n";
        }
        if (strtoupper($method) === 'POST') {
            $headerLines .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headerLines,
                    'content' => http_build_query($postFields),
                    'ignore_errors' => true,
                    'timeout' => 30,
                ],
            ]);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => $headerLines,
                    'ignore_errors' => true,
                    'timeout' => 30,
                ],
            ]);
        }

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        if ($body === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'HTTP request failed'];
        }

        return ['ok' => true, 'status' => $status, 'body' => (string) $body, 'error' => ''];
    }
}

if (!function_exists('itm_form_failed_save_test_parse_create_form')) {
    /**
     * @return array{fields: array<string, string>, csrf: string, has_form: bool, notes: string}
     */
    function itm_form_failed_save_test_parse_create_form(string $html): array
    {
        $fields = [];
        $csrf = '';
        $hasForm = false;
        $notes = '';

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if (!$dom->loadHTML($html)) {
            return ['fields' => [], 'csrf' => '', 'has_form' => false, 'notes' => 'Unable to parse HTML'];
        }

        $xpath = new DOMXPath($dom);
        $forms = $xpath->query("//form[contains(translate(@method,'POST','post'),'post')]");
        if (!$forms || $forms->length === 0) {
            return ['fields' => [], 'csrf' => '', 'has_form' => false, 'notes' => 'No POST form found'];
        }

        /** @var DOMElement $form */
        $form = $forms->item(0);
        $hasForm = true;

        foreach ($xpath->query('.//input[@name]', $form) as $node) {
            /** @var DOMElement $input */
            $input = $node;
            $name = (string) $input->getAttribute('name');
            if ($name === '' || $input->getAttribute('type') === 'file') {
                continue;
            }
            $type = strtolower((string) $input->getAttribute('type'));
            if ($type === 'checkbox' || $type === 'radio') {
                continue;
            }
            if ($name === 'csrf_token') {
                $csrf = (string) $input->getAttribute('value');
                $fields[$name] = $csrf;
                continue;
            }
            $fields[$name] = (string) $input->getAttribute('value');
        }

        foreach ($xpath->query('.//textarea[@name]', $form) as $node) {
            /** @var DOMElement $textarea */
            $textarea = $node;
            $name = (string) $textarea->getAttribute('name');
            if ($name !== '') {
                $fields[$name] = $textarea->textContent;
            }
        }

        $fkTriggered = false;
        foreach ($xpath->query('.//select[@name]', $form) as $node) {
            /** @var DOMElement $select */
            $select = $node;
            $name = (string) $select->getAttribute('name');
            if ($name === '') {
                continue;
            }

            $hasAddNew = false;
            foreach ($xpath->query('.//option', $select) as $optNode) {
                /** @var DOMElement $opt */
                $opt = $optNode;
                if ((string) $opt->getAttribute('value') === '__add_new__') {
                    $hasAddNew = true;
                    break;
                }
            }

            if ($hasAddNew && !$fkTriggered) {
                $fields[$name] = '__add_new__';
                $fkTriggered = true;
                $notes = 'Forced FK __add_new__ on ' . $name;
            } else {
                $fields[$name] = '';
            }
        }

        if (!$fkTriggered) {
            $notes = 'No FK __add_new__; will use invalid numeric on first int-like field name';
        }

        return ['fields' => $fields, 'csrf' => $csrf, 'has_form' => $hasForm, 'notes' => $notes];
    }
}

if (!function_exists('itm_form_failed_save_test_build_post_payload')) {
    /**
     * @param array<string, string> $fields
     * @return array<string, string>
     */
    function itm_form_failed_save_test_build_post_payload(array $fields, string $probe): array
    {
        $post = $fields;
        $invalidNumericUsed = false;

        foreach ($post as $name => $value) {
            if ($name === 'csrf_token' || $name === 'company_id' || $value === '__add_new__') {
                continue;
            }
            if (preg_match('/_id$/', $name) && $value === '') {
                if (!$invalidNumericUsed) {
                    $post[$name] = 'not-a-valid-int';
                    $invalidNumericUsed = true;
                }
                continue;
            }
            if (preg_match('/^(id|active|company_id)$/', $name)) {
                continue;
            }
            if (preg_match('/(email|phone|url|code|name|city|state|country|address|postal|notes|description|title|label)/i', $name)) {
                $post[$name] = $probe;
            } elseif (preg_match('/int|amount|qty|quantity|count|number/i', $name)) {
                if (!$invalidNumericUsed) {
                    $post[$name] = 'not-a-valid-int';
                    $invalidNumericUsed = true;
                }
            } elseif (!preg_match('/_at$|_date$|password/i', $name)) {
                $post[$name] = $probe;
            }
        }

        return $post;
    }
}

if (!function_exists('itm_form_failed_save_test_response_has_sql_quotes')) {
    function itm_form_failed_save_test_response_has_sql_quotes(string $html, string $probe): bool
    {
        $q = preg_quote($probe, '/');

        if (preg_match('/value=(["\'])\'' . $q . '\'\\1/i', $html)) {
            return true;
        }
        if (preg_match('/value=(["\'])\'[^\']*' . $q . '[^\']*\'\\1/i', $html)) {
            return true;
        }
        if (preg_match('/>[\s]*\'[^\n<]*' . $q . '/i', $html)) {
            return true;
        }
        if (strpos($html, '&#039;' . $probe) !== false || strpos($html, '&apos;' . $probe) !== false) {
            return true;
        }
        if (preg_match('/value=(["\'])[^\n\r]*\'[^\n\r]*' . $q . '/i', $html)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_form_failed_save_test_runtime_module')) {
    /**
     * @return array{
     *     module: string,
     *     protected: bool,
     *     status: string,
     *     http_status: int,
     *     notes: string,
     *     url: string
     * }
     */
    function itm_form_failed_save_test_runtime_module(
        string $baseUrl,
        string $cookieHeader,
        string $module,
        string $probe
    ): array {
        $protected = in_array($module, itm_form_failed_save_test_protected_modules(), true);
        $url = rtrim($baseUrl, '/') . '/modules/' . rawurlencode($module) . '/create.php';

        $get = itm_form_failed_save_test_http('GET', $url, [], $cookieHeader);
        if (!$get['ok']) {
            return [
                'module' => $module,
                'protected' => $protected,
                'status' => 'error',
                'http_status' => $get['status'],
                'notes' => 'GET failed: ' . $get['error'],
                'url' => $url,
            ];
        }

        if ($get['status'] === 302 || $get['status'] === 301) {
            return [
                'module' => $module,
                'protected' => $protected,
                'status' => 'skip',
                'http_status' => $get['status'],
                'notes' => 'Redirected (login required?)',
                'url' => $url,
            ];
        }

        if ($get['status'] !== 200) {
            return [
                'module' => $module,
                'protected' => $protected,
                'status' => 'skip',
                'http_status' => $get['status'],
                'notes' => 'Unexpected GET status',
                'url' => $url,
            ];
        }

        if (stripos($get['body'], 'login') !== false && stripos($get['body'], 'password') !== false && stripos($get['body'], 'form-grid') === false) {
            return [
                'module' => $module,
                'protected' => $protected,
                'status' => 'skip',
                'http_status' => $get['status'],
                'notes' => 'Login page returned — open this script in the browser while logged in',
                'url' => $url,
            ];
        }

        $parsed = itm_form_failed_save_test_parse_create_form($get['body']);
        if (!$parsed['has_form'] || $parsed['csrf'] === '') {
            return [
                'module' => $module,
                'protected' => $protected,
                'status' => 'skip',
                'http_status' => $get['status'],
                'notes' => $parsed['notes'] !== '' ? $parsed['notes'] : 'No create form / CSRF on GET',
                'url' => $url,
            ];
        }

        $postFields = itm_form_failed_save_test_build_post_payload($parsed['fields'], $probe);
        $postFields['csrf_token'] = $parsed['csrf'];

        $post = itm_form_failed_save_test_http('POST', $url, $postFields, $cookieHeader);
        if (!$post['ok']) {
            return [
                'module' => $module,
                'protected' => $protected,
                'status' => 'error',
                'http_status' => $post['status'],
                'notes' => 'POST failed: ' . $post['error'],
                'url' => $url,
            ];
        }

        if ($post['status'] >= 300 && $post['status'] < 400) {
            return [
                'module' => $module,
                'protected' => $protected,
                'status' => 'skip',
                'http_status' => $post['status'],
                'notes' => 'POST redirected (save succeeded or auth redirect) — no failed-save re-render to inspect',
                'url' => $url,
            ];
        }

        if (stripos($post['body'], 'form-grid') === false && stripos($post['body'], 'method="POST"') === false) {
            return [
                'module' => $module,
                'protected' => $protected,
                'status' => 'skip',
                'http_status' => $post['status'],
                'notes' => 'POST response has no form (cannot verify re-display)',
                'url' => $url,
            ];
        }

        if (itm_form_failed_save_test_response_has_sql_quotes($post['body'], $probe)) {
            return [
                'module' => $module,
                'protected' => $protected,
                'status' => 'fail',
                'http_status' => $post['status'],
                'notes' => 'SQL-quoted probe found in re-rendered form. ' . $parsed['notes'],
                'url' => $url,
            ];
        }

        if (strpos($post['body'], $probe) === false) {
            return [
                'module' => $module,
                'protected' => $protected,
                'status' => 'warn',
                'http_status' => $post['status'],
                'notes' => 'Form re-rendered but probe text not found (field names may differ). ' . $parsed['notes'],
                'url' => $url,
            ];
        }

        return [
            'module' => $module,
            'protected' => $protected,
            'status' => 'ok',
            'http_status' => $post['status'],
            'notes' => 'Failed save re-render shows clean probe value. ' . $parsed['notes'],
            'url' => $url,
        ];
    }
}

if (!function_exists('itm_form_failed_save_test_run')) {
    /**
     * @param array{static?: bool, runtime?: bool, module_filter?: string, limit?: int} $options
     * @return array{
     *     probe: string,
     *     static_results: array<int, array<string, mixed>>,
     *     runtime_results: array<int, array<string, mixed>>,
     *     summary: array<string, int>
     * }
     */
    function itm_form_failed_save_test_run(string $modulesRoot, array $options = []): array
    {
        $doStatic = !array_key_exists('static', $options) || !empty($options['static']);
        $doRuntime = !empty($options['runtime']);

        $probe = itm_form_failed_save_test_probe();
        $modules = itm_form_failed_save_test_resolve_modules($modulesRoot, $options)['modules'];

        $staticResults = [];
        $runtimeResults = [];
        $summary = [
            'modules' => count($modules),
            'static_fail' => 0,
            'static_ok' => 0,
            'runtime_fail' => 0,
            'runtime_ok' => 0,
            'runtime_skip' => 0,
            'runtime_warn' => 0,
            'runtime_error' => 0,
        ];

        if ($doStatic) {
            foreach ($modules as $module) {
                $row = itm_form_failed_save_test_static_module($modulesRoot, $module);
                $staticResults[] = $row;
                if ($row['status'] === 'fail') {
                    $summary['static_fail']++;
                } else {
                    $summary['static_ok']++;
                }
            }
        }

        if ($doRuntime) {
            $baseUrl = itm_form_failed_save_test_request_base_url();
            $cookie = itm_form_failed_save_test_cookie_header();
            if ($cookie === '' && PHP_SAPI === 'cli') {
                $runtimeResults[] = [
                    'module' => '(all)',
                    'protected' => false,
                    'status' => 'error',
                    'http_status' => 0,
                    'notes' => 'CLI runtime needs ITM_TEST_COOKIE (copy Cookie header from logged-in browser)',
                    'url' => '',
                ];
                $summary['runtime_error']++;
            } else {
                foreach ($modules as $module) {
                    $row = itm_form_failed_save_test_runtime_module($baseUrl, $cookie, $module, $probe);
                    $runtimeResults[] = $row;
                    if ($row['status'] === 'fail') {
                        $summary['runtime_fail']++;
                    } elseif ($row['status'] === 'ok') {
                        $summary['runtime_ok']++;
                    } elseif ($row['status'] === 'warn') {
                        $summary['runtime_warn']++;
                    } elseif ($row['status'] === 'error') {
                        $summary['runtime_error']++;
                    } else {
                        $summary['runtime_skip']++;
                    }
                }
            }
        }

        return [
            'probe' => $probe,
            'static_results' => $staticResults,
            'runtime_results' => $runtimeResults,
            'summary' => $summary,
        ];
    }
}
