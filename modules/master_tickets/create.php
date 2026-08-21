<?php
/**
 * Master Tickets — create from an existing major problem (≥1 linked incident).
 */

$moduleSlug = 'master_tickets';
$pageTitle = 'Master Tickets';

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';

itm_require_crud_role_module_permission($conn, 'create', $moduleSlug);

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$sessionCompanyId = (int)$company_id;
$allowedCompanyIds = itm_master_ticket_allowed_company_ids($conn, $employeeId);
$csrfToken = itm_get_csrf_token();
$errors = [];
$flash = '';

$eligibleProblems = itm_master_ticket_list_eligible_problems($conn, $allowedCompanyIds);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $problemCompanyId = max(0, (int)($_POST['problem_company_id'] ?? 0));
    $problemId = max(0, (int)($_POST['problem_id'] ?? 0));

    if ($problemCompanyId <= 0 || $problemId <= 0) {
        $errors[] = 'Select a problem with at least one linked incident.';
    } elseif (!in_array($problemCompanyId, $allowedCompanyIds, true)) {
        $errors[] = 'You do not have access to that company.';
    } else {
        $createMaster = itm_problem_create_master_ticket($conn, $problemCompanyId, $problemId, $employeeId, $sessionCompanyId);
        if (!empty($createMaster['ok'])) {
            $masterId = (int)($createMaster['master_ticket_id'] ?? 0);
            header('Location: view.php?id=' . $masterId);
            exit;
        }
        $errors[] = (string)($createMaster['error'] ?? 'Could not create master ticket.');
    }
}

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
    $browserTitle = itm_crud_apply_module_icon_to_browser_title($conn, $sessionCompanyId, $employeeId, $moduleSlugPath, 'New master ticket');
    ?>
    <title><?= sanitize($browserTitle) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
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

            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
                <h1 title="New master ticket">➕</h1>
                <a href="index.php" class="btn" title="Back">🔙</a>
            </div>

            <div class="card" style="margin-bottom:20px;">
                <p style="margin-top:0;">
                    <strong>Global master</strong> — <code>master_tickets</code> has no <code>company_id</code>.
                    Pick an existing <strong>major</strong> problem (already linked to one or more incident tickets)
                    from any company you can access. Title, description, and root cause copy from that problem;
                    all linked incidents sync after create.
                </p>
                <p style="margin-bottom:0;">
                    Problems still live in
                    <a class="itm-plain-link" href="../problems/index.php">Problem Management</a> — link incidents there first if none appear below.
                </p>
            </div>

            <?php if (empty($eligibleProblems)): ?>
                <div class="card">
                    <p>No eligible problems: each needs at least one linked incident and no existing master ticket.</p>
                    <a href="../problems/index.php" class="btn btn-primary" title="Open problems">🔎</a>
                </div>
            <?php else: ?>
                <div class="card">
                    <form method="POST" class="form-grid" style="max-width:720px;">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <div class="form-group">
                            <label for="problem-pick">Existing problem (major)</label>
                            <select id="problem-pick" name="problem_id" required>
                                <option value="">— Select —</option>
                                <?php foreach ($eligibleProblems as $prob): ?>
                                    <?php
                                    $pid = (int)($prob['id'] ?? 0);
                                    $pcid = (int)($prob['company_id'] ?? 0);
                                    $label = sanitize((string)($prob['company_name'] ?? ''))
                                        . ' · #' . $pid . ' · '
                                        . (string)($prob['title'] ?? '')
                                        . ' · '
                                        . (int)($prob['incident_count'] ?? 0) . ' incident(s) · '
                                        . (string)($prob['status'] ?? '');
                                    ?>
                                    <option value="<?php echo $pid; ?>" data-company-id="<?php echo $pcid; ?>">
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="problem_company_id" id="problem-company-id" value="">
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" title="Create">➕</button>
                            <a href="index.php" class="btn" title="Back">🔙</a>
                        </div>
                    </form>
                </div>
                <script>
                (function () {
                    var sel = document.getElementById('problem-pick');
                    var hid = document.getElementById('problem-company-id');
                    if (!sel || !hid) return;
                    function syncCompany() {
                        var opt = sel.options[sel.selectedIndex];
                        hid.value = opt && opt.dataset.companyId ? opt.dataset.companyId : '';
                    }
                    sel.addEventListener('change', syncCompany);
                    syncCompany();
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
