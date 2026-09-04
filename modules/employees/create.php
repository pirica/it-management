<?php
/**
 * Employees Module - Create
 * 
 * Provides a specialized form for manual employee record creation.
 * Handles:
 * - Basic profile information
 * - Department and employment status assignment
 * - Immediate system access permission granting
 * - Form validation and sanitization
 */

require '../../config/config.php';
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);
require '../../includes/employee_system_access.php';
require_once '../../includes/employee_profile_photo.php';
require_once '../../includes/itm_employees_hidden_accounts.php';
require_once '../../includes/itm_fk_option_labels.php';
require_once '../../includes/itm_role_assignment_rights.php';
require_once '../../includes/itm_employees_profile_save.php';

/**
 * Cleanup unique constraints for email if they exist, facilitating manual handling
 */
function emp_drop_email_unique_if_exists($conn) {
    $sql = "SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'employees' AND index_name = 'uq_employees_email_per_company' LIMIT 1";
    $res = mysqli_query($conn, $sql);
    if ($res && mysqli_num_rows($res) === 1) {
        mysqli_query($conn, 'ALTER TABLE employees DROP INDEX uq_employees_email_per_company');
    }
}

// Pre-fetch lookups for form dropdowns
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
esa_ensure_table($conn);
$systemAccessCatalog = esa_get_system_access_catalog($conn, (int)$company_id, false);
$csrfToken = itm_get_csrf_token();

$errors = [];
$form = [
    'first_name' => '', 'last_name' => '', 'display_name' => '', 'full_name' => '', 'work_email' => '', 'personal_email' => '', 'external_id' => '', 'insurance_n' => '',
    'username' => '', 'job_code' => '', 'department_id' => '', 'raw_status_code' => 'A',
    'employment_status_id' => '1', 'employee_position_id' => '', 'reports_to' => '', 'workstation_mode_id' => '',
    'assignment_type_id' => '', 'comments' => '', 'office_key_card_department_id' => '',
    'mobile_phone' => '', 'external_number' => '', 'dect' => '', 'extension' => '', 'on_contacts' => '0', 'on_orgchart' => '0',
    'start_date' => '', 'employee_type_id' => '', 'termination_date' => '',
    'employee_code' => '', 'location_id' => '',
    'request_date' => '', 'requested_by' => '', 'termination_requested_by' => '',
    'birthday' => '', 'hide_year' => '0', 'photo' => '',
    'role_id' => '', 'access_level_id' => '',
];

$selectedDepartmentIds = [];
$selectedSystemAccessIds = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    emp_drop_email_unique_if_exists($conn);

    // Populate form state from POST
    foreach ($form as $key => $default) {
        $form[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $selectedDepartmentIds = itm_employee_normalize_department_ids($_POST['department_ids'] ?? []);
    $form['department_id'] = (string)($selectedDepartmentIds[0] ?? '');
    itm_crud_force_active_live($form);
    $selectedSystemAccessIds = array_values(array_unique(array_map('intval', $_POST['system_access_ids'] ?? [])));
    if ($form['first_name'] === '') { $errors[] = 'First Name is required.'; }
    if ($form['last_name'] === '') { $errors[] = 'Last Name is required.'; }
    $emailError = itm_employee_validate_contact_email_or_error($form['work_email'], $form['personal_email']);
    if ($emailError !== null) {
        $errors[] = $emailError;
    }

    // Auto-generate display name and full name if missing
    if ($form['display_name'] === '') { $form['display_name'] = trim($form['first_name'] . ' ' . $form['last_name']); }
    if ($form['full_name'] === '') { $form['full_name'] = trim($form['first_name'] . ' ' . $form['last_name']); }

    // Execute insertion
    if (empty($errors)) {
        $profileRow = itm_employees_profile_normalize_post_row($form);

        if ($profileRow['role_id'] !== null) {
            $currentUserRoleId = itm_get_employee_role_id($conn, (int)($_SESSION['employee_id'] ?? 0));
            if (!itm_can_assign_role($conn, (int)$company_id, $currentUserRoleId, (int)$profileRow['role_id'])) {
                $errors[] = 'You do not have permission to assign this role.';
            }
        }

        if (empty($errors)) {
            $insertResult = itm_employees_profile_insert_prepared($conn, (int)$company_id, $profileRow);
            if ($insertResult['ok']) {
                $newEmployeeId = (int)$insertResult['id'];
                if (isset($_FILES['photo']) && (int)$_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newEmployee = itm_employees_profile_fetch_row($conn, (int)$company_id, $newEmployeeId);
                    if ($newEmployee) {
                        $photoResult = emp_profile_photo_store_upload((int)$company_id, $newEmployee, $_FILES['photo']);
                        if ($photoResult['ok'] ?? false) {
                            itm_employees_profile_update_photo_prepared(
                                $conn,
                                (int)$company_id,
                                $newEmployeeId,
                                (string)$photoResult['filename']
                            );
                        } elseif (!empty($photoResult['error'])) {
                            $errors[] = (string)$photoResult['error'];
                        }
                    }
                }
                if (empty($errors)) {
                    esa_save_employee_access_ids($conn, (int)$company_id, $newEmployeeId, $selectedSystemAccessIds);
                    itm_employee_sync_department_assignments($conn, (int)$company_id, $newEmployeeId, $selectedDepartmentIds, (int)($_SESSION['employee_id'] ?? 0));
                    require_once '../../includes/itm_search_index.php';
                    itm_search_index_after_module_save($conn, 'employees', (int)$company_id, $newEmployeeId);
                    header('Location: index.php');
                    exit;
                }
            }
            $errors[] = itm_format_db_constraint_error((int)$insertResult['errno'], (string)$insertResult['error']);
        }
    }
}

/**
 * Helper for checkbox state
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
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Create Employee';
}
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
        $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), basename(dirname($_SERVER['PHP_SELF'])), (string)($crud_title ?? ''));
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
                <h1 style="margin:0;">Create Employee</h1>
                <a href="index.php" class="btn">🔙</a>
            </div>

            <?php echo itm_render_alert_errors($errors); ?>

            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <?php itm_crud_render_form_hidden_active_input(); ?>
                    <?php itm_crud_render_form_hidden_audit_inputs($form, 'create'); ?>
                    <?php include __DIR__ . '/includes/profile_fields.php'; ?>
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
                                $mgrs = mysqli_query($conn, "SELECT id, display_name, username FROM employees WHERE company_id=" . (int)$company_id . " AND is_hidden=0 ORDER BY display_name");
                                if ($mgrs): while ($m = mysqli_fetch_assoc($mgrs)): ?>
                                    <option value="<?php echo (int)$m['id']; ?>" <?php echo ((string)$m['id'] === (string)$form['reports_to']) ? 'selected' : ''; ?>><?php echo sanitize(itm_employee_manager_option_label($m)); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>

                        <!-- DROP DOWNS WITH INLINE ADD SUPPORT -->
                        <div class="form-group"><label>Departments</label>
                            <select name="department_ids[]" multiple size="5" data-addable-select="1" data-add-table="departments" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="department">
                                <?php foreach ($departmentRows as $d): ?>
                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo in_array((int)$d['id'], $selectedDepartmentIds, true) ? 'selected' : ''; ?>><?php echo sanitize(itm_department_option_label($d)); ?></option>
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
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Assignment Type</label>
                            <select name="assignment_type_id" data-addable-select="1" data-add-table="assignment_types" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="assignment type">
                                <option value="">-- None --</option>
                                <?php foreach ($assignmentTypeLookup as $assignmentTypeId => $assignmentTypeName): ?>
                                    <option value="<?php echo (int)$assignmentTypeId; ?>" <?php echo ((string)$assignmentTypeId === (string)$form['assignment_type_id']) ? 'selected' : ''; ?>><?php echo sanitize($assignmentTypeName); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" style="margin-top:12px;"><label>Comments</label><textarea name="comments" rows="3"><?php echo sanitize($form['comments']); ?></textarea></div>

                    <!-- INTEGRATED SYSTEM ACCESS PERMISSIONS -->
                    <h3>System Access</h3>
                    <div style="margin-bottom:10px;">
                        <button type="button" class="btn btn-sm" id="employees-create-select-all-access">Select All</button>
                    </div>
                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;">
                        <?php foreach ($systemAccessCatalog as $access): ?>
                            <label class="role-flag-option">
                                <input type="checkbox" name="system_access_ids[]" value="<?php echo (int)$access['id']; ?>" <?php echo emp_access_checked($selectedSystemAccessIds, (int)$access['id']); ?>>
                                <span><?php echo sanitize((string)$access['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions" style="margin-top:16px;">
                        <button type="submit" class="btn btn-primary">💾</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<script>
(function () {
    const selectAllButton = document.getElementById('employees-create-select-all-access');
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
