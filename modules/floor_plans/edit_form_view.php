<?php
/**
 * Edit floor plan metadata (name, folder, tags).
 */
$fpPlanId = (int)($data['id'] ?? 0);
$fpFolders = fp_fetch_folders($conn, (int)$company_id);
$fpCurrentFolderId = (int)($data['folder_id'] ?? 0);
if ($fpCurrentFolderId > 0 && (int)$company_id > 0) {
    $fpCurrentFolderId = itm_fk_resolve_company_equivalent_id($conn, [
        'REFERENCED_TABLE_NAME' => 'floor_plan_folders',
        'REFERENCED_COLUMN_NAME' => 'id',
    ], (int)$company_id, $fpCurrentFolderId);
}
$fpTags = fp_get_tags_for_plan($conn, $fpPlanId, (int)$company_id);
$fpTagNames = [];
foreach ($fpTags as $fpTag) {
    $fpTagNames[] = (string)$fpTag['name'];
}
$fpTagValue = implode(', ', $fpTagNames);
?>
<h1>Edit Floor Plan</h1>
<form method="POST" class="form-grid" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
    <input type="hidden" name="id" value="<?php echo $fpPlanId; ?>">
    <div class="form-group">
        <label for="display_name">File name</label>
        <input type="text" name="display_name" id="display_name" value="<?php echo sanitize((string)($data['display_name'] ?? '')); ?>" required>
    </div>
    <div class="form-group">
        <label for="folder_id">Folder</label>
        <select name="folder_id" id="folder_id">
            <option value="">— Unfiled —</option>
            <?php echo fp_render_folder_select_options($fpFolders, $fpCurrentFolderId > 0 ? $fpCurrentFolderId : null); ?>
            <?php if ($fpCurrentFolderId > 0 && fp_folder_row_by_id($fpFolders, $fpCurrentFolderId) === null): ?>
                <?php
                $fpPersistedLabel = fp_folder_breadcrumb_label($fpFolders, $fpCurrentFolderId);
                if ($fpPersistedLabel === '') {
                    $fpRes = mysqli_query($conn, 'SELECT name FROM floor_plan_folders WHERE id=' . (int)$fpCurrentFolderId . ' AND company_id=' . (int)$company_id . ' LIMIT 1');
                    $fpRow = ($fpRes) ? mysqli_fetch_assoc($fpRes) : null;
                    $fpPersistedLabel = (string)($fpRow['name'] ?? ('Folder #' . $fpCurrentFolderId));
                }
                ?>
                <option value="<?php echo (int)$fpCurrentFolderId; ?>" selected><?php echo sanitize($fpPersistedLabel); ?></option>
            <?php endif; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="it_location_id"><?php echo sanitize(fp_it_location_link_label_optional()); ?></label>
        <?php echo fp_render_it_location_select($conn, (int)$company_id, 'it_location_id', 'it_location_id', $data['it_location_id'] ?? ''); ?>
    </div>
    <div class="form-group">
        <label for="tag_names">Tags (comma-separated)</label>
        <input type="text" name="tag_names" id="tag_names" value="<?php echo sanitize($fpTagValue); ?>">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary" title="Save">💾</button>
        <a href="view.php?id=<?php echo $fpPlanId; ?>" class="btn" title="Cancel">🔙</a>
    </div>
</form>
