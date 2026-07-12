<?php
/**
 * Clones an employee and transfers/copies their related data to the new record.
 *
 * Why: Useful for simulating user data or migrating responsibilities
 * while maintaining historical data in a new record.
 *
 * CLI: php scripts/transfer_data_from_employee.php --id=N
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Transfer Data from Employee</title></head><body style="font-family:Segoe UI,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> Data mutation tool must be run from the terminal.</p><pre>php scripts/transfer_data_from_employee.php --id=N</pre></body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Employee data transfer (COPY MODE)');

// Parse CLI ID
$options = getopt('', ['id:']);
$old_employee_id = isset($options['id']) ? (int)$options['id'] : 0;

if ($old_employee_id <= 0) {
    echo colorText('[FAIL] Please specify an original employee ID with --id=N', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

/**
 * Generate 3 random characters (A-Z + 0-9)
 */
function random3() {
    return substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 3);
}

/**
 * Generate unique username with -newXYZ
 */
function generateUniqueUsername($conn, $company_id, $baseUsername) {
    $suffix = random3();
    $newUsername = $baseUsername . "-new" . $suffix;

    $safe = mysqli_real_escape_string($conn, $newUsername);

    $check = mysqli_query($conn, "
        SELECT id FROM employees 
        WHERE company_id = $company_id 
          AND username = '$safe'
        LIMIT 1
    ");

    if (mysqli_num_rows($check) === 0) {
        return $newUsername;
    }

    return generateUniqueUsername($conn, $company_id, $baseUsername);
}

/**
 * Generate unique email with +newXYZ
 */
function generateUniqueEmail($conn, $company_id, $email) {
    if (empty($email)) return NULL;

    $suffix = random3();
    list($local, $domain) = explode("@", $email);

    $newEmail = $local . "+new" . $suffix . "@" . $domain;

    $safe = mysqli_real_escape_string($conn, $newEmail);

    $check = mysqli_query($conn, "
        SELECT id FROM employees 
        WHERE company_id = $company_id 
          AND work_email = '$safe'
        LIMIT 1
    ");

    if (mysqli_num_rows($check) === 0) {
        return $newEmail;
    }

    return generateUniqueEmail($conn, $company_id, $email);
}

/**
 * Create a new employee by cloning all columns except the PK.
 * Automatically fixes UNIQUE fields.
 */
function createNewEmployeeClone($conn, $old_employee_id) {

    // Fetch original employee
    $res = mysqli_query($conn, "SELECT * FROM employees WHERE id = $old_employee_id");
    $old = mysqli_fetch_assoc($res);

    if (!$old) {
        die("Old employee not found\n");
    }

    // Get all columns from employees table
    $colsRes = mysqli_query($conn, "SHOW COLUMNS FROM employees");
    $insertCols = [];
    $selectCols = [];

    while ($c = mysqli_fetch_assoc($colsRes)) {
        $col = $c['Field'];

        if ($col === 'id') continue; // skip PK

        $insertCols[] = "`$col`";

        // UNIQUE username
        if ($col === 'username' && !empty($old[$col])) {
            $uniqueUsername = generateUniqueUsername($conn, $old['company_id'], (string)$old[$col]);
            $selectCols[] = "'" . mysqli_real_escape_string($conn, $uniqueUsername) . "' AS `$col`";
            continue;
        }

        // UNIQUE emails
        if ($col === 'work_email' && !empty($old[$col])) {
            $uniqueEmail = generateUniqueEmail($conn, $old['company_id'], (string)$old[$col]);
            $selectCols[] = "'" . mysqli_real_escape_string($conn, (string)$uniqueEmail) . "' AS `$col`";
            continue;
        }

        if ($col === 'personal_email' && !empty($old[$col])) {
            $uniqueEmail = generateUniqueEmail($conn, $old['company_id'], (string)$old[$col]);
            $selectCols[] = "'" . mysqli_real_escape_string($conn, (string)$uniqueEmail) . "' AS `$col`";
            continue;
        }

        // UNIQUE external_id / employee_code
        if (($col === 'external_id' || $col === 'employee_code') && !empty($old[$col])) {
            $newValue = $old[$col] . "-new" . random3();
            $selectCols[] = "'" . mysqli_real_escape_string($conn, (string)$newValue) . "' AS `$col`";
            continue;
        }

        // Avoid duplicate first_name
        if ($col === 'first_name') {
            $value = "Clone of " . $old['first_name'];
            $selectCols[] = "'" . mysqli_real_escape_string($conn, $value) . "' AS `$col`";
            continue;
        }

        // Copy original value
        $value = isset($old[$col]) ? mysqli_real_escape_string($conn, (string)$old[$col]) : NULL;

        if ($value === NULL) {
            $selectCols[] = "NULL AS `$col`";
        } else {
            $selectCols[] = "'$value' AS `$col`";
        }
    }

    $insertList = implode(", ", $insertCols);
    $selectList = implode(", ", $selectCols);

    // Insert new employee
    $sql = "
        INSERT INTO employees ($insertList)
        SELECT $selectList
    ";

    if (!mysqli_query($conn, $sql)) {
        die("Error creating new employee: " . mysqli_error($conn) . "\n");
    }

    return mysqli_insert_id($conn);
}

// Create new employee
$new_employee_id = createNewEmployeeClone($conn, $old_employee_id);

echo colorText("New employee created: ID $new_employee_id", 'pass') . $nl;

$excludeTables = ['audit_logs', 'attempts']; // never copy these

$errors = [];
$copyTables = [];

$res = mysqli_query($conn, "SHOW TABLES");

// STEP 1 — TEST COPY
echo "Testing COPY operations..." . $nl;

while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];

    if (in_array($table, $excludeTables)) {
        continue;
    }

    // Check if table has employee_id
    $col = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE 'employee_id'");
    if (mysqli_num_rows($col) === 0) continue;

    // Build INSERT SELECT dynamically
    $columnsRes = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
    $insertCols = [];
    $selectCols = [];

    while ($c = mysqli_fetch_assoc($columnsRes)) {
        $field = $c['Field'];

        if ($field === 'id') continue; // skip PK

        $insertCols[] = "`$field`";

        if ($field === 'employee_id') {
            $selectCols[] = "$new_employee_id AS employee_id";
        } else {
            $selectCols[] = "`$field`";
        }
    }

    $insertList = implode(", ", $insertCols);
    $selectList = implode(", ", $selectCols);

    $testSql = "
        INSERT INTO `$table` ($insertList)
        SELECT $selectList
        FROM `$table`
        WHERE employee_id = $old_employee_id
        LIMIT 1
    ";

    mysqli_begin_transaction($conn);

    if (!mysqli_query($conn, $testSql)) {
        $errors[] = [
            'table' => $table,
            'error' => mysqli_error($conn)
        ];
    } else {
        $copyTables[] = $table;
    }

    mysqli_rollback($conn);
}

// STOP IF ERRORS
if (!empty($errors)) {
    echo colorText('COPY ABORTED — ERRORS DETECTED', 'fail') . $nl;
    echo "Fix these tables before retrying:" . $nl;

    foreach ($errors as $e) {
        echo " - {$e['table']}: {$e['error']}" . $nl;
    }

    itm_script_output_end();
    exit(1);
}

// STEP 2 — EXECUTE REAL COPY
echo colorText('All tests passed — performing REAL COPY', 'pass') . $nl;

foreach ($copyTables as $table) {

    $columnsRes = mysqli_query($conn, "SHOW COLUMNS FROM `$table`");
    $insertCols = [];
    $selectCols = [];

    while ($c = mysqli_fetch_assoc($columnsRes)) {
        $field = $c['Field'];

        if ($field === 'id') continue;

        $insertCols[] = "`$field`";

        if ($field === 'employee_id') {
            $selectCols[] = "$new_employee_id AS employee_id";
        } else {
            $selectCols[] = "`$field`";
        }
    }

    $insertList = implode(", ", $insertCols);
    $selectList = implode(", ", $selectCols);

    $sql = "
        INSERT INTO `$table` ($insertList)
        SELECT $selectList
        FROM `$table`
        WHERE employee_id = $old_employee_id
    ";

    if (mysqli_query($conn, $sql)) {
        echo " - $table: " . colorText('COPIED', 'pass') . $nl;
    } else {
        echo " - $table: " . colorText('FAILED', 'fail') . " " . mysqli_error($conn) . $nl;
    }
}

echo $nl . colorText('COPY PROCESS COMPLETED', 'pass') . $nl;
itm_script_output_end();
