<?php
/**
 * Change Requests — create / edit with CMDB impact graph CI picker.
 */
require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete on standalone entry files.
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);

require_once ROOT_PATH . 'includes/itm_change_requests.php';
require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;
$companyId = (int)$company_id;
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$error = '';
$csrfToken = itm_get_csrf_token();
$ciOptions = itm_cmdb_list_ci_options($conn, $companyId);
$changeTypes = itm_change_request_change_types();
$riskLevels = itm_change_request_risk_levels();

$data = [
    'title' => '',
    'description' => '',
    'status' => 'draft',
    'change_type' => 'standard',
    'risk_level' => 'medium',
    'rollback_plan' => '',
    'ticket_id' => 0,
    'source_configuration_item_id' => 0,
    'scheduled_start' => '',
    'scheduled_end' => '',
];
$selectedCiIds = [];
$formStatuses = itm_change_request_form_status_options($conn, $employeeId, 'draft');

$ticketOptions = [];
$ticketStmt = mysqli_prepare(
    $conn,
    'SELECT id, title FROM tickets WHERE company_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 100'
);
if ($ticketStmt) {
    mysqli_stmt_bind_param($ticketStmt, 'i', $companyId);
    mysqli_stmt_execute($ticketStmt);
    $ticketRes = mysqli_stmt_get_result($ticketStmt);
    while ($ticketRes && ($tRow = mysqli_fetch_assoc($ticketRes))) {
        $ticketOptions[] = $tRow;
    }
    mysqli_stmt_close($ticketStmt);
}

if ($isEdit) {
    $row = itm_change_request_fetch_row($conn, $companyId, $id);
    if (!$row) {
        header('Location: index.php');
        exit;
    }
    $data = array_merge($data, $row);
    $selectedCiIds = itm_change_request_list_affected_ci_ids($conn, $companyId, $id);
    $formStatuses = itm_change_request_form_status_options($conn, $employeeId, (string)($row['status'] ?? 'draft'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $postedCiIds = $_POST['configuration_item_ids'] ?? [];
    if (!is_array($postedCiIds)) {
        $postedCiIds = [];
    }

    $postData = [
        'title' => trim((string)($_POST['title'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'status' => strtolower(trim((string)($_POST['status'] ?? 'draft'))),
        'change_type' => strtolower(trim((string)($_POST['change_type'] ?? 'standard'))),
        'risk_level' => strtolower(trim((string)($_POST['risk_level'] ?? 'medium'))),
        'rollback_plan' => trim((string)($_POST['rollback_plan'] ?? '')),
        'ticket_id' => (int)($_POST['ticket_id'] ?? 0),
        'source_configuration_item_id' => (int)($_POST['source_configuration_item_id'] ?? 0),
        'scheduled_start' => trim((string)($_POST['scheduled_start'] ?? '')),
        'scheduled_end' => trim((string)($_POST['scheduled_end'] ?? '')),
    ];

    $result = itm_change_request_save($conn, $companyId, $employeeId, $isEdit ? $id : 0, $postData, $postedCiIds);
    if (!empty($result['ok'])) {
        header('Location: view.php?id=' . (int)($result['id'] ?? $id));
        exit;
    }
    $error = (string)($result['error'] ?? 'Save failed.');
    $data = array_merge($data, $postData);
    $selectedCiIds = array_map('intval', $postedCiIds);
    $formStatuses = itm_change_request_form_status_options($conn, $employeeId, (string)($postData['status'] ?? 'draft'));
}

$crud_title = $isEdit ? 'Edit Change Request' : 'New Change Request';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $companyId, $employeeId, 'change_requests', $crud_title);
$impactApiUrl = BASE_URL . 'modules/configuration_items/api.php';
$ciViewBase = BASE_URL . 'modules/configuration_items/view.php?id=';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($crud_title); ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($ui_config ?? [])); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .org-chart-container { width:100%; height:360px; border:1px solid var(--border-color,#ddd); overflow:auto; position:relative; background:#f8f9fa; cursor:grab; }
        .org-node { width:200px; background:#fff; border:1px solid #00d1b2; border-radius:4px; text-align:center; position:absolute; box-shadow:0 2px 4px rgba(0,0,0,.05); z-index:10; }
        #cmdb-impact-picker-nodes { position:absolute; top:0; left:0; }
        #cmdb-impact-picker-svg { position:absolute; top:0; left:0; width:4000px; height:2400px; pointer-events:none; }
        .cmdb-ci-checklist { margin-top:12px; max-height:200px; overflow:auto; border:1px solid var(--border-color,#ddd); padding:8px; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1 title="<?php echo $isEdit ? 'Edit change request' : 'New change request'; ?>"><?php echo $isEdit ? '✏️' : '➕'; ?></h1>
            <?php if ($error !== ''): ?>
            <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="card">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" name="title" id="title" required value="<?php echo sanitize((string)($data['title'] ?? '')); ?>">
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="4"><?php echo sanitize((string)($data['description'] ?? '')); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="change_type">Change type</label>
                    <select name="change_type" id="change_type">
                        <?php foreach ($changeTypes as $slug => $label): ?>
                        <option value="<?php echo sanitize($slug); ?>"<?php echo (string)($data['change_type'] ?? '') === $slug ? ' selected' : ''; ?>><?php echo sanitize($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="risk_level">Risk</label>
                    <select name="risk_level" id="risk_level">
                        <?php foreach ($riskLevels as $slug => $label): ?>
                        <option value="<?php echo sanitize($slug); ?>"<?php echo (string)($data['risk_level'] ?? '') === $slug ? ' selected' : ''; ?>><?php echo sanitize($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="rollback_plan">Rollback plan</label>
                    <textarea name="rollback_plan" id="rollback_plan" rows="3"><?php echo sanitize((string)($data['rollback_plan'] ?? '')); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="ticket_id">Linked ticket (optional)</label>
                    <select name="ticket_id" id="ticket_id">
                        <option value="0">-- None --</option>
                        <?php foreach ($ticketOptions as $tOpt): ?>
                        <option value="<?php echo (int)($tOpt['id'] ?? 0); ?>"<?php echo (int)($data['ticket_id'] ?? 0) === (int)($tOpt['id'] ?? 0) ? ' selected' : ''; ?>>
                            #<?php echo (int)($tOpt['id'] ?? 0); ?> — <?php echo sanitize((string)($tOpt['title'] ?? '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="source_configuration_item_id">Source CI (change target)</label>
                    <select name="source_configuration_item_id" id="source_configuration_item_id" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($ciOptions as $opt): ?>
                        <option value="<?php echo (int)($opt['id'] ?? 0); ?>"<?php echo (int)($data['source_configuration_item_id'] ?? 0) === (int)($opt['id'] ?? 0) ? ' selected' : ''; ?>>
                            <?php echo sanitize((string)($opt['name'] ?? '') . ' (' . (string)($opt['ci_type_name'] ?? '') . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <?php foreach ($formStatuses as $slug => $label): ?>
                        <option value="<?php echo sanitize($slug); ?>"<?php echo (string)($data['status'] ?? '') === $slug ? ' selected' : ''; ?>><?php echo sanitize($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="scheduled_start">Scheduled start</label>
                    <input type="text" name="scheduled_start" id="scheduled_start" placeholder="dd/mmm/yyyy" value="<?php echo sanitize(itm_format_date_display((string)($data['scheduled_start'] ?? ''))); ?>">
                </div>
                <div class="form-group">
                    <label for="scheduled_end">Scheduled end</label>
                    <input type="text" name="scheduled_end" id="scheduled_end" placeholder="dd/mmm/yyyy" value="<?php echo sanitize(itm_format_date_display((string)($data['scheduled_end'] ?? ''))); ?>">
                </div>

                <h3 title="Affected configuration items from blast-radius graph">🧩</h3>
                <p class="text-muted">Select the source CI to load the impact graph. Checked items are stored as affected CIs for this change.</p>
                <div id="cmdb-impact-picker-viewport" class="org-chart-container">
                    <svg id="cmdb-impact-picker-svg"></svg>
                    <div id="cmdb-impact-picker-nodes"></div>
                </div>
                <div id="cmdb-ci-checklist" class="cmdb-ci-checklist"></div>

                <div class="form-actions" style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary" title="Save">💾</button>
                    <a href="<?php echo $isEdit ? 'view.php?id=' . (int)$id : 'index.php'; ?>" class="btn" title="Back">🔙</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/itm-cmdb-impact-graph.js"></script>
<script>
(function () {
    var sourceSelect = document.getElementById('source_configuration_item_id');
    var checklist = document.getElementById('cmdb-ci-checklist');
    var apiUrl = <?php echo json_encode($impactApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var ciViewBase = <?php echo json_encode($ciViewBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var preselected = <?php echo json_encode(array_values($selectedCiIds), JSON_UNESCAPED_UNICODE); ?>;

    function renderChecklist(nodes, rootId) {
        checklist.innerHTML = '';
        if (!nodes || !nodes.length) {
            checklist.textContent = 'No linked CIs in impact graph.';
            return;
        }
        nodes.forEach(function (n) {
            var id = parseInt(n.id, 10);
            if (!id) return;
            var wrap = document.createElement('label');
            wrap.style.display = 'block';
            wrap.style.marginBottom = '4px';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'configuration_item_ids[]';
            cb.value = String(id);
            if (preselected.indexOf(id) !== -1 || id === parseInt(rootId, 10)) {
                cb.checked = true;
            }
            wrap.appendChild(cb);
            wrap.appendChild(document.createTextNode(' ' + (n.ci_type_icon || '') + ' ' + (n.name || '') + ' (' + (n.ci_type_name || '') + ')'));
            checklist.appendChild(wrap);
        });
    }

    function loadImpact(ciId) {
        if (!ciId) {
            checklist.textContent = 'Select a source CI.';
            return;
        }
        fetch(apiUrl + '?action=impact&id=' + encodeURIComponent(ciId), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (!payload || !payload.ok) {
                    checklist.textContent = 'Could not load impact graph.';
                    return;
                }
                var graph = payload.graph || {};
                if (window.itmRenderCmdbImpactGraph) {
                    window.itmRenderCmdbImpactGraph({
                        graph: graph,
                        viewportEl: document.getElementById('cmdb-impact-picker-viewport'),
                        nodesEl: document.getElementById('cmdb-impact-picker-nodes'),
                        svgEl: document.getElementById('cmdb-impact-picker-svg'),
                        viewUrl: ciViewBase,
                        enablePan: true
                    });
                }
                renderChecklist(graph.nodes || [], graph.root_id || ciId);
            })
            .catch(function () {
                checklist.textContent = 'Impact graph request failed.';
            });
    }

    sourceSelect.addEventListener('change', function () {
        loadImpact(parseInt(sourceSelect.value, 10));
    });

    if (sourceSelect.value) {
        loadImpact(parseInt(sourceSelect.value, 10));
    }
})();
</script>
</body>
</html>
