<?php
/**
 * Employees Module - View
 * 
 * Provides a detailed, read-only view of an employee's profile.
 * Displays all assigned attributes and includes a summary of their
 * system access permissions.
 */

require '../../config/config.php';
require '../../includes/employee_system_access.php';
esa_ensure_table($conn);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch employee with joined labels for department and status
$sql = "SELECT e.*, d.name AS department_name, okd.name AS office_key_card_department_name, es.name AS employment_status_name
        FROM employees e
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN departments okd ON okd.id = e.office_key_card_department_id
        LEFT JOIN employee_statuses es ON es.id = e.employment_status_id
        WHERE e.id = {$id} AND e.company_id = " . (int)$company_id . "
        LIMIT 1";
$res = mysqli_query($conn, $sql);
$employee = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;

$booleanFields = ['duplicate'];
$hiddenFields = ['company_id', 'user_id', 'location_id', 'phone', 'location'];

// Fetch a list of all systems this employee currently has access to
$systemAccessNames = [];
if ($employee) {
    $saSql = 'SELECT sa.name FROM employee_system_access_relations esar '
        . 'INNER JOIN system_access sa ON sa.id = esar.system_access_id '
        . 'WHERE esar.company_id=' . (int)$company_id . ' AND esar.employee_id=' . (int)$employee['id'] . ' AND esar.granted=1 '
        . 'ORDER BY sa.name ASC';
    $saRes = mysqli_query($conn, $saSql);
    while ($saRes && ($saRow = mysqli_fetch_assoc($saRes))) {
        $name = trim((string)($saRow['name'] ?? ''));
        if ($name !== '') { $systemAccessNames[] = $name; }
    }
}

/**
 * Humanizes field labels for the employee view
 */
function emp_label($field) {
    $map = [
        'raw_status_code' => 'Raw Status',
        'employment_status_id' => 'Employment Status ID',
        'employment_status_name' => 'Employment Status',
        'department_id' => 'Department ID',
        'department_name' => 'Department',
        'office_key_card_department_id' => 'Office Key Card Department ID',
        'office_key_card_department_name' => 'Office Key Card Department',
        'hilton_id' => 'Id',
    ];
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
                                // Handle display logic based on field type
                                if (in_array($field, $booleanFields, true)) {
                                    $display = ((int)$value === 1) ? '✅' : '❌';
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
                        
                        <!-- RENDER AGGREGATED SYSTEM ACCESS LIST -->
                        <tr>
                            <th style="width:260px;">System Access</th>
                            <td>
                                <?php if ($systemAccessNames): ?>
                                    <?php echo sanitize(implode(', ', $systemAccessNames)); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn">Back</a>
                    <?php if ($employee): ?>
                        <a href="edit.php?id=<?php echo (int)$employee['id']; ?>" class="btn btn-primary">✏️</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
