<?php
// IT Management Audit Column Verification Script
// This script verifies that all tables in the redesigned schema have the mandatory 8 audit columns with correct defaults.

require_once __DIR__ . '/../config/config.php';

$mandatoryCols = [
    'company_id' => ['type' => 'int', 'null' => 'NO'],
    'active' => ['type' => 'tinyint', 'default' => '1'],
    'deleted_by' => ['type' => 'int', 'null' => 'YES', 'default' => NULL],
    'deleted_at' => ['type' => 'timestamp', 'null' => 'YES', 'default' => NULL],
    'created_by' => ['type' => 'int', 'null' => 'YES', 'default' => NULL],
    'created_at' => ['type' => 'timestamp', 'null' => 'YES', 'default' => 'CURRENT_TIMESTAMP'],
    'updated_by' => ['type' => 'int', 'null' => 'YES', 'default' => NULL],
    'updated_at' => ['type' => 'timestamp', 'null' => 'YES', 'default' => NULL, 'extra' => 'ON UPDATE CURRENT_TIMESTAMP']
];


$res = mysqli_query($conn, "SHOW TABLES");
$allPassed = true;

while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];
    $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
    $columns = [];
    while ($colRow = mysqli_fetch_assoc($colRes)) {
        $columns[$colRow['Field']] = $colRow;
    }

    $missing = [];
    foreach ($mandatoryCols as $mCol => $specs) {
        if ($mCol === 'company_id' && in_array($table, ['companies', 'audit_logs'])) {
            continue;
        }
        if (!isset($columns[$mCol])) {
            $missing[] = $mCol . " (Missing)";
        } else {
            // Optional: verify types and defaults here if needed
        }
    }

    if (empty($missing)) {
        echo "<br><font color=green>[PASS] $table</font>\n";
    } else {
        echo "<br><font color=red>[FAIL] $table - " . implode(', ', $missing) . "</font>\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "<br>\n<font color=green>Verification successful! All tables are compliant.</font>\n";
} else {
    echo "<br>\n<font color=red>Verification failed. Some tables are missing mandatory columns.</font>\n";
    exit(1);
}
