<?php
require '../../config/config.php';
require_once '../../includes/itm_hotel_booking.php';
itm_require_crud_role_module_permission($conn, 'delete', 'hotel_booking_room_photos');

$crud_table = 'hotel_booking_room_photos';
$listUrl = 'index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    die('Method not allowed.');
}

// CSRF validation
$token = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
    http_response_code(403);
    die('Forbidden: invalid CSRF token.');
}

$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');
$scope = 'room';

function delete_physical_photo_file($companyId, $roomId, $storedFilename) {
    $relDir = itm_hotel_booking_photo_storage_dir($companyId, 'room', $roomId);
    $absDir = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
    $filePath = $absDir . DIRECTORY_SEPARATOR . $storedFilename;
    if (is_file($filePath)) {
        @unlink($filePath);
    }
}

if ($bulkAction === 'clear_table') {
    // 1. Fetch all records to delete their physical files
    $sql = 'SELECT id, room_id, stored_filename FROM hotel_booking_room_photos WHERE company_id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            delete_physical_photo_file($company_id, $row['room_id'], $row['stored_filename']);
        }
        mysqli_stmt_close($stmt);
    }

    // 2. Perform HARD DELETE on database
    $deleteSql = 'DELETE FROM hotel_booking_room_photos WHERE company_id = ?';
    $stmt = mysqli_prepare($conn, $deleteSql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        if (!mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_error'] = 'Failed to clear photos from database: ' . mysqli_error($conn);
        } else {
            $_SESSION['crud_success'] = 'Successfully cleared all room photos and their files.';
        }
        mysqli_stmt_close($stmt);
    }
    header('Location: ' . $listUrl);
    exit;
}

if ($bulkAction === 'bulk_delete') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $idList = [];
    foreach ($ids as $rawId) {
        $id = (int)$rawId;
        if ($id > 0) {
            $idList[] = $id;
        }
    }

    if (!empty($idList)) {
        $placeholders = implode(',', array_fill(0, count($idList), '?'));

        // 1. Fetch selected records to delete physical files
        $sql = "SELECT id, room_id, stored_filename FROM hotel_booking_room_photos WHERE company_id = ? AND id IN ({$placeholders})";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $types = 'i' . str_repeat('i', count($idList));
            $params = array_merge([$company_id], $idList);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                delete_physical_photo_file($company_id, $row['room_id'], $row['stored_filename']);
            }
            mysqli_stmt_close($stmt);
        }

        // 2. Perform HARD DELETE on database
        $deleteSql = "DELETE FROM hotel_booking_room_photos WHERE company_id = ? AND id IN ({$placeholders})";
        $stmt = mysqli_prepare($conn, $deleteSql);
        if ($stmt) {
            $types = 'i' . str_repeat('i', count($idList));
            $params = array_merge([$company_id], $idList);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            if (!mysqli_stmt_execute($stmt)) {
                $_SESSION['crud_error'] = 'Failed to delete photos from database: ' . mysqli_error($conn);
            } else {
                $_SESSION['crud_success'] = 'Successfully deleted selected room photos and their files.';
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $_SESSION['crud_error'] = 'No records selected for deletion.';
    }
    header('Location: ' . $listUrl);
    exit;
}

// Single delete
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0) {
    // 1. Fetch the record to delete its physical file
    $sql = 'SELECT id, room_id, stored_filename FROM hotel_booking_room_photos WHERE id = ? AND company_id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            delete_physical_photo_file($company_id, $row['room_id'], $row['stored_filename']);
        }
        mysqli_stmt_close($stmt);
    }

    // 2. Perform HARD DELETE on database
    $deleteSql = 'DELETE FROM hotel_booking_room_photos WHERE id = ? AND company_id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $deleteSql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        if (!mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_error'] = 'Failed to delete photo from database: ' . mysqli_error($conn);
        } else {
            $_SESSION['crud_success'] = 'Successfully deleted room photo and physical file.';
        }
        mysqli_stmt_close($stmt);
    }
}
header('Location: ' . $listUrl);
exit;
