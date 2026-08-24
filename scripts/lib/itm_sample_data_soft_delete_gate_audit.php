<?php
/**
 * Audit Add sample data gates vs soft-deleted list rows.
 *
 * Static: flags module index.php files that still use raw COUNT(*) for the sample gate
 * instead of itm_seed_tenant_row_count() (live rows: deleted_at IS NULL).
 *
 * Live (optional company_id): for drift modules whose table has deleted_at, compares
 * raw tenant row count vs live count and marks REPRO when list would be empty but the
 * legacy gate would still block seeding.
 */

if (!function_exists('itm_sample_data_gate_audit_module_slugs')) {
    /**
     * @return list<string>
     */
    function itm_sample_data_gate_audit_module_slugs(string $root): array
    {
        $root = rtrim($root, '/\\');
        $modulesRoot = $root . '/modules';
        if (!is_dir($modulesRoot)) {
            return [];
        }

        $slugs = [];
        foreach (scandir($modulesRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_file($modulesRoot . '/' . $entry . '/index.php')) {
                $slugs[] = $entry;
            }
        }
        sort($slugs);

        return $slugs;
    }
}

if (!function_exists('itm_sample_data_gate_audit_extract_table')) {
    function itm_sample_data_gate_audit_extract_table(string $indexContent): ?string
    {
        if (preg_match('/\$crud_table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $indexContent, $matches) === 1) {
            return (string) $matches[1];
        }

        return null;
    }
}

if (!function_exists('itm_sample_data_gate_audit_classify_index')) {
    /**
     * @return array{
     *   slug:string,
     *   table:?string,
     *   has_add_sample:bool,
     *   gate:string,
     *   list_filters_deleted:bool,
     *   notes:string
     * }
     */
    function itm_sample_data_gate_audit_classify_index(string $slug, string $indexContent): array
    {
        $hasAddSample = strpos($indexContent, 'add_sample_data') !== false;
        $table = itm_sample_data_gate_audit_extract_table($indexContent);
        $listFiltersDeleted = strpos($indexContent, 'itm_crud_append_not_deleted_predicate') !== false;

        $gate = 'none';
        $notes = '';

        if (!$hasAddSample) {
            return [
                'slug' => $slug,
                'table' => $table,
                'has_add_sample' => false,
                'gate' => 'none',
                'list_filters_deleted' => $listFiltersDeleted,
                'notes' => 'No Add sample data handler',
            ];
        }

        $sampleBlock = '';
        if (preg_match(
            '/if\s*\(\$_SERVER\[\'REQUEST_METHOD\'\]\s*===\s*\'POST\'.*?isset\(\$_POST\[\'add_sample_data\'\]\).*?(?=\nif\s*\(\$_SERVER\[\'REQUEST_METHOD\'\]\s*===\s*\'POST\'|\n\/\/ BUILD THE MAIN LIST DATA QUERY|\Z)/s',
            $indexContent,
            $blockMatch
        ) === 1) {
            $sampleBlock = (string) $blockMatch[0];
        } else {
            $sampleBlock = $indexContent;
            $notes = 'Could not isolate sample handler block; scanned full file';
        }

        // Why: Ignore temporary debug instrumentation so classification follows the real gate.
        $sampleBlock = (string) preg_replace('/\/\/ #region agent log.*?\/\/ #endregion/s', '', $sampleBlock);

        $usesSeedCount = preg_match('/\$existingRows\s*=\s*[\s\S]*?itm_seed_tenant_row_count\s*\(/', $sampleBlock) === 1;
        $usesRawCount = !$usesSeedCount && (
            preg_match('/\$countSql\s*=[\s\S]*?COUNT\(\*\)\s+AS\s+total_rows/', $sampleBlock) === 1
            || preg_match('/\$existingRows\s*=\s*[\s\S]*?\$countSql/', $sampleBlock) === 1
        );
        $usesSharedSeeder = strpos($sampleBlock, 'itm_seed_table_from_database_sql') !== false;
        $usesInlineInsert = strpos($sampleBlock, 'INSERT INTO') !== false && strpos($sampleBlock, 'sampleRows') !== false;

        if ($usesSeedCount) {
            $gate = 'live_rows';
        } elseif ($usesRawCount) {
            $gate = 'raw_count';
        } else {
            $gate = 'unknown';
            $notes = trim($notes . ' Sample gate pattern not recognized');
        }

        if ($usesInlineInsert && !$usesSharedSeeder) {
            $notes = trim($notes . ' Uses bespoke inline INSERT (may not match db/02_data_sample.sql schema)');
        }

        return [
            'slug' => $slug,
            'table' => $table,
            'has_add_sample' => true,
            'gate' => $gate,
            'list_filters_deleted' => $listFiltersDeleted,
            'notes' => $notes,
        ];
    }
}

if (!function_exists('itm_sample_data_gate_audit_live_counts')) {
    /**
     * @return array{raw:int,live:int,soft_deleted:int,has_deleted_at:bool}
     */
    function itm_sample_data_gate_audit_live_counts(mysqli $conn, string $table, int $companyId): array
    {
        if (!itm_is_safe_identifier($table) || $companyId <= 0 || !itm_table_has_column($conn, $table, 'company_id')) {
            return ['raw' => 0, 'live' => 0, 'soft_deleted' => 0, 'has_deleted_at' => false];
        }

        $hasDeletedAt = itm_table_has_column($conn, $table, 'deleted_at');
        $tableSql = '`' . str_replace('`', '``', $table) . '`';

        $raw = 0;
        $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM ' . $tableSql . ' WHERE company_id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            $raw = (int) ($row['c'] ?? 0);
        }

        $live = $raw;
        if ($hasDeletedAt && function_exists('itm_seed_tenant_row_count')) {
            $live = itm_seed_tenant_row_count($conn, $table, $companyId);
        }

        return [
            'raw' => $raw,
            'live' => $live,
            'soft_deleted' => max(0, $raw - $live),
            'has_deleted_at' => $hasDeletedAt,
        ];
    }
}

if (!function_exists('itm_sample_data_gate_audit_run')) {
    /**
     * @param array{root:string,company_id?:int,module?:string,only_drift?:bool,only_repro?:bool} $options
     * @return list<array<string,mixed>>
     */
    function itm_sample_data_gate_audit_run(array $options): array
    {
        $root = rtrim((string) ($options['root'] ?? ''), '/\\');
        $companyId = isset($options['company_id']) ? (int) $options['company_id'] : 0;
        $moduleFilter = isset($options['module']) ? trim((string) $options['module']) : '';
        $onlyDrift = !empty($options['only_drift']);
        $onlyRepro = !empty($options['only_repro']);
        $conn = $options['conn'] ?? null;

        $rows = [];
        foreach (itm_sample_data_gate_audit_module_slugs($root) as $slug) {
            if ($moduleFilter !== '' && $slug !== $moduleFilter) {
                continue;
            }

            $indexPath = $root . '/modules/' . $slug . '/index.php';
            $content = is_file($indexPath) ? (string) file_get_contents($indexPath) : '';
            if ($content === '') {
                continue;
            }

            $classified = itm_sample_data_gate_audit_classify_index($slug, $content);
            if (!$classified['has_add_sample']) {
                if (!$onlyDrift && !$onlyRepro) {
                    $rows[] = $classified + [
                        'status' => 'skip',
                        'raw' => null,
                        'live' => null,
                        'soft_deleted' => null,
                        'repro' => false,
                    ];
                }
                continue;
            }

            $drift = $classified['gate'] === 'raw_count';

            if ($onlyDrift && !$drift) {
                continue;
            }

            $row = $classified + [
                'status' => $drift ? 'drift' : 'ok',
                'raw' => null,
                'live' => null,
                'soft_deleted' => null,
                'repro' => false,
            ];

            if ($companyId > 0 && $conn instanceof mysqli && !empty($classified['table'])) {
                $counts = itm_sample_data_gate_audit_live_counts($conn, (string) $classified['table'], $companyId);
                $row['raw'] = $counts['raw'];
                $row['live'] = $counts['live'];
                $row['soft_deleted'] = $counts['soft_deleted'];
            if ($drift && $counts['has_deleted_at'] && $counts['live'] === 0 && $counts['raw'] > 0) {
                $row['repro'] = true;
                $row['status'] = 'repro';
            } elseif ($drift) {
                $row['status'] = 'drift';
            }
            }

            if ($onlyRepro && empty($row['repro'])) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
