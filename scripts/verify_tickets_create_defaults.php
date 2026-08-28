<?php
/**
 * CLI: php scripts/verify_tickets_create_defaults.php
 *
 * Regression for tickets create/edit Created By default and per-request tenant context sync.
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/verify_tickets_create_defaults.php</code> — exit <code>1</code> on failure. Run when changing <code>modules/tickets/create.php</code>, <code>modules/tickets/tickets_form_employee_defaults.php</code>, <code>includes/itm_company_session.php</code> tenant context sync, or <code>config/config.php</code> session ensure hook.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

$_SESSION = [
    'employee_id' => 1,
    'login_employee_id' => 1,
    'company_id' => 4,
    'role_name' => 'admin',
    'username' => 'Admin',
];

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'modules/tickets/tickets_form_employee_defaults.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Tickets create defaults verification');

$failures = 0;
$probeCompanyId = 4;

function vtcd_fail($message)
{
    global $failures;
    $failures++;
    itm_script_write_stderr("[FAIL] {$message}\n");
}

function vtcd_pass($message)
{
    itm_script_write_stdout("[PASS] {$message}\n");
}

$expectedAdminId = function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')
    ? (int)itm_seed_resolve_tenant_seed_admin_employee_id($conn, $probeCompanyId)
    : $probeCompanyId;

if ($expectedAdminId <= 0) {
    vtcd_fail('Could not resolve tenant seed admin for company ' . $probeCompanyId . '.');
} else {
    vtcd_pass('Tenant seed admin for company ' . $probeCompanyId . ' is employee id ' . $expectedAdminId . '.');
}

if (!($conn instanceof mysqli)) {
    vtcd_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

// Why: CLI session_start() loads an empty session file and clears pre-require $_SESSION — mimic stale browser cookie after bootstrap.
$_SESSION['employee_id'] = 1;
$_SESSION['login_employee_id'] = 1;
$_SESSION['company_id'] = $probeCompanyId;
$_SESSION['role_name'] = 'admin';
$_SESSION['username'] = 'Admin';

if (!function_exists('itm_ensure_company_context_employee_session')) {
    vtcd_fail('itm_ensure_company_context_employee_session() is unavailable.');
} else {
    itm_ensure_company_context_employee_session($conn, $probeCompanyId);
    $sessionEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
    if ($sessionEmployeeId !== $expectedAdminId) {
        vtcd_fail(
            'itm_ensure_company_context_employee_session() must remap stale Admin login to tenant id '
            . $expectedAdminId . ' for company ' . $probeCompanyId . '; got ' . $sessionEmployeeId . '.'
        );
    } else {
        vtcd_pass(
            'itm_ensure_company_context_employee_session() remapped stale session to tenant employee id '
            . $sessionEmployeeId . ' at company ' . $probeCompanyId . '.'
        );
    }
}

$defaultCreatedBy = tickets_default_created_by_employee_id($conn, $probeCompanyId);
if ($defaultCreatedBy !== $expectedAdminId) {
    vtcd_fail(
        'tickets_default_created_by_employee_id() for company ' . $probeCompanyId
        . ' must return ' . $expectedAdminId . '; got ' . $defaultCreatedBy . '.'
    );
} else {
    vtcd_pass('tickets_default_created_by_employee_id() returns tenant context employee id ' . $defaultCreatedBy . '.');
}

if (!function_exists('itm_user_options_for_company') || !function_exists('itm_user_append_selected_option')) {
    vtcd_fail('Employee dropdown helpers unavailable for HTML selection probe.');
} else {
    require_once ROOT_PATH . 'includes/employee_dropdown_helpers.php';
    $createdByOptions = itm_user_append_selected_option(
        $conn,
        $probeCompanyId,
        itm_user_options_for_company($conn, $probeCompanyId),
        $defaultCreatedBy
    );

    $selectedCreatedBy = 0;
    foreach ($createdByOptions as $option) {
        if ((int)($option['id'] ?? 0) === $defaultCreatedBy) {
            $selectedCreatedBy = (int)$option['id'];
            break;
        }
    }

    if ($selectedCreatedBy !== $expectedAdminId) {
        vtcd_fail('Created By option list does not include the tenant default employee id ' . $expectedAdminId . '.');
    } else {
        vtcd_pass('Created By dropdown would select tenant employee id ' . $selectedCreatedBy . ' (not cross-tenant Admin1).');
    }

    $wrongSelected = 0;
    foreach ($createdByOptions as $option) {
        if ((int)($option['id'] ?? 0) === 1 && $expectedAdminId !== 1) {
            $wrongSelected = 1;
            break;
        }
    }
    if ($wrongSelected === 1 && $defaultCreatedBy === 1) {
        vtcd_fail('Created By default incorrectly remains employee id 1 for company ' . $probeCompanyId . '.');
    } elseif ($defaultCreatedBy !== 1 || $expectedAdminId === 1) {
        vtcd_pass('Created By default is not pinned to employee id 1 for company ' . $probeCompanyId . '.');
    }
}

$seedStmt = mysqli_prepare(
    $conn,
    'SELECT created_by_employee_id, assigned_to_employee_id
     FROM tickets
     WHERE company_id = ? AND ticket_external_code = ?
     LIMIT 1'
);
if ($seedStmt) {
    $seedCode = 'TCK-0001';
    mysqli_stmt_bind_param($seedStmt, 'is', $probeCompanyId, $seedCode);
    mysqli_stmt_execute($seedStmt);
    $seedRes = mysqli_stmt_get_result($seedStmt);
    $seedRow = ($seedRes && ($fetched = mysqli_fetch_assoc($seedRes))) ? $fetched : null;
    mysqli_stmt_close($seedStmt);

    if (!is_array($seedRow)) {
        vtcd_fail('Missing seeded TCK-0001 row for company ' . $probeCompanyId . ' (edit-form probe).');
    } else {
        $createdBy = (int)($seedRow['created_by_employee_id'] ?? 0);
        $assignedTo = (int)($seedRow['assigned_to_employee_id'] ?? 0);
        if ($createdBy !== $expectedAdminId || $assignedTo !== $expectedAdminId) {
            vtcd_fail(
                'Edit TCK-0001 at company ' . $probeCompanyId
                . ' must use tenant admin ids ' . $expectedAdminId
                . '; got created_by=' . $createdBy . ' assigned_to=' . $assignedTo
                . ' (re-import db/ if stale).'
            );
        } else {
            vtcd_pass(
                'Edit TCK-0001 seed uses tenant admin for created_by and assigned_to (id '
                . $expectedAdminId . ').'
            );
        }
    }
} else {
    vtcd_fail('Prepare failed for TCK-0001 edit-form probe.');
}

itm_script_output_end();
exit($failures === 0 ? 0 : 1);
