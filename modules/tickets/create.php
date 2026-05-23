<?php
/**
 * Tickets Module - Create/Edit
 * 
 * Provides a dynamic form for managing support tickets.
 * Handles:
 * - Ticket profiling (External Code, Title, Description)
 * - Classification (Category, Status, Priority)
 * - Assignments (User, Asset)
 * - Multiple photo uploads with gallery management
 * - Visual tagging with UI color picker
 */

require '../../config/config.php';

/**
 * Parses JSON-encoded photo filenames from the database
 */
function ticket_parse_photo_filenames($rawValue): array
{
    if (!is_string($rawValue) || trim($rawValue) === '') { return []; }
    $decoded = json_decode($rawValue, true);
    if (!is_array($decoded)) { return []; }
    return array_values(array_filter(array_map('strval', $decoded), static function ($value) { return $value !== ''; }));
}

/**
 * Returns the public URL for a ticket photo
 */
function ticket_photo_public_path(string $filename): string
{
    return TICKET_UPLOAD_URL . rawurlencode($filename);
}

/**
 * Securely detects the MIME type of an uploaded photo
 */
function ticket_detect_upload_mime_type(string $tmpName): string
{
    if ($tmpName === '' || !is_file($tmpName)) { return ''; }
    if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $tmpName); @finfo_close($finfo);
            if (is_string($mime) && $mime !== '') { return strtolower($mime); }
        }
    }
    // Fallback detection
    $imageInfo = @getimagesize($tmpName);
    if (is_array($imageInfo) && isset($imageInfo['mime']) && $imageInfo['mime'] !== '') { return strtolower((string)$imageInfo['mime']); }
    return '';
}

/**
 * Predicts the ID of the ticket being created to use in filenames
 */
function ticket_resolve_upload_ticket_id(mysqli $conn, bool $isEdit, int $id): int
{
    if ($isEdit && $id > 0) { return $id; }
    $statusResult = mysqli_query($conn, "SHOW TABLE STATUS LIKE 'tickets'");
    if ($statusResult) {
        $statusRow = mysqli_fetch_assoc($statusResult);
        if (is_array($statusRow) && isset($statusRow['Auto_increment'])) {
            return (int)$statusRow['Auto_increment'];
        }
    }
    return 0;
}

/**
 * Tenant lookup options with persisted FK row appended when company-scoped query omits it.
 *
 * @return array<int, array{id:int,name:string}>
 */
function tickets_lookup_select_options(mysqli $conn, string $table, int $companyId, $selectedId, string $orderColumn = 'name'): array
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return [];
    }

    $orderColumn = $orderColumn === 'level' ? 'level' : 'name';
    $options = [];
    $sql = 'SELECT id, name FROM `' . str_replace('`', '``', $table) . '` WHERE company_id = '
        . (int)$companyId . ' AND active = 1 ORDER BY `' . $orderColumn . '` ASC';
    $res = mysqli_query($conn, $sql);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $options[] = ['id' => (int)($row['id'] ?? 0), 'name' => (string)($row['name'] ?? '')];
    }

    $selected = (int)$selectedId;
    if ($selected <= 0) {
        return $options;
    }

    foreach ($options as $option) {
        if ($option['id'] === $selected) {
            return $options;
        }
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id, name FROM `' . str_replace('`', '``', $table) . '` WHERE id = ? AND company_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return $options;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $selected, $companyId);
    mysqli_stmt_execute($stmt);
    $lookup = mysqli_stmt_get_result($stmt);
    $persisted = ($lookup) ? mysqli_fetch_assoc($lookup) : null;
    mysqli_stmt_close($stmt);
    if (is_array($persisted)) {
        $options[] = ['id' => (int)($persisted['id'] ?? 0), 'name' => (string)($persisted['name'] ?? '')];
    }

    return $options;
}

/**
 * @return array<int, array{id:int,label:string}>
 */
function tickets_equipment_select_options(mysqli $conn, int $companyId, $selectedId): array
{
    if ($companyId <= 0) {
        return [];
    }

    $options = [];
    $res = mysqli_query(
        $conn,
        'SELECT id, name FROM equipment WHERE company_id = ' . (int)$companyId . ' AND active = 1 ORDER BY name ASC'
    );
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $options[] = ['id' => (int)($row['id'] ?? 0), 'label' => (string)($row['name'] ?? '')];
    }

    $selected = (int)$selectedId;
    if ($selected <= 0) {
        return $options;
    }

    foreach ($options as $option) {
        if ($option['id'] === $selected) {
            return $options;
        }
    }

    $stmt = mysqli_prepare($conn, 'SELECT id, name FROM equipment WHERE id = ? AND company_id = ? LIMIT 1');
    if (!$stmt) {
        return $options;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $selected, $companyId);
    mysqli_stmt_execute($stmt);
    $lookup = mysqli_stmt_get_result($stmt);
    $persisted = ($lookup) ? mysqli_fetch_assoc($lookup) : null;
    mysqli_stmt_close($stmt);
    if (is_array($persisted)) {
        $options[] = ['id' => (int)($persisted['id'] ?? 0), 'label' => (string)($persisted['name'] ?? '')];
    }

    return $options;
}

/**
 * @return array<int, array{id:int,label:string}>
 */
function tickets_user_select_options(mysqli $conn, int $companyId, $selectedId): array
{
    if (!function_exists('itm_user_options_for_company') || !function_exists('itm_user_append_selected_option')) {
        return [];
    }

    $options = itm_user_options_for_company($conn, $companyId);

    return itm_user_append_selected_option($conn, $companyId, $options, $selectedId);
}

function tickets_resolve_created_by_user_id(mysqli $conn, int $companyId): int
{
    $fromPost = (int)($_POST['created_by_user_id'] ?? 0);
    if ($fromPost > 0) {
        return $fromPost;
    }

    $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
    if ($sessionUserId > 0) {
        return $sessionUserId;
    }

    $options = tickets_user_select_options($conn, $companyId, 0);

    return !empty($options) ? (int)$options[0]['id'] : 0;
}

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';
$csrfToken = itm_get_csrf_token();
$ticketUploadPath = TICKET_UPLOAD_PATH;

// Initial state
$data = [
    'ticket_external_code' => '', 'title' => '', 'description' => '',
    'category_id' => '', 'status_id' => '', 'priority_id' => '',
    'created_by_user_id' => (int)($_SESSION['user_id'] ?? 0),
    'assigned_to_user_id' => '', 'asset_id' => '', 'ui_color' => '#0969da',
    'tickets_photos' => '', 'created_at' => date('Y-m-d\TH:i')
];

// Load existing ticket for editing
if ($is_edit) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $q = mysqli_stmt_get_result($stmt);
        if ($q && mysqli_num_rows($q) === 1) {
            $data = mysqli_fetch_assoc($q);
            $data['created_at'] = date('Y-m-d\TH:i', strtotime($data['created_at']));
        } else { $error = 'Ticket not found.'; $is_edit = false; }
        mysqli_stmt_close($stmt);
    }
}

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    
    // Extraction and sanitization
    $ticket_external_code = escape_sql($_POST['ticket_external_code'] ?? '', $conn);
    $title = escape_sql($_POST['title'] ?? '', $conn);
    $description = escape_sql($_POST['description'] ?? '', $conn);
    
    // Normalization of FK fields
    $category_id = (int)($_POST['category_id'] ?? 0) ?: 'NULL';
    $status_id = (int)($_POST['status_id'] ?? 0) ?: 'NULL';
    $priority_id = (int)($_POST['priority_id'] ?? 0) ?: 'NULL';
    $created_by_user_id = (int)($_POST['created_by_user_id'] ?? 0);
    $assigned_to_user_id = (int)($_POST['assigned_to_user_id'] ?? 0) ?: 'NULL';
    $asset_id = (int)($_POST['asset_id'] ?? 0) ?: 'NULL';
    
    // UI Color validation
    $ui_color_raw = strtolower(trim((string)($_POST['ui_color'] ?? '#0969da')));
    $ui_color = preg_match('/^#[0-9a-f]{6}$/', $ui_color_raw) ? $ui_color_raw : '#0969da';
    $ui_color_sql = "'" . escape_sql($ui_color, $conn) . "'";

    // --- PHOTO PROCESSING ---
    $ticketPhotoFilenames = ticket_parse_photo_filenames((string)($data['tickets_photos'] ?? ''));
    $ticketPhotoFilenamesToDeleteAfterSave = [];
    
    // Handle photo removal if requested
    if (!$error && $is_edit && isset($_POST['delete_photo']) && (string)$_POST['delete_photo'] === '1') {
        $ticketPhotoFilenamesToDeleteAfterSave = $ticketPhotoFilenames; $ticketPhotoFilenames = [];
    }

    // Handle new photo uploads
    if (!$error && isset($_FILES['photo']) && is_array($_FILES['photo']['error'])) {
        $uploadTicketId = ticket_resolve_upload_ticket_id($conn, $is_edit, $id);
        foreach ($_FILES['photo']['error'] as $index => $fileError) {
            if ($fileError === UPLOAD_ERR_NO_FILE) continue;
            if ($fileError !== UPLOAD_ERR_OK) { $error = 'Upload failed.'; break; }
            
            $tmpName = (string)$_FILES['photo']['tmp_name'][$index];
            $name = (string)$_FILES['photo']['name'][$index];
            
            if (in_array(ticket_detect_upload_mime_type($tmpName), ALLOWED_TYPES, true)) {
                $ext = strtolower((string)pathinfo($name, PATHINFO_EXTENSION)) ?: 'jpg';
                $photoFilename = 'ticket_' . $uploadTicketId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                if (move_uploaded_file($tmpName, $ticketUploadPath . $photoFilename)) {
                    $ticketPhotoFilenames[] = $photoFilename;
                }
            }
        }
    }

    // --- DB COMMIT ---
    if (!$is_edit && $created_by_user_id <= 0) {
        $created_by_user_id = tickets_resolve_created_by_user_id($conn, (int)$company_id);
    }

    if (!$title) { $error = 'Ticket title is required.'; }
    elseif ($created_by_user_id <= 0) { $error = 'Created by user is required.'; }
    else {
        $photos_sql = empty($ticketPhotoFilenames) ? 'NULL' : "'" . escape_sql(json_encode($ticketPhotoFilenames, JSON_UNESCAPED_SLASHES), $conn) . "'";
        $created_at_val = isset($_POST['created_at']) ? "'" . escape_sql(str_replace('T', ' ', $_POST['created_at']) . ':00', $conn) . "'" : 'CURRENT_TIMESTAMP';

        if ($is_edit) {
            $sql = "UPDATE tickets SET
                        ticket_external_code='$ticket_external_code', title='$title', description='$description',
                        category_id=$category_id, status_id=$status_id, priority_id=$priority_id,
                        created_by_user_id=$created_by_user_id, assigned_to_user_id=$assigned_to_user_id, asset_id=$asset_id,
                        ui_color=$ui_color_sql, tickets_photos=$photos_sql, created_at=$created_at_val
                    WHERE id=$id AND company_id=$company_id";
        } else {
            $sql = "INSERT INTO tickets
                    (company_id, ticket_external_code, title, description, category_id, status_id, priority_id, created_by_user_id, assigned_to_user_id, asset_id, ui_color, tickets_photos, created_at)
                    VALUES
                    ($company_id, '$ticket_external_code', '$title', '$description', $category_id, $status_id, $priority_id, $created_by_user_id, $assigned_to_user_id, $asset_id, $ui_color_sql, $photos_sql, $created_at_val)";
        }

        if (!$error && itm_run_query($conn, $sql)) {
            // Success: Cleanup physical files for removed photos
            foreach ($ticketPhotoFilenamesToDeleteAfterSave as $df) { @unlink($ticketUploadPath . $df); }
            header('Location: index.php'); exit;
        }
    }
}

// FETCH REFERENCE DATA FOR DROPDOWNS
$categoryOptions = tickets_lookup_select_options($conn, 'ticket_categories', (int)$company_id, $data['category_id'] ?? 0, 'name');
$statusOptions = tickets_lookup_select_options($conn, 'ticket_statuses', (int)$company_id, $data['status_id'] ?? 0, 'name');
$priorityOptions = tickets_lookup_select_options($conn, 'ticket_priorities', (int)$company_id, $data['priority_id'] ?? 0, 'level');
$createdByOptions = tickets_user_select_options($conn, (int)$company_id, $data['created_by_user_id'] ?? 0);
$assignedToOptions = tickets_user_select_options($conn, (int)$company_id, $data['assigned_to_user_id'] ?? 0);
$assetOptions = tickets_equipment_select_options($conn, (int)$company_id, $data['asset_id'] ?? 0);
$existingTicketPhotos = ticket_parse_photo_filenames((string)($data['tickets_photos'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'New'; ?> Ticket</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1><?php echo $is_edit ? '✏️ Edit' : '➕ New'; ?> Ticket</h1>
            <?php echo itm_render_alert_errors($error ?? ''); ?>

            <div class="card">
                <form id="ticketForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    
                    <div class="form-row">
                        <div class="form-group"><label>Title *</label><input required name="title" value="<?php echo sanitize($data['title']); ?>"></div>
                        <div class="form-group"><label>External Code</label><input name="ticket_external_code" value="<?php echo sanitize($data['ticket_external_code']); ?>"></div>
                    </div>

                    <div class="form-group"><label>Description</label><textarea name="description"><?php echo sanitize($data['description']); ?></textarea></div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" data-addable-select="1" data-add-table="ticket_categories" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1">
                                <option value="">-- Select --</option>
                                <?php foreach ($categoryOptions as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (string)$data['category_id'] === (string)$c['id'] ? 'selected' : ''; ?>><?php echo sanitize($c['name']); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status_id" data-addable-select="1" data-add-table="ticket_statuses" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1">
                                <option value="">-- Select --</option>
                                <?php foreach ($statusOptions as $s): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo (string)$data['status_id'] === (string)$s['id'] ? 'selected' : ''; ?>><?php echo sanitize($s['name']); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Priority</label>
                            <select name="priority_id" data-addable-select="1" data-add-table="ticket_priorities" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1">
                                <option value="">-- Select --</option>
                                <?php foreach ($priorityOptions as $p): ?>
                                    <option value="<?php echo (int)$p['id']; ?>" <?php echo (string)$data['priority_id'] === (string)$p['id'] ? 'selected' : ''; ?>><?php echo sanitize($p['name']); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Created By</label>
                            <select name="created_by_user_id" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($createdByOptions as $userOption): ?>
                                    <option value="<?php echo (int)$userOption['id']; ?>" <?php echo (string)$data['created_by_user_id'] === (string)$userOption['id'] ? 'selected' : ''; ?>><?php echo sanitize($userOption['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assigned To</label>
                            <select name="assigned_to_user_id">
                                <option value="">-- Select --</option>
                                <?php foreach ($assignedToOptions as $userOption): ?>
                                    <option value="<?php echo (int)$userOption['id']; ?>" <?php echo (string)$data['assigned_to_user_id'] === (string)$userOption['id'] ? 'selected' : ''; ?>><?php echo sanitize($userOption['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Related Asset</label>
                            <select name="asset_id">
                                <option value="">-- Select --</option>
                                <?php foreach ($assetOptions as $assetOption): ?>
                                    <option value="<?php echo (int)$assetOption['id']; ?>" <?php echo (string)$data['asset_id'] === (string)$assetOption['id'] ? 'selected' : ''; ?>><?php echo sanitize($assetOption['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Created At</label>
                            <input type="datetime-local" name="created_at" value="<?php echo sanitize((string)($data['created_at'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Photo Upload</label>
                        <input type="file" name="photo[]" accept="image/*" multiple="">
                        <div class="form-hint" id="currentPhotoHint">
                            <span id="currentPhotoHintText"><?php echo count($existingTicketPhotos); ?> photos current.</span>
                            <button type="button" class="btn btn-sm" id="openPhotoPreview">🔎</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="ui_color" value="<?php echo sanitize($data['ui_color'] ?? '#0969da'); ?>">
                    </div>

                    <div style="display:flex;gap:10px;"><button class="btn btn-primary" type="submit">💾</button><a href="index.php" class="btn">🔙</a></div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- PHOTO PREVIEW MODAL [OMITTED FOR BREVITY] -->
<script src="../../js/theme.js"></script>
<script src="../../js/select-add-option.js"></script>
<script>
    // ... [JS Logic for Modals and Photo Management OMITTED] ...
</script>
</body>
</html>
