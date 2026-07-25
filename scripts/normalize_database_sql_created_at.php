<?php
/**
 * Normalize seed created_at timestamps In db/01_schema.sql (INSERT data only).
 *
 * Usage: php scripts/normalize_database_sql_created_at.php
 *
 * Use Laragon PHP 7.4 if `php -v` shows an older CLI (PATH often points at PHP 7.0):
 * C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts/normalize_database_sql_created_at.php
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

$boot = itm_apply_script_bootstrap('Normalize created_at in db/01_schema.sql');
$nl = $boot['nl'];

$root = dirname(__DIR__);
$schemaPath = itm_database_sql_schema_path();
$targetCreatedAt = '2026-01-01 00:00:01';

if (!is_file($schemaPath)) {
    echo "db/01_schema.sql not found." . $nl;
    itm_script_output_end();
    exit(1);
}

$sql = (string)file_get_contents($schemaPath);
if ($sql === '') {
    echo 'db/01_schema.sql is empty.' . $nl;
    itm_script_output_end();
    exit(1);
}

/**
 * @return array<string, true>
 */
function itm_sql_tables_with_created_at($sql)
{
    $tables = [];
    $offset = 0;
    $len = strlen($sql);

    while ($offset < $len) {
        $found = stripos($sql, 'CREATE TABLE `', $offset);
        if ($found === false) {
            break;
        }

        $nameStart = $found + strlen('CREATE TABLE `');
        $nameEnd = strpos($sql, '`', $nameStart);
        if ($nameEnd === false) {
            break;
        }

        $tableName = substr($sql, $nameStart, $nameEnd - $nameStart);
        $bodyStart = strpos($sql, '(', $nameEnd);
        if ($bodyStart === false) {
            $offset = $nameEnd + 1;
            continue;
        }

        $enginePos = stripos($sql, 'ENGINE=', $bodyStart);
        if ($enginePos === false) {
            $offset = $bodyStart + 1;
            continue;
        }

        $body = substr($sql, $bodyStart, $enginePos - $bodyStart);
        if (preg_match('/`created_at`\s+/i', $body)) {
            $tables[$tableName] = true;
        }

        $offset = $enginePos + 1;
    }

    return $tables;
}

/**
 * @return array<int, string>
 */
function itm_sql_split_csv_values($valuesPart)
{
    $values = [];
    $current = '';
    $inString = false;
    $partLen = strlen($valuesPart);

    for ($i = 0; $i < $partLen; $i++) {
        $ch = $valuesPart[$i];

        if ($inString) {
            $current .= $ch;
            if ($ch === "'") {
                if ($i + 1 < $partLen && $valuesPart[$i + 1] === "'") {
                    $current .= $valuesPart[++$i];
                    continue;
                }
                $inString = false;
            }
            continue;
        }

        if ($ch === "'") {
            $inString = true;
            $current .= $ch;
            continue;
        }

        if ($ch === ',') {
            $values[] = trim($current);
            $current = '';
            continue;
        }

        $current .= $ch;
    }

    if (trim($current) !== '') {
        $values[] = trim($current);
    }

    return $values;
}

/**
 * @param array<int, string> $values
 */
function itm_sql_join_csv_values(array $values)
{
    return implode(', ', $values);
}

/**
 * @param array<int, string> $columns
 * @return array<int, string>
 */
function itm_sql_columns_append_created_at(array $columns)
{
    if (in_array('created_at', $columns, true)) {
        return $columns;
    }

    $updatedIndex = array_search('updated_at', $columns, true);
    if ($updatedIndex !== false) {
        array_splice($columns, $updatedIndex, 0, ['created_at']);
        return $columns;
    }

    $columns[] = 'created_at';
    return $columns;
}

/**
 * @param array<int, string> $columns
 */
function itm_sql_format_column_list(array $columns)
{
    return '`' . implode('`, `', $columns) . '`';
}

function itm_sql_is_created_at_literal($token)
{
    if (!preg_match("/^'([0-9]{4}-[0-9]{2}-[0-9]{2}(?: [0-9]{2}:[0-9]{2}:[0-9]{2})?)'$/", $token)) {
        return false;
    }

    return true;
}

function itm_sql_should_normalize_created_at_token($token)
{
    $upper = strtoupper(trim($token));
    if ($upper === 'NULL' || $upper === 'CURRENT_TIMESTAMP') {
        return true;
    }

    return itm_sql_is_created_at_literal($token);
}

function itm_sql_normalize_created_at_token($token, $target)
{
    if (!itm_sql_should_normalize_created_at_token($token)) {
        return $token;
    }

    return "'" . $target . "'";
}

function itm_sql_is_seed_insert_statement($statement)
{
    if (stripos($statement, 'NEW.') !== false || stripos($statement, 'OLD.') !== false) {
        return false;
    }

    if (strpos($statement, '@app_employee_id') !== false || strpos($statement, 'JSON_OBJECT') !== false) {
        return false;
    }

    return true;
}

/**
 * @param array<string, true> $tablesWithCreatedAt
 * @return string|null
 */
function itm_sql_rewrite_insert_values_statement($statement, $targetCreatedAt, array $tablesWithCreatedAt)
{
    if (!itm_sql_is_seed_insert_statement($statement)) {
        return null;
    }

    if (!preg_match(
        '/\b(INSERT\s+(?:IGNORE\s+)?INTO\s+`[^`]+`\s*\([^)]+\))\s*VALUES\s*(.+)\s*;\s*$/is',
        $statement,
        $match
    )) {
        return null;
    }

    if (!preg_match(
        '/\bINSERT\s+(?:IGNORE\s+)?INTO\s+`([^`]+)`\s*\(([^)]+)\)\s*VALUES\s*(.+)\s*;\s*$/is',
        $statement,
        $parts
    )) {
        return null;
    }

    $insertHead = (string)$match[1];
    $tableName = (string)$parts[1];
    if (!isset($tablesWithCreatedAt[$tableName])) {
        return null;
    }

    $columnsRaw = (string)$parts[2];
    $valuesSection = trim((string)$parts[3]);
    $columns = [];
    foreach (explode(',', $columnsRaw) as $columnToken) {
        $columns[] = trim(str_replace('`', '', $columnToken));
    }

    $rows = [];
    $depth = 0;
    $rowStart = -1;
    $sectionLen = strlen($valuesSection);

    for ($i = 0; $i < $sectionLen; $i++) {
        $ch = $valuesSection[$i];
        if ($ch === '(') {
            if ($depth === 0) {
                $rowStart = $i + 1;
            }
            $depth++;
            continue;
        }
        if ($ch === ')') {
            $depth--;
            if ($depth === 0 && $rowStart >= 0) {
                $rows[] = substr($valuesSection, $rowStart, $i - $rowStart);
                $rowStart = -1;
            }
        }
    }

    if ($rows === []) {
        return null;
    }

    $hadCreatedAt = in_array('created_at', $columns, true);
    if (!$hadCreatedAt) {
        $columns = itm_sql_columns_append_created_at($columns);
    }

    $createdIndex = array_search('created_at', $columns, true);
    if ($createdIndex === false) {
        return null;
    }

    $targetLiteral = "'" . $targetCreatedAt . "'";
    $changed = false;
    $rewrittenRows = [];

    foreach ($rows as $rowValuesPart) {
        $tokens = itm_sql_split_csv_values($rowValuesPart);

        if (!$hadCreatedAt) {
            if (count($tokens) === $createdIndex) {
                $tokens[] = $targetLiteral;
            } else {
                array_splice($tokens, $createdIndex, 0, [$targetLiteral]);
            }
            $changed = true;
        } elseif (!array_key_exists($createdIndex, $tokens)) {
            $rewrittenRows[] = '(' . $rowValuesPart . ')';
            continue;
        } else {
            $newToken = itm_sql_normalize_created_at_token($tokens[$createdIndex], $targetCreatedAt);
            if ($newToken !== $tokens[$createdIndex]) {
                $tokens[$createdIndex] = $newToken;
                $changed = true;
            }
        }

        $rewrittenRows[] = '(' . itm_sql_join_csv_values($tokens) . ')';
    }

    if (!$changed) {
        return null;
    }

    if (!preg_match('/\bINSERT\s+(?:IGNORE\s+)?INTO\s+`[^`]+`/i', $statement, $insertMatch, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $ignore = (stripos($statement, 'INSERT IGNORE') === 0) ? 'INSERT IGNORE' : 'INSERT';
    $prefix = $ignore . ' INTO `' . $tableName . '` (' . itm_sql_format_column_list($columns) . ') VALUES';
    return rtrim($prefix) . ' ' . implode(",\n", $rewrittenRows) . ';';
}

/**
 * @param array<string, true> $tablesWithCreatedAt
 * @return string|null
 */
function itm_sql_rewrite_insert_select_statement($statement, $targetCreatedAt, array $tablesWithCreatedAt)
{
    if (!itm_sql_is_seed_insert_statement($statement)) {
        return null;
    }

    if (!preg_match(
        '/\b(INSERT\s+(?:IGNORE\s+)?INTO\s+`[^`]+`\s*\([^)]+\))\s+SELECT\s+(.+?)\s*;\s*$/is',
        $statement,
        $match
    )) {
        return null;
    }

    if (!preg_match(
        '/\bINSERT\s+(?:IGNORE\s+)?INTO\s+`([^`]+)`\s*\(([^)]+)\)\s+SELECT\s+(.+?)\s*;\s*$/is',
        $statement,
        $parts
    )) {
        return null;
    }

    $tableName = (string)$parts[1];
    if (!isset($tablesWithCreatedAt[$tableName])) {
        return null;
    }

    $columns = [];
    foreach (explode(',', (string)$parts[2]) as $columnToken) {
        $columns[] = trim(str_replace('`', '', $columnToken));
    }

    if (in_array('created_at', $columns, true)) {
        return null;
    }

    $selectExpr = trim((string)$parts[3]);
    if (strpos($selectExpr, "\n") !== false || strpos($selectExpr, "\r") !== false) {
        return null;
    }

    if (substr_count(strtoupper($selectExpr), ' FROM ') !== 1) {
        return null;
    }

    $fromPos = stripos($selectExpr, ' FROM ');
    if ($fromPos === false) {
        return null;
    }

    $selectList = trim(substr($selectExpr, 0, $fromPos));
    $fromClause = substr($selectExpr, $fromPos);
    $columns = itm_sql_columns_append_created_at($columns);
    $targetLiteral = "'" . $targetCreatedAt . "'";

    $ignore = (stripos($statement, 'INSERT IGNORE') === 0) ? 'INSERT IGNORE' : 'INSERT';
    $prefix = $ignore . ' INTO `' . $tableName . '` (' . itm_sql_format_column_list($columns) . ') SELECT ';
    return $prefix . $selectList . ', ' . $targetLiteral . $fromClause . ';';
}

$tablesWithCreatedAt = itm_sql_tables_with_created_at($sql);

// Why: do not split on the semicolon in "DELIMITER ;" (restore after rebuild).
$sqlProtected = preg_replace('/^DELIMITER\s+;\s*$/mi', 'DELIMITER __ITM_SEMICOLON__', $sql) ?? $sql;
$parts = preg_split('/;\s*\n/', $sqlProtected);
if (!is_array($parts)) {
    fwrite(STDERR, "Unable to split SQL statements.\n");
    exit(1);
}

$updatedCount = 0;
$rebuilt = [];

foreach ($parts as $index => $part) {
    $statement = trim($part);
    if ($statement === '') {
        $rebuilt[] = '';
        continue;
    }

    $statementWithSemi = $statement . ';';
    $rewritten = itm_sql_rewrite_insert_values_statement($statementWithSemi, $targetCreatedAt, $tablesWithCreatedAt);
    if ($rewritten === null) {
        $rewritten = itm_sql_rewrite_insert_select_statement($statementWithSemi, $targetCreatedAt, $tablesWithCreatedAt);
    }

    if ($rewritten !== null) {
        $updatedCount++;
        $rebuilt[] = rtrim($rewritten, ';');
        continue;
    }

    $rebuilt[] = $statement;
}

$output = implode(";\n", $rebuilt);
if ($output !== '' && substr($sql, -1) === ';') {
    $output .= ';';
}

$output = str_replace('DELIMITER __ITM_SEMICOLON__', 'DELIMITER ;', $output);
$output = preg_replace('/^DELIMITER;\s*$/m', 'DELIMITER ;', $output) ?? $output;

if ($boot['apply']) {
    file_put_contents($schemaPath, $output);
    echo colorText("[OK] Updated created_at in {$updatedCount} INSERT statement(s).", 'pass') . $nl;
} else {
    echo colorText("[DRY-RUN] Would update created_at in {$updatedCount} INSERT statement(s).", 'info') . $nl;
}
echo "Target value: {$targetCreatedAt}" . $nl;
echo 'Tables with created_at column: ' . count($tablesWithCreatedAt) . $nl;

itm_apply_script_finish_hint($boot['apply'], $boot['is_cli'], $updatedCount, $nl, 'normalize_database_sql_created_at.php');
itm_script_output_end();
