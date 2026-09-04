<?php
require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete on standalone entry files.
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);


$company_id = (int) ($_SESSION['company_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method not allowed.';
    exit;
}

itm_require_post_csrf();
$id = (int) ($_POST['id'] ?? 0);
$hotelId = (int) ($_POST['hotel_id'] ?? 0);

if ($id > 0) {
    itm_hotel_booking_portal_rate_plan_hard_delete($conn, $company_id, $id);
}

$redirect = 'index.php';
if ($hotelId > 0) {
    $redirect .= '?hotel_id=' . $hotelId;
}
header('Location: ' . $redirect);
exit;
