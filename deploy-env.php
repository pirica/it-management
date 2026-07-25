<?php
/**
 * Shared .env reader for www deploy PHP scripts (mirrors deploy-env.ps1).
 */
declare(strict_types=1);

function deploy_env_load(string $envFilePath): void
{
    if (!is_readable($envFilePath)) {
        return;
    }

    $lines = file($envFilePath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false || $eq < 1) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

/**
 * @return array{
 *   host: string,
 *   port: int,
 *   user: string,
 *   password: string,
 *   name: string,
 *   mysql_exe: string,
 *   php_exe: string
 * }
 */
function deploy_env_database_config(string $rootDir): array
{
    $defaults = [
        'mysql_exe' => 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe',
        'php_exe' => 'C:\\Users\\NelsonSalvador\\Downloads\\laragon-portable\\bin\\php\\php-7.4.33-nts-Win32-vc15-x64\\php.exe',
    ];

    deploy_env_load($rootDir . DIRECTORY_SEPARATOR . '.env');

    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = 3306;
    if (!empty($_ENV['DB_PORT']) && ctype_digit((string) $_ENV['DB_PORT'])) {
        $port = (int) $_ENV['DB_PORT'];
    }
    if (preg_match('/^(.+):(\d+)$/', $host, $m) === 1) {
        $host = $m[1];
        if (empty($_ENV['DB_PORT']) || !ctype_digit((string) $_ENV['DB_PORT'])) {
            $port = (int) $m[2];
        }
    }

    return [
        'host' => $host,
        'port' => $port,
        'user' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? '',
        'name' => $_ENV['DB_NAME'] ?? 'itmanagement',
        'mysql_exe' => $_ENV['MYSQL_EXE'] ?? $defaults['mysql_exe'],
        'php_exe' => $_ENV['PHP_EXE'] ?? $defaults['php_exe'],
    ];
}

function deploy_env_mysql_cli_args(array $db): string
{
    return sprintf('-h %s -P %d', $db['host'], $db['port']);
}

function deploy_env_mysqli_connect(array $db): mysqli
{
    return new mysqli($db['host'], $db['user'], $db['password'], $db['name'], $db['port']);
}

/**
 * Parse plain numeric output from count_db_tables.php (ignore warnings/HTML).
 */
function deploy_env_parse_table_count_output(string $text): ?int
{
    $lines = preg_split('/\R/', trim($text)) ?: [];
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim($lines[$i]);
        if ($line !== '' && ctype_digit($line)) {
            return (int) $line;
        }
    }

    return null;
}

function deploy_env_git_repo_root(string $scriptDir): string
{
    $appRoot = deploy_env_app_root($scriptDir);
    if (is_dir($appRoot . DIRECTORY_SEPARATOR . '.git')) {
        return $appRoot;
    }
    $nested = $scriptDir . DIRECTORY_SEPARATOR . 'it-management';
    if (is_dir($nested . DIRECTORY_SEPARATOR . '.git')) {
        return $nested;
    }

    return $appRoot;
}

/**
 * @return array{0: int, 1: list<string>}
 */
function deploy_env_exec_in_dir(string $workDir, string $command): array
{
    $full = 'cd /d ' . escapeshellarg($workDir) . ' && ' . $command;
    $output = [];
    $exitCode = 0;
    exec($full . ' 2>&1', $output, $exitCode);

    return [$exitCode, $output];
}

/** Repository root (www/it-management when script lives in www). */
function deploy_env_app_root(string $scriptDir): string
{
    $schema = $scriptDir . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . '01_schema.sql';
    if (is_file($schema)) {
        return $scriptDir;
    }
    $nested = $scriptDir . DIRECTORY_SEPARATOR . 'it-management';
    $nestedSchema = $nested . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . '01_schema.sql';
    if (is_file($nestedSchema)) {
        return $nested;
    }

    return $scriptDir;
}

/** Directory that holds `.env` (www when deploy assets sit beside it-management). */
function deploy_env_config_root(string $scriptDir): string
{
    if (is_file($scriptDir . DIRECTORY_SEPARATOR . '.env')) {
        return $scriptDir;
    }
    $nested = $scriptDir . DIRECTORY_SEPARATOR . 'it-management';
    if (is_file($nested . DIRECTORY_SEPARATOR . '.env')) {
        return $nested;
    }

    return $scriptDir;
}
