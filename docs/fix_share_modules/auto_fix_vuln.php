<?php
// PHP script to dynamically fix the seed admin role assignment in the database.
require_once dirname(__DIR__, 2) . '/config/config.php';

$sql = "UPDATE `employees` e
INNER JOIN `employee_roles` er ON er.`company_id` = e.`company_id` AND er.`name` = 'Admin'
SET e.`role_id` = er.`id`
WHERE e.`work_email` LIKE 'admin@techcorp.example%.com'";

if (mysqli_query($conn, $sql)) {
    echo "Successfully updated seed admin role assignments.\n";
} else {
    echo "Error updating admin role assignments: " . mysqli_error($conn) . "\n";
}
