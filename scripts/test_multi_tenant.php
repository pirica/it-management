<?php
require_once __DIR__ . '/../config/config.php';

echo "<h3>Validating company_id foreign keys</h3>";

$res = mysqli_query($conn, "SHOW TABLES");
$tables = [];
while ($row = mysqli_fetch_row($res)) {
    $tables[] = $row[0];
}

$globalTables = ['companies', 'audit_logs', 'attempts','floor_plan_item_tags','modules_registry','password_entries','password_folders'];

// Counters + lists
$skipped = 0;
$ok = 0;
$error = 0;
$empty = 0;

$skippedList = [];
$okList = [];
$errorList = [];
$emptyList = [];

foreach ($tables as $table) {

    if (in_array($table, $globalTables)) {
        $skipped++;
        $skippedList[] = $table;
        continue;
    }

    // Check if table has company_id column
    $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'company_id'");
    $hasCompanyId = mysqli_num_rows($colRes) > 0;

    if (!$hasCompanyId) {
        echo "<br><b>$table</b>: <font color=gray>[SKIPPED] No company_id column</font><br>";
        $skipped++;
        $skippedList[] = $table;
        continue;
    }

    echo "<br><b>Validating FK for $table</b><br>";

    $sql = "
        SELECT 
            rc.CONSTRAINT_NAME, 
            ku.REFERENCED_TABLE_NAME, 
            ku.REFERENCED_COLUMN_NAME,
            rc.UPDATE_RULE,
            rc.DELETE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        JOIN information_schema.KEY_COLUMN_USAGE ku
            ON rc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME
            AND rc.CONSTRAINT_SCHEMA = ku.TABLE_SCHEMA
        WHERE ku.TABLE_NAME = '$table'
          AND ku.COLUMN_NAME = 'company_id'
          AND rc.CONSTRAINT_SCHEMA = DATABASE()
    ";

    $fkRes = mysqli_query($conn, $sql);

    // Query failed
    if (!$fkRes) {
        echo "<br><font color=red>[ERROR] Query failed: " . mysqli_error($conn) . "</font><br>";
        echo "<font color=red>SQL: $sql</font><br>";

        $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
        if ($colRes) {
            echo "<br><b>Columns in $table:</b><br>";
            while ($col = mysqli_fetch_assoc($colRes)) {
                echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
            }
        }

        echo "<hr>";
        $error++;
        $errorList[] = $table;
        continue;
    }

    // No FK found
    if (mysqli_num_rows($fkRes) === 0) {
        echo "<font color=red>[ERROR] company_id exists but has NO FOREIGN KEY!</font><br>";

        $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
        if ($colRes) {
            echo "<br><b>Columns in $table:</b><br>";
            while ($col = mysqli_fetch_assoc($colRes)) {
                echo "- " . $col['Field'] . " (" . $col['Type'] . ")<br>";
            }
        }

        echo "<hr>";
        $error++;
        $errorList[] = $table;
        continue;
    }

    // FK found
    $fks = [];
    while ($fk = mysqli_fetch_assoc($fkRes)) {
        $fks[] = $fk;
    }

    foreach ($fks as $fk) {
        echo "<font color=green>[OK]</font> FK: {$fk['CONSTRAINT_NAME']} → ";
        echo "{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}<br>";
        echo "Update rule: {$fk['UPDATE_RULE']} | Delete rule: {$fk['DELETE_RULE']}<br>";
    }

    // Check if table has data
    $dataRes = mysqli_query($conn, "SELECT company_id FROM `$table` LIMIT 1");
    if ($dataRes && mysqli_num_rows($dataRes) === 0) {
        echo "<br><font color=orange>[EMPTY] No data to verify scoping</font>\n";
        $empty++;
        $emptyList[] = $table;
    } else {
        $ok++;
        $okList[] = $table;
    }

    echo "<hr>";
}

// SUMMARY
echo "<h3>SUMMARY</h3>";
echo "SKIPPED: $skipped<br>";
echo "OK: $ok<br>";
echo "ERROR: $error<br>";
echo "EMPTY: $empty<br>";

echo "<hr><h3>DETAILS</h3>";

echo "<b>SKIPPED ($skipped):</b><br>";
echo implode(', ', $skippedList) . "<br><br>";

echo "<b>ERROR ($error):</b><br>";
echo $error ? implode(', ', $errorList) : "None";
echo "<br><br>";

echo "<b>EMPTY ($empty):</b><br>";
echo $empty ? implode(', ', $emptyList) : "None";
echo "<br><br>";

echo "<b>OK ($ok):</b><br>";
echo implode(', ', $okList) . "<br><br>";
