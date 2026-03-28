<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$error = '';
$data = ['username'=>'','email'=>'','first_name'=>'','last_name'=>'','phone'=>'','role'=>'user','access_level'=>'read_only','active'=>1];

if ($is_edit) {
    $q = mysqli_query($conn, "SELECT * FROM users WHERE id = $id AND company_id = $company_id LIMIT 1");
    if ($q && mysqli_num_rows($q) === 1) $data = mysqli_fetch_assoc($q);
    else { $error = 'User not found.'; $is_edit = false; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = escape_sql($_POST['username'] ?? '', $conn);
    $email = escape_sql($_POST['email'] ?? '', $conn);
    $first_name = escape_sql($_POST['first_name'] ?? '', $conn);
    $last_name = escape_sql($_POST['last_name'] ?? '', $conn);
    $phone = escape_sql($_POST['phone'] ?? '', $conn);
    $role = escape_sql($_POST['role'] ?? 'user', $conn);
    $access_level = escape_sql($_POST['access_level'] ?? 'read_only', $conn);
    $active = isset($_POST['active']) ? 1 : 0;

    if (!$username) $error = 'Username is required.';
    else {
        if ($is_edit) {
            $sql = "UPDATE users SET username='$username', email='$email', first_name='$first_name', last_name='$last_name', phone='$phone', role='$role', access_level='$access_level', active=$active WHERE id=$id AND company_id=$company_id";
        } else {
            $password = password_hash('ChangeMe123!', PASSWORD_BCRYPT);
            $sql = "INSERT INTO users (company_id, username, email, password, first_name, last_name, phone, role, access_level, active) VALUES ($company_id, '$username', '$email', '$password', '$first_name', '$last_name', '$phone', '$role', '$access_level', $active)";
        }
        if (mysqli_query($conn, $sql)) { header('Location: index.php'); exit; }
        $error = 'Database error: ' . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo $is_edit ? 'Edit' : 'Add'; ?> User</title><link rel="stylesheet" href="../../css/styles.css"></head><body>
<div class="container"><?php include '../../includes/sidebar.php'; ?><div class="main-content"><?php include '../../includes/header.php'; ?><div class="content"><h1><?php echo $is_edit ? '✏️ Edit' : '➕ Add'; ?> User</h1><?php if ($error): ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?><div class="card"><form method="POST">
<div class="form-row"><div class="form-group"><label>Username *</label><input type="text" name="username" required value="<?php echo sanitize($data['username']); ?>"></div><div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize($data['email']); ?>"></div></div>
<div class="form-row"><div class="form-group"><label>First Name</label><input type="text" name="first_name" value="<?php echo sanitize($data['first_name']); ?>"></div><div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?php echo sanitize($data['last_name']); ?>"></div></div>
<div class="form-row"><div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo sanitize($data['phone']); ?>"></div><div class="form-group"><label>Role</label><select name="role"><?php foreach (['admin','it_manager','it_technician','helpdesk','user'] as $role): ?><option value="<?php echo $role; ?>" <?php echo $data['role'] === $role ? 'selected' : ''; ?>><?php echo $role; ?></option><?php endforeach; ?></select></div></div>
<div class="form-row"><div class="form-group"><label>Access Level</label><select name="access_level"><?php foreach (['full','read_only','limited'] as $level): ?><option value="<?php echo $level; ?>" <?php echo $data['access_level'] === $level ? 'selected' : ''; ?>><?php echo $level; ?></option><?php endforeach; ?></select></div><div class="form-group"><label><input type="checkbox" name="active" <?php echo (int)$data['active'] === 1 ? 'checked' : ''; ?>> Active</label></div></div>
<?php if (!$is_edit): ?><div class="form-hint">Default password for new users: ChangeMe123!</div><?php endif; ?>
<div style="display:flex;gap:10px;margin-top:20px;"><button type="submit" class="btn btn-primary">Save</button><a href="index.php" class="btn">Cancel</a></div>
</form></div></div></div></div><script src="../../js/theme.js"></script></body></html>
