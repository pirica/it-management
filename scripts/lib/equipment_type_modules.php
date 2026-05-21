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

    return strpos($moduleDirName, '_itm_eqdct_') !== false
        || strpos($moduleDirName, '_itm_edct_') !== false;
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
    $matches = glob(rtrim($modulesRoot, '/\\') . '/is_*', GLOB_ONLYDIR) ?: [];

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
            $removed++;
        }
    }

    return $removed;
}
