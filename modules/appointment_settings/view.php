<?php
require_once __DIR__ . '/aps_init.php';

aps_require_permission($conn, 'view');

$kind = trim((string)($_GET['kind'] ?? 'settings'));
$id = (int)($_GET['id'] ?? 0);
$row = null;
$pageTitle = 'View';

if ($kind === 'settings' && $id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM appointment_settings WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
} elseif ($kind === 'business_hour' && $id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM appointment_business_hours WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
} elseif ($kind === 'visit_reason' && $id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM appointment_visit_reasons WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
} elseif ($kind === 'appointment_type' && $id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM appointment_type WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
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

$pageTitle = 'View ' . aps_kind_label($kind);
aps_render_page_shell_open($conn, $company_id, $employee_id, $pageTitle);
?>
<div class="card">
    <h1 title="View details">🔎</h1>
    <p><a href="index.php" class="btn btn-sm" title="Back">🔙</a>
        <a href="edit.php?kind=<?php echo sanitize($kind); ?>&amp;id=<?php echo (int)$id; ?>" class="btn btn-sm" title="Edit">✏️</a></p>
    <table class="detail-table">
        <?php if ($kind === 'settings'): ?>
            <tr><th>Timezone</th><td><?php echo sanitize($row['timezone'] ?? ''); ?></td></tr>
            <tr><th>Slot duration (minutes)</th><td><?php echo (int)($row['slot_duration_minutes'] ?? 0); ?></td></tr>
            <tr><th>Bookable window</th><td><?php echo sanitize(aps_format_time_input($row['bookable_start_time'] ?? '') . ' – ' . aps_format_time_input($row['bookable_end_time'] ?? '')); ?></td></tr>
            <tr><th>Check-in buffer (minutes)</th><td><?php echo (int)($row['check_in_end_buffer_minutes'] ?? 0); ?></td></tr>
            <tr><th>Active</th><td><?php echo (int)($row['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td></tr>
        <?php elseif ($kind === 'business_hour'): ?>
            <tr><th>Day of week</th><td><?php echo (int)($row['day_of_week'] ?? 0); ?></td></tr>
            <tr><th>Label</th><td><?php echo sanitize($row['display_label'] ?? ''); ?></td></tr>
            <tr><th>Open</th><td><?php echo sanitize(aps_format_time_input($row['open_time'] ?? '') ?: '—'); ?></td></tr>
            <tr><th>Close</th><td><?php echo sanitize(aps_format_time_input($row['close_time'] ?? '') ?: '—'); ?></td></tr>
            <tr><th>Closed</th><td><?php echo (int)($row['is_closed'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td></tr>
            <tr><th>In Person</th><td><?php echo sanitize(aps_modality_yes_no($row['allows_in_person'] ?? 0)); ?></td></tr>
            <tr><th>Remote</th><td><?php echo sanitize(aps_modality_yes_no($row['allows_remote'] ?? 0)); ?></td></tr>
            <tr><th>Active</th><td><?php echo (int)($row['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td></tr>
        <?php elseif ($kind === 'visit_reason'): ?>
            <tr><th>Name</th><td><?php echo sanitize($row['name'] ?? ''); ?></td></tr>
            <tr><th>Sort order</th><td><?php echo (int)($row['sort_order'] ?? 0); ?></td></tr>
            <tr><th>Active</th><td><?php echo (int)($row['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td></tr>
        <?php elseif ($kind === 'appointment_type'): ?>
            <tr><th>Name</th><td><?php echo sanitize($row['name'] ?? ''); ?></td></tr>
            <tr><th>Label</th><td><?php echo sanitize(aps_type_label($row['name'] ?? '')); ?></td></tr>
            <tr><th>Active</th><td><?php echo (int)($row['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td></tr>
        <?php endif; ?>
    </table>
</div>
<?php
aps_render_page_shell_close();
