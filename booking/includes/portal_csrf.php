<?php
/**
 * Why: Legacy booking admin-panel PDO entry points need ITM CSRF helpers for smoke CSRF coverage.
 */
if (!function_exists('itm_require_post_csrf')) {
    if (!defined('ITM_HOTEL_BOOKING_PUBLIC_PORTAL')) {
        define('ITM_HOTEL_BOOKING_PUBLIC_PORTAL', true);
    }
    require_once dirname(__DIR__, 2) . '/config/config.php';
}
