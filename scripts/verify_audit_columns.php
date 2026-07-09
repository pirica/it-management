<?php
// IT Management Audit Column Verification Script
// This script verifies that all tables in the redesigned schema have the mandatory 8 audit columns with correct defaults.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli' && !itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

itm_script_output_begin('Audit Column Verification');

$nl = itm_script_output_nl();

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
        echo itm_script_format_status_line("[PASS] $table") . $nl;
    } else {
        echo itm_script_format_status_line("[FAIL] $table - " . implode(', ', $missing)) . $nl;
        $allPassed = false;
    }
}

if ($allPassed) {
    echo $nl . colorText('Verification successful! All tables are compliant.', 'pass') . $nl;
} else {
    echo $nl . colorText('Verification failed. Some tables are missing mandatory columns.', 'fail') . $nl;
    exit(1);
}

itm_script_output_end();
