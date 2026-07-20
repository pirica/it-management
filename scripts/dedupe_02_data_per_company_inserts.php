<?php
/**
 * List or remove redundant per-company INSERT blocks (companies 2–5) in db/02_data.sql
 * when an INSERT IGNORE … @replicate_source_company_id replication line already exists.
 *
 * Browser + CLI; dry-run by default. Writes with --apply or browser ?apply=1 (Admin).
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$ctx = itm_apply_script_bootstrap('Dedupe 02_data per-company INSERTs');
$apply = $ctx['apply'];
$nl = $ctx['nl'];

$sourcePath = itm_database_sql_data_path();
if (!is_readable($sourcePath)) {
    echo '[FAIL] Source not readable: ' . $sourcePath . $nl;
    exit(1);
}

$sqlBody = file_get_contents($sourcePath);
if ($sqlBody === false || $sqlBody === '') {
    echo '[FAIL] db/02_data.sql is empty.' . $nl;
    exit(1);
}

$replicatedTables = [];
if (preg_match_all(
    '/INSERT\\s+IGNORE\\s+INTO\\s+`([^`]+)`\\s*\\([^)]+\\)\\s*SELECT.+?@replicate_source_company_id/is',
    $sqlBody,
    $replicationMatches
)) {
    foreach ($replicationMatches[1] as $tableName) {
        if (itm_is_safe_identifier($tableName)) {
            $replicatedTables[$tableName] = true;
        }
    }
}

if ($replicatedTables === []) {
    echo '[FAIL] No replication blocks found in db/02_data.sql.' . $nl;
    exit(1);
}

if (!function_exists('itm_parse_database_sql_inserts')) {
    echo '[FAIL] itm_parse_database_sql_inserts() unavailable.' . $nl;
    exit(1);
}

$parsed = itm_parse_database_sql_inserts($sqlBody);
$removableStatements = [];
$removableByTable = [];

foreach ($parsed as $tableName => $insertRows) {
    if (!isset($replicatedTables[$tableName])) {
        continue;
    }

    foreach ($insertRows as $rowEntry) {
        $rawColumns = $rowEntry['columns'] ?? [];
        $rawValues = $rowEntry['values'] ?? [];
        $companyIndex = null;
        foreach ($rawColumns as $index => $columnToken) {
            $columnName = trim((string)$columnToken, "` \t\n\r\0\x0B");
            if ($columnName === 'company_id') {
                $companyIndex = $index;
                break;
            }
        }
        if ($companyIndex === null) {
            continue;
        }

        $rawCompanyToken = trim((string)($rawValues[$companyIndex] ?? ''));
        $rawCompanyToken = trim($rawCompanyToken, "'\"");
        $rowCompanyId = (int)$rawCompanyToken;
        if ($rowCompanyId < 2 || $rowCompanyId > 5) {
            continue;
        }

        $columnsSql = implode(', ', array_map(static function ($col): string {
            $name = trim((string)$col, "` \t\n\r\0\x0B");
            return '`' . str_replace('`', '``', $name) . '`';
        }, $rawColumns));
        $valuesSql = '(' . implode(', ', $rawValues) . ')';
        $statement = 'INSERT INTO `' . str_replace('`', '``', $tableName) . '` (' . $columnsSql . ') VALUES ' . $valuesSql . ';';

        $removableStatements[] = $statement;
        if (!isset($removableByTable[$tableName])) {
            $removableByTable[$tableName] = 0;
        }
        $removableByTable[$tableName]++;
    }
}

echo 'Replication tables: ' . count($replicatedTables) . $nl;
echo 'Removable company 2–5 INSERT statements: ' . count($removableStatements) . $nl;
foreach ($removableByTable as $table => $count) {
    echo '  - ' . $table . ': ' . $count . $nl;
}

if ($removableStatements === []) {
    echo '[OK] Nothing to dedupe.' . $nl;
    exit(0);
}

if (!$apply) {
    echo $nl . 'Dry-run — pass --apply to remove listed statements from db/02_data.sql.' . $nl;
    exit(0);
}

$updated = $sqlBody;
$removed = 0;
foreach ($removableStatements as $statement) {
    $pos = strpos($updated, $statement);
    if ($pos === false) {
        continue;
    }
    $updated = substr_replace($updated, '', $pos, strlen($statement));
    $removed++;
}

$updated = preg_replace("/\r?\n{3,}/", "\n\n", $updated);
if ($updated === null) {
    echo '[FAIL] Could not normalize SQL after dedupe.' . $nl;
    exit(1);
}

if (file_put_contents($sourcePath, $updated) === false) {
    echo '[FAIL] Could not write ' . $sourcePath . $nl;
    exit(1);
}

echo '[OK] Removed ' . $removed . ' redundant INSERT statement(s) from db/02_data.sql.' . $nl;
exit(0);
