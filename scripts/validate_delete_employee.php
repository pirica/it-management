<?php
require_once __DIR__ . '/../config/config.php';

echo "<h3>Validating if employees can be deleted</h3>";

echo "<hr><b>Checking FKs referencing employees...</b><br>";

$sql = "
SELECT TABLE_NAME, CONSTRAINT_NAME, DELETE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE REFERENCED_TABLE_NAME = 'employees'
  AND CONSTRAINT_SCHEMA = DATABASE()
";
$res = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($res)) {
    echo "- {$row['TABLE_NAME']} → {$row['CONSTRAINT_NAME']} (DELETE: {$row['DELETE_RULE']})<br>";
}

echo "<hr><b>Checking triggers containing DELETE FROM employees...</b><br>";

$sql = "
SELECT TRIGGER_NAME, ACTION_STATEMENT
FROM information_schema.TRIGGERS
WHERE ACTION_STATEMENT LIKE '%DELETE FROM employees%'
  AND TRIGGER_SCHEMA = DATABASE()
";
$res = mysqli_query($conn, $sql);

if (mysqli_num_rows($res) === 0) {
    echo "<font color=green>No triggers delete employees</font><br>";
} else {
    while ($row = mysqli_fetch_assoc($res)) {
        echo "<font color=red>Trigger {$row['TRIGGER_NAME']} deletes employees!</font><br>";
    }
}
