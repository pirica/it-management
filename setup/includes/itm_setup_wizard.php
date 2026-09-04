<?php
/**
 * First-run setup wizard helpers (setup/index.php).
 */

declare(strict_types=1);

if (!function_exists('itm_setup_wizard_lock_path')) {
    function itm_setup_wizard_lock_path(): string
    {
        return rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . 'setup' . DIRECTORY_SEPARATOR . '.installed';
    }
}

if (!function_exists('itm_setup_wizard_is_complete')) {
    function itm_setup_wizard_is_complete(): bool
    {
        return is_file(itm_setup_wizard_lock_path());
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
            7 => ['slug' => 'sample_data', 'title' => 'Sample data', 'subtitle' => 'Optional demo rows for company 1'],
            8 => ['slug' => 'finish', 'title' => 'Finish', 'subtitle' => 'Lock installer and remove setup entry point'],
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

        return $step;
    }
}

if (!function_exists('itm_setup_wizard_set_step')) {
    function itm_setup_wizard_set_step(int $step): void
    {
        $step = max(1, min(itm_setup_wizard_max_step(), $step));
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

if (!function_exists('itm_setup_wizard_detected_project_root')) {
    function itm_setup_wizard_detected_project_root(): string
    {
        return realpath(ROOT_PATH) ?: rtrim(ROOT_PATH, '/\\');
    }
}

if (!function_exists('itm_setup_wizard_normalize_path_input')) {
    function itm_setup_wizard_normalize_path_input(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $input), DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('itm_setup_wizard_format_path_for_input')) {
    function itm_setup_wizard_format_path_for_input(string $path): string
    {
        // Why: Windows installers expect drive-letter paths with backslashes in editable fields.
        if (preg_match('/^[A-Za-z]:/', $path) || DIRECTORY_SEPARATOR === '\\') {
            return str_replace('/', '\\', $path);
        }

        return str_replace('\\', '/', $path);
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
        if ($resolved === false || !is_dir($resolved)) {
            return ['ok' => false, 'path' => $normalized, 'message' => 'Project root folder does not exist or is not readable.'];
        }

        $schemaPath = $resolved . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . '01_schema.sql';
        if (!is_readable($schemaPath)) {
            return [
                'ok' => false,
                'path' => $resolved,
                'message' => 'Folder must contain db/01_schema.sql (IT Management project root).',
            ];
        }

        return ['ok' => true, 'path' => $resolved, 'message' => ''];
    }
}

if (!function_exists('itm_setup_wizard_project_root')) {
    function itm_setup_wizard_project_root(): string
    {
        $state = itm_setup_wizard_state();
        $saved = isset($state['project_root']) ? trim((string)$state['project_root']) : '';
        if ($saved !== '') {
            $validated = itm_setup_wizard_validate_project_root_path($saved);
            if ($validated['ok']) {
                return $validated['path'];
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
            return itm_setup_wizard_format_path_for_input(trim((string)$state['project_root']));
        }

        return itm_setup_wizard_format_path_for_input(itm_setup_wizard_detected_project_root());
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
        $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
        $detectedRoot = itm_setup_wizard_detected_project_root();
        $projectRoot = itm_setup_wizard_project_root();
        $aligned = $documentRoot !== '' && $projectRoot !== '' && strpos($projectRoot, $documentRoot) === 0;

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
            UPLOAD_PATH => 'upload',
            TICKET_UPLOAD_PATH => 'upload',
            FLOOR_PLAN_UPLOAD_PATH => 'upload',
            BACKUP_PATH => 'deny_all',
            itm_files_storage_root() . DIRECTORY_SEPARATOR => 'deny_http',
        ];
    }
}

if (!function_exists('itm_setup_wizard_verify_files')) {
    /**
     * @return array<int, array{level:string,message:string}>
     */
    function itm_setup_wizard_verify_files(): array
    {
        $results = [];

        if (!itm_setup_wizard_project_root_matches_runtime()) {
            $results[] = [
                'level' => 'warn',
                'message' => 'Confirmed project root differs from this PHP request path (' . itm_setup_wizard_detected_project_root() . ') — database bundle checks use the path from step 1.',
            ];
        }

        foreach (itm_setup_wizard_required_db_files() as $path) {
            if (!is_readable($path)) {
                $results[] = ['level' => 'fail', 'message' => 'Missing or unreadable: ' . $path];
            } else {
                $results[] = ['level' => 'pass', 'message' => 'Found ' . basename($path) . ' (' . number_format(filesize($path)) . ' bytes)'];
            }
        }

        $envPath = ROOT_PATH . '.env';
        if (is_file($envPath) && !is_writable($envPath)) {
            $results[] = ['level' => 'fail', 'message' => '.env exists but is not writable'];
        } elseif (!is_file($envPath) && !is_writable(ROOT_PATH)) {
            $results[] = ['level' => 'fail', 'message' => 'Project root is not writable — cannot create .env'];
        } else {
            $results[] = ['level' => 'pass', 'message' => is_file($envPath) ? '.env is writable' : 'Project root can create .env'];
        }

        foreach (itm_setup_wizard_required_upload_roots() as $dir => $policy) {
            $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dir), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!is_dir($dir)) {
                $results[] = ['level' => 'warn', 'message' => 'Directory missing (will be created): ' . $dir];
                continue;
            }
            if (!is_writable($dir)) {
                $results[] = ['level' => 'fail', 'message' => 'Not writable: ' . $dir];
            } else {
                $results[] = ['level' => 'pass', 'message' => 'Writable: ' . str_replace(ROOT_PATH, '', $dir)];
            }
        }

        $phpVersion = PHP_VERSION;
        if (version_compare($phpVersion, '7.4.0', '<')) {
            $results[] = ['level' => 'fail', 'message' => 'PHP 7.4+ required (found ' . $phpVersion . ')'];
        } else {
            $results[] = ['level' => 'pass', 'message' => 'PHP ' . $phpVersion];
        }

        return $results;
    }
}

if (!function_exists('itm_setup_wizard_test_database')) {
    /**
     * @return array{ok:bool,message:string,conn:mysqli|false}
     */
    function itm_setup_wizard_test_database(string $host, int $port, string $user, string $pass, string $database): array
    {
        $conn = itm_mysqli_connect($host, $user, $pass, $database, $port);
        if (!$conn) {
            return [
                'ok' => false,
                'message' => 'Connection failed: ' . (mysqli_connect_error() ?: 'unknown error'),
                'conn' => false,
            ];
        }

        mysqli_set_charset($conn, 'utf8mb4');

        return ['ok' => true, 'message' => 'Connected to ' . $database . ' on ' . $host . ':' . $port, 'conn' => $conn];
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
        $schemaPath = itm_database_sql_schema_path();
        if (!is_readable($schemaPath)) {
            $count = 0;

            return $count;
        }
        $count = preg_match_all('/^CREATE TABLE/m', (string)file_get_contents($schemaPath));

        return (int)$count;
    }
}

if (!function_exists('itm_setup_wizard_import_via_shell')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_import_via_shell(string $host, int $port, string $user, string $pass, string $database): array
    {
        $files = itm_setup_wizard_required_db_files();
        $bundle = '';
        foreach ($files as $file) {
            $chunk = file_get_contents($file);
            if ($chunk === false) {
                return ['ok' => false, 'message' => 'Could not read ' . $file];
            }
            $bundle .= $chunk . "\n";
        }

        $tmp = tempnam(sys_get_temp_dir(), 'itm_setup_import_');
        if ($tmp === false) {
            return ['ok' => false, 'message' => 'Could not create temp SQL bundle'];
        }
        file_put_contents($tmp, $bundle);

        $mysqlBin = getenv('MYSQL_BIN') ?: 'mysql';
        $cmd = sprintf(
            '%s -h %s -P %d -u %s %s --default-character-set=utf8mb4 %s < %s',
            escapeshellarg($mysqlBin),
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            $pass !== '' ? '-p' . escapeshellarg($pass) : '',
            escapeshellarg($database),
            escapeshellarg($tmp)
        );

        if (PHP_SAPI === 'win32' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = 'cmd /c ' . $cmd;
        }

        passthru($cmd, $exitCode);
        @unlink($tmp);

        if ($exitCode !== 0) {
            return ['ok' => false, 'message' => 'mysql CLI import failed (exit ' . (int)$exitCode . ')'];
        }

        return ['ok' => true, 'message' => 'Imported db/ bundle via mysql CLI'];
    }
}

if (!function_exists('itm_setup_wizard_import_via_mysqli')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_import_via_mysqli(mysqli $conn): array
    {
        if (!function_exists('itm_database_migrations_execute_sql_file')) {
            require_once ROOT_PATH . 'includes/itm_database_migrations.php';
        }

        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
        mysqli_query($conn, 'SET NAMES utf8mb4');

        foreach (itm_setup_wizard_required_db_files() as $file) {
            [$ok, $message] = itm_database_migrations_execute_sql_file($conn, $file);
            if (!$ok) {
                return ['ok' => false, 'message' => basename($file) . ': ' . $message];
            }
        }

        mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');

        return ['ok' => true, 'message' => 'Imported db/ bundle via mysqli'];
    }
}

if (!function_exists('itm_setup_wizard_import_database')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_setup_wizard_import_database(string $host, int $port, string $user, string $pass, string $database): array
    {
        $test = itm_setup_wizard_test_database($host, $port, $user, $pass, $database);
        if ($test['ok'] && $test['conn'] instanceof mysqli) {
            $mysqliResult = itm_setup_wizard_import_via_mysqli($test['conn']);
            if ($mysqliResult['ok']) {
                return $mysqliResult;
            }
        }

        return itm_setup_wizard_import_via_shell($host, $port, $user, $pass, $database);
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

if (!function_exists('itm_setup_wizard_read_env_file')) {
    /**
     * @return array<string, string>
     */
    function itm_setup_wizard_read_env_file(): array
    {
        $path = ROOT_PATH . '.env';
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

        $path = ROOT_PATH . '.env';
        if (file_put_contents($path, implode("\n", $lines) . "\n") === false) {
            return ['ok' => false, 'message' => 'Could not write .env'];
        }

        return ['ok' => true, 'message' => 'Saved .env'];
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
                'message' => ($ok ? 'Ensured ' : 'Failed ') . str_replace(ROOT_PATH, '', rtrim($dir, '/\\')) . '/ (' . $policy . ')',
            ];
        }

        return $results;
    }
}

if (!function_exists('itm_setup_wizard_reload_connection')) {
    /**
     * @return mysqli|false
     */
    function itm_setup_wizard_reload_connection()
    {
        itm_load_dotenv_file(ROOT_PATH . '.env');

        return itm_mysqli_connect(
            getenv('DB_HOST') ?: DB_HOST,
            getenv('DB_USER') ?: DB_USER,
            getenv('DB_PASS') ?: DB_PASS,
            getenv('DB_NAME') ?: DB_NAME,
            (int)(getenv('DB_PORT') ?: DB_PORT)
        );
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

if (!function_exists('itm_setup_wizard_write_lock')) {
    function itm_setup_wizard_write_lock(): bool
    {
        $payload = "installed_at=" . date('c') . "\napp_version=" . APP_VERSION . "\n";
        return file_put_contents(itm_setup_wizard_lock_path(), $payload) !== false;
    }
}

if (!function_exists('itm_setup_wizard_remove_entrypoint')) {
    /**
     * @return array{ok:bool,message:string,removed:string[]}
     */
    function itm_setup_wizard_remove_entrypoint(): array
    {
        $removed = [];
        $targets = [
            ROOT_PATH . 'setup/index.php',
            ROOT_PATH . 'setup/includes/itm_setup_wizard.php',
        ];

        foreach ($targets as $path) {
            if (is_file($path) && @unlink($path)) {
                $removed[] = str_replace(ROOT_PATH, '', $path);
            }
        }

        if (!itm_setup_wizard_write_lock()) {
            return ['ok' => false, 'message' => 'Could not write setup/.installed lock file', 'removed' => $removed];
        }

        return [
            'ok' => true,
            'message' => count($removed) > 0 ? 'Setup entry point removed' : 'Lock written (entry files already absent)',
            'removed' => $removed,
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
