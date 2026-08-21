<?php
/**
 * Problem Management — create form with optional ticket auto-link.
 */

$crud_title = 'Problem Management';
$moduleSlug = 'problems';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';

itm_require_crud_role_module_permission($conn, 'create', $moduleSlug);

$companyId = (int)$company_id;
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$errors = [];
$csrfToken = itm_get_csrf_token();
$prefillTicketId = max(0, (int)($_GET['ticket_id'] ?? 0));

$data = [
    'title' => '',
    'description' => '',
    'root_cause' => '',
    'status' => 'investigating',
    'owner_employee_id' => $employeeId > 0 ? (string)$employeeId : '',
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

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();

    $data['title'] = trim((string)($_POST['title'] ?? ''));
    $data['description'] = (string)($_POST['description'] ?? '');
    $data['root_cause'] = trim((string)($_POST['root_cause'] ?? ''));
    $data['status'] = (string)($_POST['status'] ?? 'investigating');
    $data['owner_employee_id'] = (string)(int)($_POST['owner_employee_id'] ?? 0);

    $result = itm_problem_create($conn, $companyId, [
        'title' => $data['title'],
        'description' => $data['description'],
        'root_cause' => $data['root_cause'],
        'status' => $data['status'],
        'owner_employee_id' => (int)$data['owner_employee_id'],
    ], $employeeId);

    if (empty($result['ok'])) {
        $errors[] = (string)($result['error'] ?? 'Could not create problem.');
    } else {
        $newId = (int)($result['id'] ?? 0);
        $linkTicketId = $prefillTicketId > 0 ? $prefillTicketId : max(0, (int)($_POST['ticket_id'] ?? 0));
        if ($newId > 0 && $linkTicketId > 0) {
            $linkResult = itm_problem_link_ticket($conn, $companyId, $newId, $linkTicketId, $employeeId);
            if (empty($linkResult['ok'])) {
                $_SESSION['crud_error'] = (string)($linkResult['error'] ?? 'Problem created but ticket link failed.');
            }
        }
        header('Location: view.php?id=' . $newId);
        exit;
    }
}

$statusOptions = itm_problem_status_options();
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
    $pageTitle = itm_crud_apply_module_icon_to_browser_title($conn, $companyId, $employeeId, $moduleSlugPath, 'New Problem');
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

            <h1 title="New problem">➕</h1>
            <?php if ($prefillTicketId > 0): ?>
                <p style="margin-bottom:16px;color:var(--text-secondary);">After save, ticket #<?php echo $prefillTicketId; ?> will be linked automatically.</p>
            <?php endif; ?>

            <form method="POST" class="form-grid" style="max-width:980px;">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <?php if ($prefillTicketId > 0): ?>
                    <input type="hidden" name="ticket_id" value="<?php echo $prefillTicketId; ?>">
                <?php endif; ?>

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
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
