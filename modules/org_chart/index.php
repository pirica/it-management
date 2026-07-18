<?php
/**
 * Org Chart Module - Index
 *
 * Provides a visual, interactive organizational structure diagram.
 * Features:
 * - Real-time tree visualization of company hierarchy
 * - Drag and drop to reassign managers
 * - Export to Excel (Structure)
 * - Save as Image (PNG)
 * - Integration with Employees and Positions
 */

require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_api_json_response.php';

$crud_title = 'Org Chart';
$csrfToken = itm_get_csrf_token();

// Handle AJAX update for drag and drop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_hierarchy') {
    header('Content-Type: application/json');
    itm_require_post_csrf();

    $employeeId = (int)$_POST['employee_id'];
    $reportsTo = (int)$_POST['reports_to'];

    if ($employeeId <= 0) {
        itm_api_json_response(['ok' => false, 'error' => 'Invalid Employee ID'], 400);
    }

    // Prevent self-reporting
    if ($employeeId === $reportsTo) {
        itm_api_json_response(['ok' => false, 'error' => 'An employee cannot report to themselves.'], 400);
    }

    // P1: Validate manager belongs to the same company and is on org chart to prevent cross-tenant hierarchy corruption
    if ($reportsTo > 0) {
        $stmt = mysqli_prepare($conn, "SELECT e.id FROM employees e
                                       INNER JOIN employee_statuses es ON es.id = e.employment_status_id
                                       WHERE e.id = ? AND e.company_id = ? AND e.on_orgchart = 1 AND es.name = 'Active'");
        mysqli_stmt_bind_param($stmt, "ii", $reportsTo, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($res) === 0) {
            mysqli_stmt_close($stmt);
            itm_api_json_response(['ok' => false, 'error' => 'Invalid manager selection (Manager must be on Org Chart and Active).'], 400);
        }
        mysqli_stmt_close($stmt);
    }

    // Fetch employee map for in-memory cycle detection to avoid N+1 queries
    $stmt_map = mysqli_prepare($conn, "SELECT id, reports_to FROM employees WHERE company_id = ?");
    mysqli_stmt_bind_param($stmt_map, "i", $company_id);
    mysqli_stmt_execute($stmt_map);
    $res_map = mysqli_stmt_get_result($stmt_map);
    $employeeMap = [];
    while ($row_map = mysqli_fetch_assoc($res_map)) {
        $employeeMap[(int)$row_map['id']] = (int)$row_map['reports_to'];
    }
    mysqli_stmt_close($stmt_map);

    // Verify employee belongs to current tenant
    if (!isset($employeeMap[$employeeId])) {
        itm_api_json_response(['ok' => false, 'error' => 'Employee not found in this company.'], 404);
    }

    if ($reportsTo > 0 && itm_is_circular_reporting($employeeMap, $reportsTo, $employeeId)) {
        itm_api_json_response(['ok' => false, 'error' => 'Circular reporting detected. Hierarchy update blocked.'], 400);
    }

    // High risk: Use prepared statement for update
    $reportsToVal = ($reportsTo > 0) ? $reportsTo : null;
    $stmt = mysqli_prepare($conn, "UPDATE employees SET reports_to = ? WHERE id = ? AND company_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $reportsToVal, $employeeId, $company_id);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        itm_api_json_response(['ok' => false, 'error' => 'Database update failed.'], 500);
    }

    $affectedRows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    itm_api_mutation_requires_rows($affectedRows, ['ok' => true]);
}

/**
 * Recursive cycle detection: Ensure employeeId is not in the reporting chain of reportsTo.
 * Why: Prevents infinite loops in the organizational tree when dragging/dropping.
 * Optimized: Uses in-memory map to avoid N+1 database queries.
 */
function itm_is_circular_reporting($employeeMap, $startReportsTo, $targetEmployeeId) {
    $current = $startReportsTo;
    $visited = [];
    while ($current > 0) {
        if ($current === $targetEmployeeId) return true;
        if (isset($visited[$current])) return true; // Safety against existing DB cycles
        $visited[$current] = true;

        $current = isset($employeeMap[$current]) ? $employeeMap[$current] : 0;
    }
    return false;
}

// Fetch all employees with positions and departments
$sql = "SELECT e.id, e.display_name, e.first_name, e.last_name, e.reports_to,
               ep.name AS position_name, d.name AS department_name, d.id AS department_id
        FROM employees e
        LEFT JOIN employee_positions ep ON ep.id = e.employee_position_id
        LEFT JOIN departments d ON d.id = e.department_id
        INNER JOIN employee_statuses es ON es.id = e.employment_status_id
        WHERE e.company_id = ? AND e.on_orgchart = 1 AND es.name = 'Active'
        ORDER BY d.name, e.display_name";

$stmt_list = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt_list, "i", $company_id);
mysqli_stmt_execute($stmt_list);
$res = mysqli_stmt_get_result($stmt_list);
$employees = [];
while ($row = mysqli_fetch_assoc($res)) {
    $employees[] = $row;
}
mysqli_stmt_close($stmt_list);

// Prepare JSON for JS visualization
// P2: Escape JSON for safe injection into script block
$employeesJson = json_encode($employees, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

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
if (!isset($crud_title)) {
    $crud_title = 'Organizational Chart';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .org-chart-container {
            width: 100%;
            height: 700px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            overflow: auto;
            position: relative;
            padding: 50px;
            cursor: grab;
        }
        .org-chart-container:active {
            cursor: grabbing;
        }
        .org-node {
            width: 240px;
            background: #fff;
            border: 1px solid #00d1b2;
            border-radius: 4px;
            padding: 0;
            text-align: center;
            position: absolute;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            z-index: 10;
            user-select: none;
            overflow: hidden;
        }
        .org-node:hover {
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            border-color: #00b89c;
        }
        .org-node .name {
            background: #f0fff4;
            border-bottom: 1px solid #00d1b2;
            font-weight: bold;
            font-size: 14px;
            color: #2d3748;
            padding: 8px 10px;
        }
        .org-node .details {
            padding: 8px 10px;
        }
        .org-node .position {
            font-size: 12px;
            color: #4a5568;
            margin-bottom: 2px;
        }
        .org-node .department {
            font-size: 11px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .org-connector {
            position: absolute;
            background: #333;
            z-index: 1;
        }
        .toolbar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .drop-indicator {
            border: 2px dashed #ff3860;
            background: rgba(255, 56, 96, 0.1);
            pointer-events: none;
            z-index: 5;
        }
        #chart-svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 5000px;
            height: 5000px;
            pointer-events: none;
        }
        @media (max-width: 768px) {
            .org-chart-container { height: min(700px, 60vh); padding: 16px; }
            .org-node { width: min(240px, 80vw); }
            .toolbar { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>📈 Organizational Chart</h1>
                <div class="toolbar">
                    <button id="btn-export-excel" class="btn btn-sm btn-success">📗 Export Excel</button>
                    <button id="btn-save-image" class="btn btn-sm btn-primary">📄 Save as Image</button>
                    <button id="btn-zoom-in" class="btn btn-sm">+</button>
                    <button id="btn-zoom-out" class="btn btn-sm">-</button>
                    <button id="btn-reset" class="btn btn-sm">Reset View</button>
                </div>
            </div>

            <div id="org-chart-wrapper" class="org-chart-container">
                <svg id="chart-svg">
                    <defs>
                        <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="0" refY="3.5" orient="auto">
                            <polygon points="0 0, 10 3.5, 0 7" fill="#333" />
                        </marker>
                    </defs>
                </svg>
                <div id="chart-nodes"></div>
            </div>

            <div style="margin-top:20px;" class="card">
                <h3>💡 Tips</h3>
                <ul>
                    <li><strong>Drag and Drop</strong> an employee onto another to change who they report to.</li>
                    <li>Drag the <strong>background</strong> to pan the chart.</li>
                    <li>Reporting changes are saved automatically.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="../../js/vendor/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    const employees = <?php echo $employeesJson; ?>;
    const csrfToken = <?php echo json_encode($csrfToken); ?>;
    const wrapper = document.getElementById('org-chart-wrapper');
    const nodesContainer = document.getElementById('chart-nodes');
    const svg = document.getElementById('chart-svg');

    let zoom = 1;
    let offset = { x: 0, y: 0 };
    let isPanning = false;
    let startPan = { x: 0, y: 0 };

    function buildTree(data) {
        const map = {};
        const roots = [];
        data.forEach(item => {
            map[item.id] = { ...item, children: [] };
        });
        data.forEach(item => {
            if (item.reports_to && map[item.reports_to]) {
                map[item.reports_to].children.push(map[item.id]);
            } else {
                roots.push(map[item.id]);
            }
        });
        return roots;
    }

    function layoutTree(roots) {
        const nodeWidth = 240;
        const nodeHeight = 120;
        const horizontalGap = 40;
        const verticalGap = 100;

        const levels = [];

        function traverse(node, depth, x) {
            if (!levels[depth]) levels[depth] = [];

            node.depth = depth;
            node.x = x;
            node.y = depth * verticalGap;

            levels[depth].push(node);

            let currentX = x;
            node.children.forEach((child, i) => {
                traverse(child, depth + 1, currentX);
                currentX += nodeWidth + horizontalGap;
            });

            // Adjust X to be centered over children
            if (node.children.length > 0) {
                const firstChild = node.children[0];
                const lastChild = node.children[node.children.length - 1];
                // node.x = (firstChild.x + lastChild.x) / 2;
            }
        }

        // Simple grid layout for now, will improve
        let startX = 50;
        roots.forEach(root => {
            const width = calculateWidth(root) * (nodeWidth + horizontalGap);
            traverse(root, 0, startX + width/2 - nodeWidth/2);
            startX += width;
        });

        function calculateWidth(node) {
            if (node.children.length === 0) return 1;
            return node.children.reduce((acc, child) => acc + calculateWidth(child), 0);
        }

        // Refine positions
        function refine(node) {
            if (node.children.length > 0) {
                node.children.forEach(refine);
                const firstChild = node.children[0];
                const lastChild = node.children[node.children.length - 1];
                node.x = (firstChild.x + lastChild.x) / 2;
            }
        }
        roots.forEach(refine);
    }

    function renderChart() {
        nodesContainer.innerHTML = '';
        svg.innerHTML = `
            <defs>
                <marker id="arrowhead" markerWidth="10" markerHeight="7" refX="10" refY="3.5" orient="auto">
                    <polygon points="0 0, 10 3.5, 0 7" fill="#333" />
                </marker>
            </defs>
        `;

        const tree = buildTree(employees);
        layoutTree(tree);

        const flatNodes = [];
        function flatten(node) {
            flatNodes.push(node);
            node.children.forEach(flatten);
        }
        tree.forEach(flatten);

        flatNodes.forEach(node => {
            const div = document.createElement('div');
            div.className = 'org-node';
            div.dataset.id = node.id;
            div.style.left = node.x + 'px';
            div.style.top = node.y + 'px';
            div.draggable = true;

            // P2: Prevent XSS by using textContent for labels
            const nameDiv = document.createElement('div');
            nameDiv.className = 'name';
            nameDiv.textContent = node.display_name || (node.first_name + ' ' + node.last_name);
            div.appendChild(nameDiv);

            const detailsDiv = document.createElement('div');
            detailsDiv.className = 'details';

            const positionDiv = document.createElement('div');
            positionDiv.className = 'position';
            positionDiv.textContent = node.position_name || 'No Position';
            detailsDiv.appendChild(positionDiv);

            const departmentDiv = document.createElement('div');
            departmentDiv.className = 'department';
            departmentDiv.textContent = node.department_name || 'No Department';
            detailsDiv.appendChild(departmentDiv);

            div.appendChild(detailsDiv);

            div.addEventListener('dragstart', handleDragStart);
            div.addEventListener('dragover', handleDragOver);
            div.addEventListener('drop', handleDrop);

            nodesContainer.appendChild(div);

            if (node.reports_to) {
                const parent = flatNodes.find(n => n.id == node.reports_to);
                if (parent) {
                    drawConnector(parent, node);
                }
            }
        });
    }

    function drawConnector(parent, child) {
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        const nodeWidth = 240;
        const nodeHeight = 85; // Roughly the height of the redesigned node
        const startX = parent.x + nodeWidth / 2;
        const startY = parent.y + nodeHeight;
        const endX = child.x + nodeWidth / 2;
        const endY = child.y;

        const midY = (startY + endY) / 2;

        // S-curve
        const d = `M ${startX} ${startY} C ${startX} ${midY}, ${endX} ${midY}, ${endX} ${endY}`;

        line.setAttribute('d', d);
        line.setAttribute('stroke', '#333');
        line.setAttribute('stroke-width', '2');
        line.setAttribute('fill', 'none');
        line.setAttribute('marker-end', 'url(#arrowhead)');
        svg.appendChild(line);
    }

    let draggedId = null;

    function handleDragStart(e) {
        draggedId = e.target.dataset.id;
        e.dataTransfer.setData('text/plain', draggedId);
    }

    function handleDragOver(e) {
        e.preventDefault();
    }

    function handleDrop(e) {
        e.preventDefault();
        const targetId = e.target.closest('.org-node').dataset.id;
        if (draggedId && targetId && draggedId !== targetId) {
            updateHierarchy(draggedId, targetId);
        }
    }

    function updateHierarchy(empId, reportsToId) {
        const formData = new FormData();
        formData.append('action', 'update_hierarchy');
        formData.append('employee_id', empId);
        formData.append('reports_to', reportsToId);
        formData.append('csrf_token', csrfToken);

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                location.reload();
            } else {
                alert(data.error || 'Update failed');
            }
        });
    }

    // Panning logic
    wrapper.addEventListener('mousedown', (e) => {
        if (e.target === wrapper || e.target === svg || e.target === nodesContainer) {
            isPanning = true;
            startPan = { x: e.clientX - offset.x, y: e.clientY - offset.y };
        }
    });

    window.addEventListener('mousemove', (e) => {
        if (!isPanning) return;
        offset.x = e.clientX - startPan.x;
        offset.y = e.clientY - startPan.y;
        applyTransform();
    });

    window.addEventListener('mouseup', () => {
        isPanning = false;
    });

    function applyTransform() {
        nodesContainer.style.transform = `translate(${offset.x}px, ${offset.y}px) scale(${zoom})`;
        svg.style.transform = `translate(${offset.x}px, ${offset.y}px) scale(${zoom})`;
    }

    document.getElementById('btn-zoom-in').onclick = () => { zoom += 0.1; applyTransform(); };
    document.getElementById('btn-zoom-out').onclick = () => { zoom = Math.max(0.1, zoom - 0.1); applyTransform(); };
    document.getElementById('btn-reset').onclick = () => { zoom = 1; offset = {x:0, y:0}; applyTransform(); };

    document.getElementById('btn-export-excel').onclick = () => {
        const wsData = employees.map(e => ({
            'ID': e.id,
            'Name': e.display_name,
            'Position': e.position_name,
            'Department': e.department_name,
            'Reports To ID': e.reports_to
        }));
        const ws = XLSX.utils.json_to_sheet(wsData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Org Chart");
        XLSX.writeFile(wb, "Organizational_Chart.xlsx");
    };

    document.getElementById('btn-save-image').onclick = () => {
        html2canvas(wrapper).then(canvas => {
            const link = document.createElement('a');
            link.download = 'Organizational_Chart.png';
            link.href = canvas.toDataURL();
            link.click();
        });
    };

    renderChart();
</script>
</body>
</html>
