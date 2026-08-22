<?php
/**
 * Change Requests — view with affected CI list and mini impact graph.
 */
require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_change_requests.php';
require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';

$id = (int)($_GET['id'] ?? 0);
$companyId = (int)$company_id;
$employeeId = (int)($_SESSION['employee_id'] ?? 0);

$stmt = mysqli_prepare(
    $conn,
    'SELECT cr.*, ci.name AS source_ci_name, cit.name AS source_ci_type_name, cit.icon AS source_ci_icon
     FROM change_requests cr
     INNER JOIN configuration_items ci ON ci.id = cr.source_configuration_item_id AND ci.company_id = cr.company_id
     INNER JOIN configuration_item_types cit ON cit.id = ci.ci_type_id AND cit.company_id = ci.company_id
     WHERE cr.id = ? AND cr.company_id = ? AND cr.deleted_at IS NULL
     LIMIT 1'
);
$row = null;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$row) {
    header('Location: index.php');
    exit;
}

$sourceCiId = (int)($row['source_configuration_item_id'] ?? 0);
$affectedRows = itm_change_request_list_affected_rows($conn, $companyId, $id);
$graph = itm_cmdb_build_impact_graph($conn, $companyId, $sourceCiId);
$crud_title = 'Change Request';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $companyId, $employeeId, 'change_requests', $crud_title);
$csrfToken = itm_get_csrf_token();
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
        .org-chart-container { width:100%; height:280px; border:1px solid var(--border-color,#ddd); overflow:auto; position:relative; background:#f8f9fa; }
        .org-node { width:160px; background:#fff; border:1px solid #00d1b2; border-radius:4px; text-align:center; position:absolute; padding:6px; box-shadow:0 2px 4px rgba(0,0,0,.05); z-index:10; font-size:12px; }
        #cmdb-mini-impact-nodes { position:absolute; top:0; left:0; }
        #cmdb-mini-impact-svg { position:absolute; top:0; left:0; width:3000px; height:1800px; pointer-events:none; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h1 title="View change request">🔎</h1>
                <div style="display:flex;gap:8px;">
                    <a href="edit.php?id=<?php echo (int)$id; ?>" class="btn btn-sm" title="Edit">✏️</a>
                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this record?');">
                        <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                        <?php if (function_exists('itm_crud_render_delete_hidden_audit_inputs')) {
                            itm_crud_render_delete_hidden_audit_inputs();
                        } ?>
                        <button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
                    </form>
                    <a href="index.php" class="btn btn-sm" title="Back">🔙</a>
                </div>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <h2><?php echo sanitize((string)($row['title'] ?? '')); ?></h2>
                <p><strong>Status:</strong> <span class="badge"><?php echo sanitize(itm_change_request_status_label((string)($row['status'] ?? ''))); ?></span></p>
                <p><strong>Source CI:</strong>
                    <a class="itm-plain-link" href="<?php echo sanitize($ciViewBase . $sourceCiId); ?>">
                        <?php echo sanitize((string)($row['source_ci_icon'] ?? '') . ' ' . (string)($row['source_ci_name'] ?? '')); ?>
                    </a>
                    <span class="text-muted">(<?php echo sanitize((string)($row['source_ci_type_name'] ?? '')); ?>)</span>
                </p>
                <?php if (!empty($row['description'])): ?>
                <p><strong>Description:</strong> <?php echo sanitize((string)$row['description']); ?></p>
                <?php endif; ?>
                <?php if (!empty($row['scheduled_start']) || !empty($row['scheduled_end'])): ?>
                <p><strong>Scheduled:</strong>
                    <?php echo sanitize(itm_format_date_display((string)($row['scheduled_start'] ?? ''))); ?>
                    <?php if (!empty($row['scheduled_end'])): ?>
                    → <?php echo sanitize(itm_format_date_display((string)$row['scheduled_end'])); ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <h3 title="Affected configuration items">🧩</h3>
                <?php if (!$affectedRows): ?>
                <p>No affected CIs recorded.</p>
                <?php else: ?>
                <ul>
                    <?php foreach ($affectedRows as $aRow): ?>
                    <li>
                        <a class="itm-plain-link" href="<?php echo sanitize($ciViewBase . (int)($aRow['id'] ?? 0)); ?>">
                            <?php echo sanitize((string)($aRow['ci_type_icon'] ?? '') . ' ' . (string)($aRow['name'] ?? '')); ?>
                        </a>
                        <span class="text-muted">(<?php echo sanitize((string)($aRow['ci_type_name'] ?? '')); ?>)</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3 title="Impact graph preview">📈</h3>
                <div id="cmdb-mini-impact-viewport" class="org-chart-container">
                    <svg id="cmdb-mini-impact-svg"></svg>
                    <div id="cmdb-mini-impact-nodes"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="../../js/itm-cmdb-impact-graph.js"></script>
<script>
(function () {
    var graph = <?php echo json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (window.itmRenderCmdbImpactGraph && graph && graph.nodes && graph.nodes.length) {
        window.itmRenderCmdbImpactGraph({
            graph: graph,
            viewportEl: document.getElementById('cmdb-mini-impact-viewport'),
            nodesEl: document.getElementById('cmdb-mini-impact-nodes'),
            svgEl: document.getElementById('cmdb-mini-impact-svg'),
            viewUrl: <?php echo json_encode($ciViewBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            mini: true,
            enablePan: false
        });
    }
})();
</script>
</body>
</html>
