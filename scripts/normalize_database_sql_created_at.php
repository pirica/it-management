<?php
/**
 * Normalize seed created_at timestamps in database.sql (INSERT data only).
 *
 * Usage: php scripts/normalize_database_sql_created_at.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$schemaPath = $root . DIRECTORY_SEPARATOR . 'database.sql';
$targetCreatedAt = '2026-01-01 00:00:01';

if (!is_file($schemaPath)) {
    fwrite(STDERR, "database.sql not found.\n");
    exit(1);
}

$sql = (string)file_get_contents($schemaPath);
if ($sql === '') {
    fwrite(STDERR, "database.sql is empty.\n");
    exit(1);
}

/**
 * @return array<int, string>
 */
function itm_sql_split_csv_values(string $valuesPart): array
{
    $values = [];
    $current = '';
    $inString = false;
    $len = strlen($valuesPart);

    for ($i = 0; $i < $len; $i++) {
        $ch = $valuesPart[$i];

        if ($inString) {
            $current .= $ch;
            if ($ch === "'") {
                if ($i + 1 < $len && $valuesPart[$i + 1] === "'") {
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
function itm_sql_join_csv_values(array $values): string
{
    return implode(', ', $values);
}

function itm_sql_is_created_at_literal(string $token): bool
{
    if (!preg_match("/^'([0-9]{4}-[0-9]{2}-[0-9]{2}(?: [0-9]{2}:[0-9]{2}:[0-9]{2})?)'$/", $token)) {
        return false;
    }

    return true;
}

function itm_sql_normalize_created_at_token(string $token, string $target): string
{
    if (!itm_sql_is_created_at_literal($token)) {
        return $token;
    }

    return "'" . $target . "'";
}

/**
 * @return string|null
 */
function itm_sql_rewrite_insert_statement(string $statement, string $targetCreatedAt): ?string
{
    if (!preg_match(
        '/\bINSERT\s+(?:IGNORE\s+)?INTO\s+`([^`]+)`\s*\(([^)]+)\)\s*VALUES\s*(.+)\s*;\s*$/is',
        $statement,
        $match
    )) {
        return null;
    }

    $columnsRaw = (string)$match[2];
    $valuesSection = trim((string)$match[3]);
    $columns = [];
    foreach (explode(',', $columnsRaw) as $columnToken) {
        $columns[] = trim(str_replace('`', '', $columnToken));
    }

    $createdIndex = array_search('created_at', $columns, true);
    if ($createdIndex === false) {
        return null;
    }

    $rows = [];
  $depth = 0;
    $rowStart = -1;
    $len = strlen($valuesSection);

    for ($i = 0; $i < $len; $i++) {
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

    $changed = false;
    $rewrittenRows = [];

    foreach ($rows as $rowValuesPart) {
        $tokens = itm_sql_split_csv_values($rowValuesPart);
        if (!array_key_exists($createdIndex, $tokens)) {
            $rewrittenRows[] = '(' . $rowValuesPart . ')';
            continue;
        }

        $newToken = itm_sql_normalize_created_at_token($tokens[$createdIndex], $targetCreatedAt);
        if ($newToken !== $tokens[$createdIndex]) {
            $tokens[$createdIndex] = $newToken;
            $changed = true;
        }

        $rewrittenRows[] = '(' . itm_sql_join_csv_values($tokens) . ')';
    }

    if (!$changed) {
        return null;
    }

    $valuesPos = stripos($statement, 'VALUES');
    if ($valuesPos === false) {
        return null;
    }
    $prefix = substr($statement, 0, $valuesPos + strlen('VALUES'));
    return rtrim($prefix) . ' ' . implode(",\n", $rewrittenRows) . ';';
}

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

    $rewritten = itm_sql_rewrite_insert_statement($statement . ';', $targetCreatedAt);
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

file_put_contents($schemaPath, $output);
echo "[OK] Updated created_at literals in {$updatedCount} INSERT statement(s).\n";
echo "Target value: {$targetCreatedAt}\n";
