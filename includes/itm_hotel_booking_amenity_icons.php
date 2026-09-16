<?php
/**
 * Hotel booking amenity SVG icons (booking/images/amenities/) for admin + public portal.
 */

if (!function_exists('itm_hotel_booking_amenity_icons_dir')) {
    function itm_hotel_booking_amenity_icons_dir() {
        return ROOT_PATH . 'booking/images/amenities/';
    }
}

if (!function_exists('itm_hotel_booking_amenity_sanitize_slug')) {
    function itm_hotel_booking_amenity_sanitize_slug($slug) {
        $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $slug));
        if ($slug === '' || !preg_match('/^[a-z][a-z0-9_-]{0,62}$/', $slug)) {
            return 'default';
        }
        return $slug;
    }
}

if (!function_exists('itm_hotel_booking_amenity_icon_slugs')) {
    /**
     * @return string[] sorted slug list (filename without .svg)
     */
    function itm_hotel_booking_amenity_icon_slugs() {
        $dir = itm_hotel_booking_amenity_icons_dir();
        $slugs = [];
        if (!is_dir($dir)) {
            return ['default'];
        }
        foreach (scandir($dir) as $file) {
            if (!preg_match('/\.svg$/i', $file)) {
                continue;
            }
            $slugs[] = substr($file, 0, -4);
        }
        $slugs = array_values(array_unique(array_map('itm_hotel_booking_amenity_sanitize_slug', $slugs)));
        sort($slugs, SORT_STRING);
        if (empty($slugs)) {
            $slugs[] = 'default';
        }
        return $slugs;
    }
}

if (!function_exists('itm_hotel_booking_amenity_icon_public_path')) {
    /** Web path relative to app root (no domain). */
    function itm_hotel_booking_amenity_icon_public_path($slug) {
        $slug = itm_hotel_booking_amenity_sanitize_slug($slug);
        return '/booking/images/amenities/' . rawurlencode($slug) . '.svg';
    }
}

if (!function_exists('itm_hotel_booking_amenity_icon_url')) {
    function itm_hotel_booking_amenity_icon_url($slug) {
        $path = itm_hotel_booking_amenity_icon_public_path($slug);
        if (defined('BASE_URL')) {
            return rtrim((string) BASE_URL, '/') . $path;
        }
        return $path;
    }
}

if (!function_exists('itm_hotel_booking_amenity_icon_booking_url')) {
    /** URL for public booking tree (APPURL when defined). */
    function itm_hotel_booking_amenity_icon_booking_url($slug) {
        $slug = itm_hotel_booking_amenity_sanitize_slug($slug);
        if (defined('APPURL')) {
            return rtrim((string) APPURL, '/') . '/images/amenities/' . rawurlencode($slug) . '.svg';
        }
        return itm_hotel_booking_amenity_icon_url($slug);
    }
}

if (!function_exists('itm_hotel_booking_amenity_icon_markup')) {
    function itm_hotel_booking_amenity_icon_markup($slug, $size = 28) {
        $url = itm_hotel_booking_amenity_icon_url($slug);
        $size = max(16, min(64, (int) $size));
        return '<img class="hb-amenity-icon-img" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" width="' . $size . '" height="' . $size . '" loading="lazy" decoding="async" aria-hidden="true">';
    }
}

if (!function_exists('itm_hotel_booking_amenity_guess_slug')) {
    function itm_hotel_booking_amenity_guess_slug($name) {
        $n = strtolower((string) $name);
        if (strpos($n, 'wifi') !== false) {
            return 'wifi';
        }
        if (strpos($n, 'pool') !== false) {
            return 'pool';
        }
        if (strpos($n, 'fitness') !== false || strpos($n, 'gym') !== false) {
            return 'fitness';
        }
        if (strpos($n, 'spa') !== false) {
            return 'spa';
        }
        if (strpos($n, 'parking') !== false) {
            return 'parking';
        }
        if (strpos($n, 'restaurant') !== false || strpos($n, 'dining') !== false) {
            return 'restaurant';
        }
        return 'default';
    }
}

if (!function_exists('itm_hotel_booking_amenity_resolve_slug')) {
    function itm_hotel_booking_amenity_resolve_slug($name, $iconSlug = '') {
        $iconSlug = trim((string) $iconSlug);
        if ($iconSlug !== '') {
            $slug = itm_hotel_booking_amenity_sanitize_slug($iconSlug);
            $file = itm_hotel_booking_amenity_icons_dir() . $slug . '.svg';
            if (is_file($file)) {
                return $slug;
            }
        }
        return itm_hotel_booking_amenity_guess_slug($name);
    }
}

if (!function_exists('itm_hotel_booking_amenity_icon_save_upload')) {
    /**
     * @return array{ok:bool,slug?:string,error?:string}
     */
    function itm_hotel_booking_amenity_icon_save_upload(array $file, $requestedSlug = '') {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['ok' => false, 'error' => 'No file uploaded.'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > 512000) {
            return ['ok' => false, 'error' => 'SVG must be under 500 KB.'];
        }
        $raw = file_get_contents($file['tmp_name']);
        if ($raw === false || stripos($raw, '<svg') === false) {
            return ['ok' => false, 'error' => 'Upload must be a valid SVG file.'];
        }
        if (preg_match('/<\?php|<script/i', $raw)) {
            return ['ok' => false, 'error' => 'SVG contains disallowed content.'];
        }
        $slug = itm_hotel_booking_amenity_sanitize_slug($requestedSlug !== '' ? $requestedSlug : pathinfo((string) ($file['name'] ?? ''), PATHINFO_FILENAME));
        $dir = itm_hotel_booking_amenity_icons_dir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            return ['ok' => false, 'error' => 'Could not create icons directory.'];
        }
        $dest = $dir . $slug . '.svg';
        if (file_put_contents($dest, $raw) === false) {
            return ['ok' => false, 'error' => 'Could not save SVG.'];
        }
        $emptyIndex = $dir . 'index.html';
        if (!is_file($emptyIndex)) {
            file_put_contents($emptyIndex, '');
        }
        return ['ok' => true, 'slug' => $slug];
    }
}

if (!function_exists('itm_hotel_booking_amenity_icon_delete')) {
    /**
     * @return array{ok:bool,error?:string}
     */
    function itm_hotel_booking_amenity_icon_delete($conn, $slug) {
        $slug = itm_hotel_booking_amenity_sanitize_slug($slug);
        if ($slug === 'default') {
            return ['ok' => false, 'error' => 'The default icon cannot be removed.'];
        }
        if ($conn instanceof mysqli) {
            $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM hotel_booking_amenities WHERE icon_slug = ? AND deleted_at IS NULL');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $slug);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if ($row && (int) ($row['c'] ?? 0) > 0) {
                    return ['ok' => false, 'error' => 'Icon is still used by amenity catalog rows.'];
                }
            }
        }
        $path = itm_hotel_booking_amenity_icons_dir() . $slug . '.svg';
        if (!is_file($path)) {
            return ['ok' => false, 'error' => 'Icon file not found.'];
        }
        if (!unlink($path)) {
            return ['ok' => false, 'error' => 'Could not delete icon file.'];
        }
        return ['ok' => true];
    }
}
