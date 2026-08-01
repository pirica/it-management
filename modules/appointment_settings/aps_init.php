<?php
/**
 * Shared bootstrap for appointment_settings entry files.
 */
require_once __DIR__ . '/../../config/config.php';
require_once ROOT_PATH . 'includes/itm_appointment.php';
require_once ROOT_PATH . 'includes/itm_appointment_settings_admin.php';
require_once ROOT_PATH . 'includes/itm_crud_audit_fields.php';

$moduleSlug = 'appointment_settings';
$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$ui_config = itm_get_ui_configuration($conn, $company_id, $employee_id);
$currentUiConfig = $ui_config ?? [];

itm_appointment_settings_ensure_company_config($conn, $company_id, $employee_id);

function aps_require_permission($conn, $action)
{
    global $moduleSlug;
    if (function_exists('itm_require_crud_role_module_permission')) {
        itm_require_crud_role_module_permission($conn, $action, $moduleSlug);
    }
}

function aps_format_time_input($timeVal)
{
    if ($timeVal === null || $timeVal === '') {
        return '';
    }
    return substr((string)$timeVal, 0, 5);
}

function aps_type_label($nameOrRow)
{
    if (is_array($nameOrRow)) {
        return itm_appointment_type_display_label($nameOrRow);
    }
    return itm_appointment_type_default_label_for_name((string)$nameOrRow);
}

function aps_appointment_types_for_columns(array $types)
{
    return itm_appointment_types_sort_for_ui($types);
}

function aps_modality_yes_no($value)
{
    return (int)$value === 1 ? 'Yes' : 'No';
}

function aps_kind_label($kind)
{
    $map = [
        'settings' => 'Settings',
        'business_hour' => 'Business hour',
        'visit_reason' => 'Visit reason',
        'appointment_type' => 'Appointment type',
    ];
    return $map[$kind] ?? 'Record';
}

function aps_render_page_shell_open($conn, $company_id, $employee_id, $pageTitle)
{
    global $currentUiConfig, $crud_title;
    $resolvedModuleIcon = itm_resolve_module_sidebar_icon($conn, $company_id, $employee_id, 'appointment_settings');
    $cleanModuleTitle = itm_module_access_strip_catalog_label_prefix('Appointment Settings');
    $moduleListHeading = trim($resolvedModuleIcon . ' ' . $cleanModuleTitle);
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'appointment_settings', $pageTitle);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/styles.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/appointment.css">
</head>
<body>
<div class="container">
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/header.php'; ?>
        <div class="content">
    <?php
}

function aps_render_page_shell_close()
{
    ?>
        </div>
    </div>
</div>
</body>
</html>
    <?php
}

function aps_actions_cell_open()
{
    echo '<td class="itm-actions-cell" data-itm-actions-origin="1"><div class="itm-actions-wrap">';
}

function aps_actions_cell_close()
{
    echo '</div></td>';
}
