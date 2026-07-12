<?php
/**
 * List ENUM Fields
 *
 * Why: Lists all database fields that are of ENUM type,
 * matching tables to modules by table name and providing blank target links.
 *
 * Browser: open while logged in as Admin.
 * CLI: php scripts/list_enum_fields.php [--source=sql|db|both]
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'scripts/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' && !itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

itm_script_output_begin('List ENUM Fields');

$sqlPath = ROOT_PATH . 'database.sql';

// CLI argument or query parameter source selection: 'sql', 'db', or 'both'
$source = 'both';
if (PHP_SAPI === 'cli') {
    $args = $argv ?? [];
    foreach ($args as $arg) {
        if (strpos($arg, '--source=') === 0) {
            $source = substr($arg, 9);
        }
    }
} else {
    $source = $_GET['source'] ?? 'both';
}

$source = strtolower($source);
if (!in_array($source, ['sql', 'db', 'both'])) {
    $source = 'both';
}

/**
 * Parses database.sql CREATE TABLE statements and filters columns.
 */
function parseSqlFields(string $sqlPath): array {
    if (!is_readable($sqlPath)) {
        return [];
    }
    $sql = (string)file_get_contents($sqlPath);
    if (!preg_match_all('/CREATE\s+TABLE\s+`([a-zA-Z0-9_]+)`\s*\((.*?)\)\s*ENGINE/is', $sql, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $results = [];
    foreach ($matches as $match) {
        $tableName = $match[1];
        $body = $match[2];

        $lines = preg_split('/\R/', $body);
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (preg_match('/^`([a-zA-Z0-9_]+)`\s+(.*)$/i', $trimmedLine, $colMatch)) {
                $colName = $colMatch[1];
                $colDef = rtrim(trim($colMatch[2]), ',');

                if (preg_match('/\benum\s*\(/i', $colDef)) {
                    $results[] = [
                        'table' => $tableName,
                        'column' => $colName,
                        'full_def' => rtrim($trimmedLine, ',')
                    ];
                }
            }
        }
    }
    return $results;
}

/**
 * Queries database and parses columns.
 */
function queryLiveDatabaseFields(mysqli $conn): array {
    $results = [];
    $tablesRes = $conn->query("SHOW TABLES");
    if (!$tablesRes) {
        return [];
    }
    while ($tableRow = $tablesRes->fetch_row()) {
        $tableName = $tableRow[0];
        $colsRes = $conn->query("SHOW COLUMNS FROM `" . $tableName . "`");
        if (!$colsRes) {
            continue;
        }
        while ($col = $colsRes->fetch_assoc()) {
            $fieldName = $col['Field'];
            $fieldType = $col['Type'];
            $isNull = $col['Null'];
            $default = $col['Default'];
            $extra = $col['Extra'];

            if (preg_match('/\benum\s*\(/i', $fieldType)) {
                $defParts = [];
                $defParts[] = "`" . $fieldName . "`";
                $defParts[] = $fieldType;
                if ($isNull === 'NO') {
                    $defParts[] = 'NOT NULL';
                }
                if ($default !== null) {
                    if ($default === 'CURRENT_TIMESTAMP') {
                        $defParts[] = "DEFAULT CURRENT_TIMESTAMP";
                    } else {
                        $defParts[] = "DEFAULT '" . $default . "'";
                    }
                } elseif ($isNull === 'YES') {
                    $defParts[] = "DEFAULT NULL";
                }
                if ($extra !== '') {
                    $defParts[] = $extra;
                }

                $results[] = [
                    'table' => $tableName,
                    'column' => $fieldName,
                    'full_def' => implode(' ', $defParts)
                ];
            }
        }
    }
    return $results;
}

/**
 * Check if modules/{tableName}/index.php exists and returns human-friendly info.
 */
function getModuleInfo(string $tableName): ?array {
    $modulesRoot = dirname(__DIR__) . '/modules/';
    $modulePath = 'modules/' . $tableName . '/index.php';
    $indexPath = $modulesRoot . $tableName . '/index.php';
    if (is_file($indexPath)) {
        $moduleName = $tableName;
        $content = (string)@file_get_contents($indexPath);
        if (preg_match('/\$crud_title\s*=\s*[\'"]([^\'"]+)[\'"]/i', $content, $m)) {
            $moduleName = $m[1];
        } else {
            $moduleName = str_replace('_', ' ', $tableName);
            $moduleName = ucwords($moduleName);
        }
        return [
            'name' => $moduleName,
            'path' => $modulePath
        ];
    }
    return null;
}

/**
 * Output a single record matching the style precisely.
 */
function outputFieldRecord(array $row, bool $isCli): void {
    $tableName = $row['table'];
    $fullDef = $row['full_def'];
    $moduleInfo = getModuleInfo($tableName);

    if ($moduleInfo !== null) {
        $moduleName = $moduleInfo['name'];
        $modulePath = $moduleInfo['path'];

        if ($isCli) {
            echo "{$moduleName} with link {$modulePath} target blank} - {$fullDef}\n";
        } else {
            $escModuleName = htmlspecialchars($moduleName, ENT_QUOTES, 'UTF-8');
            $escModulePath = htmlspecialchars($modulePath, ENT_QUOTES, 'UTF-8');
            $escFullDef = htmlspecialchars($fullDef, ENT_QUOTES, 'UTF-8');
            echo "{$escModuleName} with link <a href=\"../{$escModulePath}\" target=\"_blank\" rel=\"nofollow noreferrer\">{$escModulePath}</a> target blank} - {$escFullDef}\n";
        }
    } else {
        if ($isCli) {
            echo "{$fullDef}\n";
        } else {
            echo htmlspecialchars($fullDef, ENT_QUOTES, 'UTF-8') . "\n";
        }
    }
}

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');

if (!$isCli) {
    echo "<h2>List ENUM Fields</h2>";
    echo "<p class=\"report-muted\">Displays all ENUM fields across the database schema.</p>";
    echo "<div style='margin-bottom:15px;'>";
    echo "Source: ";
    echo "<a href='?source=both'>Both</a> | ";
    echo "<a href='?source=sql'>database.sql</a> | ";
    echo "<a href='?source=db'>Live Database</a>";
    echo "</div>";
}

if ($source === 'sql' || $source === 'both') {
    if (!$isCli) {
        echo "<h3>--- Source: database.sql ---</h3>\n";
    } else {
        echo "=== Source: database.sql ===\n";
    }
    $sqlFields = parseSqlFields($sqlPath);
    foreach ($sqlFields as $row) {
        outputFieldRecord($row, $isCli);
    }
    echo "\n";
}

if ($source === 'db' || $source === 'both') {
    if (!$isCli) {
        echo "<h3>--- Source: Live Database ---</h3>\n";
    } else {
        echo "=== Source: Live Database ===\n";
    }
    $dbFields = queryLiveDatabaseFields($conn);
    foreach ($dbFields as $row) {
        outputFieldRecord($row, $isCli);
    }
    echo "\n";
}

itm_script_output_end();
