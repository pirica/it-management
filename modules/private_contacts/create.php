<?php
require_once '../../config/config.php';
itm_require_crud_role_module_permission($conn, 'create', 'private_contacts');

require_once __DIR__ . '/includes/private_contact_photo.php';
require_once __DIR__ . '/pc_vault_bootstrap.php';
require_once __DIR__ . '/pc_vault_helpers.php';
require_once __DIR__ . '/pc_contact_form_helpers.php';

if (!isset($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit();
}

$employeeId = (int)$_SESSION['employee_id'];
$companyId = (int)$_SESSION['company_id'];
$username = (string)$_SESSION['username'];
$csrfToken = itm_get_csrf_token();
$pcVaultState = pc_handle_vault_requests($conn, $employeeId);
$pcVaultUnlocked = !empty($pcVaultState['unlocked']);
$pcVaultRedirect = 'create.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    if (!$pcVaultUnlocked) {
        header('Location: create.php');
        exit();
    }

    $plainRow = pc_contact_plain_row_from_post($_POST);
    $storedFields = pc_prepare_contact_fields_from_plain($plainRow);
    if ($storedFields === null) {
        die('Unlock your vault before saving a private contact.');
    }

    $sql = 'INSERT INTO private_contacts (
        company_id, employee_id, name_prefix, first_name, middle_name, last_name, name_suffix,
        phonetic_first_name, phonetic_middle_name, phonetic_last_name, nickname, file_as,
        email1_label, email1_value, phone1_label, phone1_value,
        address1_label, address1_country, address1_street, address1_extended, address1_city, address1_region, address1_postcode, address1_po_box,
        organization_name, organization_title, organization_department,
        birthday, event1_label, event1_value, relation1_label, relation1_value,
        website1_label, website1_value, custom_field1_label, custom_field1_value,
        notes, labels, is_favorite
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = $conn->prepare($sql);

    $is_favorite = isset($_POST['is_favorite']) ? 1 : 0;
    $birthday = $plainRow['birthday'] !== '' ? $plainRow['birthday'] : null;
    $event1_value = $plainRow['event1_value'] !== '' ? $plainRow['event1_value'] : null;

    $stmt->bind_param(
        'iissssssssssssssssssssssssssssssssssssi',
        $companyId,
        $employeeId,
        $storedFields['name_prefix'],
        $storedFields['first_name'],
        $storedFields['middle_name'],
        $storedFields['last_name'],
        $storedFields['name_suffix'],
        $storedFields['phonetic_first_name'],
        $storedFields['phonetic_middle_name'],
        $storedFields['phonetic_last_name'],
        $storedFields['nickname'],
        $storedFields['file_as'],
        $storedFields['email1_label'],
        $storedFields['email1_value'],
        $storedFields['phone1_label'],
        $storedFields['phone1_value'],
        $storedFields['address1_label'],
        $storedFields['address1_country'],
        $storedFields['address1_street'],
        $storedFields['address1_extended'],
        $storedFields['address1_city'],
        $storedFields['address1_region'],
        $storedFields['address1_postcode'],
        $storedFields['address1_po_box'],
        $storedFields['organization_name'],
        $storedFields['organization_title'],
        $storedFields['organization_department'],
        $birthday,
        $storedFields['event1_label'],
        $event1_value,
        $storedFields['relation1_label'],
        $storedFields['relation1_value'],
        $storedFields['website1_label'],
        $storedFields['website1_value'],
        $storedFields['custom_field1_label'],
        $storedFields['custom_field1_value'],
        $storedFields['notes'],
        $storedFields['labels'],
        $is_favorite
    );

    if ($stmt->execute()) {
        $insertId = (int)$stmt->insert_id;
        $redirect = 'index.php?msg=created';
        if (isset($_FILES['photo']) && (int)$_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $photoResult = pc_contact_photo_store_upload(
                $_FILES['photo'],
                $insertId,
                $companyId,
                $username,
                $employeeId
            );
            if (($photoResult['ok'] ?? false) && ($photoResult['filename'] ?? '') !== '') {
                $photoFilename = (string)$photoResult['filename'];
                $updateStmt = $conn->prepare('UPDATE private_contacts SET photo = ? WHERE id = ? AND employee_id = ?');
                $updateStmt->bind_param('sii', $photoFilename, $insertId, $employeeId);
                $updateStmt->execute();
                $redirect = 'view.php?id=' . $insertId . '&msg=created';
            } elseif (!($photoResult['ok'] ?? false) && ($photoResult['error'] ?? '') !== '') {
                $redirect = 'view.php?id=' . $insertId . '&msg=created&photo_error=' . rawurlencode((string)$photoResult['error']);
            }
        }
        header('Location: ' . $redirect);
    } else {
        echo "Error: " . $stmt->error;
    }
    exit();
}

$contact = [];
$pageTitle = "Add Private Contact";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Private Contacts';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if (pc_ui_requires_vault_lock_screen($pcVaultState)): ?>
                <?php pc_render_vault_lock_screen($csrfToken, $pcVaultState, $pcVaultRedirect); ?>
            <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i>🔙</a>
                <h1>Create Private Contact</h1>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                <?php include 'edit_form.php'; ?>
                <div class="form-actions" style="margin-top:20px; display:flex; gap:10px;">
                    <button class="btn btn-primary" type="submit">💾</button>
                    <a href="index.php" class="btn">🔙</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
