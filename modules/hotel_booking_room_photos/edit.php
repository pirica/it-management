<?php
require '../../config/config.php';
require_once '../../includes/itm_hotel_booking.php';
itm_require_crud_role_module_permission($conn, 'edit', 'hotel_booking_room_photos');

$crud_table = 'hotel_booking_room_photos';
$crud_title = 'Room Photos';
$listUrl = 'index.php';
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;

$errors = [];
$successMessage = '';

$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($editId <= 0) {
    $_SESSION['crud_error'] = 'Invalid photo ID.';
    header('Location: ' . $listUrl);
    exit;
}

// Fetch existing record
$where = ' WHERE id = ' . $editId;
if ($company_id > 0) {
    $where .= ' AND company_id = ' . (int)$company_id;
}
$q = mysqli_query($conn, 'SELECT * FROM hotel_booking_room_photos' . $where . ' LIMIT 1');
$data = ($q && mysqli_num_rows($q) === 1) ? mysqli_fetch_assoc($q) : null;
if (!$data) {
    $_SESSION['crud_error'] = 'Photo record not found.';
    header('Location: ' . $listUrl);
    exit;
}

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

    // Check if a file was uploaded to replace the existing photo
    $hasNewFile = !empty($_FILES['photo_file']['name']);
    $stored = $data['stored_filename'];
    $orig = $data['original_filename'];

    if ($hasNewFile && empty($errors)) {
        $orig = basename((string)$_FILES['photo_file']['name']);
        $tmpName = $_FILES['photo_file']['tmp_name'];
        $err = $_FILES['photo_file']['error'];

        if ((int)$err !== UPLOAD_ERR_OK) {
            $errors[] = 'Error during file upload.';
        } else {
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $errors[] = "File '{$orig}' has an invalid extension. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.";
            } else {
                $scope = 'room';
                $relDir = itm_hotel_booking_photo_storage_dir($company_id, $scope, $roomId);
                $absDir = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);

                if (!function_exists('itm_ensure_upload_directory')) {
                    require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
                }
                itm_ensure_upload_directory($absDir, 'upload');

                $newStored = itm_hotel_booking_photo_random_stored_filename($ext, $absDir);
                if ($newStored === '') {
                    $errors[] = "File '{$orig}' has an invalid extension. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.";
                } else {
                    $dest = $absDir . DIRECTORY_SEPARATOR . $newStored;

                    if (move_uploaded_file($tmpName, $dest)) {
                        // Delete old file if it exists and we're either changing the room or just replacing the image
                        $oldRelDir = itm_hotel_booking_photo_storage_dir($company_id, $scope, $data['room_id']);
                        $oldAbsDir = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldRelDir);
                        $oldFile = $oldAbsDir . DIRECTORY_SEPARATOR . $data['stored_filename'];
                        if (is_file($oldFile)) {
                            @unlink($oldFile);
                        }
                        $stored = $newStored;
                    } else {
                        $errors[] = 'Failed to save the new uploaded file.';
                    }
                }
            }
        }
    } elseif (!$hasNewFile && $roomId !== (int)$data['room_id'] && empty($errors)) {
        // If room is changed but no new file is uploaded, move the physical file to the new room's directory!
        $scope = 'room';

        $oldRelDir = itm_hotel_booking_photo_storage_dir($company_id, $scope, $data['room_id']);
        $oldAbsDir = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldRelDir);
        $oldFile = $oldAbsDir . DIRECTORY_SEPARATOR . $data['stored_filename'];

        $newRelDir = itm_hotel_booking_photo_storage_dir($company_id, $scope, $roomId);
        $newAbsDir = rtrim(ROOT_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newRelDir);

        if (!function_exists('itm_ensure_upload_directory')) {
            require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
        }
        itm_ensure_upload_directory($newAbsDir, 'upload');
        $newFile = $newAbsDir . DIRECTORY_SEPARATOR . $data['stored_filename'];

        if (is_file($oldFile)) {
            if (rename($oldFile, $newFile)) {
                // Moved successfully
            } else {
                $errors[] = 'Failed to move photo file to the new room directory.';
            }
        }
    }

    if (empty($errors)) {
        if ($isCover) {
            itm_hotel_booking_photo_clear_cover_for_parent($conn, (int)$company_id, 'hotel_booking_room_photos', 'room_id', $roomId, $editId);
        }

        $stmt = mysqli_prepare($conn, 'UPDATE hotel_booking_room_photos SET room_id = ?, stored_filename = ?, original_filename = ?, sort_order = ?, is_cover = ?, active = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
        if ($stmt) {
            $employeeId = (int)($_SESSION['employee_id'] ?? 0);
            mysqli_stmt_bind_param($stmt, 'issiitiii', $roomId, $stored, $orig, $sortOrder, $isCover, $isActive, $employeeId, $editId, $company_id);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['crud_success'] = 'Photo metadata successfully updated.';
                header('Location: ' . $listUrl);
                exit;
            } else {
                $errors[] = 'Failed to update database record: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = 'Failed to prepare database update query.';
        }
    }
}

$photoUrl = itm_hotel_booking_photo_public_url($company_id, 'room', $data['room_id'], $data['stored_filename']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $pageTitle = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), basename(dirname($_SERVER['PHP_SELF'])), (string)($crud_title ?? ''));
    ?>
    <title><?php echo sanitize($pageTitle); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config)); ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <h1 title="Edit">✏️</h1>
            <form method="POST" enctype="multipart/form-data" class="form-grid" style="max-width:980px;">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">

                <div class="form-group" style="grid-column: 1 / -1; display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label>Current Photo</label>
                        <div style="width: 200px; height: 150px; overflow: hidden; border-radius: 6px; background-color: #1e1e1e;">
                            <img src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Current Room Photo" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="room_id">Room</label>
                    <select name="room_id" id="room_id" required>
                        <option value="">-- Select Room --</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo (int)$room['id']; ?>" <?php echo ((int)$data['room_id'] === (int)$room['id']) ? 'selected' : ''; ?>>Room <?php echo sanitize($room['room_number']); ?> - <?php echo sanitize($room['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="photo_file">Replace Photo (Optional)</label>
                    <input type="file" name="photo_file" id="photo_file" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small style="opacity: 0.7; display: block; margin-top: 4px;">Leave blank to keep the current photo. Allowed formats: JPG, JPEG, PNG, GIF, WEBP.</small>
                </div>

                <div class="form-group">
                    <label for="sort_order">Sort Order</label>
                    <input type="number" name="sort_order" id="sort_order" value="<?php echo (int)$data['sort_order']; ?>" min="0">
                </div>

                <div class="form-group">
                    <label>Is Cover</label>
                    <label class="itm-checkbox-control">
                        <input type="checkbox" name="is_cover" value="1" <?php echo ((int)$data['is_cover'] === 1) ? 'checked' : ''; ?>>
                        <span>Is Cover <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)$data['is_cover'] === 1) ? '✅' : '❌'; ?></span></span>
                    </label>
                </div>

                <div class="form-group">
                    <label>Active</label>
                    <label class="itm-checkbox-control">
                        <input type="checkbox" name="active" value="1" <?php echo ((int)$data['active'] === 1) ? 'checked' : ''; ?>>
                        <span>Active <span class="itm-check-indicator" aria-hidden="true"><?php echo ((int)$data['active'] === 1) ? '✅' : '❌'; ?></span></span>
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
