<?php
/**
 * Regression: users merged into employees; session uses employee_id.
 *
 * Why: High-risk schema merge must keep login, company picker, and admin seed intact.
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';

$nl = PHP_SAPI === 'cli' ? PHP_EOL : '<br>';
$failures = 0;

function veam_pass($message)
{
    global $nl;
    echo '[PASS] ' . $message . $nl;
}

function veam_fail($message)
{
    global $nl, $failures;
    $failures++;
    echo '[FAIL] ' . $message . $nl;
}

// Schema: users table removed
$res = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if ($res && mysqli_num_rows($res) > 0) {
    veam_fail('Legacy users table still exists.');
} else {
    veam_pass('users table absent.');
}

foreach (['employee_companies', 'employee_roles', 'employee_sidebar_preferences'] as $table) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    if (!$res || mysqli_num_rows($res) === 0) {
        veam_fail('Missing table ' . $table . '.');
    } else {
        veam_pass('Table ' . $table . ' present.');
    }
}

// Admin seed login row
$stmt = mysqli_prepare(
    $conn,
    'SELECT id, username, work_email, password, role_id FROM employees
     WHERE id = 1 AND active = 1 AND password IS NOT NULL LIMIT 1'
);
if (!$stmt) {
    veam_fail('Cannot prepare admin seed query.');
} else {
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row || strtolower((string)$row['username']) !== 'admin') {
        veam_fail('Admin employee seed (id=1) missing or invalid.');
    } else {
        veam_pass('Admin employee seed id=1 present.');
    }
}

// employee_companies links for admin
$ec = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM employee_companies WHERE employee_id = 1');
$ecRow = $ec ? mysqli_fetch_assoc($ec) : null;
if (!$ecRow || (int)$ecRow['c'] < 1) {
    veam_fail('employee_companies has no rows for admin employee_id=1.');
} else {
    veam_pass('employee_companies seeded for admin.');
}

// Session contract (static grep-free sanity via PHP constant names in config)
if (!function_exists('itm_is_admin')) {
    veam_fail('itm_is_admin() missing from config bootstrap.');
} else {
    veam_pass('itm_is_admin() available.');
}

// Password verify path for admin (hash in database.sql seed)
$stmt = mysqli_prepare($conn, 'SELECT password FROM employees WHERE id = 1 LIMIT 1');
if ($stmt) {
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $hash);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if (!is_string($hash) || !password_verify('Admin', $hash)) {
        veam_fail('Admin password hash does not verify against Admin.');
    } else {
        veam_pass('Admin password verifies.');
    }
}

// employees must not have legacy user_id link column
$colRes = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'user_id'");
if ($colRes && mysqli_num_rows($colRes) > 0) {
    veam_fail('employees.user_id link column still exists.');
} else {
    veam_pass('employees.user_id link column absent.');
}

exit($failures > 0 ? 1 : 0);
