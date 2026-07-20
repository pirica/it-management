<?php
/**
 * Apply module/table sample seed rows to db/ across all seeded companies.
 *
 * Why: idf_device_type sample rows were added manually across companies. This script automates
 * that same process per module/table so new sample values can be propagated safely.
 *
 * Usage (repository root):
 *   php scripts/apply_module_sample_data_seed.php --module=idf_device_type
 *   php scripts/apply_module_sample_data_seed.php --module=idf_device_type --dry-run
 *   php scripts/apply_module_sample_data_seed.php --table=equipment_poe --value-column=name --sample=POE+ --sample=POE++
 *
 * Browser: scripts/apply_module_sample_data_seed.php?module=idf_device_type (dry-run default)
 * Browser apply: scripts/apply_module_sample_data_seed.php?module=idf_device_type&apply=1
 */

declare(strict_types=1);

/**
 * Write error output in CLI (STDERR) or browser (<pre> from apply bootstrap).
 */
function itm_seed_fwrite_stderr(string $message): void
{
    if (defined('STDERR') && is_resource(STDERR)) {
        fwrite(STDERR, $message);
        return;
    }

    $line = rtrim($message, "\r\n");
    if (function_exists('colorText') && function_exists('itm_script_output_nl')) {
        echo colorText($line, 'fail') . itm_script_output_nl();
        return;
    }

    echo $line . (PHP_SAPI === 'cli' ? PHP_EOL : "<br>\n");
}

/**
 * @return array{module:string,table:string,value_column:string,emoji_column:string,dry_run:bool,samples:array<int,array{value:string,emoji:string}>}
 */
function itm_seed_parse_args(array $argv): array
{
    $result = [
        'module' => '',
        'table' => '',
        'value_column' => '',
        'emoji_column' => '',
        'dry_run' => false,
        'samples' => [],
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $result['dry_run'] = true;
            continue;
        }
        if ($arg === '--help' || $arg === '-h') {
            echo itm_seed_usage();
            exit(0);
        }
        if (strpos($arg, '--module=') === 0) {
            $result['module'] = trim(substr($arg, 9));
            continue;
        }
        if (strpos($arg, '--table=') === 0) {
            $result['table'] = trim(substr($arg, 8));
            continue;
        }
        if (strpos($arg, '--value-column=') === 0) {
            $result['value_column'] = trim(substr($arg, 15));
            continue;
        }
        if (strpos($arg, '--emoji-column=') === 0) {
            $result['emoji_column'] = trim(substr($arg, 15));
            continue;
        }
        if (strpos($arg, '--sample=') === 0) {
            $raw = trim(substr($arg, 9));
            if ($raw === '') {
                itm_seed_fwrite_stderr("Empty --sample value is not allowed.\n");
                exit(2);
            }

            $name = $raw;
            $emoji = '';
            $sepPos = strpos($raw, ':');
            if ($sepPos !== false) {
                $name = trim(substr($raw, 0, $sepPos));
                $emoji = trim(substr($raw, $sepPos + 1));
            }
            if ($name === '') {
                itm_seed_fwrite_stderr("Invalid --sample format. Use --sample=name or --sample=name:emoji\n");
                exit(2);
            }

            $result['samples'][] = ['value' => $name, 'emoji' => $emoji];
            continue;
        }

        itm_seed_fwrite_stderr("Unknown option: {$arg}\n");
        itm_seed_fwrite_stderr(itm_seed_usage());
        exit(2);
    }

    return $result;
}

/**
 * Build pseudo-argv from browser query string for itm_seed_parse_args().
 *
 * @return array<int, string>
 */
function itm_seed_browser_argv(): array
{
    $args = [];
    foreach (['module', 'table', 'value-column', 'emoji-column'] as $key) {
        if (isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') {
            $args[] = '--' . $key . '=' . trim((string)$_GET[$key]);
        }
    }
    if (isset($_GET['sample'])) {
        $samples = $_GET['sample'];
        if (!is_array($samples)) {
            $samples = [$samples];
        }
        foreach ($samples as $sample) {
            $sample = trim((string)$sample);
            if ($sample !== '') {
                $args[] = '--sample=' . $sample;
            }
        }
    }
    return $args;
}

function itm_seed_usage(): string
{
    return <<<TXT
Usage:
  php scripts/apply_module_sample_data_seed.php --module=<module_name> [options]
  php scripts/apply_module_sample_data_seed.php --table=<table_name> --value-column=<column> --sample=<value> [--sample=<value2>] [options]

Options:
  --module=<name>         Module/table name (e.g. idf_device_type). If --table is missing, table = module.
  --table=<name>          Database table in db/.
  --value-column=<name>   Column used for uniqueness per company (default: idfdevicetype_name, then name).
  --emoji-column=<name>   Optional column for emoji/value metadata (default: field_edit_emoji when present).
  --sample=<name>         Sample value to add for every company (repeatable).
  --sample=<name:emoji>   Sample value plus emoji in one option (repeatable).
  --dry-run               Preview changes; do not write db/02_data.sql (default).
  --apply                 Write db/02_data.sql (CLI only; browser uses ?apply=1, Admin required).
  --help                  Show this help.

How to use it in the browser:
  Dry-run (preview):
    http://localhost/it-management/scripts/apply_module_sample_data_seed.php?module=idf_device_type

  Apply (Admin, writes db/):
    http://localhost/it-management/scripts/apply_module_sample_data_seed.php?module=idf_device_type&apply=1

Defaults:
  For --module=idf_device_type with no --sample options, the script applies:
    other:📦, server:🖥️, ups:🔋, patch_panel:➿, switch:🔀, firewall:🛡️, router:📡, pdu:🔌

Mirror INSERT … SELECT (e.g. knowledge_base):
  When db/ copies tenant rows via SELECT N, cols FROM table WHERE company_id = 1,
  new samples are added only to the source company VALUES block; other tenants replicate on import.
  Use --sample=title or --sample=title:content for tables with a title/content pair.

TXT;
}

/**
 * @return array<int, string>
 */
function itm_seed_split_csv_values(string $valuesPart): array
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

    $trimmed = trim($current);
    if ($trimmed !== '') {
        $values[] = $trimmed;
    }

    return $values;
}

function itm_seed_sql_unquote(string $token): string
{
    $token = trim($token);
    if (preg_match("/^'(.*)'$/s", $token, $m)) {
        return str_replace("''", "'", (string)$m[1]);
    }
    if (strcasecmp($token, 'NULL') === 0) {
        return '';
    }
    return trim($token, "` \t\n\r\0\x0B");
}

function itm_seed_sql_quote(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

/**
 * @return array<int, int>
 */
function itm_seed_extract_company_ids(string $sql): array
{
    $ids = [];
    if (!preg_match_all('/^INSERT INTO `companies` \(([^)]+)\) VALUES \((.+)\);\s*$/m', $sql, $matches, PREG_SET_ORDER)) {
        return [];
    }

    foreach ($matches as $match) {
        $columnsRaw = (string)$match[1];
        $valuesRaw = (string)$match[2];
        $columns = array_map(static function ($column): string {
            return trim(str_replace('`', '', $column));
        }, explode(',', $columnsRaw));
        $tokens = itm_seed_split_csv_values($valuesRaw);
        if (count($columns) !== count($tokens)) {
            continue;
        }
        $row = array_combine($columns, $tokens);
        if (!is_array($row) || !isset($row['id'])) {
            continue;
        }
        $id = (int)itm_seed_sql_unquote((string)$row['id']);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    ksort($ids);
    return array_values($ids);
}

/**
 * @return array<string,array{not_null:bool,has_default:bool,auto_increment:bool,generated:bool}>
 */
function itm_seed_collect_column_specs(string $createBody): array
{
    $specs = [];
    $lines = preg_split('/\R/', $createBody);
    if (!is_array($lines)) {
        return $specs;
    }

    foreach ($lines as $line) {
        $trimmed = trim((string)$line);
        if ($trimmed === '' || strpos($trimmed, '`') !== 0) {
            continue;
        }
        if (!preg_match('/^`([^`]+)`\s+(.*)$/', $trimmed, $match)) {
            continue;
        }

        $columnName = strtolower((string)$match[1]);
        $definition = strtoupper(rtrim((string)$match[2], ", \t"));
        $specs[$columnName] = [
            'not_null' => strpos($definition, 'NOT NULL') !== false,
            'has_default' => strpos($definition, 'DEFAULT ') !== false,
            'auto_increment' => strpos($definition, 'AUTO_INCREMENT') !== false,
            'generated' => strpos($definition, ' GENERATED ') !== false || strpos($definition, ' AS (') !== false,
        ];
    }

    return $specs;
}

/**
 * @return array{columns:array<int,string>,column_specs:array<string,array{not_null:bool,has_default:bool,auto_increment:bool,generated:bool}>,create_start:int,create_end:int,engine_start:int,engine_end:int}|null
 */
function itm_seed_find_table_metadata(string $sql, string $table): ?array
{
    $escaped = preg_quote($table, '/');
    if (!preg_match('/CREATE TABLE `' . $escaped . '` \((.*?)\)\s*ENGINE=InnoDB([^;]*);/s', $sql, $match, PREG_OFFSET_CAPTURE)) {
        return null;
    }

    $full = $match[0][0];
    $fullStart = (int)$match[0][1];
    $fullEnd = $fullStart + strlen($full);

    $body = (string)$match[1][0];
    if (!preg_match_all('/^\s*`([^`]+)`\s+/m', $body, $columnsMatch)) {
        return null;
    }
    $columns = $columnsMatch[1];

    $enginePos = strpos($full, 'ENGINE=InnoDB');
    if ($enginePos === false) {
        return null;
    }
    $engineStart = $fullStart + $enginePos;
    $engineEnd = $fullEnd;

    return [
        'columns' => $columns,
        'column_specs' => itm_seed_collect_column_specs($body),
        'create_start' => $fullStart,
        'create_end' => $fullEnd,
        'engine_start' => $engineStart,
        'engine_end' => $engineEnd,
    ];
}

/**
 * Find the terminating semicolon for an INSERT … VALUES block (not inside a string).
 */
function itm_seed_find_values_statement_end(string $sql, int $valuesStart): ?int
{
    $len = strlen($sql);
    $inString = false;

    for ($i = $valuesStart; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($inString) {
            if ($ch === "'") {
                if ($i + 1 < $len && $sql[$i + 1] === "'") {
                    $i++;
                    continue;
                }
                $inString = false;
            }
            continue;
        }

        if ($ch === "'") {
            $inString = true;
            continue;
        }

        if ($ch === ';') {
            return $i;
        }
    }

    return null;
}

/**
 * @return array<int, string>
 */
function itm_seed_split_value_tuples(string $valuesPart): array
{
    $valuesPart = trim($valuesPart);
    if ($valuesPart === '') {
        return [];
    }

    $tuples = [];
    $current = '';
    $depth = 0;
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

        if ($ch === '(') {
            $depth++;
            $current .= $ch;
            continue;
        }

        if ($ch === ')') {
            $depth--;
            $current .= $ch;
            if ($depth === 0) {
                $trimmed = trim($current);
                if ($trimmed !== '' && $trimmed[0] === '(' && substr($trimmed, -1) === ')') {
                    $tuples[] = substr($trimmed, 1, -1);
                }
                $current = '';
            }
            continue;
        }

        if ($depth > 0) {
            $current .= $ch;
        }
    }

    return $tuples;
}

/**
 * @param array<int, array{columns:array<int,string>,values:array<int,string>}> $insertRows
 */
function itm_seed_column_uses_unquoted_numeric_tokens(array $insertRows, string $columnName): bool
{
    foreach ($insertRows as $insertRow) {
        $columnIndex = array_search($columnName, $insertRow['columns'], true);
        if ($columnIndex === false) {
            continue;
        }
        $token = trim((string)$insertRow['values'][$columnIndex]);
        if ($token === '' || strcasecmp($token, 'NULL') === 0) {
            continue;
        }
        if ($token[0] !== "'" && $token[0] !== '`') {
            return true;
        }
    }

    return false;
}

/**
 * @param array<int, array{columns:array<int,string>,values:array<int,string>}> $insertRows
 */
function itm_seed_column_uses_null_tokens(array $insertRows, string $columnName): bool
{
    foreach ($insertRows as $insertRow) {
        $columnIndex = array_search($columnName, $insertRow['columns'], true);
        if ($columnIndex === false) {
            continue;
        }
        if (strcasecmp(trim((string)$insertRow['values'][$columnIndex]), 'NULL') === 0) {
            return true;
        }
    }

    return false;
}

function itm_seed_format_sql_token($value, bool $unquotedNumeric): string
{
    if ($value === null) {
        return 'NULL';
    }
    $stringValue = (string)$value;
    if ($stringValue === '') {
        return 'NULL';
    }
    if ($unquotedNumeric && is_numeric($stringValue)) {
        return (string)(int)$stringValue;
    }

    return itm_seed_sql_quote($stringValue);
}

/**
 * @return array{
 *   rows:array<int,array{columns:array<int,string>,values:array<int,string>}>,
 *   last_insert_end:int,
 *   last_block:array{columns:array<int,string>,values_end:int,multi:bool}|null
 * }
 */
function itm_seed_collect_insert_rows(string $sql, string $table): array
{
    $rows = [];
    $lastInsertEnd = -1;
    $lastBlock = null;
    $pattern = '/INSERT INTO `' . preg_quote($table, '/') . '` \(([^)]+)\) VALUES\s*/i';
    $offset = 0;

    while (preg_match($pattern, $sql, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $blockStart = (int)$match[0][1];
        $columnsRaw = (string)$match[1][0];
        $valuesStart = $blockStart + strlen((string)$match[0][0]);
        $statementEnd = itm_seed_find_values_statement_end($sql, $valuesStart);
        if ($statementEnd === null) {
            break;
        }

        $columns = array_map(static function ($column): string {
            return trim(str_replace('`', '', $column));
        }, explode(',', $columnsRaw));

        $valuesPart = substr($sql, $valuesStart, $statementEnd - $valuesStart);
        $tuples = itm_seed_split_value_tuples((string)$valuesPart);
        if ($tuples === []) {
            $offset = $statementEnd + 1;
            continue;
        }

        foreach ($tuples as $tuple) {
            $values = itm_seed_split_csv_values($tuple);
            if (count($columns) !== count($values)) {
                continue;
            }
            $rows[] = [
                'columns' => $columns,
                'values' => $values,
            ];
        }

        $lastInsertEnd = $statementEnd + 1;
        $lastBlock = [
            'columns' => $columns,
            'values_end' => $statementEnd,
            'multi' => count($tuples) > 1 || strpos((string)$valuesPart, "\n") !== false,
        ];
        $offset = $statementEnd + 1;
    }

    return [
        'rows' => $rows,
        'last_insert_end' => $lastInsertEnd,
        'last_block' => $lastBlock,
    ];
}

/**
 * Detect knowledge_base-style mirror blocks:
 * INSERT INTO `t` (cols) SELECT {target_id}, col2, ... FROM `t` WHERE company_id = {source_id};
 *
 * @return array{source_company_id:int,target_company_ids:array<int,int>,insert_columns:array<int,string>}|null
 */
function itm_seed_detect_mirror_selects(string $sql, string $table): ?array
{
    $escaped = preg_quote($table, '/');
    $pattern = '/INSERT\s+INTO\s+`' . $escaped . '`\s*\(([^)]+)\)\s*SELECT\s+(\d+)\s*,\s*([^;]+?)\s+FROM\s+`?' . $escaped . '`?\s+WHERE\s+company_id\s*=\s*(\d+)\s*;/is';

    if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
        return null;
    }

    $sourceCompanyId = null;
    $targetCompanyIds = [];
    $insertColumns = null;

    foreach ($matches as $match) {
        $columns = array_map(static function ($column): string {
            return trim(str_replace('`', '', $column));
        }, explode(',', (string)$match[1]));

        $targetId = (int)$match[2];
        $selectTail = trim((string)$match[3]);
        $sourceId = (int)$match[4];

        if ($columns === [] || strcasecmp($columns[0], 'company_id') !== 0) {
            return null;
        }

        $expectedSelectCols = array_slice($columns, 1);
        $actualSelectCols = array_map('trim', explode(',', $selectTail));
        if ($actualSelectCols !== $expectedSelectCols) {
            return null;
        }

        if ($insertColumns === null) {
            $insertColumns = $columns;
        } elseif ($insertColumns !== $columns) {
            return null;
        }

        if ($sourceCompanyId === null) {
            $sourceCompanyId = $sourceId;
        } elseif ($sourceCompanyId !== $sourceId) {
            return null;
        }

        $targetCompanyIds[$targetId] = $targetId;
    }

    if ($sourceCompanyId === null || $insertColumns === null || $targetCompanyIds === []) {
        return null;
    }

    ksort($targetCompanyIds);

    return [
        'source_company_id' => $sourceCompanyId,
        'target_company_ids' => array_values($targetCompanyIds),
        'insert_columns' => $insertColumns,
    ];
}

/**
 * @param array<int, array{columns:array<int,string>,values:array<int,string>}> $insertRows
 * @return array<string, string>|null
 */
function itm_seed_first_source_row_token_map(array $insertRows, int $sourceCompanyId): ?array
{
    foreach ($insertRows as $insertRow) {
        if (count($insertRow['columns']) !== count($insertRow['values'])) {
            continue;
        }
        $rowAssoc = array_combine($insertRow['columns'], $insertRow['values']);
        if (!is_array($rowAssoc) || !isset($rowAssoc['company_id'])) {
            continue;
        }
        $companyId = (int)itm_seed_sql_unquote((string)$rowAssoc['company_id']);
        if ($companyId !== $sourceCompanyId) {
            continue;
        }

        $tokenMap = [];
        foreach ($insertRow['columns'] as $index => $columnName) {
            $tokenMap[$columnName] = $insertRow['values'][$index];
        }

        return $tokenMap;
    }

    return null;
}

/**
 * @param array<int,string> $templateColumns
 * @param array<string,bool> $unquotedNumericColumns
 * @param array<string,bool> $nullTokenColumns
 */
function itm_seed_build_row_tokens(
    array $templateColumns,
    int $companyId,
    string $valueColumn,
    string $value,
    string $emojiColumn,
    string $emoji,
    string $contentColumn,
    string $activeColumn,
    string $createdAtColumn,
    string $updatedAtColumn,
    string $createdAtDefault,
    bool $idUsesNull,
    int &$nextId,
    array $unquotedNumericColumns,
    array $nullTokenColumns,
    array $templateDefaults = []
): array {
    $rowTokens = [];
    foreach ($templateColumns as $columnName) {
        if ($columnName === 'id') {
            if ($idUsesNull) {
                $rowTokens[] = 'NULL';
            } else {
                $nextId++;
                $rowTokens[] = itm_seed_format_sql_token((string)$nextId, $unquotedNumericColumns['id'] ?? false);
            }
            continue;
        }
        if ($columnName === 'company_id') {
            $rowTokens[] = itm_seed_format_sql_token((string)$companyId, $unquotedNumericColumns['company_id'] ?? false);
            continue;
        }
        if ($columnName === $valueColumn) {
            $rowTokens[] = itm_seed_format_sql_token($value, $unquotedNumericColumns[$valueColumn] ?? false);
            continue;
        }
        if ($emojiColumn !== '' && $columnName === $emojiColumn) {
            $rowTokens[] = $emoji !== ''
                ? itm_seed_format_sql_token($emoji, $unquotedNumericColumns[$emojiColumn] ?? false)
                : 'NULL';
            continue;
        }
        if ($contentColumn !== '' && $columnName === $contentColumn && $emoji !== '') {
            $rowTokens[] = itm_seed_format_sql_token($emoji, false);
            continue;
        }
        if ($activeColumn !== '' && $columnName === $activeColumn) {
            $rowTokens[] = itm_seed_format_sql_token('1', $unquotedNumericColumns[$activeColumn] ?? false);
            continue;
        }
        if ($createdAtColumn !== '' && $columnName === $createdAtColumn) {
            $rowTokens[] = itm_seed_format_sql_token($createdAtDefault, false);
            continue;
        }
        if ($updatedAtColumn !== '' && $columnName === $updatedAtColumn) {
            $rowTokens[] = 'NULL';
            continue;
        }

        if ($nullTokenColumns[$columnName] ?? false) {
            $rowTokens[] = 'NULL';
            continue;
        }

        if (isset($templateDefaults[$columnName])) {
            $rowTokens[] = $templateDefaults[$columnName];
            continue;
        }

        $rowTokens[] = 'NULL';
    }

    return $rowTokens;
}

/**
 * @param array<int, string> $tableColumns
 */
function itm_seed_pick_value_column(array $tableColumns, string $preferred): string
{
    if ($preferred !== '') {
        foreach ($tableColumns as $column) {
            if (strcasecmp($column, $preferred) === 0) {
                return $column;
            }
        }
        itm_seed_fwrite_stderr("Value column '{$preferred}' not found in target table.\n");
        exit(2);
    }

    foreach (['idfdevicetype_name', 'name', 'title'] as $candidate) {
        foreach ($tableColumns as $column) {
            if (strcasecmp($column, $candidate) === 0) {
                return $column;
            }
        }
    }

    itm_seed_fwrite_stderr("Unable to auto-detect value column. Use --value-column=<column>.\n");
    exit(2);
}

/**
 * @param array<int, string> $tableColumns
 */
function itm_seed_pick_emoji_column(array $tableColumns, string $preferred): string
{
    if ($preferred !== '') {
        foreach ($tableColumns as $column) {
            if (strcasecmp($column, $preferred) === 0) {
                return $column;
            }
        }
        itm_seed_fwrite_stderr("Emoji column '{$preferred}' not found in target table.\n");
        exit(2);
    }

    foreach ($tableColumns as $column) {
        if (strcasecmp($column, 'field_edit_emoji') === 0) {
            return $column;
        }
    }

    return '';
}

/**
 * @param array<int,string> $templateColumns
 * @param array<string,array{not_null:bool,has_default:bool,auto_increment:bool,generated:bool}> $columnSpecs
 * @param array<int,string> $handledColumns
 */
function itm_seed_assert_supported_template_columns(string $table, array $templateColumns, array $columnSpecs, array $handledColumns): void
{
    $handledMap = [];
    foreach ($handledColumns as $column) {
        $handledMap[strtolower($column)] = true;
    }

    $unsupportedRequired = [];
    foreach ($templateColumns as $columnName) {
        $columnKey = strtolower($columnName);
        if (isset($handledMap[$columnKey])) {
            continue;
        }

        if (!isset($columnSpecs[$columnKey])) {
            $unsupportedRequired[] = $columnName . ' (definition parse failed)';
            continue;
        }

        $spec = $columnSpecs[$columnKey];
        if ($spec['generated']) {
            $unsupportedRequired[] = $columnName . ' (generated column)';
            continue;
        }

        if ($spec['auto_increment']) {
            continue;
        }

        if ($spec['not_null']) {
            $unsupportedRequired[] = $columnName;
        }
    }

    if ($unsupportedRequired !== []) {
        itm_seed_fwrite_stderr("[ERROR] Unsupported required columns for table '{$table}': " . implode(', ', $unsupportedRequired) . "\n");
        itm_seed_fwrite_stderr("This script only auto-populates lookup-safe columns. Add explicit mappings before seeding this table.\n");
        exit(2);
    }
}

$boot = (function () {
    require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
    return itm_apply_script_bootstrap('Sample Data Seed Application');
})();
$apply = $boot['apply'];
$nl = $boot['nl'];
$parseArgv = $boot['is_cli'] ? $boot['argv'] : array_merge([$boot['argv'][0] ?? 'apply_module_sample_data_seed.php'], itm_seed_browser_argv());
$args = itm_seed_parse_args($parseArgv);
$args['dry_run'] = !$apply;

$module = $args['module'];
$table = $args['table'] !== '' ? $args['table'] : $module;
if ($table === '') {
    itm_seed_fwrite_stderr("Missing --module or --table.\n");
    itm_seed_fwrite_stderr(itm_seed_usage());
    exit(2);
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
    itm_seed_fwrite_stderr("Unsafe table/module name '{$table}'. Use letters, numbers, underscore only.\n");
    exit(2);
}

$samples = $args['samples'];
if ($samples === [] && strcasecmp($module, 'idf_device_type') === 0) {
    $samples = [
        ['value' => 'other', 'emoji' => '📦'],
        ['value' => 'server', 'emoji' => '🖥️'],
        ['value' => 'ups', 'emoji' => '🔋'],
        ['value' => 'patch_panel', 'emoji' => '➿'],
        ['value' => 'switch', 'emoji' => '🔀'],
        ['value' => 'firewall', 'emoji' => '🛡️'],
        ['value' => 'router', 'emoji' => '📡'],
        ['value' => 'pdu', 'emoji' => '🔌'],
    ];
}
if ($samples === []) {
    itm_seed_fwrite_stderr("No sample values supplied. Use --sample=<name> (repeatable).\n");
    exit(2);
}

$seenSamples = [];
$normalizedSamples = [];
foreach ($samples as $sample) {
    $sampleValue = trim((string)($sample['value'] ?? ''));
    $sampleEmoji = trim((string)($sample['emoji'] ?? ''));
    if ($sampleValue === '') {
        continue;
    }
    $sampleKey = strtolower($sampleValue);
    if (isset($seenSamples[$sampleKey])) {
        continue;
    }
    $seenSamples[$sampleKey] = true;
    $normalizedSamples[] = ['value' => $sampleValue, 'emoji' => $sampleEmoji];
}
if ($normalizedSamples === []) {
    itm_seed_fwrite_stderr("No valid sample values after normalization.\n");
    exit(2);
}

$root = dirname(__DIR__);
$schemaPath = itm_database_sql_schema_path();
if (!is_file($schemaPath)) {
    itm_seed_fwrite_stderr("db/01_schema.sql not found.\n");
    exit(2);
}

$sql = (string)file_get_contents($schemaPath);
if ($sql === '') {
    itm_seed_fwrite_stderr("db/02_data.sql is empty.\n");
    exit(2);
}

$meta = itm_seed_find_table_metadata($sql, $table);
if ($meta === null) {
    itm_seed_fwrite_stderr("Table '{$table}' not found in db/ CREATE TABLE blocks.\n");
    exit(2);
}

$tableColumns = $meta['columns'];
$tableColumnSpecs = $meta['column_specs'];
$columnMap = [];
foreach ($tableColumns as $column) {
    $columnMap[strtolower($column)] = $column;
}

if (!isset($columnMap['company_id'])) {
    itm_seed_fwrite_stderr("Table '{$table}' must include company_id column.\n");
    exit(2);
}

$valueColumn = itm_seed_pick_value_column($tableColumns, $args['value_column']);
$emojiColumn = itm_seed_pick_emoji_column($tableColumns, $args['emoji_column']);

$companyIds = itm_seed_extract_company_ids($sql);
if ($companyIds === []) {
    itm_seed_fwrite_stderr("No company ids found in db/ companies seed rows.\n");
    exit(2);
}

$insertData = itm_seed_collect_insert_rows($sql, $table);
$insertRows = $insertData['rows'];
if ($insertRows === []) {
    itm_seed_fwrite_stderr("No INSERT … VALUES seed rows found for '{$table}' in db/.\n");
    exit(2);
}

$mirrorConfig = itm_seed_detect_mirror_selects($sql, $table);
$mirrorMode = $mirrorConfig !== null;

$maxId = 0;
$existingByCompanyAndValue = [];
$templateColumns = $mirrorMode ? $mirrorConfig['insert_columns'] : $insertRows[0]['columns'];
$hasIdColumn = in_array('id', $templateColumns, true);
$idUsesNull = $hasIdColumn && itm_seed_column_uses_null_tokens($insertRows, 'id');
$sourceCompanyId = $mirrorMode ? (int)$mirrorConfig['source_company_id'] : 0;

foreach ($insertRows as $insertRow) {
    $columns = $insertRow['columns'];
    $values = $insertRow['values'];
    if (count($columns) !== count($values)) {
        continue;
    }
    $rowAssoc = array_combine($columns, $values);
    if (!is_array($rowAssoc)) {
        continue;
    }

    $companyId = isset($rowAssoc['company_id']) ? (int)itm_seed_sql_unquote((string)$rowAssoc['company_id']) : 0;
    if ($mirrorMode && $companyId !== $sourceCompanyId) {
        continue;
    }

    if ($hasIdColumn && !$idUsesNull && isset($rowAssoc['id'])) {
        $id = (int)itm_seed_sql_unquote((string)$rowAssoc['id']);
        if ($id > $maxId) {
            $maxId = $id;
        }
    }

    $value = isset($rowAssoc[$valueColumn]) ? trim(itm_seed_sql_unquote((string)$rowAssoc[$valueColumn])) : '';
    if ($companyId > 0 && $value !== '') {
        $existingByCompanyAndValue[$companyId . '|' . strtolower($value)] = true;
    }
}

if ($emojiColumn === '' && isset($columnMap['color']) && in_array($columnMap['color'], $templateColumns, true)) {
    $emojiColumn = $columnMap['color'];
}

$contentColumn = '';
if ($mirrorMode && isset($columnMap['content']) && in_array($columnMap['content'], $templateColumns, true)) {
    $contentColumn = $columnMap['content'];
}

$templateDefaults = [];
if ($mirrorMode) {
    $sourceTokenMap = itm_seed_first_source_row_token_map($insertRows, $sourceCompanyId);
    if ($sourceTokenMap === null) {
        itm_seed_fwrite_stderr("Mirror SELECT mode requires at least one VALUES row for source company_id {$sourceCompanyId}.\n");
        exit(2);
    }
    foreach ($sourceTokenMap as $columnName => $token) {
        if ($columnName === 'company_id' || $columnName === $valueColumn) {
            continue;
        }
        $templateDefaults[$columnName] = $token;
    }
}

$unquotedNumericColumns = [];
$nullTokenColumns = [];
foreach ($templateColumns as $columnName) {
    $unquotedNumericColumns[$columnName] = itm_seed_column_uses_unquoted_numeric_tokens($insertRows, $columnName);
    $nullTokenColumns[$columnName] = itm_seed_column_uses_null_tokens($insertRows, $columnName);
}

$newInsertLines = [];
$newMultiTuples = [];
$addedCount = 0;
$nextId = $maxId;
$activeColumn = $columnMap['active'] ?? '';
$createdAtColumn = $columnMap['created_at'] ?? '';
$updatedAtColumn = $columnMap['updated_at'] ?? '';
$createdAtDefault = '2026-01-01 00:00:01';
$handledColumns = ['company_id', $valueColumn];
if ($hasIdColumn) {
    $handledColumns[] = 'id';
}
if ($emojiColumn !== '') {
    $handledColumns[] = $emojiColumn;
}
if ($contentColumn !== '') {
    $handledColumns[] = $contentColumn;
}
foreach (array_keys($templateDefaults) as $defaultColumn) {
    $handledColumns[] = $defaultColumn;
}
if ($activeColumn !== '') {
    $handledColumns[] = $activeColumn;
}
if ($createdAtColumn !== '') {
    $handledColumns[] = $createdAtColumn;
}
if ($updatedAtColumn !== '') {
    $handledColumns[] = $updatedAtColumn;
}

itm_seed_assert_supported_template_columns($table, $templateColumns, $tableColumnSpecs, $handledColumns);

$appendToMultiBlock = is_array($insertData['last_block']) && ($insertData['last_block']['multi'] ?? false);
$insertCompanyIds = $mirrorMode ? [$sourceCompanyId] : $companyIds;

foreach ($insertCompanyIds as $companyId) {
    foreach ($normalizedSamples as $sample) {
        $value = $sample['value'];
        $emoji = $sample['emoji'];
        $key = $companyId . '|' . strtolower($value);
        if (isset($existingByCompanyAndValue[$key])) {
            continue;
        }

        $rowTokens = itm_seed_build_row_tokens(
            $templateColumns,
            $companyId,
            $valueColumn,
            $value,
            $emojiColumn,
            $emoji,
            $contentColumn,
            $activeColumn,
            $createdAtColumn,
            $updatedAtColumn,
            $createdAtDefault,
            $idUsesNull,
            $nextId,
            $unquotedNumericColumns,
            $nullTokenColumns,
            $templateDefaults
        );

        if ($appendToMultiBlock) {
            $newMultiTuples[] = '(' . implode(', ', $rowTokens) . ')';
        } else {
            $columnsSql = '`' . implode('`, `', $templateColumns) . '`';
            $newInsertLines[] = "INSERT INTO `{$table}` ({$columnsSql}) VALUES (" . implode(', ', $rowTokens) . ");";
        }

        $existingByCompanyAndValue[$key] = true;
        $addedCount++;
    }
}

if ($addedCount === 0) {
    echo colorText("[OK] No missing sample rows for table '{$table}'. Nothing to update.", 'pass') . itm_script_output_nl();
    if ($mirrorMode) {
        echo 'Source company: ' . $sourceCompanyId . '; mirrored to: ' . implode(', ', $mirrorConfig['target_company_ids']) . itm_script_output_nl();
    } else {
        echo 'Companies checked: ' . count($companyIds) . itm_script_output_nl();
    }
    exit(0);
}

$insertPos = $insertData['last_insert_end'];
if ($insertPos < 0) {
    $insertPos = $meta['create_end'];
}

if ($appendToMultiBlock && $newMultiTuples !== []) {
    $valuesEnd = (int)$insertData['last_block']['values_end'];
    $insertChunk = ",\n" . implode(",\n", $newMultiTuples);
    $newSql = substr($sql, 0, $valuesEnd) . $insertChunk . substr($sql, $valuesEnd);
    foreach ($newMultiTuples as $tupleLine) {
        $newInsertLines[] = $tupleLine;
    }
} else {
    $insertChunk = "\n" . implode("\n", $newInsertLines);
    $newSql = substr($sql, 0, $insertPos) . $insertChunk . substr($sql, $insertPos);
}

$nextAutoIncrement = $hasIdColumn && !$idUsesNull ? $nextId + 1 : 0;
if ($nextAutoIncrement > 0) {
    $newSql = preg_replace(
        '/(CREATE TABLE `' . preg_quote($table, '/') . '` \((?:.|\n)*?\)\s*ENGINE=InnoDB[^;]*AUTO_INCREMENT=)(\d+)([^;]*;)/',
        '${1}' . $nextAutoIncrement . '${3}',
        $newSql,
        1
    );
}

if (!is_string($newSql) || $newSql === '') {
    itm_seed_fwrite_stderr("Failed to rebuild SQL content.\n");
    exit(2);
}

echo "Table: {$table}" . itm_script_output_nl();
echo "Value column: {$valueColumn}" . itm_script_output_nl();
echo "Emoji column: " . ($emojiColumn !== '' ? $emojiColumn : '(none)') . itm_script_output_nl();
if ($mirrorMode) {
    echo 'Replication: mirror INSERT … SELECT from company ' . $sourceCompanyId . ' to ' . implode(', ', $mirrorConfig['target_company_ids']) . itm_script_output_nl();
} else {
    echo 'Companies: ' . implode(', ', $companyIds) . itm_script_output_nl();
}
echo 'New rows to add: ' . $addedCount . $nl;
if ($nextAutoIncrement > 0) {
    echo 'AUTO_INCREMENT target: ' . $nextAutoIncrement . $nl;
}
itm_apply_script_echo_list('New INSERT statements', $newInsertLines);

if (!$apply) {
    echo '[DRY-RUN] db/02_data.sql was not modified.' . $nl;
    itm_apply_script_finish_hint(false, $boot['is_cli'], $addedCount, $nl, 'apply_module_sample_data_seed.php');
    itm_script_output_end();
    exit(0);
}

if (file_put_contents($schemaPath, $newSql) === false) {
    itm_seed_fwrite_stderr("Failed to write db/02_data.sql\n");
    exit(2);
}

echo colorText('[OK] db/02_data.sql updated successfully.', 'pass') . $nl;
itm_apply_script_finish_hint(true, $boot['is_cli'], $addedCount, $nl, 'apply_module_sample_data_seed.php');

itm_script_output_end();
