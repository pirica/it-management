<?php
/**
 * Public hotel booking bootstrap (MySQLi + ITM config).
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!defined('ITM_HOTEL_BOOKING_PUBLIC_PORTAL')) {
    define('ITM_HOTEL_BOOKING_PUBLIC_PORTAL', true);
}
require dirname(__DIR__) . '/config/config.php';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (!defined('APPURL')) {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $bookingPos = strpos($scriptName, '/booking/');
    if ($bookingPos !== false) {
        $basePath = substr($scriptName, 0, $bookingPos + strlen('/booking'));
    } else {
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if (substr($basePath, -strlen('/booking')) !== '/booking') {
            $basePath .= '/booking';
        }
    }
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

/**
 * Load one active hotel by primary key (any tenant — public index lists all companies).
 */
function hb_load_active_hotel_row($conn, $hotelId) {
    $hotelId = (int) $hotelId;
    if ($hotelId < 1) {
        return null;
    }
    $stmt = mysqli_prepare($conn, 'SELECT * FROM hotel_booking_hotels WHERE id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1');
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $hotelId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}
