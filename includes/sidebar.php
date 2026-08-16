<?php
/**
 * Navigation Sidebar Template
 * 
 * Renders the main navigation menu for the application.
 * Menu items, their visibility, and their display order are dynamically
 * controlled by the UI configuration settings stored in the database.
 */

require_once __DIR__ . '/itm_script_entry_guard.php';
if (itm_skip_view_partial_unless_context(false, __FILE__)) {
    return;
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$sidebarConfig = $ui_config ?? itm_ui_config_defaults();
// Retrieve the defined structure and all possible menu items
$sidebarStructure = itm_sidebar_structure();
$sidebarItemCatalog = itm_sidebar_item_catalog();
$csrfToken = itm_get_csrf_token();

// Extract preferences from configuration
$visibility = $sidebarConfig['sidebar_visibility'] ?? itm_default_sidebar_visibility();
$mainOrder = $sidebarConfig['sidebar_main_order'] ?? itm_default_sidebar_main_order();
$submenuOrder = $sidebarConfig['sidebar_submenu_order'] ?? itm_default_sidebar_submenu_order();
if (function_exists('itm_normalize_sidebar_submenu_order')) {
    // Why: Persisted prefs can predate new catalog modules; always merge before render (same as Settings layout save).
    $submenuOrder = itm_normalize_sidebar_submenu_order($submenuOrder);
}

// Re-order top-level sections based on configuration
$sectionsById = [];
foreach ($sidebarStructure as $section) {
    $sectionsById[$section['id']] = $section;
}

$orderedSections = [];
foreach ($mainOrder as $sectionId) {
    if (isset($sectionsById[$sectionId])) {
        $orderedSections[] = $sectionsById[$sectionId];
        unset($sectionsById[$sectionId]);
    }
}
// Append any sections not explicitly mentioned in the ordering list
foreach ($sectionsById as $section) {
    $orderedSections[] = $section;
}
?>
<div class="sidebar" id="appSidebar" role="navigation" aria-label="Main navigation">
    <div class="sidebar-header">
        <button
            type="button"
            id="sidebarCollapseBtn"
            class="btn btn-sm sidebar-collapse-btn"
            title="Hide sidebar"
            aria-label="Hide sidebar"
            aria-controls="appSidebar"
            aria-expanded="true"
            data-sidebar-bound="false"
        >⏮</button>
        <div class="sidebar-header-text">
            <h3><?php echo sanitize($app_name ?? itm_ui_config_app_name($sidebarConfig)); ?></h3>
            <p><?php echo sanitize($_SESSION['company_name'] ?? 'Company'); ?></p>
        </div>
    </div>

    <?php foreach ($orderedSections as $section): ?>
        <?php
        $sectionId = $section['id'];
        // Skip hidden sections (pref + no visible children)
        if (!itm_sidebar_section_effective_visible($sectionId, $sidebarConfig, $conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0))) {
            continue;
        }

        // Re-order and filter submenu items within the section
        $sectionItemOrder = $submenuOrder[$sectionId] ?? [];
        $orderedItems = [];
        foreach ($sectionItemOrder as $itemId) {
            if (isset($sidebarItemCatalog[$itemId])) {
                $orderedItems[] = $sidebarItemCatalog[$itemId];
            }
        }

        $visibleItems = array_values(array_filter($orderedItems, static function ($sidebarItem) use ($sidebarConfig, $conn, $company_id) {
            return itm_sidebar_item_effective_visible($sidebarItem, $sidebarConfig, $conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0));
        }));

        // Don't render a section if it has no visible items
        if (!$visibleItems) {
            continue;
        }
        ?>
        <div class="sidebar-title"><?php echo sanitize($section['title']); ?></div>
        <ul class="sidebar-nav">
            <?php foreach ($visibleItems as $sidebarItem): ?>
                <?php
                // Determine if the current menu item corresponds to the page being viewed
                $isActive = false;
                if (isset($sidebarItem['match_page'])) {
                    $isActive = $current_page === $sidebarItem['match_page'];
                } elseif (isset($sidebarItem['match_dir'])) {
                    $isActive = $current_dir === $sidebarItem['match_dir'];
                }
                $displayLabel = (string)($sidebarItem['label'] ?? '');
                $moduleSlug = trim((string)($sidebarItem['match_dir'] ?? ''));
                if ($moduleSlug !== '' && function_exists('itm_resolve_module_sidebar_label')) {
                    $displayLabel = itm_resolve_module_sidebar_label(
                        $conn,
                        (int)($company_id ?? 0),
                        (int)($_SESSION['employee_id'] ?? 0),
                        $moduleSlug,
                        $displayLabel
                    );
                }
                ?>
                <li>
                    <a href="<?php echo BASE_URL . $sidebarItem['href']; ?>" class="<?php echo $isActive ? 'active' : ''; ?>">
                        <?php echo sanitize($displayLabel); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>

        <!-- Vault Status (Passwords Module Only) -->
    <?php if ($current_dir === 'passwords'): ?>
        <div class="sidebar-title">🔐 Vault</div>
        <ul class="sidebar-nav">
            <?php if (empty($_SESSION['vault_key'])): ?>
                <li><a href="#" onclick="showUnlockModal(); return false;">🔓 Unlock Vault</a></li>
            <?php else: ?>
                <li><a href="?action=lock">🔒 Lock Vault</a></li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
    <!-- Secondary Sidebar Actions -->
    <ul class="sidebar-nav sidebar-footer-nav">
        <li>
            <form method="POST" action="<?php echo BASE_URL; ?>logout.php" class="sidebar-logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <button type="submit" class="btn btn-sm sidebar-logout-btn">🚪 Logout</button>
            </form>
        </li>
    </ul>
</div>
