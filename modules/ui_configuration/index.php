<?php
require_once '../../config/config.php';

$itm_ui_company_id = (int)($company_id ?? 0);
$itm_ui_error = '';

/**
 * Why this helper exists: sample-data handling and empty-state rendering both need
 * a scoped existence check, and duplicating that query risks drift in tenant safety.
 */
function itm_ui_configuration_company_row_count($conn, $companyId) {
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS total FROM ui_configuration WHERE company_id = ?');
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    itm_require_post_csrf();

    if ($itm_ui_company_id <= 0) {
        $itm_ui_error = 'An active company is required before adding sample data.';
    } else {
        $existing = itm_ui_configuration_company_row_count($conn, $itm_ui_company_id);
        if ($existing === null) {
            $itm_ui_error = 'Unable to verify existing UI configuration records.';
        } elseif ($existing > 0) {
            $itm_ui_error = 'Sample data was not added because this company already has UI configuration.';
        } else {
            $sampleRows = [
                1 => ['left_right', 'left_right', 'left_right', 'left_right', 1, 1, '25', '⚙️ IT Controls', '', null, null, null, null],
                2 => ['left_right', 'left_right', 'left_right', 'left_right', 1, 1, '25', '⚙️ IT Controls', '', null, null, null, null],
                3 => ['left_right', 'left_right', 'left_right', 'left_right', 1, 1, '25', '⚙️ IT Controls', '', null, null, null, null],
                4 => [
                    'left',
                    'left',
                    'left',
                    'left',
                    1,
                    1,
                    '25',
                    '⚙️ IT Controls',
                    '',
                    '{"is_access_point":1,"is_cctv":1,"is_firewall":1,"is_other":1,"is_phone":1,"is_port_patch_panel":1,"is_pos":1,"is_printer":1,"is_router":1,"is_server":1,"is_switch":1,"is_workstation":1}',
                    '{"dashboard":1,"dashboard_link":1,"settings":1,"management":1,"equipment":1,"is_workstation":1,"is_server":1,"is_switch":1,"is_printer":1,"is_pos":1,"switch_ports":1,"tickets":1,"is_other":1,"is_port_patch_panel":1,"is_cctv":1,"is_phone":1,"is_access_point":1,"is_firewall":1,"is_router":1,"employee":1,"employees":1,"employee_system_access":1,"system_access":1,"departments":1,"admin":1,"inventory":1,"users":1,"companies":1,"reference_data":1,"it_locations":1,"location_types":1,"equipment_types":1,"equipment_statuses":1,"manufacturers":1,"catalogs":1,"suppliers":1,"supplier_statuses":1,"racks":1,"idfs":1,"rack_statuses":1,"switch_status":1,"cable_colors":1,"ticket_categories":1,"ticket_statuses":1,"ticket_priorities":1,"employee_statuses":1,"audit_logs":1,"access_levels":0,"assignment_types":0,"attempts":1,"employee_onboarding_requests":0,"employee_system_access_relations":0,"equipment_environment":0,"equipment_fiber":0,"equipment_fiber_count":0,"equipment_fiber_patch":0,"equipment_fiber_rack":0,"equipment_poe":0,"equipment_rj45":0,"idf_device_type":1,"idf_links":1,"idf_ports":1,"idf_positions":1,"inventory_categories":1,"inventory_items":1,"patches_updates":1,"patches_updates_level":0,"patches_updates_status":0,"printer_device_types":0,"role_assignment_rights":0,"role_hierarchy":0,"role_module_permissions":0,"sidebar_layout":0,"switch_port_numbering_layout":0,"switch_port_types":0,"ui_configuration":1,"user_companies":0,"user_roles":0,"vlans":1,"warranty_types":0,"workstation_device_types":0,"workstation_modes":0,"workstation_office":0,"workstation_os_types":0,"workstation_os_versions":0,"workstation_ram":0}',
                    '["dashboard","management","employee","admin","reference_data"]',
                    '{"dashboard":["dashboard_link","settings"],"management":["equipment","is_workstation","is_server","is_switch","is_printer","is_pos","switch_ports","tickets","is_other","is_port_patch_panel","is_cctv","is_phone","is_access_point","is_firewall","is_router"],"employee":["employees","employee_system_access","system_access","departments"],"admin":["inventory","users","companies"],"reference_data":["it_locations","location_types","equipment_types","equipment_statuses","manufacturers","catalogs","suppliers","supplier_statuses","racks","idfs","rack_statuses","switch_status","cable_colors","ticket_categories","ticket_statuses","ticket_priorities","employee_statuses","audit_logs","access_levels","assignment_types","attempts","employee_onboarding_requests","employee_system_access_relations","equipment_environment","equipment_fiber","equipment_fiber_count","equipment_fiber_patch","equipment_fiber_rack","equipment_poe","equipment_rj45","idf_device_type","idf_links","idf_ports","idf_positions","inventory_categories","inventory_items","patches_updates","patches_updates_level","patches_updates_status","printer_device_types","role_assignment_rights","role_hierarchy","role_module_permissions","sidebar_layout","switch_port_numbering_layout","switch_port_types","ui_configuration","user_companies","user_roles","vlans","warranty_types","workstation_device_types","workstation_modes","workstation_office","workstation_os_types","workstation_os_versions","workstation_ram"]}'
                ],
                5 => ['left_right', 'left_right', 'left_right', 'left_right', 1, 1, '25', '⚙️ IT Controls', '', null, null, null, null],
            ];

            $seed = $sampleRows[$itm_ui_company_id] ?? $sampleRows[1];
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO ui_configuration (company_id, table_actions_position, new_button_position, export_buttons_position, back_save_position, enable_all_error_reporting, enable_audit_logs, records_per_page, app_name, favicon_path, equipment_type_sidebar_visibility, sidebar_visibility, sidebar_main_order, sidebar_submenu_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            if (!$stmt) {
                $itm_ui_error = 'Unable to prepare sample data insert.';
            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    'issssiisssssss',
                    $itm_ui_company_id,
                    $seed[0],
                    $seed[1],
                    $seed[2],
                    $seed[3],
                    $seed[4],
                    $seed[5],
                    $seed[6],
                    $seed[7],
                    $seed[8],
                    $seed[9],
                    $seed[10],
                    $seed[11],
                    $seed[12]
                );

                if (!mysqli_stmt_execute($stmt)) {
                    $itm_ui_error = 'Unable to insert sample data for this company.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

$itm_ui_row_count = 0;
if ($itm_ui_company_id > 0) {
    $resolvedCount = itm_ui_configuration_company_row_count($conn, $itm_ui_company_id);
    if ($resolvedCount === null) {
        $itm_ui_error = $itm_ui_error !== '' ? $itm_ui_error : 'Unable to load UI configuration data.';
    } else {
        $itm_ui_row_count = $resolvedCount;
    }
}

if ($itm_ui_error === '' && $itm_ui_row_count > 0) {
    header('Location: ../settings/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UI Configuration</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h1>🧩 UI Configuration</h1>
                <a class="btn" href="../settings/index.php">Open Settings</a>
            </div>

            <?php if ($itm_ui_error !== ''): ?>
                <div class="alert alert-danger"><?php echo sanitize($itm_ui_error); ?></div>
            <?php endif; ?>

            <div class="card">
                <p>No UI configuration exists for the active company yet.</p>
                <form method="POST" style="margin-top:12px;">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                    <button type="submit" name="add_sample_data" value="1" class="btn btn-primary">Add sample data</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
