<?php
/**
 * CLI: php scripts/verify_share_modules_enable.php
 * Verifies that seed admins for companies 1, 3, and 5 are fully authorized,
 * and enables/verifies all capable share modules for companies 1, 3, and 5.
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once ROOT_PATH . 'includes/itm_module_share.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Share Modules Company 1, 3, 5 Enable and Verification');
$nl = itm_script_output_nl();
$failures = 0;

function verify_enable_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function verify_enable_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

if (!($conn instanceof mysqli)) {
    verify_enable_fail('Database connection unavailable.');
    exit(1);
}

// 1. Verify admins for companies 1, 3, 5
$targetCompanies = [1, 3, 5];
foreach ($targetCompanies as $companyId) {
    $username = $companyId === 1 ? 'Admin' : 'Admin' . $companyId;
    $stmt = mysqli_prepare($conn, 'SELECT id FROM employees WHERE company_id = ? AND username = ? LIMIT 1');
    if (!$stmt) {
        verify_enable_fail("Could not prepare select for company {$companyId} admin.");
        continue;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $employeeRow = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$employeeRow) {
        verify_enable_fail("Admin employee with username {$username} not found for company {$companyId}.");
        continue;
    }

    $employeeId = (int)$employeeRow['id'];
    $isAdmin = itm_is_admin($conn, $employeeId);
    if (!$isAdmin) {
        verify_enable_fail("Employee {$username} (ID: {$employeeId}) is NOT recognized as admin for company {$companyId}.");
    } else {
        verify_enable_pass("Employee {$username} (ID: {$employeeId}) is successfully recognized as admin for company {$companyId}.");
    }
}

// 2. Enable and verify all capable modules for companies 1, 3, 5
$capableSlugs = itm_qr_share_capable_module_slugs();
foreach ($targetCompanies as $companyId) {
    echo "{$nl}Enabling and verifying share modules for Company {$companyId}...{$nl}";
    foreach ($capableSlugs as $moduleSlug) {
        $moduleId = itm_module_share_registry_id_by_slug($conn, $moduleSlug);
        if ($moduleId <= 0) {
            verify_enable_fail("Module {$moduleSlug} registry ID not found.");
            continue;
        }

        // Enable module share
        $ok = itm_set_company_module_share($conn, $companyId, $moduleId, 1);
        if (!$ok) {
            verify_enable_fail("Could not enable share for module {$moduleSlug} (ID: {$moduleId}) in company {$companyId}.");
            continue;
        }

        // Verify share is active/enabled
        if (!has_module_share_access($conn, $companyId, $moduleSlug)) {
            verify_enable_fail("Verification failed: module {$moduleSlug} share is not active for company {$companyId}.");
        } else {
            verify_enable_pass("Module {$moduleSlug} share is successfully enabled and verified for company {$companyId}.");
        }
    }
}

echo $nl . ($failures === 0 ? colorText('All share-module enable and verification checks passed.', 'pass') : colorText("Failed with {$failures} issue(s).", 'fail')) . $nl;
exit($failures === 0 ? 0 : 1);
