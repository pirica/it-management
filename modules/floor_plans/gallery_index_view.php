<?php
/**
 * Gallery-first index layout for floor plans.
 */
$fpListUrl = $modulePath . '/list_all.php';
$fpCreateUrl = $modulePath . '/create.php';
?>
<?php echo itm_render_alert_errors($fpGalleryAccessError ?? ''); ?>
<div data-itm-new-button-managed="server" class="itm-floor-plan-toolbar">
    <div class="itm-floor-plan-toolbar-left">
        <a href="<?php echo sanitize($fpCreateUrl); ?>" class="btn btn-primary">➕ Upload</a>
        <a href="<?php echo sanitize($fpListUrl); ?>" class="btn btn-sm">Table view</a>
    </div>
    <h1 class="itm-floor-plan-title"><?php echo sanitize($moduleListHeading); ?></h1>
</div>

<div class="itm-floor-plan-layout">
    <aside class="card itm-floor-plan-sidebar">
        <h2 class="itm-floor-plan-sidebar-title">Folders</h2>
        <ul class="itm-folder-tree">
            <li class="itm-folder-tree-item itm-folder-reparent-root itm-folder-drop-target<?php echo ($galleryFolderId === 0 && !$galleryUnfiled) ? ' is-active' : ''; ?>" data-folder-reparent-root="1">
                <a href="index.php">📂 All files <span class="itm-drop-hint">(root)</span></a>
            </li>
            <li class="itm-folder-tree-item itm-folder-drop-target<?php echo $galleryUnfiled ? ' is-active' : ''; ?>" data-folder-drop-id="0" data-folder-drop-unfiled="1">
                <a href="index.php?unfiled=1">📄 Unfiled <span class="itm-drop-hint">(drop here)</span></a>
            </li>
            <?php echo fp_render_folder_tree_html($galleryTree, $galleryFolderId, $galleryUnfiled); ?>
        </ul>
        <details class="itm-folder-manage">
            <summary class="btn btn-sm">New folder</summary>
            <form method="POST" class="itm-folder-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <input type="hidden" name="fp_action" value="folder_create">
                <input type="hidden" name="folder_id" value="<?php echo (int)$galleryFolderId; ?>">
                <div class="form-group">
                    <label>Folder name</label>
                    <input type="text" name="folder_name" required>
                </div>
                <div class="form-group">
                    <label>Parent folder</label>
                    <select name="parent_folder_id">
                        <option value="">— Root —</option>
                        <?php foreach ($galleryFolders as $fpFolder): ?>
                            <option value="<?php echo (int)$fpFolder['id']; ?>" <?php echo ((int)$galleryFolderId === (int)$fpFolder['id']) ? 'selected' : ''; ?>>
                                <?php echo sanitize((string)$fpFolder['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm" title="Create">➕</button>
            </form>
        </details>
        <?php if ($galleryFolderId > 0): ?>
            <?php
            $fpCurrentFolderName = '';
            $fpCurrentFolderParentId = null;
            foreach ($galleryFolders as $fpFolder) {
                if ((int)$fpFolder['id'] === $galleryFolderId) {
                    $fpCurrentFolderName = (string)$fpFolder['name'];
                    $fpCurrentFolderParentId = fp_folder_parent_id_from_row($fpFolder);
                    break;
                }
            }
            ?>
            <details class="itm-folder-manage">
                <summary class="btn btn-sm">Move folder</summary>
                <form method="POST" class="itm-folder-form">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="fp_action" value="folder_move">
                    <input type="hidden" name="move_folder_id" value="<?php echo (int)$galleryFolderId; ?>">
                    <div class="form-group">
                        <label for="moveFolderParent">Move into</label>
                        <select name="parent_folder_id" id="moveFolderParent">
                            <option value=""<?php echo $fpCurrentFolderParentId === null ? ' selected' : ''; ?>>— Root —</option>
                            <?php echo fp_render_folder_move_parent_options($galleryFolders, (int)$galleryFolderId, $fpCurrentFolderParentId); ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Move</button>
                </form>
            </details>
            <details class="itm-folder-manage">
                <summary class="btn btn-sm">Rename folder</summary>
                <form method="POST" class="itm-folder-form">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="fp_action" value="folder_rename">
                    <input type="hidden" name="folder_id" value="<?php echo (int)$galleryFolderId; ?>">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="folder_name" value="<?php echo sanitize($fpCurrentFolderName); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" title="Save">💾</button>
                </form>
            </details>
            <form method="POST" onsubmit="return confirm('Delete this folder? It must be empty.');">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <input type="hidden" name="fp_action" value="folder_delete">
                <input type="hidden" name="folder_id" value="<?php echo (int)$galleryFolderId; ?>">
                <button type="submit" class="btn btn-sm btn-danger">Delete folder</button>
            </form>
        <?php endif; ?>
    </aside>

    <section class="itm-floor-plan-main">
        <div class="card" style="margin-bottom:16px;">
            <form method="GET" class="itm-floor-plan-search">
                <?php if ($galleryFolderId > 0): ?>
                    <input type="hidden" name="folder_id" value="<?php echo (int)$galleryFolderId; ?>">
                <?php endif; ?>
                <?php if ($galleryUnfiled): ?>
                    <input type="hidden" name="unfiled" value="1">
                <?php endif; ?>
                <div class="form-group" style="margin:0;flex:1;">
                    <label for="gallerySearch">Search file, folder, tag, IT location, or extension (e.g. pdf, png)</label>
                    <input type="text" id="gallerySearch" name="search" value="<?php echo sanitize($gallerySearch); ?>" placeholder="Name, folder, tag, IT location, or extension (pdf, png, dwg)...">
                </div>
                <div class="form-actions" style="margin:0;">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn">Clear</a>
                </div>
            </form>
        </div>

        <form method="POST" enctype="multipart/form-data" class="card itm-floor-plan-upload-form" id="floorPlanUploadForm">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <input type="hidden" name="fp_action" value="upload_files">
            <input type="hidden" name="folder_id" value="<?php echo (int)$galleryFolderId; ?>">
            <div id="floorPlanUploadTarget" class="itm-floor-plan-upload-target" role="button" tabindex="0" aria-label="Upload floor plan files">
                <p class="itm-dropzone-hint">Drag and drop images, PDFs, or AutoCAD files (DWG, DXF, DWF, DWS) here, or click to browse (max 20MB each).</p>
                <input type="file" name="gallery_files[]" id="galleryFilesInput" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.dwg,.dxf,.dwf,.dws,image/*,application/pdf" multiple>
            </div>
            <p class="itm-dropzone-hint itm-dropzone-hint-secondary">Drag a file card (thumbnail or ⠿ handle) onto a folder row to move it. Use the ⠿ handle on a folder row to reorganize folders.</p>
            <div class="form-group" style="margin-top:12px;">
                <label for="uploadItLocation"><?php echo sanitize(fp_it_location_link_label_optional()); ?></label>
                <?php echo fp_render_it_location_select($conn, (int)$company_id, 'it_location_id', 'uploadItLocation', ''); ?>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label for="uploadTags">Tags (optional, comma-separated)</label>
                <input type="text" name="upload_tags" id="uploadTags" placeholder="e.g. Ground Floor, Building A">
            </div>
            <button type="submit" class="btn btn-primary">Upload files</button>
        </form>

        <form id="floorPlanMoveForm" method="POST" action="index.php" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <input type="hidden" name="fp_action" value="move_file">
            <input type="hidden" name="plan_id" id="floorPlanMovePlanId" value="">
            <input type="hidden" name="folder_id" id="floorPlanMoveFolderId" value="">
        </form>
        <form id="floorPlanMoveFolderForm" method="POST" action="index.php" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <input type="hidden" name="fp_action" value="folder_move">
            <input type="hidden" name="move_folder_id" id="floorPlanMoveFolderSourceId" value="">
            <input type="hidden" name="parent_folder_id" id="floorPlanMoveFolderParentId" value="">
        </form>

        <div class="itm-floor-plan-gallery" id="floorPlanGallery">
            <?php if (empty($galleryItems)): ?>
                <p class="itm-gallery-empty">No floor plans found. Upload files or adjust your search.</p>
            <?php else: ?>
                <?php foreach ($galleryItems as $fpItem): ?>
                    <?php
                    $fpId = (int)$fpItem['id'];
                    $fpUrl = fp_public_url((int)$company_id, (string)$fpItem['stored_filename']);
                    $fpMime = (string)$fpItem['mime_type'];
                    $fpExt = (string)$fpItem['file_ext'];
                    $fpPreviewKind = fp_resolve_preview_kind($fpMime, $fpExt);
                    $fpTags = trim((string)($fpItem['tag_names'] ?? ''));
                    $fpDownloadName = preg_replace('/[^\w.\-]+/u', '_', (string)($fpItem['display_name'] ?? 'floor-plan'));
                    if ($fpDownloadName === '') {
                        $fpDownloadName = 'floor-plan';
                    }
                    if ($fpExt !== '' && !preg_match('/\.' . preg_quote($fpExt, '/') . '$/i', $fpDownloadName)) {
                        $fpDownloadName .= '.' . $fpExt;
                    }
                    ?>
                    <article class="itm-floor-plan-card" data-plan-id="<?php echo $fpId; ?>">
                        <span class="itm-plan-drag-handle" draggable="true" data-plan-id="<?php echo $fpId; ?>" title="Drag to move file" aria-label="Drag to move file">⠿</span>
                        <a href="view.php?id=<?php echo $fpId; ?>" class="itm-floor-plan-thumb-link" draggable="false" data-preview-url="<?php echo sanitize($fpUrl); ?>" data-preview-type="<?php echo sanitize($fpPreviewKind); ?>" data-preview-name="<?php echo sanitize((string)$fpItem['display_name']); ?>" data-preview-download-name="<?php echo sanitize($fpDownloadName); ?>">
                            <?php if ($fpPreviewKind === 'pdf'): ?>
                                <div class="itm-floor-plan-pdf-thumb">PDF</div>
                            <?php elseif ($fpPreviewKind === 'cad'): ?>
                                <div class="itm-floor-plan-cad-thumb"><?php echo sanitize(strtoupper($fpExt)); ?></div>
                            <?php elseif ($fpPreviewKind === 'image'): ?>
                                <img src="<?php echo sanitize($fpUrl); ?>" alt="<?php echo sanitize((string)$fpItem['display_name']); ?>" loading="lazy">
                            <?php else: ?>
                                <div class="itm-floor-plan-cad-thumb">FILE</div>
                            <?php endif; ?>
                        </a>
                        <div class="itm-floor-plan-card-body">
                            <h3 title="<?php echo sanitize((string)$fpItem['display_name']); ?>"><?php echo sanitize((string)$fpItem['display_name']); ?></h3>
                            <?php if ((string)($fpItem['folder_name'] ?? '') !== ''): ?>
                                <p class="itm-floor-plan-meta">📁 <?php echo sanitize((string)$fpItem['folder_name']); ?></p>
                            <?php endif; ?>
                            <?php if ((string)($fpItem['location_name'] ?? '') !== ''): ?>
                                <p class="itm-floor-plan-meta">📍 <?php echo sanitize((string)$fpItem['location_name']); ?></p>
                            <?php endif; ?>
                            <?php if ($fpTags !== ''): ?>
                                <p class="itm-floor-plan-tags"><?php echo sanitize($fpTags); ?></p>
                            <?php endif; ?>
                            <p class="itm-floor-plan-meta"><?php echo sanitize(fp_format_file_size((int)$fpItem['file_size'])); ?> · <?php echo sanitize(strtoupper((string)$fpItem['file_ext'])); ?></p>
                            <div class="itm-actions-wrap">
                                <a class="btn btn-sm" href="view.php?id=<?php echo $fpId; ?>">🔎</a>
                                <a class="btn btn-sm" href="edit.php?id=<?php echo $fpId; ?>">✏️</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this file?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                    <input type="hidden" name="fp_action" value="delete_file">
                                    <input type="hidden" name="plan_id" value="<?php echo $fpId; ?>">
                                    <input type="hidden" name="folder_id" value="<?php echo (int)$galleryFolderId; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($hasCompany && $company_id > 0 && $gallerySampleEmpty): ?>
            <div class="card" style="margin-top:12px;">
                <form method="POST" style="display:flex;justify-content:center;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</div>

<div id="floorPlanPreviewModal" class="itm-floor-plan-modal" hidden>
    <div class="itm-floor-plan-modal-backdrop" data-close-preview="1"></div>
    <div class="itm-floor-plan-modal-content">
        <div class="itm-floor-plan-modal-header">
            <h3 id="floorPlanPreviewTitle"></h3>
            <div class="itm-floor-plan-modal-header-actions">
                <div id="floorPlanPreviewActions" class="itm-floor-plan-modal-actions"></div>
                <button type="button" class="btn btn-sm itm-floor-plan-modal-close" data-close-preview="1">Close</button>
            </div>
        </div>
        <div id="floorPlanPreviewBody" class="itm-floor-plan-preview-body"></div>
    </div>
</div>
