<?php
/**
 * Employees module — fast demo account creator (module-scoped RBAC).
 *
 * Browser UI for the active session company. CLI / seed bundle remain on scripts/fast_create_acc.php.
 */

declare(strict_types=1);

require '../../config/config.php';
itm_require_admin($conn, $_SESSION['employee_id'] ?? 0);
require_once '../../includes/itm_fk_option_labels.php';
require_once ROOT_PATH . 'scripts/lib/itm_demo_module_users_seed.php';

$selectedCompanyId = (int)($company_id ?? 0);
if ($selectedCompanyId <= 0) {
    $selectedCompanyId = (int)($_SESSION['company_id'] ?? 1);
}

$fkOptions = itm_demo_module_users_fetch_fk_options($conn, $selectedCompanyId);
$grantedByEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$messages = [];
$errors = [];

$form = [
    'company_id' => $selectedCompanyId,
    'username' => '',
    'password' => '',
    'first_name' => '',
    'last_name' => 'Demo',
    'work_email' => '',
    'personal_email' => '',
    'role_id' => 0,
    'role_name' => '',
    'module_slugs' => [],
    'access_level_id' => 0,
    'employment_status_id' => 0,
    'department_id' => 0,
    'department_ids' => [],
    'employee_position_id' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $postedModuleSlugs = $_POST['module_slugs'] ?? [];
    if (!is_array($postedModuleSlugs)) {
        $postedModuleSlugs = [];
    }
    $form['module_slugs'] = array_values(array_filter(array_map('trim', $postedModuleSlugs)));
    $form['department_ids'] = itm_employee_normalize_department_ids($_POST['department_ids'] ?? []);
    $form['department_id'] = (int)($form['department_ids'][0] ?? 0);
    $form['company_id'] = $selectedCompanyId;

    foreach (['role_id', 'employment_status_id', 'access_level_id', 'employee_position_id'] as $intFkField) {
        if (isset($_POST[$intFkField]) && (string)$_POST[$intFkField] === '__add_new__') {
            $_POST[$intFkField] = '0';
        }
    }

    foreach (['username', 'password', 'first_name', 'last_name', 'work_email', 'personal_email', 'role_name'] as $textField) {
        $form[$textField] = trim((string)($_POST[$textField] ?? ''));
    }
    foreach (['role_id', 'access_level_id', 'employment_status_id', 'employee_position_id'] as $intField) {
        $form[$intField] = (int)($_POST[$intField] ?? 0);
    }

    if ($form['module_slugs'] === []) {
        $errors[] = 'Select at least one module.';
    }
    if ($form['username'] === '') {
        $errors[] = 'Username is required.';
    }
    if ($form['password'] === '') {
        $errors[] = 'Password is required.';
    }
    $emailError = itm_employee_validate_contact_email_or_error($form['work_email'], $form['personal_email']);
    if ($emailError !== null) {
        $errors[] = $emailError;
    }

    if ($errors === []) {
        $payload = [
            'company_id' => $selectedCompanyId,
            'username' => $form['username'],
            'password' => $form['password'],
            'first_name' => $form['first_name'],
            'last_name' => $form['last_name'] !== '' ? $form['last_name'] : 'Demo',
            'work_email' => $form['work_email'],
            'personal_email' => $form['personal_email'],
            'role_id' => (int)$form['role_id'],
            'role_name' => $form['role_name'],
            'module_slugs' => $form['module_slugs'],
            'access_level_id' => (int)$form['access_level_id'],
            'employment_status_id' => (int)$form['employment_status_id'],
            'department_id' => (int)$form['department_id'],
            'department_ids' => $form['department_ids'],
            'employee_position_id' => (int)$form['employee_position_id'],
            'granted_by_employee_id' => $grantedByEmployeeId,
        ];

        $upsert = itm_demo_module_users_upsert_employee($conn, $payload);
        $messages = array_merge($messages, $upsert['messages']);
        $errors = array_merge($errors, $upsert['errors']);
        $fkOptions = itm_demo_module_users_fetch_fk_options($conn, $selectedCompanyId);
    }
}

$roleLookup = [];
foreach ($fkOptions['employee_roles'] as $roleRow) {
    $roleId = (int)($roleRow['id'] ?? 0);
    if ($roleId > 0) {
        $roleLookup[$roleId] = (string)($roleRow['name'] ?? '');
    }
}
$selectedRoleId = (int)$form['role_id'];
$selectedDepartmentIds = is_array($form['department_ids']) ? $form['department_ids'] : [];

$crud_title = 'Fast Create Account';
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
    ?>
    <title><?php echo sanitize($crud_title); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
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
                <h1 style="margin:0;" title="Fast create account">🚀</h1>
                <a href="index.php" class="btn" title="Back">🔙</a>
            </div>

            <?php echo itm_render_alert_errors($errors); ?>
            <?php foreach ($messages as $message): ?>
                <div class="alert alert-success"><?php echo sanitize($message); ?></div>
            <?php endforeach; ?>

            <div class="card">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="company_id" value="<?php echo (int)$selectedCompanyId; ?>">

                    <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                        <div class="form-group full" style="grid-column:1 / -1;">
                            <label for="module_slugs">Modules *</label>
                            <select name="module_slugs[]" id="module_slugs" multiple required style="min-height:220px;">
                                <?php foreach ($fkOptions['modules'] as $module): ?>
                                    <?php
                                    $slug = (string)$module['module_slug'];
                                    $isSelected = in_array($slug, $form['module_slugs'], true);
                                    ?>
                                    <option value="<?php echo sanitize($slug); ?>"<?php echo $isSelected ? ' selected' : ''; ?>>
                                        <?php echo sanitize((string)$module['module_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group"><label for="username">Username *</label><input type="text" name="username" id="username" required value="<?php echo sanitize($form['username']); ?>"></div>
                        <div class="form-group"><label for="password">Password *</label><input type="text" name="password" id="password" required value="<?php echo sanitize($form['password']); ?>"></div>
                        <div class="form-group"><label for="first_name">First name</label><input type="text" name="first_name" id="first_name" value="<?php echo sanitize($form['first_name']); ?>"></div>
                        <div class="form-group"><label for="last_name">Last name</label><input type="text" name="last_name" id="last_name" value="<?php echo sanitize($form['last_name']); ?>"></div>
                        <div class="form-group"><label for="work_email">Work email</label><input type="email" name="work_email" id="work_email" value="<?php echo sanitize($form['work_email']); ?>"></div>
                        <div class="form-group"><label for="personal_email">Personal email</label><input type="email" name="personal_email" id="personal_email" value="<?php echo sanitize($form['personal_email']); ?>"></div>

                        <div class="form-group"><label for="role_id">Role *</label>
                            <select name="role_id" id="role_id" required
                                data-addable-select="1"
                                data-add-table="employee_roles"
                                data-add-id-col="id"
                                data-add-label-col="name"
                                data-add-company-scoped="1"
                                data-add-friendly="role">
                                <option value="">-- Select --</option>
                                <?php foreach ($roleLookup as $roleId => $roleName): ?>
                                    <option value="<?php echo (int)$roleId; ?>"<?php echo (int)$roleId === $selectedRoleId ? ' selected' : ''; ?>><?php echo sanitize($roleName); ?></option>
                                <?php endforeach; ?>
                                <?php if ($selectedRoleId > 0 && !isset($roleLookup[$selectedRoleId])): ?>
                                    <option value="<?php echo (int)$selectedRoleId; ?>" selected>#<?php echo (int)$selectedRoleId; ?></option>
                                <?php endif; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>

                        <div class="form-group"><label for="employment_status_id">Employment status</label>
                            <select name="employment_status_id" id="employment_status_id"
                                data-addable-select="1"
                                data-add-table="employee_statuses"
                                data-add-id-col="id"
                                data-add-label-col="name"
                                data-add-company-scoped="1"
                                data-add-friendly="employment status">
                                <option value="0">-- Auto (Active) --</option>
                                <?php foreach ($fkOptions['employee_statuses'] as $row): ?>
                                    <option value="<?php echo (int)$row['id']; ?>"<?php echo (int)$row['id'] === (int)$form['employment_status_id'] ? ' selected' : ''; ?>><?php echo sanitize((string)$row['name']); ?></option>
                                <?php endforeach; ?>
                                <?php if ((int)$form['employment_status_id'] > 0 && !in_array((int)$form['employment_status_id'], array_map('intval', array_column($fkOptions['employee_statuses'], 'id')), true)): ?>
                                    <option value="<?php echo (int)$form['employment_status_id']; ?>" selected>#<?php echo (int)$form['employment_status_id']; ?></option>
                                <?php endif; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>

                        <div class="form-group"><label for="access_level_id">Access level</label>
                            <select name="access_level_id" id="access_level_id"
                                data-addable-select="1"
                                data-add-table="access_levels"
                                data-add-id-col="id"
                                data-add-label-col="name"
                                data-add-company-scoped="1"
                                data-add-friendly="access level">
                                <option value="0">-- Auto (Limited) --</option>
                                <?php foreach ($fkOptions['access_levels'] as $row): ?>
                                    <option value="<?php echo (int)$row['id']; ?>"<?php echo (int)$row['id'] === (int)$form['access_level_id'] ? ' selected' : ''; ?>><?php echo sanitize((string)$row['name']); ?></option>
                                <?php endforeach; ?>
                                <?php if ((int)$form['access_level_id'] > 0 && !in_array((int)$form['access_level_id'], array_map('intval', array_column($fkOptions['access_levels'], 'id')), true)): ?>
                                    <option value="<?php echo (int)$form['access_level_id']; ?>" selected>#<?php echo (int)$form['access_level_id']; ?></option>
                                <?php endif; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>

                        <div class="form-group"><label for="department_ids">Departments</label>
                            <select name="department_ids[]" id="department_ids" multiple size="5"
                                data-addable-select="1"
                                data-add-table="departments"
                                data-add-id-col="id"
                                data-add-label-col="name"
                                data-add-company-scoped="1"
                                data-add-friendly="department">
                                <?php foreach ($fkOptions['departments'] as $row): ?>
                                    <?php $deptId = (int)($row['id'] ?? 0); ?>
                                    <option value="<?php echo $deptId; ?>"<?php echo in_array($deptId, $selectedDepartmentIds, true) ? ' selected' : ''; ?>><?php echo sanitize(itm_department_option_label($row)); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>

                        <div class="form-group"><label for="employee_position_id">Position</label>
                            <select name="employee_position_id" id="employee_position_id"
                                data-addable-select="1"
                                data-add-table="employee_positions"
                                data-add-id-col="id"
                                data-add-label-col="name"
                                data-add-company-scoped="1"
                                data-add-friendly="position title">
                                <option value="0">-- None --</option>
                                <?php foreach ($fkOptions['employee_positions'] as $row): ?>
                                    <option value="<?php echo (int)$row['id']; ?>"<?php echo (int)$row['id'] === (int)$form['employee_position_id'] ? ' selected' : ''; ?>><?php echo sanitize((string)$row['name']); ?></option>
                                <?php endforeach; ?>
                                <?php if ((int)$form['employee_position_id'] > 0 && !in_array((int)$form['employee_position_id'], array_map('intval', array_column($fkOptions['employee_positions'], 'id')), true)): ?>
                                    <option value="<?php echo (int)$form['employee_position_id']; ?>" selected>#<?php echo (int)$form['employee_position_id']; ?></option>
                                <?php endif; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top:16px;">
                        <button type="submit" class="btn btn-primary" title="Create account">💾</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
window.ITM_BASE_URL = <?php echo json_encode(BASE_URL, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="../../js/select-add-option.js"></script>
</body>
</html>
