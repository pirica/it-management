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
        global $conn, $company_id, $ui_config, $app_name, $favicon_url;

        $currentUiConfig = $ui_config ?? [];
        $appName = $app_name ?? itm_ui_config_app_name($currentUiConfig);
        $faviconUrl = $favicon_url ?? null;
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

if (!function_exists('itm_hospitality_bookings_hub_url')) {
    function itm_hospitality_bookings_hub_url()
    {
        return BASE_URL . 'modules/hotel_bookings/index.php';
    }
}

if (!function_exists('itm_hospitality_render_bookings_hub_link')) {
    /**
     * Emoji-only hub link to hotel bookings planning (sibling modules use this instead of Back).
     *
     * @param string $extraClass Button classes (default matches list Create buttons).
     */
    function itm_hospitality_render_bookings_hub_link($extraClass = 'btn btn-primary')
    {
        $class = trim((string) $extraClass);
        if ($class === '') {
            $class = 'btn btn-primary';
        }
        echo '<a class="' . sanitize($class) . '" href="' . sanitize(itm_hospitality_bookings_hub_url()) . '" title="Hotel bookings hub">🏨</a>';
    }
}

if (!function_exists('itm_hospitality_render_list_create_and_hub')) {
    /**
     * Vertical stack: Create (➕) then hotel bookings hub (🏨) — avoids overlap with centered list h1.
     *
     * @param string $buttonClass Shared btn classes for both controls.
     * @param string $createHref Optional create URL (default create.php).
     */
    function itm_hospitality_render_list_create_and_hub($buttonClass = 'btn btn-primary', $createHref = 'create.php')
    {
        $class = trim((string) $buttonClass);
        if ($class === '') {
            $class = 'btn btn-primary';
        }
        $createHref = trim((string) $createHref);
        if ($createHref === '') {
            $createHref = 'create.php';
        }
        echo '<div class="itm-hospitality-list-actions">';
        echo '<a href="' . sanitize($createHref) . '" class="' . sanitize($class) . ' itm-list-new-button" title="Create">➕</a>';
        itm_hospitality_render_bookings_hub_link($class);
        echo '</div>';
    }
}

if (!function_exists('itm_hospitality_admin_layout_end')) {
    /**
     * @param array<int, string> $extraScripts Module-relative or absolute script src paths.
     * @param string $htmlBeforeScripts Optional HTML emitted after layout wrappers close (e.g. body-level modals).
     */
    function itm_hospitality_admin_layout_end(array $extraScripts = [], $htmlBeforeScripts = '')
    {
        echo '</div></div></div>';
        if ($htmlBeforeScripts !== '') {
            echo $htmlBeforeScripts;
        }
        echo '<script src="' . sanitize(BASE_URL . 'js/theme.js') . '"></script>';
        foreach ($extraScripts as $src) {
            $src = trim((string) $src);
            if ($src !== '') {
                echo '<script src="' . sanitize($src) . '"></script>';
            }
        }
        echo '</body></html>';
    }
}
