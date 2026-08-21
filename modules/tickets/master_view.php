<?php
/**
 * Tickets — master rollup incident view (cross-company; not session company scoped).
 *
 * Query: id, company_id (required), master_ticket_id (optional back-link).
 */

require '../../config/config.php';
require_once ROOT_PATH . 'includes/itm_problem_management.php';
require_once ROOT_PATH . 'includes/itm_tickets_view.php';

itm_require_crud_role_module_permission($conn, 'view', 'tickets');

$employeeId = (int)($_SESSION['employee_id'] ?? 0);
$ticketId = max(0, (int)($_GET['id'] ?? 0));
$ticketCompanyId = max(0, (int)($_GET['company_id'] ?? 0));
$masterTicketId = max(0, (int)($_GET['master_ticket_id'] ?? 0));

$load = itm_ticket_load_master_view_row($conn, $ticketId, $ticketCompanyId, $employeeId);
$item = $load['item'];
$companyName = (string)($load['company_name'] ?? '');
$loadError = (string)($load['error'] ?? '');

if ($item) {
    $ticketCompanyId = (int)($load['company_id'] ?? $ticketCompanyId);
    $resolvedMasterId = itm_ticket_resolve_master_ticket_id($conn, $ticketCompanyId, $ticketId);
    if ($masterTicketId <= 0) {
        $masterTicketId = $resolvedMasterId;
    }
    $linkedProblems = itm_problem_list_for_ticket($conn, $ticketCompanyId, $ticketId);
} else {
    $linkedProblems = [];
}

function ticket_master_render_lookup_badge(string $label, string $color, string $fallbackLabel = '-'): string
{
    $name = trim($label);
    if ($name === '') {
        $name = $fallbackLabel;
    }
    $hex = trim($color);
    if ($hex === '' || !preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
        $hex = '#9aa4b2';
    }

    return '<span class="badge" style="background-color:' . sanitize($hex) . '33;color:' . sanitize($hex) . ';">' . sanitize($name) . '</span>';
}

$backMasterHref = $masterTicketId > 0 ? '../master_tickets/view.php?id=' . $masterTicketId : '../master_tickets/index.php';
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
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $browserTitle = itm_crud_apply_module_icon_to_browser_title(
        $conn,
        (int)($company_id ?? 0),
        $employeeId,
        'tickets',
        'Master incident'
    );
    ?>
    <title><?= sanitize($browserTitle) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                <h1 title="Master rollup incident view">🔎</h1>
                <div style="display:flex;gap:8px;">
                    <a href="<?php echo sanitize($backMasterHref); ?>" class="btn" title="Back to master ticket">🔙</a>
                </div>
            </div>

            <?php if ($loadError !== ''): ?>
                <?php echo itm_render_alert_errors([$loadError]); ?>
            <?php elseif ($item): ?>
                <p class="itm-muted" style="margin-top:0;">
                    Cross-company read-only view — company: <strong><?php echo sanitize($companyName); ?></strong>
                    (id <?php echo (int)$ticketCompanyId; ?>).
                    <?php if ($masterTicketId > 0): ?>
                        Master <a href="../master_tickets/view.php?id=<?php echo (int)$masterTicketId; ?>">#<?php echo (int)$masterTicketId; ?></a>.
                    <?php endif; ?>
                </p>
                <div class="card">
                    <table>
                        <tbody>
                        <tr><th style="width:220px;">Company</th><td><?php echo sanitize($companyName); ?></td></tr>
                        <tr><th>ID</th><td><?php echo (int)$item['id']; ?></td></tr>
                        <tr><th>External Code</th><td><?php echo sanitize($item['ticket_external_code'] ?? '—'); ?></td></tr>
                        <tr><th>Title</th><td><?php echo sanitize($item['title'] ?? ''); ?></td></tr>
                        <tr><th>Description</th><td><?php echo nl2br(sanitize($item['description'] ?? '')); ?></td></tr>
                        <tr><th>Category</th><td><?php echo sanitize($item['category_name'] ?? '—'); ?></td></tr>
                        <tr><th>Status</th><td><?php echo ticket_master_render_lookup_badge((string)($item['status_name'] ?? ''), (string)($item['status_color'] ?? ''), 'Open'); ?></td></tr>
                        <tr><th>Priority</th><td><?php echo ticket_master_render_lookup_badge((string)($item['priority_name'] ?? ''), (string)($item['priority_color'] ?? '')); ?></td></tr>
                        <tr><th>SLA Status</th><td><?php echo itm_ticket_sla_render_badge($item); ?></td></tr>
                        <tr><th>Assigned To</th><td><?php echo sanitize($item['assigned_to_username'] ?? '—'); ?></td></tr>
                        <tr><th>Created By</th><td><?php echo sanitize($item['created_by_username'] ?? '—'); ?></td></tr>
                        <tr><th>Equipment</th><td><?php echo sanitize($item['equipment_name'] ?? '—'); ?></td></tr>
                        <tr><th>Due Date</th><td><?php echo sanitize($item['due_date'] ?? '—'); ?></td></tr>
                        <tr>
                            <th>Master Ticket</th>
                            <td>
                                <?php if ($masterTicketId > 0): ?>
                                    <a href="../master_tickets/view.php?id=<?php echo (int)$masterTicketId; ?>" title="View master ticket">#<?php echo (int)$masterTicketId; ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr><th>Updated At</th><td><?php echo sanitize(itm_format_audit_timestamp_display($item['updated_at'] ?? '')); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($linkedProblems)): ?>
                    <div class="card" style="margin-top:16px;">
                        <h2 style="margin-top:0;" title="Linked problems">Linked Problems</h2>
                        <table data-itm-no-import-excel="1">
                            <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($linkedProblems as $linkedProblem): ?>
                                <tr>
                                    <td><?php echo sanitize((string)($linkedProblem['title'] ?? '')); ?></td>
                                    <td><?php echo itm_problem_status_badge($linkedProblem['status'] ?? ''); ?></td>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <a class="btn btn-sm" href="../problems/view.php?id=<?php echo (int)$linkedProblem['id']; ?>" title="View">🔎</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
