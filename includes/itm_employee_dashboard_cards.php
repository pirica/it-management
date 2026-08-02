<?php
/**
 * Render employee dashboard stat card sections.
 *
 * Expects variables from itm_employee_dashboard_load_context() in scope.
 *
 * @var mysqli $conn
 * @var int $company_id
 * @var int $user_id
 */

if (!isset($dash) || !is_array($dash)) {
    return;
}

$dashEmployeeId = (int)($user_id ?? ($_SESSION['employee_id'] ?? 0));

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
$dashMyActivityCount = (int)($dash['my_activity_count'] ?? 0);
$dashPrivateModuleCounts = is_array($dash['private_module_counts'] ?? null) ? $dash['private_module_counts'] : [];
$dashSystemAccessCount = (int)($dash['system_access_count_1'] ?? 0);
$dashWorkstation = $dash['workstation'] ?? null;
$dashAllStats = is_array($dash['all_stats'] ?? null) ? $dash['all_stats'] : [];

/**
 * @param string $href
 * @param string $value
 * @param string $label
 * @param string $icon
 * @param bool $isLink
 */
$renderDashCard = static function ($href, $value, $label, $icon = '📊', $isLink = true) {
    $tag = $isLink ? 'a' : 'div';
    $class = 'itm-emp-dash-card' . ($isLink ? '' : ' itm-emp-dash-card-static');
    $hrefAttr = $isLink ? ' href="' . sanitize($href) . '"' : '';
    echo '<' . $tag . ' class="' . $class . '"' . $hrefAttr . '>';
    echo '<span class="itm-emp-dash-card-top">';
    echo '<span class="itm-emp-dash-card-icon" aria-hidden="true">' . sanitize($icon) . '</span>';
    echo '<span class="itm-emp-dash-card-val">' . sanitize((string)$value) . '</span>';
    echo '</span>';
    echo '<span class="itm-emp-dash-card-lbl">' . sanitize($label) . '</span>';
    echo '</' . $tag . '>';
};

/**
 * @param string $title
 * @param string $subtitle
 * @param callable $renderCards
 * @param string $sectionClass
 */
$renderDashSection = static function ($title, $subtitle, $renderCards, $sectionClass = '') use ($renderDashCard) {
    ob_start();
    $renderCards($renderDashCard);
    $cardsHtml = trim((string)ob_get_clean());
    if ($cardsHtml === '') {
        return;
    }
    $sectionClassAttr = $sectionClass !== '' ? ' ' . sanitize($sectionClass) : '';
    ?>
    <section class="itm-emp-dash-section<?php echo $sectionClassAttr; ?>">
        <header class="itm-emp-dash-section-head">
            <h2 class="itm-emp-dash-section-title"><?php echo sanitize($title); ?></h2>
            <span class="itm-emp-dash-section-sub"><?php echo sanitize($subtitle); ?></span>
        </header>
        <div class="itm-emp-dash-panel">
            <div class="itm-emp-dash-grid">
                <?php echo $cardsHtml; ?>
            </div>
        </div>
    </section>
    <?php
};
?>
<div class="itm-emp-dash-sections">
<?php
$renderDashSection('My work', 'Assets, tickets, and assignments', static function ($renderDashCard) use (
    $conn,
    $company_id,
    $dashAssignedAssets,
    $dashTicketSummary,
    $dashEventsCreated,
    $dashEventsForMe,
    $dashAlertsCreated,
    $dashAlertsForMe,
    $dashWorkstation,
    $dashSystemAccessCount
) {
    if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'equipment')) {
        $renderDashCard('modules/equipment/index.php', $dashAssignedAssets, 'My Assets', '💻');
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'tickets')) {
        $renderDashCard(
            'modules/tickets/index.php',
            (int)$dashTicketSummary['open'] . '/' . (int)$dashTicketSummary['total'],
            'My Tickets (Open/Total)',
            '🎟️'
        );
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'events')) {
        $renderDashCard(
            'modules/events/index.php',
            $dashEventsCreated . '/' . $dashEventsForMe,
            'My Events (My/For Me)',
            '📅'
        );
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'alerts')) {
        $renderDashCard(
            'modules/alerts/index.php',
            $dashAlertsCreated . '/' . $dashAlertsForMe,
            'My Alerts (My/For Me)',
            '📢'
        );
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'equipment')) {
        $workstation = $dashWorkstation ?? null;
        $renderDashCard('modules/equipment/index.php', $workstation ? '1' : '0', 'My Hardware', '💻');
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'employee_system_access')) {
        $renderDashCard('modules/employee_system_access/index.php', $dashSystemAccessCount, 'System Access', '🔑');
    }
});

$renderDashSection('Personal', 'Files, vault, and your modules', static function ($renderDashCard) use (
    $conn,
    $company_id,
    $dashFileCount,
    $dashVaultCount,
    $dashAllStats,
    $dashShownTables
) {
    if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'explorer')) {
        $renderDashCard('modules/explorer/index.php', $dashFileCount ? '1' : '0', 'My Files', '🌐');
    }
    if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'passwords')) {
        $renderDashCard('modules/passwords/index.php', $dashVaultCount, 'Vault Entries', '🔐');
    }
    foreach ($dashAllStats as $statRow) {
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
        $renderDashCard(
            'modules/' . $slug . '/index.php',
            (int)$statRow['count'],
            (string)($statRow['label'] ?? 'Module'),
            '📊'
        );
    }
});

$renderDashSection('Private', 'Personal modules with no audit trail', static function ($renderDashCard) use (
    $conn,
    $company_id,
    $dashEmployeeId,
    $dashPrivateModuleCounts
) {
    foreach (itm_employee_dashboard_private_module_definitions() as $privateDef) {
        $slug = (string)($privateDef['slug'] ?? '');
        if ($slug === '' || !itm_employee_dashboard_module_slug_allowed($conn, $company_id, $slug)) {
            continue;
        }
        $icon = (string)($privateDef['icon'] ?? '📊');
        if (function_exists('itm_resolve_module_sidebar_icon')) {
            $resolvedIcon = trim((string)itm_resolve_module_sidebar_icon($conn, (int)$company_id, $dashEmployeeId, $slug));
            if ($resolvedIcon !== '') {
                $icon = $resolvedIcon;
            }
        }
        $renderDashCard(
            'modules/' . $slug . '/index.php',
            (int)($dashPrivateModuleCounts[$slug] ?? 0),
            (string)($privateDef['label'] ?? $slug),
            $icon
        );
    }
}, 'itm-emp-dash-section--private');

$renderDashSection('Activity', 'Login history and recent actions', static function ($renderDashCard) use (
    $conn,
    $company_id,
    $dashLastLogin,
    $dashJoinedAt,
    $dashMyActivityCount
) {
    $lastLoginDisplay = $dashLastLogin ? date('d/m/y', strtotime((string)$dashLastLogin)) : 'Never';
    $joinedDisplay = $dashJoinedAt !== '' ? date('d/m/y', strtotime($dashJoinedAt)) : '—';
    $renderDashCard('#', $lastLoginDisplay, 'Last Login', '🕒', false);
    $renderDashCard('#', $joinedDisplay, 'Joined Date', '📅', false);
    if (itm_employee_dashboard_module_slug_allowed($conn, $company_id, 'myactivity')) {
        $renderDashCard('modules/myactivity/index.php', $dashMyActivityCount, 'My Activity', '🕒');
    }
}, 'itm-emp-dash-section--activity');
?>
</div>
