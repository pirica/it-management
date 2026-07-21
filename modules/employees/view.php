<?php
/**
 * Employees Module - View
 *
 * Provides a read-only employee profile page scoped to the active company.
 * This dedicated page keeps employee details and access permissions visible
 * in one place so edits remain intentional from the edit form.
 */

require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_crud_record_share.php';
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);
require '../../includes/employee_system_access.php';
require_once '../../includes/employee_profile_photo.php';
require_once '../../includes/itm_employees_hidden_accounts.php';

// Keep system access metadata available even in partially initialized environments.
esa_ensure_table($conn);

$employeeId = (int)($_GET['id'] ?? 0);
if ($employeeId <= 0) {
    header('Location: index.php');
    exit;
}

$sql = 'SELECT e.*, d.name AS department_name, okd.name AS office_key_card_department_name, es.name AS employment_status_name, wm.mode_name AS workstation_mode_name, at.name AS assignment_type_name, ep.name AS position_name, m.display_name AS manager_name, et.name_type AS employee_type_name, il.name AS location_name, er.name AS role_name, al.name AS access_level_name '
    . 'FROM employees e '
    . 'LEFT JOIN departments d ON d.id = e.department_id '
    . 'LEFT JOIN departments okd ON okd.id = e.office_key_card_department_id '
    . 'LEFT JOIN employee_statuses es ON es.id = e.employment_status_id '
    . 'LEFT JOIN workstation_modes wm ON wm.id = e.workstation_mode_id AND wm.company_id = e.company_id '
    . 'LEFT JOIN assignment_types at ON at.id = e.assignment_type_id AND at.company_id = e.company_id '
    . 'LEFT JOIN employee_type et ON et.id = e.employee_type_id AND et.company_id = e.company_id '
    . 'LEFT JOIN employee_positions ep ON ep.id = e.employee_position_id '
    . 'LEFT JOIN employees m ON m.id = e.reports_to '
    . 'LEFT JOIN it_locations il ON il.id = e.location_id AND il.company_id = e.company_id '
    . 'LEFT JOIN employee_roles er ON er.id = e.role_id AND er.company_id = e.company_id '
    . 'LEFT JOIN access_levels al ON al.id = e.access_level_id AND al.company_id = e.company_id '
    . 'WHERE e.id = ? AND e.company_id = ? AND e.is_hidden = 0 '
    . 'LIMIT 1';

$stmt = mysqli_prepare($conn, $sql);
$employee = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $company_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $employee = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$employee) {
    header('Location: index.php');
    exit;
}

$systemAccessCatalog = esa_get_system_access_catalog($conn, (int)$company_id, true);
$selectedSystemAccessIds = esa_get_employee_access_ids($conn, (int)$company_id, $employeeId);
$selectedSystemAccessMap = array_fill_keys($selectedSystemAccessIds, true);

function emp_view_value($value) {
    $text = trim((string)($value ?? ''));
    return $text === '' ? '—' : sanitize($text);
}

function emp_view_bool_icon($value) {
    return ((int)$value === 1) ? '✅' : '❌';
}

$profileFields = [
    'ID' => (string)($employee['id'] ?? ''),
    'First Name' => (string)($employee['first_name'] ?? ''),
    'Last Name' => (string)($employee['last_name'] ?? ''),
    'Display Name' => (string)($employee['display_name'] ?? ''),
    'Full Name' => (string)($employee['full_name'] ?? ''),
    'Work Email' => (string)($employee['work_email'] ?? ''),
    'Personal Email' => (string)($employee['personal_email'] ?? ''),
    'Mobile Phone' => (string)($employee['mobile_phone'] ?? ''),
    'External Number' => (string)($employee['external_number'] ?? ''),
    'Extension' => (string)($employee['extension'] ?? ''),
    'Dect' => (string)($employee['dect'] ?? ''),
    'On Contacts' => ((int)($employee['on_contacts'] ?? 0) === 1 ? '✅' : '❌'),
    'On Orgchart' => ((int)($employee['on_orgchart'] ?? 0) === 1 ? '✅' : '❌'),
    'External ID' => (string)($employee['external_id'] ?? ''),
    'Insurance N' => (string)($employee['insurance_n'] ?? ''),
    'Employee Code' => (string)($employee['employee_code'] ?? ''),
    'Username' => (string)($employee['username'] ?? ''),
    'Role' => (string)($employee['role_name'] ?? ''),
    'Access Level' => (string)($employee['access_level_name'] ?? ''),
    'Department' => (string)($employee['department_name'] ?? ''),
    'IT Location' => (string)($employee['location_name'] ?? ''),
    'Office Key Card Department' => (string)($employee['office_key_card_department_name'] ?? ''),
    'Job Code' => (string)($employee['job_code'] ?? ''),
    'Position Title' => (string)($employee['position_name'] ?? ''),
    'Reports To' => (string)($employee['manager_name'] ?? ''),
    'Raw Status Code' => (string)($employee['raw_status_code'] ?? ''),
    'Employment Status' => (string)($employee['employment_status_name'] ?? ''),
    'Request Date' => itm_format_date_display($employee['request_date'] ?? ''),
    'Requested By' => (string)($employee['requested_by'] ?? ''),
    'Termination Requested By' => (string)($employee['termination_requested_by'] ?? ''),
    'Start Date' => itm_format_date_display($employee['start_date'] ?? ''),
    'Employee Type' => (string)($employee['employee_type_name'] ?? ''),
    'Termination Date' => itm_format_date_display($employee['termination_date'] ?? ''),
    'Birthday' => emp_format_birthday_display($employee['birthday'] ?? null, $employee['hide_year'] ?? 0),
    'Hide Year' => ((int)($employee['hide_year'] ?? 0) === 1 ? '✅' : '❌'),
    'Workstation Mode' => (string)($employee['workstation_mode_name'] ?? ''),
    'Assignment Type' => (string)($employee['assignment_type_name'] ?? ''),
    'Comments' => (string)($employee['comments'] ?? ''),
    'Duplicate Flag' => emp_view_bool_icon($employee['duplicate'] ?? 0),
    'Deleted By' => itm_crud_render_audit_cell_value($conn, (int)$company_id, 'deleted_by', $employee['deleted_by'] ?? null),
    'Deleted At' => itm_crud_render_audit_cell_value($conn, (int)$company_id, 'deleted_at', $employee['deleted_at'] ?? null),
    'Created By' => itm_crud_render_audit_cell_value($conn, (int)$company_id, 'created_by', $employee['created_by'] ?? null),
    'Created At' => itm_crud_render_audit_cell_value($conn, (int)$company_id, 'created_at', $employee['created_at'] ?? null),
    'Updated By' => itm_crud_render_audit_cell_value($conn, (int)$company_id, 'updated_by', $employee['updated_by'] ?? null),
    'Updated At' => itm_crud_render_audit_cell_value($conn, (int)$company_id, 'updated_at', $employee['updated_at'] ?? null),
];
$auditProfileLabels = ['Deleted By', 'Deleted At', 'Created By', 'Created At', 'Updated By', 'Updated At'];
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
    $crud_title = 'View Employee';
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
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <?php $empPhotoUrl = emp_profile_photo_url($employee); ?>
                    <?php if ($empPhotoUrl !== ''): ?>
                        <img src="<?= sanitize($empPhotoUrl) ?>" alt="" class="rounded-circle border" width="72" height="72" style="object-fit:cover;" onerror="this.onerror=null; this.src='../../images/5x5-pixel.png';">
                    <?php endif; ?>
                    <h1 style="margin:0;">Employee #<?php echo (int)$employeeId; ?></h1>
                </div>
                <div style="display:flex;gap:8px;">
                    <?php echo itm_crud_record_share_render_action_buttons('employees', (int)($employeeId ?? $id ?? $item['id'] ?? 0), 'employee'); ?>
                    <a href="index.php" class="btn">🔙</a>
                    <?php if (empty($employee['deleted_at'])): ?>
                        <a href="edit.php?id=<?php echo (int)$employeeId; ?>" class="btn btn-primary">✏️</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <h2 style="margin-top:0;">Employee Details</h2>
                <div class="table-responsive">
                    <table>
                        <tbody>
                        <?php foreach ($profileFields as $label => $value): ?>
                            <tr>
                                <th style="width:280px;"><?php echo sanitize($label); ?></th>
                                <td>
                                    <?php if (($label === 'Work Email' || $label === 'Personal Email') && $value !== ''): ?>
                                        <a href="mailto:<?php echo sanitize($value); ?>"><?php echo sanitize($value); ?></a>
                                    <?php elseif ($label === 'Employment Status'): ?>
                                        <?php echo function_exists('itm_crud_render_status_label_badge') ? itm_crud_render_status_label_badge((string)$value) : emp_view_value($value); ?>
                                    <?php elseif (in_array($label, $auditProfileLabels, true)): ?>
                                        <?php echo $value === '' ? '—' : $value; ?>
                                    <?php else: ?>
                                        <?php echo ($label === 'Duplicate Flag') ? $value : emp_view_value($value); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h2 style="margin-top:0;">System Access</h2>
                <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;">
                    <?php foreach ($systemAccessCatalog as $access): ?>
                        <?php $isGranted = !empty($selectedSystemAccessMap[(int)$access['id']]); ?>
                        <div class="role-flag-option" style="display:flex;justify-content:space-between;align-items:center;">
                            <span><?php echo sanitize((string)$access['name']); ?></span>
                            <span><?php echo $isGranted ? '✅' : '❌'; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php itm_crud_record_share_include_modal(); ?>
</body>
</html>
