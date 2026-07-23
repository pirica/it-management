<?php
/**
 * Sidebar preferences regression: employee_sidebar_preferences save/load and UI contracts.
 *
 * CLI: php scripts/verify_sidebar_preferences.php
 * Browser: scripts/verify_sidebar_preferences.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Sidebar Preferences Verification');

$nl = itm_script_output_nl();
$failures = 0;

function vsp_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function vsp_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    vsp_fail('No database connection.');
    exit(1);
}

$companyId = 1;
$employee = itm_script_test_employee_create($conn, $companyId, [
    'script_slug' => 'verify-sidebar-prefs',
    'first_name' => 'Sidebar',
    'last_name' => 'Prefs',
]);
if (!is_array($employee) || (int)($employee['id'] ?? 0) <= 0) {
    vsp_fail('Could not create disposable employee.');
    exit(1);
}

$employeeId = (int)$employee['id'];
itm_script_test_employee_register_teardown($conn, $employeeId, [], [
    'company_id' => $companyId,
    'username' => (string)$employee['username'],
]);

// --- Static: user-config reloads $ui_config after sidebar save ---
$userConfigSource = (string)file_get_contents(ROOT_PATH . 'user-config.php');
if (strpos($userConfigSource, 'itm_get_ui_configuration($conn, $company_id, $user_id)') === false) {
    vsp_fail('user-config.php must reload $ui_config via itm_get_ui_configuration after render mutations.');
} else {
    vsp_pass('user-config.php reloads $ui_config from itm_get_ui_configuration().');
}

if (strpos($userConfigSource, '$user_config_sidebar_ui = $ui_config') === false) {
    vsp_fail('user-config.php must assign $user_config_sidebar_ui from fresh $ui_config.');
} else {
    vsp_pass('user-config.php binds Personalized Sidebar to fresh $ui_config.');
}

// --- Static: settings SideMenu access helpers ---
$settingsSource = (string)file_get_contents(ROOT_PATH . 'modules/settings/index.php');
foreach ([
    'itm_sidebar_item_passes_access_gate' => 'Settings SideMenu filters by module access.',
    'itm_sidebar_item_effective_visible' => 'Settings SideMenu uses effective visibility for checkboxes.',
    'itm_equipment_type_sidebar_effective_visible' => 'Settings Equipment Type Sidebar uses effective visibility.',
    'itm_sidebar_apply_access_gates_to_visibility' => 'Settings save applies access gates to sidebar visibility.',
    'itm_sidebar_section_effective_visible' => 'Settings SideMenu uses section effective visibility.',
    'itm_sidebar_prepare_layout_config_for_save' => 'Settings save normalizes submenu sections before persist.',
] as $needle => $label) {
    if (strpos($settingsSource, $needle) === false) {
        vsp_fail('modules/settings/index.php missing ' . $needle . '.');
    } else {
        vsp_pass($label);
    }
}

$uiConfigSource = (string)file_get_contents(ROOT_PATH . 'includes/ui_config.php');
if (strpos($uiConfigSource, 'itm_employee_role_sidebar_show_enabled') === false) {
    vsp_fail('includes/ui_config.php missing itm_employee_role_sidebar_show_enabled().');
} else {
    vsp_pass('ui_config.php resolves employee_roles.sidebar_show.');
}

// --- Canonical section placement ---
$normalizedExplorer = itm_normalize_sidebar_submenu_order(['management' => ['explorer']]);
$employeeItems = $normalizedExplorer['employee'] ?? [];
if (!in_array('explorer', $employeeItems, true)) {
    vsp_fail('itm_normalize_sidebar_submenu_order() must place explorer under employee, not persisted section.');
} else {
    vsp_pass('explorer normalizes to employee section.');
}

if (!function_exists('itm_sidebar_sync_section_visibility_from_items')) {
    vsp_fail('itm_sidebar_sync_section_visibility_from_items() unavailable.');
} else {
    $defaults = itm_ui_config_defaults();
    $hideAllConfig = $defaults;
    $hideAllVisibility = $defaults['sidebar_visibility'];
    foreach (itm_sidebar_item_catalog() as $catalogItemId => $catalogItem) {
        if ($catalogItemId === 'dashboard_link') {
            continue;
        }
        $hideAllVisibility[$catalogItemId] = 0;
    }
    $syncedVisibility = itm_sidebar_sync_section_visibility_from_items($hideAllVisibility, $hideAllConfig, $conn, $companyId);
    if ((int)($syncedVisibility['employee'] ?? 1) !== 0) {
        vsp_fail('itm_sidebar_sync_section_visibility_from_items() should hide employee when all children hidden.');
    } else {
        vsp_pass('Section visibility sync hides empty sections.');
    }
}

require_once __DIR__ . '/lib/itm_verify_db_migrations_report.php';
if (function_exists('itm_verify_db_migrations_column_exists')
    && itm_verify_db_migrations_column_exists($conn, 'employee_roles', 'sidebar_show')
    && function_exists('has_module_access')
    && has_module_access($conn, $companyId, 'tickets')) {
    $helpdeskRoleId = 0;
    $roleStmt = mysqli_prepare(
        $conn,
        'SELECT id FROM employee_roles WHERE company_id = ? AND LOWER(name) = ? AND active = 1 LIMIT 1'
    );
    if ($roleStmt) {
        $helpdeskName = 'helpdesk';
        mysqli_stmt_bind_param($roleStmt, 'is', $companyId, $helpdeskName);
        mysqli_stmt_execute($roleStmt);
        mysqli_stmt_bind_result($roleStmt, $helpdeskRoleId);
        mysqli_stmt_fetch($roleStmt);
        mysqli_stmt_close($roleStmt);
    }
    if ($helpdeskRoleId > 0) {
        $roleUpdate = mysqli_prepare($conn, 'UPDATE employees SET role_id = ? WHERE id = ? AND company_id = ? LIMIT 1');
        if ($roleUpdate) {
            mysqli_stmt_bind_param($roleUpdate, 'iii', $helpdeskRoleId, $employeeId, $companyId);
            mysqli_stmt_execute($roleUpdate);
            mysqli_stmt_close($roleUpdate);
        }

        $layoutConfig = itm_get_ui_configuration($conn, $companyId, $employeeId);
        if (!is_array($layoutConfig)) {
            $layoutConfig = itm_ui_config_defaults();
        }
        $layoutConfig['sidebar_visibility']['tickets'] = 0;
        $ticketsItem = itm_sidebar_item_catalog()['tickets'] ?? null;
        if (is_array($ticketsItem)) {
            $ticketsItem['id'] = 'tickets';
            if (itm_sidebar_item_effective_visible($ticketsItem, $layoutConfig, $conn, $companyId, $employeeId)) {
                vsp_pass('sidebar_show=1 keeps RBAC-allowed tickets visible when layout pref hides it.');
            } else {
                vsp_fail('Expected tickets effective visible when employee_roles.sidebar_show=1 and role has can_view.');
            }

            $disableStmt = mysqli_prepare(
                $conn,
                'UPDATE employee_roles SET sidebar_show = 0 WHERE company_id = ? AND id = ? LIMIT 1'
            );
            if ($disableStmt) {
                mysqli_stmt_bind_param($disableStmt, 'ii', $companyId, $helpdeskRoleId);
                mysqli_stmt_execute($disableStmt);
                mysqli_stmt_close($disableStmt);
                itm_employee_role_sidebar_show_enabled($conn, $employeeId, true);
                if (!itm_sidebar_item_effective_visible($ticketsItem, $layoutConfig, $conn, $companyId, $employeeId)) {
                    vsp_pass('sidebar_show=0 respects layout hide prefs for tickets.');
                } else {
                    vsp_fail('Expected tickets hidden when employee_roles.sidebar_show=0.');
                }
                $restoreStmt = mysqli_prepare(
                    $conn,
                    'UPDATE employee_roles SET sidebar_show = 1 WHERE company_id = ? AND id = ? LIMIT 1'
                );
                if ($restoreStmt) {
                    mysqli_stmt_bind_param($restoreStmt, 'ii', $companyId, $helpdeskRoleId);
                    mysqli_stmt_execute($restoreStmt);
                    mysqli_stmt_close($restoreStmt);
                    itm_employee_role_sidebar_show_enabled($conn, $employeeId, true);
                }
            }
        }
    } else {
        vsp_fail('Helpdesk role missing for sidebar_show runtime probe.');
    }
} else {
    vsp_pass('sidebar_show runtime probe skipped (column missing, or tickets module inaccessible).');
}

// --- DB round-trip: wrong section_id reconciles to canonical parent ---
$wrongSectionSql = 'INSERT INTO employee_sidebar_preferences (company_id, employee_id, entry_type, entry_id, section_id, display_order, is_visible, active)
    VALUES (?, ?, \'item\', \'explorer\', \'management\', 0, 1, 1)
    ON DUPLICATE KEY UPDATE section_id = VALUES(section_id), is_visible = 1, active = 1';
$wrongSectionStmt = mysqli_prepare($conn, $wrongSectionSql);
if (!$wrongSectionStmt) {
    vsp_fail('Could not seed explorer row with wrong section_id.');
} else {
    mysqli_stmt_bind_param($wrongSectionStmt, 'ii', $companyId, $employeeId);
    if (!mysqli_stmt_execute($wrongSectionStmt)) {
        vsp_fail('Could not insert explorer row with management section_id.');
    } else {
        mysqli_stmt_close($wrongSectionStmt);
        itm_reconcile_employee_sidebar_preferences_canonical_sections($conn, $companyId, $employeeId);
        $reconciledLayout = itm_get_employee_sidebar_preferences_config($conn, $companyId, $employeeId);
        $reconciledSubmenu = $reconciledLayout['sidebar_submenu_order'] ?? [];
        $reconciledEmployee = $reconciledSubmenu['employee'] ?? [];
        if (!in_array('explorer', $reconciledEmployee, true)) {
            vsp_fail('Reconciled explorer row must load under employee section.');
        } else {
            vsp_pass('DB reconcile moves explorer from management to employee.');
        }
    }
}

// --- DB round-trip: personalized sidebar save ---
$checkedIds = ['settings'];
foreach (['explorer', 'tickets', 'employees'] as $candidateId) {
    if (function_exists('has_module_access') && has_module_access($conn, $companyId, $candidateId)) {
        $checkedIds[] = $candidateId;
        break;
    }
}
$checkedIds = array_values(array_unique($checkedIds));

if (!function_exists('itm_user_config_save_personalized_sidebar_items')) {
    vsp_fail('itm_user_config_save_personalized_sidebar_items() unavailable.');
} elseif (!itm_user_config_save_personalized_sidebar_items($conn, $companyId, $employeeId, $checkedIds)) {
    vsp_fail('itm_user_config_save_personalized_sidebar_items() returned false.');
} else {
    vsp_pass('Personalized sidebar save helper persisted rows.');
}

$layout = itm_get_employee_sidebar_preferences_config($conn, $companyId, $employeeId);
if (!is_array($layout) || !isset($layout['sidebar_visibility'])) {
    vsp_fail('Could not reload employee_sidebar_preferences layout.');
} else {
    $visibility = $layout['sidebar_visibility'];
    foreach ($checkedIds as $checkedId) {
        if ((int)($visibility[$checkedId] ?? 0) !== 1) {
            vsp_fail('Expected is_visible=1 for checked module ' . $checkedId . '.');
        }
    }
    if (function_exists('has_module_access')
        && has_module_access($conn, $companyId, 'departments')
        && !in_array('departments', $checkedIds, true)
        && (int)($visibility['departments'] ?? 1) !== 0) {
        vsp_fail('Unchecked accessible module departments should be is_visible=0 after save.');
    }
    vsp_pass('employee_sidebar_preferences visibility matches checked modules.');
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) AS row_count FROM employee_sidebar_preferences WHERE company_id = ? AND employee_id = ?'
);
if (!$stmt) {
    vsp_fail('Could not prepare employee_sidebar_preferences count.');
} else {
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $employeeId);
    mysqli_stmt_execute($stmt);
    $countRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $rowCount = (int)($countRow['row_count'] ?? 0);
    if ($rowCount <= 0) {
        vsp_fail('employee_sidebar_preferences has no rows after save.');
    } else {
        vsp_pass('employee_sidebar_preferences row count > 0 after save (' . $rowCount . ').');
    }
}

itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    echo colorText('FAILED: ' . $failures . ' check(s).', 'fail') . $nl;
    exit(1);
}

echo colorText('All sidebar preferences checks passed.', 'pass') . $nl;
exit(0);
