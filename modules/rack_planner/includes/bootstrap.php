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

$data = [
    'id' => 0,
    'name' => '',
    'rack_units' => 42,
    'layout_json' => '{"version":1,"units":42,"devices":[]}',
    'notes' => '',
    'active' => 1,
    'status_id' => $defaultStatusId,
    'employee_id' => 0,
    'deleted_by' => null,
    'deleted_at' => null,
    'created_by' => null,
    'created_at' => null,
    'updated_by' => null,
    'updated_at' => null,
    'status_name' => '',
    'created_by_name' => '',
    'updated_by_name' => '',
    'deleted_by_name' => '',
    'formatted_created_at' => '',
    'formatted_updated_at' => '',
    'formatted_deleted_at' => '',
];

if (in_array($crud_action, ['edit', 'view'])) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        // Explicitly select INVISIBLE tracking columns so they can be read and resolved
        $stmt = mysqli_prepare($conn, "SELECT *, active, employee_id, deleted_by, deleted_at, created_by, created_at, updated_by, updated_at FROM rack_planner WHERE id = ? AND company_id = ? AND deleted_at IS NULL");
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

        // Helper function to resolve employee names
        $resolveEmployeeName = function ($conn, $empId, $company_id) {
            $empId = (int)$empId;
            if ($empId <= 0) {
                return '';
            }
            $sql = "SELECT first_name, last_name, display_name FROM employees WHERE id = ? AND company_id = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $empId, $company_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                if ($row = mysqli_fetch_assoc($res)) {
                    $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                    if ($fullName !== '') {
                        mysqli_stmt_close($stmt);
                        return $fullName;
                    }
                    if (!empty($row['display_name'])) {
                        mysqli_stmt_close($stmt);
                        return trim($row['display_name']);
                    }
                }
                mysqli_stmt_close($stmt);
            }
            return '';
        };

        // Helper function to format timestamp
        $formatTimestamp = function ($timestampStr) {
            if (!$timestampStr) {
                return '';
            }
            $time = strtotime($timestampStr);
            if ($time === false) {
                return '';
            }
            return date('d-m-Y - H:i:s', $time);
        };

        // Resolve Status Name
        $data['status_name'] = '';
        if (!empty($data['status_id'])) {
            $stmt = mysqli_prepare($conn, "SELECT name FROM rack_statuses WHERE id = ? AND company_id = ? LIMIT 1");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $data['status_id'], $company_id);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                if ($row = mysqli_fetch_assoc($res)) {
                    $data['status_name'] = $row['name'];
                }
                mysqli_stmt_close($stmt);
            }
        }

        // Resolve Employee Names
        $data['created_by_name'] = $resolveEmployeeName($conn, $data['created_by'] ?? 0, $company_id);
        $data['updated_by_name'] = $resolveEmployeeName($conn, $data['updated_by'] ?? 0, $company_id);
        $data['deleted_by_name'] = $resolveEmployeeName($conn, $data['deleted_by'] ?? 0, $company_id);

        // Format Timestamps
        $data['formatted_created_at'] = $formatTimestamp($data['created_at'] ?? null);
        $data['formatted_updated_at'] = $formatTimestamp($data['updated_at'] ?? null);
        $data['formatted_deleted_at'] = $formatTimestamp($data['deleted_at'] ?? null);
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
