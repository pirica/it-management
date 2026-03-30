<?php
require '../../config/config.php';
require '../../includes/employee_system_access.php';
esa_ensure_table($conn);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$sql = "SELECT e.*, d.name AS department_name, okd.name AS office_key_card_department_name, es.name AS employment_status_name,
            esa.network_access, esa.micros_emc, esa.opera_username, esa.micros_card, esa.pms_id, esa.synergy_mms,
            esa.hu_the_lobby, esa.navision, esa.onq_ri, esa.birchstreet, esa.delphi, esa.omina, esa.vingcard_system,
            esa.digital_rev, esa.office_key_card
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN departments okd ON okd.id = e.office_key_card_department_id
        LEFT JOIN employee_statuses es ON es.id = e.employment_status_id
        LEFT JOIN employee_system_access esa ON esa.company_id = e.company_id AND esa.employee_id = e.id
        WHERE e.id = {$id} AND e.company_id = " . (int)$company_id . "
        LIMIT 1";
$res = mysqli_query($conn, $sql);
$employee = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;

$abilityFields = array_keys(esa_ability_fields());
$booleanFields = array_merge(['active', 'duplicate'], $abilityFields);
$hiddenFields = ['company_id', 'user_id', 'location_id', 'phone', 'location'];

function emp_label($field) {
    $map = [
        'raw_status_code' => 'Raw Status',
        'employment_status_id' => 'Employment Status ID',
        'employment_status_name' => 'Employment Status',
        'department_id' => 'Department ID',
        'department_name' => 'Department',
        'office_key_card_department_id' => 'Office Key Card Department ID',
        'office_key_card_department_name' => 'Office Key Card Department',
        'hilton_id' => 'Hilton ID',
    ];
    foreach (esa_ability_fields() as $field => $label) {
        $map[$field] = $label;
    }
    if (isset($map[$field])) return $map[$field];
    if ($field === 'id') return 'ID';
    return ucwords(str_replace('_', ' ', $field));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Employee</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>🔎 View Employee</h1>
            <div class="card">
                <?php if (!$employee): ?>
                    <div class="alert alert-danger">Employee not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <?php foreach ($employee as $field => $value): ?>
                            <?php if (in_array($field, $hiddenFields, true)) { continue; } ?>
                            <?php
                                if (in_array($field, $booleanFields, true)) {
                                    $display = ((int)$value === 1) ? '✔️' : '❌';
                                } elseif ($field === 'email' && (string)$value !== '') {
                                    $safe = sanitize((string)$value);
                                    $display = '<a href="mailto:' . $safe . '">' . $safe . '</a>';
                                } else {
                                    $display = sanitize((string)($value ?? ''));
                                }
                            ?>
                            <tr>
                                <th style="width:260px;"><?php echo sanitize(emp_label($field)); ?></th>
                                <td><?php echo $display === '' ? '-' : $display; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn">Back</a>
                    <?php if ($employee): ?>
                        <a href="edit.php?id=<?php echo (int)$employee['id']; ?>" class="btn btn-primary">✏️ Edit</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
