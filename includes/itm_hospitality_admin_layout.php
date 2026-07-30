<?php
/**
 * Bespoke hospitality admin pages (hotel bookings hub + settings/rates) — standard shell without footer.php.
 */

if (!function_exists('itm_hospitality_admin_layout_begin')) {
    /**
     * @param string $crudTitle Browser title text (icon-prefixed when callers use itm_crud_apply_module_icon_to_browser_title).
     * @param array<int, string> $extraCssRelative Module-relative CSS paths (e.g. css/hotel-bookings.css).
     */
    function itm_hospitality_admin_layout_begin($crudTitle, array $extraCssRelative = [])
    {
        $currentUiConfig = $GLOBALS['ui_config'] ?? [];
        $appName = $GLOBALS['app_name'] ?? itm_ui_config_app_name($currentUiConfig);
        $faviconUrl = $GLOBALS['favicon_url'] ?? null;
        echo '<!DOCTYPE html><html lang="en"><head>';
        echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>' . sanitize((string) $crudTitle) . ' - ' . sanitize((string) $appName) . '</title>';
        echo itm_render_head_favicon_link($faviconUrl);
        echo '<link rel="stylesheet" href="../../css/styles.css">';
        foreach ($extraCssRelative as $href) {
            $href = trim((string) $href);
            if ($href !== '') {
                echo '<link rel="stylesheet" href="' . sanitize($href) . '">';
            }
        }
        echo '</head><body>';
        echo '<div class="container">';
        include ROOT_PATH . 'includes/sidebar.php';
        echo '<div class="main-content">';
        include ROOT_PATH . 'includes/header.php';
        echo '<div class="content">';
    }
}

if (!function_exists('itm_hospitality_admin_layout_end')) {
    function itm_hospitality_admin_layout_end()
    {
        echo '</div></div></div>';
        echo '<script src="' . sanitize(BASE_URL . 'js/theme.js') . '"></script>';
        echo '</body></html>';
    }
}
