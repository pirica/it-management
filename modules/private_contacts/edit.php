<?php
require_once '../../config/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];

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
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photoFilename = $id . '_photo.' . $ext;
        $dir = "../../files/$companyId/Private/{$_SESSION['username']}_{$userId}/private_contacts";
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (move_uploaded_file($_FILES['photo']['tmp_name'], "$dir/$photoFilename")) {
            $photo = $photoFilename;
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

    $stmt->bind_param("ssssssssssssssssssssssssssssssssssssii",
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
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
        <h1>Edit Private Contact</h1>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
        <?php include 'edit_form.php'; ?>
        <div class="card mt-4 mb-5">
            <div class="card-body text-center">
                <button type="submit" class="btn btn-primary btn-lg px-5"><i class="fas fa-save"></i> Save Contact</button>
                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-link text-muted">Cancel</a>
            </div>
        </div>
    </form>
</div>

</body></html>
