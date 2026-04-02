<?php
$itm_current_page = basename($_SERVER['PHP_SELF']);
$itm_current_dir = basename(dirname($_SERVER['PHP_SELF']));
$itm_sidebarConfig = $ui_config ?? itm_ui_config_defaults();
$itm_sidebarStructure = itm_sidebar_structure();
$itm_sidebarItemCatalog = itm_sidebar_item_catalog();

$itm_visibility = $itm_sidebarConfig['sidebar_visibility'] ?? itm_default_sidebar_visibility();
$itm_mainOrder = $itm_sidebarConfig['sidebar_main_order'] ?? itm_default_sidebar_main_order();
$itm_submenuOrder = $itm_sidebarConfig['sidebar_submenu_order'] ?? itm_default_sidebar_submenu_order();

$itm_sectionsById = [];
foreach ($itm_sidebarStructure as $itm_section) {
    $itm_sectionsById[$itm_section['id']] = $itm_section;
}

$itm_orderedSections = [];
foreach ($itm_mainOrder as $itm_sectionId) {
    if (isset($itm_sectionsById[$itm_sectionId])) {
        $itm_orderedSections[] = $itm_sectionsById[$itm_sectionId];
        unset($itm_sectionsById[$itm_sectionId]);
    }
}
foreach ($itm_sectionsById as $itm_section) {
    $itm_orderedSections[] = $itm_section;
}
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h3>⚙️ IT Manager</h3>
        <p><?php echo sanitize($_SESSION['company_name'] ?? 'Company'); ?></p>
    </div>

    <?php foreach ($itm_orderedSections as $itm_section): ?>
        <?php
        $itm_sectionId = $itm_section['id'];
        if (($itm_visibility[$itm_sectionId] ?? 1) !== 1) {
            continue;
        }
        $itm_sectionItemOrder = $itm_submenuOrder[$itm_sectionId] ?? [];
        $itm_orderedItems = [];
        foreach ($itm_sectionItemOrder as $itm_itemId) {
            if (isset($itm_sidebarItemCatalog[$itm_itemId])) {
                $itm_orderedItems[] = $itm_sidebarItemCatalog[$itm_itemId];
            }
        }

        $itm_visibleItems = array_values(array_filter($itm_orderedItems, static function ($itm_item) use ($itm_visibility) {
            return ($itm_visibility[$itm_item['id']] ?? 1) === 1;
        }));

        if (!$itm_visibleItems) {
            continue;
        }
        ?>
        <div class="sidebar-title"><?php echo sanitize($itm_section['title']); ?></div>
        <ul class="sidebar-nav">
            <?php foreach ($itm_visibleItems as $itm_item): ?>
                <?php
                $itm_isActive = false;
                if (isset($itm_item['match_page'])) {
                    $itm_isActive = $itm_current_page === $itm_item['match_page'];
                } elseif (isset($itm_item['match_dir'])) {
                    $itm_isActive = $itm_current_dir === $itm_item['match_dir'];
                }
                ?>
                <li>
                    <a href="<?php echo BASE_URL . $itm_item['href']; ?>" class="<?php echo $itm_isActive ? 'active' : ''; ?>">
                        <?php echo sanitize($itm_item['label']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>

    <ul class="sidebar-nav" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
        <li><a href="<?php echo BASE_URL; ?>logout.php">🚪 Logout</a></li>
    </ul>
</div>
