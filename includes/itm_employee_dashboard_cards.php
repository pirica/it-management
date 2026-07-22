<?php
/**
 * Render employee dashboard stat card sections.
 *
 * Expects variables from itm_employee_dashboard_load_context() in scope.
 *
 * @var mysqli $conn
 * @var int $company_id
 */

if (!isset($dash) || !is_array($dash)) {
    return;
}

$dashShownTables = itm_employee_dashboard_card_shown_tables();
$dashAssignedAssets = (int)($dash['assigned_assets_count'] ?? 0);
$dashFileCount = (int)($dash['file_count'] ?? 0);
$dashTicketSummary = is_array($dash['ticket_summary'] ?? null) ? $dash['ticket_summary'] : ['open' => 0, 'total' => 0];
$dashEventsCreated = (int)($dash['total_events_created'] ?? 0);
$dashEventsForMe = (int)($dash['total_events_forme'] ?? 0);
$dashAlertsCreated = (int)($dash['total_alerts_created'] ?? 0);
$dashAlertsForMe = (int)($dash['total_alerts_forme'] ?? 0);
$dashVaultCount = (int)($dash['vault_entries_count'] ?? 0);
$dashLastLogin = $dash['last_login_row']['created_at'] ?? null;
$dashJoinedAt = (string)($current_user['created_at'] ?? '');
$dashWorkstation = $dash['workstation'] ?? null;
$dashActivityCount = count($dash['activity_list'] ?? []);
$dashSystemAccessCount = (int)($dash['system_access_count_1'] ?? 0);
$dashAllStats = is_array($dash['all_stats'] ?? null) ? $dash['all_stats'] : [];

/**
 * @param string $href
 * @param string $value
 * @param string $label
 * @param bool $isLink
 */
$renderDashCard = static function ($href, $value, $label, $isLink = true) {
    $tag = $isLink ? 'a' : 'div';
    $class = 'itm-emp-dash-card' . ($isLink ? '' : ' itm-emp-dash-card-static');
    $hrefAttr = $isLink ? ' href="' . sanitize($href) . '"' : '';
    echo '<' . $tag . ' class="' . $class . '"' . $hrefAttr . '>';
    echo '<span class="itm-emp-dash-card-val">' . sanitize((string)$value) . '</span>';
    echo '<span class="itm-emp-dash-card-lbl">' . sanitize($label) . '</span>';
    echo '</' . $tag . '>';
};
?>
<section class="itm-emp-dash-section">
    <h2 class="itm-emp-dash-section-title">My work</h2>
    <div class="itm-emp-dash-grid">
        <?php if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'equipment')): ?>
            <?php $renderDashCard('modules/equipment/index.php', $dashAssignedAssets, '💻 My Assets'); ?>
        <?php endif; ?>
        <?php if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'tickets')): ?>
            <?php $renderDashCard('modules/tickets/index.php', (int)$dashTicketSummary['open'] . '/' . (int)$dashTicketSummary['total'], '🎟️ My Tickets (Open/Total)'); ?>
        <?php endif; ?>
        <?php if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'events')): ?>
            <?php $renderDashCard('modules/events/index.php', $dashEventsCreated . '/' . $dashEventsForMe, '📅 My Events (My/For Me)'); ?>
        <?php endif; ?>
        <?php if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'alerts')): ?>
            <?php $renderDashCard('modules/alerts/index.php', $dashAlertsCreated . '/' . $dashAlertsForMe, '📢 My Alerts (My/For Me)'); ?>
        <?php endif; ?>
        <?php if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'equipment')): ?>
            <?php $renderDashCard('modules/equipment/index.php', $dashWorkstation ? '1' : '0', '💻 My Hardware'); ?>
        <?php endif; ?>
        <?php if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'employee_system_access')): ?>
            <?php $renderDashCard('modules/employee_system_access/index.php', $dashSystemAccessCount, 'System Access'); ?>
        <?php endif; ?>
    </div>
</section>

<section class="itm-emp-dash-section">
    <h2 class="itm-emp-dash-section-title">Personal</h2>
    <div class="itm-emp-dash-grid">
        <?php if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'explorer')): ?>
            <?php $renderDashCard('modules/explorer/index.php', $dashFileCount ? '1' : '0', '🌐 My Files'); ?>
        <?php endif; ?>
        <?php if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'passwords')): ?>
            <?php $renderDashCard('modules/passwords/index.php', $dashVaultCount, '🔐 Vault Entries'); ?>
        <?php endif; ?>
        <?php foreach ($dashAllStats as $statRow): ?>
            <?php
            if ((int)($statRow['count'] ?? 0) <= 0) {
                continue;
            }
            if (in_array((string)($statRow['table'] ?? ''), $dashShownTables, true)) {
                continue;
            }
            $slug = (string)($statRow['slug'] ?? '');
            if (!itm_employee_dashboard_module_slug_allowed($conn, $company_id, $slug)) {
                continue;
            }
            $renderDashCard('modules/' . $slug . '/index.php', (int)$statRow['count'], (string)($statRow['label'] ?? 'Module'));
            ?>
        <?php endforeach; ?>
    </div>
</section>

<section class="itm-emp-dash-section">
    <h2 class="itm-emp-dash-section-title">Activity</h2>
    <div class="itm-emp-dash-grid">
        <?php
        $lastLoginDisplay = $dashLastLogin ? date('d/m/y', strtotime((string)$dashLastLogin)) : 'Never';
        $joinedDisplay = $dashJoinedAt !== '' ? date('d/m/y', strtotime($dashJoinedAt)) : '—';
        $renderDashCard('#', $lastLoginDisplay, '🕒 Last Login', false);
        $renderDashCard('#', $joinedDisplay, '📅 Joined Date', false);
        if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'audit_logs')) {
            $renderDashCard('modules/audit_logs/index.php', $dashActivityCount, '🕒 My Activity');
        }
        ?>
    </div>
</section>
