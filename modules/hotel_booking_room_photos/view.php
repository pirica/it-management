<?php
require '../../config/config.php';
require_once '../../includes/itm_hotel_booking.php';
require_once '../../includes/itm_crud_audit_fields.php';
itm_require_crud_role_module_permission($conn, 'view', 'hotel_booking_room_photos');

$crud_table = 'hotel_booking_room_photos';
$crud_title = 'Room Photos';
$listUrl = 'index.php';

$errors = [];
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($editId <= 0) {
    $_SESSION['crud_error'] = 'Invalid photo ID.';
    header('Location: ' . $listUrl);
    exit;
}

$where = ' WHERE id = ' . $editId;
if ($company_id > 0) {
    $where .= ' AND company_id = ' . (int)$company_id;
}
if (function_exists('itm_crud_append_not_deleted_predicate')) {
    $where = itm_crud_append_not_deleted_predicate($where);
}
$q = mysqli_query($conn, 'SELECT * FROM hotel_booking_room_photos' . $where . ' LIMIT 1');
$data = ($q && mysqli_num_rows($q) === 1) ? mysqli_fetch_assoc($q) : null;
if (!$data) {
    $_SESSION['crud_error'] = 'Photo record not found.';
    header('Location: ' . $listUrl);
    exit;
}

$photoUrl = itm_hotel_booking_photo_public_url($company_id, 'room', $data['room_id'], $data['stored_filename']);

$roomLabel = '';
$roomQuery = mysqli_query($conn, 'SELECT room_number, name FROM hotel_booking_rooms WHERE id = ' . (int)$data['room_id'] . ' LIMIT 1');
if ($roomQuery && ($roomRow = mysqli_fetch_assoc($roomQuery))) {
    $roomLabel = 'Room ' . $roomRow['room_number'] . ' - ' . $roomRow['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $pageTitle = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), basename(dirname($_SERVER['PHP_SELF'])), (string)($crud_title ?? ''));
    ?>
    <title><?php echo sanitize($pageTitle); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config)); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <h1 title="View">🔎</h1>
            <div class="card">
                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 300px; max-width: 500px;">
                        <div style="overflow: hidden; border-radius: 8px; background-color: #1e1e1e; border: 1px solid #333; width: 100%;">
                            <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo sanitize($data['original_filename']); ?>" style="width: 100%; height: auto; display: block; object-fit: contain;">
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 300px;">
                        <table>
                            <tbody>
                            <tr>
                                <th style="width:200px;">ID</th>
                                <td><?php echo (int)$data['id']; ?></td>
                            </tr>
                            <tr>
                                <th>Room</th>
                                <td><?php echo sanitize($roomLabel); ?></td>
                            </tr>
                            <tr>
                                <th>Original Filename</th>
                                <td><?php echo sanitize($data['original_filename']); ?></td>
                            </tr>
                            <tr>
                                <th>Stored Filename</th>
                                <td><code><?php echo sanitize($data['stored_filename']); ?></code></td>
                            </tr>
                            <tr>
                                <th>Sort Order</th>
                                <td><?php echo (int)$data['sort_order']; ?></td>
                            </tr>
                            <tr>
                                <th>Is Cover</th>
                                <td><?php echo ((int)$data['is_cover'] === 1) ? '✅' : '❌'; ?></td>
                            </tr>
                            <tr>
                                <th>Active</th>
                                <td>
                                    <?php if ((int)$data['active'] === 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Created By</th>
                                <td><?php echo itm_crud_render_audit_cell_value($conn, (int)$company_id, 'created_by', $data['created_by'] ?? ''); ?></td>
                            </tr>
                            <tr>
                                <th>Created At</th>
                                <td><?php echo itm_crud_render_audit_cell_value($conn, (int)$company_id, 'created_at', $data['created_at'] ?? ''); ?></td>
                            </tr>
                            <?php if (!empty($data['updated_by'])): ?>
                                <tr>
                                    <th>Updated By</th>
                                    <td><?php echo itm_crud_render_audit_cell_value($conn, (int)$company_id, 'updated_by', $data['updated_by']); ?></td>
                                </tr>
                                <tr>
                                    <th>Updated At</th>
                                    <td><?php echo itm_crud_render_audit_cell_value($conn, (int)$company_id, 'updated_at', $data['updated_at'] ?? ''); ?></td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <p style="margin-top:20px;">
                            <a href="index.php" class="btn" title="Back">🔙</a>
                            <a class="btn btn-primary" href="edit.php?id=<?php echo (int)$data['id']; ?>" title="Edit">✏️</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
