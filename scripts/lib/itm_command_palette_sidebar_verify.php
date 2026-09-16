<?php
/**
 * Shared command-palette sidebar slug regression helpers.
 *
 * Why: verify_command_palette_sidebar_slugs.php and verify_command_palette_search.php
 * must share one contract for "every live sidebar module slug is palette-searchable".
 */

if (!function_exists('itm_command_palette_sidebar_verify_collect_misses')) {
    /**
     * Compare live sidebar module slugs against palette module navigation.
     *
     * @return array{sidebar_slugs: string[], nav_misses: string[], palette_misses: string[]}
     */
    function itm_command_palette_sidebar_verify_collect_misses($conn, $companyId, $employeeId)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $result = [
            'sidebar_slugs' => [],
            'nav_misses' => [],
            'palette_misses' => [],
        ];

        if (!($conn instanceof mysqli) || $companyId <= 0 || $employeeId <= 0) {
            return $result;
        }

        if (!function_exists('itm_command_palette_sidebar_visible_module_slugs')) {
            require_once dirname(__DIR__, 2) . '/includes/itm_command_palette_search.php';
        }

        $sidebarSlugs = itm_command_palette_sidebar_visible_module_slugs($conn, $companyId, $employeeId);
        $result['sidebar_slugs'] = $sidebarSlugs;

        foreach ($sidebarSlugs as $moduleSlug) {
            $navResults = itm_command_palette_search_module_navigation($conn, $companyId, $employeeId, $moduleSlug, 25);
            $navHit = false;
            foreach ($navResults as $navRow) {
                if (($navRow['module_slug'] ?? '') === $moduleSlug
                    && strpos((string)($navRow['url'] ?? ''), 'modules/' . $moduleSlug . '/index.php') !== false) {
                    $navHit = true;
                    break;
                }
            }
            if (!$navHit) {
                $result['nav_misses'][] = $moduleSlug;
            }

            $palettePayload = itm_command_palette_search($conn, $companyId, $employeeId, $moduleSlug, 25);
            $paletteHit = false;
            foreach ($palettePayload['groups'] ?? [] as $group) {
                if (($group['module_slug'] ?? '') !== 'modules') {
                    continue;
                }
                foreach ($group['results'] ?? [] as $paletteRow) {
                    if (($paletteRow['module_slug'] ?? '') === $moduleSlug) {
                        $paletteHit = true;
                        break 2;
                    }
                }
            }
            if (!$paletteHit) {
                $result['palette_misses'][] = $moduleSlug;
            }
        }

        return $result;
    }
}
