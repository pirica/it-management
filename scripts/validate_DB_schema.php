<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Validating Database Schema</h2>";

$errors = [];
$warnings = [];
$info = [];

// 1) Get all tables
$tablesRes = mysqli_query($conn, "SHOW TABLES");
$tables = [];

while ($row = mysqli_fetch_row($tablesRes)) {
    $tables[] = $row[0];
}

// 2) Validate employee_id columns
echo "<h3>Checking employee_id columns...</h3>";

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
        $errors[] = "Table <b>$table</b> has employee_id but NO FOREIGN KEY!";
        echo "<span style='color:red'>[ERROR]</span> $table → missing FK<br>";
        continue;
    }

    $fk = mysqli_fetch_assoc($fkRes);
    $rule = $fk['DELETE_RULE'];

    // Validate ON DELETE rule
    if ($rule !== 'RESTRICT' && $rule !== 'NO ACTION' && $rule !== 'SET NULL') {
        $warnings[] = "Table <b>$table</b> uses DELETE $rule (not recommended)";
        echo "<span style='color:orange'>[WARN]</span> $table → DELETE $rule<br>";
    } else {
        echo "<span style='color:green'>[OK]</span> $table → DELETE $rule<br>";
    }
}

// 3) Detect duplicate indexes
echo "<h3>Checking for duplicate indexes...</h3>";

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
                echo "<span style='color:orange'>[WARN]</span> $table → duplicate index $nameA / $nameB<br>";
                $warnings[] = "Duplicate index in <b>$table</b>: $nameA and $nameB";
            }
        }
    }
}




// 4) Detect orphaned indexes (indexes with no FK)
echo "<h3>Checking for orphaned indexes...</h3>";

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
            $warnings[] = "Orphaned index in <b>$table</b>: index on employee_id but no FK";
            echo "<span style='color:orange'>[WARN]</span> $table → orphaned employee_id index<br>";
        }
    }
}

// 5) Final report
echo "<h2>Schema Validation Completed</h2>";

if (empty($errors) && empty($warnings)) {
    echo "<h3 style='color:green'>No issues found. Schema is consistent.</h3>";
} else {
    echo "<h3 style='color:red'>Errors:</h3>";
    foreach ($errors as $e) echo "$e<br>";

    echo "<h3 style='color:orange'>Warnings:</h3>";
    foreach ($warnings as $w) echo "$w<br>";
}


echo "<h1>FILE: " . __FILE__ . "</h1>";
echo "<h1>DB NAME: " . mysqli_fetch_row(mysqli_query($conn, "SELECT DATABASE()"))[0] . "</h1>";
echo "<h1>HOST: " . mysqli_get_host_info($conn) . "</h1>";