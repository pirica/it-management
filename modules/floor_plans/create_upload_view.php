<?php
/**
 * Upload form for new floor plan files.
 */
$fpFolders = fp_fetch_folders($conn, (int)$company_id);
?>
<h1 title="Upload floor plans">➕</h1>
<p><a href="index.php" class="btn btn-sm" title="Back">🔙</a></p>
<form method="POST" action="index.php" enctype="multipart/form-data" class="form-grid itm-floor-plan-upload-form" id="floorPlanCreateForm" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
    <input type="hidden" name="fp_action" value="upload_files">
    <div class="form-group">
        <label for="uploadFolder">Folder</label>
        <select name="folder_id" id="uploadFolder">
            <option value="">— Unfiled —</option>
            <?php echo fp_render_folder_select_options($fpFolders, null); ?>
        </select>
    </div>
    <div class="form-group">
        <label for="uploadItLocationCreate"><?php echo sanitize(fp_it_location_link_label_optional()); ?></label>
        <?php echo fp_render_it_location_select($conn, (int)$company_id, 'it_location_id', 'uploadItLocationCreate', ''); ?>
    </div>
    <div class="form-group">
        <label for="uploadFiles">Files (images, PDF, or AutoCAD)</label>
        <div id="floorPlanUploadTarget" class="itm-photo-upload-target" role="button" tabindex="0" aria-label="Upload floor plans">
            <p class="itm-dropzone-hint">Drag and drop images, PDF, or AutoCAD files here, or click to browse.</p>
            <input type="file" name="gallery_files[]" id="uploadFiles" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.dwg,.dxf,.dwf,.dws,image/*,application/pdf" multiple required>
        </div>
    </div>
    <div class="form-group">
        <label for="uploadTagsCreate">Tags (comma-separated)</label>
        <input type="text" name="upload_tags" id="uploadTagsCreate" placeholder="Ground Floor, Building A">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary" title="Save">💾</button>
        <a href="index.php" class="btn" title="Cancel">🔙</a>
    </div>
</form>

<script src="../../js/itm-upload-helper.js"></script>
<script>
(function() {
    if (typeof itmUploadHelper !== 'undefined') {
        itmUploadHelper.setupById("floorPlanUploadTarget", "uploadFiles");
    }
})();
</script>
