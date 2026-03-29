<?php
require '../../config/config.php';

function emp_drop_email_unique_if_exists($conn) {
    $legacyUniqueIndexes = [
        'uq_employees_email_per_company',
        'uq_employees_code_per_company'
    ];

    foreach ($legacyUniqueIndexes as $indexName) {
        $sql = "SELECT 1
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'employees'
                  AND index_name = '" . mysqli_real_escape_string($conn, $indexName) . "'
                LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($res && mysqli_num_rows($res) === 1) {
            mysqli_query($conn, 'ALTER TABLE employees DROP INDEX `' . str_replace('`', '``', $indexName) . '`');
        }
    }
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$employeeSql = 'SELECT * FROM employees WHERE id=' . $id . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
$employeeRes = mysqli_query($conn, $employeeSql);
$employee = ($employeeRes && mysqli_num_rows($employeeRes) === 1) ? mysqli_fetch_assoc($employeeRes) : null;
if (!$employee) {
    header('Location: index.php');
    exit;
}

$statuses = mysqli_query($conn, 'SELECT id, name FROM employee_statuses ORDER BY name');
$departments = mysqli_query($conn, 'SELECT id, name FROM departments WHERE company_id=' . (int)$company_id . ' ORDER BY name');

$errors = [];
$booleanFields = ['network_access','micros_emc','opera_username','micros_card','pms_id','synergy_mms','hu_the_lobby','navision','onq_ri','birchstreet','delphi','omina','vingcard_system','digital_rev','office_key_card'];

$form = [
    'first_name' => (string)($employee['first_name'] ?? ''),
    'last_name' => (string)($employee['last_name'] ?? ''),
    'display_name' => (string)($employee['display_name'] ?? ''),
    'email' => (string)($employee['email'] ?? ''),
    'hilton_id' => (string)($employee['hilton_id'] ?? ''),
    'username' => (string)($employee['username'] ?? ''),
    'job_code' => (string)($employee['job_code'] ?? ''),
    'job_title' => (string)($employee['job_title'] ?? ''),
    'department_id' => (string)($employee['department_id'] ?? ''),
    'raw_status_code' => (string)($employee['raw_status_code'] ?? 'A'),
    'employment_status_id' => (string)($employee['employment_status_id'] ?? '1'),
    'comments' => (string)($employee['comments'] ?? ''),
    'office_key_card_department_id' => (string)($employee['office_key_card_department_id'] ?? ''),
    'active' => ((int)($employee['active'] ?? 1) === 1) ? '1' : '0',
];

foreach ($booleanFields as $field) {
    $form[$field] = ((int)($employee[$field] ?? 0) === 1) ? '1' : '0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    emp_drop_email_unique_if_exists($conn);

    foreach ($form as $key => $default) {
        if (in_array($key, $booleanFields, true) || $key === 'active') {
            $form[$key] = isset($_POST[$key]) ? '1' : '0';
        } else {
            $form[$key] = trim((string)($_POST[$key] ?? ''));
        }
    }

    if ($form['first_name'] === '') {
        $errors[] = 'First Name is required.';
    }
    if ($form['last_name'] === '') {
        $errors[] = 'Last Name is required.';
    }
    if ($form['display_name'] === '') {
        $form['display_name'] = trim($form['first_name'] . ' ' . $form['last_name']);
    }

    if (!$errors) {
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

        $sql = "UPDATE employees SET
            first_name='{$firstName}',
            last_name='{$lastName}',
            display_name={$displayName},
            email={$email},
            hilton_id={$hiltonId},
            username={$username},
            department_id={$departmentId},
            job_code={$jobCode},
            job_title={$jobTitle},
            comments={$comments},
            raw_status_code={$rawStatusCode},
            employment_status_id={$employmentStatusId},
            network_access=" . (int)$form['network_access'] . ",
            micros_emc=" . (int)$form['micros_emc'] . ",
            opera_username=" . (int)$form['opera_username'] . ",
            micros_card=" . (int)$form['micros_card'] . ",
            pms_id=" . (int)$form['pms_id'] . ",
            synergy_mms=" . (int)$form['synergy_mms'] . ",
            hu_the_lobby=" . (int)$form['hu_the_lobby'] . ",
            navision=" . (int)$form['navision'] . ",
            onq_ri=" . (int)$form['onq_ri'] . ",
            birchstreet=" . (int)$form['birchstreet'] . ",
            delphi=" . (int)$form['delphi'] . ",
            omina=" . (int)$form['omina'] . ",
            vingcard_system=" . (int)$form['vingcard_system'] . ",
            digital_rev=" . (int)$form['digital_rev'] . ",
            office_key_card=" . (int)$form['office_key_card'] . ",
            office_key_card_department_id={$officeDeptId},
            active=" . (int)$form['active'] . "
            WHERE id={$id} AND company_id=" . (int)$company_id . " LIMIT 1";

        if (mysqli_query($conn, $sql)) {
            header('Location: view.php?id=' . $id);
            exit;
        }
        $errors[] = 'Could not update employee: ' . mysqli_error($conn);
    }
}

function emp_checked($form, $field) {
    return (($form[$field] ?? '0') === '1') ? 'checked' : '';
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
                <a href="index.php" class="btn">← Back</a>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?php echo sanitize($error); ?></div>
            <?php endforeach; ?>

            <div class="card">
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        <div class="form-group"><label>First Name *</label><input type="text" name="first_name" value="<?php echo sanitize($form['first_name']); ?>" required></div>
                        <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" value="<?php echo sanitize($form['last_name']); ?>" required></div>
                        <div class="form-group"><label>Display Name</label><input type="text" name="display_name" value="<?php echo sanitize($form['display_name']); ?>"></div>
                        <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo sanitize($form['email']); ?>"></div>
                        <div class="form-group"><label>Hilton Id</label><input type="text" name="hilton_id" value="<?php echo sanitize($form['hilton_id']); ?>"></div>
                        <div class="form-group"><label>Username</label><input type="text" name="username" value="<?php echo sanitize($form['username']); ?>"></div>
                        <div class="form-group"><label>Job Code</label><input type="text" name="job_code" value="<?php echo sanitize($form['job_code']); ?>"></div>
                        <div class="form-group"><label>Job Title</label><input type="text" name="job_title" value="<?php echo sanitize($form['job_title']); ?>"></div>
                        <div class="form-group"><label>Department</label>
                            <select name="department_id" data-addable-select="1" data-add-table="departments" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="1" data-add-friendly="department">
                                <option value="">-- None --</option>
                                <?php if ($departments): while ($d = mysqli_fetch_assoc($departments)): ?>
                                    <option value="<?php echo (int)$d['id']; ?>" <?php echo ((string)$d['id'] === (string)$form['department_id']) ? 'selected' : ''; ?>><?php echo sanitize((string)$d['name']); ?></option>
                                <?php endwhile; endif; ?>
                                <option value="__add_new__">➕ Add</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Office Key Card Department Id</label>
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
                            <select name="employment_status_id" data-addable-select="1" data-add-table="employee_statuses" data-add-id-col="id" data-add-label-col="name" data-add-company-scoped="0" data-add-friendly="employment status">
                                <?php if ($statuses): while ($s = mysqli_fetch_assoc($statuses)): ?>
                                    <option value="<?php echo (int)$s['id']; ?>" <?php echo ((string)$s['id'] === (string)$form['employment_status_id']) ? 'selected' : ''; ?>><?php echo sanitize((string)$s['name']); ?></option>
                                <?php endwhile; endif; ?>
                                <option value="__add_new__">➕ Add</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:12px;"><label>Comments</label><textarea name="comments" rows="3"><?php echo sanitize($form['comments']); ?></textarea></div>

                    <h3>System Access</h3>
                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;">
                        <?php foreach ($booleanFields as $field): ?>
                            <label><input type="checkbox" name="<?php echo sanitize($field); ?>" value="1" <?php echo emp_checked($form, $field); ?>> <?php echo sanitize(ucwords(str_replace('_', ' ', $field))); ?></label>
                        <?php endforeach; ?>
                        <label><input type="checkbox" name="active" value="1" <?php echo (($form['active'] ?? '1') === '1') ? 'checked' : ''; ?>> Active</label>
                    </div>

                    <div class="form-actions" style="margin-top:16px;display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="view.php?id=<?php echo (int)$id; ?>" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
