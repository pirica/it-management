<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/script_cli_output.php';

/**
 * Browser/CLI helper to run the same cleanup that module_browser_qa_runner.php
 * executes before/after QA runs.
 *
 * CLI:
 *   php scripts/module_clean_tests_qa_runner.php
 *   php scripts/module_clean_tests_qa_runner.php --help
 *
 * Browser:
 *   scripts/module_clean_tests_qa_runner.php
 *   submit POST run form (CSRF-protected)
 */

require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/mbqa_report_paths.php';

function mbqa_clean_tests_is_cli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

/**
 * @return array{run:bool, help:bool}
 */
function mbqa_clean_tests_parse_options(): array
{
    $options = [
        'run' => false,
        'help' => false,
    ];

    if (mbqa_clean_tests_is_cli()) {
        $options['run'] = true;
        foreach (array_slice($GLOBALS['argv'] ?? [], 1) as $arg) {
            if ($arg === '--help' || $arg === '-h') {
                $options['help'] = true;
                $options['run'] = false;
            }
        }

        return $options;
    }

    $options['run'] = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['run']);
    $options['help'] = isset($_GET['help']);

    return $options;
}

/**
 * @return array{md_href:string,xlsx_href:string,clean_href:string,rebuild_href:string,rerun_href:string,runner_href:string}
 */
function mbqa_clean_tests_action_hrefs(string $root): array
{
    $latestJson = mbqa_report_find_latest_json_path($root);
    $xlsxHref = '';
    if ($latestJson !== '') {
        $files = mbqa_report_files_from_json_path($latestJson);
        if ($files !== null && isset($files['xlsx_basename']) && $files['xlsx_basename'] !== '') {
            $xlsxHref = '../qa-reports/' . $files['xlsx_basename'];
        }
    }

    return [
        'clean_href' => 'module_clean_tests_qa_runner.php',
        'md_href' => '../qa-reports/' . mbqa_report_markdown_basename(),
        'xlsx_href' => $xlsxHref,
        'rebuild_href' => 'module_browser_qa_build_report.php?run=1',
        'rerun_href' => 'module_browser_qa_runner.php?autostart=1',
        'runner_href' => 'module_browser_qa_runner.php',
    ];
}

/**
 * @param array{md_href:string,xlsx_href:string,clean_href:string,rebuild_href:string,rerun_href:string,runner_href:string} $hrefs
 */
function mbqa_clean_tests_action_links_html(array $hrefs): string
{
    $links = [];
    $links[] = '<a href="' . htmlspecialchars($hrefs['clean_href'], ENT_QUOTES, 'UTF-8') . '">Clean Tests</a>';
    $links[] = '<a href="' . htmlspecialchars($hrefs['md_href'], ENT_QUOTES, 'UTF-8') . '">Open markdown file</a>';
    if ($hrefs['xlsx_href'] !== '') {
        $links[] = '<a href="' . htmlspecialchars($hrefs['xlsx_href'], ENT_QUOTES, 'UTF-8') . '">Download XLSX</a>';
    } else {
        $links[] = '<span style="color:#57606a;">Download XLSX (not found)</span>';
    }
    $links[] = '<a href="' . htmlspecialchars($hrefs['rebuild_href'], ENT_QUOTES, 'UTF-8') . '">Rebuild report</a>';
    $links[] = '<a href="' . htmlspecialchars($hrefs['rerun_href'], ENT_QUOTES, 'UTF-8') . '">Re-Run Test</a>';
    $links[] = '<a href="' . htmlspecialchars($hrefs['runner_href'], ENT_QUOTES, 'UTF-8') . '">Run QA runner</a>';

    return implode(' &middot; ', $links);
}

function mbqa_clean_tests_cli_help(): void
{
    echo "Module QA clean tests helper\n\n";
    echo "Runs the same cleanup used by module_browser_qa_runner.php before/after runs.\n\n";
    echo "Usage:\n";
    echo "  php scripts/module_clean_tests_qa_runner.php\n";
    echo "  php scripts/module_clean_tests_qa_runner.php --help\n";
}

/**
 * Delete rows created by module_browser_qa_runner by signature across all text columns.
 *
 * Why: aborted runs can leave MBQA/QA-IMPORT rows in many modules, not only equipment artifacts.
 *
 * @return array{ok:bool,deleted_total:int,detached_total:int,tables_touched:int,table_details:array<string,array{deleted:int,detached:int}>,errors:array<int,string>,warnings:array<int,string>}
 */
function mbqa_clean_tests_delete_runner_seed_rows(mysqli $conn): array
{
    $result = [
        'ok' => true,
        'deleted_total' => 0,
        'detached_total' => 0,
        'tables_touched' => 0,
        'table_details' => [],
        'errors' => [],
        'warnings' => [],
    ];

    $dbRes = mysqli_query($conn, 'SELECT DATABASE() AS db_name');
    $dbRow = $dbRes ? mysqli_fetch_assoc($dbRes) : null;
    $dbName = trim((string)($dbRow['db_name'] ?? ''));
    if ($dbName === '') {
        $result['ok'] = false;
        $result['errors'][] = 'Cannot resolve current database name.';
        return $result;
    }

    $dbNameEsc = mysqli_real_escape_string($conn, $dbName);

    // Fetch all tables first so we can track unblocking even for tables without text columns.
    $tableRes = mysqli_query($conn, "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='{$dbNameEsc}' AND TABLE_TYPE='BASE TABLE'");
    if (!$tableRes) {
        $result['ok'] = false;
        $result['errors'][] = 'INFORMATION_SCHEMA.TABLES scan failed: ' . mysqli_error($conn);
        return $result;
    }

    $tableDeleteSpecs = [];
    while ($tableRow = mysqli_fetch_assoc($tableRes)) {
        $tableName = trim((string)($tableRow['TABLE_NAME'] ?? ''));
        if ($tableName === '' || (function_exists('itm_is_safe_identifier') && !itm_is_safe_identifier($tableName))) {
            continue;
        }

        $tableEsc = '`' . str_replace('`', '``', $tableName) . '`';
        $tableDeleteSpecs[$tableName] = [
            'where_sql' => '1=0',
            'where_sql_child' => '1=0',
            'where_sql_parent' => '1=0',
            'delete_sql' => 'DELETE FROM ' . $tableEsc . ' WHERE 1=0',
            'count_sql' => 'SELECT COUNT(*) AS c FROM ' . $tableEsc . ' WHERE 1=0',
        ];
    }

    // Now scan text columns to build real predicates for tables that can contain signature data.
    $metaSql = "SELECT TABLE_NAME, COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA='{$dbNameEsc}'
          AND DATA_TYPE IN ('char','varchar','tinytext','text','mediumtext','longtext')";
    $metaRes = mysqli_query($conn, $metaSql);
    if (!$metaRes) {
        $result['ok'] = false;
        $result['errors'][] = 'INFORMATION_SCHEMA.COLUMNS scan failed: ' . mysqli_error($conn);
        return $result;
    }

    $textColumnsByTable = [];
    while ($metaRow = mysqli_fetch_assoc($metaRes)) {
        $tableName = trim((string)($metaRow['TABLE_NAME'] ?? ''));
        $columnName = trim((string)($metaRow['COLUMN_NAME'] ?? ''));
        if ($tableName === '' || $columnName === '') {
            continue;
        }
        $textColumnsByTable[$tableName][] = $columnName;
    }

    foreach ($textColumnsByTable as $tableName => $columns) {
        if (!isset($tableDeleteSpecs[$tableName])) {
            continue;
        }

        $predicates = [];
        $predicatesChild = [];
        $predicatesParent = [];
        foreach ($columns as $columnName) {
            if ($columnName === '' || (function_exists('itm_is_safe_identifier') && !itm_is_safe_identifier($columnName))) {
                continue;
            }

            $colEsc = '`' . str_replace('`', '``', $columnName) . '`';
            $patterns = [
                "LOWER(COALESCE(%s, '')) LIKE '%%mbqa-%%'",
                "UPPER(COALESCE(%s, '')) LIKE '%%QA-IMPORT-%%'",
                "LOWER(COALESCE(%s, '')) LIKE '%%qa-import-%%'",
                "LOWER(COALESCE(%s, '')) LIKE '%%itm %%test%%'",
                "LOWER(COALESCE(%s, '')) LIKE '%%itm debug %%'",
                "LOWER(COALESCE(%s, '')) LIKE '%%is_mbqa_equipment_types_%%'",
                "LOWER(COALESCE(%s, '')) LIKE '%%is_qa_import_name_%%'",
            ];

            foreach ($patterns as $pattern) {
                $predicates[] = sprintf($pattern, $colEsc);
                $predicatesChild[] = sprintf($pattern, 'c.' . $colEsc);
                $predicatesParent[] = sprintf($pattern, 'p.' . $colEsc);
            }
        }

        $predicates = array_values(array_unique($predicates));
        $predicatesChild = array_values(array_unique($predicatesChild));
        $predicatesParent = array_values(array_unique($predicatesParent));
        if (empty($predicates) || empty($predicatesChild) || empty($predicatesParent)) {
            continue;
        }

        $tableEsc = '`' . str_replace('`', '``', $tableName) . '`';
        $whereSql = implode(' OR ', $predicates);
        $tableDeleteSpecs[$tableName]['where_sql'] = $whereSql;
        $tableDeleteSpecs[$tableName]['where_sql_child'] = implode(' OR ', $predicatesChild);
        $tableDeleteSpecs[$tableName]['where_sql_parent'] = implode(' OR ', $predicatesParent);
        $tableDeleteSpecs[$tableName]['delete_sql'] = 'DELETE FROM ' . $tableEsc . ' WHERE ' . $whereSql;
        $tableDeleteSpecs[$tableName]['count_sql'] = 'SELECT COUNT(*) AS c FROM ' . $tableEsc . ' WHERE ' . $whereSql;
    }

    $inboundRefs = [];
    $fkSql = "SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, c.IS_NULLABLE
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
        INNER JOIN INFORMATION_SCHEMA.COLUMNS c ON k.TABLE_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME AND k.COLUMN_NAME = c.COLUMN_NAME
        WHERE k.TABLE_SCHEMA='{$dbNameEsc}'
          AND k.REFERENCED_TABLE_SCHEMA='{$dbNameEsc}'
          AND k.REFERENCED_TABLE_NAME IS NOT NULL";
    $fkRes = mysqli_query($conn, $fkSql);
    if ($fkRes) {
        while ($fkRow = mysqli_fetch_assoc($fkRes)) {
            $childTable = trim((string)($fkRow['TABLE_NAME'] ?? ''));
            $childColumn = trim((string)($fkRow['COLUMN_NAME'] ?? ''));
            $parentTable = trim((string)($fkRow['REFERENCED_TABLE_NAME'] ?? ''));
            $parentColumn = trim((string)($fkRow['REFERENCED_COLUMN_NAME'] ?? ''));
            $isNullable = (strtoupper((string)($fkRow['IS_NULLABLE'] ?? 'NO')) === 'YES');

            if (!isset($tableDeleteSpecs[$parentTable])) {
                continue;
            }
            if (($childTable === '' || $childColumn === '' || $parentTable === '' || $parentColumn === '')
                || (function_exists('itm_is_safe_identifier')
                    && (!itm_is_safe_identifier($childTable)
                        || !itm_is_safe_identifier($childColumn)
                        || !itm_is_safe_identifier($parentTable)
                        || !itm_is_safe_identifier($parentColumn)))) {
                continue;
            }

            $key = $childTable . '|' . $childColumn . '|' . $parentTable . '|' . $parentColumn;
            $inboundRefs[$parentTable][$key] = [
                'child_table' => $childTable,
                'child_column' => $childColumn,
                'parent_column' => $parentColumn,
                'is_nullable' => $isNullable,
            ];
        }
    }

    $protectedTables = ['companies', 'users'];
    $fkBlockedTables = [];
    for ($pass = 1; $pass <= 8; $pass++) {
        $passDeleted = 0;
        foreach ($tableDeleteSpecs as $tableName => $spec) {
            if (isset($inboundRefs[$tableName]) && is_array($inboundRefs[$tableName])) {
                foreach ($inboundRefs[$tableName] as $ref) {
                    $childTable = (string)$ref['child_table'];
                    $childTableEsc = '`' . str_replace('`', '``', $childTable) . '`';
                    $childColEsc = '`' . str_replace('`', '``', (string)$ref['child_column']) . '`';
                    $parentTableEsc = '`' . str_replace('`', '``', $tableName) . '`';
                    $parentColEsc = '`' . str_replace('`', '``', (string)$ref['parent_column']) . '`';

                    // If the child row itself is an MBQA row, it should be deleted even if the table is protected.
                    // If the child table is protected and the row is NOT an MBQA row, we should only NULL the FK (if nullable) to unblock parent.
                    $childIsProtected = in_array($childTable, $protectedTables, true);
                    $childWhereSqlAliased = isset($tableDeleteSpecs[$childTable]) ? (string)$tableDeleteSpecs[$childTable]['where_sql_child'] : '1=0';

                    if ($childIsProtected) {
                        // 1. Delete rows in protected table that are themselves MBQA rows.
                        $childDeleteSql = 'DELETE c FROM ' . $childTableEsc . ' c'
                            . ' INNER JOIN ' . $parentTableEsc . ' p ON c.' . $childColEsc . ' = p.' . $parentColEsc
                            . ' WHERE (' . (string)($spec['where_sql_parent'] ?? '') . ') AND (' . $childWhereSqlAliased . ')';
                        $childDeletedRes = mysqli_query($conn, $childDeleteSql);
                        if ($childDeletedRes) {
                            $childAffected = max(0, (int)mysqli_affected_rows($conn));
                            if ($childAffected > 0) {
                                $passDeleted += $childAffected;
                                $result['deleted_total'] += $childAffected;
                                if (!isset($result['table_details'][$childTable])) {
                                    $result['table_details'][$childTable] = ['deleted' => 0, 'detached' => 0];
                                }
                                $result['table_details'][$childTable]['deleted'] += $childAffected;
                            }
                        } else {
                            $result['ok'] = false;
                            $result['errors'][] = $childTable . ' MBQA row cleanup via ' . $tableName . ': ' . mysqli_error($conn);
                        }

                        // 2. Unblock parent by NULLing the FK in protected table for non-MBQA rows.
                        if (!empty($ref['is_nullable'])) {
                            $childUpdateSql = 'UPDATE ' . $childTableEsc . ' c'
                                . ' INNER JOIN ' . $parentTableEsc . ' p ON c.' . $childColEsc . ' = p.' . $parentColEsc
                                . ' SET c.' . $childColEsc . ' = NULL'
                                . ' WHERE (' . (string)($spec['where_sql_parent'] ?? '') . ') AND NOT (' . $childWhereSqlAliased . ')';
                            $childUpdatedRes = mysqli_query($conn, $childUpdateSql);
                            if ($childUpdatedRes) {
                                $childAffected = max(0, (int)mysqli_affected_rows($conn));
                                if ($childAffected > 0) {
                                    $passDeleted += $childAffected;
                                $result['detached_total'] += $childAffected;
                                if (!isset($result['table_details'][$childTable])) {
                                    $result['table_details'][$childTable] = ['deleted' => 0, 'detached' => 0];
                                }
                                $result['table_details'][$childTable]['detached'] += $childAffected;
                                }
                            } else {
                                $result['ok'] = false;
                                $result['errors'][] = $childTable . ' FK detachment via ' . $tableName . ': ' . mysqli_error($conn);
                            }
                        }
                    } else {
                        // Non-protected table: standard unblocking by deletion.
                        $childDeleteSql = 'DELETE c FROM ' . $childTableEsc . ' c'
                            . ' INNER JOIN ' . $parentTableEsc . ' p ON c.' . $childColEsc . ' = p.' . $parentColEsc
                            . ' WHERE ' . (string)($spec['where_sql_parent'] ?? '');
                        $childDeletedRes = mysqli_query($conn, $childDeleteSql);
                        if (!$childDeletedRes) {
                            $errNo = (int)mysqli_errno($conn);
                            if ($errNo !== 1451) {
                                $result['ok'] = false;
                                $result['errors'][] = $childTable . ' cleanup via ' . $tableName . ': ' . mysqli_error($conn);
                            }
                            continue;
                        }

                        $childAffected = max(0, (int)mysqli_affected_rows($conn));
                        if ($childAffected > 0) {
                            $passDeleted += $childAffected;
                            $result['deleted_total'] += $childAffected;
                            if (!isset($result['table_details'][$childTable])) {
                                $result['table_details'][$childTable] = ['deleted' => 0, 'detached' => 0];
                            }
                            $result['table_details'][$childTable]['deleted'] += $childAffected;
                        }
                    }
                }
            }

            $deletedRes = mysqli_query($conn, (string)$spec['delete_sql']);
            if (!$deletedRes) {
                $errNo = (int)mysqli_errno($conn);
                if ($errNo === 1451) {
                    $fkBlockedTables[$tableName] = true;
                    continue;
                }

                $result['ok'] = false;
                $result['errors'][] = $tableName . ' cleanup: ' . mysqli_error($conn);
                continue;
            }

            $affected = max(0, (int)mysqli_affected_rows($conn));
            if ($affected > 0) {
                $passDeleted += $affected;
                $result['deleted_total'] += $affected;
                if (!isset($result['table_details'][$tableName])) {
                    $result['table_details'][$tableName] = ['deleted' => 0, 'detached' => 0];
                }
                $result['table_details'][$tableName]['deleted'] += $affected;
                unset($fkBlockedTables[$tableName]);
            }
        }

        if ($passDeleted === 0) {
            break;
        }
    }

    $result['tables_touched'] = count($result['table_details']);
    foreach (array_keys($fkBlockedTables) as $tableName) {
        if (!isset($tableDeleteSpecs[$tableName]['count_sql'])) {
            continue;
        }

        $countRes = mysqli_query($conn, (string)$tableDeleteSpecs[$tableName]['count_sql']);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        $remaining = (int)($countRow['c'] ?? 0);
        if ($remaining > 0) {
            $result['warnings'][] = $tableName . ': kept ' . $remaining . ' QA-signature row(s) because non-QA rows still reference them.';
        }
    }

    return $result;
}

/**
 * @return array{
 *   ok:bool,
 *   dirs_removed:int,
 *   companies_deleted:int,
 *   types_deleted:int,
 *   sidebar_deleted:int,
 *   canonical_ensured:int,
 *   runner_rows_deleted:int,
 *   runner_rows_detached:int,
 *   runner_tables_touched:int,
 *   runner_table_details:array<string,array{deleted:int,detached:int}>,
 *   errors:array<int,string>,
 *   warnings:array<int,string>
 * }
 */
function mbqa_clean_tests_run_cleanup(): array
{
    if (mbqa_clean_tests_is_cli() && !defined('ITM_CLI_SCRIPT')) {
        define('ITM_CLI_SCRIPT', true);
    }

    $conn = null;
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $conn = $GLOBALS['conn'];
    } else {
        require_once dirname(__DIR__) . '/config/config.php';
        if (isset($conn) && $conn instanceof mysqli) {
            // Loaded in local scope by include.
        } elseif (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            $conn = $GLOBALS['conn'];
        }
    }
    require_once __DIR__ . '/lib/equipment_type_modules.php';

    if (!($conn instanceof mysqli)) {
        return [
            'ok' => false,
            'dirs_removed' => 0,
            'companies_deleted' => 0,
            'types_deleted' => 0,
            'sidebar_deleted' => 0,
            'canonical_ensured' => 0,
            'runner_rows_deleted' => 0,
            'runner_tables_touched' => 0,
            'errors' => ['Database connection is not available.'],
            'warnings' => [],
        ];
    }

    $modulesRoot = dirname(__DIR__) . '/modules';
    $equipmentCleanup = itm_run_equipment_test_module_artifacts_cleanup($conn, $modulesRoot);
    $runnerCleanup = mbqa_clean_tests_delete_runner_seed_rows($conn);

    $equipmentCleanup['ok'] = $equipmentCleanup['ok'] && $runnerCleanup['ok'];
    $equipmentCleanup['runner_rows_deleted'] = (int)($runnerCleanup['deleted_total'] ?? 0);
    $equipmentCleanup['runner_rows_detached'] = (int)($runnerCleanup['detached_total'] ?? 0);
    $equipmentCleanup['runner_tables_touched'] = (int)($runnerCleanup['tables_touched'] ?? 0);
    $equipmentCleanup['runner_table_details'] = (array)($runnerCleanup['table_details'] ?? []);
    $equipmentCleanup['errors'] = array_values(array_merge(
        $equipmentCleanup['errors'] ?? [],
        $runnerCleanup['errors'] ?? []
    ));
    $equipmentCleanup['warnings'] = array_values(array_merge(
        $equipmentCleanup['warnings'] ?? [],
        $runnerCleanup['warnings'] ?? []
    ));

    return $equipmentCleanup;
}

// Allow other scripts (for example module_browser_qa_runner.php) to reuse
// cleanup helpers without executing this file's CLI/browser entrypoint.
if (defined('MBQA_CLEAN_TESTS_LIBRARY_MODE') && MBQA_CLEAN_TESTS_LIBRARY_MODE === true) {
    return;
}

$options = mbqa_clean_tests_parse_options();
$root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$hrefs = mbqa_clean_tests_action_hrefs($root);

if (!mbqa_clean_tests_is_cli()) {
    require_once dirname(__DIR__) . '/config/config.php';
}

if ($options['help']) {
    if (mbqa_clean_tests_is_cli()) {
        mbqa_clean_tests_cli_help();
        exit(0);
    }

    header('Content-Type: text/html; charset=utf-8');
    itm_script_browser_nav_echo();
    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:860px;margin:16px;line-height:1.5;">';
    echo '<h1>Clean tests for module QA runner</h1>';
    echo '<p>Runs the same cleanup used automatically at the end of <code>module_browser_qa_runner.php</code>:</p>';
    echo '<ul>';
    echo '<li>Remove temporary equipment scaffold module folders under <code>modules/</code>.</li>';
    echo '<li>Delete QA/test rows in <code>equipment_types</code>, test company rows, and sidebar artifacts.</li>';
    echo '<li>Delete MBQA / QA-IMPORT runner-seeded rows across DB tables by signature.</li>';
    echo '<li>Re-ensure canonical <code>modules/is_*</code> facades.</li>';
    echo '</ul>';
    echo '<p><a href="module_clean_tests_qa_runner.php">Back to clean tests page</a></p>';
    echo '</main>';
    exit(0);
}

if (!$options['run']) {
    if (mbqa_clean_tests_is_cli()) {
        mbqa_clean_tests_cli_help();
        exit(0);
    }

    header('Content-Type: text/html; charset=utf-8');
    itm_script_browser_nav_echo();
    echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:860px;margin:16px;line-height:1.5;">';
    echo '<h1>Clean tests for module QA runner</h1>';
    echo '<p><strong>Destructive (local dev DB):</strong> runs the same cleanup used by the QA runner and removes known test artifacts plus MBQA / QA-IMPORT rows by signature.</p>';
    echo '<p>This does <strong>not</strong> remove canonical equipment modules like <code>is_switch</code> or <code>is_server</code>.</p>';
    echo '<form method="post" action="module_clean_tests_qa_runner.php" style="margin:16px 0;">';
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars((string)itm_get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="run" value="1">';
    echo '<button type="submit" style="padding:10px 16px;font-weight:600;">Run Clean Tests</button>';
    echo '</form>';
    echo '<p style="font-size:0.95rem;">' . mbqa_clean_tests_action_links_html($hrefs) . '</p>';
    echo '<p style="font-size:0.9rem;"><a href="module_clean_tests_qa_runner.php?help=1">Help</a></p>';
    echo '</main>';
    exit(0);
}

$isBrowserRun = !mbqa_clean_tests_is_cli() && $options['run'];
if ($isBrowserRun) {
    itm_require_post_csrf();
}

$cleanup = mbqa_clean_tests_run_cleanup();
$exitCode = $cleanup['ok'] ? 0 : 1;

if (mbqa_clean_tests_is_cli()) {
    if ($cleanup['dirs_removed'] > 0) {
        fwrite(STDOUT, colorText("[OK] Removed {$cleanup['dirs_removed']} regression-test / QA scaffold module folder(s)", 'pass') . "\n");
    } else {
        fwrite(STDOUT, colorText('[OK] No regression-test module folders to remove', 'pass') . "\n");
    }

    if ($cleanup['companies_deleted'] > 0) {
        $noun = $cleanup['companies_deleted'] === 1 ? 'y' : 'ies';
        fwrite(STDOUT, colorText("[OK] Removed {$cleanup['companies_deleted']} ITM test compan{$noun}", 'pass') . "\n");
    }

    if ($cleanup['types_deleted'] > 0) {
        fwrite(STDOUT, colorText("[OK] Removed {$cleanup['types_deleted']} equipment_types test row(s)", 'pass') . "\n");
    } elseif ($cleanup['ok']) {
        fwrite(STDOUT, colorText('[OK] No equipment_types test rows to remove', 'pass') . "\n");
    }

    if ($cleanup['sidebar_deleted'] > 0) {
        fwrite(STDOUT, colorText("[OK] Removed {$cleanup['sidebar_deleted']} user_sidebar_preferences test row(s)", 'pass') . "\n");
    }

    if ($cleanup['runner_rows_deleted'] > 0 || $cleanup['runner_rows_detached'] > 0) {
        fwrite(STDOUT, colorText('[OK] Cleaned MBQA/QA-IMPORT signature data:', 'pass') . "\n");
        foreach ($cleanup['runner_table_details'] as $table => $counts) {
            $parts = [];
            if ($counts['deleted'] > 0) {
                $parts[] = "{$counts['deleted']} deleted";
            }
            if ($counts['detached'] > 0) {
                $parts[] = "{$counts['detached']} FKs detached";
            }
            fwrite(STDOUT, "     - {$table}: " . implode(', ', $parts) . "\n");
        }
        fwrite(STDOUT, "     Total: {$cleanup['runner_rows_deleted']} row(s) deleted, {$cleanup['runner_rows_detached']} FK(s) detached across {$cleanup['runner_tables_touched']} table(s)\n");
    } elseif ($cleanup['ok']) {
        fwrite(STDOUT, colorText('[OK] No MBQA/QA-IMPORT signature rows found across DB tables', 'pass') . "\n");
    }

    fwrite(STDOUT, colorText("[OK] Verified canonical modules/is_* facades ({$cleanup['canonical_ensured']} scaffold pass(es))", 'pass') . "\n");

    foreach ($cleanup['errors'] as $errorLine) {
        fwrite(STDERR, colorText('[FAIL] ' . $errorLine, 'fail') . "\n");
    }
    foreach (($cleanup['warnings'] ?? []) as $warningLine) {
        fwrite(STDOUT, colorText('[WARN] ' . $warningLine, 'warn') . "\n");
    }

    fwrite(STDOUT, "\nSummary: {$cleanup['dirs_removed']} test/QA scaffold folder(s) removed; {$cleanup['runner_rows_deleted']} MBQA/QA-IMPORT row(s) removed; {$cleanup['runner_rows_detached']} FK(s) detached; canonical is_* modules preserved.\n");
    exit($exitCode);
}

header('Content-Type: text/html; charset=utf-8');
itm_script_browser_nav_echo();
echo '<main style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Helvetica,Arial,sans-serif;max-width:860px;margin:16px;line-height:1.5;">';
echo '<h1>Clean tests completed</h1>';
if ($cleanup['ok']) {
    echo '<p style="color:#1a7f37;"><strong>Cleanup finished.</strong> Test artifacts were removed where found.</p>';
} else {
    echo '<p style="color:#cf222e;"><strong>Cleanup finished with errors.</strong> Review details below.</p>';
}

echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:780px;">';
echo '<thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
echo '<tr><td>Scaffold folders removed</td><td>' . (int)$cleanup['dirs_removed'] . '</td></tr>';
echo '<tr><td>Test companies removed</td><td>' . (int)$cleanup['companies_deleted'] . '</td></tr>';
echo '<tr><td>equipment_types rows removed</td><td>' . (int)$cleanup['types_deleted'] . '</td></tr>';
echo '<tr><td>Sidebar test rows removed</td><td>' . (int)$cleanup['sidebar_deleted'] . '</td></tr>';
echo '<tr><td>MBQA / QA-IMPORT rows removed</td><td>' . (int)$cleanup['runner_rows_deleted'] . '</td></tr>';
echo '<tr><td>MBQA / QA-IMPORT FKs detached</td><td>' . (int)$cleanup['runner_rows_detached'] . '</td></tr>';
echo '<tr><td>Tables touched (signature cleanup)</td><td>' . (int)$cleanup['runner_tables_touched'] . '</td></tr>';
echo '<tr><td>Canonical facade ensure passes</td><td>' . (int)$cleanup['canonical_ensured'] . '</td></tr>';
echo '</tbody></table>';

if ($cleanup['runner_rows_deleted'] > 0 || $cleanup['runner_rows_detached'] > 0) {
    echo '<h2>MBQA Cleanup Details</h2>';
    echo '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;max-width:780px;font-size:0.9rem;">';
    echo '<thead><tr><th>Table</th><th>Deleted Rows</th><th>FKs Detached</th></tr></thead><tbody>';
    foreach ($cleanup['runner_table_details'] as $table => $counts) {
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars($table, ENT_QUOTES, 'UTF-8') . '</code></td>';
        echo '<td>' . (int)$counts['deleted'] . '</td>';
        echo '<td>' . (int)$counts['detached'] . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

if (!empty($cleanup['errors'])) {
    echo '<h2>Errors</h2><ul>';
    foreach ($cleanup['errors'] as $errorLine) {
        echo '<li style="color:#cf222e;">' . htmlspecialchars((string)$errorLine, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul>';
}

if (!empty($cleanup['warnings'])) {
    echo '<h2>Warnings</h2><ul>';
    foreach ($cleanup['warnings'] as $warningLine) {
        echo '<li style="color:#9a6700;">' . htmlspecialchars((string)$warningLine, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul>';
}

echo '<p style="font-size:0.95rem;">' . mbqa_clean_tests_action_links_html($hrefs) . '</p>';
echo '</main>';
exit($exitCode);
