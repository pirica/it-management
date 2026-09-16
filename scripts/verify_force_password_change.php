<?php
/**
 * Regression: employees.must_change_password first-login gate (ITM-PENTEST-004 mitigation).
 *
 * CLI: php scripts/verify_force_password_change.php
 * Browser: scripts/verify_force_password_change.php?run=1 (Administrator).
 */

require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

if (!function_exists('itm_script_browser_how_to_use')) {
    function itm_script_browser_how_to_use(): string
    {
        return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_force_password_change.php</code> — validates <code>must_change_password</code> helpers, seed flag, and portal gate wiring.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
    }
}

$isBrowser = PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg' && !defined('PHPUNIT_RUNNING');
if ($isBrowser) {
    if (empty($_GET['run'])) {
        itm_script_output_begin('Verify force password change');
        itm_script_output_close_pre();
        echo '<p>' . itm_script_browser_how_to_use() . '</p>';
        echo '<p><a href="?run=1">Run verification</a></p>';
        itm_script_output_end();
        exit(0);
    }
    require_once dirname(__DIR__) . '/config/config.php';
    if (!itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
        http_response_code(403);
        echo 'Administrator session required.';
        exit(1);
    }
} else {
    if (!defined('ITM_CLI_SCRIPT')) {
        define('ITM_CLI_SCRIPT', true);
    }
    require_once dirname(__DIR__) . '/config/config.php';
}

$nl = itm_script_output_nl();
$failures = 0;

if (!($conn instanceof mysqli)) {
    echo '[FAIL] No database connection.' . $nl;
    exit(1);
}

$pass = static function (string $message) use ($nl): void {
    echo '[PASS] ' . $message . $nl;
};

$fail = static function (string $message) use ($nl, &$failures): void {
    $failures++;
    echo '[FAIL] ' . $message . $nl;
};

if (!function_exists('itm_employee_must_change_password')) {
    $fail('includes/itm_force_password_change.php not loaded.');
} else {
    $pass('itm_force_password_change helpers loaded from config.php');
}

if (!itm_force_password_change_column_exists($conn)) {
    $fail('employees.must_change_password column missing — import db/ or run migrate.php --apply');
} else {
    $pass('employees.must_change_password column exists');
}

$adminStmt = mysqli_prepare(
    $conn,
    "SELECT id, must_change_password FROM employees WHERE username = 'Admin' AND deleted_at IS NULL LIMIT 1"
);
$adminId = 0;
$adminFlag = null;
if ($adminStmt) {
    mysqli_stmt_execute($adminStmt);
    $adminRow = mysqli_fetch_assoc(mysqli_stmt_get_result($adminStmt));
    mysqli_stmt_close($adminStmt);
    if (is_array($adminRow)) {
        $adminId = (int)($adminRow['id'] ?? 0);
        $adminFlag = (int)($adminRow['must_change_password'] ?? 0);
    }
}

if ($adminId <= 0) {
    $fail('Seed Admin employee not found.');
} elseif ($adminFlag !== 1) {
    $fail('Seed Admin must_change_password expected 1, got ' . (int)$adminFlag);
} else {
    $pass('Seed Admin row has must_change_password = 1');
}

$validation = itm_force_password_change_validate_new_password($conn, $adminId > 0 ? $adminId : 1, 'Admin', 'Admin');
if ($validation['ok']) {
    $fail('Validation should reject password matching username Admin');
} else {
    $pass('Validation rejects password equal to username');
}

$testEmployee = itm_script_test_employee_create($conn, 1, ['script_slug' => 'verify_force_password_change']);
if (!is_array($testEmployee) || empty($testEmployee['id'])) {
    $fail('Unable to create disposable test employee');
} else {
    $testId = (int)$testEmployee['id'];
    $weakHash = password_hash('short', PASSWORD_DEFAULT);
    $flagStmt = mysqli_prepare(
        $conn,
        'UPDATE employees SET password = ?, must_change_password = 1 WHERE id = ? LIMIT 1'
    );
    if ($flagStmt) {
        mysqli_stmt_bind_param($flagStmt, 'si', $weakHash, $testId);
        mysqli_stmt_execute($flagStmt);
        mysqli_stmt_close($flagStmt);
    }

    if (!itm_employee_must_change_password($conn, $testId)) {
        $fail('Disposable employee with must_change_password=1 should require change');
    } else {
        $pass('itm_employee_must_change_password() true when flag set');
    }

    $newPassword = 'ScriptTest-' . bin2hex(random_bytes(4));
    if (!itm_force_password_change_apply_new_password($conn, $testId, $newPassword)) {
        $fail('itm_force_password_change_apply_new_password() failed');
    } elseif (itm_employee_must_change_password($conn, $testId)) {
        $fail('Flag should clear after successful password change');
    } else {
        $readStmt = mysqli_prepare($conn, 'SELECT password FROM employees WHERE id = ? LIMIT 1');
        $stored = '';
        if ($readStmt) {
            mysqli_stmt_bind_param($readStmt, 'i', $testId);
            mysqli_stmt_execute($readStmt);
            $readRow = mysqli_fetch_assoc(mysqli_stmt_get_result($readStmt));
            mysqli_stmt_close($readStmt);
            $stored = (string)($readRow['password'] ?? '');
        }
        if (!password_verify($newPassword, $stored)) {
            $fail('Updated password hash does not verify');
        } else {
            $pass('Password updated and must_change_password cleared');
        }
    }

    itm_script_test_employee_register_teardown($conn, $testId);
}

$configSource = (string)@file_get_contents(dirname(__DIR__) . '/config/config.php');
if (strpos($configSource, 'itm_force_password_change_enforce_or_redirect') === false) {
    $fail('config/config.php missing force-password-change gate');
} else {
    $pass('config/config.php enforces must_change_password redirect');
}

if (is_file(dirname(__DIR__) . '/force-password-change.php')) {
    $pass('force-password-change.php entry page present');
} else {
    $fail('force-password-change.php missing');
}

if ($failures > 0) {
    if ($isBrowser) {
        itm_script_output_begin('Verify force password change');
        itm_script_output_close_pre();
    }
    echo $nl . 'Result: FAIL (' . $failures . ')' . $nl;
    exit(1);
}

if ($isBrowser) {
    itm_script_output_begin('Verify force password change');
    itm_script_output_close_pre();
}
echo $nl . 'Result: PASS' . $nl;
exit(0);
