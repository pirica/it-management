<?php
/**
 * Database migration runner helpers (db/migrations/*.sql + schema_migrations).
 *
 * Why: Production upgrades need filename-ordered SQL in one session with an applied log.
 */

if (!function_exists('itm_database_migrations_root_path')) {
    function itm_database_migrations_root_path()
    {
        return defined('ROOT_PATH')
            ? rtrim((string)ROOT_PATH, '/\\') . '/db/migrations'
            : dirname(__DIR__) . '/db/migrations';
    }
}

if (!function_exists('itm_database_migrations_bootstrap_filenames')) {
    /**
     * Files handled by table bootstrap — not executed by the runner loop.
     *
     * @return string[]
     */
    function itm_database_migrations_bootstrap_filenames()
    {
        return ['schema_migrations.sql'];
    }
}

if (!function_exists('itm_database_migrations_create_table_sql')) {
    function itm_database_migrations_create_table_sql()
    {
        return <<<'SQL'
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `checksum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schema_migrations_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    }
}

if (!function_exists('itm_database_migrations_table_exists')) {
    function itm_database_migrations_table_exists($conn)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $schema = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $schemaEsc = mysqli_real_escape_string($conn, $schema);
        $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = '{$schemaEsc}' AND table_name = 'schema_migrations' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            return false;
        }
        $exists = (bool)mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return $exists;
    }
}

if (!function_exists('itm_database_migrations_ensure_table')) {
    function itm_database_migrations_ensure_table($conn)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        if (itm_database_migrations_table_exists($conn)) {
            return true;
        }

        return mysqli_query($conn, itm_database_migrations_create_table_sql()) === true;
    }
}

if (!function_exists('itm_database_migrations_file_checksum')) {
    function itm_database_migrations_file_checksum($absolutePath)
    {
        $absolutePath = (string)$absolutePath;
        if ($absolutePath === '' || !is_file($absolutePath)) {
            return '';
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            return '';
        }

        if (strncmp($contents, "\xEF\xBB\xBF", 3) === 0) {
            $contents = substr($contents, 3);
        }

        return hash('sha256', $contents);
    }
}

if (!function_exists('itm_database_migrations_discover_files')) {
    /**
     * @return array<int, array{filename: string, path: string, checksum: string}>
     */
    function itm_database_migrations_discover_files()
    {
        $root = itm_database_migrations_root_path();
        $skip = array_fill_keys(itm_database_migrations_bootstrap_filenames(), true);
        $files = glob($root . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $rows = [];
        foreach ($files as $path) {
            $filename = basename((string)$path);
            if ($filename === '' || isset($skip[$filename])) {
                continue;
            }
            $checksum = itm_database_migrations_file_checksum($path);
            if ($checksum === '') {
                continue;
            }
            $rows[] = [
                'filename' => $filename,
                'path' => $path,
                'checksum' => $checksum,
            ];
        }

        return $rows;
    }
}

if (!function_exists('itm_database_migrations_probe_lib_path')) {
    function itm_database_migrations_probe_lib_path()
    {
        return dirname(__DIR__) . '/scripts/lib/itm_verify_db_migrations_report.php';
    }
}

if (!function_exists('itm_database_migrations_schema_satisfied')) {
    /**
     * Whether live schema/data already matches this migration file (fresh db/ import path).
     *
     * @return array{0: bool, 1: string} satisfied, probe detail
     */
    function itm_database_migrations_schema_satisfied($conn, $filename)
    {
        $filename = (string)$filename;
        if ($filename === '' || !($conn instanceof mysqli)) {
            return [false, ''];
        }

        $probeLib = itm_database_migrations_probe_lib_path();
        if (!is_file($probeLib)) {
            return [false, ''];
        }
        if (!function_exists('itm_verify_db_migrations_probe_file')) {
            require_once $probeLib;
        }

        $probe = itm_verify_db_migrations_probe_file($conn, $filename);
        $status = (string)($probe['status'] ?? '');
        $label = (string)($probe['label'] ?? '');
        $detail = (string)($probe['detail'] ?? '');

        if ($status === 'pass' || $status === 'superseded') {
            $message = $detail;
            if ($label !== '' && stripos($detail, $label) !== 0) {
                $message = $label . ($detail !== '' ? ': ' . $detail : '');
            }

            return [true, $message];
        }

        return [false, $detail];
    }
}

if (!function_exists('itm_database_migrations_fetch_applied_map')) {
    /**
     * @return array<string, array{filename: string, checksum: string, applied_at: string}>
     */
    function itm_database_migrations_fetch_applied_map($conn)
    {
        $map = [];
        if (!($conn instanceof mysqli) || !itm_database_migrations_table_exists($conn)) {
            return $map;
        }

        $res = mysqli_query($conn, 'SELECT filename, checksum, applied_at FROM schema_migrations ORDER BY filename ASC');
        if (!$res) {
            return $map;
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $filename = (string)($row['filename'] ?? '');
            if ($filename === '') {
                continue;
            }
            $map[$filename] = [
                'filename' => $filename,
                'checksum' => (string)($row['checksum'] ?? ''),
                'applied_at' => (string)($row['applied_at'] ?? ''),
            ];
        }
        mysqli_free_result($res);

        return $map;
    }
}

if (!function_exists('itm_database_migrations_build_status')) {
    /**
     * @return array{
     *   ok: bool,
     *   database: string,
     *   table_ready: bool,
     *   file_count: int,
     *   applied_count: int,
     *   pending_count: int,
     *   drift_count: int,
     *   migrations: array<int, array<string, mixed>>
     * }
     */
    function itm_database_migrations_build_status($conn)
    {
        $database = defined('DB_NAME') ? (string)DB_NAME : 'itmanagement';
        $tableReady = itm_database_migrations_ensure_table($conn);
        $discovered = itm_database_migrations_discover_files();
        $appliedMap = itm_database_migrations_fetch_applied_map($conn);

        $rows = [];
        $pendingCount = 0;
        $driftCount = 0;

        foreach ($discovered as $fileRow) {
            $filename = (string)$fileRow['filename'];
            $checksum = (string)$fileRow['checksum'];
            $applied = $appliedMap[$filename] ?? null;

            $recorded = ($applied !== null);

            if ($applied === null) {
                [$schemaSatisfied, $probeDetail] = itm_database_migrations_schema_satisfied($conn, $filename);
                if ($schemaSatisfied) {
                    $state = 'applied';
                    $label = 'Applied';
                    $detail = 'Live schema already matches'
                        . ($probeDetail !== '' ? ' (' . $probeDetail . ')' : '')
                        . '; not recorded in schema_migrations.';
                } else {
                    $state = 'pending';
                    $label = 'Pending';
                    $detail = $probeDetail !== ''
                        ? 'Not recorded in schema_migrations — ' . $probeDetail
                        : 'Not recorded in schema_migrations.';
                    $pendingCount++;
                }
            } elseif ($applied['checksum'] !== $checksum) {
                $state = 'drift';
                $label = 'Checksum drift';
                $detail = 'File changed after apply (applied ' . $applied['checksum'] . ' vs current ' . $checksum . ').';
                $driftCount++;
            } else {
                $state = 'applied';
                $label = 'Applied';
                $detail = 'Recorded at ' . ($applied['applied_at'] !== '' ? $applied['applied_at'] : 'unknown time') . '.';
            }

            $rows[] = [
                'filename' => $filename,
                'checksum' => $checksum,
                'state' => $state,
                'label' => $label,
                'detail' => $detail,
                'recorded' => $recorded,
                'applied_at' => $applied['applied_at'] ?? null,
            ];
        }

        $appliedCount = 0;
        foreach ($rows as $row) {
            if (($row['state'] ?? '') === 'applied') {
                $appliedCount++;
            }
        }

        return [
            'ok' => $tableReady && $driftCount === 0,
            'database' => $database,
            'table_ready' => $tableReady,
            'file_count' => count($discovered),
            'applied_count' => $appliedCount,
            'pending_count' => $pendingCount,
            'drift_count' => $driftCount,
            'migrations' => $rows,
        ];
    }
}

if (!function_exists('itm_database_migrations_drain_multi_query')) {
    function itm_database_migrations_drain_multi_query($conn)
    {
        if (!($conn instanceof mysqli)) {
            return [false, 'No database connection.'];
        }

        do {
            if ($result = mysqli_store_result($conn)) {
                mysqli_free_result($result);
            }
        } while (mysqli_more_results($conn) && mysqli_next_result($conn));

        if (mysqli_errno($conn)) {
            return [false, mysqli_error($conn)];
        }

        return [true, ''];
    }
}

if (!function_exists('itm_database_migrations_execute_sql_file')) {
    /**
     * @return array{0: bool, 1: string}
     */
    function itm_database_migrations_execute_sql_file($conn, $absolutePath)
    {
        if (!($conn instanceof mysqli)) {
            return [false, 'No database connection.'];
        }

        $absolutePath = (string)$absolutePath;
        if ($absolutePath === '' || !is_file($absolutePath)) {
            return [false, 'Migration file not found.'];
        }

        $sql = file_get_contents($absolutePath);
        if ($sql === false || trim($sql) === '') {
            return [false, 'Migration file is empty or unreadable.'];
        }

        if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
            $sql = substr($sql, 3);
        }

        if (!mysqli_multi_query($conn, $sql)) {
            return [false, mysqli_error($conn)];
        }

        [$drained, $drainError] = itm_database_migrations_drain_multi_query($conn);
        if (!$drained) {
            return [false, $drainError];
        }

        return [true, ''];
    }
}

if (!function_exists('itm_database_migrations_record_applied')) {
    function itm_database_migrations_record_applied($conn, $filename, $checksum)
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO schema_migrations (filename, checksum) VALUES (?, ?)'
        );
        if (!$stmt) {
            return false;
        }

        $filename = (string)$filename;
        $checksum = (string)$checksum;
        mysqli_stmt_bind_param($stmt, 'ss', $filename, $checksum);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }
}

if (!function_exists('itm_database_migrations_apply_pending')) {
    /**
     * @return array{
     *   ok: bool,
     *   applied: array<int, string>,
     *   recorded: array<int, string>,
     *   skipped: array<int, string>,
     *   errors: array<int, array{filename: string, message: string}>
     * }
     */
    function itm_database_migrations_apply_pending($conn, $dryRun = true)
    {
        $result = [
            'ok' => true,
            'applied' => [],
            'recorded' => [],
            'skipped' => [],
            'errors' => [],
        ];

        if (!($conn instanceof mysqli)) {
            $result['ok'] = false;
            $result['errors'][] = ['filename' => '', 'message' => 'No database connection.'];

            return $result;
        }

        if (!itm_database_migrations_ensure_table($conn)) {
            $result['ok'] = false;
            $result['errors'][] = ['filename' => '', 'message' => 'Could not ensure schema_migrations table.'];

            return $result;
        }

        $status = itm_database_migrations_build_status($conn);
        if ((int)($status['drift_count'] ?? 0) > 0) {
            $result['ok'] = false;
            $result['errors'][] = [
                'filename' => '',
                'message' => 'Checksum drift detected — resolve before applying new migrations.',
            ];

            return $result;
        }

        foreach ($status['migrations'] as $row) {
            $filename = (string)($row['filename'] ?? '');
            $state = (string)($row['state'] ?? '');
            if ($filename === '') {
                continue;
            }
            if ($state === 'applied') {
                if (!empty($row['recorded'])) {
                    $result['skipped'][] = $filename;
                    continue;
                }

                if ($dryRun) {
                    $result['recorded'][] = $filename;
                    continue;
                }

                if (!itm_database_migrations_record_applied($conn, $filename, (string)$row['checksum'])) {
                    $result['ok'] = false;
                    $result['errors'][] = [
                        'filename' => $filename,
                        'message' => 'Schema already matched but schema_migrations insert failed: ' . mysqli_error($conn),
                    ];
                    break;
                }

                $result['recorded'][] = $filename;
                continue;
            }
            if ($state !== 'pending') {
                continue;
            }

            if ($dryRun) {
                $result['applied'][] = $filename;
                continue;
            }

            $path = itm_database_migrations_root_path() . '/' . $filename;
            [$executed, $message] = itm_database_migrations_execute_sql_file($conn, $path);
            if (!$executed) {
                $result['ok'] = false;
                $result['errors'][] = ['filename' => $filename, 'message' => $message];
                break;
            }

            if (!itm_database_migrations_record_applied($conn, $filename, (string)$row['checksum'])) {
                $result['ok'] = false;
                $result['errors'][] = [
                    'filename' => $filename,
                    'message' => 'SQL ran but schema_migrations insert failed: ' . mysqli_error($conn),
                ];
                break;
            }

            $result['applied'][] = $filename;
        }

        return $result;
    }
}
