<?php
require_once __DIR__ . '/../config/config.php';

// ID do clone que queremos apagar
$new_employee_id = 7;

echo "<h2>Reversing clone — deleting employee $new_employee_id</h2>";

$excludeTables = ['audit_logs', 'attempts']; // nunca apagar

$errors = [];
$deletedTables = [];

$res = mysqli_query($conn, "SHOW TABLES");

// STEP 1 — DELETE RELATED DATA
echo "<h3>Deleting related data...</h3>";

while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];

    if (in_array($table, $excludeTables)) {
        echo "<b>$table</b> <span style='color:gray'>[SKIPPED]</span><br>";
        continue;
    }

    // Check if table has employee_id
    $col = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
    if (mysqli_num_rows($col) === 0) continue;

    $sql = "DELETE FROM `$table` WHERE employee_id = $new_employee_id";

    if (mysqli_query($conn, $sql)) {
        $affected = mysqli_affected_rows($conn);
        echo "<b>$table</b>: <span style='color:green'>DELETED ($affected rows)</span><br>";
        $deletedTables[] = $table;
    } else {
        echo "<b>$table</b>: <span style='color:red'>FAILED</span> " . mysqli_error($conn) . "<br>";
        $errors[] = $table;
    }
}

// STEP 2 — DELETE EMPLOYEE ITSELF
echo "<h3>Deleting employee record...</h3>";

$sql = "DELETE FROM employees WHERE id = $new_employee_id";

if (mysqli_query($conn, $sql)) {
    echo "<b>employees</b>: <span style='color:green'>DELETED</span><br>";
} else {
    echo "<b>employees</b>: <span style='color:red'>FAILED</span> " . mysqli_error($conn) . "<br>";
    $errors[] = "employees";
}

// FINAL RESULT
if (empty($errors)) {
    echo "<h2 style='color:blue'>Clone reversed successfully — all data removed.</h2>";
} else {
    echo "<h2 style='color:red'>Some tables failed to delete:</h2>";
    foreach ($errors as $t) {
        echo "<b>$t</b><br>";
    }
}
