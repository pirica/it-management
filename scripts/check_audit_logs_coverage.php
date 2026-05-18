<?php
/**
 * Audit Logs Coverage Static Analysis Script
 *
 * Why: AGENTS.md requires INSERT/UPDATE/DELETE traceability in audit_logs when enabled.
 * Modules can satisfy that via PHP helpers (itm_run_query / itm_log_audit / bulk helpers)
 * or database triggers defined in database.sql (trg_{table}_audit_*).
 *
 * Usage:
 *   php scripts/check_audit_logs_coverage.php
 *   php scripts/check_audit_logs_coverage.php --module=rack_planner
 *   php scripts/check_audit_logs_coverage.php --json
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(2);
}

$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';
$databaseSqlPath = $root . DIRECTORY_SEPARATOR . 'database.sql';

$options = [
    'json' => false,
    'module' => '',
    'help' => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        $options['help'] = true;
        continue;
    }
    if ($arg === '--json') {
        $options['json'] = true;
        continue;
    }
    if (strpos($arg, '--module=') === 0) {
        $options['module'] = trim(substr($arg, 9));
        continue;
    }
    fwrite(STDERR, "Unknown option: {$arg}\n");
    exit(2);
}

if ($options['help']) {
    echo "Audit logs coverage check\n\n";
    echo "Options:\n";
    echo "  --module=NAME   Limit scan to one module folder\n";
    echo "  --json          Machine-readable output\n";
    echo "  --help          Show this help\n";
    exit(0);
}

/**
 * @return array{triggers: array<string, bool>, schema_tables: array<string, bool>}
 */
function audit_logs_load_database_sql_maps(string $databaseSqlPath): array
{
    $triggers = [];
    $schemaTables = [];

    if (!is_readable($databaseSqlPath)) {
        return ['triggers' => $triggers, 'schema_tables' => $schemaTables];
    }

    $sql = file_get_contents($databaseSqlPath);
    if (!is_string($sql)) {
        return ['triggers' => $triggers, 'schema_tables' => $schemaTables];
    }

    if (preg_match_all('/CREATE TABLE `([^`]+)`/i', $sql, $tableMatches)) {
        foreach ($tableMatches[1] as $tableName) {
            $schemaTables[(string)$tableName] = true;
        }
    }

    if (preg_match_all('/CREATE TRIGGER `trg_([a-zA-Z0-9_]+)_audit_insert`/i', $sql, $matches)) {
        foreach ($matches[1] as $tableName) {
            $triggers[(string)$tableName] = true;
        }
    }

    return ['triggers' => $triggers, 'schema_tables' => $schemaTables];
}

/**
 * @return array<int, string>
 */
function audit_logs_list_modules(string $modulesDir, string $onlyModule): array
{
    if (!is_dir($modulesDir)) {
        return [];
    }

    $modules = [];
    foreach (scandir($modulesDir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $modulesDir . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($path)) {
            continue;
        }
        if ($onlyModule !== '' && $entry !== $onlyModule) {
            continue;
        }
        $modules[] = $entry;
    }

    sort($modules, SORT_NATURAL | SORT_FLAG_CASE);
    return $modules;
}

/**
 * @return array<int, string>
 */
function audit_logs_module_php_files(string $modulePath): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($modulePath, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        if (substr($fileInfo->getFilename(), -4) !== '.php') {
            continue;
        }
        $files[] = $fileInfo->getPathname();
    }

    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

function audit_logs_read_module_source(array $phpFiles): string
{
    $chunks = [];
    foreach ($phpFiles as $path) {
        $content = file_get_contents($path);
        if (is_string($content)) {
            $chunks[] = $content;
        }
    }

    return implode("\n", $chunks);
}

function audit_logs_extract_crud_table(string $indexContent): ?string
{
    if (preg_match('/\$crud_table\s*=\s*\$crud_table\s*\?\?\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $indexContent, $match)) {
        return $match[1];
    }
    if (preg_match('/\$crud_table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $indexContent, $match)) {
        return $match[1];
    }

    return null;
}

function audit_logs_module_has_mutations(string $source, array $schemaTables): bool
{
    if (!empty(audit_logs_extract_mutated_tables($source, $schemaTables))) {
        return true;
    }

    return preg_match('/mysqli_prepare\s*\(\s*\$conn\s*,\s*["\'][^"\']*\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i', $source) === 1;
}

function audit_logs_module_has_php_audit_hooks(string $source): bool
{
    $patterns = [
        'itm_run_query(',
        'itm_log_audit(',
        'cr_collect_audit_rows_for_where(',
    ];

    foreach ($patterns as $pattern) {
        if (strpos($source, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @return array<int, string>
 */
function audit_logs_collect_tables_from_sql(string $sql, array $schemaTables): array
{
    $tables = [];

    if (preg_match_all('/\bINSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $matches)) {
        foreach ($matches[1] as $tableName) {
            $tableName = (string)$tableName;
            if (isset($schemaTables[$tableName])) {
                $tables[$tableName] = $tableName;
            }
        }
    }

    if (preg_match_all('/\bDELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $matches)) {
        foreach ($matches[1] as $tableName) {
            $tableName = (string)$tableName;
            if (isset($schemaTables[$tableName])) {
                $tables[$tableName] = $tableName;
            }
        }
    }

    if (preg_match_all('/\bUPDATE\s+`?([a-zA-Z0-9_]+)`?\s+SET\b/i', $sql, $matches)) {
        foreach ($matches[1] as $tableName) {
            $tableName = (string)$tableName;
            if (isset($schemaTables[$tableName])) {
                $tables[$tableName] = $tableName;
            }
        }
    }

    return $tables;
}

/**
 * @return array<int, string>
 */
function audit_logs_extract_mutated_tables(string $source, array $schemaTables): array
{
    $tables = audit_logs_collect_tables_from_sql($source, $schemaTables);

    if (preg_match_all('/mysqli_prepare\s*\(\s*\$conn\s*,\s*["\']([^"\']+)["\']/is', $source, $matches)) {
        foreach ($matches[1] as $sqlFragment) {
            foreach (audit_logs_collect_tables_from_sql((string)$sqlFragment, $schemaTables) as $tableName) {
                $tables[$tableName] = $tableName;
            }
        }
    }

    ksort($tables);
    return array_values($tables);
}

/**
 * @param array<string, bool> $triggerTables
 * @return array{status:string,details:string,crud_table:?string,php_audit:bool,db_trigger:bool,mutated_tables:array<int,string>,uncovered_tables:array<int,string>}
 */
function audit_logs_assess_module(
    string $moduleName,
    string $modulePath,
    array $triggerTables,
    array $schemaTables
): array {
    $phpFiles = audit_logs_module_php_files($modulePath);
    $source = audit_logs_read_module_source($phpFiles);

    $indexPath = $modulePath . DIRECTORY_SEPARATOR . 'index.php';
    $indexContent = is_file($indexPath) ? (string)file_get_contents($indexPath) : '';
    $crudTable = audit_logs_extract_crud_table($indexContent);

    $hasMutations = audit_logs_module_has_mutations($source, $schemaTables);
    $phpAudit = audit_logs_module_has_php_audit_hooks($source);
    $dbTrigger = $crudTable !== null && !empty($triggerTables[$crudTable]);

    $mutatedTables = audit_logs_extract_mutated_tables($source, $schemaTables);
    if ($crudTable !== null) {
        $mutatedTables[] = $crudTable;
    }
    $mutatedTables = array_values(array_unique($mutatedTables));
    sort($mutatedTables, SORT_NATURAL | SORT_FLAG_CASE);

    $uncoveredTables = [];
    foreach ($mutatedTables as $tableName) {
        if ($tableName === 'audit_logs') {
            continue;
        }
        if (empty($triggerTables[$tableName])) {
            $uncoveredTables[] = $tableName;
        }
    }

    if ($moduleName === 'audit_logs') {
        return [
            'status' => 'skip',
            'details' => 'Audit center module (clears audit_logs rows; excluded from CRUD audit requirement)',
            'crud_table' => $crudTable,
            'php_audit' => $phpAudit,
            'db_trigger' => $dbTrigger,
            'mutated_tables' => $mutatedTables,
            'uncovered_tables' => $uncoveredTables,
        ];
    }

    if (!$hasMutations) {
        return [
            'status' => 'n/a',
            'details' => 'No INSERT/UPDATE/DELETE mutations detected in module PHP',
            'crud_table' => $crudTable,
            'php_audit' => $phpAudit,
            'db_trigger' => $dbTrigger,
            'mutated_tables' => $mutatedTables,
            'uncovered_tables' => $uncoveredTables,
        ];
    }

    $tablesNeedingTriggers = array_values(array_filter($mutatedTables, static function ($tableName) {
        return $tableName !== 'audit_logs';
    }));

    $allMutatedTablesHaveTriggers = true;
    foreach ($tablesNeedingTriggers as $tableName) {
        if (empty($triggerTables[$tableName])) {
            $allMutatedTablesHaveTriggers = false;
            break;
        }
    }

    if ($phpAudit || $dbTrigger || ($hasMutations && $allMutatedTablesHaveTriggers && !empty($tablesNeedingTriggers))) {
        $channels = [];
        if ($phpAudit) {
            $channels[] = 'PHP (itm_run_query / itm_log_audit / bulk helper)';
        }
        if ($dbTrigger) {
            $channels[] = 'database trigger on ' . ($crudTable ?? 'primary table');
        }
        if (!$phpAudit && !$dbTrigger && $allMutatedTablesHaveTriggers) {
            $channels[] = 'database triggers on all mutated tables';
        }

        $status = 'pass';
        $details = 'Audit path: ' . implode(' + ', $channels);

        if (!$phpAudit && ($dbTrigger || $allMutatedTablesHaveTriggers)) {
            $details .= ' (PHP uses prepared statements; relies on database.sql triggers)';
        }

        if (!empty($uncoveredTables)) {
            $status = 'warn';
            $details .= '; tables without trg_*_audit_insert in database.sql: ' . implode(', ', $uncoveredTables);
        }

        return [
            'status' => $status,
            'details' => $details,
            'crud_table' => $crudTable,
            'php_audit' => $phpAudit,
            'db_trigger' => $dbTrigger,
            'mutated_tables' => $mutatedTables,
            'uncovered_tables' => $uncoveredTables,
        ];
    }

    $detailParts = ['Mutations detected but no PHP audit hooks and no trg_{table}_audit_* triggers for primary table'];
    if ($crudTable !== null) {
        $detailParts[] = 'table=' . $crudTable;
    }
    if (!empty($uncoveredTables)) {
        $detailParts[] = 'uncovered=' . implode(', ', $uncoveredTables);
    }

    return [
        'status' => 'fail',
        'details' => implode('; ', $detailParts),
        'crud_table' => $crudTable,
        'php_audit' => $phpAudit,
        'db_trigger' => $dbTrigger,
        'mutated_tables' => $mutatedTables,
        'uncovered_tables' => $uncoveredTables,
    ];
}

$dbMaps = audit_logs_load_database_sql_maps($databaseSqlPath);
$triggerTables = $dbMaps['triggers'];
$schemaTables = $dbMaps['schema_tables'];
$modules = audit_logs_list_modules($modulesDir, $options['module']);

if ($options['module'] !== '' && empty($modules)) {
    fwrite(STDERR, "Module not found: {$options['module']}\n");
    exit(2);
}

$results = [];
$totals = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'n/a' => 0, 'skip' => 0];

foreach ($modules as $moduleName) {
    $modulePath = $modulesDir . DIRECTORY_SEPARATOR . $moduleName;
    $assessment = audit_logs_assess_module($moduleName, $modulePath, $triggerTables, $schemaTables);
    $status = $assessment['status'];
    $totals[$status] = ($totals[$status] ?? 0) + 1;
    $results[] = array_merge(['module' => $moduleName], $assessment);
}

if ($options['json']) {
    echo json_encode(
        [
            'database_sql' => $databaseSqlPath,
            'trigger_table_count' => count($triggerTables),
            'totals' => $totals,
            'modules' => $results,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n";
    exit($totals['fail'] > 0 ? 2 : 0);
}

echo "Audit Logs Coverage Check\n";
echo "Root: {$root}\n";
echo 'Trigger tables in database.sql: ' . count($triggerTables) . "\n\n";

foreach ($results as $row) {
    $label = str_pad(strtoupper($row['status']), 4, ' ', STR_PAD_RIGHT);
    $tableLabel = $row['crud_table'] !== null ? $row['crud_table'] : '(no $crud_table)';
    echo "[{$label}] {$row['module']} :: {$tableLabel} — {$row['details']}\n";
}

echo "\n==== Summary ====\n";
echo 'PASS: ' . $totals['pass'] . "\n";
echo 'WARN: ' . $totals['warn'] . "\n";
echo 'FAIL: ' . $totals['fail'] . "\n";
echo 'N/A : ' . $totals['n/a'] . "\n";
echo 'SKIP: ' . $totals['skip'] . "\n";

if ($totals['fail'] > 0) {
    echo "\nModules failing audit coverage:\n";
    foreach ($results as $row) {
        if ($row['status'] !== 'fail') {
            continue;
        }
        echo " - {$row['module']}: {$row['details']}\n";
    }
    echo "\nNote: PHP itm_log_audit() honors ui_configuration.enable_audit_logs; database triggers always write audit_logs rows.\n";
    exit(2);
}

if ($totals['warn'] > 0) {
    echo "\nWarnings (review multi-table / custom modules):\n";
    foreach ($results as $row) {
        if ($row['status'] !== 'warn') {
            continue;
        }
        echo " - {$row['module']}: {$row['details']}\n";
    }
}

echo "\nNote: PHP itm_log_audit() honors ui_configuration.enable_audit_logs; database triggers always write audit_logs rows.\n";
exit(0);
