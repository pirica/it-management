<?php
/**
 * Helpers for equipment-type façade modules (modules/is_*).
 *
 * Why: regression tests must only remove *_itm_eqdct_* junk folders, never is_switch / is_server / …
 */

/**
 * Canonical modules/is_* directory names (equipment-type façades).
 *
 * @return string[]
 */
function itm_canonical_equipment_is_module_names(): array
{
    return [
        'is_workstation',
        'is_server',
        'is_switch',
        'is_printer',
        'is_pos',
        'is_router',
        'is_port_patch_panel',
        'is_cctv',
        'is_phone',
        'is_firewall',
        'is_access_point',
        'is_other',
    ];
}

/**
 * Equipment type labels used to (re)create canonical modules/is_* wrappers.
 *
 * @return string[]
 */
function itm_canonical_equipment_type_names(): array
{
    return [
        'Workstation',
        'Server',
        'Switch',
        'Printer',
        'POS',
        'Router',
        'Port Patch Panel',
        'CCTV',
        'Phone',
        'Firewall',
        'Access Point',
        'Other',
    ];
}

/**
 * True only for regression-test scaffold folders (never canonical is_* modules).
 */
function itm_is_equipment_regression_test_module_dir(string $moduleDirName): bool
{
    $moduleDirName = trim($moduleDirName);
    if ($moduleDirName === '') {
        return false;
    }

    if (in_array($moduleDirName, itm_canonical_equipment_is_module_names(), true)) {
        return false;
    }

    if (strpos($moduleDirName, '_itm_eqdct_') !== false
        || strpos($moduleDirName, '_itm_edct_') !== false) {
        return true;
    }

    // Orphan wrappers from module_browser_qa_runner inserts/imports on equipment_types
    // (DB row may already be cleared).
    return strpos($moduleDirName, 'is_mbqa_equipment_types_') === 0
        || strpos($moduleDirName, 'is_qa_import_name_') === 0;
}

/**
 * True when an equipment_types.name value was seeded by module_browser_qa_runner (MBQA-{table}-… tag).
 */
function itm_equipment_type_name_is_mbqa_runner_seeded(string $typeName): bool
{
    $typeName = trim($typeName);
    if ($typeName === '') {
        return false;
    }

    return (bool)preg_match('/^mbqa-equipment_types-\d+-\d+-[a-f0-9]{6}$/i', $typeName);
}

/**
 * SQL predicate for runner-seeded equipment_types.name (same shape as itm_mbqa_runner_row_tag()).
 */
function itm_mbqa_equipment_type_name_pattern_sql(): string
{
    return "name REGEXP '^mbqa-equipment_types-[0-9]+-[0-9]+-[a-f0-9]{6}$'";
}

/**
 * SQL predicate for import smoke names from equipment_types QA paths.
 */
function itm_qa_import_equipment_type_name_pattern_sql(): string
{
    return "name REGEXP '^qa_import_name_[0-9]{14}$'";
}

/**
 * Sidebar entry_id for MBQA equipment-type scaffolds (matches itm_equipment_type_sidebar_item_id() output).
 */
function itm_mbqa_equipment_type_scaffold_entry_id_pattern_sql(): string
{
    return "entry_id LIKE 'is_mbqa_equipment_types\\_%'";
}

/**
 * Sidebar entry_id for QA import smoke scaffolds (is_qa_import_name_YYYYMMDDHHMMSS).
 */
function itm_qa_import_equipment_type_scaffold_entry_id_pattern_sql(): string
{
    return "entry_id REGEXP '^is_qa_import_name_[0-9]{14}$'";
}

/**
 * @return int Number of canonical modules verified/created
 */
function itm_ensure_canonical_equipment_type_modules(mysqli $conn): int
{
    if (!function_exists('itm_ensure_equipment_type_module_scaffold')) {
        return 0;
    }

    $ensured = 0;
    foreach (itm_canonical_equipment_type_names() as $typeName) {
        if (itm_ensure_equipment_type_module_scaffold($typeName)) {
            $ensured++;
        }
    }

    return $ensured;
}

/**
 * @return int Number of test-artifact module directories removed
 */
function itm_remove_equipment_regression_test_module_dirs(string $modulesRoot): int
{
    $removed = 0;
    $modulesRoot = rtrim($modulesRoot, '/\\');

    // Why: Windows glob batches can leave orphans when many is_mbqa_* folders exist; repeat until none remain.
    for ($pass = 0; $pass < 20; $pass++) {
        $passRemoved = 0;
        $matches = glob($modulesRoot . '/is_*', GLOB_ONLYDIR) ?: [];

        foreach ($matches as $moduleDir) {
            $base = basename($moduleDir);
            if (!itm_is_equipment_regression_test_module_dir($base)) {
                continue;
            }

            $files = glob($moduleDir . '/*') ?: [];
            foreach ($files as $filePath) {
                if (is_file($filePath)) {
                    @unlink($filePath);
                }
            }
            if (@rmdir($moduleDir)) {
                $passRemoved++;
            }
        }

        $removed += $passRemoved;
        if ($passRemoved === 0) {
            break;
        }
    }

    return $removed;
}

/**
 * List regression-test module folder basenames that cleanup would remove (dry-run preview).
 *
 * @param string $modulesRoot
 * @return array<int, string>
 */
function itm_list_equipment_regression_test_module_dir_names(string $modulesRoot): array
{
    $names = [];
    $modulesRoot = rtrim($modulesRoot, '/\\');
    foreach (glob($modulesRoot . '/is_*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
        $base = basename($moduleDir);
        if (itm_is_equipment_regression_test_module_dir($base)) {
            $names[] = $base;
        }
    }
    sort($names, SORT_STRING);

    return $names;
}

/**
 * Dry-run preview counts for equipment test artifact cleanup (no writes).
 *
 * @param mysqli $conn
 * @param string $modulesRoot
 * @return array{dirs:array<int,string>,companies:int,types:int,sidebar:int}
 */
function itm_preview_equipment_test_module_artifacts_cleanup(mysqli $conn, string $modulesRoot): array
{
    $preview = [
        'dirs' => itm_list_equipment_regression_test_module_dir_names($modulesRoot),
        'companies' => 0,
        'types' => 0,
        'sidebar' => 0,
    ];

    if (!$conn instanceof mysqli) {
        return $preview;
    }

    $companyRes = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM companies WHERE company LIKE 'ITM ClearTable Test %'
            OR company LIKE 'ITM Equipment ClearTable %'
            OR company LIKE 'ITM Debug %'"
    );
    if ($companyRes && ($row = mysqli_fetch_assoc($companyRes))) {
        $preview['companies'] = (int)($row['c'] ?? 0);
    }
    if ($companyRes) {
        mysqli_free_result($companyRes);
    }

    $typesRes = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM equipment_types WHERE name LIKE '%itm_eqdct%' OR name LIKE '%itm_edct%'
            OR " . itm_mbqa_equipment_type_name_pattern_sql() . "
            OR " . itm_qa_import_equipment_type_name_pattern_sql()
    );
    if ($typesRes && ($row = mysqli_fetch_assoc($typesRes))) {
        $preview['types'] = (int)($row['c'] ?? 0);
    }
    if ($typesRes) {
        mysqli_free_result($typesRes);
    }

    $sidebarRes = mysqli_query(
        $conn,
        'SELECT COUNT(*) AS c FROM employee_sidebar_preferences WHERE entry_id LIKE \'%itm_eqdct%\' OR entry_id LIKE \'%itm_edct%\'
            OR ' . itm_mbqa_equipment_type_scaffold_entry_id_pattern_sql() . '
            OR ' . itm_qa_import_equipment_type_scaffold_entry_id_pattern_sql()
    );
    if ($sidebarRes && ($row = mysqli_fetch_assoc($sidebarRes))) {
        $preview['sidebar'] = (int)($row['c'] ?? 0);
    }
    if ($sidebarRes) {
        mysqli_free_result($sidebarRes);
    }

    return $preview;
}

/**
 * Removes equipment regression / MBQA-runner scaffold pollution (DB + modules/is_* orphans).
 *
 * @return array{
 *   ok: bool,
 *   dirs_removed: int,
 *   companies_deleted: int,
 *   types_deleted: int,
 *   sidebar_deleted: int,
 *   canonical_ensured: int,
 *   errors: string[]
 * }
 */
function itm_run_equipment_test_module_artifacts_cleanup(mysqli $conn, string $modulesRoot): array
{
    $result = [
        'ok' => true,
        'dirs_removed' => 0,
        'companies_deleted' => 0,
        'types_deleted' => 0,
        'sidebar_deleted' => 0,
        'canonical_ensured' => 0,
        'errors' => [],
    ];

    if (!$conn instanceof mysqli) {
        $result['ok'] = false;
        $result['errors'][] = 'Database connection is not available.';

        return $result;
    }

    require_once __DIR__ . '/itm_script_test_employee.php';

    mysqli_query($conn, 'SET @app_company_id = 1');
    $auditUser = itm_script_test_employee_create($conn, 1, ['script_slug' => 'equipment-type-modules-cleanup']);
    if (is_array($auditUser)) {
        itm_script_test_employee_set_audit_context($conn, (int)$auditUser['id'], (string)$auditUser['username'], 1);
        itm_script_test_employee_register_teardown($conn, (int)$auditUser['id']);
    } else {
        mysqli_query($conn, 'SET @app_employee_id = NULL');
        mysqli_query($conn, "SET @app_username = 'cli-cleanup'");
    }
    mysqli_query($conn, "SET @app_email = 'cli-cleanup@example.com'");
    mysqli_query($conn, "SET @app_ip_address = '127.0.0.1'");
    mysqli_query($conn, "SET @app_user_agent = 'equipment_test_module_artifacts_cleanup'");

    $result['dirs_removed'] = itm_remove_equipment_regression_test_module_dirs($modulesRoot);

    $companiesRes = mysqli_query(
        $conn,
        "DELETE FROM companies WHERE company LIKE 'ITM ClearTable Test %'
            OR company LIKE 'ITM Equipment ClearTable %'
            OR company LIKE 'ITM Debug %'"
    );
    if ($companiesRes) {
        $result['companies_deleted'] = (int)mysqli_affected_rows($conn);
    } else {
        $result['ok'] = false;
        $result['errors'][] = 'companies cleanup: ' . mysqli_error($conn);
    }

    $typesRes = mysqli_query(
        $conn,
        "DELETE FROM equipment_types WHERE name LIKE '%itm_eqdct%' OR name LIKE '%itm_edct%'
            OR " . itm_mbqa_equipment_type_name_pattern_sql() . "
            OR " . itm_qa_import_equipment_type_name_pattern_sql()
    );
    if ($typesRes) {
        $result['types_deleted'] = (int)mysqli_affected_rows($conn);
    } else {
        $result['ok'] = false;
        $result['errors'][] = 'equipment_types cleanup: ' . mysqli_error($conn);
    }

    $sidebarRes = mysqli_query(
        $conn,
        'DELETE FROM employee_sidebar_preferences WHERE entry_id LIKE \'%itm_eqdct%\' OR entry_id LIKE \'%itm_edct%\'
            OR ' . itm_mbqa_equipment_type_scaffold_entry_id_pattern_sql() . '
            OR ' . itm_qa_import_equipment_type_scaffold_entry_id_pattern_sql()
    );
    if ($sidebarRes) {
        $result['sidebar_deleted'] = (int)mysqli_affected_rows($conn);
    } else {
        $result['ok'] = false;
        $result['errors'][] = 'employee_sidebar_preferences cleanup: ' . mysqli_error($conn);
    }

    $result['canonical_ensured'] = itm_ensure_canonical_equipment_type_modules($conn);

    return $result;
}

/**
 * One-line summary for QA runner completion output (empty when nothing was removed).
 */
function itm_equipment_cleanup_report_summary(array $cleanup): string
{
    if (!$cleanup['ok'] && !empty($cleanup['errors'])) {
        return 'Post-QA equipment cleanup failed: ' . implode('; ', $cleanup['errors']);
    }

    $parts = [];
    if ((int)($cleanup['dirs_removed'] ?? 0) > 0) {
        $parts[] = (int)$cleanup['dirs_removed'] . ' scaffold folder(s)';
    }
    if ((int)($cleanup['types_deleted'] ?? 0) > 0) {
        $parts[] = (int)$cleanup['types_deleted'] . ' equipment_types row(s)';
    }
    if ((int)($cleanup['sidebar_deleted'] ?? 0) > 0) {
        $parts[] = (int)$cleanup['sidebar_deleted'] . ' sidebar pref row(s)';
    }
    if ((int)($cleanup['companies_deleted'] ?? 0) > 0) {
        $parts[] = (int)$cleanup['companies_deleted'] . ' test compan' . ((int)$cleanup['companies_deleted'] === 1 ? 'y' : 'ies');
    }

    if ($parts === []) {
        return '';
    }

    return 'Post-QA equipment cleanup: removed ' . implode(', ', $parts) . '.';
}
