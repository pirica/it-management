<?php
require_once '../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];
$username = $_SESSION['username'];

$stmt = $conn->prepare("SELECT * FROM private_contacts WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$contact = $stmt->get_result()->fetch_assoc();

if (!$contact) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $photo = $contact['photo'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['photo']['tmp_name']);
        if ($mime === 'image/png') {
            $photoFilename = $id . '_photo.png';
            $dir = "../../files/$companyId/Private/{$username}_{$userId}/private_contacts";

            // Debug
            echo "DIR: $dir\n";
            echo "REALPATH (parent): " . realpath(dirname($dir)) . "\n";
            error_log("Creating directory: " . realpath(dirname($dir)) . " -> $dir");

            $can_write = true;
            if (file_exists("$dir/$photoFilename") && ($_POST['confirm_replace'] ?? '0') !== '1') {
                $can_write = false;
            }

            if ($can_write) {
                if (!is_dir($dir)) {
                    if (!mkdir($dir, 0777, true)) {
                        echo "MKDIR FAILED\n";
                        echo "Last error: ";
                        print_r(error_get_last());
                        error_log("MKDIR FAILED: $dir");
                        @chmod($dir, 0777);
                    } else {
                        echo "MKDIR SUCCESS\n";
                        chmod($dir, 0777);
                    }
                } else {
                    chmod($dir, 0777);
                }

                if (move_uploaded_file($_FILES['photo']['tmp_name'], "$dir/$photoFilename")) {
                    $photo = $photoFilename;
                }
            }
        }
    }

    $sql = "UPDATE private_contacts SET
        name_prefix = ?, first_name = ?, middle_name = ?, last_name = ?, name_suffix = ?,
        phonetic_first_name = ?, phonetic_middle_name = ?, phonetic_last_name = ?, nickname = ?, file_as = ?,
        email1_label = ?, email1_value = ?, phone1_label = ?, phone1_value = ?,
        address1_label = ?, address1_country = ?, address1_street = ?, address1_extended = ?, address1_city = ?, address1_region = ?, address1_postcode = ?, address1_po_box = ?,
        organization_name = ?, organization_title = ?, organization_department = ?,
        birthday = ?, event1_label = ?, event1_value = ?, relation1_label = ?, relation1_value = ?,
        website1_label = ?, website1_value = ?, custom_field1_label = ?, custom_field1_value = ?,
        notes = ?, labels = ?, photo = ?, is_favorite = ?
        WHERE id = ? AND user_id = ?";

    $stmt = $conn->prepare($sql);

    $is_favorite = isset($_POST['is_favorite']) ? 1 : 0;
    $birthday = $_POST['birthday'] ?: null;
    $event1_value = $_POST['event1_value'] ?: null;

    $stmt->bind_param("sssssssssssssssssssssssssssssssssssssiii",
        $_POST['name_prefix'], $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'], $_POST['name_suffix'],
        $_POST['phonetic_first_name'], $_POST['phonetic_middle_name'], $_POST['phonetic_last_name'], $_POST['nickname'], $_POST['file_as'],
        $_POST['email1_label'], $_POST['email1_value'], $_POST['phone1_label'], $_POST['phone1_value'],
        $_POST['address1_label'], $_POST['address1_country'], $_POST['address1_street'], $_POST['address1_extended'], $_POST['address1_city'], $_POST['address1_region'], $_POST['address1_postcode'], $_POST['address1_po_box'],
        $_POST['organization_name'], $_POST['organization_title'], $_POST['organization_department'],
        $birthday, $_POST['event1_label'], $event1_value, $_POST['relation1_label'], $_POST['relation1_value'],
        $_POST['website1_label'], $_POST['website1_value'], $_POST['custom_field1_label'], $_POST['custom_field1_value'],
        $_POST['notes'], $_POST['labels'], $photo, $is_favorite,
        $id, $userId
    );

    if ($stmt->execute()) {
        header("Location: view.php?id=$id&msg=updated");
    } else {
        echo "Error: " . $stmt->error;
    }
    exit();
}

$pageTitle = "Edit Private Contact";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> Management</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i>🔙</a>
                <h1>Edit Private Contact</h1>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                <?php include 'edit_form.php'; ?>
                <div class="form-actions" style="margin-top:20px; display:flex; gap:10px;">
                    <button class="btn btn-primary" type="submit">💾</button>
                    <a href="view.php?id=<?php echo $id; ?>" class="btn">🔙</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
