<?php
require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete on standalone entry files.
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);


$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
if ($company_id < 1 || $id < 1) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $upd = mysqli_prepare(
        $conn,
        'UPDATE hotel_booking_distribution_channels SET active = 0, deleted_by = ?, deleted_at = NOW(), updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
    );
    if ($upd) {
        mysqli_stmt_bind_param($upd, 'iiii', $employee_id, $employee_id, $id, $company_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT channel_code, name FROM hotel_booking_distribution_channels WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}
if (!$row) {
    header('Location: index.php');
    exit;
}

$crud_title = 'Delete Distribution Channel';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_booking_distribution_channels', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title);
?>
<div class="card">
<h1 title="Delete distribution channel">🗑️</h1>
<p>Soft-delete channel <strong><?php echo sanitize($row['name'] ?? ''); ?></strong> (<code><?php echo sanitize($row['channel_code'] ?? ''); ?></code>)? API keys stop working immediately.</p>
<form method="post">
<input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
<a class="btn" href="index.php" title="Back">🔙</a>
<button type="submit" class="btn btn-danger" title="Delete">🗑️</button>
</form>
</div>
<?php itm_hospitality_admin_layout_end(); ?>
