<?php
/**
 * IT Locations detail: floor plans linked via floor_plans.it_location_id (nullable FK).
 */
require_once ROOT_PATH . 'includes/floor_plans_link_helpers.php';

require_once ROOT_PATH . 'includes/itm_script_entry_guard.php';
if (itm_skip_view_partial_unless_context(true, __FILE__)) {
    return;
}

$itmLocationIdForFloorPlans = (int)($data['id'] ?? 0);
$itmLinkedFloorPlans = array();
if ($itmLocationIdForFloorPlans > 0 && isset($conn) && $conn instanceof mysqli) {
    $itmLinkedFloorPlans = itm_fetch_floor_plans_for_it_location($conn, (int)$company_id, $itmLocationIdForFloorPlans);
}
$itmFloorPlansModuleUrl = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/') . '/modules/floor_plans/';
?>
<div class="card" style="margin-top:16px;">
    <h2 style="margin-top:0;">Link to Floor Plans</h2>
    <p style="opacity:0.85;margin-top:0;">Floor plan files optionally linked to this IT location (<code>floor_plans.it_location_id</code>).</p>
    <?php if (!itm_floor_plans_schema_ready($conn)): ?>
        <p class="alert alert-error">Floor Plans tables are not installed. Apply the Floor Plans section from <code>db/</code> split bundle.</p>
    <?php elseif (empty($itmLinkedFloorPlans)): ?>
        <p>No floor plans are linked to this location. Link files from <a href="<?php echo sanitize($itmFloorPlansModuleUrl); ?>">Floor Plans</a> using <strong>Link to IT Location</strong> on upload or edit.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Folder</th>
                        <th>Type</th>
                        <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itmLinkedFloorPlans as $itmFpRow): ?>
                        <?php
                        $itmFpId = (int)($itmFpRow['id'] ?? 0);
                        $itmFpName = (string)($itmFpRow['display_name'] ?? '');
                        $itmFpFolder = trim((string)($itmFpRow['folder_name'] ?? ''));
                        $itmFpExt = strtolower((string)($itmFpRow['file_ext'] ?? ''));
                        ?>
                        <tr>
                            <td><?php echo sanitize($itmFpName); ?></td>
                            <td><?php echo $itmFpFolder !== '' ? sanitize($itmFpFolder) : '— Unfiled —'; ?></td>
                            <td><?php echo sanitize(strtoupper($itmFpExt !== '' ? $itmFpExt : 'file')); ?></td>
                            <td class="itm-actions-cell" data-itm-actions-origin="1">
                                <div class="itm-actions-wrap">
                                    <a class="btn btn-sm" href="<?php echo sanitize($itmFloorPlansModuleUrl . 'view.php?id=' . $itmFpId); ?>">🔎</a>
                                    <a class="btn btn-sm" href="<?php echo sanitize($itmFloorPlansModuleUrl . 'edit.php?id=' . $itmFpId); ?>">✏️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
