<?php
/**
 * Why: database.sql defines employees columns (e.g. termination_date) that may be absent
 * from the live schema or from employees module screens. Surfaces both gaps in one run.
 *
 * Browser: open scripts/employee_fields_missing.php (login required).
 * CLI: php scripts/employee_fields_missing.php
 */
declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Employees Fields Missing Audit');

$nl = itm_script_output_nl();
$failures = 0;

function efm_fail(string $message): void
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function efm_pass(string $message): void
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

function efm_info(string $message): void
{
    global $nl;
    echo colorText('[INFO] ' . $message, 'info') . $nl;
}

/**
 * @return list<string>
 */
function efm_expected_columns_from_database_sql(): array
{
    $path = ROOT_PATH . 'database.sql';
    if (!is_readable($path)) {
        fwrite(STDERR, "Cannot read database.sql\n");
        exit(2);
    }
    $sql = file_get_contents($path);
    if ($sql === false || !preg_match('/CREATE TABLE `employees`\s*\((.*?)\)\s*ENGINE=/s', $sql, $match)) {
        fwrite(STDERR, "Could not parse employees CREATE TABLE from database.sql\n");
        exit(2);
    }
    $body = $match[1];
    if (!preg_match_all('/^\s*`([^`]+)`/m', $body, $columns)) {
        return [];
    }
    return $columns[1];
}

/**
 * @return list<string>
 */
function efm_live_columns(mysqli $conn): array
{
    $columns = [];
    $res = mysqli_query($conn, 'SHOW COLUMNS FROM employees');
    if (!$res) {
        return [];
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $columns[] = (string)($row['Field'] ?? '');
    }
    return $columns;
}

/**
 * @return array<string, string>
 */
function efm_module_file_map(): array
{
    $base = ROOT_PATH . 'modules/employees/';
    return [
        'create' => $base . 'create.php',
        'edit' => $base . 'edit.php',
        'view' => $base . 'view.php',
        'index' => $base . 'index.php',
        'list_all' => $base . 'list_all.php',
        'includes' => $base . 'includes',
    ];
}

function efm_file_bundle_has_field(string $field, array $paths): bool
{
    foreach ($paths as $path) {
        if (is_dir($path)) {
            foreach (glob($path . '/*.php') ?: [] as $includeFile) {
                $content = file_get_contents($includeFile);
                if ($content !== false && preg_match('/name=["\']' . preg_quote($field, '/') . '["\']/', $content)) {
                    return true;
                }
            }
            continue;
        }
        if (!is_readable($path)) {
            continue;
        }
        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }
        if (preg_match('/name=["\']' . preg_quote($field, '/') . '["\']/', $content)) {
            return true;
        }
    }
    return false;
}

function efm_view_label_map(): array
{
    // Why: view.php renders FK columns with human labels and joined *_name aliases, not raw *_id keys.
    return [
        'employee_position_id' => ['Position Title', 'position_name'],
        'department_id' => ['Department', 'department_name'],
        'office_key_card_department_id' => ['Office Key Card Department', 'office_key_card_department_name'],
        'employment_status_id' => ['Employment Status', 'employment_status_name'],
        'employee_type_id' => ['Employee Type', 'employee_type_name'],
        'workstation_mode_id' => ['Workstation Mode', 'workstation_mode_name'],
        'assignment_type_id' => ['Assignment Type', 'assignment_type_name'],
        'reports_to' => ['Reports To', 'manager_name'],
        'location_id' => ['IT Location', 'location_name'],
        'role_id' => ['Role', 'role_name'],
        'access_level_id' => ['Access Level', 'access_level_name'],
    ];
}

/**
 * @return list<string>
 */
function efm_view_label_candidates(string $field): array
{
    $candidates = [$field, ucwords(str_replace('_', ' ', $field))];
    if (isset(efm_view_label_map()[$field])) {
        $candidates = array_merge($candidates, efm_view_label_map()[$field]);
    }
    return array_values(array_unique($candidates));
}

function efm_view_has_field(string $field, string $viewPath): bool
{
    if (!is_readable($viewPath)) {
        return false;
    }
    $content = file_get_contents($viewPath);
    if ($content === false) {
        return false;
    }
    if ($field === 'photo' && strpos($content, 'emp_profile_photo_url') !== false) {
        return true;
    }
    foreach (efm_view_label_candidates($field) as $candidate) {
        if (strpos($content, "'{$candidate}'") !== false) {
            return true;
        }
        if (preg_match('/\$employee\[[\'"]' . preg_quote($candidate, '/') . '[\'"]\]/', $content)) {
            return true;
        }
    }
    return false;
}

function efm_index_has_field(string $field, string $indexPath): bool
{
    if (!is_readable($indexPath)) {
        return false;
    }
    $content = file_get_contents($indexPath);
    if ($content === false) {
        return false;
    }
    return (bool)preg_match("/['\"]{$field}['\"]/", $content);
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    fwrite(STDERR, "[FAIL] No database connection.\n");
    exit(1);
}

$expected = efm_expected_columns_from_database_sql();
$live = efm_live_columns($conn);
$files = efm_module_file_map();

$metaColumns = ['id', 'company_id', 'created_at', 'updated_at', 'user_id', 'is_hidden'];
$systemAccessColumns = [
    'network_access', 'micros_emc', 'opera_username', 'micros_card', 'pms_id', 'synergy_mms',
    'hu_the_lobby', 'navision', 'onq_ri', 'birchstreet', 'delphi', 'omina', 'vingcard_system',
    'digital_rev', 'office_key_card',
];
$criticalFormFields = [
    'first_name', 'last_name', 'display_name', 'work_email', 'personal_email', 'mobile_phone',
    'external_number', 'dect', 'extension', 'on_contacts', 'on_orgchart', 'external_id', 'username',
    'role_id', 'access_level_id',
    'job_code', 'employee_position_id', 'reports_to', 'department_id', 'office_key_card_department_id',
    'raw_status_code', 'employment_status_id', 'employee_code', 'location_id',
    'request_date', 'requested_by', 'termination_requested_by',
    'start_date', 'employee_type_id', 'termination_date',
    'birthday', 'hide_year', 'photo', 'workstation_mode_id', 'assignment_type_id', 'comments',
];
$optionalFormFields = [
    'duplicate',
];

echo 'Employees schema/UI audit' . $nl;
echo 'Expected columns (database.sql): ' . count($expected) . $nl;
echo 'Live columns (SHOW COLUMNS): ' . count($live) . $nl . $nl;

$schemaMissing = array_values(array_diff($expected, $live));
$schemaExtra = array_values(array_diff($live, $expected));

if ($schemaMissing === []) {
    efm_pass('Live employees table includes every column defined in database.sql');
} else {
    foreach ($schemaMissing as $column) {
        efm_fail("Live DB missing employees.{$column} (present in database.sql)");
    }
}

if ($schemaExtra !== []) {
    foreach ($schemaExtra as $column) {
        efm_info("Live DB has extra employees.{$column} not listed in database.sql");
    }
}

$uiFormPaths = [$files['create'], $files['edit'], $files['includes']];

echo $nl . 'Critical UI coverage (create/edit, view.php, index.php)' . $nl;

foreach ($criticalFormFields as $field) {
    $formOk = efm_file_bundle_has_field($field, $uiFormPaths);
    $viewOk = efm_view_has_field($field, $files['view']);
    $indexOk = efm_index_has_field($field, $files['index']);

    if (!$formOk) {
        efm_fail("employees create/edit missing form field: {$field}");
    }
    if (!$viewOk) {
        efm_fail("employees view.php missing display for: {$field}");
    }
    if (!$indexOk) {
        efm_fail("employees index.php missing list/import reference for: {$field}");
    }
    if ($formOk && $viewOk && $indexOk) {
        efm_pass("employees UI covers {$field}");
    }
}

echo $nl . 'Optional / extended columns (report only)' . $nl;

foreach ($optionalFormFields as $field) {
    if (!in_array($field, $expected, true)) {
        continue;
    }
    $formOk = efm_file_bundle_has_field($field, $uiFormPaths);
    $viewOk = efm_view_has_field($field, $files['view']);
    $indexOk = efm_index_has_field($field, $files['index']);
    if (!$formOk || !$viewOk || !$indexOk) {
        $gaps = [];
        if (!$formOk) {
            $gaps[] = 'create/edit';
        }
        if (!$viewOk) {
            $gaps[] = 'view';
        }
        if (!$indexOk) {
            $gaps[] = 'index';
        }
        efm_info("employees.{$field} not fully wired (" . implode(', ', $gaps) . ' missing)');
    } else {
        efm_pass("employees optional UI covers {$field}");
    }
}

foreach ($systemAccessColumns as $field) {
    if (!in_array($field, $expected, true)) {
        continue;
    }
    if (efm_index_has_field($field, $files['index'])) {
        efm_pass("employees system access column {$field} referenced via employee_system_access join in index.php");
    } else {
        efm_info("employees system access column {$field} is managed via employee_system_access matrix (not a direct form input)");
    }
}

foreach ($metaColumns as $field) {
    if (in_array($field, $expected, true)) {
        efm_info("employees.{$field} is meta/system scope (not required on create/edit forms)");
    }
}

if (is_readable($files['list_all']) && strpos((string)file_get_contents($files['list_all']), "header('Location: index.php')") !== false) {
    efm_pass('employees list_all.php redirects to index.php (list columns inherit from index.php)');
}

echo $nl;
if ($failures > 0) {
    echo colorText("Result: {$failures} failure(s).", 'fail') . $nl;
    exit(1);
}

echo colorText('Result: all checks passed.', 'pass') . $nl;
exit(0);
