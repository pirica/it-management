<?php
/**
 * Public hotel booking bootstrap (MySQLi + ITM config).
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require dirname(__DIR__) . '/config/config.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = '/it-management/booking';
if (!defined('APPURL')) {
    define('APPURL', $scheme . '://' . $host . $basePath);
}

function hb_public_company_id($conn) {
    if (!empty($_SESSION['company_id'])) {
        return (int) $_SESSION['company_id'];
    }
    $cid = 1;
    $row = itm_hotel_booking_settings_row($conn, $cid);
    if ($row && !empty($row['public_portal_enabled'])) {
        return $cid;
    }
    for ($i = 1; $i <= 5; $i++) {
        $row = itm_hotel_booking_settings_row($conn, $i);
        if ($row && !empty($row['public_portal_enabled'])) {
            return $i;
        }
    }
    return 1;
}

function hb_portal_customer_id() {
    return (int) ($_SESSION['hotel_booking_customer_id'] ?? 0);
}

function hb_portal_logged_in() {
    return hb_portal_customer_id() > 0;
}
