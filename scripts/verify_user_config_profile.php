<?php
/**
 * user-config.php profile field regression checks.
 *
 * Verifies home-company profile UPDATEs and profile photo URL/serve contract
 * (root page must not use module-relative ../../ explorer paths).
 *
 * CLI: php scripts/verify_user_config_profile.php
 * Browser: scripts/verify_user_config_profile.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/employee_profile_photo.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('User Config Profile Verification');

$nl = itm_script_output_nl();
$failures = 0;

function ucp_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function ucp_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    ucp_fail('No database connection.');
    exit(1);
}

$companyId = 1;
$employee = itm_script_test_employee_create($conn, $companyId, [
    'script_slug' => 'ucp-profile',
    'first_name' => 'UCP',
    'last_name' => 'Photo',
]);
if (!is_array($employee) || (int)($employee['id'] ?? 0) <= 0) {
    ucp_fail('Could not create disposable employee.');
    exit(1);
}

$employeeId = (int)$employee['id'];
$homeCompanyId = (int)$employee['company_id'];
$username = (string)$employee['username'];
$wrongCompanyId = $homeCompanyId === 1 ? 2 : 1;
itm_script_test_employee_register_teardown($conn, $employeeId, [], [
    'company_id' => $homeCompanyId,
    'username' => $username,
]);

// --- Photo URL must be app-absolute (works from root user-config.php) ---
$tmpPng = sys_get_temp_dir() . '/itm_ucp_avatar_' . $employeeId . '.png';
$im = imagecreatetruecolor(16, 16);
$bg = imagecolorallocate($im, 20, 120, 200);
imagefill($im, 0, 0, $bg);
imagepng($im, $tmpPng);
imagedestroy($im);

$dir = emp_profile_photo_absolute_dir($homeCompanyId, $username, $employeeId);
if (!itm_ensure_files_storage_directory($dir)) {
    ucp_fail('Could not ensure profile photo directory.');
} else {
    ucp_pass('Profile photo directory ensured under home company.');
}

$filename = emp_profile_photo_filename($username, $employeeId, 'png');
$target = $dir . '/' . $filename;
if (!@copy($tmpPng, $target) || !is_file($target)) {
    ucp_fail('Could not write profile photo file for test.');
} else {
    ucp_pass('Profile photo file written.');
}

$upd = mysqli_prepare($conn, 'UPDATE employees SET photo = ? WHERE id = ? AND company_id = ?');
mysqli_stmt_bind_param($upd, 'sii', $filename, $employeeId, $homeCompanyId);
mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

$employeeRow = mysqli_fetch_assoc(
    mysqli_query($conn, 'SELECT * FROM employees WHERE id = ' . (int)$employeeId . ' LIMIT 1')
);
$url = emp_profile_photo_url($employeeRow);
$servePath = emp_profile_photo_serve_path($employeeRow);

if ($servePath === '' || strpos($servePath, 'Private/') !== 0) {
    ucp_fail('serve_path missing/invalid: ' . $servePath);
} else {
    ucp_pass('serve_path OK: ' . $servePath);
}

if ($url === '' || strpos($url, '../../modules/explorer/file.php') !== false) {
    ucp_fail('photo URL still module-relative or empty: ' . $url);
} elseif (strpos($url, 'modules/explorer/file.php?path=') === false) {
    ucp_fail('photo URL missing explorer proxy: ' . $url);
} else {
    ucp_pass('photo URL is app-absolute explorer proxy.');
}

// --- Home company UPDATE vs tenant-switcher company ---
$email = 'ucp_saved_' . $employeeId . '@example.test';
$phone = '+35190000' . substr((string)$employeeId, -4);
$theme = 'dark';
$ecName = 'EC Name';
$ecRel = 'Spouse';
$ecPhone = '+351911112222';
$birthday = '1990-07-15';
$hideYear = 1;

$sql = 'UPDATE employees SET work_email = ?, mobile_phone = ?, theme = ?, emergency_contact_name = ?, emergency_contact_relationship = ?, emergency_contact_phone = ?, birthday = ?, hide_year = ? WHERE id = ? AND company_id = ?';
$stmtWrong = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $stmtWrong,
    'sssssssiii',
    $email,
    $phone,
    $theme,
    $ecName,
    $ecRel,
    $ecPhone,
    $birthday,
    $hideYear,
    $employeeId,
    $wrongCompanyId
);
mysqli_stmt_execute($stmtWrong);
$wrongAffected = mysqli_stmt_affected_rows($stmtWrong);
mysqli_stmt_close($stmtWrong);
if ($wrongAffected !== 0) {
    ucp_fail('UPDATE with wrong session company_id unexpectedly affected rows: ' . $wrongAffected);
} else {
    ucp_pass('UPDATE with wrong tenant company_id affects 0 rows.');
}

$stmtHome = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param(
    $stmtHome,
    'sssssssiii',
    $email,
    $phone,
    $theme,
    $ecName,
    $ecRel,
    $ecPhone,
    $birthday,
    $hideYear,
    $employeeId,
    $homeCompanyId
);
mysqli_stmt_execute($stmtHome);
$homeAffected = mysqli_stmt_affected_rows($stmtHome);
mysqli_stmt_close($stmtHome);
if ($homeAffected < 1) {
    ucp_fail('UPDATE with home company_id affected ' . $homeAffected . ' rows.');
} else {
    ucp_pass('UPDATE with home company_id persists profile fields.');
}

$after = mysqli_fetch_assoc(
    mysqli_query($conn, 'SELECT work_email, mobile_phone, theme, birthday, hide_year, emergency_contact_name, emergency_contact_relationship, emergency_contact_phone, photo FROM employees WHERE id = ' . (int)$employeeId . ' LIMIT 1')
);

$checks = [
    'work_email' => $email,
    'mobile_phone' => $phone,
    'theme' => 'dark',
    'birthday' => '1990-07-15',
    'hide_year' => '1',
    'emergency_contact_name' => $ecName,
    'emergency_contact_relationship' => $ecRel,
    'emergency_contact_phone' => $ecPhone,
    'photo' => $filename,
];
foreach ($checks as $col => $expected) {
    $got = (string)($after[$col] ?? '');
    if ($got !== (string)$expected) {
        ucp_fail($col . ' expected ' . $expected . ' got ' . $got);
    } else {
        ucp_pass($col . ' round-trip OK.');
    }
}

// --- Static contract: user-config uses home_company_id for profile mutations ---
$userConfig = file_get_contents(ROOT_PATH . 'user-config.php');
if ($userConfig === false) {
    ucp_fail('Could not read user-config.php');
} else {
    if (strpos($userConfig, '$home_company_id') === false) {
        ucp_fail('user-config.php missing $home_company_id for profile self-updates.');
    } else {
        ucp_pass('user-config.php uses $home_company_id.');
    }
    if (!preg_match('/upload_photo[\s\S]{0,800}home_company_id/', $userConfig)) {
        ucp_fail('upload_photo path may still use session company_id.');
    } else {
        ucp_pass('upload_photo uses home_company_id.');
    }
    if (strpos($userConfig, 'itm_sidebar_item_effective_visible') === false) {
        ucp_fail('user-config.php Personalized Sidebar must use itm_sidebar_item_effective_visible().');
    } else {
        ucp_pass('user-config.php Personalized Sidebar uses effective visibility helper.');
    }
    if (strpos($userConfig, 'itm_user_config_save_personalized_sidebar_items') === false) {
        ucp_fail('user-config.php must save sidebar prefs via itm_user_config_save_personalized_sidebar_items().');
    } else {
        ucp_pass('user-config.php uses shared personalized sidebar save helper.');
    }
    if (!preg_match('/update_sidebar[\s\S]{0,400}\$ui_config\s*=\s*itm_get_ui_configuration/', $userConfig)) {
        ucp_fail('user-config.php must reload $ui_config after successful update_sidebar.');
    } else {
        ucp_pass('user-config.php reloads $ui_config after sidebar save.');
    }
}

@unlink($tmpPng);
@unlink($target);
itm_script_test_employee_delete($conn, $employeeId);
itm_script_test_employee_cleanup_storage($homeCompanyId, $username, $employeeId);

if ($failures > 0) {
    echo colorText('FAILED: ' . $failures . ' check(s).', 'fail') . $nl;
    exit(1);
}

echo colorText('All user-config profile checks passed.', 'pass') . $nl;
exit(0);
