<?php
/**
 * Problem Management — edit form.
 */

$crud_title = 'Problem Management';
$moduleSlug = 'problems';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';

itm_require_crud_role_module_permission($conn, 'edit', $moduleSlug);

$companyId = (int)$company_id;
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$errors = [];
$csrfToken = itm_get_csrf_token();
$editId = max(0, (int)($_GET['id'] ?? 0));

$problem = $editId > 0 ? itm_problem_fetch_row($conn, $companyId, $editId) : null;
if (!$problem) {
    $errors[] = 'Problem not found.';
}

$data = [
    'title' => (string)($problem['title'] ?? ''),
    'description' => (string)($problem['description'] ?? ''),
    'root_cause' => (string)($problem['root_cause'] ?? ''),
    'status' => (string)($problem['status'] ?? 'investigating'),
    'owner_employee_id' => (string)($problem['owner_employee_id'] ?? ''),
];

$employees = [];
$empStmt = mysqli_prepare(
    $conn,
    'SELECT id, first_name, last_name, username FROM employees WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY first_name ASC, last_name ASC'
);
if ($empStmt) {
    mysqli_stmt_bind_param($empStmt, 'i', $companyId);
    mysqli_stmt_execute($empStmt);
    $empRes = mysqli_stmt_get_result($empStmt);
    while ($empRes && ($empRow = mysqli_fetch_assoc($empRes))) {
        $employees[] = $empRow;
    }
    mysqli_stmt_close($empStmt);
}

$ownerId = (int)($data['owner_employee_id'] ?? 0);
if ($ownerId > 0) {
    $found = false;
    foreach ($employees as $emp) {
        if ((int)$emp['id'] === $ownerId) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $persistStmt = mysqli_prepare(
            $conn,
            'SELECT id, first_name, last_name, username FROM employees WHERE id = ? AND company_id = ? LIMIT 1'
        );
        if ($persistStmt) {
            mysqli_stmt_bind_param($persistStmt, 'ii', $ownerId, $companyId);
            mysqli_stmt_execute($persistStmt);
            $persistRes = mysqli_stmt_get_result($persistStmt);
            if ($persistRes && ($persistRow = mysqli_fetch_assoc($persistRes))) {
                $employees[] = $persistRow;
            }
            mysqli_stmt_close($persistStmt);
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $problem) {
    itm_require_post_csrf();

    $data['title'] = trim((string)($_POST['title'] ?? ''));
    $data['description'] = (string)($_POST['description'] ?? '');
    $data['root_cause'] = trim((string)($_POST['root_cause'] ?? ''));
    $data['status'] = (string)($_POST['status'] ?? $data['status']);
    $data['owner_employee_id'] = (string)(int)($_POST['owner_employee_id'] ?? 0);

    $result = itm_problem_update($conn, $companyId, $editId, [
        'title' => $data['title'],
        'description' => $data['description'],
        'root_cause' => $data['root_cause'],
        'status' => $data['status'],
        'owner_employee_id' => (int)$data['owner_employee_id'],
    ], $employeeId);

    if (empty($result['ok'])) {
        $errors[] = (string)($result['error'] ?? 'Could not update problem.');
    } else {
        header('Location: view.php?id=' . $editId);
        exit;
    }
}

$statusOptions = itm_problem_status_options();
$currentStatus = strtolower(trim((string)($data['status'] ?? 'investigating')));
$allowedStatuses = itm_problem_allowed_transitions()[$currentStatus] ?? array_keys($statusOptions);

$moduleSlugPath = basename(dirname($_SERVER['PHP_SELF']));
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
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $pageTitle = itm_crud_apply_module_icon_to_browser_title($conn, $companyId, $employeeId, $moduleSlugPath, 'Edit Problem');
    ?>
    <title><?= sanitize($pageTitle) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <?php if ($problem): ?>
                <h1 title="Edit problem">✏️</h1>
                <form method="POST" class="form-grid" style="max-width:980px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">

                    <div class="form-group">
                        <label for="problem-title">Title</label>
                        <input type="text" id="problem-title" name="title" value="<?php echo sanitize($data['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="problem-description">Description</label>
                        <textarea id="problem-description" name="description" rows="5"><?php echo sanitize($data['description']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="problem-root-cause">Root Cause</label>
                        <textarea id="problem-root-cause" name="root_cause" rows="4"><?php echo sanitize($data['root_cause']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="problem-status">Status</label>
                        <select id="problem-status" name="status">
                            <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
                                <?php if (!in_array($statusKey, $allowedStatuses, true)) {
                                    continue;
                                } ?>
                                <option value="<?php echo sanitize($statusKey); ?>" <?php echo ($data['status'] === $statusKey) ? 'selected' : ''; ?>><?php echo sanitize($statusLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="problem-owner">Owner</label>
                        <select id="problem-owner" name="owner_employee_id">
                            <option value="">— Select —</option>
                            <?php foreach ($employees as $emp): ?>
                                <?php
                                $empLabel = trim((string)($emp['first_name'] ?? '') . ' ' . (string)($emp['last_name'] ?? ''));
                                if ($empLabel === '') {
                                    $empLabel = (string)($emp['username'] ?? 'Employee');
                                }
                                ?>
                                <option value="<?php echo (int)$emp['id']; ?>" <?php echo ((string)$data['owner_employee_id'] === (string)$emp['id']) ? 'selected' : ''; ?>><?php echo sanitize($empLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit" title="Save">💾</button>
                        <a href="view.php?id=<?php echo $editId; ?>" class="btn" title="Back">🔙</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
