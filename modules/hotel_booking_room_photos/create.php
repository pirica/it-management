<?php
require '../../config/config.php';
require_once '../../includes/itm_hotel_booking.php';
itm_require_crud_role_module_permission($conn, 'create', 'hotel_booking_room_photos');

$crud_table = 'hotel_booking_room_photos';
$crud_title = 'Room Photos';
$listUrl = 'index.php';
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

$errors = [];
$successMessage = '';

// Load rooms for dropdown
$rooms = [];
$roomQuery = mysqli_query($conn, 'SELECT id, room_number, name FROM hotel_booking_rooms WHERE company_id = ' . (int)$company_id . ' AND deleted_at IS NULL ORDER BY room_number ASC');
while ($roomQuery && ($roomRow = mysqli_fetch_assoc($roomQuery))) {
    $rooms[] = $roomRow;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
    $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
    $isCover = isset($_POST['is_cover']) ? 1 : 0;
    $isActive = isset($_POST['active']) ? 1 : 0;

    if ($roomId <= 0) {
        $errors[] = 'Please select a Room.';
    }

    if (empty($_FILES['photo_files']['name']) || !is_array($_FILES['photo_files']['name']) || empty(array_filter($_FILES['photo_files']['name']))) {
        $errors[] = 'Please select at least one photo file to upload.';
    }

    if (empty($errors)) {
        $names = $_FILES['photo_files']['name'];
        $tmpNames = $_FILES['photo_files']['tmp_name'];
        $errs = $_FILES['photo_files']['error'];

        $scope = 'room';
        $relDir = itm_hotel_booking_photo_storage_dir($company_id, $scope, $roomId);
        $absDir = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);

        if (!function_exists('itm_ensure_upload_directory')) {
            require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
        }
        itm_ensure_upload_directory($absDir, 'upload');

        $insertedCount = 0;
        $count = count($names);

        for ($i = 0; $i < $count; $i++) {
            if ((int)($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $orig = basename((string)$names[$i]);
            if ($orig === '' || !is_uploaded_file($tmpNames[$i])) {
                continue;
            }
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $errors[] = "File '{$orig}' has an invalid extension. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.";
                continue;
            }

            $stored = 'hb_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $dest = $absDir . DIRECTORY_SEPARATOR . $stored;

            if (move_uploaded_file($tmpNames[$i], $dest)) {
                // Prepare INSERT statement with audit fields
                $stmt = mysqli_prepare($conn, 'INSERT INTO hotel_booking_room_photos (company_id, room_id, stored_filename, original_filename, sort_order, is_cover, active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                if ($stmt) {
                    $employeeId = (int)($_SESSION['employee_id'] ?? 0);
                    // If multiple files are uploaded, only the first one gets the Cover status if checked, or we can apply it to all
                    $currentIsCover = ($insertedCount === 0) ? $isCover : 0;
                    mysqli_stmt_bind_param($stmt, 'iissiiii', $company_id, $roomId, $stored, $orig, $sortOrder, $currentIsCover, $isActive, $employeeId);
                    if (mysqli_stmt_execute($stmt)) {
                        $insertedCount++;
                        $sortOrder++; // Increment sort order for successive uploads
                    } else {
                        $errors[] = "Failed to save photo metadata in database for '{$orig}': " . mysqli_error($conn);
                        // Delete the file if DB insert failed to keep it clean
                        @unlink($dest);
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $errors[] = "Failed to prepare query: " . mysqli_error($conn);
                }
            } else {
                $errors[] = "Failed to move uploaded file '{$orig}' to destination.";
            }
        }

        if ($insertedCount > 0) {
            $_SESSION['crud_success'] = "Successfully uploaded {$insertedCount} photo(s).";
            header('Location: ' . $listUrl);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Room Photo - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config)); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <h1>New Room Photo</h1>
            <form method="POST" enctype="multipart/form-data" class="form-grid" style="max-width:980px;">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">

                <div class="form-group">
                    <label for="room_id">Room</label>
                    <select name="room_id" id="room_id" required>
                        <option value="">-- Select Room --</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo (int)$room['id']; ?>">Room <?php echo sanitize($room['room_number']); ?> - <?php echo sanitize($room['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="photo_files">Select Photo(s)</label>
                    <input type="file" name="photo_files[]" id="photo_files" accept="image/jpeg,image/png,image/gif,image/webp" multiple required>
                    <small style="opacity: 0.7; display: block; margin-top: 4px;">You can select multiple files at once. Allowed formats: JPG, JPEG, PNG, GIF, WEBP.</small>
                </div>

                <div class="form-group">
                    <label for="sort_order">Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" value="0" min="0">
                </div>

                <div class="form-group">
                    <label>Is Cover</label>
                    <label class="itm-checkbox-control">
                        <input type="checkbox" name="is_cover" value="1">
                        <span>Is Cover <span class="itm-check-indicator" aria-hidden="true">❌</span></span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Active</label>
                    <label class="itm-checkbox-control">
                        <input type="checkbox" name="active" value="1" checked>
                        <span>Active <span class="itm-check-indicator" aria-hidden="true">✅</span></span>
                    </label>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit" title="Save">💾</button>
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script>
document.addEventListener('change', function (event) {
    if (!event.target.matches('.itm-checkbox-control input[type="checkbox"]')) return;
    const indicator = event.target.closest('.itm-checkbox-control')?.querySelector('.itm-check-indicator');
    if (indicator) {
        indicator.textContent = event.target.checked ? '✅' : '❌';
    }
});
</script>
</body>
</html>
