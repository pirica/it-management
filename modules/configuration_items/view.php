<?php
require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_cmdb.php';
require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tab = strtolower(trim((string)($_GET['tab'] ?? 'details')));
if (!in_array($tab, ['details', 'relationships', 'impact'], true)) {
    $tab = 'details';
}

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmdb_delete_relationship'])) {
    itm_require_post_csrf();
    $relId = (int)($_POST['relationship_id'] ?? 0);
    if ($relId > 0 && itm_cmdb_delete_relationship($conn, (int)$company_id, $relId, $employeeId)) {
        header('Location: view.php?id=' . $id . '&tab=relationships&saved=1');
        exit;
    }
    $flash = 'Could not remove relationship.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cmdb_add_relationship'])) {
    itm_require_post_csrf();
    $relatedCiId = (int)($_POST['related_ci_id'] ?? 0);
    $direction = strtolower(trim((string)($_POST['direction'] ?? 'upstream')));
    $relationshipType = strtolower(trim((string)($_POST['relationship_type'] ?? 'depends_on')));
    $types = itm_cmdb_relationship_types();
    if (!isset($types[$relationshipType])) {
        $relationshipType = 'depends_on';
    }
    if ($id > 0 && $relatedCiId > 0) {
        if ($direction === 'downstream') {
            $parentCiId = $id;
            $childCiId = $relatedCiId;
        } else {
            $parentCiId = $relatedCiId;
            $childCiId = $id;
        }
        $result = itm_cmdb_add_relationship($conn, (int)$company_id, $parentCiId, $childCiId, $relationshipType, $employeeId);
        if (!empty($result['ok'])) {
            header('Location: view.php?id=' . $id . '&tab=relationships&saved=1');
            exit;
        }
        $flash = (string)($result['error'] ?? 'Could not add relationship.');
    }
}

$stmt = mysqli_prepare(
    $conn,
    'SELECT ci.*, cit.name AS ci_type_name, cit.icon AS ci_type_icon
     FROM configuration_items ci
     INNER JOIN configuration_item_types cit ON cit.id = ci.ci_type_id AND cit.company_id = ci.company_id
     WHERE ci.id = ? AND ci.company_id = ? AND ci.deleted_at IS NULL
     LIMIT 1'
);
if (!$stmt) {
    die('Database error.');
}
mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$row) {
    header('Location: index.php');
    exit;
}

$rels = itm_cmdb_list_relationships_for_ci($conn, (int)$company_id, $id);
$ciOptions = itm_cmdb_list_ci_options($conn, (int)$company_id, $id);
$graph = itm_cmdb_build_impact_graph($conn, (int)$company_id, $id);
$csrfToken = itm_get_csrf_token();
$crud_title = 'Configuration Item';
$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)$company_id, $employeeId, 'configuration_items', $crud_title);
$relationshipTypes = itm_cmdb_relationship_types();
$companyId = (int)$company_id;

function ci_view_render_cell_value($table, $field, $value) {
    if ($field === 'active') {
        $isActive = ((int)$value === 1);
        return '<span class="badge ' . ($isActive ? 'badge-success' : 'badge-danger') . '">' . ($isActive ? 'Active' : 'Inactive') . '</span>';
    }
    if (function_exists('itm_crud_render_audit_cell_value')) {
        $auditHtml = itm_crud_render_audit_cell_value($GLOBALS['conn'] ?? null, (int)($GLOBALS['company_id'] ?? 0), $field, $value);
        if ($auditHtml !== null) {
            return $auditHtml;
        }
    }
    return sanitize((string)($value ?? ''));
}
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
        .org-chart-container { width:100%; overflow:auto; position:relative; background:var(--bg-secondary,#f8f9fa); }
        .org-node { width:160px; background:#fff; border:1px solid #00d1b2; border-radius:4px; text-align:center; position:absolute; padding:6px; box-shadow:0 2px 4px rgba(0,0,0,.05); z-index:10; font-size:12px; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h1 title="View configuration item">🔎</h1>
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

            <?php if ($flash !== ''): ?>
            <div class="alert alert-danger"><?php echo sanitize($flash); ?></div>
            <?php endif; ?>

            <div class="card" style="margin-bottom:16px;">
                <h2><?php echo sanitize((string)($row['ci_type_icon'] ?? '') . ' ' . (string)($row['name'] ?? '')); ?></h2>
                <p><strong>Type:</strong> <?php echo sanitize((string)($row['ci_type_name'] ?? '')); ?></p>
                <?php if (!empty($row['external_ref'])): ?>
                <p><strong>External ref:</strong> <?php echo sanitize((string)$row['external_ref']); ?></p>
                <?php endif; ?>
                <?php if (!empty($row['record_module_slug']) && !empty($row['record_id'])): ?>
                <p><strong>Source:</strong>
                    <?php
                    $srcSlug = (string)$row['record_module_slug'];
                    $srcId = (int)$row['record_id'];
                    $srcUrl = BASE_URL . 'modules/' . $srcSlug . '/view.php?id=' . $srcId;
                    ?>
                    <a class="itm-plain-link" href="<?php echo sanitize($srcUrl); ?>"><?php echo sanitize($srcSlug . ' #' . $srcId); ?></a>
                </p>
                <?php endif; ?>
                <p><?php echo ci_view_render_cell_value('configuration_items', 'active', $row['active'] ?? 0); ?></p>
            </div>

            <div class="card" style="margin-bottom:16px;">
                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    <a class="btn btn-sm<?php echo $tab === 'details' ? ' btn-primary' : ''; ?>" href="?id=<?php echo (int)$id; ?>&tab=details">Details</a>
                    <a class="btn btn-sm<?php echo $tab === 'relationships' ? ' btn-primary' : ''; ?>" href="?id=<?php echo (int)$id; ?>&tab=relationships">Relationships</a>
                    <a class="btn btn-sm<?php echo $tab === 'impact' ? ' btn-primary' : ''; ?>" href="?id=<?php echo (int)$id; ?>&tab=impact">Impact</a>
                </div>

                <?php if ($tab === 'details'): ?>
                <table class="table">
                    <?php
                    $detailFields = ['name', 'external_ref', 'record_module_slug', 'record_id', 'active', 'created_at', 'updated_at'];
                    foreach ($detailFields as $field):
                        if (!array_key_exists($field, $row)) {
                            continue;
                        }
                    ?>
                    <tr>
                        <th style="width:220px;"><?php echo sanitize(ucwords(str_replace('_', ' ', $field))); ?></th>
                        <td><?php echo ci_view_render_cell_value('configuration_items', $field, $row[$field]); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php if (count($graph['nodes'] ?? []) > 1): ?>
                <h4 title="Mini impact preview">📈</h4>
                <div id="cmdb-mini-impact-viewport" class="org-chart-container" style="height:240px;margin-top:12px;border:1px solid var(--border-color,#ddd);overflow:auto;position:relative;background:var(--bg-secondary,#f8f9fa);">
                    <svg id="cmdb-mini-impact-svg" style="position:absolute;top:0;left:0;width:3000px;height:1800px;pointer-events:none;"></svg>
                    <div id="cmdb-mini-impact-nodes" style="position:absolute;top:0;left:0;"></div>
                </div>
                <?php endif; ?>
                <?php elseif ($tab === 'relationships'): ?>
                <form method="POST" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:flex-end;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <input type="hidden" name="cmdb_add_relationship" value="1">
                    <div>
                        <label>Related CI</label>
                        <select name="related_ci_id" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($ciOptions as $opt): ?>
                            <option value="<?php echo (int)($opt['id'] ?? 0); ?>"><?php echo sanitize((string)($opt['name'] ?? '') . ' (' . (string)($opt['ci_type_name'] ?? '') . ')'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Direction</label>
                        <select name="direction">
                            <option value="upstream">This CI depends on selected</option>
                            <option value="downstream">Selected depends on this CI</option>
                        </select>
                    </div>
                    <div>
                        <label>Type</label>
                        <select name="relationship_type">
                            <?php foreach ($relationshipTypes as $slug => $label): ?>
                            <option value="<?php echo sanitize($slug); ?>"><?php echo sanitize($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" title="Add relationship">➕</button>
                </form>
                <div style="display:flex;gap:24px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:260px;">
                        <h3>Upstream</h3>
                        <table class="table">
                            <thead><tr><th>CI</th><th>Type</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($rels['upstream'] as $rel): ?>
                            <tr>
                                <td><a class="itm-plain-link" href="view.php?id=<?php echo (int)($rel['related_ci_id'] ?? 0); ?>"><?php echo sanitize((string)($rel['related_name'] ?? '')); ?></a></td>
                                <td><?php echo sanitize(itm_cmdb_relationship_type_label((string)($rel['relationship_type'] ?? ''))); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this relationship?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="cmdb_delete_relationship" value="1">
                                        <input type="hidden" name="relationship_id" value="<?php echo (int)($rel['id'] ?? 0); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="flex:1;min-width:260px;">
                        <h3>Downstream</h3>
                        <table class="table">
                            <thead><tr><th>CI</th><th>Type</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($rels['downstream'] as $rel): ?>
                            <tr>
                                <td><a class="itm-plain-link" href="view.php?id=<?php echo (int)($rel['related_ci_id'] ?? 0); ?>"><?php echo sanitize((string)($rel['related_name'] ?? '')); ?></a></td>
                                <td><?php echo sanitize(itm_cmdb_relationship_type_label((string)($rel['relationship_type'] ?? ''))); ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this relationship?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                                        <input type="hidden" name="cmdb_delete_relationship" value="1">
                                        <input type="hidden" name="relationship_id" value="<?php echo (int)($rel['id'] ?? 0); ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted">Read-only blast-radius graph (upstream + downstream). Pan with mouse drag.</p>
                <div id="cmdb-impact-viewport" class="org-chart-container" style="position:relative;overflow:auto;border:1px solid var(--border-color,#ddd);height:480px;background:var(--bg-secondary,#f8f9fa);cursor:grab;">
                    <svg id="cmdb-impact-svg" width="4000" height="2400" style="position:absolute;top:0;left:0;pointer-events:none;"></svg>
                    <div id="cmdb-impact-nodes" style="position:absolute;top:0;left:0;transform-origin:0 0;"></div>
                </div>
                <script src="../../js/itm-cmdb-impact-graph.js"></script>
                <script>
                (function () {
                    var graph = <?php echo json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                    if (window.itmRenderCmdbImpactGraph) {
                        window.itmRenderCmdbImpactGraph({
                            graph: graph,
                            viewportEl: document.getElementById('cmdb-impact-viewport'),
                            nodesEl: document.getElementById('cmdb-impact-nodes'),
                            svgEl: document.getElementById('cmdb-impact-svg'),
                            viewUrl: 'view.php?id=',
                            enablePan: true
                        });
                    }
                })();
                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php if ($tab === 'details' && count($graph['nodes'] ?? []) > 1): ?>
<script src="../../js/itm-cmdb-impact-graph.js"></script>
<script>
(function () {
    var graph = <?php echo json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (window.itmRenderCmdbImpactGraph) {
        window.itmRenderCmdbImpactGraph({
            graph: graph,
            viewportEl: document.getElementById('cmdb-mini-impact-viewport'),
            nodesEl: document.getElementById('cmdb-mini-impact-nodes'),
            svgEl: document.getElementById('cmdb-mini-impact-svg'),
            viewUrl: 'view.php?id=',
            mini: true,
            enablePan: false
        });
    }
})();
</script>
<?php endif; ?>
<script src="../../js/theme.js"></script>
</body>
</html>
