<?php
/**
 * Navigation Sidebar Template
 * 
 * Renders the main navigation menu for the application.
 * Menu items, their visibility, and their display order are dynamically
 * controlled by the UI configuration settings stored in the database.
 */

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
$equipmentTypeSidebarVisibility = $sidebarConfig['equipment_type_sidebar_visibility'] ?? [];

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
<div class="sidebar">
    <div class="sidebar-header">
        <h3><?php echo sanitize($app_name ?? itm_ui_config_app_name($sidebarConfig)); ?></h3>
        <p><?php echo sanitize($_SESSION['company_name'] ?? 'Company'); ?></p>
    </div>

    <?php foreach ($orderedSections as $section): ?>
        <?php
        $sectionId = $section['id'];
        // Skip hidden sections
        if (($visibility[$sectionId] ?? 1) !== 1) {
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

        $visibleItems = array_values(array_filter($orderedItems, static function ($sidebarItem) use ($visibility, $equipmentTypeSidebarVisibility, $sidebarConfig) {
            if (($visibility[$sidebarItem['id']] ?? 1) !== 1) {
                return false;
            }

            $itemId = (string)($sidebarItem['id'] ?? '');
            if ($itemId === 'audit_logs' && ((int)($sidebarConfig['enable_audit_logs'] ?? 1) !== 1)) {
                return false;
            }

            if ($itemId !== '' && array_key_exists($itemId, $equipmentTypeSidebarVisibility)) {
                return ((int)$equipmentTypeSidebarVisibility[$itemId]) === 1;
            }

            return true;
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
                ?>
                <li>
                    <a href="<?php echo BASE_URL . $sidebarItem['href']; ?>" class="<?php echo $isActive ? 'active' : ''; ?>">
                        <?php echo sanitize($sidebarItem['label']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>

    <!-- Secondary Sidebar Actions -->
    <ul class="sidebar-nav" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
        <li>
            <form method="POST" action="<?php echo BASE_URL; ?>logout.php" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <button type="submit" class="btn btn-sm" style="width:100%;">🚪 Logout</button>
            </form>
        </li>
    </ul>
</div>
