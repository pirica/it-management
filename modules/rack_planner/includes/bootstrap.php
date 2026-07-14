<?php
// Data Fetching
$defaultStatusId = 0;
$statusQuery = "SELECT id FROM rack_statuses WHERE company_id = ? AND name = 'Active' LIMIT 1";
$stmtStatus = mysqli_prepare($conn, $statusQuery);
if ($stmtStatus) {
    mysqli_stmt_bind_param($stmtStatus, 'i', $company_id);
    mysqli_stmt_execute($stmtStatus);
    $resStatus = mysqli_stmt_get_result($stmtStatus);
    if ($rowStatus = mysqli_fetch_assoc($resStatus)) {
        $defaultStatusId = (int)$rowStatus['id'];
    }
    mysqli_stmt_close($stmtStatus);
}
if ($defaultStatusId === 0) {
    $statusQuery = "SELECT id FROM rack_statuses WHERE company_id = ? ORDER BY id ASC LIMIT 1";
    $stmtStatus = mysqli_prepare($conn, $statusQuery);
    if ($stmtStatus) {
        mysqli_stmt_bind_param($stmtStatus, 'i', $company_id);
        mysqli_stmt_execute($stmtStatus);
        $resStatus = mysqli_stmt_get_result($stmtStatus);
        if ($rowStatus = mysqli_fetch_assoc($resStatus)) {
            $defaultStatusId = (int)$rowStatus['id'];
        }
        mysqli_stmt_close($stmtStatus);
    }
}

$data = ['id' => 0, 'name' => '', 'rack_units' => 42, 'layout_json' => '{"version":1,"units":42,"devices":[]}', 'notes' => '', 'active' => 1, 'status_id' => $defaultStatusId];
if (in_array($crud_action, ['edit', 'view'])) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        // Explicitly select active (even though it's INVISIBLE and always 1) to populate the data array correctly
        $stmt = mysqli_prepare($conn, "SELECT *, active FROM rack_planner WHERE id = ? AND company_id = ? AND deleted_at IS NULL");
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
