<?php
/**
 * Identify tables in database.sql that contain phone-related columns.
 *
 * Why: Useful for auditing PII (Personally Identifiable Information) coverage
 * and ensuring consistent phone number formatting across the system.
 *
 * Browser: open scripts/check_phones.php (login required).
 * CLI: php scripts/check_phones.php
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli' && !itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

itm_script_output_begin('Check Phones');
$nl = itm_script_output_nl();

$sqlPath = dirname(__DIR__) . '/database.sql';
if (!is_file($sqlPath)) {
    echo "Error: database.sql not found at $sqlPath" . $nl;
    exit(1);
}

$content = file_get_contents($sqlPath);
$lines = explode("\n", $content);
$currentTable = null;
$tables = [];

foreach ($lines as $line) {
    if (preg_match('/CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
        $tables[$currentTable] = [];
    }
    if ($currentTable && preg_match('/^\s+`([^`]+)`/', $line, $matches)) {
        $tables[$currentTable][] = $matches[1];
    }
    if (strpos($line, ');') !== false && strpos($line, 'ENGINE=') !== false) {
        $currentTable = null;
    }
}

$foundCount = 0;
foreach ($tables as $table => $cols) {
    $matched = [];
    if (in_array('phone', $cols)) $matched[] = 'phone';
    if (in_array('external_number', $cols)) $matched[] = 'external_number';

    if (!empty($matched)) {
        echo "Table $table has: " . implode(', ', $matched) . $nl;
        $foundCount++;
    }
}

echo "Total tables with phone columns: $foundCount" . $nl;
