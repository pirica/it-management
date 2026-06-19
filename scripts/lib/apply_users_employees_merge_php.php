#!/usr/bin/env php
<?php
/**
 * Bulk rename users → employees identity across PHP/JS/SQL test mirrors.
 * Run from repo root: php scripts/lib/apply_users_employees_merge_php.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$extensions = ['php', 'js', 'md', 'sql'];

$skipDirs = [
    $root . '/.git',
    $root . '/vendor',
    $root . '/node_modules',
    $root . '/qa-reports',
];

$replacements = [
    'granted_by_user_id' => 'granted_by_employee_id',
    'invited_by_user_id' => 'invited_by_employee_id',
    'created_by_user_id' => 'created_by_employee_id',
    'assigned_to_user_id' => 'assigned_to_employee_id',
    'assigned_by_user_id' => 'assigned_by_employee_id',
    'received_by_user_id' => 'received_by_employee_id',
    'cat_from_user_id' => 'cat_from_employee_id',
    'last_user_manual' => 'last_employee_manual',
    'last_user_id' => 'last_employee_id',
    'user_sidebar_preferences' => 'employee_sidebar_preferences',
    'user_companies' => 'employee_companies',
    'user_roles' => 'employee_roles',
    'User Sidebar Preferences' => 'Employee Sidebar Preferences',
    'User Companies' => 'Employee Companies',
    'User Roles' => 'Employee Roles',
    "modules/users/" => "modules/employees/",
    "'users'" => "'employees'",
    '"users"' => '"employees"',
    '$_SESSION[\'user_id\']' => '$_SESSION[\'employee_id\']',
    '$_SESSION["user_id"]' => '$_SESSION["employee_id"]',
    '@app_user_id' => '@app_employee_id',
    'itm_users_sensitive' => 'itm_employees_auth_sensitive',
    'itm_users_filter_ui_columns' => 'itm_employees_auth_filter_ui_columns',
    'itm_users_is_sensitive_field' => 'itm_employees_auth_is_sensitive_field',
    'itm_script_test_user' => 'itm_script_test_employee',
    'check_script_disposable_users' => 'check_script_disposable_employees',
    'user_dropdown_helpers.php' => 'employee_dropdown_helpers.php',
    'FROM users' => 'FROM employees',
    'JOIN users' => 'JOIN employees',
    'INTO users' => 'INTO employees',
    'UPDATE users' => 'UPDATE employees',
    'DELETE FROM users' => 'DELETE FROM employees',
    '`users`' => '`employees`',
    ' user_id ' => ' employee_id ',
    ' user_id,' => ' employee_id,',
    ' user_id)' => ' employee_id)',
    ' user_id`' => ' employee_id`',
    '`user_id`' => '`employee_id`',
    '($userId' => '($employeeId',
    '$userId' => '$employeeId',
    'itm_require_admin($conn, $_SESSION[\'employee_id\']' => 'itm_require_admin($conn, $_SESSION[\'employee_id\']',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$changed = 0;
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }
    $path = $fileInfo->getPathname();
    foreach ($skipDirs as $skip) {
        if (strpos($path, $skip . DIRECTORY_SEPARATOR) === 0 || $path === $skip) {
            continue 2;
        }
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions, true)) {
        continue;
    }
    if (basename($path) === 'apply_users_employees_merge_php.php') {
        continue;
    }
    if (basename($path) === 'apply_users_employees_merge_sql.py') {
        continue;
    }
    $original = file_get_contents($path);
    if ($original === false) {
        continue;
    }
    $updated = $original;
    foreach ($replacements as $from => $to) {
        $updated = str_replace($from, $to, $updated);
    }
    if ($updated !== $original) {
        file_put_contents($path, $updated);
        $changed++;
    }
}

fwrite(STDOUT, "Updated {$changed} files\n");
