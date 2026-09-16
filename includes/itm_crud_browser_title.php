<?php
/**
 * Browser tab title: prepend resolved module sidebar icon to $crud_title.
 *
 * Why: Canonical <title> uses sanitize($crud_title); emoji comes from runtime resolution, not hardcoded in the tag.
 */

if (!function_exists('itm_crud_apply_module_icon_to_browser_title')) {
    /**
     * @param string $crudTitle Plain module title (e.g. "Departments") before icon prepend
     */
    function itm_crud_apply_module_icon_to_browser_title(
        $conn,
        int $companyId,
        int $employeeId,
        string $moduleSlug,
        string $crudTitle
    ): string {
        $crudTitle = trim($crudTitle);
        $moduleSlug = trim($moduleSlug);
        if ($moduleSlug === '' || !($conn instanceof mysqli)) {
            return $crudTitle;
        }

        if (!function_exists('itm_resolve_module_sidebar_icon')) {
            require_once ROOT_PATH . 'includes/itm_company_module_access.php';
        }
        if (!function_exists('itm_module_access_strip_catalog_label_prefix')) {
            require_once ROOT_PATH . 'includes/itm_company_module_access.php';
        }

        $icon = trim((string) itm_resolve_module_sidebar_icon($conn, $companyId, $employeeId, $moduleSlug));
        if ($icon === '') {
            return $crudTitle;
        }

        if (strpos($crudTitle, $icon) === 0) {
            return trim($crudTitle);
        }

        $clean = $crudTitle;
        if (function_exists('itm_sidebar_label_for_module')) {
            $catalogLabel = trim((string) (itm_sidebar_label_for_module($moduleSlug) ?: ''));
            if ($catalogLabel !== '') {
                $clean = itm_module_access_strip_catalog_label_prefix($catalogLabel);
            }
        }
        if ($clean === '') {
            $clean = itm_module_access_strip_catalog_label_prefix($crudTitle);
        }
        if ($clean === '') {
            $clean = $crudTitle;
        }

        return trim($icon . ' ' . $clean);
    }
}
