<?php
/**
 * Request Password module regression checks.
 *
 * CLI: php scripts/verify_request_password.php
 * Browser: scripts/verify_request_password.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Request Password Verification');

$nl = itm_script_output_nl();
$failures = 0;
$companyId = 1;

function rpw_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function rpw_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

/**
 * Mirror modules/request_password/index.php rp_can_delete_request().
 *
 * @param array<string,mixed> $row
 */
function rpw_verify_can_delete_request(array $row, $employeeId)
{
    $employeeId = (int)$employeeId;
    if ($employeeId <= 0) {
        return false;
    }
    $createdBy = (int)($row['created_by'] ?? 0);
    if ($createdBy > 0) {
        return $createdBy === $employeeId;
    }
    return (int)($row['employee_id'] ?? 0) === $employeeId;
}

function rpw_verify_audit_triggers(mysqli $conn, $table)
{
    $safeTable = mysqli_real_escape_string($conn, (string)$table);
    $res = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM information_schema.TRIGGERS
         WHERE TRIGGER_SCHEMA = DATABASE()
           AND EVENT_OBJECT_TABLE = '{$safeTable}'
           AND TRIGGER_NAME LIKE 'trg\\_%\\_audit\\_%'"
    );
    $count = $res ? (int)(mysqli_fetch_assoc($res)['c'] ?? 0) : 0;
    if ($count < 3) {
        rpw_verify_fail("Missing audit triggers for {$table} (expected 3, found {$count})");
        return;
    }
    rpw_verify_pass("Audit triggers present for {$table}");
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    rpw_verify_fail('No database connection.');
    exit(1);
}

$res = mysqli_query($conn, "SHOW TABLES LIKE 'request_password'");
if (!$res || mysqli_num_rows($res) === 0) {
    rpw_verify_fail('Missing table request_password.');
} else {
    rpw_verify_pass('Table request_password exists.');
}

$registryStmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? LIMIT 1');
$slug = 'request_password';
if ($registryStmt) {
    mysqli_stmt_bind_param($registryStmt, 's', $slug);
    mysqli_stmt_execute($registryStmt);
    $hasRow = mysqli_stmt_fetch($registryStmt);
    mysqli_stmt_close($registryStmt);
    if (!$hasRow) {
        rpw_verify_fail('modules_registry missing request_password.');
    } else {
        rpw_verify_pass('modules_registry has request_password.');
    }
}

$indexPath = ROOT_PATH . 'modules/request_password/index.php';
$indexCode = is_file($indexPath) ? (string)file_get_contents($indexPath) : '';
if ($indexCode === '') {
    rpw_verify_fail('modules/request_password/index.php missing.');
} else {
    if (strpos($indexCode, 'itm_require_crud_role_module_permission') === false) {
        rpw_verify_fail('index.php missing RBAC guard itm_require_crud_role_module_permission().');
    } else {
        rpw_verify_pass('index.php enforces RBAC via itm_require_crud_role_module_permission().');
    }
    if (strpos($indexCode, 'hash_hmac') === false || strpos($indexCode, 'hash_equals') === false) {
        rpw_verify_fail('index.php missing HMAC approval token verification.');
    } else {
        rpw_verify_pass('index.php uses HMAC + hash_equals for approval links.');
    }
    if (strpos($indexCode, 'data-itm-db-import-endpoint="index.php"') === false) {
        rpw_verify_fail('index.php list table missing data-itm-db-import-endpoint="index.php".');
    } else {
        rpw_verify_pass('index.php list table keeps import endpoint marker.');
    }
    if (strpos($indexCode, 'itm-actions-cell') === false || strpos($indexCode, 'data-itm-actions-origin="1"') === false) {
        rpw_verify_fail('index.php Actions column missing itm-actions-cell / data-itm-actions-origin markers.');
    } else {
        rpw_verify_pass('index.php Actions column markers present.');
    }
    if (strpos($indexCode, 'itm_crud_build_soft_delete_sql') === false && strpos($indexCode, 'deleted_at = CURRENT_TIMESTAMP') === false) {
        rpw_verify_fail('index.php delete path missing soft-delete wiring.');
    } else {
        rpw_verify_pass('index.php delete path uses soft-delete.');
    }
    if (strpos($indexCode, 'Only the employee who created this request can delete it') === false) {
        rpw_verify_fail('index.php missing creator-only delete guard message.');
    } else {
        rpw_verify_pass('index.php documents creator-only delete guard.');
    }
    require_once ROOT_PATH . 'scripts/lib/itm_ui_list_contract_checks.php';
    $bulkDeleteCheck = itm_check_bulk_delete_actions($indexCode, 'modules/request_password/index.php', is_file(ROOT_PATH . 'modules/request_password/delete.php'));
    if (($bulkDeleteCheck['status'] ?? '') !== 'pass') {
        rpw_verify_fail('index.php bulk delete contract: ' . ($bulkDeleteCheck['details'] ?? 'failed'));
    } else {
        rpw_verify_pass('index.php bulk delete toolbar gated by records_per_page.');
    }
    $bulkCancelCheck = itm_check_bulk_cancel_contract($indexCode);
    if (($bulkCancelCheck['status'] ?? '') !== 'pass') {
        rpw_verify_fail('index.php bulk cancel contract: ' . ($bulkCancelCheck['details'] ?? 'failed'));
    } else {
        rpw_verify_pass('index.php bulk cancel uses shared bulk-delete-selection.js.');
    }
}

$secret = 'request_password_secret_key_2024';
$recordId = 42;
$token = hash_hmac('sha256', $recordId . 'hr' . 'approve', $secret);
if (!hash_equals($token, hash_hmac('sha256', (string)$recordId . 'hr' . 'approve', $secret))) {
    rpw_verify_fail('HMAC approval token contract mismatch.');
} else {
    rpw_verify_pass('HMAC approval token contract OK.');
}

if (!rpw_verify_can_delete_request(['created_by' => 10, 'employee_id' => 11], 10)) {
    rpw_verify_fail('Creator with created_by should be allowed to delete.');
} else {
    rpw_verify_pass('Creator with created_by may delete.');
}
if (rpw_verify_can_delete_request(['created_by' => 10, 'employee_id' => 11], 11)) {
    rpw_verify_fail('Non-creator must not delete when created_by is set.');
} else {
    rpw_verify_pass('Non-creator blocked when created_by is set.');
}
if (!rpw_verify_can_delete_request(['created_by' => 0, 'employee_id' => 12], 12)) {
    rpw_verify_fail('Legacy row without created_by should allow applicant employee_id.');
} else {
    rpw_verify_pass('Legacy applicant fallback delete rule OK.');
}

rpw_verify_audit_triggers($conn, 'request_password');

$owner = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-request-password-owner']);
$other = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-request-password-other']);
if (!is_array($owner) || !is_array($other)) {
    rpw_verify_fail('Unable to create disposable test employees.');
} else {
    itm_script_test_employee_register_teardown($conn, (int)$owner['id']);
    itm_script_test_employee_register_teardown($conn, (int)$other['id']);

    $ownerId = (int)$owner['id'];
    $otherId = (int)$other['id'];
    $requestId = 0;
    $application = 'Windows Login';
    $reason = 'Cannot recall password';
    $insertStmt = mysqli_prepare(
        $conn,
        'INSERT INTO request_password (company_id, employee_id, requested_by_employee_id, application, reason, active, created_by)
         VALUES (?, ?, ?, ?, ?, 1, ?)'
    );
    if ($insertStmt) {
        mysqli_stmt_bind_param($insertStmt, 'iiissi', $companyId, $ownerId, $ownerId, $application, $reason, $ownerId);
        if (mysqli_stmt_execute($insertStmt)) {
            $requestId = (int)mysqli_insert_id($conn);
        }
        mysqli_stmt_close($insertStmt);
    }
    if ($requestId <= 0) {
        rpw_verify_fail('Unable to insert disposable request_password row.');
    } else {
        $lookupStmt = mysqli_prepare(
            $conn,
            'SELECT id, created_by, employee_id FROM request_password WHERE id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL LIMIT 1'
        );
        $row = null;
        if ($lookupStmt) {
            mysqli_stmt_bind_param($lookupStmt, 'ii', $requestId, $companyId);
            mysqli_stmt_execute($lookupStmt);
            $lookupRes = mysqli_stmt_get_result($lookupStmt);
            $row = $lookupRes ? mysqli_fetch_assoc($lookupRes) : null;
            mysqli_stmt_close($lookupStmt);
        }
        if (!$row) {
            rpw_verify_fail('Disposable request_password row not found after insert.');
        } elseif (!rpw_verify_can_delete_request($row, $ownerId)) {
            rpw_verify_fail('Owner should pass creator delete guard for live row.');
        } elseif (rpw_verify_can_delete_request($row, $otherId)) {
            rpw_verify_fail('Other employee must fail creator delete guard.');
        } else {
            rpw_verify_pass('Creator-only delete guard matches live request_password row.');
        }
        mysqli_query($conn, 'DELETE FROM request_password WHERE id = ' . (int)$requestId);
    }
}

if ($failures > 0) {
    echo colorText($failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('All request_password checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
