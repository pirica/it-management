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
itm_crud_apply_module_icon_to_browser_title($conn, (int)$company_id, $employeeId, 'configuration_items', $crud_title);
$relationshipTypes = itm_cmdb_relationship_types();

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
<?php include '../../includes/header.php'; ?>
<div class="container">
    <div class="main-content">
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h1 title="View configuration item">🔎</h1>
                <div style="display:flex;gap:8px;">
                    <a href="edit.php?id=<?php echo (int)$id; ?>" class="btn btn-sm" title="Edit">✏️</a>
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
                <div id="cmdb-impact-viewport" style="position:relative;overflow:auto;border:1px solid var(--border-color,#ddd);height:480px;background:var(--bg-secondary,#f8f9fa);">
                    <svg id="cmdb-impact-svg" width="4000" height="2400" style="position:absolute;top:0;left:0;pointer-events:none;"></svg>
                    <div id="cmdb-impact-nodes" style="position:absolute;top:0;left:0;transform-origin:0 0;"></div>
                </div>
                <script>
                (function () {
                    var graph = <?php echo json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                    var nodes = graph.nodes || [];
                    var edges = graph.edges || [];
                    var rootId = graph.root_id || 0;
                    if (!nodes.length) return;

                    var nodeW = 200, nodeH = 56, gapX = 48, gapY = 90;
                    var childrenMap = {};
                    var parentCount = {};
                    nodes.forEach(function (n) { parentCount[n.id] = 0; });
                    edges.forEach(function (e) {
                        if (!childrenMap[e.parent_ci_id]) childrenMap[e.parent_ci_id] = [];
                        childrenMap[e.parent_ci_id].push(e.child_ci_id);
                        parentCount[e.child_ci_id] = (parentCount[e.child_ci_id] || 0) + 1;
                    });

                    var levels = {};
                    function assignLevel(id, depth) {
                        if (levels[id] !== undefined && levels[id] <= depth) return;
                        levels[id] = depth;
                        (childrenMap[id] || []).forEach(function (cid) { assignLevel(cid, depth + 1); });
                    }
                    assignLevel(rootId, 0);
                    nodes.forEach(function (n) {
                        if (levels[n.id] === undefined) levels[n.id] = n.depth || 0;
                    });

                    var byLevel = {};
                    nodes.forEach(function (n) {
                        var lv = levels[n.id] || 0;
                        if (!byLevel[lv]) byLevel[lv] = [];
                        byLevel[lv].push(n);
                    });

                    var positions = {};
                    Object.keys(byLevel).sort(function (a,b){return a-b;}).forEach(function (lv) {
                        var row = byLevel[lv];
                        row.forEach(function (n, idx) {
                            positions[n.id] = { x: idx * (nodeW + gapX) + 40, y: parseInt(lv, 10) * (nodeH + gapY) + 40 };
                        });
                    });

                    var host = document.getElementById('cmdb-impact-nodes');
                    var svg = document.getElementById('cmdb-impact-svg');
                    nodes.forEach(function (n) {
                        var pos = positions[n.id];
                        if (!pos) return;
                        var el = document.createElement('a');
                        el.href = 'view.php?id=' + n.id;
                        el.className = 'org-node card';
                        el.style.cssText = 'position:absolute;width:' + nodeW + 'px;padding:8px;text-align:center;left:' + pos.x + 'px;top:' + pos.y + 'px;';
                        if (parseInt(n.id, 10) === parseInt(rootId, 10)) {
                            el.style.border = '2px solid #0d6efd';
                        }
                        el.innerHTML = '<div>' + (n.ci_type_icon || '') + '</div><strong>' + (n.name || '') + '</strong><div style="font-size:12px;opacity:.8;">' + (n.ci_type_name || '') + '</div>';
                        host.appendChild(el);
                    });

                    edges.forEach(function (e) {
                        var p = positions[e.parent_ci_id];
                        var c = positions[e.child_ci_id];
                        if (!p || !c) return;
                        var x1 = p.x + nodeW / 2, y1 = p.y + nodeH;
                        var x2 = c.x + nodeW / 2, y2 = c.y;
                        var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                        var mid = (y1 + y2) / 2;
                        path.setAttribute('d', 'M' + x1 + ',' + y1 + ' C' + x1 + ',' + mid + ' ' + x2 + ',' + mid + ' ' + x2 + ',' + y2);
                        path.setAttribute('stroke', '#6c757d');
                        path.setAttribute('fill', 'none');
                        path.setAttribute('stroke-width', '2');
                        svg.appendChild(path);
                    });

                    var viewport = document.getElementById('cmdb-impact-viewport');
                    var dragging = false, sx = 0, sy = 0, sl = 0, st = 0;
                    viewport.addEventListener('mousedown', function (ev) {
                        dragging = true; sx = ev.clientX; sy = ev.clientY; sl = viewport.scrollLeft; st = viewport.scrollTop;
                    });
                    window.addEventListener('mouseup', function () { dragging = false; });
                    viewport.addEventListener('mousemove', function (ev) {
                        if (!dragging) return;
                        viewport.scrollLeft = sl - (ev.clientX - sx);
                        viewport.scrollTop = st - (ev.clientY - sy);
                    });
                })();
                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
