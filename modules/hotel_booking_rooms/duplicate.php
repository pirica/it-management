<?php
/**
 * Hotel Rooms Module - Duplicate
 *
 * Clones a room for the active company with a new room number and name.
 */

$crud_table = 'hotel_booking_rooms';
$crud_title = 'Hotel Rooms';
?>
<?php
require_once '../../config/config.php';

if (!isset($crud_table) || !preg_match('/^[a-zA-Z0-9_]+$/', $crud_table)) {
    die('Invalid table configuration');
}

$modulePath = dirname($_SERVER['PHP_SELF']);
$listUrl = $modulePath . '/index.php';

function hb_room_duplicate_require_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        echo 'Forbidden: invalid CSRF token.';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

hb_room_duplicate_require_csrf();
itm_require_crud_role_module_permission($conn, 'create', $crud_table);

$sourceId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($sourceId < 1 || (int)$company_id < 1) {
    $_SESSION['crud_error'] = 'Invalid room.';
    header('Location: ' . $listUrl);
    exit;
}

$result = itm_hotel_booking_room_duplicate_record($conn, (int)$company_id, $sourceId);
if (empty($result['ok'])) {
    $_SESSION['crud_error'] = (string)($result['message'] ?? 'Could not duplicate the room.');
    header('Location: ' . $listUrl);
    exit;
}

header('Location: ' . $modulePath . '/edit.php?id=' . (int)$result['new_id']);
exit;
