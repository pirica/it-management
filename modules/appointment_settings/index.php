<?php
/**
 * Appointment Settings — configuration hub for modules/appointment/.
 */
require_once __DIR__ . '/aps_init.php';

aps_require_permission($conn, 'view');

$flashMessage = trim((string)($_GET['msg'] ?? ''));
$settings = itm_appointment_load_settings($conn, $company_id);
$businessHours = itm_appointment_load_business_hours($conn, $company_id);
$visitReasons = itm_appointment_settings_load_visit_reasons_admin($conn, $company_id);
$appointmentTypes = aps_appointment_types_for_columns(itm_appointment_settings_load_appointment_types_admin($conn, $company_id));

$settingsRows = [];
if ($settings) {
    $settingsRows[] = $settings;
}

$resolvedModuleIcon = itm_resolve_module_sidebar_icon($conn, $company_id, $employee_id, 'appointment_settings');
$cleanModuleTitle = itm_module_access_strip_catalog_label_prefix('Appointment Settings');
$moduleListHeading = trim($resolvedModuleIcon . ' ' . $cleanModuleTitle);

aps_render_page_shell_open($conn, $company_id, $employee_id, $moduleListHeading);
?>
<div class="card" style="margin-bottom:16px;">
    <h1 title="Appointment settings"><?php echo sanitize($moduleListHeading); ?></h1>
    <p>
        <a href="<?php echo sanitize(BASE_URL . 'modules/appointment/'); ?>" class="btn btn-sm" title="Open employee booking">📅</a>
    </p>
    <?php if ($flashMessage !== ''): ?>
        <p><?php echo sanitize($flashMessage); ?></p>
    <?php endif; ?>
</div>

<div class="card appointment-settings-section">
    <div class="appointment-settings-section-header">
        <h2 title="Company settings">⚙️</h2>
    </div>
    <table class="appointment-list-table" data-itm-no-import-excel="1">
        <thead>
        <tr>
            <th>Timezone</th>
            <th>Slot (min)</th>
            <th>Active</th>
            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($settingsRows as $row): ?>
            <tr>
                <td><?php echo sanitize($row['timezone'] ?? ''); ?></td>
                <td><?php echo (int)($row['slot_duration_minutes'] ?? 0); ?></td>
                <td><?php echo (int)($row['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                <?php aps_actions_cell_open(); ?>
                <a class="btn btn-sm" href="view.php?kind=settings&amp;id=<?php echo (int)$row['id']; ?>" title="View">🔎</a>
                <a class="btn btn-sm" href="edit.php?kind=settings&amp;id=<?php echo (int)$row['id']; ?>" title="Edit">✏️</a>
                <form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete company appointment settings?');">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="kind" value="settings">
                    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                </form>
                <?php aps_actions_cell_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($settingsRows)): ?>
            <tr><td colspan="4">No settings row — refresh to create defaults.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card appointment-settings-section">
    <div class="appointment-settings-section-header">
        <h2 title="Business hours">🕐</h2>
        <a href="create.php?kind=business_hour" class="btn btn-sm btn-primary" title="Add">➕</a>
    </div>
    <table class="appointment-list-table" data-itm-no-import-excel="1">
        <thead>
        <tr>
            <th>Day</th>
            <th>Label</th>
            <th>Open</th>
            <th>Close</th>
            <th>Closed</th>
            <?php foreach ($appointmentTypes as $typeCol): ?>
            <th><?php echo sanitize(aps_type_label($typeCol)); ?></th>
            <?php endforeach; ?>
            <th>Active</th>
            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($businessHours as $dow => $hour): ?>
            <tr>
                <td><?php echo (int)$dow; ?></td>
                <td><?php echo sanitize($hour['display_label'] ?? ''); ?></td>
                <td><?php echo sanitize(aps_format_time_input($hour['open_time'] ?? '') ?: '—'); ?></td>
                <td><?php echo sanitize(aps_format_time_input($hour['close_time'] ?? '') ?: '—'); ?></td>
                <td><?php echo (int)($hour['is_closed'] ?? 0) === 1 ? 'Yes' : 'No'; ?></td>
                <?php
                $allowedMap = itm_appointment_hour_allowed_types_map($hour);
                foreach ($appointmentTypes as $typeCol):
                    $typeName = (string)($typeCol['name'] ?? '');
                ?>
                <td><?php echo sanitize(aps_modality_yes_no(!empty($allowedMap[$typeName]) ? 1 : 0)); ?></td>
                <?php endforeach; ?>
                <td><?php echo (int)($hour['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                <?php aps_actions_cell_open(); ?>
                <a class="btn btn-sm" href="view.php?kind=business_hour&amp;id=<?php echo (int)$hour['id']; ?>" title="View">🔎</a>
                <a class="btn btn-sm" href="edit.php?kind=business_hour&amp;id=<?php echo (int)$hour['id']; ?>" title="Edit">✏️</a>
                <form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this business hour row?');">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="kind" value="business_hour">
                    <input type="hidden" name="id" value="<?php echo (int)$hour['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                </form>
                <?php aps_actions_cell_close(); ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card appointment-settings-section">
    <div class="appointment-settings-section-header">
        <h2 title="Visit reasons">📋</h2>
        <a href="create.php?kind=visit_reason" class="btn btn-sm btn-primary" title="Add">➕</a>
    </div>
    <table class="appointment-list-table" data-itm-no-import-excel="1">
        <thead>
        <tr>
            <th>Name</th>
            <th>Sort</th>
            <th>Active</th>
            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($visitReasons as $reason): ?>
            <tr>
                <td><?php echo sanitize($reason['name'] ?? ''); ?></td>
                <td><?php echo (int)($reason['sort_order'] ?? 0); ?></td>
                <td><?php echo (int)($reason['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                <?php aps_actions_cell_open(); ?>
                <a class="btn btn-sm" href="view.php?kind=visit_reason&amp;id=<?php echo (int)$reason['id']; ?>" title="View">🔎</a>
                <a class="btn btn-sm" href="edit.php?kind=visit_reason&amp;id=<?php echo (int)$reason['id']; ?>" title="Edit">✏️</a>
                <form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this visit reason?');">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="kind" value="visit_reason">
                    <input type="hidden" name="id" value="<?php echo (int)$reason['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                </form>
                <?php aps_actions_cell_close(); ?>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($visitReasons)): ?>
            <tr><td colspan="4">No visit reasons yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card appointment-settings-section">
    <div class="appointment-settings-section-header">
        <h2 title="Appointment types">🏷️</h2>
        <a href="create.php?kind=appointment_type" class="btn btn-sm btn-primary" title="Add">➕</a>
    </div>
    <p>Core types in_person and remote are used by the booking API.</p>
    <table class="appointment-list-table" data-itm-no-import-excel="1">
        <thead>
        <tr>
            <th>Name</th>
            <th>Label</th>
            <th>Active</th>
            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($appointmentTypes as $typeRow): ?>
            <?php $coreType = in_array((string)($typeRow['name'] ?? ''), ['in_person', 'remote'], true); ?>
            <tr>
                <td><?php echo sanitize($typeRow['name'] ?? ''); ?></td>
                <td><?php echo sanitize(aps_type_label($typeRow)); ?></td>
                <td><?php echo (int)($typeRow['active'] ?? 0) === 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>'; ?></td>
                <?php aps_actions_cell_open(); ?>
                <a class="btn btn-sm" href="view.php?kind=appointment_type&amp;id=<?php echo (int)$typeRow['id']; ?>" title="View">🔎</a>
                <a class="btn btn-sm" href="edit.php?kind=appointment_type&amp;id=<?php echo (int)$typeRow['id']; ?>" title="Edit">✏️</a>
                <?php if ($coreType): ?>
                <button type="button" class="btn btn-sm btn-danger" title="Cannot delete core appointment types" disabled>🗑️</button>
                <?php else: ?>
                <form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Permanently delete this appointment type? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="kind" value="appointment_type">
                    <input type="hidden" name="id" value="<?php echo (int)$typeRow['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                </form>
                <?php endif; ?>
                <?php aps_actions_cell_close(); ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
aps_render_page_shell_close();
