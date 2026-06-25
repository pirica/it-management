<?php
require_once __DIR__ . '/../config/config.php';

echo "<h3>Generate FK employee_id (ON DELETE RESTRICT)</h3>";

$res = mysqli_query($conn, "SHOW TABLES");
while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];

    echo "<hr><b>TABLE: <u>$table</u></b><br>";

    // Skip global tables
    $skip = ['companies','audit_logs','modules_registry','floor_plan_item_tags'];
    if (in_array($table, $skip)) {
        echo "<font color=gray>[SKIPPED] Global table</font><br>";
        continue;
    }

    // Check if employee_id exists
    $col = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
    if (mysqli_num_rows($col) === 0) {
        echo "<font color=gray>[SKIPPED] No employee_id column</font><br>";
        continue;
    }

    echo "<b>employee_id detected</b><br>";

    // Check FK
    $sql = "
        SELECT rc.CONSTRAINT_NAME
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        JOIN information_schema.KEY_COLUMN_USAGE ku
            ON rc.CONSTRAINT_NAME = ku.CONSTRAINT_NAME
        WHERE ku.TABLE_NAME = '$table'
          AND ku.COLUMN_NAME = 'employee_id'
          AND rc.CONSTRAINT_SCHEMA = DATABASE()
    ";
    $fk = mysqli_query($conn, $sql);

    if (mysqli_num_rows($fk) > 0) {
        echo "<font color=green>[OK] FK already exists</font><br>";
        continue;
    }

    echo "<font color=red>[ERROR] Missing FK!</font><br>";

    echo "Sugested: <pre>
ALTER TABLE `$table`
ADD CONSTRAINT `{$table}_ibfk_employee`
FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`)
ON DELETE RESTRICT;
</pre>";
}
