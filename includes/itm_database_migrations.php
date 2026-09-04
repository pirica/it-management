<?php
/**
 * Database migration runner helpers (db/migrations/*.sql + schema_migrations).
 *
 * Why: Production upgrades need filename-ordered SQL in one session with an applied log.
 * Applied/pending status always comes from live DB probes — schema_migrations is audit only.
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

if (!function_exists('itm_database_migrations_resolve_bootstrap_file')) {
    /**
     * Resolve a bootstrap migration file on disk (not runner apply scope).
     *
     * @return array{filename: string, path: string, checksum: string}|null
     */
    function itm_database_migrations_resolve_bootstrap_file($filename)
    {
        $filename = basename((string)$filename);
        if ($filename === '' || !preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $filename)) {
            return null;
        }
        if (!in_array($filename, itm_database_migrations_bootstrap_filenames(), true)) {
            return null;
        }

        $path = itm_database_migrations_root_path() . '/' . $filename;
        if (!is_file($path)) {
            return null;
        }

        $checksum = itm_database_migrations_file_checksum($path);
        if ($checksum === '') {
            return null;
        }

        return [
            'filename' => $filename,
            'path' => $path,
            'checksum' => $checksum,
        ];
    }
}

if (!function_exists('itm_database_migrations_resolve_file_row')) {
    /**
     * Resolve any migration SQL file on disk (bootstrap + runner-scoped) for read-only use.
     *
     * @return array{filename: string, path: string, checksum: string}|null
     */
    function itm_database_migrations_resolve_file_row($filename)
    {
        $bootstrap = itm_database_migrations_resolve_bootstrap_file($filename);
        if ($bootstrap !== null) {
            return $bootstrap;
        }

        return itm_database_migrations_resolve_discovered_file($filename);
    }
}

if (!function_exists('itm_database_migrations_record_bootstrap_audit_rows')) {
    /**
     * Record bootstrap migration files in schema_migrations when the history table exists.
     *
     * Why: schema_migrations.sql is excluded from the runner loop but operators expect it in audit history.
     */
    function itm_database_migrations_record_bootstrap_audit_rows($conn)
    {
        if (!($conn instanceof mysqli) || !itm_database_migrations_table_exists($conn)) {
            return;
        }

        $appliedMap = itm_database_migrations_fetch_applied_map($conn);
        foreach (itm_database_migrations_bootstrap_filenames() as $filename) {
            if (isset($appliedMap[$filename])) {
                continue;
            }

            $fileRow = itm_database_migrations_resolve_bootstrap_file($filename);
            if ($fileRow === null) {
                continue;
            }

            itm_database_migrations_record_applied(
                $conn,
                (string)$fileRow['filename'],
                (string)$fileRow['checksum']
            );
        }
    }
}

if (!function_exists('itm_database_migrations_create_table_sql')) {
    function itm_database_migrations_create_table_sql()
    {
        $bootstrapPath = itm_database_migrations_root_path() . '/schema_migrations.sql';
        if (is_readable($bootstrapPath)) {
            $sql = file_get_contents($bootstrapPath);
            if ($sql !== false && trim($sql) !== '') {
                if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
                    $sql = substr($sql, 3);
                }

                return trim($sql);
            }
        }

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
            itm_database_migrations_record_bootstrap_audit_rows($conn);

            return true;
        }

        $bootstrapPath = itm_database_migrations_root_path() . '/schema_migrations.sql';
        if (is_readable($bootstrapPath)) {
            [$executed] = itm_database_migrations_execute_sql_file($conn, $bootstrapPath);
            if ($executed && itm_database_migrations_table_exists($conn)) {
                itm_database_migrations_record_bootstrap_audit_rows($conn);

                return true;
            }

            return false;
        }

        if (mysqli_query($conn, itm_database_migrations_create_table_sql()) === true) {
            itm_database_migrations_record_bootstrap_audit_rows($conn);

            return true;
        }

        return false;
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

if (!function_exists('itm_database_migrations_resolve_discovered_file')) {
    /**
     * Resolve a migration filename to a discovered on-disk row (runner scope only).
     *
     * @return array{filename: string, path: string, checksum: string}|null
     */
    function itm_database_migrations_resolve_discovered_file($filename)
    {
        $filename = basename((string)$filename);
        if ($filename === '' || !preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $filename)) {
            return null;
        }
        if (in_array($filename, itm_database_migrations_bootstrap_filenames(), true)) {
            return null;
        }
        foreach (itm_database_migrations_discover_files() as $row) {
            if (($row['filename'] ?? '') === $filename) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('itm_database_migrations_delete_discovered_file')) {
    /**
     * Delete a runner-scoped migration file from disk and drop any schema_migrations row.
     *
     * @return array{0: bool, 1: string}
     */
    function itm_database_migrations_delete_discovered_file($conn, $filename)
    {
        $fileRow = itm_database_migrations_resolve_discovered_file($filename);
        if ($fileRow === null) {
            return [false, 'Migration file not found or not allowed.'];
        }

        $path = (string)($fileRow['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return [false, 'Migration file is missing on disk.'];
        }

        if (!@unlink($path)) {
            return [false, 'Failed to delete migration file from filesystem.'];
        }

        if ($conn instanceof mysqli) {
            $stmt = mysqli_prepare($conn, 'DELETE FROM schema_migrations WHERE filename = ? LIMIT 1');
            if ($stmt) {
                $basename = (string)$fileRow['filename'];
                mysqli_stmt_bind_param($stmt, 's', $basename);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        return [true, 'Deleted ' . (string)$fileRow['filename'] . ' from filesystem.'];
    }
}

if (!function_exists('itm_database_migrations_delete_audit_row_by_id')) {
    /**
     * Remove one schema_migrations audit history row (does not change live schema).
     *
     * @return array{0: bool, 1: string}
     */
    function itm_database_migrations_delete_audit_row_by_id($conn, $id)
    {
        $rowId = (int)$id;
        if (!$conn instanceof mysqli || $rowId <= 0) {
            return [false, 'Invalid migration history row.'];
        }

        itm_database_migrations_ensure_table($conn);

        $stmt = mysqli_prepare($conn, 'SELECT filename FROM schema_migrations WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return [false, 'Could not load migration history row.'];
        }
        mysqli_stmt_bind_param($stmt, 'i', $rowId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            return [false, 'Migration history row not found.'];
        }

        $filename = (string)($row['filename'] ?? '');
        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM schema_migrations WHERE id = ? LIMIT 1');
        if (!$deleteStmt) {
            return [false, 'Could not delete migration history row.'];
        }
        mysqli_stmt_bind_param($deleteStmt, 'i', $rowId);
        $deleted = mysqli_stmt_execute($deleteStmt);
        $affected = mysqli_stmt_affected_rows($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        if (!$deleted || $affected < 1) {
            return [false, 'Migration history row was not deleted.'];
        }

        return [true, 'Removed audit history for ' . $filename . '. Live schema is unchanged — use migrate.php to apply or remove migration files.'];
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

            // Why: Live DB probe is authoritative — schema_migrations history is audit only.
            [$schemaSatisfied, $probeDetail] = itm_database_migrations_schema_satisfied($conn, $filename);
            $checksumDrift = $recorded && $applied['checksum'] !== $checksum;

            if ($checksumDrift) {
                $state = 'drift';
                $label = 'Checksum drift';
                $detail = 'File changed after apply (applied ' . $applied['checksum'] . ' vs current ' . $checksum . ').';
                if ($probeDetail !== '') {
                    $detail .= ' Live probe: ' . $probeDetail;
                }
                $driftCount++;
            } elseif (!$schemaSatisfied) {
                $state = 'pending';
                $label = 'Pending';
                $detail = $probeDetail !== ''
                    ? 'Live schema does not match — ' . $probeDetail
                    : 'Live schema does not match migration file.';
                if ($recorded) {
                    $detail .= ' Recorded at '
                        . ($applied['applied_at'] !== '' ? $applied['applied_at'] : 'unknown time')
                        . ' but live DB no longer matches.';
                }
                $pendingCount++;
            } elseif ($recorded) {
                $state = 'applied';
                $label = 'Applied';
                $detail = 'Live schema matches'
                    . ($probeDetail !== '' ? ' (' . $probeDetail . ')' : '')
                    . '; recorded at '
                    . ($applied['applied_at'] !== '' ? $applied['applied_at'] : 'unknown time')
                    . '.';
            } else {
                $state = 'applied';
                $label = 'Applied';
                $detail = 'Live schema matches'
                    . ($probeDetail !== '' ? ' (' . $probeDetail . ')' : '')
                    . '; not recorded in schema_migrations.';
            }

            $rows[] = [
                'filename' => $filename,
                'checksum' => $checksum,
                'state' => $state,
                'label' => $label,
                'detail' => $detail,
                'recorded' => $recorded,
                'schema_satisfied' => $schemaSatisfied,
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

if (!function_exists('itm_database_migrations_execute_sql_text')) {
    /**
     * Execute multi-statement SQL with DELIMITER blocks (e.g. db/03_triggers.sql).
     *
     * @return array{0: bool, 1: string}
     */
    function itm_database_migrations_execute_sql_text($conn, $sql)
    {
        if (!($conn instanceof mysqli)) {
            return [false, 'No database connection.'];
        }

        $sql = (string)$sql;
        if (trim($sql) === '') {
            return [false, 'SQL text is empty.'];
        }

        $lines = preg_split('/\R/', $sql);
        if ($lines === false) {
            return [false, 'Could not parse SQL text.'];
        }

        $statement = '';
        $delimiter = ';';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $delimiterMatch) === 1) {
                $delimiter = (string)$delimiterMatch[1];
                continue;
            }
            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0 || strpos($trimmed, '*/') === 0) {
                continue;
            }

            $statement .= $line . "\n";
            $lineWithoutTrailingSpaces = rtrim($line);
            $isStatementComplete = false;
            if ($delimiter === ';') {
                $isStatementComplete = substr($lineWithoutTrailingSpaces, -1) === ';';
            } elseif (strlen($delimiter) > 0) {
                $isStatementComplete = substr($lineWithoutTrailingSpaces, -strlen($delimiter)) === $delimiter;
            }

            if ($isStatementComplete) {
                $statementToRun = rtrim($statement);
                if ($delimiter !== ';' && strlen($delimiter) > 0 && substr($statementToRun, -strlen($delimiter)) === $delimiter) {
                    $statementToRun = substr($statementToRun, 0, -strlen($delimiter));
                }
                $statementToRun = trim($statementToRun);
                if ($statementToRun !== '' && !mysqli_query($conn, $statementToRun)) {
                    return [false, mysqli_error($conn)];
                }
                $statement = '';
            }
        }

        $tail = trim($statement);
        if ($tail !== '' && !mysqli_query($conn, $tail)) {
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

        return itm_database_migrations_execute_sql_text($conn, $sql);
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

if (!function_exists('itm_database_migrations_find_status_row')) {
    /**
     * @return array<string, mixed>|null
     */
    function itm_database_migrations_find_status_row($conn, $filename)
    {
        $filename = basename((string)$filename);
        if ($filename === '' || !($conn instanceof mysqli)) {
            return null;
        }

        $status = itm_database_migrations_build_status($conn);
        foreach ($status['migrations'] as $row) {
            if ((string)($row['filename'] ?? '') === $filename) {
                return $row;
            }
        }

        if (in_array($filename, itm_database_migrations_bootstrap_filenames(), true)) {
            $fileRow = itm_database_migrations_resolve_bootstrap_file($filename);
            if ($fileRow === null) {
                return null;
            }
            $appliedMap = itm_database_migrations_fetch_applied_map($conn);
            $audit = $appliedMap[$filename] ?? null;

            return [
                'filename' => $filename,
                'checksum' => (string)($fileRow['checksum'] ?? ''),
                'state' => 'applied',
                'label' => 'Applied',
                'detail' => 'Bootstrap table definition — not in runner apply loop.',
                'recorded' => $audit !== null,
                'schema_satisfied' => true,
                'applied_at' => $audit['applied_at'] ?? null,
            ];
        }

        return null;
    }
}

if (!function_exists('itm_database_migrations_record_satisfied_file')) {
    /**
     * Insert an audit row when the live schema probe already passes (no SQL re-run).
     *
     * @return array{0: bool, 1: string}
     */
    function itm_database_migrations_record_satisfied_file($conn, $filename)
    {
        if (!($conn instanceof mysqli)) {
            return [false, 'No database connection.'];
        }

        $filename = basename((string)$filename);
        if ($filename === '' || !preg_match('/^[A-Za-z0-9_.-]+\.sql$/', $filename)) {
            return [false, 'Invalid migration filename.'];
        }

        if (!itm_database_migrations_ensure_table($conn)) {
            return [false, 'Could not ensure schema_migrations table.'];
        }

        $appliedMap = itm_database_migrations_fetch_applied_map($conn);
        if (isset($appliedMap[$filename])) {
            return [true, 'Audit row already recorded for ' . $filename . '.'];
        }

        $statusRow = itm_database_migrations_find_status_row($conn, $filename);
        if ($statusRow === null) {
            return [false, 'Migration file not found on disk.'];
        }

        if ((string)($statusRow['state'] ?? '') === 'drift') {
            return [false, 'Checksum drift — resolve before recording.'];
        }

        if ((string)($statusRow['state'] ?? '') !== 'applied') {
            return [false, 'Live schema probe has not passed — apply the migration first.'];
        }

        $checksum = (string)($statusRow['checksum'] ?? '');
        if ($checksum === '') {
            return [false, 'Could not resolve file checksum.'];
        }

        if (!itm_database_migrations_record_applied($conn, $filename, $checksum)) {
            return [false, 'Could not insert audit row: ' . mysqli_error($conn)];
        }

        return [true, 'Recorded audit history for ' . $filename . '.'];
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
