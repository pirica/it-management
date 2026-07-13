<?php
// Data Fetching
$data = ['id' => 0, 'name' => '', 'rack_units' => 42, 'layout_json' => '{"version":1,"units":42,"devices":[]}', 'notes' => '', 'active' => 1];
if (in_array($crud_action, ['edit', 'view'])) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM rack_planner WHERE id = ? AND company_id = ? AND deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            $data = $row;
        } else {
            $_SESSION['crud_error'] = 'Rack plan not found.';
            header('Location: index.php');
            exit;
        }
        mysqli_stmt_close($stmt);
    }
}

$componentCatalog = rack_planner_component_catalog();
$componentGroups = rack_planner_component_groups();
$normalizedLayout = rack_planner_normalize_layout_json((string)($data['layout_json'] ?? ''), (int)($data['rack_units'] ?? 42), $combinedCodeMeta);
$data['layout_json'] = rack_planner_encode_layout($normalizedLayout);
$rackAssignmentsByUnit = rack_planner_assignments_by_unit($normalizedLayout);
$layoutTotalAmount = rack_planner_layout_total($normalizedLayout);

$search = trim((string)($_GET['search'] ?? ''));
$sort = $_GET['sort'] ?? 'id';
$dir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

$ui_config = itm_get_ui_configuration($conn, $company_id);
$perPage = itm_resolve_records_per_page($ui_config);
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;


?>
