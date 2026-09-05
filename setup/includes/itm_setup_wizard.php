<?php
/**
 * First-run setup wizard helpers (setup/index.php).
 */

declare(strict_types=1);

if (!function_exists('itm_setup_wizard_setup_directory')) {
    function itm_setup_wizard_setup_directory(): string
    {
        return rtrim(itm_setup_wizard_project_root(), '/\\') . DIRECTORY_SEPARATOR . 'setup' . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('itm_setup_wizard_is_complete')) {
    function itm_setup_wizard_is_complete(): bool
    {
        return false;
    }
}

if (!function_exists('itm_setup_wizard_steps')) {
    /**
     * @return array<int, array{slug:string,title:string,subtitle:string}>
     */
    function itm_setup_wizard_steps(): array
    {
        return [
            1 => ['slug' => 'install_path', 'title' => 'Install folder', 'subtitle' => 'Confirm application root and public URL'],
            2 => ['slug' => 'verify_files', 'title' => 'Verify files', 'subtitle' => 'Database bundle, writable paths, and hardening'],
            3 => ['slug' => 'database', 'title' => 'Database', 'subtitle' => 'Connection test and schema import'],
            4 => ['slug' => 'extensions', 'title' => 'PHP extensions', 'subtitle' => 'Runtime and optional PHPUnit extensions'],
            5 => ['slug' => 'settings', 'title' => 'Environment settings', 'subtitle' => 'Development vs production profile'],
            6 => ['slug' => 'admin', 'title' => 'Administrator', 'subtitle' => 'Create or secure the primary admin account'],
            7 => ['slug' => 'sample_data', 'title' => 'Sample data', 'subtitle' => 'Optional demo rows per company'],
            8 => ['slug' => 'finish', 'title' => 'Finish', 'subtitle' => 'Delete setup entry point'],
        ];
    }
}

if (!function_exists('itm_setup_wizard_session_key')) {
    function itm_setup_wizard_session_key(): string
    {
        return 'itm_setup_wizard';
    }
}

if (!function_exists('itm_setup_wizard_state')) {
    /**
     * @return array<string, mixed>
     */
    function itm_setup_wizard_state(): array
    {
        $key = itm_setup_wizard_session_key();
        if (!isset($_SESSION[$key]) || !is_array($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }

        return $_SESSION[$key];
    }
}

if (!function_exists('itm_setup_wizard_state_set')) {
    /**
     * @param array<string, mixed> $patch
     */
    function itm_setup_wizard_state_set(array $patch): void
    {
        $key = itm_setup_wizard_session_key();
        $state = itm_setup_wizard_state();
        foreach ($patch as $name => $value) {
            $state[$name] = $value;
        }
        $_SESSION[$key] = $state;
    }
}

if (!function_exists('itm_setup_wizard_max_step')) {
    function itm_setup_wizard_max_step(): int
    {
        return count(itm_setup_wizard_steps());
    }
}

if (!function_exists('itm_setup_wizard_current_step')) {
    function itm_setup_wizard_current_step(): int
    {
        $state = itm_setup_wizard_state();
        $step = (int)($state['current_step'] ?? 1);
        if ($step < 1) {
            $step = 1;
        }
        if ($step > itm_setup_wizard_max_step()) {
            $step = itm_setup_wizard_max_step();
        }

        return itm_setup_wizard_clamp_step($step);
    }
}

if (!function_exists('itm_setup_wizard_set_step')) {
    function itm_setup_wizard_set_step(int $step): void
    {
        $step = itm_setup_wizard_clamp_step($step);
        itm_setup_wizard_state_set(['current_step' => $step]);
    }
}

if (!function_exists('itm_setup_wizard_mark_step_done')) {
    function itm_setup_wizard_mark_step_done(int $step): void
    {
        $state = itm_setup_wizard_state();
        $done = isset($state['completed_steps']) && is_array($state['completed_steps']) ? $state['completed_steps'] : [];
        $done[$step] = true;
        itm_setup_wizard_state_set(['completed_steps' => $done]);
    }
}

if (!function_exists('itm_setup_wizard_step_done')) {
    function itm_setup_wizard_step_done(int $step): bool
    {
        $state = itm_setup_wizard_state();
        $done = $state['completed_steps'] ?? [];

        return !empty($done[$step]);
    }
}

if (!function_exists('itm_setup_wizard_reset_install_progress')) {
    /**
     * Clear step completion and database import markers when starting a fresh install path.
     */
    function itm_setup_wizard_reset_install_progress(): void
    {
        itm_setup_wizard_state_set([
            'completed_steps' => [],
            'db' => null,
            'db_probe' => null,
            'db_import' => null,
            'table_count' => null,
            'trigger_count' => null,
        ]);
    }
}

if (!function_exists('itm_setup_wizard_first_incomplete_step')) {
    function itm_setup_wizard_first_incomplete_step(): int
    {
        $max = itm_setup_wizard_max_step();
        for ($step = 1; $step <= $max; $step++) {
            if (!itm_setup_wizard_step_done($step)) {
                return $step;
            }
        }

        return $max;
    }
}

if (!function_exists('itm_setup_wizard_clamp_step')) {
    function itm_setup_wizard_clamp_step(int $requestedStep): int
    {
        $requestedStep = max(1, min(itm_setup_wizard_max_step(), $requestedStep));
        $firstIncomplete = itm_setup_wizard_first_incomplete_step();

        return min($requestedStep, $firstIncomplete);
    }
}

if (!function_exists('itm_setup_wizard_import_bundle_satisfied')) {
    /**
     * Step 3 must have imported the canonical db/ bundle; live schema counts must still match.
     *
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_import_bundle_satisfied(?mysqli $conn = null): array
    {
        if (!itm_setup_wizard_step_done(3)) {
            return [
                'ok' => false,
                'message' => 'Complete step 3 — test connection, then Import database bundle — before continuing.',
            ];
        }

        $state = itm_setup_wizard_state();
        $expectedTables = itm_setup_wizard_expected_table_count();
        $expectedTriggers = itm_setup_wizard_expected_trigger_count();
        $sessionTables = (int)($state['table_count'] ?? 0);
        $sessionTriggers = (int)($state['trigger_count'] ?? 0);

        if ($expectedTables > 0 && $sessionTables < $expectedTables) {
            return [
                'ok' => false,
                'message' => 'Step 3 import is incomplete in this wizard session (tables '
                    . $sessionTables . '/' . $expectedTables . '). Re-run Import database bundle on step 3.',
            ];
        }
        if ($expectedTriggers > 0 && $sessionTriggers < $expectedTriggers) {
            return [
                'ok' => false,
                'message' => 'Step 3 import is incomplete in this wizard session (triggers '
                    . $sessionTriggers . '/' . $expectedTriggers . '). Re-run Import database bundle on step 3.',
            ];
        }

        $credentials = itm_setup_wizard_session_db_credentials();
        if ($credentials === null) {
            return [
                'ok' => false,
                'message' => 'Database settings are missing from the wizard session — return to step 3.',
            ];
        }

        $ownsConnection = false;
        if (!($conn instanceof mysqli)) {
            $conn = itm_setup_wizard_connect_database($credentials);
            $ownsConnection = $conn instanceof mysqli;
        }
        if (!($conn instanceof mysqli)) {
            return [
                'ok' => false,
                'message' => 'Cannot connect to the database from step 3 settings — verify credentials and import again.',
            ];
        }

        $schema = $credentials['name'];
        $liveTables = itm_setup_wizard_count_tables($conn, $schema);
        $liveTriggers = itm_setup_wizard_count_triggers($conn, $schema);
        if ($ownsConnection) {
            mysqli_close($conn);
        }

        if ($expectedTables > 0 && $liveTables < $expectedTables) {
            return [
                'ok' => false,
                'message' => 'Database does not match a fresh bundle import (tables '
                    . $liveTables . '/' . $expectedTables . '). On step 3, confirm replacement and Import database bundle to wipe existing data.',
            ];
        }
        if ($expectedTriggers > 0 && $liveTriggers < $expectedTriggers) {
            return [
                'ok' => false,
                'message' => 'Database does not match a fresh bundle import (triggers '
                    . $liveTriggers . '/' . $expectedTriggers . '). On step 3, confirm replacement and Import database bundle to wipe existing data.',
            ];
        }

        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('itm_setup_wizard_require_import_bundle_or_redirect')) {
    /**
     * @return bool true when satisfied; otherwise sets flash, redirects to step 3, and exits.
     */
    function itm_setup_wizard_require_import_bundle_or_redirect(): bool
    {
        $check = itm_setup_wizard_import_bundle_satisfied();
        if ($check['ok']) {
            return true;
        }

        $state = itm_setup_wizard_state();
        $done = isset($state['completed_steps']) && is_array($state['completed_steps'])
            ? $state['completed_steps']
            : [];
        for ($step = 3; $step <= itm_setup_wizard_max_step(); $step++) {
            unset($done[$step]);
        }

        itm_setup_wizard_state_set([
            'completed_steps' => $done,
            'flash' => ['type' => 'error', 'message' => $check['message']],
        ]);
        itm_setup_wizard_set_step(3);
        header('Location: ' . BASE_URL . 'setup/index.php?step=3');
        exit;
    }
}

if (!function_exists('itm_setup_wizard_detected_project_root')) {
    function itm_setup_wizard_detected_project_root(): string
    {
        if (defined('ITM_SETUP_WIZARD_TEST_DETECTED_ROOT')) {
            return (string)ITM_SETUP_WIZARD_TEST_DETECTED_ROOT;
        }

        return realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, '/\\');
    }
}

if (!function_exists('itm_setup_wizard_collapse_path_token')) {
    function itm_setup_wizard_collapse_path_token(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/', '', $value));
    }
}

if (!function_exists('itm_setup_wizard_path_dirname')) {
    function itm_setup_wizard_path_dirname(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $parent = dirname(str_replace('\\', '/', $path));

            return str_replace('/', '\\', $parent);
        }

        return dirname($path);
    }
}

if (!function_exists('itm_setup_wizard_path_basename')) {
    function itm_setup_wizard_path_basename(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            return basename(str_replace('\\', '/', $path));
        }

        return basename($path);
    }
}

if (!function_exists('itm_setup_wizard_repair_windows_path_input')) {
    /**
     * Restore Windows paths when backslashes were stripped (e.g. C:Users... → C:\Users\...\it-management3).
     */
    function itm_setup_wizard_repair_windows_path_input(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/].+/', $input) || strpos($input, '\\\\') === 0) {
            return $input;
        }

        if (!preg_match('/^([A-Za-z]):(.*)$/s', $input, $matches)) {
            return $input;
        }

        $drive = $matches[1];
        $rest = $matches[2];
        $detected = itm_setup_wizard_detected_project_root();

        if (!preg_match('/^[A-Za-z]:[\\\\\\/]/', $detected)) {
            return $drive . ':\\' . ltrim($rest, '\\/');
        }

        $restCollapsed = itm_setup_wizard_collapse_path_token($rest);
        if ($restCollapsed === '') {
            return $drive . ':\\';
        }

        $detectedParent = itm_setup_wizard_path_dirname($detected);
        $detectedBase = itm_setup_wizard_path_basename($detected);
        $parentCollapsed = itm_setup_wizard_collapse_path_token($detectedParent);
        $baseCollapsed = itm_setup_wizard_collapse_path_token($detectedBase);
        $detectedTail = preg_replace('/^[A-Za-z]:[\\\\\\/]?/', '', $detected);
        $detectedTailCollapsed = itm_setup_wizard_collapse_path_token((string)$detectedTail);

        if ($restCollapsed === $detectedTailCollapsed) {
            return $detected;
        }

        if ($parentCollapsed !== '' && $restCollapsed === $parentCollapsed) {
            return itm_setup_wizard_format_path_for_input($detectedParent);
        }

        if ($parentCollapsed !== '' && strpos($restCollapsed, $parentCollapsed) === 0) {
            $folderCollapsed = substr($restCollapsed, strlen($parentCollapsed));
            if ($folderCollapsed !== '') {
                // Why: Collapsed tails like …itmanagement5 must work when runtime folder is it-management3 (stem + new digits).
                $baseStem = preg_replace('/\d+$/', '', $detectedBase);
                if ($baseStem === '') {
                    $baseStem = $detectedBase;
                }
                $stemCollapsed = itm_setup_wizard_collapse_path_token($baseStem);
                if ($stemCollapsed !== '' && strpos($folderCollapsed, $stemCollapsed) === 0) {
                    $userDigits = substr($folderCollapsed, strlen($stemCollapsed));
                    if ($userDigits === '' || preg_match('/^\d+$/', $userDigits)) {
                        $folder = $baseStem . $userDigits;

                        return itm_setup_wizard_format_path_for_input($detectedParent) . '\\' . $folder;
                    }
                }
                if ($baseCollapsed !== '' && strpos($folderCollapsed, $baseCollapsed) === 0) {
                    $extra = substr($folderCollapsed, strlen($baseCollapsed));
                    $folder = $detectedBase . $extra;

                    return itm_setup_wizard_format_path_for_input($detectedParent) . '\\' . $folder;
                }
            }
        }

        if ($baseCollapsed !== '' && preg_match('/' . preg_quote($baseCollapsed, '/') . '([0-9]*)$/', $restCollapsed, $suffixMatch)) {
            $folder = $detectedBase . $suffixMatch[1];

            return itm_setup_wizard_format_path_for_input($detectedParent) . '\\' . $folder;
        }

        return $drive . ':\\' . $rest;
    }
}

if (!function_exists('itm_setup_wizard_normalize_path_input')) {
    function itm_setup_wizard_normalize_path_input(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        $input = itm_setup_wizard_repair_windows_path_input($input);

        $input = preg_replace('#^\.[\\\\/]+#', '', $input) ?? $input;

        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $input), DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('itm_setup_wizard_format_path_for_input')) {
    function itm_setup_wizard_format_path_for_input(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        $path = itm_setup_wizard_repair_windows_path_input($path);

        // Why: Windows installers expect drive-letter paths with backslashes in editable fields.
        if (preg_match('/^[A-Za-z]:/', $path) || DIRECTORY_SEPARATOR === '\\') {
            return str_replace('/', '\\', $path);
        }

        return str_replace('\\', '/', $path);
    }
}

if (!function_exists('itm_setup_wizard_preview_project_root_path')) {
    function itm_setup_wizard_preview_project_root_path(string $input): string
    {
        $normalized = itm_setup_wizard_normalize_path_input($input);
        if ($normalized === '') {
            return '';
        }

        return itm_setup_wizard_format_path_for_input($normalized);
    }
}

if (!function_exists('itm_setup_wizard_step1_preview_config')) {
    /**
     * @return array<string, string>
     */
    function itm_setup_wizard_step1_preview_config(): array
    {
        $runtime = itm_setup_wizard_detected_project_root();
        $parent = dirname($runtime);
        $baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '';

        return [
            'runtimeRoot' => itm_setup_wizard_preview_project_root_path($runtime),
            'parentPath' => itm_setup_wizard_format_path_for_input($parent),
            'parentCollapsed' => itm_setup_wizard_collapse_path_token($parent),
            'baseName' => basename($runtime),
            'baseCollapsed' => itm_setup_wizard_collapse_path_token(basename($runtime)),
            'runtimeBaseName' => basename($runtime),
            'baseUrl' => $baseUrl,
            'documentRoot' => itm_setup_wizard_preview_document_root(),
        ];
    }
}

if (!function_exists('itm_setup_wizard_preview_document_root')) {
    function itm_setup_wizard_preview_document_root(): string
    {
        $raw = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:[\\\\\\/].+/', $raw)) {
            return itm_setup_wizard_format_path_for_input($raw);
        }

        if (preg_match('/^[A-Za-z]:/', $raw)) {
            $runtime = itm_setup_wizard_detected_project_root();
            $runtimeParent = dirname($runtime);
            $rawCollapsed = itm_setup_wizard_collapse_path_token($raw);
            if ($rawCollapsed === itm_setup_wizard_collapse_path_token($runtimeParent)) {
                return itm_setup_wizard_format_path_for_input($runtimeParent);
            }
            if ($rawCollapsed === itm_setup_wizard_collapse_path_token($runtime)) {
                return itm_setup_wizard_format_path_for_input($runtime);
            }
            $repaired = itm_setup_wizard_repair_windows_path_input($raw);
            if ($repaired !== $raw) {
                return itm_setup_wizard_format_path_for_input($repaired);
            }
        }

        return itm_setup_wizard_preview_project_root_path($raw);
    }
}

if (!function_exists('itm_setup_wizard_resolve_step1_document_root')) {
    function itm_setup_wizard_resolve_step1_document_root(string $repairedProjectRoot): string
    {
        $serverRoot = itm_setup_wizard_preview_document_root();
        if ($repairedProjectRoot !== '' && $serverRoot !== '' && itm_setup_wizard_docroot_aligned($repairedProjectRoot, $serverRoot)) {
            return $serverRoot;
        }

        $normalized = itm_setup_wizard_normalize_path_input($repairedProjectRoot);
        if ($normalized === '') {
            return $serverRoot;
        }

        $parent = dirname($normalized);
        if ($parent !== '' && $parent !== '.' && itm_setup_wizard_docroot_aligned($repairedProjectRoot, $parent)) {
            return itm_setup_wizard_format_path_for_input($parent);
        }

        return $serverRoot;
    }
}

if (!function_exists('itm_setup_wizard_docroot_aligned')) {
    function itm_setup_wizard_docroot_aligned(string $projectRoot, string $documentRoot): bool
    {
        if ($projectRoot === '' || $documentRoot === '') {
            return false;
        }

        $project = str_replace('\\', '/', $projectRoot);
        $docRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');

        return stripos($project, $docRoot) === 0;
    }
}

if (!function_exists('itm_setup_wizard_probe_localhost_port')) {
    /**
     * Best-effort TCP probe for informational UI only (does not gate install steps).
     *
     * @param string $host Loopback host only — 127.0.0.1 or localhost.
     * @return 'open'|'closed'|'unknown'
     */
    function itm_setup_wizard_probe_localhost_port(string $host, int $port, float $timeoutSeconds = 0.35): string
    {
        $host = strtolower(trim($host));
        if (!in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return 'unknown';
        }

        if ($port < 1 || $port > 65535) {
            return 'unknown';
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
        if (is_resource($socket)) {
            fclose($socket);

            return 'open';
        }

        if (in_array((int)$errno, [61, 111, 10061], true)) {
            return 'closed';
        }

        return 'unknown';
    }
}

if (!function_exists('itm_setup_wizard_localhost_port_status_label')) {
    function itm_setup_wizard_localhost_port_status_label(string $status): string
    {
        switch ($status) {
            case 'open':
                return '🟢 Open';
            case 'closed':
                return '🔴 Closed';
            default:
                return '⭕ Unknown';
        }
    }
}

if (!function_exists('itm_setup_wizard_localhost_port_status_rows')) {
    /**
     * @return array<int, array{host:string,port:int,endpoint:string,status:string,label:string}>
     */
    function itm_setup_wizard_loopback_port_status_rows(array $ports, array $hosts = ['127.0.0.1', 'localhost']): array
    {
        $rows = [];
        foreach ($hosts as $host) {
            foreach ($ports as $port) {
                $port = (int)$port;
                $status = itm_setup_wizard_probe_localhost_port((string)$host, $port);
                $rows[] = [
                    'host' => (string)$host,
                    'port' => $port,
                    'endpoint' => (string)$host . ':' . $port,
                    'status' => $status,
                    'label' => itm_setup_wizard_localhost_port_status_label($status),
                ];
            }
        }

        return $rows;
    }

    function itm_setup_wizard_localhost_port_status_rows(): array
    {
        return itm_setup_wizard_loopback_port_status_rows([80, 443]);
    }

    function itm_setup_wizard_mysql_port_status_rows(): array
    {
        return itm_setup_wizard_loopback_port_status_rows([3306, 3307], ['127.0.0.1']);
    }
}

if (!function_exists('itm_setup_wizard_detect_open_mysql_loopback_port')) {
    /**
     * Return the sole open MySQL loopback port, or 3306 when both are open; null when neither is open.
     */
    function itm_setup_wizard_detect_open_mysql_loopback_port(): ?int
    {
        $host = '127.0.0.1';
        $open3306 = itm_setup_wizard_probe_localhost_port($host, 3306) === 'open';
        $open3307 = itm_setup_wizard_probe_localhost_port($host, 3307) === 'open';

        if ($open3306 && !$open3307) {
            return 3306;
        }
        if ($open3307 && !$open3306) {
            return 3307;
        }
        if ($open3306 && $open3307) {
            return 3306;
        }

        return null;
    }
}

if (!function_exists('itm_setup_wizard_default_db_port')) {
    /**
     * Suggest MySQL port for step 1 / step 3 defaults (saved session values win; open loopback probe beats .env).
     */
    function itm_setup_wizard_default_db_port(?int $sessionPort = null, array $envFile = [], ?int $step1MysqlPort = null): int
    {
        if ($sessionPort !== null && $sessionPort > 0) {
            return $sessionPort;
        }
        if ($step1MysqlPort !== null && $step1MysqlPort > 0) {
            return $step1MysqlPort;
        }

        $openPort = itm_setup_wizard_detect_open_mysql_loopback_port();
        if ($openPort !== null) {
            return $openPort;
        }

        $envPort = getenv('DB_PORT');
        if ($envPort !== false && $envPort !== '') {
            return max(1, (int)$envPort);
        }
        if (!empty($envFile['DB_PORT'])) {
            return max(1, (int)$envFile['DB_PORT']);
        }

        return 3306;
    }
}

if (!function_exists('itm_setup_wizard_format_database_connection_error')) {
    function itm_setup_wizard_format_database_connection_error(string $host, int $port, string $rawError): string
    {
        $message = 'Connection failed: ' . $rawError;
        if (stripos($rawError, 'refused') === false) {
            return $message;
        }

        $message .= ' — no MySQL listener on ' . $host . ':' . $port . '.';
        if ($port === 3306) {
            $message .= ' On Dunebox try MySQL port 3307; confirm MySQL is running.';
        } else {
            $message .= ' Confirm MySQL is running and the port matches phpMyAdmin / .env DB_PORT.';
        }

        return $message;
    }
}

if (!function_exists('itm_setup_wizard_derive_app_url_from_project_root')) {
    function itm_setup_wizard_derive_app_url_from_project_root(string $repairedPath): string
    {
        $baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '';
        if ($baseUrl === '') {
            return '';
        }

        $runtimeBase = basename(itm_setup_wizard_detected_project_root());
        $normalized = str_replace('\\', '/', rtrim($repairedPath, '/\\'));
        $parts = explode('/', $normalized);
        $newBase = $parts !== [] ? (string)end($parts) : '';
        if ($newBase === '' || $newBase === $runtimeBase) {
            return $baseUrl;
        }

        $parsed = parse_url($baseUrl);
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            $pos = strrpos($baseUrl, $runtimeBase);
            if ($pos !== false) {
                return rtrim(substr_replace($baseUrl, $newBase, $pos, strlen($runtimeBase)), '/') . '/';
            }

            return $baseUrl;
        }

        $path = $parsed['path'] ?? '/';
        $escaped = preg_quote($runtimeBase, '/');
        if (preg_match('/(^|\/)' . $escaped . '(?=\/|$)/', $path)) {
            $path = preg_replace('/(^|\/)' . $escaped . '(?=\/|$)/', '$1' . $newBase, $path);
        } else {
            $segments = array_values(array_filter(explode('/', $path)));
            if ($segments === []) {
                $path = '/' . $newBase . '/';
            } else {
                $segments[count($segments) - 1] = $newBase;
                $path = '/' . implode('/', $segments) . '/';
            }
        }

        $port = isset($parsed['port']) ? ':' . (int)$parsed['port'] : '';

        return $parsed['scheme'] . '://' . $parsed['host'] . $port . $path;
    }
}

if (!function_exists('itm_setup_wizard_assess_project_root_for_step1')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_assess_project_root_for_step1(string $normalized): array
    {
        if ($normalized === '') {
            return ['ok' => false, 'message' => 'Project root is required.', 'needs_replace_confirm' => false];
        }

        if (!itm_setup_wizard_is_safe_project_root_path($normalized)) {
            return [
                'ok' => false,
                'message' => 'Enter a valid absolute path (example: C:\\laragon\\www\\it-management). Use backslashes after the drive letter.',
                'needs_replace_confirm' => false,
            ];
        }

        $runtimeRoot = itm_setup_wizard_detected_project_root();
        $resolved = realpath($normalized);
        if ($resolved !== false && is_dir($resolved)) {
            if (itm_setup_wizard_paths_equal($resolved, $runtimeRoot) && itm_setup_wizard_project_root_has_schema($resolved)) {
                return ['ok' => true, 'message' => 'Current install folder is valid.', 'needs_replace_confirm' => false];
            }

            return [
                'ok' => false,
                'needs_replace_confirm' => true,
                'message' => 'Project root folder already exists. Confirm replacement to delete all files inside and download a fresh copy.',
            ];
        }

        $parent = dirname($normalized);
        if (!is_dir($parent)) {
            return [
                'ok' => false,
                'message' => 'Parent folder does not exist: ' . itm_setup_wizard_format_path_display($parent),
            ];
        }
        if (!is_writable($parent)) {
            return [
                'ok' => false,
                'message' => 'Parent folder is not writable: ' . itm_setup_wizard_format_path_display($parent),
            ];
        }

        return ['ok' => true, 'message' => 'New folder path accepted — folder will be created when you Download.', 'needs_replace_confirm' => false];
    }
}

if (!function_exists('itm_setup_wizard_step1_preview_payload')) {
    /**
     * @return array<string, mixed>
     */
    function itm_setup_wizard_step1_preview_payload(string $input, bool $persistState = true): array
    {
        $repaired = itm_setup_wizard_preview_project_root_path($input);
        $normalized = itm_setup_wizard_normalize_path_input($input);
        $validation = itm_setup_wizard_assess_project_root_for_step1($normalized);
        $documentRoot = itm_setup_wizard_resolve_step1_document_root($repaired);
        $appUrl = itm_setup_wizard_derive_app_url_from_project_root($repaired);
        $aligned = itm_setup_wizard_docroot_aligned($repaired, $documentRoot);

        if ($persistState && $validation['ok']) {
            itm_setup_wizard_state_set([
                'project_root' => $repaired,
                'itm_app_url' => $appUrl,
                'file_checks' => null,
            ]);
        }

        return [
            'ok' => $validation['ok'],
            'message' => $validation['message'],
            'needsReplaceConfirm' => !empty($validation['needs_replace_confirm']),
            'projectRoot' => $repaired,
            'autoDetect' => $repaired,
            'documentRoot' => $documentRoot !== '' ? $documentRoot : '(not detected)',
            'baseUrl' => $appUrl,
            'appUrl' => $appUrl,
            'docrootAligned' => $aligned,
            'docrootAlignedHtml' => $aligned
                ? '<span class="ok">Yes</span>'
                : '<span class="warn">Check Apache alias / virtual host</span>',
        ];
    }
}

if (!function_exists('itm_setup_wizard_github_repo_slug')) {
    function itm_setup_wizard_github_repo_slug(): string
    {
        $slug = getenv('ITM_SETUP_GITHUB_REPO');
        if (is_string($slug) && trim($slug) !== '') {
            return trim($slug);
        }

        return 'pirica/it-management';
    }
}

if (!function_exists('itm_setup_wizard_github_default_branch')) {
    function itm_setup_wizard_github_default_branch(): string
    {
        $branch = getenv('ITM_SETUP_GITHUB_BRANCH');
        if (is_string($branch) && trim($branch) !== '') {
            return trim($branch);
        }

        return 'master';
    }
}

if (!function_exists('itm_setup_wizard_github_clone_url')) {
    function itm_setup_wizard_github_clone_url(): string
    {
        return 'https://github.com/' . itm_setup_wizard_github_repo_slug() . '.git';
    }
}

if (!function_exists('itm_setup_wizard_github_zip_url')) {
    function itm_setup_wizard_github_zip_url(): string
    {
        $slug = itm_setup_wizard_github_repo_slug();
        $branch = itm_setup_wizard_github_default_branch();

        return 'https://github.com/' . $slug . '/archive/refs/heads/' . rawurlencode($branch) . '.zip';
    }
}

if (!function_exists('itm_setup_wizard_paths_equal')) {
    function itm_setup_wizard_paths_equal(string $a, string $b): bool
    {
        $a = strtolower(str_replace('\\', '/', rtrim($a, '/\\')));
        $b = strtolower(str_replace('\\', '/', rtrim($b, '/\\')));

        return $a === $b;
    }
}

if (!function_exists('itm_setup_wizard_is_safe_project_root_path')) {
    function itm_setup_wizard_is_safe_project_root_path(string $normalized): bool
    {
        if ($normalized === '' || strpos($normalized, '..') !== false) {
            return false;
        }

        // Windows absolute paths (validated on Laragon even when path uses backslashes).
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $normalized) || (isset($normalized[0]) && ($normalized[0] === '\\'))) {
            return true;
        }

        return $normalized[0] === '/';
    }
}

if (!function_exists('itm_setup_wizard_project_root_has_schema')) {
    function itm_setup_wizard_project_root_has_schema(string $dir): bool
    {
        $schema = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . '01_schema.sql';

        return is_readable($schema);
    }
}

if (!function_exists('itm_setup_wizard_shell_exec_available')) {
    function itm_setup_wizard_shell_exec_available(): bool
    {
        if (!function_exists('shell_exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));

        return !in_array('shell_exec', $disabled, true);
    }
}

if (!function_exists('itm_setup_wizard_which_git')) {
    function itm_setup_wizard_which_git(): ?string
    {
        if (!itm_setup_wizard_shell_exec_available()) {
            return null;
        }

        $isWin = PHP_SAPI === 'win32' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $cmd = $isWin ? 'where git 2>NUL' : 'command -v git 2>/dev/null';
        $out = trim((string)shell_exec($cmd));
        if ($out === '') {
            return null;
        }

        $line = strtok($out, "\r\n");

        return $line !== false && $line !== '' ? $line : null;
    }
}

if (!function_exists('itm_setup_wizard_run_shell_command')) {
    /**
     * @return array{exit:int,output:string}
     */
    function itm_setup_wizard_run_shell_command(string $command): array
    {
        $output = [];
        $exit = 1;
        if (function_exists('exec')) {
            exec($command . ' 2>&1', $output, $exit);
        }

        return ['exit' => $exit, 'output' => implode("\n", $output)];
    }
}

if (!function_exists('itm_setup_wizard_remove_directory_tree')) {
    function itm_setup_wizard_remove_directory_tree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                itm_setup_wizard_remove_directory_tree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}

if (!function_exists('itm_setup_wizard_copy_path')) {
    function itm_setup_wizard_copy_path(string $source, string $destination): bool
    {
        if (is_dir($source)) {
            if (!is_dir($destination) && !@mkdir($destination, 0755, true)) {
                return false;
            }
            $items = scandir($source);
            if ($items === false) {
                return false;
            }
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                if (!itm_setup_wizard_copy_path(
                    $source . DIRECTORY_SEPARATOR . $item,
                    $destination . DIRECTORY_SEPARATOR . $item
                )) {
                    return false;
                }
            }

            return true;
        }

        $parent = dirname($destination);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true)) {
            return false;
        }

        return @copy($source, $destination);
    }
}

if (!function_exists('itm_setup_wizard_move_directory_contents')) {
    function itm_setup_wizard_move_directory_contents(string $from, string $to): bool
    {
        $items = scandir($from);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $from . DIRECTORY_SEPARATOR . $item;
            $dst = $to . DIRECTORY_SEPARATOR . $item;
            if (!@rename($src, $dst)) {
                if (!itm_setup_wizard_copy_path($src, $dst)) {
                    return false;
                }
                if (is_dir($src)) {
                    itm_setup_wizard_remove_directory_tree($src);
                } else {
                    @unlink($src);
                }
            }
        }

        return true;
    }
}

if (!function_exists('itm_setup_wizard_http_download_to_file')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_http_download_to_file(string $url, string $destination): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'message' => 'curl_init failed'];
            }
            $fp = fopen($destination, 'wb');
            if ($fp === false) {
                curl_close($ch);

                return ['ok' => false, 'message' => 'Could not open temp file for download'];
            }
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => true,
                CURLOPT_TIMEOUT => 600,
                CURLOPT_USERAGENT => 'ITM-Setup-Wizard/1.0',
            ]);
            $ok = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            fclose($fp);
            if ($ok === false) {
                @unlink($destination);

                return ['ok' => false, 'message' => 'Download failed: ' . $error];
            }

            return ['ok' => true, 'message' => ''];
        }

        if (!ini_get('allow_url_fopen')) {
            return ['ok' => false, 'message' => 'curl extension and allow_url_fopen are both unavailable'];
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 600,
                'header' => "User-Agent: ITM-Setup-Wizard/1.0\r\n",
            ],
        ]);
        $data = @file_get_contents($url, false, $context);
        if ($data === false) {
            return ['ok' => false, 'message' => 'Could not download GitHub archive'];
        }
        if (file_put_contents($destination, $data) === false) {
            return ['ok' => false, 'message' => 'Could not write GitHub archive to disk'];
        }

        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('itm_setup_wizard_clone_from_github')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_clone_from_github(string $targetDir): array
    {
        $git = itm_setup_wizard_which_git();
        if ($git === null) {
            return ['ok' => false, 'message' => 'git not found on PATH'];
        }

        $cmd = sprintf(
            '%s clone --depth 1 --branch %s %s %s',
            escapeshellarg($git),
            escapeshellarg(itm_setup_wizard_github_default_branch()),
            escapeshellarg(itm_setup_wizard_github_clone_url()),
            escapeshellarg($targetDir)
        );
        if (PHP_SAPI === 'win32' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = 'cmd /c ' . $cmd;
        }

        $run = itm_setup_wizard_run_shell_command($cmd);
        if ($run['exit'] !== 0) {
            return ['ok' => false, 'message' => 'git clone failed: ' . $run['output']];
        }

        if (!itm_setup_wizard_project_root_has_schema($targetDir)) {
            return ['ok' => false, 'message' => 'git clone finished but db/01_schema.sql is missing'];
        }

        return [
            'ok' => true,
            'message' => 'Cloned ' . itm_setup_wizard_github_repo_slug() . ' (' . itm_setup_wizard_github_default_branch() . ') from GitHub.',
        ];
    }
}

if (!function_exists('itm_setup_wizard_download_github_zip')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_download_github_zip(string $targetDir): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'message' => 'ZipArchive PHP extension is required when git is unavailable'];
        }

        $tmpZip = tempnam(sys_get_temp_dir(), 'itm_setup_zip_');
        if ($tmpZip === false) {
            return ['ok' => false, 'message' => 'Could not create temp file for GitHub download'];
        }

        $download = itm_setup_wizard_http_download_to_file(itm_setup_wizard_github_zip_url(), $tmpZip);
        if (!$download['ok']) {
            @unlink($tmpZip);

            return $download;
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);

            return ['ok' => false, 'message' => 'Could not open downloaded GitHub archive'];
        }

        $extractRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'itm_setup_extract_' . bin2hex(random_bytes(4));
        if (!@mkdir($extractRoot, 0755, true)) {
            $zip->close();
            @unlink($tmpZip);

            return ['ok' => false, 'message' => 'Could not create temp extract folder'];
        }

        if (!$zip->extractTo($extractRoot)) {
            $zip->close();
            @unlink($tmpZip);
            itm_setup_wizard_remove_directory_tree($extractRoot);

            return ['ok' => false, 'message' => 'Could not extract GitHub archive'];
        }
        $zip->close();
        @unlink($tmpZip);

        $entries = array_values(array_diff(scandir($extractRoot) ?: [], ['.', '..']));
        if (count($entries) !== 1) {
            itm_setup_wizard_remove_directory_tree($extractRoot);

            return ['ok' => false, 'message' => 'Unexpected GitHub archive layout'];
        }

        $sourceDir = $extractRoot . DIRECTORY_SEPARATOR . $entries[0];
        if (!is_dir($sourceDir) || !itm_setup_wizard_move_directory_contents($sourceDir, $targetDir)) {
            itm_setup_wizard_remove_directory_tree($extractRoot);

            return ['ok' => false, 'message' => 'Could not move extracted project files into project root'];
        }

        itm_setup_wizard_remove_directory_tree($extractRoot);

        if (!itm_setup_wizard_project_root_has_schema($targetDir)) {
            return ['ok' => false, 'message' => 'Archive extracted but db/01_schema.sql is missing'];
        }

        return [
            'ok' => true,
            'message' => 'Downloaded ' . itm_setup_wizard_github_repo_slug() . ' archive from GitHub.',
        ];
    }
}

if (!function_exists('itm_setup_wizard_download_project_from_github')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_download_project_from_github(string $targetDir): array
    {
        $clone = itm_setup_wizard_clone_from_github($targetDir);
        if ($clone['ok']) {
            return $clone;
        }

        $zip = itm_setup_wizard_download_github_zip($targetDir);
        if ($zip['ok']) {
            return $zip;
        }

        return [
            'ok' => false,
            'message' => trim($clone['message'] . ' ' . $zip['message']),
        ];
    }
}

if (!function_exists('itm_setup_wizard_provision_project_root')) {
    /**
     * Create a new project root when missing (download from GitHub) or accept the current runtime install.
     *
     * @return array{ok:bool,path:string,message:string}
     */
    function itm_setup_wizard_provision_project_root(string $input, bool $confirmReplace = false): array
    {
        $normalized = itm_setup_wizard_normalize_path_input($input);
        if ($normalized === '') {
            return ['ok' => false, 'path' => '', 'message' => 'Project root is required.', 'needs_replace_confirm' => false];
        }

        if (!itm_setup_wizard_is_safe_project_root_path($normalized)) {
            return [
                'ok' => false,
                'path' => $normalized,
                'message' => 'Enter a valid absolute path (example: C:\\laragon\\www\\it-management). Use backslashes after the drive letter.',
                'needs_replace_confirm' => false,
            ];
        }

        $runtimeRoot = itm_setup_wizard_detected_project_root();
        $resolved = realpath($normalized);

        if ($resolved !== false && is_dir($resolved)) {
            if (itm_setup_wizard_paths_equal($resolved, $runtimeRoot) && itm_setup_wizard_project_root_has_schema($resolved)) {
                return ['ok' => true, 'path' => $resolved, 'message' => 'Using current install folder.', 'needs_replace_confirm' => false];
            }

            if (!$confirmReplace) {
                return [
                    'ok' => false,
                    'path' => $resolved,
                    'needs_replace_confirm' => true,
                    'message' => 'Project root folder already exists. Confirm replacement to delete all files inside and download a fresh copy.',
                ];
            }

            if (!is_writable($resolved)) {
                return [
                    'ok' => false,
                    'path' => $resolved,
                    'needs_replace_confirm' => false,
                    'message' => 'Project root folder is not writable: ' . itm_setup_wizard_format_path_for_input($resolved),
                ];
            }

            itm_setup_wizard_remove_directory_tree($resolved);
            if (is_dir($resolved) && !@rmdir($resolved)) {
                return [
                    'ok' => false,
                    'path' => $resolved,
                    'needs_replace_confirm' => false,
                    'message' => 'Could not clear existing project root folder.',
                ];
            }
            if (!@mkdir($normalized, 0755, false)) {
                return ['ok' => false, 'path' => $normalized, 'message' => 'Could not recreate project root folder.', 'needs_replace_confirm' => false];
            }

            $download = itm_setup_wizard_download_project_from_github($normalized);
            if (!$download['ok']) {
                itm_setup_wizard_remove_directory_tree($normalized);
                @rmdir($normalized);

                return ['ok' => false, 'path' => $normalized, 'message' => $download['message'], 'needs_replace_confirm' => false];
            }

            $resolved = realpath($normalized);
            if ($resolved === false || !itm_setup_wizard_project_root_has_schema($resolved)) {
                itm_setup_wizard_remove_directory_tree($normalized);
                @rmdir($normalized);

                return ['ok' => false, 'path' => $normalized, 'message' => 'Download finished but db/01_schema.sql was not found.', 'needs_replace_confirm' => false];
            }

            return ['ok' => true, 'path' => $resolved, 'message' => $download['message'], 'needs_replace_confirm' => false];
        }

        $parent = dirname($normalized);
        if (!is_dir($parent)) {
            return [
                'ok' => false,
                'path' => $normalized,
                'message' => 'Parent folder does not exist: ' . itm_setup_wizard_format_path_for_input($parent),
            ];
        }
        if (!is_writable($parent)) {
            return [
                'ok' => false,
                'path' => $normalized,
                'message' => 'Parent folder is not writable: ' . itm_setup_wizard_format_path_for_input($parent),
            ];
        }

        if (!@mkdir($normalized, 0755, false)) {
            return ['ok' => false, 'path' => $normalized, 'message' => 'Could not create project root folder.'];
        }

        $download = itm_setup_wizard_download_project_from_github($normalized);
        if (!$download['ok']) {
            itm_setup_wizard_remove_directory_tree($normalized);
            @rmdir($normalized);

            return ['ok' => false, 'path' => $normalized, 'message' => $download['message']];
        }

        $resolved = realpath($normalized);
        if ($resolved === false || !itm_setup_wizard_project_root_has_schema($resolved)) {
            itm_setup_wizard_remove_directory_tree($normalized);
            @rmdir($normalized);

            return ['ok' => false, 'path' => $normalized, 'message' => 'Download finished but db/01_schema.sql was not found.'];
        }

        return ['ok' => true, 'path' => $resolved, 'message' => $download['message']];
    }
}

if (!function_exists('itm_setup_wizard_project_subdirectory')) {
    function itm_setup_wizard_project_subdirectory(string $segment): string
    {
        $root = rtrim(itm_setup_wizard_project_root(), '/\\');

        return $root . DIRECTORY_SEPARATOR . trim($segment, '/\\') . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('itm_setup_wizard_h')) {
    /**
     * HTML-escape wizard output without stripslashes — global sanitize() breaks Windows paths.
     */
    function itm_setup_wizard_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('itm_setup_wizard_h_path_display')) {
    /**
     * Escape a filesystem path for HTML; allow wrap only after directory separators.
     */
    function itm_setup_wizard_h_path_display(string $path): string
    {
        $escaped = itm_setup_wizard_h($path);
        $escaped = str_replace('-', '&#8209;', $escaped);

        return str_replace(['\\', '/'], ['\\<wbr>', '/<wbr>'], $escaped);
    }
}

if (!function_exists('itm_setup_wizard_verify_row')) {
    /**
     * @return array{level:string,label:string,path:string,message:string}
     */
    function itm_setup_wizard_verify_row(string $level, string $label, string $path = ''): array
    {
        $path = trim($path);

        return [
            'level' => $level,
            'label' => $label,
            'path' => $path,
            'message' => $path !== '' ? rtrim($label) . ' ' . $path : $label,
        ];
    }
}

if (!function_exists('itm_setup_wizard_format_path_display')) {
    function itm_setup_wizard_format_path_display(string $path): string
    {
        return itm_setup_wizard_format_path_for_input($path);
    }
}
if (!function_exists('itm_setup_wizard_validate_project_root_path')) {
    /**
     * @return array{ok:bool,path:string,message:string}
     */
    function itm_setup_wizard_validate_project_root_path(string $input): array
    {
        $normalized = itm_setup_wizard_normalize_path_input($input);
        if ($normalized === '') {
            return ['ok' => false, 'path' => '', 'message' => 'Project root is required.'];
        }

        $resolved = realpath($normalized);
        if ($resolved === false && is_dir($normalized) && itm_setup_wizard_project_root_has_schema($normalized)) {
            $resolved = $normalized;
        }
        if ($resolved === false || !is_dir($resolved) || !itm_setup_wizard_project_root_has_schema($resolved)) {
            return ['ok' => false, 'path' => $normalized, 'message' => 'Project root must contain db/01_schema.sql.'];
        }

        return ['ok' => true, 'path' => $resolved, 'message' => ''];
    }
}

if (!function_exists('itm_setup_wizard_resolve_saved_project_root')) {
    /**
     * Repair and normalize the wizard session project_root string (never falls back to runtime).
     */
    function itm_setup_wizard_resolve_saved_project_root(): string
    {
        $state = itm_setup_wizard_state();
        $saved = isset($state['project_root']) ? trim((string)$state['project_root']) : '';
        if ($saved === '') {
            return '';
        }

        $repaired = itm_setup_wizard_preview_project_root_path($saved);
        if ($repaired !== '' && $repaired !== $saved) {
            itm_setup_wizard_state_set(['project_root' => $repaired]);
        }

        return $repaired !== '' ? $repaired : $saved;
    }
}

if (!function_exists('itm_setup_wizard_project_root')) {
    function itm_setup_wizard_project_root(): string
    {
        $saved = itm_setup_wizard_resolve_saved_project_root();
        if ($saved !== '') {
            $validated = itm_setup_wizard_validate_project_root_path($saved);
            if ($validated['ok']) {
                return $validated['path'];
            }

            // Why: Step 1 may persist a repaired path before the folder exists (preview save) or when
            // realpath() fails on a valid Windows path — never substitute the PHP runtime install path.
            if (itm_setup_wizard_step_done(1)) {
                $normalized = itm_setup_wizard_normalize_path_input($saved);
                if ($normalized !== '' && itm_setup_wizard_is_safe_project_root_path($normalized)) {
                    $resolved = realpath($normalized);
                    if ($resolved !== false && is_dir($resolved)) {
                        return $resolved;
                    }
                    if (is_dir($normalized)) {
                        return $normalized;
                    }

                    return $normalized;
                }
            }
        }

        return itm_setup_wizard_detected_project_root();
    }
}

if (!function_exists('itm_setup_wizard_project_root_input_value')) {
    function itm_setup_wizard_project_root_input_value(): string
    {
        $state = itm_setup_wizard_state();
        if (isset($state['project_root']) && trim((string)$state['project_root']) !== '') {
            return itm_setup_wizard_preview_project_root_path(trim((string)$state['project_root']));
        }

        return itm_setup_wizard_preview_project_root_path(itm_setup_wizard_detected_project_root());
    }
}

if (!function_exists('itm_setup_wizard_project_root_matches_runtime')) {
    function itm_setup_wizard_project_root_matches_runtime(): bool
    {
        $runtime = str_replace('\\', '/', strtolower(itm_setup_wizard_detected_project_root()));
        $chosen = str_replace('\\', '/', strtolower(itm_setup_wizard_project_root()));

        return $runtime === $chosen;
    }
}

if (!function_exists('itm_setup_wizard_detect_paths')) {
    /**
     * @return array<string, string>
     */
    function itm_setup_wizard_detect_paths(): array
    {
        $detectedRoot = itm_setup_wizard_detected_project_root();
        $projectRoot = itm_setup_wizard_project_root();
        $documentRoot = itm_setup_wizard_preview_document_root();
        $projectRootDisplay = itm_setup_wizard_preview_project_root_path($projectRoot);
        $aligned = $documentRoot !== '' && $projectRootDisplay !== ''
            && stripos(
                str_replace('\\', '/', $projectRootDisplay),
                rtrim(str_replace('\\', '/', $documentRoot), '/')
            ) === 0;

        return [
            'project_root' => $projectRoot,
            'detected_project_root' => $detectedRoot,
            'document_root' => $documentRoot,
            'base_url' => defined('BASE_URL') ? (string)BASE_URL : '',
            'setup_url' => (defined('BASE_URL') ? (string)BASE_URL : '/') . 'setup/',
            'docroot_aligned' => $aligned ? 'yes' : 'no',
            'project_root_matches_runtime' => itm_setup_wizard_project_root_matches_runtime() ? 'yes' : 'no',
        ];
    }
}

if (!function_exists('itm_setup_wizard_required_db_files')) {
    /**
     * @return string[]
     */
    function itm_setup_wizard_required_db_files(): array
    {
        $dbRoot = rtrim(itm_setup_wizard_project_root(), '/\\') . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR;

        return [
            $dbRoot . '01_schema.sql',
            $dbRoot . '02_data.sql',
            $dbRoot . '03_triggers.sql',
        ];
    }
}

if (!function_exists('itm_setup_wizard_required_upload_roots')) {
    /**
     * @return array<string, string> path => policy
     */
    function itm_setup_wizard_required_upload_roots(): array
    {
        return [
            itm_setup_wizard_project_subdirectory('images') => 'upload',
            itm_setup_wizard_project_subdirectory('tickets_photos') => 'upload',
            itm_setup_wizard_project_subdirectory('floor_plans') => 'upload',
            itm_setup_wizard_project_subdirectory('backups') => 'deny_all',
            itm_setup_wizard_project_subdirectory('files') => 'deny_http',
        ];
    }
}

if (!function_exists('itm_setup_wizard_verify_files')) {
    /**
     * @return array<int, array{level:string,label:string,path:string,message:string}>
     */
    function itm_setup_wizard_verify_files(): array
    {
        $results = [];

        $projectRootDisplay = itm_setup_wizard_format_path_display(itm_setup_wizard_project_root());
        $results[] = itm_setup_wizard_verify_row('pass', 'Project root:', $projectRootDisplay);

        foreach (itm_setup_wizard_required_db_files() as $path) {
            if (!is_readable($path)) {
                $results[] = itm_setup_wizard_verify_row(
                    'fail',
                    'Missing or unreadable:',
                    itm_setup_wizard_format_path_display($path)
                );
            } else {
                $results[] = itm_setup_wizard_verify_row(
                    'pass',
                    'Found ' . basename($path) . ' (' . number_format(filesize($path)) . ' bytes)'
                );
            }
        }

        $envPath = itm_setup_wizard_env_file_path();
        $projectRoot = rtrim(itm_setup_wizard_project_root(), '/\\');
        if (is_file($envPath) && !is_writable($envPath)) {
            $results[] = itm_setup_wizard_verify_row(
                'fail',
                '.env exists but is not writable:',
                itm_setup_wizard_format_path_display($envPath)
            );
        } elseif (!is_file($envPath) && !is_writable($projectRoot)) {
            $results[] = itm_setup_wizard_verify_row(
                'fail',
                'Project root is not writable — cannot create .env:',
                $projectRootDisplay
            );
        } else {
            $results[] = itm_setup_wizard_verify_row(
                'pass',
                is_file($envPath) ? '.env is writable:' : 'Project root can create .env:',
                is_file($envPath) ? itm_setup_wizard_format_path_display($envPath) : $projectRootDisplay
            );
        }

        foreach (itm_setup_wizard_required_upload_roots() as $dir => $policy) {
            $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $displayDir = itm_setup_wizard_format_path_display(rtrim($dir, '/\\'));
            if (!is_dir($dir)) {
                $results[] = itm_setup_wizard_verify_row('warn', 'Directory missing (will be created):', $displayDir);
                continue;
            }
            if (!is_writable($dir)) {
                $results[] = itm_setup_wizard_verify_row('fail', 'Not writable:', $displayDir);
            } else {
                $results[] = itm_setup_wizard_verify_row('pass', 'Writable:', $displayDir);
            }
        }

        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '7.4.0', '<')) {
            $results[] = itm_setup_wizard_verify_row('fail', 'PHP 7.4+ required (found ' . $phpVersion . ')');
        } else {
            $results[] = itm_setup_wizard_verify_row('pass', 'PHP ' . $phpVersion);
        }

        return $results;
    }
}

if (!function_exists('itm_setup_wizard_test_database')) {
    /**
     * @return array{ok:bool,message:string,conn:mysqli|false,server_ok?:bool,database_exists?:bool,table_count?:int,needs_create?:bool,needs_replace_confirm?:bool}
     */
    function itm_setup_wizard_is_safe_database_name(string $name): bool
    {
        return $name !== '' && (bool)preg_match('/^[A-Za-z0-9_]+$/', $name);
    }

    function itm_setup_wizard_connect_mysql_server(string $host, int $port, string $user, string $pass)
    {
        return itm_mysqli_connect($host, $user, $pass, '', $port);
    }

    function itm_setup_wizard_database_exists(mysqli $conn, string $database): bool
    {
        if (!itm_setup_wizard_is_safe_database_name($database)) {
            return false;
        }

        $stmt = mysqli_prepare($conn, 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 's', $database);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $exists = $res && mysqli_fetch_assoc($res);
        if ($res) {
            mysqli_free_result($res);
        }
        mysqli_stmt_close($stmt);

        return (bool)$exists;
    }

    /**
     * @return array{ok:bool,message:string,conn:mysqli|false,server_ok:bool,database_exists:bool,table_count:int,needs_create:bool,needs_replace_confirm:bool}
     */
    function itm_setup_wizard_probe_database(string $host, int $port, string $user, string $pass, string $database): array
    {
        $database = trim($database);
        if ($database === '') {
            return [
                'ok' => false,
                'message' => 'Database name is required.',
                'conn' => false,
                'server_ok' => false,
                'database_exists' => false,
                'table_count' => 0,
                'needs_create' => false,
                'needs_replace_confirm' => false,
            ];
        }
        if (!itm_setup_wizard_is_safe_database_name($database)) {
            return [
                'ok' => false,
                'message' => 'Database name may only contain letters, numbers, and underscores.',
                'conn' => false,
                'server_ok' => false,
                'database_exists' => false,
                'table_count' => 0,
                'needs_create' => false,
                'needs_replace_confirm' => false,
            ];
        }

        $serverConn = itm_setup_wizard_connect_mysql_server($host, $port, $user, $pass);
        if (!$serverConn) {
            return [
                'ok' => false,
                'message' => itm_setup_wizard_format_database_connection_error(
                    $host,
                    $port,
                    mysqli_connect_error() ?: 'unknown error'
                ),
                'conn' => false,
                'server_ok' => false,
                'database_exists' => false,
                'table_count' => 0,
                'needs_create' => false,
                'needs_replace_confirm' => false,
            ];
        }
        mysqli_set_charset($serverConn, 'utf8mb4');

        if (!itm_setup_wizard_database_exists($serverConn, $database)) {
            mysqli_close($serverConn);

            return [
                'ok' => false,
                'message' => 'Database "' . $database . '" does not exist yet. Create it below, then test the connection again.',
                'conn' => false,
                'server_ok' => true,
                'database_exists' => false,
                'table_count' => 0,
                'needs_create' => true,
                'needs_replace_confirm' => false,
            ];
        }

        $dbConn = itm_mysqli_connect($host, $user, $pass, $database, $port);
        if (!$dbConn) {
            mysqli_close($serverConn);

            return [
                'ok' => false,
                'message' => itm_setup_wizard_format_database_connection_error(
                    $host,
                    $port,
                    mysqli_connect_error() ?: 'unknown error'
                ),
                'conn' => false,
                'server_ok' => true,
                'database_exists' => true,
                'table_count' => 0,
                'needs_create' => false,
                'needs_replace_confirm' => false,
            ];
        }
        mysqli_set_charset($dbConn, 'utf8mb4');
        $tableCount = itm_setup_wizard_count_tables($dbConn, $database);
        mysqli_close($serverConn);

        $message = 'Connected to ' . $database . ' on ' . $host . ':' . $port;
        if ($tableCount > 0) {
            $message .= ' (' . $tableCount . ' existing table(s) — import will replace all tables and data after confirmation).';
        }

        return [
            'ok' => true,
            'message' => $message,
            'conn' => $dbConn,
            'server_ok' => true,
            'database_exists' => true,
            'table_count' => $tableCount,
            'needs_create' => false,
            'needs_replace_confirm' => $tableCount > 0,
        ];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_create_database(string $host, int $port, string $user, string $pass, string $database): array
    {
        $database = trim($database);
        if (!itm_setup_wizard_is_safe_database_name($database)) {
            return ['ok' => false, 'message' => 'Database name may only contain letters, numbers, and underscores.'];
        }

        $serverConn = itm_setup_wizard_connect_mysql_server($host, $port, $user, $pass);
        if (!$serverConn) {
            return ['ok' => false, 'message' => 'Connection failed: ' . (mysqli_connect_error() ?: 'unknown error')];
        }

        if (itm_setup_wizard_database_exists($serverConn, $database)) {
            mysqli_close($serverConn);

            return ['ok' => false, 'message' => 'Database "' . $database . '" already exists.'];
        }

        $sql = 'CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        if (!mysqli_query($serverConn, $sql)) {
            $error = mysqli_error($serverConn);
            mysqli_close($serverConn);

            return ['ok' => false, 'message' => 'Could not create database: ' . ($error ?: 'unknown error')];
        }
        mysqli_close($serverConn);

        return ['ok' => true, 'message' => 'Created database "' . $database . '". Test the connection, then import the bundle.'];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_reset_database(string $host, int $port, string $user, string $pass, string $database): array
    {
        $database = trim($database);
        if (!itm_setup_wizard_is_safe_database_name($database)) {
            return ['ok' => false, 'message' => 'Database name may only contain letters, numbers, and underscores.'];
        }

        $serverConn = itm_setup_wizard_connect_mysql_server($host, $port, $user, $pass);
        if (!$serverConn) {
            return ['ok' => false, 'message' => 'Connection failed: ' . (mysqli_connect_error() ?: 'unknown error')];
        }

        if (!itm_setup_wizard_database_exists($serverConn, $database)) {
            mysqli_close($serverConn);

            return itm_setup_wizard_create_database($host, $port, $user, $pass, $database);
        }

        $dropSql = 'DROP DATABASE `' . $database . '`';
        if (!mysqli_query($serverConn, $dropSql)) {
            $error = mysqli_error($serverConn);
            mysqli_close($serverConn);

            return ['ok' => false, 'message' => 'Could not drop database: ' . ($error ?: 'unknown error')];
        }

        $createSql = 'CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        if (!mysqli_query($serverConn, $createSql)) {
            $error = mysqli_error($serverConn);
            mysqli_close($serverConn);

            return ['ok' => false, 'message' => 'Database dropped but recreate failed: ' . ($error ?: 'unknown error')];
        }
        mysqli_close($serverConn);

        return ['ok' => true, 'message' => 'Replaced database "' . $database . '" with an empty schema.'];
    }

    function itm_setup_wizard_test_database(string $host, int $port, string $user, string $pass, string $database): array
    {
        $probe = itm_setup_wizard_probe_database($host, $port, $user, $pass, $database);

        return [
            'ok' => $probe['ok'],
            'message' => $probe['message'],
            'conn' => $probe['conn'],
            'server_ok' => $probe['server_ok'],
            'database_exists' => $probe['database_exists'],
            'table_count' => $probe['table_count'],
            'needs_create' => $probe['needs_create'],
            'needs_replace_confirm' => $probe['needs_replace_confirm'],
        ];
    }
}

if (!function_exists('itm_setup_wizard_count_tables')) {
    function itm_setup_wizard_count_tables(mysqli $conn, string $schema): int
    {
        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 's', $schema);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($res) {
            mysqli_free_result($res);
        }
        mysqli_stmt_close($stmt);

        return (int)($row['cnt'] ?? 0);
    }
}

if (!function_exists('itm_setup_wizard_expected_table_count')) {
    function itm_setup_wizard_expected_table_count(): int
    {
        static $count = null;
        if ($count !== null) {
            return $count;
        }
        if (!function_exists('itm_database_sql_schema_path')) {
            require_once ROOT_PATH . 'includes/itm_database_sql_source.php';
        }
        $schemaPath = itm_database_sql_schema_path();
        if (!is_readable($schemaPath)) {
            $count = 0;

            return $count;
        }
        $count = preg_match_all('/^CREATE TABLE/m', (string)file_get_contents($schemaPath));

        return (int)$count;
    }
}

if (!function_exists('itm_setup_wizard_triggers_sql_path')) {
    function itm_setup_wizard_triggers_sql_path(): string
    {
        return rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . '03_triggers.sql';
    }
}

if (!function_exists('itm_setup_wizard_expected_trigger_count')) {
    function itm_setup_wizard_expected_trigger_count(): int
    {
        static $count = null;
        if ($count !== null) {
            return $count;
        }
        $path = itm_setup_wizard_triggers_sql_path();
        if (!is_readable($path)) {
            $count = 0;

            return $count;
        }
        $count = preg_match_all('/^CREATE TRIGGER/m', (string)file_get_contents($path));

        return (int)$count;
    }
}

if (!function_exists('itm_setup_wizard_count_triggers')) {
    function itm_setup_wizard_count_triggers(mysqli $conn, string $schema): int
    {
        $schema = trim($schema);
        if ($schema === '') {
            return 0;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT COUNT(*) AS cnt FROM information_schema.triggers WHERE trigger_schema = ?'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 's', $schema);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($res) {
            mysqli_free_result($res);
        }
        mysqli_stmt_close($stmt);

        return (int)($row['cnt'] ?? 0);
    }
}

if (!function_exists('itm_setup_wizard_canonical_database_name')) {
    function itm_setup_wizard_canonical_database_name(): string
    {
        return 'itmanagement';
    }
}

if (!function_exists('itm_setup_wizard_rewrite_sql_for_database')) {
    function itm_setup_wizard_rewrite_sql_for_database(string $sql, string $targetDatabase): string
    {
        $targetDatabase = trim($targetDatabase);
        $canonical = itm_setup_wizard_canonical_database_name();
        if ($targetDatabase === '' || strcasecmp($targetDatabase, $canonical) === 0) {
            return $sql;
        }
        if (!itm_setup_wizard_is_safe_database_name($targetDatabase)) {
            return $sql;
        }

        return str_replace('`' . $canonical . '`', '`' . $targetDatabase . '`', $sql);
    }
}

if (!function_exists('itm_setup_wizard_build_import_sql_bundle')) {
    function itm_setup_wizard_build_import_sql_bundle(string $targetDatabase): array
    {
        $bundle = '';
        foreach (itm_setup_wizard_required_db_files() as $file) {
            $chunk = file_get_contents($file);
            if ($chunk === false) {
                return ['ok' => false, 'message' => 'Could not read ' . $file, 'sql' => ''];
            }
            $bundle .= itm_setup_wizard_rewrite_sql_for_database($chunk, $targetDatabase) . "\n";
        }

        return ['ok' => true, 'message' => '', 'sql' => $bundle];
    }
}

if (!function_exists('itm_setup_wizard_mysql_cli_connect_host')) {
    /**
     * Host passed to mysql.exe — on Windows prefer localhost for loopback to reduce TCP socket failures from Apache proc_open.
     */
    function itm_setup_wizard_mysql_cli_connect_host(string $host): string
    {
        $host = trim($host);
        if ($host === '') {
            return '127.0.0.1';
        }
        if (DIRECTORY_SEPARATOR === '\\' && ($host === '127.0.0.1' || strcasecmp($host, 'localhost') === 0)) {
            return 'localhost';
        }

        return $host;
    }
}

if (!function_exists('itm_setup_wizard_import_via_shell')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_import_via_shell(string $host, int $port, string $user, string $pass, string $database): array
    {
        $bundleResult = itm_setup_wizard_build_import_sql_bundle($database);
        if (!$bundleResult['ok']) {
            return ['ok' => false, 'message' => $bundleResult['message']];
        }
        $bundle = $bundleResult['sql'];

        if (!function_exists('itm_resolve_cli_mysql_binary')) {
            require_once ROOT_PATH . 'includes/itm_cli_binary.php';
        }

        $mysqlBin = getenv('MYSQL_BIN') ?: '';
        if ($mysqlBin === '' || !is_file($mysqlBin)) {
            $mysqlBin = itm_resolve_cli_mysql_binary();
        }

        $cliHost = itm_setup_wizard_mysql_cli_connect_host($host);

        $cmd = [
            $mysqlBin,
            '-h',
            $cliHost,
            '-P',
            (string)$port,
            '-u',
            $user,
            '--default-character-set=utf8mb4',
            $database,
        ];
        if (DIRECTORY_SEPARATOR === '\\') {
            $cmd[] = '--protocol=TCP';
        }

        $env = $_ENV;
        if ($pass !== '') {
            $env['MYSQL_PWD'] = $pass;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes, null, $env);
        if (!is_resource($process)) {
            return ['ok' => false, 'message' => 'Could not start mysql CLI for import.'];
        }

        fwrite($pipes[0], $bundle);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $detail = trim((string)$stderr);
            if ($detail === '') {
                $detail = trim((string)$stdout);
            }
            $message = 'mysql CLI import failed (exit ' . (int)$exitCode . ')';
            if ($detail !== '') {
                $message .= ': ' . $detail;
            }

            return ['ok' => false, 'message' => $message];
        }

        $expectedTriggers = itm_setup_wizard_expected_trigger_count();
        if ($expectedTriggers > 0) {
            $verifyConn = itm_mysqli_connect($host, $user, $pass, $database, $port);
            if ($verifyConn instanceof mysqli) {
                $triggerCount = itm_setup_wizard_count_triggers($verifyConn, $database);
                mysqli_close($verifyConn);
                if ($triggerCount < $expectedTriggers) {
                    return [
                        'ok' => false,
                        'message' => '03_triggers.sql: expected ' . $expectedTriggers . ' triggers, found ' . $triggerCount,
                    ];
                }
            }
        }

        return ['ok' => true, 'message' => 'Imported db/ bundle via mysql CLI'];
    }
}

if (!function_exists('itm_setup_wizard_import_via_mysqli')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_import_via_mysqli(mysqli $conn, string $database): array
    {
        if (!function_exists('itm_database_migrations_execute_sql_text')) {
            require_once ROOT_PATH . 'includes/itm_database_migrations.php';
        }

        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
        mysqli_query($conn, 'SET NAMES utf8mb4');

        foreach (itm_setup_wizard_required_db_files() as $file) {
            $sql = file_get_contents($file);
            if ($sql === false || trim($sql) === '') {
                return ['ok' => false, 'message' => basename($file) . ': file is empty or unreadable'];
            }
            if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
                $sql = substr($sql, 3);
            }
            $sql = itm_setup_wizard_rewrite_sql_for_database($sql, $database);
            [$executed, $executeError] = itm_database_migrations_execute_sql_text($conn, $sql);
            if (!$executed) {
                $detail = trim($executeError);
                $message = basename($file);
                if ($detail !== '') {
                    $message .= ': ' . $detail;
                }

                return ['ok' => false, 'message' => $message];
            }
        }

        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');

        $expectedTriggers = itm_setup_wizard_expected_trigger_count();
        if ($expectedTriggers > 0) {
            $triggerCount = itm_setup_wizard_count_triggers($conn, $database);
            if ($triggerCount < $expectedTriggers) {
                return [
                    'ok' => false,
                    'message' => '03_triggers.sql: expected ' . $expectedTriggers . ' triggers, found ' . $triggerCount,
                ];
            }
        }

        return ['ok' => true, 'message' => 'Imported db/ bundle via mysqli'];
    }
}

if (!function_exists('itm_setup_wizard_import_database')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_import_database(string $host, int $port, string $user, string $pass, string $database): array
    {
        $mysqliError = '';
        $test = itm_setup_wizard_test_database($host, $port, $user, $pass, $database);
        if ($test['ok'] && $test['conn'] instanceof mysqli) {
            $mysqliResult = itm_setup_wizard_import_via_mysqli($test['conn'], $database);
            mysqli_close($test['conn']);
            if ($mysqliResult['ok']) {
                return $mysqliResult;
            }
            $mysqliError = $mysqliResult['message'];
        }

        $shellResult = itm_setup_wizard_import_via_shell($host, $port, $user, $pass, $database);
        if (!$shellResult['ok'] && $mysqliError !== '') {
            $shellResult['message'] = $mysqliError . ' Shell fallback: ' . $shellResult['message'];
        }

        return $shellResult;
    }
}

if (!function_exists('itm_setup_wizard_extension_matrix')) {
    /**
     * @return array<int, array{slug:string,label:string,required:bool,loaded:bool,group:string}>
     */
    function itm_setup_wizard_extension_matrix(): array
    {
        $rows = [
            ['slug' => 'mysqli', 'label' => 'mysqli', 'required' => true, 'group' => 'core'],
            ['slug' => 'json', 'label' => 'json', 'required' => true, 'group' => 'core'],
            ['slug' => 'mbstring', 'label' => 'mbstring', 'required' => true, 'group' => 'core'],
            ['slug' => 'dom', 'label' => 'dom', 'required' => false, 'group' => 'phpunit'],
            ['slug' => 'libxml', 'label' => 'libxml', 'required' => false, 'group' => 'phpunit'],
            ['slug' => 'tokenizer', 'label' => 'tokenizer', 'required' => false, 'group' => 'phpunit'],
            ['slug' => 'xml', 'label' => 'xml', 'required' => false, 'group' => 'phpunit'],
            ['slug' => 'xmlwriter', 'label' => 'xmlwriter', 'required' => false, 'group' => 'phpunit'],
            ['slug' => 'ldap', 'label' => 'ldap (SSO)', 'required' => false, 'group' => 'optional'],
            ['slug' => 'imap', 'label' => 'imap (CLI inbound email)', 'required' => false, 'group' => 'optional'],
            ['slug' => 'xdebug', 'label' => 'xdebug (coverage)', 'required' => false, 'group' => 'optional'],
            ['slug' => 'pcov', 'label' => 'pcov (coverage)', 'required' => false, 'group' => 'optional'],
        ];

        foreach ($rows as &$row) {
            $row['loaded'] = extension_loaded($row['slug']);
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('itm_setup_wizard_env_file_path')) {
    /**
     * Target install folder .env — matches step 2 verification (not necessarily the PHP runtime ROOT_PATH).
     */
    function itm_setup_wizard_env_file_path(): string
    {
        return rtrim(itm_setup_wizard_project_root(), '/\\') . DIRECTORY_SEPARATOR . '.env';
    }
}

if (!function_exists('itm_setup_wizard_finish_login_url')) {
    function itm_setup_wizard_finish_login_url(): string
    {
        $state = itm_setup_wizard_state();
        $appUrl = trim((string)($state['itm_app_url'] ?? ''));
        if ($appUrl !== '') {
            return rtrim($appUrl, '/') . '/login.php?setup=done';
        }

        return rtrim((defined('BASE_URL') ? (string)BASE_URL : '/'), '/') . '/login.php?setup=done';
    }
}

if (!function_exists('itm_setup_wizard_read_env_file')) {
    /**
     * @return array<string, string>
     */
    function itm_setup_wizard_read_env_file(): array
    {
        $path = itm_setup_wizard_env_file_path();
        $vars = [];
        if (!is_readable($path)) {
            return $vars;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $vars[trim($name)] = trim($value, " \t\"'");
        }

        return $vars;
    }
}

if (!function_exists('itm_setup_wizard_write_env_file')) {
    /**
     * @param array<string, string|null> $values
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_write_env_file(array $values): array
    {
        $existing = itm_setup_wizard_read_env_file();
        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }
            $existing[$key] = (string)$value;
        }

        $lines = [
            '# Generated/updated by setup wizard on ' . date('c'),
            '',
        ];

        $order = [
            'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS',
            'ITM_APP_URL', 'ITM_ALLOWED_HOSTS', 'APP_ENV', 'ITM_DEV',
            'ITM_SKIP_FORCE_PASSWORD_CHANGE', 'ITM_SESSION_COOKIE_SECURE',
            'ITM_MAINTENANCE_TOKEN', 'ITM_SCRIPT_NO_AUTH_ALLOWED_IPS', 'ITM_SCRIPT_NO_AUTH_ALLOWED_HOSTS',
        ];

        $written = [];
        foreach ($order as $key) {
            if (!array_key_exists($key, $existing)) {
                continue;
            }
            $lines[] = $key . '=' . $existing[$key];
            $written[$key] = true;
        }

        foreach ($existing as $key => $value) {
            if (isset($written[$key])) {
                continue;
            }
            $lines[] = $key . '=' . $value;
        }

        $path = itm_setup_wizard_env_file_path();
        $projectRoot = rtrim(itm_setup_wizard_project_root(), '/\\');
        if (!is_dir($projectRoot)) {
            return [
                'ok' => false,
                'message' => 'Project root does not exist — cannot write .env: '
                    . itm_setup_wizard_format_path_display($projectRoot),
            ];
        }
        if (is_file($path) && !is_writable($path)) {
            return [
                'ok' => false,
                'message' => '.env exists but is not writable: ' . itm_setup_wizard_format_path_display($path),
            ];
        }
        if (!is_file($path) && !is_writable($projectRoot)) {
            return [
                'ok' => false,
                'message' => 'Project root is not writable — cannot create .env: '
                    . itm_setup_wizard_format_path_display($projectRoot),
            ];
        }
        if (file_put_contents($path, implode("\n", $lines) . "\n") === false) {
            return [
                'ok' => false,
                'message' => 'Could not write .env: ' . itm_setup_wizard_format_path_display($path),
            ];
        }

        return [
            'ok' => true,
            'message' => 'Saved .env at ' . itm_setup_wizard_format_path_display($path),
        ];
    }
}

if (!function_exists('itm_setup_wizard_ensure_directories')) {
    /**
     * @return array<int, array{level:string,message:string}>
     */
    function itm_setup_wizard_ensure_directories(): array
    {
        $results = [];
        foreach (itm_setup_wizard_required_upload_roots() as $dir => $policy) {
            $ok = itm_ensure_upload_directory($dir, $policy);
            $results[] = [
                'level' => $ok ? 'pass' : 'fail',
                'message' => ($ok ? 'Ensured ' : 'Failed ')
                    . itm_setup_wizard_format_path_display(rtrim($dir, '/\\'))
                    . ' (' . $policy . ')',
            ];
        }

        return $results;
    }
}

if (!function_exists('itm_setup_wizard_session_db_credentials')) {
    /**
     * Wizard session DB settings (steps 3–7). Not read from .env — avoids stale on-disk creds mid-install.
     *
     * @return array{host:string,port:int,user:string,pass:string,name:string}|null
     */
    function itm_setup_wizard_session_db_credentials(): ?array
    {
        $state = itm_setup_wizard_state();
        $db = $state['db'] ?? null;
        if (!is_array($db)) {
            return null;
        }

        $host = trim((string)($db['host'] ?? ''));
        $name = trim((string)($db['name'] ?? ''));
        $user = trim((string)($db['user'] ?? ''));
        if ($host === '' || $name === '' || $user === '') {
            return null;
        }

        return [
            'host' => $host,
            'port' => max(1, (int)($db['port'] ?? 3306)),
            'user' => $user,
            'pass' => (string)($db['pass'] ?? ''),
            'name' => $name,
        ];
    }
}

if (!function_exists('itm_setup_wizard_connect_database')) {
    /**
     * @param array{host?:string,port?:int,user?:string,pass?:string,name?:string}|null $credentials
     * @return mysqli|false
     */
    function itm_setup_wizard_connect_database(?array $credentials = null)
    {
        if ($credentials === null) {
            $credentials = itm_setup_wizard_session_db_credentials();
        }

        if ($credentials !== null) {
            return itm_mysqli_connect(
                $credentials['host'],
                $credentials['user'],
                $credentials['pass'],
                $credentials['name'],
                $credentials['port']
            );
        }

        itm_load_dotenv_file(itm_setup_wizard_env_file_path());

        return itm_mysqli_connect(
            getenv('DB_HOST') ?: DB_HOST,
            getenv('DB_USER') ?: DB_USER,
            getenv('DB_PASS') ?: DB_PASS,
            getenv('DB_NAME') ?: DB_NAME,
            (int)(getenv('DB_PORT') ?: DB_PORT)
        );
    }
}

if (!function_exists('itm_setup_wizard_reload_connection')) {
    /**
     * @return mysqli|false
     */
    function itm_setup_wizard_reload_connection()
    {
        return itm_setup_wizard_connect_database();
    }
}

if (!function_exists('itm_setup_wizard_persist_env_from_state')) {
    /**
     * Write .env from wizard session (step 7 / finish). Deferred so partial installs do not poison .env.
     *
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_persist_env_from_state(): array
    {
        $credentials = itm_setup_wizard_session_db_credentials();
        if ($credentials === null) {
            return ['ok' => false, 'message' => 'Database settings missing from wizard session — complete step 3 first.'];
        }

        $state = itm_setup_wizard_state();
        $values = [
            'DB_HOST' => $credentials['host'],
            'DB_PORT' => (string)$credentials['port'],
            'DB_USER' => $credentials['user'],
            'DB_PASS' => $credentials['pass'],
            'DB_NAME' => $credentials['name'],
        ];

        $appUrl = trim((string)($state['itm_app_url'] ?? ''));
        if ($appUrl !== '') {
            $values['ITM_APP_URL'] = rtrim($appUrl, '/') . '/';
        }

        $appEnv = trim((string)($state['app_env'] ?? ''));
        if ($appEnv !== '') {
            $values['APP_ENV'] = $appEnv;
        }

        if (array_key_exists('itm_dev', $state)) {
            $values['ITM_DEV'] = !empty($state['itm_dev']) ? '1' : '0';
        } elseif ($appEnv === 'production') {
            $values['ITM_DEV'] = '0';
        }

        if (array_key_exists('itm_skip_force_password_change', $state)) {
            $values['ITM_SKIP_FORCE_PASSWORD_CHANGE'] = !empty($state['itm_skip_force_password_change']) ? '1' : '0';
        } elseif ($appEnv === 'production') {
            $values['ITM_SKIP_FORCE_PASSWORD_CHANGE'] = '0';
        }

        return itm_setup_wizard_write_env_file($values);
    }
}

if (!function_exists('itm_setup_wizard_save_admin')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_save_admin(mysqli $conn, string $username, string $password, string $firstName, string $lastName, string $workEmail): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return ['ok' => false, 'message' => 'Username and password are required'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        if ($hash === false) {
            return ['ok' => false, 'message' => 'Could not hash password'];
        }

        $sql = 'UPDATE employees SET password = ?, first_name = ?, last_name = ?, work_email = ?, must_change_password = 0, updated_at = NOW()
                WHERE username = ? AND deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Prepare failed'];
        }
        mysqli_stmt_bind_param($stmt, 'sssss', $hash, $firstName, $lastName, $workEmail, $username);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected < 1) {
            return ['ok' => false, 'message' => 'No employee row updated for username ' . $username . ' — import database first'];
        }

        return ['ok' => true, 'message' => 'Administrator "' . $username . '" password updated'];
    }
}

if (!function_exists('itm_setup_wizard_apply_minimal_single_user_install')) {
    /**
     * Remove seed Admin2–Admin5 and demo1–demo5 after step 7 skip — keep only the step 6 administrator.
     *
     * @return array{ok:bool,message:string,employee_count:int,keeper_employee_id:int}
     */
    function itm_setup_wizard_apply_minimal_single_user_install(mysqli $conn, string $keeperUsername): array
    {
        $keeperUsername = trim($keeperUsername);
        if ($keeperUsername === '') {
            return ['ok' => false, 'message' => 'Administrator username is required', 'employee_count' => 0, 'keeper_employee_id' => 0];
        }

        $keeperId = 0;
        $lookup = mysqli_prepare($conn, 'SELECT id FROM employees WHERE username = ? AND deleted_at IS NULL LIMIT 1');
        if (!$lookup) {
            return ['ok' => false, 'message' => 'Could not resolve administrator employee', 'employee_count' => 0, 'keeper_employee_id' => 0];
        }
        mysqli_stmt_bind_param($lookup, 's', $keeperUsername);
        mysqli_stmt_execute($lookup);
        $lookupResult = mysqli_stmt_get_result($lookup);
        if ($lookupResult && ($keeperRow = mysqli_fetch_assoc($lookupResult))) {
            $keeperId = (int)($keeperRow['id'] ?? 0);
        }
        mysqli_stmt_close($lookup);

        if ($keeperId < 1) {
            return [
                'ok' => false,
                'message' => 'No employee row found for administrator username "' . $keeperUsername . '"',
                'employee_count' => 0,
                'keeper_employee_id' => 0,
            ];
        }

        $removeIds = [];
        $employeeResult = mysqli_query($conn, 'SELECT id FROM employees WHERE deleted_at IS NULL');
        if ($employeeResult) {
            while ($employeeRow = mysqli_fetch_assoc($employeeResult)) {
                $employeeId = (int)($employeeRow['id'] ?? 0);
                if ($employeeId > 0 && $employeeId !== $keeperId) {
                    $removeIds[] = $employeeId;
                }
            }
            mysqli_free_result($employeeResult);
        }

        if ($removeIds === []) {
            return [
                'ok' => true,
                'message' => 'Single administrator account already present',
                'employee_count' => 1,
                'keeper_employee_id' => $keeperId,
            ];
        }

        $removeList = implode(',', array_map('intval', $removeIds));
        $keeperSql = (string)(int)$keeperId;

        $statements = [
            'UPDATE employees SET reports_to = NULL WHERE reports_to IN (' . $removeList . ')',
            'UPDATE tickets SET created_by_employee_id = ' . $keeperSql . ' WHERE created_by_employee_id IN (' . $removeList . ')',
            'UPDATE tickets SET assigned_to_employee_id = ' . $keeperSql . ' WHERE assigned_to_employee_id IN (' . $removeList . ')',
            'UPDATE ticket_surveys SET issued_by_employee_id = ' . $keeperSql . ' WHERE issued_by_employee_id IN (' . $removeList . ')',
            'UPDATE ticket_surveys SET created_by = ' . $keeperSql . ' WHERE created_by IN (' . $removeList . ')',
            'UPDATE ticket_inbound_email_routing_rules SET assigned_to_employee_id = ' . $keeperSql . ' WHERE assigned_to_employee_id IN (' . $removeList . ')',
            'UPDATE equipment SET assigned_to_employee_id = NULL WHERE assigned_to_employee_id IN (' . $removeList . ')',
            'UPDATE inventory_items SET last_employee_id = NULL WHERE last_employee_id IN (' . $removeList . ')',
            'DELETE FROM employee_sidebar_preferences WHERE employee_id IN (' . $removeList . ')',
            'DELETE FROM ui_configuration WHERE employee_id IN (' . $removeList . ')',
            'DELETE FROM employee_companies WHERE employee_id IN (' . $removeList . ')',
            'DELETE FROM change_request_cab_members WHERE employee_id IN (' . $removeList . ')',
            'DELETE FROM attempts WHERE employee_id IN (' . $removeList . ')',
            'DELETE FROM registration_invitations WHERE invited_by_employee_id IN (' . $removeList . ')',
            'DELETE FROM employees WHERE id IN (' . $removeList . ')',
        ];

        mysqli_begin_transaction($conn);
        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');

        $failedSql = '';
        foreach ($statements as $sql) {
            if (!mysqli_query($conn, $sql)) {
                $failedSql = $sql;
                break;
            }
        }

        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');

        if ($failedSql !== '') {
            mysqli_rollback($conn);
            return [
                'ok' => false,
                'message' => 'Minimal install cleanup failed: ' . mysqli_error($conn),
                'employee_count' => 0,
                'keeper_employee_id' => $keeperId,
            ];
        }

        mysqli_commit($conn);

        $remaining = 0;
        $countResult = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM employees WHERE deleted_at IS NULL');
        if ($countResult && ($countRow = mysqli_fetch_assoc($countResult))) {
            $remaining = (int)($countRow['c'] ?? 0);
            mysqli_free_result($countResult);
        }

        if ($remaining !== 1) {
            return [
                'ok' => false,
                'message' => 'Expected exactly one employee after skip cleanup, found ' . $remaining,
                'employee_count' => $remaining,
                'keeper_employee_id' => $keeperId,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Removed ' . count($removeIds) . ' seed user(s); kept administrator "' . $keeperUsername . '"',
            'employee_count' => $remaining,
            'keeper_employee_id' => $keeperId,
        ];
    }
}

if (!function_exists('itm_setup_wizard_list_seed_companies')) {
    /**
     * @return array<int, array{id:int,name:string}>
     */
    function itm_setup_wizard_list_seed_companies(mysqli $conn): array
    {
        $rows = [];
        $sql = 'SELECT id, company FROM companies WHERE active = 1 AND deleted_at IS NULL ORDER BY id ASC';
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            return $rows;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'name' => trim((string)($row['company'] ?? '')),
            ];
        }

        return $rows;
    }
}

if (!function_exists('itm_setup_wizard_seed_company_catalog')) {
    /**
     * Canonical five seed tenants from db/02_data.sql — used when step 7 cannot query live companies yet.
     *
     * @return array<int, array{id:int,name:string}>
     */
    function itm_setup_wizard_seed_company_catalog(): array
    {
        return [
            ['id' => 1, 'name' => 'TechCorp Global'],
            ['id' => 2, 'name' => 'DataCenter Plus'],
            ['id' => 3, 'name' => 'Network Solutions'],
            ['id' => 4, 'name' => 'CloudTech Services'],
            ['id' => 5, 'name' => 'Enterprise IT'],
        ];
    }
}

if (!function_exists('itm_setup_wizard_resolve_sample_company_options')) {
    /**
     * @return array<int, array{id:int,name:string}>
     */
    function itm_setup_wizard_resolve_sample_company_options(?mysqli $conn): array
    {
        if ($conn instanceof mysqli) {
            $liveRows = itm_setup_wizard_list_seed_companies($conn);
            if ($liveRows !== []) {
                return $liveRows;
            }
        }

        return itm_setup_wizard_seed_company_catalog();
    }
}

if (!function_exists('itm_setup_wizard_install_sample_data')) {
    /**
     * @return array{ok:bool,message:string,detail:string}
     */
    function itm_setup_wizard_install_sample_data(mysqli $conn, int $companyId = 1): array
    {
        if (!function_exists('itm_seed_all_tables_from_database_sql')) {
            return ['ok' => false, 'message' => 'Sample data helper unavailable', 'detail' => ''];
        }

        $error = '';
        $report = [];
        $ok = itm_seed_all_tables_from_database_sql($conn, $companyId, $error, $report);
        if (!$ok) {
            return ['ok' => false, 'message' => $error !== '' ? $error : 'Sample data seed failed', 'detail' => ''];
        }

        $seeded = isset($report['seeded']) && is_array($report['seeded']) ? count($report['seeded']) : 0;

        return [
            'ok' => true,
            'message' => 'Sample data installed for company ' . $companyId,
            'detail' => $seeded . ' table(s) seeded',
        ];
    }
}

if (!function_exists('itm_setup_wizard_install_sample_data_for_companies')) {
    /**
     * @param array<int, int|string> $companyIds
     * @return array{ok:bool,message:string,detail:string}
     */
    function itm_setup_wizard_install_sample_data_for_companies(mysqli $conn, array $companyIds): array
    {
        $normalized = [];
        foreach ($companyIds as $rawId) {
            $id = (int)$rawId;
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }
        $normalized = array_values($normalized);
        if ($normalized === []) {
            return ['ok' => false, 'message' => 'Select at least one company', 'detail' => ''];
        }

        $successes = [];
        $failures = [];
        $totalTables = 0;
        $lastSuccessMessage = '';
        $lastSuccessDetail = '';
        foreach ($normalized as $companyId) {
            $seed = itm_setup_wizard_install_sample_data($conn, $companyId);
            if (!$seed['ok']) {
                $failures[] = 'Company ' . $companyId . ': ' . $seed['message'];
                continue;
            }

            $successes[] = (int)$companyId;
            $lastSuccessMessage = (string)$seed['message'];
            $lastSuccessDetail = (string)$seed['detail'];
            if (preg_match('/(\d+)\s+table\(s\)\s+seeded/i', $lastSuccessDetail, $matches)) {
                $totalTables += (int)$matches[1];
            }
        }

        if ($successes === []) {
            return [
                'ok' => false,
                'message' => implode('; ', $failures),
                'detail' => '',
            ];
        }

        if (count($successes) === 1) {
            return [
                'ok' => true,
                'message' => $lastSuccessMessage,
                'detail' => $lastSuccessDetail,
            ];
        }

        $message = 'Sample data installed for companies ' . implode(', ', $successes);
        if ($failures !== []) {
            $message .= ' — partial failures: ' . implode('; ', $failures);
        }

        return [
            'ok' => true,
            'message' => $message,
            'detail' => $totalTables . ' table seed pass(es) across ' . count($successes) . ' companies',
        ];
    }
}

if (!function_exists('itm_setup_wizard_remove_entrypoint')) {
    /**
     * @return array{ok:bool,message:string,removed:string[]}
     */
    function itm_setup_wizard_remove_entrypoint(): array
    {
        $indexPath = rtrim(itm_setup_wizard_project_root(), '/\\') . DIRECTORY_SEPARATOR . 'setup' . DIRECTORY_SEPARATOR . 'index.php';
        if (is_file($indexPath)) {
            if (!@unlink($indexPath)) {
                return [
                    'ok' => false,
                    'message' => 'Could not delete setup/index.php: ' . itm_setup_wizard_format_path_display($indexPath),
                    'removed' => [],
                ];
            }
        }

        return [
            'ok' => true,
            'message' => 'Setup finished and setup/index.php removed (restore setup/index.php on the server to run the installer again)',
            'removed' => [$indexPath],
        ];
    }
}

if (!function_exists('itm_setup_wizard_apply_ui_error_reporting')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_apply_ui_error_reporting(mysqli $conn, int $enable): array
    {
        $enable = $enable === 1 ? 1 : 0;
        $sql = 'UPDATE ui_configuration SET enable_all_error_reporting = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Could not update ui_configuration'];
        }
        mysqli_stmt_bind_param($stmt, 'i', $enable);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return ['ok' => true, 'message' => 'enable_all_error_reporting set to ' . $enable . ' on all ui_configuration rows'];
    }
}
