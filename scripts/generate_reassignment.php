<?php
require_once __DIR__ . '/../config/config.php';

//generate_reassignment.php

echo "<h3>Generate reassignment SQL before deleting an employee</h3>";

$res = mysqli_query($conn, "SHOW TABLES");

while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];

    // Check if table has employee_id
    $col = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
    if (mysqli_num_rows($col) === 0) continue;

    echo "<b>$table</b><br>";

    echo "<pre>
-- Reassign before deleting employee
UPDATE `$table`
SET employee_id = :new_employee_id
WHERE employee_id = :old_employee_id;
</pre>";
}
