<?php
/**
 * Audit Logs Coverage Static Analysis Script
 *
 * Why: AGENTS.md requires INSERT/UPDATE/DELETE traceability in audit_logs when enabled.
 * Modules can satisfy that via PHP helpers (itm_run_query / itm_log_audit / bulk helpers)
 * or database triggers defined in database.sql (trg_{table}_audit_*).
 * Also compares every CREATE TABLE in database.sql against trg_{table}_audit_insert
 * (audit_logs and private-data tables per AGENTS.md are exempt) and exits non-zero
 * when other schema tables are missing triggers.
 *
 * Usage (PHP 7.4+ with MySQLi, from repository root):
 *   php scripts/check_audit_logs_coverage.php
 *   php scripts/check_audit_logs_coverage.php --module=rack_planner
 *   php scripts/check_audit_logs_coverage.php --json
 *
 * Windows Laragon when `php` on PATH is too old:
 *   <laragon-root>\bin\php\php-7.4.33-nts-Win32-vc15-x64\php.exe scripts\check_audit_logs_coverage.php
 */

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        fwrite(STDERR, "Unable to resolve project root.\n");
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo "Unable to resolve project root." . $nl;
    }
    exit(2);
}

require_once __DIR__ . '/lib/script_cli_output.php';

$modulesDir = $root . DIRECTORY_SEPARATOR . 'modules';
$databaseSqlPath = $root . DIRECTORY_SEPARATOR . 'database.sql';

$options = [
    'json' => false,
    'module' => '',
    'help' => false,
];

if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
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
} else {
    $options['json'] = isset($_GET['json']);
    $options['module'] = trim((string)($_GET['module'] ?? ''));
    if (isset($_GET['help'])) {
        $options['help'] = true;
    }
}

if ($options['help']) {
    itm_script_output_begin('Audit logs coverage check');
    echo "Audit logs coverage check\n" . $nl;
    echo "Options:" . $nl;
    echo "  --module=NAME   Limit scan to one module folder" . $nl;
    echo "  --json          Machine-readable output" . $nl;
    echo "  --help          Show this help" . $nl;
    if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
        echo "\nBrowser query: ?module=rack_planner or ?json=1" . $nl;
    }
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
 * Tables that intentionally have no audit triggers (audit destination, private user data, etc.).
 * Keep aligned with AGENTS.md → Private data — no audit trail.
 *
 * @return array<string, bool>
 */
function audit_logs_private_data_tables(): array
{
    return [
        'emails' => true,
        'password_entries' => true,
        'password_folders' => true,
        'private_contacts' => true,
        'todo_categories' => true,
        'todo' => true,
        'notes' => true,
        'note_labels' => true,
        'note_share_sessions' => true,
        'password_share_sessions' => true,
        'bookmark_share_sessions' => true,
        'todo_share_sessions' => true,
        'bookmark_folders' => true,
        'bookmarks' => true,
    ];
}

/**
 * @return array<string, bool>
 */
function audit_logs_trigger_exempt_tables(): array
{
    return array_merge(
        ['audit_logs' => true],
        audit_logs_private_data_tables()
    );
}

/**
 * @param string $tableName
 * @return bool
 */
function audit_logs_table_is_private_data_exempt(string $tableName): bool
{
    return !empty(audit_logs_private_data_tables()[$tableName]);
}

/**
 * @param array<string, bool> $schemaTables
 * @param array<string, bool> $triggerTables
 * @return array<int, string>
 */
function audit_logs_schema_tables_missing_triggers(array $schemaTables, array $triggerTables): array
{
    $exempt = audit_logs_trigger_exempt_tables();
    $missing = [];

    foreach (array_keys($schemaTables) as $tableName) {
        if (!empty($exempt[$tableName])) {
            continue;
        }
        if (empty($triggerTables[$tableName])) {
            $missing[] = $tableName;
        }
    }

    sort($missing, SORT_NATURAL | SORT_FLAG_CASE);
    return $missing;
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

/**
 * @return string|null
 */
function audit_logs_extract_crud_table(string $indexContent)
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

    if (preg_match('/mysqli_prepare\s*\(\s*\$conn\s*,\s*["\'][^"\']*\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i', $source) === 1) {
        return true;
    }

    // Why: Standard CRUD modules build SQL with cr_escape_identifier($crud_table) instead of literal table names.
    if (preg_match('/\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s*[\'"]\s*\.\s*cr_escape_identifier\s*\(/i', $source) === 1) {
        return true;
    }
    if (
        preg_match('/cr_escape_identifier\s*\(\s*\$crud_table\s*\)/i', $source) === 1
        && preg_match('/\b(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i', $source) === 1
    ) {
        return true;
    }

    return false;
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

    if (preg_match_all('/\bINSERT(?:\s+IGNORE)?\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $matches)) {
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

    if (preg_match_all('/mysqli_query\s*\(\s*\$conn\s*,\s*["\']([^"\']+)["\']/is', $source, $matches)) {
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
        if ($tableName === 'audit_logs' || audit_logs_table_is_private_data_exempt($tableName)) {
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
        if ($phpAudit && $crudTable !== null) {
            return [
                'status' => 'pass',
                'details' => 'Audit path: PHP (itm_run_query / itm_log_audit / bulk helper); dynamic SQL mutations inferred from CRUD helpers',
                'crud_table' => $crudTable,
                'php_audit' => $phpAudit,
                'db_trigger' => $dbTrigger,
                'mutated_tables' => $mutatedTables,
                'uncovered_tables' => $uncoveredTables,
            ];
        }

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
        return $tableName !== 'audit_logs' && !audit_logs_table_is_private_data_exempt($tableName);
    }));

    if ($hasMutations && empty($tablesNeedingTriggers) && !empty($mutatedTables)) {
        return [
            'status' => 'pass',
            'details' => 'Private-data module: mutations on exempt tables only (no audit_logs / triggers per AGENTS.md)',
            'crud_table' => $crudTable,
            'php_audit' => $phpAudit,
            'db_trigger' => $dbTrigger,
            'mutated_tables' => $mutatedTables,
            'uncovered_tables' => $uncoveredTables,
        ];
    }

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
$schemaTablesMissingTriggers = audit_logs_schema_tables_missing_triggers($schemaTables, $triggerTables);
$modules = audit_logs_list_modules($modulesDir, $options['module']);

if ($options['module'] !== '' && empty($modules)) {
    itm_script_output_begin('Audit logs coverage check');
    echo "Module not found: {$options['module']}" . $nl;
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
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(
        [
            'database_sql' => $databaseSqlPath,
            'schema_table_count' => count($schemaTables),
            'trigger_table_count' => count($triggerTables),
            'schema_tables_missing_triggers' => $schemaTablesMissingTriggers,
            'totals' => $totals,
            'modules' => $results,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . "\n";
    $jsonExitCode = ($totals['fail'] > 0 || !empty($schemaTablesMissingTriggers)) ? 2 : 0;
    exit($jsonExitCode);
}

itm_script_output_begin('Audit logs coverage check');
$nl = itm_script_output_nl();

echo "Audit Logs Coverage Check" . $nl;
echo "Root: {$root}" . $nl;
echo 'Schema tables in database.sql: ' . count($schemaTables) . $nl;
echo 'Tables with trg_*_audit_insert in database.sql: ' . count($triggerTables) . $nl;
if (!empty($schemaTablesMissingTriggers)) {
    echo 'Schema tables missing audit triggers: ' . implode(', ', $schemaTablesMissingTriggers) . $nl;
} else {
    echo "Schema tables missing audit triggers: (none)" . $nl;
}
echo $nl;

foreach ($results as $row) {
    $label = str_pad(strtoupper($row['status']), 4, ' ', STR_PAD_RIGHT);
    $tableLabel = $row['crud_table'] !== null ? $row['crud_table'] : '(no $crud_table)';
    $moduleLabel = itm_script_format_module_link($row['module']);
    echo itm_script_format_status_line("[{$label}]") . " {$moduleLabel} :: {$tableLabel} — {$row['details']}" . $nl;
}

echo $nl . "==== Summary ====" . $nl;
echo 'PASS: ' . $totals['pass'] . $nl;
echo 'WARN: ' . $totals['warn'] . $nl;
echo 'FAIL: ' . $totals['fail'] . $nl;
echo 'N/A : ' . $totals['n/a'] . $nl;
echo 'SKIP: ' . $totals['skip'] . $nl;

if ($totals['fail'] > 0) {
    echo $nl . "Modules failing audit coverage:" . $nl;
    foreach ($results as $row) {
        if ($row['status'] !== 'fail') {
            continue;
        }
        echo ' - ' . itm_script_format_module_link($row['module']) . ": {$row['details']}" . $nl;
    }
    echo $nl . "Note: PHP itm_log_audit() honors ui_configuration.enable_audit_logs; database triggers write audit_logs rows for non-private tables only (see AGENTS.md → Private data — no audit trail)." . $nl;
    exit(2);
}

if ($totals['warn'] > 0) {
    echo $nl . "Warnings (review multi-table / custom modules):" . $nl;
    foreach ($results as $row) {
        if ($row['status'] !== 'warn') {
            continue;
        }
        echo ' - ' . itm_script_format_module_link($row['module']) . ": {$row['details']}" . $nl;
    }
}

if (!empty($schemaTablesMissingTriggers)) {
    echo $nl . "Schema gaps (add trg_{table}_audit_insert/update/delete in database.sql):" . $nl;
    foreach ($schemaTablesMissingTriggers as $tableName) {
        echo " - {$tableName}" . $nl;
    }
    echo $nl . "Note: PHP itm_log_audit() honors ui_configuration.enable_audit_logs; database triggers write audit_logs rows for non-private tables only (see AGENTS.md → Private data — no audit trail)." . $nl;
    exit(2);
}

echo $nl . "Note: PHP itm_log_audit() honors ui_configuration.enable_audit_logs; database triggers write audit_logs rows for non-private tables only (see AGENTS.md → Private data — no audit trail)." . $nl;
exit(0);

itm_script_output_end();
