<?php
/**
 * Shared report builder for list_modules_not_on_sidebar.php.
 */

if (!function_exists('itm_read_module_crud_table_for_sidebar_audit')) {
    /**
     * @return string|null
     */
    function itm_read_module_crud_table_for_sidebar_audit(string $indexPath): ?string
    {
        if (!is_file($indexPath)) {
            return null;
        }
        $lines = @file($indexPath);
        if (!is_array($lines)) {
            return null;
        }
        foreach ($lines as $lineText) {
            if (preg_match('/\$crud_table\s*=\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $lineText, $matches)) {
                return (string) $matches[1];
            }
        }

        return null;
    }
}

if (!function_exists('itm_collect_modules_not_on_sidebar_report')) {
    /**
     * Compare module folders, live sidebar discovery, and modules_registry rows.
     *
     * @return array{
     *   summary: array{
     *     modules_with_index:int,
     *     sidebar_match_dir_count:int,
     *     sidebar_catalog_count:int,
     *     modules_not_on_sidebar:int,
     *     sidebar_missing_module:int,
     *     registry_without_module:int,
     *     registry_without_module_policy:int,
     *     registry_without_module_unexpected:int
     *   },
     *   policy_hidden_module_ids: array<int,string>,
     *   modules_not_on_sidebar: array<int,array{module:string,crud_table:?string,reason:string}>,
     *   sidebar_missing_module: array<int,array{match_dir:string,sidebar_id:string,label:string,reason:string}>,
     *   registry_without_module: array<int,array{module_slug:string,module_name:string,reason:string}>
     * }
     */
    function itm_collect_modules_not_on_sidebar_report($conn): array
    {
        if ($conn instanceof mysqli && function_exists('itm_sidebar_structure')) {
            itm_sidebar_structure($conn, true);
        }

        $modulesRoot = ROOT_PATH . 'modules/';
        $policyHidden = array_fill_keys(itm_sidebar_excluded_module_ids(), true);

        $sidebarMatchDirs = [];
        $sidebarCatalogCount = 0;
        if (function_exists('itm_sidebar_item_catalog')) {
            foreach (itm_sidebar_item_catalog() as $itemId => $item) {
                $sidebarCatalogCount++;
                $matchDir = trim((string) ($item['match_dir'] ?? ''));
                if ($matchDir === '') {
                    continue;
                }
                $sidebarMatchDirs[$matchDir] = [
                    'sidebar_id' => (string) $itemId,
                    'label' => (string) ($item['label'] ?? ''),
                ];
            }
        }

        $modulesNotOnSidebar = [];
        $modulesWithIndex = 0;
        $entries = scandir($modulesRoot) ?: [];
        sort($entries, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($entries as $moduleName) {
            if ($moduleName === '.' || $moduleName === '..') {
                continue;
            }
            if (!is_file($modulesRoot . $moduleName . '/index.php')) {
                continue;
            }
            $modulesWithIndex++;

            if (isset($sidebarMatchDirs[$moduleName])) {
                continue;
            }

            $reason = 'not in live sidebar (match_dir)';
            if (isset($policyHidden[$moduleName])) {
                $reason = 'hidden by policy (internal/support module)';
            }

            $modulesNotOnSidebar[] = [
                'module' => $moduleName,
                'crud_table' => itm_read_module_crud_table_for_sidebar_audit($modulesRoot . $moduleName . '/index.php'),
                'reason' => $reason,
            ];
        }

        $sidebarMissingModule = [];
        foreach ($sidebarMatchDirs as $matchDir => $meta) {
            if (is_file($modulesRoot . $matchDir . '/index.php')) {
                continue;
            }

            $reason = 'sidebar match_dir has no modules/' . $matchDir . '/index.php';
            if (isset($policyHidden[$matchDir])) {
                $reason = 'policy-hidden registry/sidebar entry without module folder';
            }

            $sidebarMissingModule[] = [
                'match_dir' => $matchDir,
                'sidebar_id' => (string) ($meta['sidebar_id'] ?? ''),
                'label' => (string) ($meta['label'] ?? ''),
                'reason' => $reason,
            ];
        }

        $registryWithoutModule = [];
        $registryPolicy = 0;
        $registryUnexpected = 0;
        if ($conn instanceof mysqli && function_exists('itm_list_all_modules_registry')) {
            foreach (itm_list_all_modules_registry($conn) as $registryRow) {
                if ((int) ($registryRow['active'] ?? 0) !== 1) {
                    continue;
                }
                $slug = trim((string) ($registryRow['module_slug'] ?? ''));
                if ($slug === '' || is_file($modulesRoot . $slug . '/index.php')) {
                    continue;
                }

                $reason = 'active registry row without module folder';
                if (isset($policyHidden[$slug])) {
                    $reason = 'policy-hidden internal registry row (no module folder expected)';
                    $registryPolicy++;
                } else {
                    $registryUnexpected++;
                }

                $registryWithoutModule[] = [
                    'module_slug' => $slug,
                    'module_name' => trim((string) ($registryRow['module_name'] ?? '')),
                    'reason' => $reason,
                ];
            }
        }

        usort($registryWithoutModule, static function (array $a, array $b): int {
            return strcasecmp((string) $a['module_slug'], (string) $b['module_slug']);
        });

        return [
            'summary' => [
                'modules_with_index' => $modulesWithIndex,
                'sidebar_match_dir_count' => count($sidebarMatchDirs),
                'sidebar_catalog_count' => $sidebarCatalogCount,
                'modules_not_on_sidebar' => count($modulesNotOnSidebar),
                'sidebar_missing_module' => count($sidebarMissingModule),
                'registry_without_module' => count($registryWithoutModule),
                'registry_without_module_policy' => $registryPolicy,
                'registry_without_module_unexpected' => $registryUnexpected,
            ],
            'policy_hidden_module_ids' => itm_sidebar_excluded_module_ids(),
            'modules_not_on_sidebar' => $modulesNotOnSidebar,
            'sidebar_missing_module' => $sidebarMissingModule,
            'registry_without_module' => $registryWithoutModule,
        ];
    }
}
