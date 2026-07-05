<?php
/**
 * Static validation of database schema consistency.
 *
 * Why: Checks for missing FKs on employee_id columns, duplicate indexes,
 * and orphaned indexes to ensure long-term database health.
 *
 * Browser: open scripts/validate_DB_schema.php (login required).
 * CLI: php scripts/validate_DB_schema.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
} else {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Validating Database Schema');

$errors = [];
$warnings = [];

// 1) Get all tables
$tablesRes = mysqli_query($conn, "SHOW TABLES");
$tables = [];

while ($row = mysqli_fetch_row($tablesRes)) {
    $tables[] = $row[0];
}

// 2) Validate employee_id columns
echo "### Checking employee_id columns..." . $nl;

foreach ($tables as $table) {
    $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
    if (mysqli_num_rows($colRes) === 0) continue;

    // Check FK existence
    $fkRes = mysqli_query($conn, "
        SELECT CONSTRAINT_NAME, DELETE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE TABLE_NAME = '$table'
          AND REFERENCED_TABLE_NAME = 'employees'
          AND CONSTRAINT_SCHEMA = DATABASE()
    ");

    if (mysqli_num_rows($fkRes) === 0) {
        $errors[] = "Table $table has employee_id but NO FOREIGN KEY!";
        echo colorText("[ERROR]", 'fail') . " $table → missing FK" . $nl;
        continue;
    }

    $fk = mysqli_fetch_assoc($fkRes);
    $rule = $fk['DELETE_RULE'];

    // Validate ON DELETE rule
    if ($rule !== 'RESTRICT' && $rule !== 'NO ACTION' && $rule !== 'SET NULL') {
        $warnings[] = "Table $table uses DELETE $rule (not recommended)";
        echo colorText("[WARN]", 'warn') . " $table → DELETE $rule" . $nl;
    } else {
        echo colorText("[OK]", 'pass') . " $table → DELETE $rule" . $nl;
    }
}

// 3) Detect duplicate indexes
echo $nl . "### Checking for duplicate indexes..." . $nl;

foreach ($tables as $table) {
    $idxRes = mysqli_query($conn, "SHOW INDEX FROM `$table`");
    $indexes = [];

    while ($idx = mysqli_fetch_assoc($idxRes)) {
        $key = $idx['Key_name'];
        $col = $idx['Column_name'];

        if (!isset($indexes[$key])) {
            $indexes[$key] = [];
        }
        $indexes[$key][] = $col;
    }

    // Normalize column order
    foreach ($indexes as $k => $cols) {
        sort($indexes[$k]);
    }

    // Compare index definitions without duplicates
    $checked = [];

    foreach ($indexes as $nameA => $colsA) {
        foreach ($indexes as $nameB => $colsB) {
            if ($nameA === $nameB) continue;

            // Prevent mirrored comparisons
            $pair = $nameA . '|' . $nameB;
            $rev  = $nameB . '|' . $nameA;

            if (isset($checked[$pair]) || isset($checked[$rev])) continue;
            $checked[$pair] = true;

            // Only warn if BOTH indexes exist in DB
            if ($colsA === $colsB) {
                echo colorText("[WARN]", 'warn') . " $table → duplicate index $nameA / $nameB" . $nl;
                $warnings[] = "Duplicate index in $table: $nameA and $nameB";
            }
        }
    }
}

// 4) Detect orphaned indexes (indexes with no FK)
echo $nl . "### Checking for orphaned indexes..." . $nl;

foreach ($tables as $table) {
    $idxRes = mysqli_query($conn, "SHOW INDEX FROM `$table` WHERE Key_name LIKE '%employee%'");

    while ($idx = mysqli_fetch_assoc($idxRes)) {
        $col = $idx['Column_name'];

        if ($col !== 'employee_id') continue;

        // Check if FK exists
        $fkRes = mysqli_query($conn, "
            SELECT 1
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = '$table'
              AND COLUMN_NAME = 'employee_id'
              AND REFERENCED_TABLE_NAME = 'employees'
              AND CONSTRAINT_SCHEMA = DATABASE()
        ");

        if (mysqli_num_rows($fkRes) === 0) {
            $warnings[] = "Orphaned index in $table: index on employee_id but no FK";
            echo colorText("[WARN]", 'warn') . " $table → orphaned employee_id index" . $nl;
        }
    }
}

// 5) Final report
echo $nl . "## Schema Validation Completed" . $nl;

if (empty($errors) && empty($warnings)) {
    echo colorText("No issues found. Schema is consistent.", 'pass') . $nl;
} else {
    if (!empty($errors)) {
        echo colorText("Errors:", 'fail') . $nl;
        foreach ($errors as $e) echo " - $e" . $nl;
    }

    if (!empty($warnings)) {
        echo colorText("Warnings:", 'warn') . $nl;
        foreach ($warnings as $w) echo " - $w" . $nl;
    }
}

itm_script_output_end();
