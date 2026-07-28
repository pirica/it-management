<?php
/**
 * Shared report builder for list_modules_without_share.php.
 */

if (!function_exists('itm_collect_modules_without_share_report')) {
    /**
     * @param array{active_only?:bool} $options
     * @return array{
     *   summary: array{
     *     registry_total:int,
     *     capable_count:int,
     *     without_share_count:int,
     *     active_only:bool
     *   },
     *   capable_slugs: array<int,string>,
     *   without_share: array<int,array{
     *     module_slug:string,
     *     module_name:string,
     *     active:int,
     *     is_system_module:int,
     *     has_module_folder:bool
     *   }>
     * }
     */
    function itm_collect_modules_without_share_report($conn, array $options = [])
    {
        $activeOnly = !empty($options['active_only']);

        if (!function_exists('itm_qr_share_capable_module_slugs')) {
            require_once ROOT_PATH . 'includes/itm_qr_share.php';
        }

        $capableSlugs = itm_qr_share_capable_module_slugs();
        $capableLookup = array_fill_keys($capableSlugs, true);

        if (!function_exists('itm_list_all_modules_registry')) {
            require_once ROOT_PATH . 'includes/itm_company_module_access.php';
        }

        $registryRows = itm_list_all_modules_registry($conn);
        $withoutShare = [];

        foreach ($registryRows as $row) {
            $slug = trim((string)($row['module_slug'] ?? ''));
            if ($slug === '' || isset($capableLookup[$slug])) {
                continue;
            }
            $active = (int)($row['active'] ?? 0);
            if ($activeOnly && $active !== 1) {
                continue;
            }

            $hasFolder = function_exists('itm_script_table_has_module')
                ? itm_script_table_has_module($slug)
                : is_file(ROOT_PATH . 'modules/' . $slug . '/index.php');

            $withoutShare[] = [
                'module_slug' => $slug,
                'module_name' => trim((string)($row['module_name'] ?? '')),
                'active' => $active,
                'is_system_module' => (int)($row['is_system_module'] ?? 0),
                'has_module_folder' => $hasFolder,
            ];
        }

        return [
            'summary' => [
                'registry_total' => count($registryRows),
                'capable_count' => count($capableSlugs),
                'without_share_count' => count($withoutShare),
                'active_only' => $activeOnly,
            ],
            'capable_slugs' => $capableSlugs,
            'without_share' => $withoutShare,
        ];
    }
}
