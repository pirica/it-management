<?php
/**
 * Employees Module - Edit
 * 
 * Provides an interface to update an existing employee's information.
 * Manages profile data, department/status assignments, and granular system permissions.
 */

require '../../config/config.php';
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);
require '../../includes/employee_system_access.php';
require_once '../../includes/employee_profile_photo.php';
require_once '../../includes/itm_employees_hidden_accounts.php';
require_once '../../includes/itm_fk_option_labels.php';

/**
 * Ensures unique constraints don't block manual updates when duplicates are allowed in the UI
 */
function emp_drop_email_unique_if_exists($conn) {
    $legacyUniqueIndexes = [
        'uq_employees_email_per_company',
        'uq_employees_code_per_company'
    ];

    foreach ($legacyUniqueIndexes as $indexName) {
        $sql = "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'employees' AND index_name = '" . mysqli_real_escape_string($conn, $indexName) . "' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) === 1) {
            mysqli_query($conn, 'ALTER TABLE employees DROP INDEX `' . str_replace('`', '``', $indexName) . '`');
        }
    }
}

// Validate ID
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Load existing employee record
$employeeSql = 'SELECT * FROM employees WHERE id=' . $id . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
$employeeRes = mysqli_query($conn, $employeeSql);
$employee = ($employeeRes && mysqli_num_rows($employeeRes) === 1) ? mysqli_fetch_assoc($employeeRes) : null;
if (!$employee) {
    header('Location: index.php');
    exit;
}
if (itm_employees_is_hidden_account($employee)) {
    header('Location: index.php');
    exit;
}

// Load reference data
$statuses = mysqli_query($conn, 'SELECT id, name FROM employee_statuses WHERE company_id=' . (int)$company_id . ' ORDER BY name');
$departmentRows = itm_department_select_rows_for_company($conn, (int)$company_id);
$workstationModeOptions = mysqli_query($conn, 'SELECT id, mode_name FROM workstation_modes WHERE company_id=' . (int)$company_id . ' ORDER BY pos, mode_name');
$assignmentTypeOptions = mysqli_query($conn, 'SELECT id, name FROM assignment_types WHERE company_id=' . (int)$company_id . ' ORDER BY name');
$workstationModeLookup = [];
if ($workstationModeOptions) {
    while ($modeRow = mysqli_fetch_assoc($workstationModeOptions)) {
        $modeId = (int)($modeRow['id'] ?? 0);
        $modeName = trim((string)($modeRow['mode_name'] ?? ''));
        if ($modeId > 0 && $modeName !== '') {
            $workstationModeLookup[$modeId] = $modeName;
        }
    }
}
$assignmentTypeLookup = [];
if ($assignmentTypeOptions) {
    while ($assignmentTypeRow = mysqli_fetch_assoc($assignmentTypeOptions)) {
        $assignmentTypeId = (int)($assignmentTypeRow['id'] ?? 0);
        $assignmentTypeName = trim((string)($assignmentTypeRow['name'] ?? ''));
        if ($assignmentTypeId > 0 && $assignmentTypeName !== '') {
            $assignmentTypeLookup[$assignmentTypeId] = $assignmentTypeName;
        }
    }
}
$workstationModesColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'workstation_mode_id'");
$hasWorkstationModesColumn = $workstationModesColumnRes && mysqli_num_rows($workstationModesColumnRes) === 1;
$assignmentTypesColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM employees LIKE 'assignment_type_id'");
$hasAssignmentTypesColumn = $assignmentTypesColumnRes && mysqli_num_rows($assignmentTypesColumnRes) === 1;
esa_ensure_table($conn);
$systemAccessCatalog = esa_get_system_access_catalog($conn, (int)$company_id, true);
$csrfToken = itm_get_csrf_token();

$errors = [];
$form = [
    'first_name' => (string)($employee['first_name'] ?? ''),
    'last_name' => (string)($employee['last_name'] ?? ''),
    'display_name' => (string)($employee['display_name'] ?? ''),
    'full_name' => (string)($employee['full_name'] ?? ''),
    'work_email' => (string)($employee['work_email'] ?? ''),
    'personal_email' => (string)($employee['personal_email'] ?? ''),
    'external_id' => (string)($employee['external_id'] ?? ''),
    'insurance_n' => (string)($employee['insurance_n'] ?? ''),
    'username' => (string)($employee['username'] ?? ''),
    'job_code' => (string)($employee['job_code'] ?? ''),
    'department_id' => (string)($employee['department_id'] ?? ''),
    'raw_status_code' => (string)($employee['raw_status_code'] ?? 'A'),
    'employment_status_id' => (string)($employee['employment_status_id'] ?? '1'),
    'employee_position_id' => (string)($employee['employee_position_id'] ?? ''),
    'reports_to' => (string)($employee['reports_to'] ?? ''),
    'workstation_mode_id' => (string)($employee['workstation_mode_id'] ?? ''),
    'assignment_type_id' => (string)($employee['assignment_type_id'] ?? ''),
    'comments' => (string)($employee['comments'] ?? ''),
    'office_key_card_department_id' => (string)($employee['office_key_card_department_id'] ?? ''),
    'mobile_phone' => (string)($employee['mobile_phone'] ?? ''),
    'external_number' => (string)($employee['external_number'] ?? ''),
    'dect' => (string)($employee['dect'] ?? ''),
    'extension' => (string)($employee['extension'] ?? ''),
    'on_contacts' => (string)($employee['on_contacts'] ?? '0'),
    'on_orgchart' => (string)($employee['on_orgchart'] ?? '0'),
    'start_date' => (string)($employee['start_date'] ?? ''),
    'employee_type_id' => (string)($employee['employee_type_id'] ?? ''),
    'termination_date' => (string)($employee['termination_date'] ?? ''),
    'employee_code' => (string)($employee['employee_code'] ?? ''),
    'location_id' => (string)($employee['location_id'] ?? ''),
    'request_date' => (string)($employee['request_date'] ?? ''),
    'requested_by' => (string)($employee['requested_by'] ?? ''),
    'termination_requested_by' => (string)($employee['termination_requested_by'] ?? ''),
    'birthday' => (string)($employee['birthday'] ?? ''),
    'hide_year' => (string)($employee['hide_year'] ?? '0'),
    'photo' => (string)($employee['photo'] ?? ''),
    'role_id' => (string)($employee['role_id'] ?? ''),
    'access_level_id' => (string)($employee['access_level_id'] ?? ''),
];

// Load current permission IDs
$selectedSystemAccessIds = esa_get_employee_access_ids($conn, (int)$company_id, $id);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    emp_drop_email_unique_if_exists($conn);

    foreach ($form as $key => $default) {
        $form[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $selectedSystemAccessIds = array_values(array_unique(array_map('intval', $_POST['system_access_ids'] ?? [])));

    // Validation
    if ($form['first_name'] === '') { $errors[] = 'First Name is required.'; }
    if ($form['last_name'] === '') { $errors[] = 'Last Name is required.'; }
    if ($form['work_email'] === '' && $form['personal_email'] === '') {
        $errors[] = 'Personal Email is required if Work Email is not provided.';
    }
    if ($form['display_name'] === '') { $form['display_name'] = trim($form['first_name'] . ' ' . $form['last_name']); }

    if (!$errors) {
        $firstName = mysqli_real_escape_string($conn, $form['first_name']);
        $lastName = mysqli_real_escape_string($conn, $form['last_name']);
        $displayName = $form['display_name'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['display_name']) . "'";
        $fullName = $form['full_name'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['full_name']) . "'";
        $workEmail = $form['work_email'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['work_email']) . "'";
        $personalEmail = $form['personal_email'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['personal_email']) . "'";
        $externalId = $form['external_id'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['external_id']) . "'";
        $insuranceN = $form['insurance_n'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['insurance_n']) . "'";
        $username = $form['username'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['username']) . "'";
        $jobCode = $form['job_code'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['job_code']) . "'";
        $departmentId = $form['department_id'] === '' ? 'NULL' : (string)(int)$form['department_id'];
        $officeDeptId = $form['office_key_card_department_id'] === '' ? 'NULL' : (string)(int)$form['office_key_card_department_id'];
        $rawStatusCode = $form['raw_status_code'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['raw_status_code']) . "'";
        $employmentStatusId = $form['employment_status_id'] === '' ? '1' : (string)(int)$form['employment_status_id'];
        $employeePositionId = $form['employee_position_id'] === '' ? 'NULL' : (string)(int)$form['employee_position_id'];
        $reportsTo = $form['reports_to'] === '' ? 'NULL' : (string)(int)$form['reports_to'];
        $workstationModeId = $form['workstation_mode_id'] === '' ? 'NULL' : (string)(int)$form['workstation_mode_id'];
        $assignmentTypeId = $form['assignment_type_id'] === '' ? 'NULL' : (string)(int)$form['assignment_type_id'];
        $startDate = itm_sql_date_fragment($conn, $form['start_date']);
        $employeeTypeId = $form['employee_type_id'] === '' ? 'NULL' : (string)(int)$form['employee_type_id'];
        $terminationDate = itm_sql_date_fragment($conn, $form['termination_date']);
        $employeeCode = $form['employee_code'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['employee_code']) . "'";
        $locationId = $form['location_id'] === '' ? 'NULL' : (string)(int)$form['location_id'];
        $requestDate = itm_sql_date_fragment($conn, $form['request_date']);
        $requestedBy = $form['requested_by'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['requested_by']) . "'";
        $terminationRequestedBy = $form['termination_requested_by'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['termination_requested_by']) . "'";
        $comments = $form['comments'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['comments']) . "'";
        $mobilePhone = $form['mobile_phone'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['mobile_phone']) . "'";
        $externalNumber = $form['external_number'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['external_number']) . "'";
        $dect = $form['dect'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['dect']) . "'";
        $extension = $form['extension'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['extension']) . "'";
        $onContacts = (int)$form['on_contacts'];
        $onOrgchart = (int)$form['on_orgchart'];
        $birthday = itm_sql_date_fragment($conn, $form['birthday']);
        $hideYear = (int)$form['hide_year'];
        $roleId = $form['role_id'] === '' ? 'NULL' : (string)(int)$form['role_id'];
        $accessLevelId = $form['access_level_id'] === '' ? 'NULL' : (string)(int)$form['access_level_id'];
        $photoValue = mysqli_real_escape_string($conn, (string)($employee['photo'] ?? ''));
        if (isset($_FILES['photo']) && (int)$_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $photoEmployee = [
                'id' => (int)$id,
                'username' => $form['username'],
                'photo' => (string)($employee['photo'] ?? ''),
            ];
            $photoResult = emp_profile_photo_store_upload((int)$company_id, $photoEmployee, $_FILES['photo']);
            if ($photoResult['ok'] ?? false) {
                $photoValue = mysqli_real_escape_string($conn, (string)$photoResult['filename']);
            } elseif (!empty($photoResult['error'])) {
                $errors[] = (string)$photoResult['error'];
            }
        }
        $workstationModesSql = $hasWorkstationModesColumn ? ", workstation_mode_id={$workstationModeId}" : '';
        $assignmentTypesSql = $hasAssignmentTypesColumn ? ", assignment_type_id={$assignmentTypeId}" : '';

        if (!$errors) {
        $sql = "UPDATE employees SET
            first_name='{$firstName}', last_name='{$lastName}', display_name={$displayName}, full_name={$fullName},
            work_email={$workEmail}, personal_email={$personalEmail}, external_id={$externalId}, insurance_n={$insuranceN}, username={$username}, employee_code={$employeeCode},
            department_id={$departmentId}, location_id={$locationId}, job_code={$jobCode}, mobile_phone={$mobilePhone}, external_number={$externalNumber}, dect={$dect}, extension={$extension}, on_contacts={$onContacts}, on_orgchart={$onOrgchart},
            raw_status_code={$rawStatusCode}, employment_status_id={$employmentStatusId},
            employee_position_id={$employeePositionId}, reports_to={$reportsTo},
            office_key_card_department_id={$officeDeptId}{$workstationModesSql}{$assignmentTypesSql},
            request_date={$requestDate}, requested_by={$requestedBy}, termination_requested_by={$terminationRequestedBy},
            start_date={$startDate}, employee_type_id={$employeeTypeId}, termination_date={$terminationDate},
            comments={$comments}, birthday={$birthday}, hide_year={$hideYear}, photo='{$photoValue}',
            role_id={$roleId}, access_level_id={$accessLevelId}
            WHERE id={$id} AND company_id=" . (int)$company_id . " AND is_hidden=0 LIMIT 1";

        if (mysqli_query($conn, $sql)) {
            // Update permissions in modern normalized table
            esa_save_employee_access_ids($conn, (int)$company_id, $id, $selectedSystemAccessIds);
            header('Location: view.php?id=' . $id);
            exit;
        }
        $errors[] = itm_format_db_constraint_error(mysqli_errno($conn), mysqli_error($conn));
        }
    }
}

/**
 * Checkbox helper
 */
function emp_access_checked($selectedSystemAccessIds, $accessId) {
    return in_array((int)$accessId, $selectedSystemAccessIds, true) ? 'checked' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px;">
                <h1 style="margin:0;">Edit Employee #<?php echo (int)$id; ?></h1>
                <a href="index.php" class="btn">🔙</a>
            </div>

            <?php echo itm_render_alert_errors($errors); ?>

            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <?php $employee = $employee ?? []; include __DIR__ . '/includes/profile_fields.php'; ?>
                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        <div class="form-group"><label>First Name *</label><input type="text" name="first_name" value="<?php echo sanitize($form['first_name']); ?>" required></div>
                        <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" value="<?php echo sanitize($form['last_name']); ?>" required></div>
                        <div class="form-group"><label>Display Name</label><input type="text" name="display_name" value="<?php echo sanitize($form['display_name']); ?>"></div>
                        <div class="form-group"><label>Full Name</label><input type="text" name="full_name" value="<?php echo sanitize($form['full_name']); ?>"></div>
                        <div class="form-group"><label>Work Email</label><input type="email" name="work_email" value="<?php echo sanitize($form['work_email']); ?>"></div>
                        <div class="form-group"><label>Personal Email</label><input type="email" name="personal_email" value="<?php echo sanitize($form['personal_email']); ?>"></div>
                        <div class="form-group"><label>Mobile Phone</label><input type="text" name="mobile_phone" value="<?php echo sanitize($form['mobile_phone']); ?>"></div>
                        <div class="form-group"><label>External Number</label><input type="text" name="external_number" value="<?php echo sanitize($form['external_number']); ?>"></div>
                        <div class="form-group"><label>Extension</label><input type="text" name="extension" value="<?php echo sanitize($form['extension']); ?>"></div>
                        <div class="form-group"><label>Dect</label><input type="text" name="dect" value="<?php echo sanitize($form['dect']); ?>"></div>
                        <div class="form-group">
                            <label>On Contacts</label>
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="on_contacts" value="1" <?php echo ($form['on_contacts'] == 1) ? 'checked' : ''; ?>>
                                <span>On Contacts <span class="itm-check-indicator" aria-hidden="true"><?php echo ($form['on_contacts'] == 1) ? '✅' : '❌'; ?></span></span>
                            </label>
                        </div>
                        <div class="form-group">
                            <label>On Orgchart</label>
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="on_orgchart" value="1" <?php echo ($form['on_orgchart'] == 1) ? 'checked' : ''; ?>>
                                <span>On Orgchart <span class="itm-check-indicator" aria-hidden="true"><?php echo ($form['on_orgchart'] == 1) ? '✅' : '❌'; ?></span></span>
                            </label>
                        </div>
                        <div class="form-group"><label>External ID</label><input type="text" name="external_id" value="<?php echo sanitize($form['external_id']); ?>"></div>
                        <div class="form-group"><label>Insurance N</label><input type="text" name="insurance_n" value="<?php echo sanitize($form['insurance_n']); ?>"></div>
                        <?php include __DIR__ . '/includes/profile_employee_code_field.php'; ?>
                        <div class="form-group"><label>Username</label><input type="text" name="username" value="<?php echo sanitize($form['username']); ?>"></div>
                        <?php include __DIR__ . '/includes/profile_role_access_fields.php'; ?>
                        <div class="form-group"><label>Job Code</label><input type="text" name="job_code" value="<?php echo sanitize($form['job_code']); ?>"></div>
                        <div class="form-group"><label>Position Title</label>
                            <select name="employee_position_id" data-addable-select="1" data-add-table="employee_positions" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="position title">
                                <option value="">-- None --</option>
                                <?php
                                $positions = mysqli_query($conn, 'SELECT id, name FROM employee_positions WHERE company_id=' . (int)$company_id . ' ORDER BY name');
                                if ($positions): while ($p = mysqli_fetch_assoc($positions)): ?>
                                    <option value="<?php echo (int)$p['id']; ?>" <?php echo ((string)$p['id'] === (string)$form['employee_position_id']) ? 'selected' : ''; ?>><?php echo sanitize((string)$p['name']); ?></option>
                                <?php endwhile; endif; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Reports To</label>
                            <select name="reports_to">
                                <option value="">-- None --</option>
                                <?php
                                $mgrs = mysqli_query($conn, "SELECT id, display_name, username FROM employees WHERE company_id=" . (int)$company_id . " AND id != " . $id . " AND is_hidden=0 ORDER BY display_name");
                                if ($mgrs): while ($m = mysqli_fetch_assoc($mgrs)): ?>
                                    <option value="<?php echo (int)$m['id']; ?>" <?php echo ((string)$m['id'] === (string)$form['reports_to']) ? 'selected' : ''; ?>><?php echo sanitize(itm_employee_manager_option_label($m)); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <!-- LOOKUP SELECTS -->
                        <div class="form-group"><label>Department</label>
                            <select name="department_id" data-addable-select="1" data-add-table="departments" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="department">
                                <option value="">-- None --</option>
                                <?php foreach ($departmentRows as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo ((string)$d['id'] === (string)$form['department_id']) ? 'selected' : ''; ?>><?php echo sanitize(itm_department_option_label($d)); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <?php include __DIR__ . '/includes/profile_location_field.php'; ?>
                        <div class="form-group"><label>Office Key Card Department</label>
                            <select name="office_key_card_department_id" data-addable-select="1" data-add-table="departments" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="office key card department">
                                <option value="">-- None --</option>
                                <?php foreach ($departmentRows as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo ((string)$d['id'] === (string)$form['office_key_card_department_id']) ? 'selected' : ''; ?>><?php echo sanitize(itm_department_option_label($d)); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Raw Status Code</label><input type="text" name="raw_status_code" value="<?php echo sanitize($form['raw_status_code']); ?>"></div>
                        <div class="form-group"><label>Employment Status</label>
                            <select name="employment_status_id" data-addable-select="1" data-add-table="employee_statuses" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="employment status">
                                <?php if ($statuses): while ($s = mysqli_fetch_assoc($statuses)): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ((string)$s['id'] === (string)$form['employment_status_id']) ? 'selected' : ''; ?>><?php echo sanitize((string)$s['name']); ?></option>
                                <?php endwhile; endif; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <?php include __DIR__ . '/includes/profile_request_fields.php'; ?>
                        <?php include __DIR__ . '/includes/profile_start_date_field.php'; ?>
                        <?php include __DIR__ . '/includes/profile_employee_type_fields.php'; ?>
                        <?php include __DIR__ . '/includes/profile_termination_date_field.php'; ?>
                        <?php include __DIR__ . '/includes/profile_birthday_fields.php'; ?>
                    </div>

                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-top:12px;">
                        <div class="form-group"><label>Workstation Mode</label>
                            <select name="workstation_mode_id" data-addable-select="1" data-add-table="workstation_modes" data-add-id-col="id" data-add-label-col="mode_name" data-add-company-scoped="1" data-add-friendly="workstation mode">
                                <option value="">-- None --</option>
                                <?php foreach ($workstationModeLookup as $modeId => $modeName): ?>
                                    <option value="<?php echo (int)$modeId; ?>" <?php echo ((string)$modeId === (string)$form['workstation_mode_id']) ? 'selected' : ''; ?>><?php echo sanitize($modeName); ?></option>
                                <?php endforeach; ?>
                                <?php if ($form['workstation_mode_id'] !== '' && !isset($workstationModeLookup[(int)$form['workstation_mode_id']])): ?>
                                    <option value="<?php echo (int)$form['workstation_mode_id']; ?>" selected>#<?php echo (int)$form['workstation_mode_id']; ?></option>
                                <?php endif; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Assignment Type</label>
                            <select name="assignment_type_id" data-addable-select="1" data-add-table="assignment_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="assignment type">
                                <option value="">-- None --</option>
                                <?php foreach ($assignmentTypeLookup as $assignmentTypeId => $assignmentTypeName): ?>
                                    <option value="<?php echo (int)$assignmentTypeId; ?>" <?php echo ((string)$assignmentTypeId === (string)$form['assignment_type_id']) ? 'selected' : ''; ?>><?php echo sanitize($assignmentTypeName); ?></option>
                                <?php endforeach; ?>
                                <?php if ($form['assignment_type_id'] !== '' && !isset($assignmentTypeLookup[(int)$form['assignment_type_id']])): ?>
                                    <option value="<?php echo (int)$form['assignment_type_id']; ?>" selected>#<?php echo (int)$form['assignment_type_id']; ?></option>
                                <?php endif; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:12px;"><label>Comments</label><textarea name="comments" rows="3"><?php echo sanitize($form['comments']); ?></textarea></div>

                    <!-- PERMISSION MANAGEMENT -->
                    <h3>System Access</h3>
                    <div style="margin-bottom:10px;">
                        <button type="button" class="btn btn-sm" id="employees-edit-select-all-access">Select All</button>
                    </div>
                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;">
                        <?php foreach ($systemAccessCatalog as $access): ?>
                            <label class="role-flag-option">
                                <input type="checkbox" name="system_access_ids[]" value="<?php echo (int)$access['id']; ?>" <?php echo emp_access_checked($selectedSystemAccessIds, (int)$access['id']); ?>>
                                <span><?php echo sanitize((string)$access['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions itm-form-actions itm-align-left">
                        <button class="btn btn-primary" type="submit">💾</button>
                        <a href="index.php" class="btn">🔙</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script>
(function () {
    const selectAllButton = document.getElementById('employees-edit-select-all-access');
    if (!selectAllButton) {
        return;
    }

    const checkboxes = Array.from(document.querySelectorAll('input[name="system_access_ids[]"]'));
    if (!checkboxes.length) {
        selectAllButton.style.display = 'none';
        return;
    }

    function updateButtonLabel() {
        const allChecked = checkboxes.every(function (checkbox) { return checkbox.checked; });
        selectAllButton.textContent = allChecked ? 'Deselect All' : 'Select All';
    }

    selectAllButton.addEventListener('click', function () {
        const shouldCheckAll = !checkboxes.every(function (checkbox) { return checkbox.checked; });
        checkboxes.forEach(function (checkbox) {
            checkbox.checked = shouldCheckAll;
        });
        updateButtonLabel();
    });

    checkboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateButtonLabel);
    });
    updateButtonLabel();
})();
</script>
</body>
</html>
