<?php
require '../../config/config.php';

function ticket_parse_photo_filenames($rawValue): array
{
    if (!is_string($rawValue) || trim($rawValue) === '') {
        return [];
    }

    $decoded = json_decode($rawValue, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $decoded), static function ($value) {
        return $value !== '';
    }));
}

function ticket_photo_public_path(string $filename): string
{
    return TICKET_UPLOAD_URL . rawurlencode($filename);
}

function ticket_detect_upload_mime_type(string $tmpName): string
{
    if ($tmpName === '' || !is_file($tmpName)) {
        return '';
    }

    if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $tmpName);
            @finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($tmpName);
        if (is_string($mime) && $mime !== '') {
            return strtolower($mime);
        }
    }

    $imageInfo = @getimagesize($tmpName);
    if (is_array($imageInfo) && isset($imageInfo['mime']) && $imageInfo['mime'] !== '') {
        return strtolower((string)$imageInfo['mime']);
    }

    if (function_exists('exif_imagetype')) {
        $imageType = @exif_imagetype($tmpName);
        $imageTypeMimeMap = [
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_GIF => 'image/gif',
        ];
        if (isset($imageTypeMimeMap[$imageType])) {
            return $imageTypeMimeMap[$imageType];
        }
    }

    return '';
}

function ticket_resolve_upload_ticket_id(mysqli $conn, bool $isEdit, int $id): int
{
    if ($isEdit && $id > 0) {
        return $id;
    }

    $statusResult = mysqli_query($conn, "SHOW TABLE STATUS LIKE 'tickets'");
    if ($statusResult) {
        $statusRow = mysqli_fetch_assoc($statusResult);
        if (is_array($statusRow) && isset($statusRow['Auto_increment'])) {
            $nextId = (int)$statusRow['Auto_increment'];
            if ($nextId > 0) {
                return $nextId;
            }
        }
    }

    return 0;
}

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';
$csrfToken = itm_get_csrf_token();
$ticketUploadPath = TICKET_UPLOAD_PATH;

$data = [
    'ticket_external_code' => '',
    'title' => '',
    'description' => '',
    'category_id' => '',
    'status_id' => '',
    'priority_id' => '',
    'assigned_to_user_id' => '',
    'asset_id' => '',
    'ui_color' => '#0969da',
    'tickets_photos' => '',
    'created_at' => date('Y-m-d\TH:i')
];

if ($is_edit) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $q = mysqli_stmt_get_result($stmt);
        if ($q && mysqli_num_rows($q) === 1) {
            $data = mysqli_fetch_assoc($q);
            $data['created_at'] = date('Y-m-d\TH:i', strtotime($data['created_at']));
        } else {
            $error = 'Ticket not found.';
            $is_edit = false;
        }
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $ticket_external_code = escape_sql($_POST['ticket_external_code'] ?? '', $conn);
    $title = escape_sql($_POST['title'] ?? '', $conn);
    $description = escape_sql($_POST['description'] ?? '', $conn);
    $category_id_post = $_POST['category_id'] ?? 0;
    $status_id_post = $_POST['status_id'] ?? 0;
    $priority_id_post = $_POST['priority_id'] ?? 0;
    $assigned_to_user_id_post = $_POST['assigned_to_user_id'] ?? 0;
    $asset_id_post = $_POST['asset_id'] ?? 0;
    $ui_color_raw = strtolower(trim((string)($_POST['ui_color'] ?? '#0969da')));
    $ui_color = preg_match('/^#[0-9a-f]{6}$/', $ui_color_raw) ? $ui_color_raw : '#0969da';
    $ui_color_sql = "'" . escape_sql($ui_color, $conn) . "'";

    foreach (['category_id_post', 'status_id_post', 'priority_id_post', 'assigned_to_user_id_post', 'asset_id_post'] as $fkPostField) {
        if ($$fkPostField === '__add_new__') {
            $$fkPostField = 0;
        }
    }

    $category_id = (int)$category_id_post ?: 'NULL';
    $status_id = (int)$status_id_post ?: 'NULL';
    $priority_id = (int)$priority_id_post ?: 'NULL';
    $assigned_to_user_id = (int)$assigned_to_user_id_post ?: 'NULL';
    $asset_id = (int)$asset_id_post ?: 'NULL';
    $ticketPhotoFilenames = ticket_parse_photo_filenames((string)($data['tickets_photos'] ?? ''));
    $ticketPhotoFilenamesToDeleteAfterSave = [];
    $deleteCurrentPhotos = isset($_POST['delete_photo']) && (string)$_POST['delete_photo'] === '1';
    $deletePhotoIndexesRaw = trim((string)($_POST['delete_photo_indexes'] ?? ''));
    $deletePhotoIndexes = [];
    if ($deletePhotoIndexesRaw !== '') {
        $deletePhotoIndexes = array_values(array_unique(array_filter(array_map(static function ($indexValue) {
            if (!is_numeric($indexValue)) {
                return null;
            }

            $index = (int)$indexValue;
            return $index >= 0 ? $index : null;
        }, explode(',', $deletePhotoIndexesRaw)), static function ($value) {
            return $value !== null;
        })));
    }
    if (!$error && $is_edit && $deleteCurrentPhotos && !empty($ticketPhotoFilenames)) {
        $ticketPhotoFilenamesToDeleteAfterSave = array_values(array_unique(array_merge(
            $ticketPhotoFilenamesToDeleteAfterSave,
            $ticketPhotoFilenames
        )));
        $ticketPhotoFilenames = [];
    } elseif (!$error && $is_edit && !empty($deletePhotoIndexes) && !empty($ticketPhotoFilenames)) {
        foreach ($deletePhotoIndexes as $deletePhotoIndex) {
            if (!array_key_exists($deletePhotoIndex, $ticketPhotoFilenames)) {
                continue;
            }
            $ticketPhotoFilenamesToDeleteAfterSave[] = (string)$ticketPhotoFilenames[$deletePhotoIndex];
            unset($ticketPhotoFilenames[$deletePhotoIndex]);
        }
        $ticketPhotoFilenames = array_values($ticketPhotoFilenames);
        $ticketPhotoFilenamesToDeleteAfterSave = array_values(array_unique($ticketPhotoFilenamesToDeleteAfterSave));
    }

    $created_at_raw = $_POST['created_at'] ?? '';
    $created_at = $created_at_raw
        ? "'" . escape_sql(str_replace('T', ' ', $created_at_raw) . ':00', $conn) . "'"
        : 'CURRENT_TIMESTAMP';
    $uploadTicketId = ticket_resolve_upload_ticket_id($conn, $is_edit, $id);

    if (
        !$error
        && isset($_FILES['photo'])
        && is_array($_FILES['photo']['error'] ?? null)
    ) {
        $uploadedPhotoFilenames = [];
        $fileCount = count($_FILES['photo']['error']);

        for ($index = 0; $index < $fileCount; $index++) {
            $fileError = (int)($_FILES['photo']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($fileError === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($fileError !== UPLOAD_ERR_OK) {
                $error = 'One of the photo uploads failed.';
                break;
            }

            $fileSize = (int)($_FILES['photo']['size'][$index] ?? 0);
            if ($fileSize > MAX_FILE_SIZE) {
                $error = 'One of the photos exceeds max allowed size.';
                break;
            }

            $tmpName = (string)($_FILES['photo']['tmp_name'][$index] ?? '');
            $name = (string)($_FILES['photo']['name'][$index] ?? '');
            $mime = ticket_detect_upload_mime_type($tmpName);
            $mimeAliases = [
                'image/x-png' => 'image/png',
                'image/pjpeg' => 'image/jpeg',
            ];
            if (isset($mimeAliases[$mime])) {
                $mime = $mimeAliases[$mime];
            }

            if (!in_array($mime, ALLOWED_TYPES, true)) {
                $error = 'One of the uploaded files has an unsupported image type.';
                break;
            }

            if (!is_dir($ticketUploadPath)) {
                @mkdir($ticketUploadPath, 0775, true);
            }

            $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION));
            if ($ext === '') {
                $ext = 'jpg';
            }
            $photoFilename = 'ticket_' . $uploadTicketId . '_' . time() . '_' . mt_rand(1000, 9999) . '_' . $index . '.' . $ext;
            if (!move_uploaded_file($tmpName, $ticketUploadPath . $photoFilename)) {
                $error = 'Unable to save one of the uploaded photos.';
                break;
            }
            $uploadedPhotoFilenames[] = $photoFilename;
        }

        if ($error !== '' && !empty($uploadedPhotoFilenames)) {
            foreach ($uploadedPhotoFilenames as $uploadedPhotoFilename) {
                $uploadedPath = $ticketUploadPath . $uploadedPhotoFilename;
                if (is_file($uploadedPath)) {
                    @unlink($uploadedPath);
                }
            }
        } elseif (!$error && !empty($uploadedPhotoFilenames)) {
            $ticketPhotoFilenames = array_values(array_unique(array_merge($ticketPhotoFilenames, $uploadedPhotoFilenames)));
        }
    }

    $tickets_photos = empty($ticketPhotoFilenames)
        ? 'NULL'
        : "'" . escape_sql(json_encode($ticketPhotoFilenames, JSON_UNESCAPED_SLASHES), $conn) . "'";

    if (!$title) {
        $error = 'Ticket title is required.';
    } else {
        if ($is_edit) {
            $sql = "UPDATE tickets SET
                        ticket_external_code='$ticket_external_code',
                        title='$title',
                        description='$description',
                        category_id=$category_id,
                        status_id=$status_id,
                        priority_id=$priority_id,
                        assigned_to_user_id=$assigned_to_user_id,
                        asset_id=$asset_id,
                        ui_color=$ui_color_sql,
                        tickets_photos=$tickets_photos,
                        created_at=$created_at
                    WHERE id=$id AND company_id=$company_id";
        } else {
            $created_by_user_id = 0;
            $creator_stmt = mysqli_prepare($conn, 'SELECT id FROM users WHERE company_id = ? AND active = 1 ORDER BY id ASC LIMIT 1');
            if ($creator_stmt) {
                mysqli_stmt_bind_param($creator_stmt, 'i', $company_id);
                mysqli_stmt_execute($creator_stmt);
                $creator_result = mysqli_stmt_get_result($creator_stmt);
                if ($creator_result && mysqli_num_rows($creator_result) === 1) {
                    $created_by_user_id = (int)mysqli_fetch_assoc($creator_result)['id'];
                }
                mysqli_stmt_close($creator_stmt);
            }

            if ($created_by_user_id <= 0) {
                $error = 'Please add at least one active user before adding tickets.';
            } else {
                $sql = "INSERT INTO tickets
                        (company_id, ticket_external_code, title, description, category_id, status_id, priority_id, created_by_user_id, assigned_to_user_id, asset_id, ui_color, tickets_photos, created_at)
                        VALUES
                        ($company_id, '$ticket_external_code', '$title', '$description', $category_id, $status_id, $priority_id, $created_by_user_id, $assigned_to_user_id, $asset_id, $ui_color_sql, $tickets_photos, $created_at)";
            }
        }

        if (!$error) {
            $dbErrorCode = 0;
            $dbErrorMessage = '';
            if (itm_run_query($conn, $sql, $dbErrorCode, $dbErrorMessage)) {
                foreach ($ticketPhotoFilenamesToDeleteAfterSave as $deletedFilename) {
                    $existingPhotoPath = $ticketUploadPath . $deletedFilename;
                    if (is_file($existingPhotoPath)) {
                        @unlink($existingPhotoPath);
                    }
                }
                header('Location: index.php');
                exit;
            }
            $error = itm_format_db_constraint_error($dbErrorCode, $dbErrorMessage);
        }
    }
}

$categories = mysqli_query($conn, "SELECT id,name FROM ticket_categories WHERE company_id=$company_id AND active=1 ORDER BY name");
$statuses = mysqli_query($conn, "SELECT id,name,color FROM ticket_statuses WHERE company_id=$company_id AND active=1 ORDER BY name");
$priorities = mysqli_query($conn, "SELECT id,name,color FROM ticket_priorities WHERE company_id=$company_id AND active=1 ORDER BY level");
$users = mysqli_query($conn, "SELECT id,username FROM users WHERE company_id=$company_id AND active=1 ORDER BY username");
$assets = mysqli_query($conn, "SELECT id,name FROM equipment WHERE company_id=$company_id AND active=1 ORDER BY name");
$existingTicketPhotos = ticket_parse_photo_filenames((string)($data['tickets_photos'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'New'; ?> Ticket</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
    .photo-preview-modal {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1200;
        padding: 16px;
    }
    .photo-preview-content {
        width: min(920px, 100%);
        max-height: 90vh;
        overflow: auto;
        background: #fff;
        border-radius: 10px;
        padding: 16px;
        box-shadow: 0 12px 36px rgba(0, 0, 0, 0.22);
    }
    .photo-preview-content img {
        max-width: 100%;
        border-radius: 8px;
        display: block;
    }
    .photo-preview-actions {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 10px;
    }
    .photo-preview-trigger {
        margin-left: 8px;
    }
    .photo-preview-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
    }
    .photo-preview-item {
        border: 1px solid #d0d7de;
        border-radius: 8px;
        padding: 8px;
    }
    .photo-preview-gallery img {
        width: 100%;
        height: 130px;
        object-fit: cover;
        margin-bottom: 8px;
    }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1><?php echo $is_edit ? '✏️ Edit' : '➕ New'; ?> Ticket</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Title *</label>
                            <input required name="title" value="<?php echo sanitize($data['title']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Ticket External Code</label>
                            <input name="ticket_external_code" value="<?php echo sanitize($data['ticket_external_code']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description"><?php echo sanitize($data['description']); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" data-addable-select="1" data-add-table="ticket_categories" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="ticket category">
                                <option value="">-- Select --</option>
                                <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (string)$data['category_id'] === (string)$c['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($c['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status_id" data-addable-select="1" data-add-table="ticket_statuses" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="ticket status">
                                <option value="">-- Select --</option>
                                <?php while ($s = mysqli_fetch_assoc($statuses)): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo (string)$data['status_id'] === (string)$s['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($s['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority_id" data-addable-select="1" data-add-table="ticket_priorities" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="ticket priority">
                                <option value="">-- Select --</option>
                                <?php while ($p = mysqli_fetch_assoc($priorities)): ?>
                                    <option value="<?php echo (int)$p['id']; ?>" <?php echo (string)$data['priority_id'] === (string)$p['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($p['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assigned To</label>
                            <select name="assigned_to_user_id" data-addable-select="1" data-add-table="users" data-add-id-col="id" data-add-label-col="username" data-add-company-scoped="1" data-add-friendly="assigned user">
                                <option value="">-- Unassigned --</option>
                                <?php while ($u = mysqli_fetch_assoc($users)): ?>
                                    <option value="<?php echo (int)$u['id']; ?>" <?php echo (string)$data['assigned_to_user_id'] === (string)$u['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($u['username']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Related Asset</label>
                            <select name="asset_id" data-addable-select="1" data-add-table="equipment" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="related asset">
                                <option value="">-- None --</option>
                                <?php while ($a = mysqli_fetch_assoc($assets)): ?>
                                    <option value="<?php echo (int)$a['id']; ?>" <?php echo (string)$data['asset_id'] === (string)$a['id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitize($a['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Created Date</label>
                            <input type="datetime-local" name="created_at" value="<?php echo sanitize($data['created_at']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Photo Upload</label>
                        <input type="file" name="photo[]" accept="image/*" multiple="">
                        <div class="form-hint">You can upload one or many photos at once.</div>
                        <?php if (!empty($existingTicketPhotos)): ?>
                            <input type="hidden" name="delete_photo" id="deletePhotoInput" value="0">
                            <input type="hidden" name="delete_photo_indexes" id="deletePhotoIndexesInput" value="">
                            <div class="form-hint" id="currentPhotoHint">
                                <span id="currentPhotoHintText">Current photos: <?php echo count($existingTicketPhotos); ?></span>
                                <button type="button" class="btn btn-sm photo-preview-trigger" id="openPhotoPreview">View Photos</button>
                                <button type="button" class="btn btn-sm" id="deletePhotoButton" style="margin-left:8px;">Delete All</button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Quick Color Tag (UI)</label>
                        <input type="color" name="ui_color" value="<?php echo sanitize($data['ui_color'] ?? '#0969da'); ?>">
                        <div class="form-hint">Color picker for fast visual tagging while creating tickets.</div>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a class="btn" href="index.php">✖️</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($existingTicketPhotos)): ?>
<div class="photo-preview-modal" id="photoPreviewModal" aria-hidden="true">
    <div class="photo-preview-content" role="dialog" aria-modal="true" aria-label="Current ticket photos" onclick="event.stopPropagation()">
        <div class="photo-preview-actions">
            <button type="button" class="btn btn-sm" id="closePhotoPreview">Close</button>
        </div>
        <div class="photo-preview-gallery">
            <?php foreach ($existingTicketPhotos as $photoIndex => $ticketPhoto): ?>
                <div class="photo-preview-item">
                    <a href="<?php echo sanitize(ticket_photo_public_path($ticketPhoto)); ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo sanitize(ticket_photo_public_path($ticketPhoto)); ?>" alt="Ticket photo <?php echo (int)$photoIndex + 1; ?>">
                    </a>
                    <button type="button" class="btn btn-sm delete-photo-item" data-photo-index="<?php echo (int)$photoIndex; ?>" aria-label="Delete photo <?php echo (int)$photoIndex + 1; ?>">♻️ Delete</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="../../js/theme.js"></script>
<script src="../../js/select-add-option.js"></script>
<script>
(function () {
    var openPhotoPreview = document.getElementById('openPhotoPreview');
    var photoPreviewModal = document.getElementById('photoPreviewModal');
    var closePhotoPreview = document.getElementById('closePhotoPreview');
    var deletePhotoButton = document.getElementById('deletePhotoButton');
    var deletePhotoInput = document.getElementById('deletePhotoInput');
    var deletePhotoIndexesInput = document.getElementById('deletePhotoIndexesInput');
    var currentPhotoHintText = document.getElementById('currentPhotoHintText');
    var photoInput = document.querySelector('input[name="photo[]"]');
    var deletePhotoItemButtons = document.querySelectorAll('.delete-photo-item');
    var pendingDeletedPhotoIndexes = new Set();
    var totalCurrentPhotos = deletePhotoItemButtons.length;

    function resetPendingPhotoDeletionState() {
        pendingDeletedPhotoIndexes.clear();
        if (deletePhotoInput) {
            deletePhotoInput.value = '0';
        }
        if (deletePhotoIndexesInput) {
            deletePhotoIndexesInput.value = '';
        }
        if (deletePhotoButton) {
            deletePhotoButton.disabled = false;
        }
    }

    function syncDeletePhotoIndexes() {
        if (!deletePhotoIndexesInput) {
            return;
        }
        deletePhotoIndexesInput.value = Array.from(pendingDeletedPhotoIndexes).sort(function (a, b) { return a - b; }).join(',');
    }

    function updateCurrentPhotoHint() {
        if (!currentPhotoHintText) {
            return;
        }
        if (deletePhotoInput && deletePhotoInput.value === '1') {
            currentPhotoHintText.textContent = 'Current photos will be deleted after you save.';
            return;
        }
        if (pendingDeletedPhotoIndexes.size > 0) {
            var remainingPhotos = Math.max(totalCurrentPhotos - pendingDeletedPhotoIndexes.size, 0);
            currentPhotoHintText.textContent = pendingDeletedPhotoIndexes.size + ' photo(s) will be deleted after you save. Remaining: ' + remainingPhotos + '.';
            return;
        }
        currentPhotoHintText.textContent = 'Current photos: ' + totalCurrentPhotos;
    }

    function hidePhotoModal() {
        if (!photoPreviewModal) {
            return;
        }
        photoPreviewModal.style.display = 'none';
        photoPreviewModal.setAttribute('aria-hidden', 'true');
    }

    resetPendingPhotoDeletionState();
    updateCurrentPhotoHint();
    window.addEventListener('pageshow', function () {
        resetPendingPhotoDeletionState();
        updateCurrentPhotoHint();
    });

    if (openPhotoPreview && photoPreviewModal) {
        openPhotoPreview.addEventListener('click', function (event) {
            event.preventDefault();
            photoPreviewModal.style.display = 'flex';
            photoPreviewModal.setAttribute('aria-hidden', 'false');
        });

        photoPreviewModal.addEventListener('click', hidePhotoModal);

        if (closePhotoPreview) {
            closePhotoPreview.addEventListener('click', hidePhotoModal);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hidePhotoModal();
            }
        });
    }

    if (deletePhotoButton && deletePhotoInput) {
        deletePhotoButton.addEventListener('click', function () {
            deletePhotoInput.value = '1';
            pendingDeletedPhotoIndexes.clear();
            syncDeletePhotoIndexes();
            hidePhotoModal();
            updateCurrentPhotoHint();
            if (photoInput) {
                photoInput.value = '';
            }
            deletePhotoButton.disabled = true;
        });
    }

    if (deletePhotoItemButtons.length > 0 && deletePhotoInput) {
        deletePhotoItemButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                if (deletePhotoInput.value === '1') {
                    return;
                }
                var photoIndex = parseInt(button.getAttribute('data-photo-index') || '', 10);
                if (!Number.isInteger(photoIndex) || photoIndex < 0) {
                    return;
                }
                pendingDeletedPhotoIndexes.add(photoIndex);
                syncDeletePhotoIndexes();
                var photoItem = button.closest('.photo-preview-item');
                if (photoItem) {
                    photoItem.style.display = 'none';
                }
                updateCurrentPhotoHint();
            });
        });
    }
})();
</script>
</body>
</html>
