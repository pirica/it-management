<?php
/**
 * Employees Module - Edit
 * 
 * Provides an interface to update an existing employee's information.
 * Manages profile data, department/status assignments, and granular system permissions.
 */

require '../../config/config.php';
require '../../includes/employee_system_access.php';

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

// Load reference data
$statuses = mysqli_query($conn, 'SELECT id, name FROM employee_statuses WHERE company_id=' . (int)$company_id . ' ORDER BY name');
$departments = mysqli_query($conn, 'SELECT id, name FROM departments WHERE company_id=' . (int)$company_id . ' ORDER BY name');
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
    'email' => (string)($employee['email'] ?? ''),
    'external_id' => (string)($employee['external_id'] ?? ''),
    'username' => (string)($employee['username'] ?? ''),
    'job_code' => (string)($employee['job_code'] ?? ''),
    'job_title' => (string)($employee['job_title'] ?? ''),
    'department_id' => (string)($employee['department_id'] ?? ''),
    'raw_status_code' => (string)($employee['raw_status_code'] ?? 'A'),
    'employment_status_id' => (string)($employee['employment_status_id'] ?? '1'),
    'workstation_mode_id' => (string)($employee['workstation_mode_id'] ?? ''),
    'assignment_type_id' => (string)($employee['assignment_type_id'] ?? ''),
    'comments' => (string)($employee['comments'] ?? ''),
    'office_key_card_department_id' => (string)($employee['office_key_card_department_id'] ?? ''),
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
    if ($form['display_name'] === '') { $form['display_name'] = trim($form['first_name'] . ' ' . $form['last_name']); }

    if (!$errors) {
        $firstName = mysqli_real_escape_string($conn, $form['first_name']);
        $lastName = mysqli_real_escape_string($conn, $form['last_name']);
        $displayName = $form['display_name'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['display_name']) . "'";
        $email = $form['email'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['email']) . "'";
        $externalId = $form['external_id'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['external_id']) . "'";
        $username = $form['username'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['username']) . "'";
        $jobCode = $form['job_code'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['job_code']) . "'";
        $jobTitle = $form['job_title'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['job_title']) . "'";
        $departmentId = $form['department_id'] === '' ? 'NULL' : (string)(int)$form['department_id'];
        $officeDeptId = $form['office_key_card_department_id'] === '' ? 'NULL' : (string)(int)$form['office_key_card_department_id'];
        $rawStatusCode = $form['raw_status_code'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['raw_status_code']) . "'";
        $employmentStatusId = $form['employment_status_id'] === '' ? '1' : (string)(int)$form['employment_status_id'];
        $workstationModeId = $form['workstation_mode_id'] === '' ? 'NULL' : (string)(int)$form['workstation_mode_id'];
        $assignmentTypeId = $form['assignment_type_id'] === '' ? 'NULL' : (string)(int)$form['assignment_type_id'];
        $comments = $form['comments'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['comments']) . "'";
        $workstationModesSql = $hasWorkstationModesColumn ? ", workstation_mode_id={$workstationModeId}" : '';
        $assignmentTypesSql = $hasAssignmentTypesColumn ? ", assignment_type_id={$assignmentTypeId}" : '';

        $sql = "UPDATE employees SET
            first_name='{$firstName}', last_name='{$lastName}', display_name={$displayName},
            email={$email}, external_id={$externalId}, username={$username},
            department_id={$departmentId}, job_code={$jobCode}, job_title={$jobTitle},
            raw_status_code={$rawStatusCode}, employment_status_id={$employmentStatusId},
            office_key_card_department_id={$officeDeptId}{$workstationModesSql}{$assignmentTypesSql},
            comments={$comments}
            WHERE id={$id} AND company_id=" . (int)$company_id . " LIMIT 1";

        if (mysqli_query($conn, $sql)) {
            // Update permissions in modern normalized table
            esa_save_employee_access_ids($conn, (int)$company_id, $id, $selectedSystemAccessIds);
            header('Location: view.php?id=' . $id);
            exit;
        }
        $errors[] = 'Could not update employee: ' . mysqli_error($conn);
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

            <?php foreach ($errors as $error): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endforeach; ?>

            <div class="card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        <div class="form-group"><label>First Name *</label><input type="text" name="first_name" value="<?php echo sanitize($form['first_name']); ?>" required></div>
                        <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" value="<?php echo sanitize($form['last_name']); ?>" required></div>
                        <div class="form-group"><label>Display Name</label><input type="text" name="display_name" value="<?php echo sanitize($form['display_name']); ?>"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize($form['email']); ?>"></div>
                        <div class="form-group"><label>External ID</label><input type="text" name="external_id" value="<?php echo sanitize($form['external_id']); ?>"></div>
                        <div class="form-group"><label>Username</label><input type="text" name="username" value="<?php echo sanitize($form['username']); ?>"></div>
                        <div class="form-group"><label>Job Code</label><input type="text" name="job_code" value="<?php echo sanitize($form['job_code']); ?>"></div>
                        <div class="form-group"><label>Job Title</label><input type="text" name="job_title" value="<?php echo sanitize($form['job_title']); ?>"></div>
                        
                        <!-- LOOKUP SELECTS -->
                        <div class="form-group"><label>Department</label>
                            <select name="department_id" data-addable-select="1" data-add-table="departments" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="department">
                                <option value="">-- None --</option>
                                <?php if ($departments): while ($d = mysqli_fetch_assoc($departments)): ?>
                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo ((string)$d['id'] === (string)$form['department_id']) ? 'selected' : ''; ?>><?php echo sanitize((string)$d['name']); ?></option>
                                <?php endwhile; endif; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Office Key Card Department</label>
                            <select name="office_key_card_department_id" data-addable-select="1" data-add-table="departments" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="office key card department">
                                <option value="">-- None --</option>
                                <?php if ($departments instanceof mysqli_result) { mysqli_data_seek($departments, 0); } ?>
                                <?php if ($departments): while ($d = mysqli_fetch_assoc($departments)): ?>
                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo ((string)$d['id'] === (string)$form['office_key_card_department_id']) ? 'selected' : ''; ?>><?php echo sanitize((string)$d['name']); ?></option>
                                <?php endwhile; endif; ?>
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
                        <button type="button" class="btn btn-sm" id="employees-edit-select-all-access">Select all</button>
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

    selectAllButton.addEventListener('click', function () {
        document.querySelectorAll('input[name="system_access_ids[]"]').forEach(function (checkbox) {
            checkbox.checked = true;
        });
    });
})();
</script>
</body>
</html>
