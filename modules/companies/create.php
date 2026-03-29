<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';

$data = [
    'company' => '',
    'incode' => '',
    'city' => '',
    'country' => '',
    'phone' => '',
    'email' => '',
    'website' => '',
    'vat' => '',
    'comments' => '',
    'active' => 1,
];

if ($is_edit) {
    $q = mysqli_query($conn, "SELECT * FROM companies WHERE id = $id LIMIT 1");
    if ($q && mysqli_num_rows($q) === 1) {
        $data = mysqli_fetch_assoc($q);
    } else {
        $error = 'Company not found.';
        $is_edit = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company = escape_sql($_POST['company'] ?? '', $conn);
    $incode = strtoupper(substr(escape_sql($_POST['incode'] ?? '', $conn), 0, 6));
    $city = escape_sql($_POST['city'] ?? '', $conn);
    $country = escape_sql($_POST['country'] ?? '', $conn);
    $phone = escape_sql($_POST['phone'] ?? '', $conn);
    $email = escape_sql($_POST['email'] ?? '', $conn);
    $website = escape_sql($_POST['website'] ?? '', $conn);
    $vat = escape_sql($_POST['vat'] ?? '', $conn);
    $comments = escape_sql($_POST['comments'] ?? '', $conn);
    $active = isset($_POST['active']) ? 1 : 0;

    if (!$company) {
        $error = 'Company is required.';
    } else {
        if ($is_edit) {
            $sql = "UPDATE companies SET company='$company', incode='$incode', city='$city', country='$country', phone='$phone', email='$email', website='$website', vat='$vat', comments='$comments', active=$active WHERE id=$id";
        } else {
            $sql = "INSERT INTO companies (company, incode, city, country, phone, email, website, vat, comments, active) VALUES ('$company','$incode','$city','$country','$phone','$email','$website','$vat','$comments',$active)";
        }

        if (mysqli_query($conn, $sql)) {
            header('Location: index.php');
            exit;
        }
        $error = 'Database error: ' . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Company</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1><?php echo $is_edit ? '✏️ Edit' : '➕ Add'; ?> Company</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?>
            <div class="card">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group"><label>Company *</label><input type="text" name="company" required value="<?php echo sanitize($data['company'] ?? ''); ?>"></div>
                        <div class="form-group"><label>InCode</label><input type="text" name="incode" maxlength="6" size="6" value="<?php echo sanitize($data['incode'] ?? ''); ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>City</label><input type="text" name="city" value="<?php echo sanitize($data['city'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Country</label><input type="text" name="country" value="<?php echo sanitize($data['country'] ?? ''); ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo sanitize($data['phone'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize($data['email'] ?? ''); ?>"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Website</label><input type="url" name="website" value="<?php echo sanitize($data['website'] ?? ''); ?>"></div>
                        <div class="form-group"><label>VAT</label><input type="text" name="vat" value="<?php echo sanitize($data['vat'] ?? ''); ?>"></div>
                    </div>
                    <div class="form-group"><label>Comments</label><textarea name="comments" rows="4"><?php echo sanitize($data['comments'] ?? ''); ?></textarea></div>
                    <div class="form-group"><label><input type="checkbox" name="active" <?php echo (int)($data['active'] ?? 0) === 1 ? 'checked' : ''; ?>> Active</label></div>
                    <div style="display:flex;gap:10px;"><button class="btn btn-primary" type="submit">💾</button><a href="index.php" class="btn">✖️</a></div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
