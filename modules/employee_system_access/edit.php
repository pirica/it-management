<?php
require '../../config/config.php';
require '../../includes/employee_system_access.php';

esa_ensure_table($conn);

$employeeId = (int)($_GET['employee_id'] ?? $_POST['employee_id'] ?? 0);
if ($employeeId <= 0) {
    header('Location: index.php');
    exit;
}

$employeeSql = 'SELECT id, first_name, last_name, display_name, email FROM employees WHERE id=' . $employeeId . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
$employeeRes = mysqli_query($conn, $employeeSql);
$employee = ($employeeRes && mysqli_num_rows($employeeRes) === 1) ? mysqli_fetch_assoc($employeeRes) : null;
if (!$employee) {
    header('Location: index.php');
    exit;
}

$abilityFields = esa_ability_fields();
$form = [];
$current = esa_get_employee_access($conn, (int)$company_id, $employeeId);
foreach (array_keys($abilityFields) as $field) {
    $form[$field] = ((int)($current[$field] ?? 0) === 1) ? '1' : '0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($abilityFields) as $field) {
        $form[$field] = isset($_POST[$field]) ? '1' : '0';
    }
    esa_save_employee_access($conn, (int)$company_id, $employeeId, $form);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee System Access</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;">
                <h1 style="margin:0;">Edit Access: <?php echo sanitize((string)($employee['display_name'] ?: trim((string)$employee['first_name'] . ' ' . (string)$employee['last_name']))); ?></h1>
                <a href="index.php" class="btn">← Back</a>
            </div>
            <div class="card">
                <p style="margin-top:0;"><strong>Email:</strong> <?php echo sanitize((string)($employee['email'] ?? '')); ?></p>
                <form method="POST">
                    <input type="hidden" name="employee_id" value="<?php echo (int)$employeeId; ?>">
                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;">
                        <?php foreach ($abilityFields as $field => $label): ?>
                            <label><input type="checkbox" name="<?php echo sanitize($field); ?>" value="1" <?php echo (($form[$field] ?? '0') === '1') ? 'checked' : ''; ?>> <?php echo sanitize($label); ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-actions" style="margin-top:16px;">
                        <button type="submit" class="btn btn-primary">Save Access</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
