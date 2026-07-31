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

itm_require_post_csrf();

$bulkAction = (string)($_POST['bulk_action'] ?? 'single_delete');

if ($bulkAction === 'clear_table') {
    if (itm_hotel_booking_room_photos_hard_delete($conn, (int)$company_id, null)) {
        $_SESSION['crud_success'] = 'Successfully cleared all room photos and their files.';
    } else {
        $_SESSION['crud_error'] = 'Failed to clear photos from database: ' . mysqli_error($conn);
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
        if (itm_hotel_booking_room_photos_hard_delete($conn, (int)$company_id, $idList)) {
            $_SESSION['crud_success'] = 'Successfully deleted selected room photos and their files.';
        } else {
            $_SESSION['crud_error'] = 'Failed to delete photos from database: ' . mysqli_error($conn);
        }
    } else {
        $_SESSION['crud_error'] = 'No records selected for deletion.';
    }
    header('Location: ' . $listUrl);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0) {
    if (itm_hotel_booking_room_photos_hard_delete($conn, (int)$company_id, [$id])) {
        $_SESSION['crud_success'] = 'Successfully deleted room photo and physical file.';
    } else {
        $_SESSION['crud_error'] = 'Failed to delete photo from database: ' . mysqli_error($conn);
    }
}
header('Location: ' . $listUrl);
exit;
