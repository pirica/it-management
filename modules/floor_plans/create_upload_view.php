<?php
/**
 * Upload form for new floor plan files.
 */
$fpFolders = fp_fetch_folders($conn, (int)$company_id);
?>
<h1>Upload Floor Plans</h1>
<p><a href="index.php" class="btn btn-sm">← Gallery</a></p>
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
        <div id="floorPlanCreateUploadTarget" class="itm-photo-upload-target" role="button" tabindex="0" aria-label="Upload floor plan files">
            <p class="itm-dropzone-hint">Drag and drop images, PDFs, or AutoCAD files (DWG, DXF, DWF, DWS) here, or click to browse.</p>
            <input type="file" name="gallery_files[]" id="uploadFiles" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.dwg,.dxf,.dwf,.dws,image/*,application/pdf" multiple required>
        </div>
    </div>
    <div class="form-group">
        <label for="uploadTagsCreate">Tags (comma-separated)</label>
        <input type="text" name="upload_tags" id="uploadTagsCreate" placeholder="Ground Floor, Building A">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Upload</button>
        <a href="index.php" class="btn">Cancel</a>
    </div>
</form>

<script>
(function() {
    var uploadTarget = document.getElementById('floorPlanCreateUploadTarget');
    var fileInput = document.getElementById('uploadFiles');

    function assignFilesToInput(input, incomingFiles, mergeExisting) {
        if (!input || !incomingFiles) return;
        var transfer = new DataTransfer();
        if (mergeExisting && input.files) {
            Array.prototype.forEach.call(input.files, function(file) {
                transfer.items.add(file);
            });
        }
        Array.prototype.forEach.call(incomingFiles, function(file) {
            transfer.items.add(file);
        });
        input.files = transfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (uploadTarget && fileInput) {
        uploadTarget.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadTarget.classList.add('is-dragover');
        });
        uploadTarget.addEventListener('dragleave', function(e) {
            uploadTarget.classList.remove('is-dragover');
        });
        uploadTarget.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadTarget.classList.remove('is-dragover');
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                assignFilesToInput(fileInput, e.dataTransfer.files, true);
            }
        });
        uploadTarget.addEventListener('click', function(e) {
            if (e.target !== fileInput) fileInput.click();
        });
        uploadTarget.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                fileInput.click();
            }
        });
    }
})();
</script>
