<?php
require_once __DIR__ . '/../config/config.php';

$old_employee_id = 1; // employee original

echo "<h2>Starting employee data transfer (COPY MODE)</h2>";

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
        die("Old employee not found");
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
            $uniqueUsername = generateUniqueUsername($conn, $old['company_id'], $old[$col]);
            $selectCols[] = "'" . mysqli_real_escape_string($conn, $uniqueUsername) . "' AS `$col`";
            continue;
        }

        // UNIQUE emails
        if ($col === 'work_email' && !empty($old[$col])) {
            $uniqueEmail = generateUniqueEmail($conn, $old['company_id'], $old[$col]);
            $selectCols[] = "'" . mysqli_real_escape_string($conn, $uniqueEmail) . "' AS `$col`";
            continue;
        }

        if ($col === 'personal_email' && !empty($old[$col])) {
            $uniqueEmail = generateUniqueEmail($conn, $old['company_id'], $old[$col]);
            $selectCols[] = "'" . mysqli_real_escape_string($conn, $uniqueEmail) . "' AS `$col`";
            continue;
        }

        // UNIQUE external_id / employee_code
        if (($col === 'external_id' || $col === 'employee_code') && !empty($old[$col])) {
            $newValue = $old[$col] . "-new" . random3();
            $selectCols[] = "'" . mysqli_real_escape_string($conn, $newValue) . "' AS `$col`";
            continue;
        }

        // Avoid duplicate first_name
        if ($col === 'first_name') {
            $value = "Clone of " . $old['first_name'];
            $selectCols[] = "'" . mysqli_real_escape_string($conn, $value) . "' AS `$col`";
            continue;
        }

        // Copy original value
        $value = isset($old[$col]) ? mysqli_real_escape_string($conn, $old[$col]) : NULL;

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
        die("Error creating new employee: " . mysqli_error($conn));
    }

    return mysqli_insert_id($conn);
}

// Create new employee
$new_employee_id = createNewEmployeeClone($conn, $old_employee_id);

echo "<h3>New employee created: ID $new_employee_id</h3>";

$excludeTables = ['audit_logs', 'attempts']; // never copy these

$errors = [];
$copyTables = [];

$res = mysqli_query($conn, "SHOW TABLES");

// STEP 1 — TEST COPY
echo "<h3>Testing COPY operations...</h3>";

while ($row = mysqli_fetch_row($res)) {
    $table = $row[0];

    if (in_array($table, $excludeTables)) {
        echo "<b>$table</b> <span style='color:gray'>[SKIPPED]</span><br>";
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
    echo "<h2 style='color:red'>COPY ABORTED — ERRORS DETECTED</h2>";
    echo "<p>Fix these tables before retrying:</p>";

    foreach ($errors as $e) {
        echo "<b>{$e['table']}</b>: {$e['error']}<br>";
    }

    exit;
}

// STEP 2 — EXECUTE REAL COPY
echo "<h2 style='color:green'>All tests passed — performing REAL COPY</h2>";

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
        echo "<b>$table</b>: <span style='color:green'>COPIED</span><br>";
    } else {
        echo "<b>$table</b>: <span style='color:red'>FAILED</span> " . mysqli_error($conn) . "<br>";
    }
}

echo "<h2 style='color:blue'>COPY PROCESS COMPLETED</h2>";
