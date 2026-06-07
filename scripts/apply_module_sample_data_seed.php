<?php
/**
 * Apply module/table sample seed rows to database.sql across all seeded companies.
 *
 * Why: PR #1993 added idf_device_type sample rows manually. This script automates
 * that same process per module/table so new sample values can be propagated safely.
 *
 * Usage (repository root):
 *   php scripts/apply_module_sample_data_seed.php --module=idf_device_type
 *   php scripts/apply_module_sample_data_seed.php --module=idf_device_type --dry-run
 *   php scripts/apply_module_sample_data_seed.php --table=equipment_poe --value-column=name --sample=POE+ --sample=POE++
 *
 * Windows Laragon when php is not on PATH:
 *   C:\Users\NelsonSalvador\Downloads\laragon-portable\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\apply_module_sample_data_seed.php --module=idf_device_type
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;max-width:840px;">';
    require_once __DIR__ . '/lib/script_browser_nav.php';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> This script rewrites <code>database.sql</code>. Run from project root:</p>';
    echo '<pre style="background:#f6f8fa;padding:12px;border:1px solid #d0d7de;border-radius:6px;">php scripts/apply_module_sample_data_seed.php --module=idf_device_type --dry-run</pre>';
    echo '</body></html>';
    exit(1);
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
                fwrite(STDERR, "Empty --sample value is not allowed.\n");
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
                fwrite(STDERR, "Invalid --sample format. Use --sample=name or --sample=name:emoji\n");
                exit(2);
            }

            $result['samples'][] = ['value' => $name, 'emoji' => $emoji];
            continue;
        }

        fwrite(STDERR, "Unknown option: {$arg}\n");
        fwrite(STDERR, itm_seed_usage());
        exit(2);
    }

    return $result;
}

function itm_seed_usage(): string
{
    return <<<TXT
Usage:
  php scripts/apply_module_sample_data_seed.php --module=<module_name> [options]
  php scripts/apply_module_sample_data_seed.php --table=<table_name> --value-column=<column> --sample=<value> [--sample=<value2>] [options]

Options:
  --module=<name>         Module/table name (e.g. idf_device_type). If --table is missing, table = module.
  --table=<name>          Database table in database.sql.
  --value-column=<name>   Column used for uniqueness per company (default: idfdevicetype_name, then name).
  --emoji-column=<name>   Optional column for emoji/value metadata (default: field_edit_emoji when present).
  --sample=<name>         Sample value to add for every company (repeatable).
  --sample=<name:emoji>   Sample value plus emoji in one option (repeatable).
  --dry-run               Preview changes; do not write database.sql.
  --help                  Show this help.

Defaults:
  For --module=idf_device_type with no --sample options, the script applies:
    other:📦, server:🖥️, ups:🔋, patch_panel:➿, switch:🔀, firewall:🛡️, router:📡, pdu:🔌

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
 * @return array{rows:array<int,array{columns:array<int,string>,values:array<int,string>,offset:int,length:int}>,last_insert_end:int}
 */
function itm_seed_collect_insert_rows(string $sql, string $table): array
{
    $rows = [];
    $lastInsertEnd = -1;
    $pattern = '/^INSERT INTO `' . preg_quote($table, '/') . '` \(([^)]+)\) VALUES \((.+)\);\s*$/m';

    if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        return ['rows' => [], 'last_insert_end' => -1];
    }

    foreach ($matches as $match) {
        $fullText = (string)$match[0][0];
        $fullOffset = (int)$match[0][1];
        $columnsRaw = (string)$match[1][0];
        $valuesRaw = (string)$match[2][0];

        $columns = array_map(static function ($column): string {
            return trim(str_replace('`', '', $column));
        }, explode(',', $columnsRaw));
        $values = itm_seed_split_csv_values($valuesRaw);

        $rows[] = [
            'columns' => $columns,
            'values' => $values,
            'offset' => $fullOffset,
            'length' => strlen($fullText),
        ];
        $lastInsertEnd = $fullOffset + strlen($fullText);
    }

    return ['rows' => $rows, 'last_insert_end' => $lastInsertEnd];
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
        fwrite(STDERR, "Value column '{$preferred}' not found in target table.\n");
        exit(2);
    }

    foreach (['idfdevicetype_name', 'name'] as $candidate) {
        foreach ($tableColumns as $column) {
            if (strcasecmp($column, $candidate) === 0) {
                return $column;
            }
        }
    }

    fwrite(STDERR, "Unable to auto-detect value column. Use --value-column=<column>.\n");
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
        fwrite(STDERR, "Emoji column '{$preferred}' not found in target table.\n");
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
        fwrite(STDERR, "[ERROR] Unsupported required columns for table '{$table}': " . implode(', ', $unsupportedRequired) . "\n");
        fwrite(STDERR, "This script only auto-populates lookup-safe columns. Add explicit mappings before seeding this table.\n");
        exit(2);
    }
}

$args = itm_seed_parse_args($argv);

$module = $args['module'];
$table = $args['table'] !== '' ? $args['table'] : $module;
if ($table === '') {
    fwrite(STDERR, "Missing --module or --table.\n");
    fwrite(STDERR, itm_seed_usage());
    exit(2);
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
    fwrite(STDERR, "Unsafe table/module name '{$table}'. Use letters, numbers, underscore only.\n");
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
    fwrite(STDERR, "No sample values supplied. Use --sample=<name> (repeatable).\n");
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
    fwrite(STDERR, "No valid sample values after normalization.\n");
    exit(2);
}

$root = dirname(__DIR__);
$schemaPath = $root . DIRECTORY_SEPARATOR . 'database.sql';
if (!is_file($schemaPath)) {
    fwrite(STDERR, "database.sql not found.\n");
    exit(2);
}

$sql = (string)file_get_contents($schemaPath);
if ($sql === '') {
    fwrite(STDERR, "database.sql is empty.\n");
    exit(2);
}

$meta = itm_seed_find_table_metadata($sql, $table);
if ($meta === null) {
    fwrite(STDERR, "Table '{$table}' not found in database.sql CREATE TABLE blocks.\n");
    exit(2);
}

$tableColumns = $meta['columns'];
$tableColumnSpecs = $meta['column_specs'];
$columnMap = [];
foreach ($tableColumns as $column) {
    $columnMap[strtolower($column)] = $column;
}

if (!isset($columnMap['id']) || !isset($columnMap['company_id'])) {
    fwrite(STDERR, "Table '{$table}' must include id and company_id columns.\n");
    exit(2);
}

$valueColumn = itm_seed_pick_value_column($tableColumns, $args['value_column']);
$emojiColumn = itm_seed_pick_emoji_column($tableColumns, $args['emoji_column']);

$companyIds = itm_seed_extract_company_ids($sql);
if ($companyIds === []) {
    fwrite(STDERR, "No company ids found in database.sql companies seed rows.\n");
    exit(2);
}

$insertData = itm_seed_collect_insert_rows($sql, $table);
$insertRows = $insertData['rows'];
if ($insertRows === []) {
    fwrite(STDERR, "No existing single-row INSERT statements found for '{$table}'. This script expects lookup-style inserts.\n");
    exit(2);
}

$maxId = 0;
$existingByCompanyAndValue = [];
$templateColumns = $insertRows[0]['columns'];

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

    $id = isset($rowAssoc['id']) ? (int)itm_seed_sql_unquote((string)$rowAssoc['id']) : 0;
    if ($id > $maxId) {
        $maxId = $id;
    }

    $companyId = isset($rowAssoc['company_id']) ? (int)itm_seed_sql_unquote((string)$rowAssoc['company_id']) : 0;
    $value = isset($rowAssoc[$valueColumn]) ? trim(itm_seed_sql_unquote((string)$rowAssoc[$valueColumn])) : '';
    if ($companyId > 0 && $value !== '') {
        $existingByCompanyAndValue[$companyId . '|' . strtolower($value)] = true;
    }
}

$newInsertLines = [];
$addedCount = 0;
$nextId = $maxId;
$activeColumn = $columnMap['active'] ?? '';
$createdAtColumn = $columnMap['created_at'] ?? '';
$updatedAtColumn = $columnMap['updated_at'] ?? '';
$createdAtDefault = '2026-01-01 00:00:01';
$handledColumns = ['id', 'company_id', $valueColumn];
if ($emojiColumn !== '') {
    $handledColumns[] = $emojiColumn;
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

foreach ($companyIds as $companyId) {
    foreach ($normalizedSamples as $sample) {
        $value = $sample['value'];
        $emoji = $sample['emoji'];
        $key = $companyId . '|' . strtolower($value);
        if (isset($existingByCompanyAndValue[$key])) {
            continue;
        }

        $nextId++;
        $rowTokens = [];
        foreach ($templateColumns as $columnName) {
            if ($columnName === 'id') {
                $rowTokens[] = itm_seed_sql_quote((string)$nextId);
                continue;
            }
            if ($columnName === 'company_id') {
                $rowTokens[] = itm_seed_sql_quote((string)$companyId);
                continue;
            }
            if ($columnName === $valueColumn) {
                $rowTokens[] = itm_seed_sql_quote($value);
                continue;
            }
            if ($emojiColumn !== '' && $columnName === $emojiColumn) {
                $rowTokens[] = $emoji !== '' ? itm_seed_sql_quote($emoji) : 'NULL';
                continue;
            }
            if ($activeColumn !== '' && $columnName === $activeColumn) {
                $rowTokens[] = itm_seed_sql_quote('1');
                continue;
            }
            if ($createdAtColumn !== '' && $columnName === $createdAtColumn) {
                $rowTokens[] = itm_seed_sql_quote($createdAtDefault);
                continue;
            }
            if ($updatedAtColumn !== '' && $columnName === $updatedAtColumn) {
                $rowTokens[] = 'NULL';
                continue;
            }

            // Fallback for unexpected nullable/default columns in lookup seeds.
            $rowTokens[] = 'NULL';
        }

        $columnsSql = '`' . implode('`, `', $templateColumns) . '`';
        $newInsertLines[] = "INSERT INTO `{$table}` ({$columnsSql}) VALUES (" . implode(', ', $rowTokens) . ");";
        $existingByCompanyAndValue[$key] = true;
        $addedCount++;
    }
}

if ($addedCount === 0) {
    echo "[OK] No missing sample rows for table '{$table}'. Nothing to update.\n";
    echo 'Companies checked: ' . count($companyIds) . "\n";
    exit(0);
}

$insertPos = $insertData['last_insert_end'];
if ($insertPos < 0) {
    $insertPos = $meta['create_end'];
}

$insertChunk = "\n" . implode("\n", $newInsertLines);
$newSql = substr($sql, 0, $insertPos) . $insertChunk . substr($sql, $insertPos);

$nextAutoIncrement = $nextId + 1;
$newSql = preg_replace(
    '/(CREATE TABLE `' . preg_quote($table, '/') . '` \((?:.|\n)*?\)\s*ENGINE=InnoDB[^;]*AUTO_INCREMENT=)(\d+)([^;]*;)/',
    '${1}' . $nextAutoIncrement . '${3}',
    $newSql,
    1
);

if (!is_string($newSql) || $newSql === '') {
    fwrite(STDERR, "Failed to rebuild SQL content.\n");
    exit(2);
}

echo "Table: {$table}\n";
echo "Value column: {$valueColumn}\n";
echo "Emoji column: " . ($emojiColumn !== '' ? $emojiColumn : '(none)') . "\n";
echo 'Companies: ' . implode(', ', $companyIds) . "\n";
echo "New rows to add: {$addedCount}\n";
echo "AUTO_INCREMENT target: {$nextAutoIncrement}\n";

if ($args['dry_run']) {
    echo "[DRY-RUN] database.sql was not modified.\n";
    exit(0);
}

if (file_put_contents($schemaPath, $newSql) === false) {
    fwrite(STDERR, "Failed to write database.sql\n");
    exit(2);
}

echo "[OK] database.sql updated successfully.\n";
