<?php
/**
 * UTF-8-safe import of db/ split bundle (Windows-friendly alternative to bash).
 *
 * CLI: php scripts/import_database_split.php
 * Reads db/01_schema.sql → 02_data.sql → 03_triggers.sql as UTF-8 and pipes to mysql
 * with --default-character-set=utf8mb4 (avoids PowerShell Get-Content encoding loss).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/import_database_split.php</code> — Admin browser or CLI. Use on Windows instead of piping <code>Get-Content db/*.sql</code> to mysql (that corrupts emoji seeds to <code>????</code>).
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
} else {
    define('ITM_CLI_SCRIPT', true);
    require_once dirname(__DIR__) . '/config/config.php';
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin('Import database split (UTF-8)');

$nl = itm_script_output_nl();

if (PHP_SAPI !== 'cli') {
    itm_script_require_admin_script_or_exit($conn);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Import DB UTF-8</title></head><body><pre>';
}

$root = dirname(__DIR__);
$files = [
    $root . '/db/01_schema.sql',
    $root . '/db/02_data.sql',
    $root . '/db/03_triggers.sql',
];

foreach ($files as $file) {
    if (!is_file($file)) {
        echo itm_script_format_status_line('[FAIL] Missing ' . $file) . $nl;
        itm_script_output_end();
        exit(1);
    }
}

$host = getenv('MYSQL_HOST') ?: '127.0.0.1';
$port = getenv('MYSQL_PORT') ?: '3307';
$user = getenv('MYSQL_USER') ?: 'root';
$password = getenv('MYSQL_PASSWORD') ?: 'itmanagement';
if (is_file($root . '/.env')) {
    foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos($line, 'DB_PASS=') === 0) {
            $password = trim(substr($line, 8), " \t\"'");
        }
        if (strpos($line, 'DB_PORT=') === 0) {
            $port = trim(substr($line, 8));
        }
    }
}

$bundle = '';
foreach ($files as $file) {
    $chunk = file_get_contents($file);
    if ($chunk === false) {
        echo itm_script_format_status_line('[FAIL] Could not read ' . $file) . $nl;
        itm_script_output_end();
        exit(1);
    }
    $bundle .= $chunk . "\n";
}

$tmp = tempnam(sys_get_temp_dir(), 'itm_db_import_');
if ($tmp === false) {
    echo itm_script_format_status_line('[FAIL] Could not create temp file.') . $nl;
    itm_script_output_end();
    exit(1);
}
file_put_contents($tmp, $bundle);

$mysqlBin = getenv('MYSQL_BIN') ?: '';
if ($mysqlBin === '' && is_file('D:/dunebox-v1.0.6/system/apps/mysql/mysql-8.0.45-winx64/bin/mysql.exe')) {
    $mysqlBin = 'D:/dunebox-v1.0.6/system/apps/mysql/mysql-8.0.45-winx64/bin/mysql.exe';
}
if ($mysqlBin === '') {
    $mysqlBin = 'mysql';
}

$cmd = sprintf(
    '%s -h %s -P %s -u %s -p%s --default-character-set=utf8mb4 itmanagement < %s',
    escapeshellarg($mysqlBin),
    escapeshellarg($host),
    escapeshellarg($port),
    escapeshellarg($user),
    escapeshellarg($password),
    escapeshellarg($tmp)
);

if (PHP_SAPI === 'win32' || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $cmd = 'cmd /c ' . $cmd;
}

echo itm_script_format_status_line('[INFO] Importing via ' . $mysqlBin . ' (UTF-8 bundle ' . strlen($bundle) . ' bytes)') . $nl;
passthru($cmd, $exitCode);
@unlink($tmp);

if ($exitCode !== 0) {
    echo itm_script_format_status_line('[FAIL] mysql import exited ' . (int)$exitCode) . $nl;
    itm_script_output_end();
    exit(1);
}

echo itm_script_format_status_line('[PASS] Split bundle imported with UTF-8 preserved.') . $nl;
echo itm_script_format_status_line('[INFO] Run php scripts/verify_database_schema.php to validate table count.') . $nl;

if (PHP_SAPI !== 'cli') {
    echo '</pre></body></html>';
}

itm_script_output_end();
exit(0);
