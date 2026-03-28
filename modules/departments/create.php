<?php
require '../../config/config.php';
$id = (int)($_GET['id'] ?? 0); $is_edit = $id > 0; $error = '';
$data = ['name'=>'','code'=>'','description'=>'','active'=>1];
if ($is_edit) { $q = mysqli_query($conn, "SELECT * FROM departments WHERE id=$id AND company_id=$company_id LIMIT 1"); if ($q && mysqli_num_rows($q)===1) $data = mysqli_fetch_assoc($q); else { $error='Department not found.'; $is_edit=false; } }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name=escape_sql($_POST['name']??'', $conn); $code=escape_sql($_POST['code']??'', $conn); $description=escape_sql($_POST['description']??'', $conn); $active=isset($_POST['active'])?1:0;
    if (!$name) $error='Department name is required.'; else {
        $sql = $is_edit
            ? "UPDATE departments SET name='$name', code='$code', description='$description', active=$active WHERE id=$id AND company_id=$company_id"
            : "INSERT INTO departments (company_id,name,code,description,active) VALUES ($company_id,'$name','$code','$description',$active)";
        if (mysqli_query($conn,$sql)) { header('Location: index.php'); exit; }
        $error='Database error: '.mysqli_error($conn);
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?php echo $is_edit ? 'Edit' : 'Add'; ?> Department</title><link rel="stylesheet" href="../../css/styles.css"></head><body><div class="container"><?php include '../../includes/sidebar.php'; ?><div class="main-content"><?php include '../../includes/header.php'; ?><div class="content"><h1><?php echo $is_edit ? '✏️ Edit' : '➕ Add'; ?> Department</h1><?php if ($error): ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?><div class="card"><form method="POST"><div class="form-row"><div class="form-group"><label>Name *</label><input required name="name" value="<?php echo sanitize($data['name']); ?>"></div><div class="form-group"><label>Code</label><input name="code" value="<?php echo sanitize($data['code']); ?>"></div></div><div class="form-group"><label>Description</label><textarea name="description"><?php echo sanitize($data['description']); ?></textarea></div><div class="form-group"><label><input type="checkbox" name="active" <?php echo (int)$data['active']===1?'checked':''; ?>> Active</label></div><div style="display:flex;gap:10px;"><button class="btn btn-primary" type="submit">Save</button><a class="btn" href="index.php">Cancel</a></div></form></div></div></div></div><script src="../../js/theme.js"></script></body></html>
