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
    $fpFoldersForPath = fp_fetch_folders($conn, (int)$company_id);
    $fpPathLabel = fp_folder_breadcrumb_label($fpFoldersForPath, $fpFid);
    if ($fpPathLabel !== '') {
        $fpFolderLabel = $fpPathLabel;
    } else {
        $fpRes = mysqli_query($conn, 'SELECT name FROM floor_plan_folders WHERE id=' . $fpFid . ' AND company_id=' . (int)$company_id . ' LIMIT 1');
        $fpRow = ($fpRes) ? mysqli_fetch_assoc($fpRes) : null;
        if ($fpRow) {
            $fpFolderLabel = (string)$fpRow['name'];
        }
    }
}
$fpLocationLabel = '—';
if (!empty($data['it_location_id'])) {
    $fpLocLabel = fp_it_location_label_by_id($conn, (int)$company_id, $data['it_location_id']);
    if ($fpLocLabel !== '') {
        $fpLocationLabel = $fpLocLabel;
    }
}
?>
<h1 title="View floor plan"><?php echo sanitize((string)($data['display_name'] ?? 'Floor Plan')); ?></h1>
<?php
$fpDownloadName = preg_replace('/[^\w.\-]+/u', '_', (string)($data['display_name'] ?? 'floor-plan'));
if ($fpDownloadName === '') {
    $fpDownloadName = 'floor-plan';
}
if ($fpExt !== '' && !preg_match('/\.' . preg_quote($fpExt, '/') . '$/i', $fpDownloadName)) {
    $fpDownloadName .= '.' . $fpExt;
}
?>
<div class="card itm-floor-plan-view-card" data-itm-pdf-preview="1"
     data-itm-pdf-preview-kind="<?php echo sanitize($fpPreviewKind); ?>"
     data-itm-pdf-file-url="<?php echo sanitize($fpUrl); ?>"
     data-itm-pdf-file-name="<?php echo sanitize($fpDownloadName); ?>">
    <div class="itm-floor-plan-view-preview">
        <?php if ($fpPreviewKind === 'pdf'): ?>
            <iframe src="<?php echo sanitize($fpUrl); ?>#view=FitH" title="PDF preview" class="itm-floor-plan-pdf-frame"></iframe>
        <?php elseif ($fpPreviewKind === 'image'): ?>
            <img src="<?php echo sanitize($fpUrl); ?>" alt="<?php echo sanitize((string)($data['display_name'] ?? '')); ?>" class="itm-floor-plan-view-image">
        <?php else: ?>
            <div class="itm-floor-plan-cad-view">
                <p>AutoCAD / CAD file preview is not available in the browser.</p>
            </div>
        <?php endif; ?>
        <p class="itm-floor-plan-file-actions">
            <a href="<?php echo sanitize($fpUrl); ?>" class="btn btn-primary" download="<?php echo sanitize($fpDownloadName); ?>">Download file</a>
            <a href="<?php echo sanitize($fpUrl); ?>" target="_blank" rel="noopener" class="btn">Open in new tab</a>
        </p>
        <?php if ($fpPreviewKind === 'pdf'): ?>
            <p class="itm-dropzone-hint">Signed or protected PDFs may disable Save in the browser viewer; use <strong>Download file</strong> to save a copy.</p>
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
            <tr><th><?php echo sanitize(cr_humanize_field('active')); ?></th><td><?php echo cr_render_cell_value('floor_plans', 'active', $data['active'] ?? ''); ?></td></tr>
            <?php if (function_exists('itm_crud_render_view_audit_meta_rows')): ?>
                <?php itm_crud_render_view_audit_meta_rows($conn, (int)$company_id, $data); ?>
            <?php endif; ?>
        </tbody>
    </table>
    <p class="itm-dropzone-hint" style="margin-top:12px;">To move this file between folders, open the <a href="index.php">Gallery</a> and drag the file card (⠿ handle) onto a folder row.</p>
    <p style="margin-top:16px;">
        <a href="index.php" class="btn" title="Back">🔙</a>
        <a href="edit.php?id=<?php echo $fpPlanId; ?>" class="btn btn-primary" title="Edit">✏️</a>
        <button type="button" class="btn btn-sm" onclick="itmOpenQrShareModal('index.php?ajax_action=create_share_session', <?php echo $fpPlanId; ?>)" title="Share to device">📱</button>
        <button type="button" class="btn btn-sm" onclick="itmOpenWhatsAppShare('index.php?ajax_action=create_share_session', <?php echo $fpPlanId; ?>, null, 'floor plan')" title="Share on WhatsApp"><img src="../../images/whatsapp.svg" alt="" width="16" height="16" style="display:block;"></button>
        <button type="button" class="btn btn-sm" onclick="itmOpenOutlookShare('index.php?ajax_action=create_share_session', <?php echo $fpPlanId; ?>, null, 'floor plan')" title="Share on Outlook">📨</button>
    </p>
</div>
