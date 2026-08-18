<?php
/**
 * Admin overview stat cards (admin.php) — stacked sections using stats-grid.
 *
 * Expects $dash from itm_employee_dashboard_load_context() and session company context.
 *
 * @var mysqli $conn
 * @var int $company_id
 * @var int $user_id
 */

if (!isset($dash) || !is_array($dash)) {
    return;
}

$adminEmployeeId = (int)($user_id ?? ($_SESSION['employee_id'] ?? 0));
$adminCompanyId = (int)($company_id ?? ($_SESSION['company_id'] ?? 0));

$adminAssignedAssets = (int)($dash['assigned_assets_count'] ?? 0);
$adminTicketSummary = is_array($dash['ticket_summary'] ?? null) ? $dash['ticket_summary'] : ['open' => 0, 'total' => 0];
$adminEventsCreated = (int)($dash['total_events_created'] ?? 0);
$adminEventsForMe = (int)($dash['total_events_forme'] ?? 0);
$adminAlertsCreated = (int)($dash['total_alerts_created'] ?? 0);
$adminAlertsForMe = (int)($dash['total_alerts_forme'] ?? 0);
$adminFileCount = (int)($dash['file_count'] ?? 0);
$adminVaultCount = (int)($dash['vault_entries_count'] ?? 0);
$adminSystemAccessCount = (int)($dash['system_access_count_1'] ?? 0);
$adminWorkstation = $dash['workstation'] ?? null;
$adminMyActivityCount = (int)($dash['my_activity_count'] ?? 0);
$adminPrivateModuleCounts = is_array($dash['private_module_counts'] ?? null) ? $dash['private_module_counts'] : [];
$adminCompanyAuditLogsCount = itm_employee_dashboard_company_audit_logs_count($conn, $adminCompanyId);
$adminLastLogin = $dash['last_login_row']['created_at'] ?? null;
$adminJoinedAt = (string)($current_user['created_at'] ?? '');
$adminShownTables = itm_employee_dashboard_card_shown_tables();
$adminAllStats = is_array($dash['all_stats'] ?? null) ? $dash['all_stats'] : [];

/**
 * @param string $title
 * @param string $subtitle
 * @param callable $renderCards
 */
$renderAdminSection = static function ($title, $subtitle, $renderCards) {
    ob_start();
    $renderCards();
    $cardsHtml = trim((string)ob_get_clean());
    if ($cardsHtml === '') {
        return;
    }
    ?>
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <h2><?php echo sanitize($title); ?></h2>
        </div>
        <div class="card-body">
            <?php if ($subtitle !== ''): ?>
                <p style="margin:0 0 16px;color:var(--text-secondary);"><?php echo sanitize($subtitle); ?></p>
            <?php endif; ?>
            <div class="stats-grid" style="margin-bottom:0;">
                <?php echo $cardsHtml; ?>
            </div>
        </div>
    </div>
    <?php
};

/**
 * @param string $href
 * @param string $label
 * @param string|int $value
 * @param bool $isLink
 */
$renderAdminStatCard = static function ($href, $label, $value, $isLink = true) {
    $tag = $isLink ? 'a' : 'div';
    $class = 'stat-card' . ($isLink ? ' stat-card-link' : '');
    $hrefAttr = $isLink ? ' href="' . sanitize($href) . '"' : '';
    echo '<' . $tag . ' class="' . $class . '"' . $hrefAttr . '>';
    echo '<div class="stat-label">' . sanitize($label) . '</div>';
    echo '<div class="stat-number">' . sanitize((string)$value) . '</div>';
    echo '</' . $tag . '>';
};

$renderAdminSection('Company audit', 'Compliance and change history for this tenant', static function () use (
    $renderAdminStatCard,
    $conn,
    $adminCompanyId,
    $adminCompanyAuditLogsCount
) {
    if (itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, 'audit_logs')) {
        $renderAdminStatCard('modules/audit_logs/index.php', 'Audit Logs', $adminCompanyAuditLogsCount);
    }
});

$renderAdminSection('My work', 'Assets, tickets, and assignments', static function () use (
    $renderAdminStatCard,
    $conn,
    $adminCompanyId,
    $adminAssignedAssets,
    $adminTicketSummary,
    $adminEventsCreated,
    $adminEventsForMe,
    $adminAlertsCreated,
    $adminAlertsForMe,
    $adminWorkstation,
    $adminSystemAccessCount
) {
    if (itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, 'equipment')) {
        $renderAdminStatCard('modules/equipment/index.php', 'My Assets', $adminAssignedAssets);
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, 'tickets')) {
        $renderAdminStatCard(
            'modules/tickets/index.php',
            'My Tickets (Open/Total)',
            (int)$adminTicketSummary['open'] . '/' . (int)$adminTicketSummary['total']
        );
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, 'events')) {
        $renderAdminStatCard(
            'modules/events/index.php',
            'My Events (My/For Me)',
            $adminEventsCreated . '/' . $adminEventsForMe
        );
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, 'alerts')) {
        $renderAdminStatCard(
            'modules/alerts/index.php',
            'My Alerts (My/For Me)',
            $adminAlertsCreated . '/' . $adminAlertsForMe
        );
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, 'equipment')) {
        $renderAdminStatCard('modules/equipment/index.php', 'My Hardware', $adminWorkstation ? '1' : '0');
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, 'employee_system_access')) {
        $renderAdminStatCard('modules/employee_system_access/index.php', 'System Access', $adminSystemAccessCount);
    }
});

$renderAdminSection('Personal', 'Files, vault, and your modules', static function () use (
    $renderAdminStatCard,
    $conn,
    $adminCompanyId,
    $adminFileCount,
    $adminVaultCount,
    $adminAllStats,
    $adminShownTables
) {
    if (itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, 'explorer')) {
        $renderAdminStatCard('modules/explorer/index.php', 'My Files', $adminFileCount ? '1' : '0');
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, 'passwords')) {
        $renderAdminStatCard('modules/passwords/index.php', 'Vault Entries', $adminVaultCount);
    }
    foreach ($adminAllStats as $statRow) {
        if ((int)($statRow['count'] ?? 0) <= 0) {
            continue;
        }
        if (in_array((string)($statRow['table'] ?? ''), $adminShownTables, true)) {
            continue;
        }
        $slug = (string)($statRow['slug'] ?? '');
        if (!itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, $slug)) {
            continue;
        }
        $renderAdminStatCard(
            'modules/' . $slug . '/index.php',
            (string)($statRow['label'] ?? 'Module'),
            (int)$statRow['count']
        );
    }
});

$renderAdminSection('Private', 'Personal modules with no audit trail', static function () use (
    $renderAdminStatCard,
    $conn,
    $adminCompanyId,
    $adminPrivateModuleCounts
) {
    foreach (itm_employee_dashboard_private_module_definitions() as $privateDef) {
        $slug = (string)($privateDef['slug'] ?? '');
        if ($slug === '' || !itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, $slug)) {
            continue;
        }
        $renderAdminStatCard(
            'modules/' . $slug . '/index.php',
            (string)($privateDef['label'] ?? $slug),
            (int)($adminPrivateModuleCounts[$slug] ?? 0)
        );
    }
});

$renderAdminSection('Activity', 'Login history and recent actions', static function () use (
    $renderAdminStatCard,
    $conn,
    $adminCompanyId,
    $adminLastLogin,
    $adminJoinedAt,
    $adminMyActivityCount
) {
    $lastLoginDisplay = $adminLastLogin ? date('d/m/y', strtotime((string)$adminLastLogin)) : 'Never';
    $joinedDisplay = $adminJoinedAt !== '' ? date('d/m/y', strtotime($adminJoinedAt)) : '—';
    $renderAdminStatCard('#', 'Last Login', $lastLoginDisplay, false);
    $renderAdminStatCard('#', 'Joined Date', $joinedDisplay, false);
    if (itm_employee_dashboard_module_slug_allowed($conn, $adminCompanyId, 'myactivity')) {
        $renderAdminStatCard('modules/myactivity/index.php', 'My Activity', $adminMyActivityCount);
    }
});
