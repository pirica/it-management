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
    $stmt = mysqli_prepare(
        $conn,
        'SELECT company_id FROM hotel_booking_settings WHERE public_portal_enabled = 1 AND deleted_at IS NULL ORDER BY company_id ASC LIMIT 1'
    );
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if ($row && (int) ($row['company_id'] ?? 0) > 0) {
            return (int) $row['company_id'];
        }
    }
    return 1;
}

/**
 * Why: public_portal_enabled must gate browse/book for that tenant, not only welcome copy.
 */
function hb_company_public_portal_enabled($conn, $companyId) {
    $companyId = (int) $companyId;
    if ($companyId < 1) {
        return false;
    }
    $row = itm_hotel_booking_settings_row($conn, $companyId);
    return $row && !empty($row['public_portal_enabled']);
}

/**
 * Why: Block Step 1–4 / calendar when the hotel's company disabled the public portal.
 *
 * @param array $options json=true for calendar API; redirect override optional
 */
function hb_require_company_public_portal($conn, $companyId, array $options = []) {
    if (hb_company_public_portal_enabled($conn, $companyId)) {
        return;
    }
    if (!empty($options['json'])) {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['error' => 'Public booking portal is disabled for this hotel.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $redirect = isset($options['redirect']) ? (string) $options['redirect'] : (APPURL . '/');
    header('Location: ' . $redirect);
    exit;
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

function hb_portal_checkout_get_room_company_id($conn, $roomId) {
    $roomId = (int) $roomId;
    if ($roomId <= 0) {
        return 0;
    }
    $stmt = mysqli_prepare($conn, 'SELECT company_id FROM hotel_booking_rooms WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $roomId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? (int) $row['company_id'] : 0;
}

function hb_portal_get_booking_company_id($conn, $bookingId) {
    $bookingId = (int) $bookingId;
    if ($bookingId <= 0) {
        return 0;
    }
    $stmt = mysqli_prepare($conn, 'SELECT company_id FROM hotel_bookings WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $bookingId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? (int) $row['company_id'] : 0;
}

function hb_portal_get_booking_company_id_by_confirmation_code($conn, $confirmationCode) {
    $confirmationCode = itm_hotel_booking_normalize_guest_confirmation_code($confirmationCode);
    if ($confirmationCode === '') {
        return 0;
    }
    $stmt = mysqli_prepare(
        $conn,
        'SELECT company_id FROM hotel_bookings WHERE guest_confirmation_code = ? AND deleted_at IS NULL LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 's', $confirmationCode);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return $row ? (int) $row['company_id'] : 0;
}
