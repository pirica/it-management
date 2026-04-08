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
require '../../includes/employee_system_access.php';

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
$departments = mysqli_query($conn, 'SELECT id, name FROM departments WHERE company_id=' . (int)$company_id . ' ORDER BY name');
esa_ensure_table($conn);
$systemAccessCatalog = esa_get_system_access_catalog($conn, (int)$company_id, false);
$csrfToken = itm_get_csrf_token();

$errors = [];
$form = [
    'first_name' => '', 'last_name' => '', 'display_name' => '', 'email' => '', 'hilton_id' => '',
    'username' => '', 'job_code' => '', 'job_title' => '', 'department_id' => '', 'raw_status_code' => 'A',
    'employment_status_id' => '1', 'comments' => '', 'office_key_card_department_id' => '',
];

$selectedSystemAccessIds = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    emp_drop_email_unique_if_exists($conn);

    // Populate form state from POST
    foreach ($form as $key => $default) {
        $form[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $selectedSystemAccessIds = array_values(array_unique(array_map('intval', $_POST['system_access_ids'] ?? [])));

    // Validation
    if ($form['first_name'] === '') { $errors[] = 'First Name is required.'; }
    if ($form['last_name'] === '') { $errors[] = 'Last Name is required.'; }
    
    // Auto-generate display name if missing
    if ($form['display_name'] === '') { $form['display_name'] = trim($form['first_name'] . ' ' . $form['last_name']); }

    // Execute insertion
    if (empty($errors)) {
        $firstName = mysqli_real_escape_string($conn, $form['first_name']);
        $lastName = mysqli_real_escape_string($conn, $form['last_name']);
        $displayName = $form['display_name'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['display_name']) . "'";
        $email = $form['email'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['email']) . "'";
        $hiltonId = $form['hilton_id'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['hilton_id']) . "'";
        $username = $form['username'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['username']) . "'";
        $jobCode = $form['job_code'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['job_code']) . "'";
        $jobTitle = $form['job_title'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['job_title']) . "'";
        $departmentId = $form['department_id'] === '' ? 'NULL' : (string)(int)$form['department_id'];
        $officeDeptId = $form['office_key_card_department_id'] === '' ? 'NULL' : (string)(int)$form['office_key_card_department_id'];
        $rawStatusCode = $form['raw_status_code'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['raw_status_code']) . "'";
        $employmentStatusId = $form['employment_status_id'] === '' ? '1' : (string)(int)$form['employment_status_id'];
        $comments = $form['comments'] === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $form['comments']) . "'";
        $sql = "INSERT INTO employees (
            company_id, first_name, last_name, display_name, email, hilton_id, username,
            department_id, job_code, job_title, comments, raw_status_code, employment_status_id,
            office_key_card_department_id
        ) VALUES (
            " . (int)$company_id . ", '{$firstName}', '{$lastName}', {$displayName}, {$email}, {$hiltonId}, {$username},
            {$departmentId}, {$jobCode}, {$jobTitle}, {$comments}, {$rawStatusCode}, {$employmentStatusId},
            {$officeDeptId}
        )";

        if (mysqli_query($conn, $sql)) {
            $newEmployeeId = (int)mysqli_insert_id($conn);
            // Save associated system permissions in the relations table
            esa_save_employee_access_ids($conn, (int)$company_id, $newEmployeeId, $selectedSystemAccessIds);
            header('Location: index.php');
            exit;
        }
        $errors[] = 'Could not save employee: ' . mysqli_error($conn);
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
    <title>Create Employee</title>
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

            <?php foreach ($errors as $error): ?><div class="alert alert-error"><?php echo sanitize($error); ?></div><?php endforeach; ?>

            <div class="card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        <div class="form-group"><label>First Name *</label><input type="text" name="first_name" value="<?php echo sanitize($form['first_name']); ?>" required></div>
                        <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" value="<?php echo sanitize($form['last_name']); ?>" required></div>
                        <div class="form-group"><label>Display Name</label><input type="text" name="display_name" value="<?php echo sanitize($form['display_name']); ?>"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize($form['email']); ?>"></div>
                        <div class="form-group"><label>Id</label><input type="text" name="hilton_id" value="<?php echo sanitize($form['hilton_id']); ?>"></div>
                        <div class="form-group"><label>Username</label><input type="text" name="username" value="<?php echo sanitize($form['username']); ?>"></div>
                        <div class="form-group"><label>Job Code</label><input type="text" name="job_code" value="<?php echo sanitize($form['job_code']); ?>"></div>
                        <div class="form-group"><label>Job Title</label><input type="text" name="job_title" value="<?php echo sanitize($form['job_title']); ?>"></div>
                        
                        <!-- DROP DOWNS WITH INLINE ADD SUPPORT -->
                        <div class="form-group"><label>Department</label>
                            <select name="department_id" data-addable-select="1" data-add-table="departments" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="department">
                                <option value="">-- None --</option>
                                <?php if ($departments): while ($d = mysqli_fetch_assoc($departments)): ?>
                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo ((string)$d['id'] === (string)$form['department_id']) ? 'selected' : ''; ?>><?php echo sanitize((string)$d['name']); ?></option>
                                <?php endwhile; endif; ?>
                                <option value="__add_new__">➕ Add</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Office Key Card Department</label>
                            <select name="office_key_card_department_id" data-addable-select="1" data-add-table="departments" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="office key card department">
                                <option value="">-- None --</option>
                                <?php if ($departments instanceof mysqli_result) { mysqli_data_seek($departments, 0); } ?>
                                <?php if ($departments): while ($d = mysqli_fetch_assoc($departments)): ?>
                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo ((string)$d['id'] === (string)$form['office_key_card_department_id']) ? 'selected' : ''; ?>><?php echo sanitize((string)$d['name']); ?></option>
                                <?php endwhile; endif; ?>
                                <option value="__add_new__">➕ Add</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Raw Status Code</label><input type="text" name="raw_status_code" value="<?php echo sanitize($form['raw_status_code']); ?>"></div>
                        <div class="form-group"><label>Employment Status</label>
                            <select name="employment_status_id" data-addable-select="1" data-add-table="employee_statuses" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="employment status">
                                <?php if ($statuses): while ($s = mysqli_fetch_assoc($statuses)): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ((string)$s['id'] === (string)$form['employment_status_id']) ? 'selected' : ''; ?>><?php echo sanitize((string)$s['name']); ?></option>
                                <?php endwhile; endif; ?>
                                <option value="__add_new__">➕ Add</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:12px;"><label>Comments</label><textarea name="comments" rows="3"><?php echo sanitize($form['comments']); ?></textarea></div>

                    <!-- INTEGRATED SYSTEM ACCESS PERMISSIONS -->
                    <h3>System Access</h3>
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
</div>
</body>
</html>
