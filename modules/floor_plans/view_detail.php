<?php
/**
 * Floor plan preview (image, PDF, or CAD download).
 */
$fpPlanId = (int)($data['id'] ?? 0);
$fpUrl = fp_public_url((int)$company_id, (string)($data['stored_filename'] ?? ''));
$fpMime = (string)($data['mime_type'] ?? '');
$fpExt = (string)($data['file_ext'] ?? '');
$fpPreviewKind = fp_resolve_preview_kind($fpMime, $fpExt);
$fpTags = fp_get_tags_for_plan($conn, $fpPlanId, (int)$company_id);
$fpFolderLabel = '—';
if (!empty($data['folder_id'])) {
    $fpFid = (int)$data['folder_id'];
    $fpRes = mysqli_query($conn, 'SELECT name FROM floor_plan_folders WHERE id=' . $fpFid . ' AND company_id=' . (int)$company_id . ' LIMIT 1');
    $fpRow = ($fpRes) ? mysqli_fetch_assoc($fpRes) : null;
    if ($fpRow) {
        $fpFolderLabel = (string)$fpRow['name'];
    }
}
$fpLocationLabel = '—';
if (!empty($data['it_location_id'])) {
    $fpLocLabel = fp_it_location_label_by_id($conn, (int)$company_id, $data['it_location_id']);
    if ($fpLocLabel !== '') {
        $fpLocationLabel = $fpLocLabel;
    }
}
$fpCreatedByLabel = '';
if (!empty($data['created_by_user_id'])) {
    $fpCreatedByLabel = cr_user_label_by_id($conn, (int)$company_id, $data['created_by_user_id']);
}
?>
<h1><?php echo sanitize((string)($data['display_name'] ?? 'Floor Plan')); ?></h1>
<div class="card itm-floor-plan-view-card">
    <div class="itm-floor-plan-view-preview">
        <?php if ($fpPreviewKind === 'pdf'): ?>
            <iframe src="<?php echo sanitize($fpUrl); ?>#view=FitH" title="PDF preview" class="itm-floor-plan-pdf-frame"></iframe>
            <p><a href="<?php echo sanitize($fpUrl); ?>" target="_blank" rel="noopener">Open PDF in new tab</a></p>
        <?php elseif ($fpPreviewKind === 'image'): ?>
            <img src="<?php echo sanitize($fpUrl); ?>" alt="<?php echo sanitize((string)($data['display_name'] ?? '')); ?>" class="itm-floor-plan-view-image">
        <?php else: ?>
            <div class="itm-floor-plan-cad-view">
                <p>AutoCAD / CAD file preview is not available in the browser.</p>
                <p><a href="<?php echo sanitize($fpUrl); ?>" class="btn btn-primary" download>Download <?php echo sanitize(strtoupper($fpExt)); ?> file</a></p>
            </div>
        <?php endif; ?>
    </div>
    <table style="margin-top:16px;">
        <tbody>
            <tr><th style="width:200px;">Folder</th><td><?php echo sanitize($fpFolderLabel); ?></td></tr>
            <tr><th><?php echo sanitize(fp_it_location_link_label()); ?></th><td><?php echo sanitize($fpLocationLabel); ?></td></tr>
            <tr><th>Tags</th><td>
                <?php if (empty($fpTags)): ?>—<?php else: ?>
                    <?php foreach ($fpTags as $fpTag): ?>
                        <span class="badge badge-success" style="margin-right:6px;"><?php echo sanitize((string)$fpTag['name']); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </td></tr>
            <tr><th>Type</th><td><?php echo sanitize($fpMime); ?> (<?php echo sanitize(strtoupper($fpExt)); ?>)</td></tr>
            <tr><th>Size</th><td><?php echo sanitize(fp_format_file_size((int)($data['file_size'] ?? 0))); ?></td></tr>
            <?php if ($fpCreatedByLabel !== ''): ?>
                <tr><th>Uploaded by</th><td><?php echo sanitize($fpCreatedByLabel); ?></td></tr>
            <?php endif; ?>
            <tr><th>Active</th><td><?php echo ((int)($data['active'] ?? 0) === 1) ? '✅' : '❌'; ?></td></tr>
            <tr><th>Created</th><td><?php echo sanitize((string)($data['created_at'] ?? '—')); ?></td></tr>
        </tbody>
    </table>
    <p style="margin-top:16px;">
        <a href="index.php" class="btn">Gallery</a>
        <a href="edit.php?id=<?php echo $fpPlanId; ?>" class="btn btn-primary">Edit</a>
    </p>
</div>
