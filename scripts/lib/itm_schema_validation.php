<?php
/**
 * Collect database schema validation errors and warnings (no CLI output).
 *
 * @return array{errors:array<int,string>,warnings:array<int,string>}
 */
function itm_schema_collect_validation_issues(mysqli $conn): array
{
    $errors = [];
    $warnings = [];

    $tablesRes = mysqli_query($conn, 'SHOW TABLES');
    if (!$tablesRes) {
        return [
            'errors' => ['Could not list tables: ' . mysqli_error($conn)],
            'warnings' => [],
        ];
    }

    $tables = [];
    while ($row = mysqli_fetch_row($tablesRes)) {
        $tables[] = (string)$row[0];
    }

    foreach ($tables as $table) {
        $tableEsc = mysqli_real_escape_string($conn, $table);
        $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE 'employee_id'");
        if (!$colRes || mysqli_num_rows($colRes) === 0) {
            continue;
        }

        $fkRes = mysqli_query($conn, "
            SELECT CONSTRAINT_NAME, DELETE_RULE
            FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE TABLE_NAME = '{$tableEsc}'
              AND REFERENCED_TABLE_NAME = 'employees'
              AND CONSTRAINT_SCHEMA = DATABASE()
        ");

        if (!$fkRes || mysqli_num_rows($fkRes) === 0) {
            $errors[] = "Table {$table} has employee_id but NO FOREIGN KEY!";
            continue;
        }

        $fk = mysqli_fetch_assoc($fkRes);
        $rule = (string)($fk['DELETE_RULE'] ?? '');
        if ($rule !== 'RESTRICT' && $rule !== 'NO ACTION' && $rule !== 'SET NULL') {
            $warnings[] = "Table {$table} uses DELETE {$rule} (not recommended)";
        }
    }

    foreach ($tables as $table) {
        $tableEsc = mysqli_real_escape_string($conn, $table);
        $idxRes = mysqli_query($conn, "SHOW INDEX FROM `{$tableEsc}`");
        if (!$idxRes) {
            continue;
        }

        $indexes = [];
        while ($idx = mysqli_fetch_assoc($idxRes)) {
            $key = (string)$idx['Key_name'];
            $col = (string)$idx['Column_name'];
            if (!isset($indexes[$key])) {
                $indexes[$key] = [];
            }
            $indexes[$key][] = $col;
        }

        foreach ($indexes as $k => $cols) {
            sort($indexes[$k]);
        }

        $checked = [];
        foreach ($indexes as $nameA => $colsA) {
            foreach ($indexes as $nameB => $colsB) {
                if ($nameA === $nameB) {
                    continue;
                }

                $pair = $nameA . '|' . $nameB;
                $rev = $nameB . '|' . $nameA;
                if (isset($checked[$pair]) || isset($checked[$rev])) {
                    continue;
                }
                $checked[$pair] = true;

                if ($colsA === $colsB) {
                    $warnings[] = "Duplicate index in {$table}: {$nameA} and {$nameB}";
                }
            }
        }
    }

    foreach ($tables as $table) {
        $tableEsc = mysqli_real_escape_string($conn, $table);
        $idxRes = mysqli_query($conn, "SHOW INDEX FROM `{$tableEsc}` WHERE Key_name LIKE '%employee%'");
        if (!$idxRes) {
            continue;
        }

        while ($idx = mysqli_fetch_assoc($idxRes)) {
            $col = (string)$idx['Column_name'];
            if ($col !== 'employee_id') {
                continue;
            }

            $fkRes = mysqli_query($conn, "
                SELECT 1
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_NAME = '{$tableEsc}'
                  AND COLUMN_NAME = 'employee_id'
                  AND REFERENCED_TABLE_NAME = 'employees'
                  AND CONSTRAINT_SCHEMA = DATABASE()
            ");

            if (!$fkRes || mysqli_num_rows($fkRes) === 0) {
                $warnings[] = "Orphaned index in {$table}: index on employee_id but no FK";
            }
        }
    }

    return [
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}
