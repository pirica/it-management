<?php
/**
 * Human-style regression for invite registration, login, and password reset.
 *
 * Why: Mirrors public auth flows end-to-end without a browser — invitation create,
 * register INSERT contract, login SELECT + password_verify, forgot/reset token cycle.
 *
 * CLI: php scripts/auth_register_reset_human_test.php
 * Optional: php scripts/auth_register_reset_human_test.php --company=2
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/itm_employee_employment_status.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Auth Register / Login / Reset Human Test');

$conn = $GLOBALS['conn'] ?? null;
$nl = itm_script_output_nl();
$failures = 0;
$companyIds = [1, 2];

$argvLocal = $argv ?? [];
if (PHP_SAPI !== 'cli' && isset($_GET['company'])) {
    $argvLocal[] = '--company=' . (int)$_GET['company'];
}
foreach ($argvLocal as $arg) {
    if (strpos((string)$arg, '--company=') === 0) {
        $companyIds = [(int)substr((string)$arg, 10)];
    }
}

function auth_human_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function auth_human_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

/**
 * Why: bind_param mismatches surface as PHP warnings — catch them like a human page load would.
 */
function auth_human_bind_param_ok($typeString, $sql, array $params)
{
    global $conn;

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    $warned = false;
    set_error_handler(static function ($errno, $errstr) use (&$warned) {
        if (stripos($errstr, 'mysqli_stmt_bind_param') !== false) {
            $warned = true;
        }
        return true;
    });

    $bindArgs = array_merge([$stmt, $typeString], $params);
    $refs = [];
    foreach ($bindArgs as $key => $value) {
        $refs[$key] = &$bindArgs[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', $refs);
    restore_error_handler();
    mysqli_stmt_close($stmt);

    return !$warned;
}

if (!($conn instanceof mysqli)) {
    auth_human_fail('No database connection.');
    exit(1);
}

$resetAttemptSql = "INSERT INTO attempts (attempt_source, attempt_type, ip_address, employee_id, email, active)
 VALUES ('password_reset', 'reset', ?, ?, ?, IF(
    EXISTS(
        SELECT 1 FROM employees
        WHERE LOWER(TRIM(COALESCE(work_email, personal_email, ''))) = LOWER(TRIM(COALESCE(?, '')))
        LIMIT 1
    ),
    1,
    0
 ))";
$forgotAttemptSql = "INSERT INTO attempts (attempt_source, attempt_type, ip_address, email, employee_id, active)
 VALUES ('password_reset', ?, ?, ?, ?, IF(
    EXISTS(
        SELECT 1 FROM employees
        WHERE LOWER(TRIM(COALESCE(work_email, personal_email, ''))) = LOWER(TRIM(COALESCE(?, '')))
        LIMIT 1
    ),
    1,
    0
 ))";
$loginAttemptSql = "INSERT INTO attempts (attempt_source, attempt_type, ip_address, email, employee_id, active)
 VALUES ('login', ?, ?, ?, ?, IF(
    EXISTS(
        SELECT 1 FROM employees
        WHERE LOWER(TRIM(COALESCE(work_email, personal_email, ''))) = LOWER(TRIM(COALESCE(?, '')))
        LIMIT 1
    ),
    1,
    0
 ))";

$ip = '127.0.0.1';
$sampleEmail = 'human-auth@example.com';
$sampleEmployeeId = 1;

if (auth_human_bind_param_ok('siss', $resetAttemptSql, [$ip, $sampleEmployeeId, $sampleEmail, $sampleEmail])) {
    auth_human_pass('reset-password.php attempt logging bind_param contract');
} else {
    auth_human_fail('reset-password.php attempt logging bind_param contract');
}

if (auth_human_bind_param_ok('sssis', $forgotAttemptSql, ['request', $ip, $sampleEmail, $sampleEmployeeId, $sampleEmail])) {
    auth_human_pass('forgot-password.php attempt logging bind_param contract');
} else {
    auth_human_fail('forgot-password.php attempt logging bind_param contract');
}

if (auth_human_bind_param_ok('sssis', $loginAttemptSql, ['success', $ip, $sampleEmail, $sampleEmployeeId, $sampleEmail])) {
    auth_human_pass('login.php attempt logging bind_param contract');
} else {
    auth_human_fail('login.php attempt logging bind_param contract');
}

foreach ($companyIds as $companyId) {
    $companyId = (int)$companyId;
    if ($companyId <= 0) {
        continue;
    }

    echo $nl . colorText('--- Company ' . $companyId . ' invite → register → login → reset ---', 'info') . $nl;

    $inviteCode = 'HUMAN-INVITE-' . $companyId . '-' . strtoupper(bin2hex(random_bytes(4)));
    $email = 'human-register-' . $companyId . '-' . bin2hex(random_bytes(3)) . '@script-test.example.com';
    $username = itm_script_test_employee_username('auth-human-' . $companyId);
    $passwordPlain = 'HumanTestPass!' . $companyId;
    $invitationId = 0;
    $employeeId = 0;

    $roleStmt = mysqli_prepare($conn, 'SELECT id FROM employee_roles WHERE company_id = ? ORDER BY id ASC LIMIT 1');
    $accessStmt = mysqli_prepare($conn, 'SELECT id FROM access_levels WHERE company_id = ? ORDER BY id ASC LIMIT 1');
    $roleId = 0;
    $accessLevelId = 0;
    if ($roleStmt) {
        mysqli_stmt_bind_param($roleStmt, 'i', $companyId);
        mysqli_stmt_execute($roleStmt);
        mysqli_stmt_bind_result($roleStmt, $roleId);
        mysqli_stmt_fetch($roleStmt);
        mysqli_stmt_close($roleStmt);
    }
    if ($accessStmt) {
        mysqli_stmt_bind_param($accessStmt, 'i', $companyId);
        mysqli_stmt_execute($accessStmt);
        mysqli_stmt_bind_result($accessStmt, $accessLevelId);
        mysqli_stmt_fetch($accessStmt);
        mysqli_stmt_close($accessStmt);
    }

    $employmentStatusId = itm_employee_resolve_active_status_id($conn, $companyId);
    if ($roleId <= 0 || $accessLevelId <= 0 || $employmentStatusId <= 0) {
        auth_human_fail('Company ' . $companyId . ' missing role, access level, or Active employment status seed.');
        continue;
    }

    $inviteStmt = mysqli_prepare(
        $conn,
        'INSERT INTO registration_invitations (company_id, email, invitation_code, invited_by_employee_id, role_id, access_level_id, active)
         VALUES (?, ?, ?, 1, ?, ?, 1)'
    );
    if ($inviteStmt) {
        mysqli_stmt_bind_param($inviteStmt, 'issii', $companyId, $email, $inviteCode, $roleId, $accessLevelId);
        if (mysqli_stmt_execute($inviteStmt)) {
            $invitationId = (int)mysqli_insert_id($conn);
            auth_human_pass('Company ' . $companyId . ' invitation created (' . $inviteCode . ').');
        } else {
            auth_human_fail('Company ' . $companyId . ' invitation insert failed.');
        }
        mysqli_stmt_close($inviteStmt);
    }

    if ($invitationId <= 0) {
        continue;
    }

    $passwordHash = password_hash($passwordPlain, PASSWORD_DEFAULT);
    $insertStmt = mysqli_prepare(
        $conn,
        'INSERT INTO employees (company_id, first_name, last_name, username, work_email, password, role_id, access_level_id, employment_status_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($insertStmt) {
        $firstName = 'Human';
        $lastName = 'Register';
        mysqli_stmt_bind_param(
            $insertStmt,
            'isssssiii',
            $companyId,
            $firstName,
            $lastName,
            $username,
            $email,
            $passwordHash,
            $roleId,
            $accessLevelId,
            $employmentStatusId
        );
        if (mysqli_stmt_execute($insertStmt)) {
            $employeeId = (int)mysqli_insert_id($conn);
            auth_human_pass('Company ' . $companyId . ' register INSERT with tenant Active status id ' . $employmentStatusId . '.');
        } else {
            auth_human_fail('Company ' . $companyId . ' register INSERT failed.');
        }
        mysqli_stmt_close($insertStmt);
    }

    if ($employeeId <= 0) {
        mysqli_query($conn, 'DELETE FROM registration_invitations WHERE id = ' . $invitationId);
        continue;
    }

    mysqli_query($conn, 'INSERT INTO employee_companies (employee_id, company_id, granted_by_employee_id) VALUES (' . $employeeId . ', ' . $companyId . ', 1)');
    mysqli_query($conn, 'UPDATE registration_invitations SET accepted_at = NOW(), active = 0 WHERE id = ' . $invitationId);

    $join = itm_employee_active_employment_status_join_sql('e', 'es');
    $activePredicate = itm_employee_active_employment_status_predicate_sql('es');
    $loginSql = 'SELECT e.id, e.password, e.work_email, e.personal_email, e.username
                 FROM employees e' . $join . '
                 WHERE ' . $activePredicate . '
                   AND e.password IS NOT NULL
                   AND (
                        LOWER(COALESCE(e.work_email, "")) = LOWER(?)
                        OR LOWER(COALESCE(e.personal_email, "")) = LOWER(?)
                        OR LOWER(COALESCE(e.username, "")) = LOWER(?)
                   )
                 LIMIT 1';
    $loginStmt = mysqli_prepare($conn, $loginSql);
    $loginUser = null;
    if ($loginStmt) {
        mysqli_stmt_bind_param($loginStmt, 'sss', $email, $email, $username);
        mysqli_stmt_execute($loginStmt);
        $loginResult = mysqli_stmt_get_result($loginStmt);
        $loginUser = $loginResult ? mysqli_fetch_assoc($loginResult) : null;
        mysqli_stmt_close($loginStmt);
    }

    if (!$loginUser) {
        auth_human_fail('Company ' . $companyId . ' login lookup did not find registered user (employment status mismatch).');
    } elseif (!password_verify($passwordPlain, (string)($loginUser['password'] ?? ''))) {
        auth_human_fail('Company ' . $companyId . ' password_verify failed after registration.');
    } elseif (!itm_employee_has_active_employment_status($conn, $employeeId)) {
        auth_human_fail('Company ' . $companyId . ' active employment status check failed.');
    } else {
        auth_human_pass('Company ' . $companyId . ' login credentials valid for email and username.');
    }

    $resetToken = bin2hex(random_bytes(32));
    if (!itm_password_reset_store_token_for_employee($conn, $employeeId, $resetToken)) {
        auth_human_fail('Company ' . $companyId . ' reset token store failed.');
    }

    $newPasswordPlain = 'ResetHuman!' . $companyId;
    $newPasswordHash = password_hash($newPasswordPlain, PASSWORD_DEFAULT);
    $resetOk = itm_password_reset_complete_for_employee($conn, $employeeId, $resetToken, $newPasswordHash);

    if (!$resetOk) {
        auth_human_fail('Company ' . $companyId . ' reset-password UPDATE did not apply.');
    } else {
        auth_human_pass('Company ' . $companyId . ' reset-password token consumed and password updated.');
    }

    $loginStmt2 = mysqli_prepare($conn, $loginSql);
    $loginUser2 = null;
    if ($loginStmt2) {
        mysqli_stmt_bind_param($loginStmt2, 'sss', $email, $email, $username);
        mysqli_stmt_execute($loginStmt2);
        $loginResult2 = mysqli_stmt_get_result($loginStmt2);
        $loginUser2 = $loginResult2 ? mysqli_fetch_assoc($loginResult2) : null;
        mysqli_stmt_close($loginStmt2);
    }

    if (!$loginUser2 || !password_verify($newPasswordPlain, (string)($loginUser2['password'] ?? ''))) {
        auth_human_fail('Company ' . $companyId . ' login failed with password after reset.');
    } else {
        auth_human_pass('Company ' . $companyId . ' login succeeds with new password after reset.');
    }

    if (itm_script_test_employee_guard_mutable_id($conn, $employeeId)) {
        itm_script_test_employee_register_teardown($conn, $employeeId);
    }
    mysqli_query($conn, 'DELETE FROM registration_invitations WHERE id = ' . $invitationId);
}

if ($failures > 0) {
    echo colorText('Auth human test finished with ' . $failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('All auth register/login/reset human checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
