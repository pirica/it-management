<?php
/**
 * Companies Module - Create/Edit
 * 
 * Provides a unified form for adding new companies or updating existing ones.
 * Implements strict input normalization (e.g., InCode uppercase) and
 * comprehensive audit logging for all changes.
 */

require '../../config/config.php';

// Determine if we are in Edit mode based on the presence of an ID
$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';
$csrfToken = itm_get_csrf_token();

// Default form data structure
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

// Load existing data if in Edit mode
if ($is_edit) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM companies WHERE id = ? AND id > 0 LIMIT 1');
    if ($stmt) {
        // Why: bind the prepared statement handle first so edit mode can load the target row safely.
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) === 1) {
            $data = mysqli_fetch_assoc($res);
        } else {
            $error = 'Company not found.';
            $is_edit = false;
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = 'Failed to load company.';
        $is_edit = false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    // Sanitize and normalize inputs
    $company = trim((string)($_POST['company'] ?? ''));
    $incode = strtoupper(substr(trim((string)($_POST['incode'] ?? '')), 0, 6));
    $city = trim((string)($_POST['city'] ?? ''));
    $country = trim((string)($_POST['country'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $website = trim((string)($_POST['website'] ?? ''));
    $vat = trim((string)($_POST['vat'] ?? ''));
    $comments = trim((string)($_POST['comments'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;

    $data = [
        'company' => $company,
        'incode' => $incode,
        'city' => $city,
        'country' => $country,
        'phone' => $phone,
        'email' => $email,
        'website' => $website,
        'vat' => $vat,
        'comments' => $comments,
        'active' => $active,
    ];

    if ($company === '') {
        $error = 'Company is required.';
    } else {
        if ($is_edit) {
            // Process UPDATE
            $old = itm_fetch_audit_record($conn, 'companies', $id, (int)($_SESSION['company_id'] ?? 0));
            $sql = 'UPDATE companies SET company=?, incode=?, city=?, country=?, phone=?, email=?, website=?, vat=?, comments=?, active=? WHERE id=? AND id > 0';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssssssssii', $company, $incode, $city, $country, $phone, $email, $website, $vat, $comments, $active, $id);
                if (mysqli_stmt_execute($stmt)) {
                    itm_log_audit($conn, 'companies', $id, 'UPDATE', $old, $data);
                    mysqli_stmt_close($stmt);
                    header('Location: index.php');
                    exit;
                }
                $error = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
                mysqli_stmt_close($stmt);
            } else {
                $error = 'Failed to update company.';
            }
        } else {
            // Process INSERT
            $sql = 'INSERT INTO companies (company, incode, city, country, phone, email, website, vat, comments, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssssssssi', $company, $incode, $city, $country, $phone, $email, $website, $vat, $comments, $active);
                if (mysqli_stmt_execute($stmt)) {
                    $newId = (int)mysqli_insert_id($conn);
                    itm_log_audit($conn, 'companies', $newId, 'INSERT', null, $data);
                    mysqli_stmt_close($stmt);
                    header('Location: index.php');
                    exit;
                }
                $error = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
                mysqli_stmt_close($stmt);
            } else {
                $error = 'Failed to create company.';
            }
        }
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
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
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
                    <div class="form-group"><label class="role-flag-option"><input type="checkbox" name="active" <?php echo (int)($data['active'] ?? 0) === 1 ? 'checked' : ''; ?>> <span>Active</span></label></div>
                    <div style="display:flex;gap:10px;"><button class="btn btn-primary" type="submit">💾</button><a href="index.php" class="btn">🔙</a></div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
