<?php
/**
 * Regression tests for employees clear_table soft-delete + detach.
 *
 * Usage (Laragon PHP 7.4+, repository root):
 *   php scripts/employees_delete_clear_table_test.php
 *
 * Optional env:
 *   ITM_DB_HOST, ITM_DB_USER, ITM_DB_PASS, ITM_DB_NAME
 *   ITM_SKIP_DB_TESTS=1   Skip integration cases (static checks still run)
 */

if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    fwrite(STDERR, "This script requires PHP 7.1 or newer.\n");
    exit(1);
}

define('ITM_CLI_SCRIPT', true);

$projectRoot = dirname(__DIR__);
require $projectRoot . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

require_once __DIR__ . '/lib/itm_script_test_employee.php';
require $projectRoot . '/modules/employees/delete_clear_table.php';
require $projectRoot . '/modules/employees/delete_functions.php';

function edct_is_cli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function edct_out($message)
{
    echo itm_script_format_status_line((string) $message) . itm_script_output_nl();
}

function edct_pass($message)
{
    edct_out('[PASS] ' . $message);
}

function edct_fail($message)
{
    throw new RuntimeException('[FAIL] ' . $message);
}

function edct_assert($condition, $message)
{
    if (!$condition) {
        edct_fail($message);
    }
    edct_pass($message);
}

function edct_count_for_company(mysqli $conn, string $table, int $companyId): int
{
    if (!itm_is_safe_identifier($table)) {
        return -1;
    }
    $sql = 'SELECT COUNT(*) AS c FROM `' . $table . '` WHERE company_id=' . (int)$companyId;
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return -1;
    }
    $row = mysqli_fetch_assoc($res);
    return (int)($row['c'] ?? 0);
}

/** Live (list-visible) employees for a tenant. */
function edct_count_live_employees(mysqli $conn, int $companyId): int
{
    $sql = 'SELECT COUNT(*) AS c FROM employees WHERE company_id=' . (int)$companyId . ' AND deleted_at IS NULL';
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return -1;
    }
    $row = mysqli_fetch_assoc($res);
    return (int)($row['c'] ?? 0);
}

/**
 * @return int
 */
function edct_set_audit_context(mysqli $conn, int $companyId, int $employeeId = 1): void
{
    // Why: audit triggers require a valid @app_company_id FK when CLI has no web session.
    mysqli_query($conn, 'SET @app_employee_id = ' . (int)$employeeId);
    mysqli_query($conn, 'SET @app_company_id = ' . (int)$companyId);
    mysqli_query($conn, "SET @app_username = 'cli-test'");
    mysqli_query($conn, "SET @app_email = 'cli-test@example.com'");
    mysqli_query($conn, "SET @app_ip_address = '127.0.0.1'");
    mysqli_query($conn, "SET @app_user_agent = 'employees_delete_clear_table_test'");
}

function edct_insert_company(mysqli $conn, string $name)
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO companies (company, active) VALUES (?, 1)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 's', $name);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function edct_delete_company(mysqli $conn, int $companyId)
{
    if ($companyId <= 0) {
        return;
    }
    $cid = (int)$companyId;
    // Why: Soft-deleted employees remain until teardown; wipe tenant children before company.
    mysqli_query($conn, 'DELETE FROM approvers WHERE company_id=' . $cid);
    mysqli_query($conn, 'DELETE FROM employee_system_access WHERE company_id=' . $cid);
    mysqli_query($conn, 'DELETE FROM employee_companies WHERE company_id=' . $cid);
    mysqli_query($conn, 'DELETE FROM employee_positions WHERE company_id=' . $cid);
    mysqli_query($conn, 'DELETE FROM departments WHERE company_id=' . $cid);
    mysqli_query($conn, 'DELETE FROM approver_types WHERE company_id=' . $cid);
    mysqli_query($conn, 'DELETE FROM employees WHERE company_id=' . $cid);
    mysqli_query($conn, 'DELETE FROM employee_statuses WHERE company_id=' . $cid);
    mysqli_query($conn, 'DELETE FROM companies WHERE id=' . $cid . ' LIMIT 1');
}

/**
 * @return int
 */
function edct_insert_status(mysqli $conn, int $companyId, string $name)
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO employee_statuses (company_id, name, active) VALUES (?, ?, 1)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $name);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

/**
 * @return int
 */
function edct_insert_department(mysqli $conn, int $companyId, string $name, string $code)
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO departments (company_id, name, code, active) VALUES (?, ?, ?, 1)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'iss', $companyId, $name, $code);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

/**
 * @return int
 */
function edct_insert_position(mysqli $conn, int $companyId, int $departmentId, string $name)
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO employee_positions (company_id, department_id, name, active) VALUES (?, ?, ?, 1)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'iis', $companyId, $departmentId, $name);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

/**
 * @return int
 */
function edct_insert_approver_type(mysqli $conn, int $companyId, string $description)
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO approver_type (company_id, approver_type_description, active) VALUES (?, ?, 1)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $description);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

/**
 * @return int
 */
function edct_insert_employee(mysqli $conn, int $companyId, int $statusId, string $first, string $last)
{
    $workEmail = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $first . '.' . $last)) . '@edct-test.example.com';
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO employees (company_id, first_name, last_name, work_email, employment_status_id) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'isssi', $companyId, $first, $last, $workEmail, $statusId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function edct_insert_access(mysqli $conn, int $companyId, int $employeeId): bool
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO employee_system_access (company_id, employee_id) VALUES (?, ?)');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $employeeId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (bool)$ok;
}

function edct_insert_employee_company(mysqli $conn, int $companyId, int $employeeId): bool
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO employee_companies (employee_id, company_id, active) VALUES (?, ?, 1)');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (bool)$ok;
}

function edct_insert_attempt(mysqli $conn, int $employeeId, string $email): bool
{
    $source = 'login';
    $type = 'success';
    $ip = '127.0.0.1';
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO attempts (employee_id, email, attempt_source, attempt_type, ip_address) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'issss', $employeeId, $email, $source, $type, $ip);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (bool)$ok;
}

function edct_insert_audit_log(mysqli $conn, int $companyId, int $employeeId): bool
{
    $tableName = 'employees';
    $recordId = $employeeId;
    $action = 'UPDATE';
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO audit_logs (company_id, employee_id, table_name, record_id, action) VALUES (?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iisis', $companyId, $employeeId, $tableName, $recordId, $action);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (bool)$ok;
}

function edct_count_attempts_for_employee(mysqli $conn, int $employeeId): int
{
    $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM attempts WHERE employee_id=' . (int)$employeeId);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    return (int)($row['c'] ?? 0);
}

function edct_insert_approver(
    mysqli $conn,
    int $companyId,
    int $employeeId,
    int $positionId,
    int $departmentId,
    int $approverTypeId
): bool {
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO approvers (company_id, employee_id, employee_position_id, department_id, approver_type_id, active) VALUES (?, ?, ?, ?, ?, 1)'
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iiiii', $companyId, $employeeId, $positionId, $departmentId, $approverTypeId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (bool)$ok;
}

function edct_run_static_checks()
{
    $deletePath = dirname(__DIR__) . '/modules/employees/delete.php';
    $helperPath = dirname(__DIR__) . '/modules/employees/delete_clear_table.php';
    $functionsPath = dirname(__DIR__) . '/modules/employees/delete_functions.php';
    $dependenciesPath = dirname(__DIR__) . '/includes/itm_employees_delete_dependencies.php';
    edct_assert(is_file($deletePath), 'delete.php exists');
    edct_assert(is_file($helperPath), 'delete_clear_table.php exists');
    edct_assert(is_file($functionsPath), 'delete_functions.php exists');
    edct_assert(is_file($dependenciesPath), 'itm_employees_delete_dependencies.php exists');

    $deleteSource = (string)file_get_contents($deletePath);
    $helperSource = (string)file_get_contents($helperPath);
    $functionsSource = (string)file_get_contents($functionsPath);
    $dependenciesSource = (string)file_get_contents($dependenciesPath);

    edct_assert(stripos($deleteSource, 'delete_clear_table.php') !== false, 'delete.php loads clear-table helper');
    edct_assert(stripos($deleteSource, 'delete_functions.php') !== false, 'delete.php loads delete_functions.php');
    edct_assert(stripos($deleteSource, 'employees_clear_table_for_company') !== false, 'delete.php calls employees_clear_table_for_company');
    edct_assert(stripos($functionsSource, 'itm_employees_detach_delete_dependencies') !== false, 'delete_functions.php detaches dependencies before soft-delete');
    edct_assert(stripos($functionsSource, 'itm_crud_build_soft_delete_sql') !== false, 'delete_functions.php soft-deletes via shared helper');
    edct_assert(stripos($functionsSource, 'mysqli_begin_transaction') !== false, 'delete_functions.php uses mysqli_begin_transaction');
    edct_assert(stripos($functionsSource, 'mysqli_rollback') !== false, 'delete_functions.php uses mysqli_rollback on failure');
    edct_assert(stripos($functionsSource, 'mysqli_commit') !== false, 'delete_functions.php commits on success');
    edct_assert(stripos($functionsSource, 'DELETE FROM employees') === false, 'delete_functions.php does not hard-delete employees');
    edct_assert(stripos($helperSource, 'deleted_at IS NULL') !== false, 'clear_table selects live rows only');
    edct_assert(stripos($helperSource, 'employees_delete_record') !== false, 'clear_table reuses employees_delete_record');
    edct_assert(stripos($dependenciesSource, 'DELETE FROM attempts WHERE employee_id = ?') !== false, 'dependency helper clears attempts rows');
    edct_assert(stripos($dependenciesSource, 'DELETE FROM employee_companies WHERE employee_id = ?') !== false, 'dependency helper clears employee_companies rows');
}

function edct_run_db_integration(mysqli $conn)
{
    $bootstrapCompanyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
    if ($bootstrapCompanyId <= 0) {
        $bootstrapCompanyId = 1;
    }
    edct_set_audit_context($conn, $bootstrapCompanyId);

    // Why: Soft-delete stamps deleted_by from $_SESSION['employee_id'].
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $_SESSION['employee_id'] = 1;
    $_SESSION['company_id'] = $bootstrapCompanyId;

    $suffix = 'itm_edct_' . gmdate('YmdHis') . '_' . mt_rand(1000, 9999);
    $companyName = 'ITM ClearTable Test ' . $suffix;
    $companyId = edct_insert_company($conn, $companyName);
    edct_assert($companyId > 0, 'created isolated test company');

    try {
        edct_set_audit_context($conn, $companyId);
        $statusId = edct_insert_status($conn, $companyId, 'Active ' . $suffix);
        $departmentId = edct_insert_department($conn, $companyId, 'Test Dept ' . $suffix, 'T' . substr($suffix, -4));
        $positionId = edct_insert_position($conn, $companyId, $departmentId, 'Test Role ' . $suffix);
        $approverTypeId = edct_insert_approver_type($conn, $companyId, 'Test Approver ' . $suffix);
        edct_assert($statusId > 0 && $departmentId > 0 && $positionId > 0 && $approverTypeId > 0, 'seeded reference rows');

        $employeeId = edct_insert_employee($conn, $companyId, $statusId, 'Clear', 'TableSuccess');
        edct_assert($employeeId > 0, 'inserted test employee');
        edct_assert(edct_insert_access($conn, $companyId, $employeeId), 'inserted employee_system_access row');
        edct_assert(edct_count_for_company($conn, 'employees', $companyId) === 1, 'one employee before clear');
        edct_assert(edct_count_for_company($conn, 'employee_system_access', $companyId) === 1, 'one access row before clear');

        $clearError = employees_clear_table_for_company($conn, $companyId);
        edct_assert($clearError === null, 'clear_table succeeds without FK blockers: ' . (string)$clearError);
        edct_assert(edct_count_live_employees($conn, $companyId) === 0, 'live employees cleared for test company');
        edct_assert(edct_count_for_company($conn, 'employees', $companyId) === 1, 'soft-deleted employee row remains');
        edct_assert(edct_count_for_company($conn, 'employee_system_access', $companyId) === 0, 'access cleared for test company');

        // Why: Approvers are detached before soft-delete, so clear_table must succeed
        // (no hard-delete FK rollback). Soft-deleted rows stay for audit view.
        $withApproverId = edct_insert_employee($conn, $companyId, $statusId, 'Clear', 'WithApprover');
        edct_assert($withApproverId > 0, 'inserted employee with approver child');
        edct_assert(edct_insert_access($conn, $companyId, $withApproverId), 'inserted access for approver case');
        edct_assert(
            edct_insert_approver($conn, $companyId, $withApproverId, $positionId, $departmentId, $approverTypeId),
            'inserted approver row detached during soft-delete'
        );

        $approverClearError = employees_clear_table_for_company($conn, $companyId);
        edct_assert($approverClearError === null, 'clear_table soft-deletes after detaching approvers: ' . (string)$approverClearError);
        edct_assert(edct_count_live_employees($conn, $companyId) === 0, 'no live employees after approver clear');
        edct_assert(edct_count_for_company($conn, 'employee_system_access', $companyId) === 0, 'access cleared after approver clear');
        edct_assert(edct_count_for_company($conn, 'approvers', $companyId) === 0, 'approvers detached during soft-delete');

        $statusId2 = edct_insert_status($conn, $companyId, 'Active single ' . $suffix);
        edct_assert($statusId2 > 0, 'seeded status for single-delete test');
        $disposable = itm_script_test_employee_create($conn, $companyId, [
            'employment_status_id' => $statusId2,
            'first_name' => 'Delete',
            'last_name' => 'DepsTest',
        ]);
        edct_assert(is_array($disposable) && (int)($disposable['id'] ?? 0) > 0, 'created disposable employee for single delete');
        $singleEmployeeId = (int)$disposable['id'];
        $singleEmail = (string)($disposable['email'] ?? 'deps-test@example.com');

        edct_assert(edct_insert_access($conn, $companyId, $singleEmployeeId), 'inserted access row for single delete');
        edct_assert(edct_insert_employee_company($conn, $companyId, $singleEmployeeId), 'inserted employee_companies row for single delete');
        edct_assert(edct_insert_attempt($conn, $singleEmployeeId, $singleEmail), 'inserted attempts row for single delete');
        edct_assert(edct_insert_audit_log($conn, $companyId, $singleEmployeeId), 'inserted audit_logs row for single delete');
        edct_assert(edct_count_attempts_for_employee($conn, $singleEmployeeId) >= 1, 'attempts row present before single delete');

        $singleDeleteError = employees_delete_record($conn, $companyId, $singleEmployeeId);
        edct_assert($singleDeleteError === null, 'single delete succeeds with dependency rows: ' . (string)$singleDeleteError);
        edct_assert(edct_count_live_employees($conn, $companyId) === 0, 'employee soft-deleted after single delete');
        $softRow = mysqli_query($conn, 'SELECT active, deleted_at FROM employees WHERE id=' . (int)$singleEmployeeId . ' LIMIT 1');
        $softAssoc = $softRow ? mysqli_fetch_assoc($softRow) : null;
        edct_assert(is_array($softAssoc) && (int)($softAssoc['active'] ?? 1) === 0 && !empty($softAssoc['deleted_at']), 'soft-delete stamps active=0 and deleted_at');
        edct_assert(edct_count_attempts_for_employee($conn, $singleEmployeeId) === 0, 'attempts detached during single delete');
    } finally {
        edct_delete_company($conn, $companyId);
        edct_pass('removed temporary test company');
    }
}

if (!edct_is_cli()) {
    header('Content-Type: text/html; charset=UTF-8');
    require_once __DIR__ . '/lib/script_browser_nav.php';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Employees clear_table test</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;">';
}

$failures = 0;
edct_out('Employees clear_table regression');
edct_out('PHP ' . PHP_VERSION);

try {
    edct_run_static_checks();
} catch (Throwable $e) {
    edct_out($e->getMessage());
    $failures++;
}

$skipDb = getenv('ITM_SKIP_DB_TESTS') === '1' || getenv('ITM_SKIP_DB_TESTS') === 'true';
if (!edct_is_cli()) {
    edct_out('[SKIP] Database integration (CLI only — browser runs static checks only)');
} elseif ($skipDb) {
    edct_out('[SKIP] Database integration (ITM_SKIP_DB_TESTS=1)');
} elseif (!isset($conn) || !($conn instanceof mysqli)) {
    edct_out('[SKIP] Database integration (no mysqli connection)');
} else {
    try {
        edct_run_db_integration($conn);
    } catch (Throwable $e) {
        edct_out($e->getMessage());
        $failures++;
    }
}

if (!edct_is_cli()) {
    echo '</body></html>';
}

if ($failures > 0) {
    edct_out('');
    edct_out('Result: FAILED (' . $failures . ')');
    exit(1);
}

edct_out('');
edct_out('Result: OK');
exit(0);

itm_script_output_end();
