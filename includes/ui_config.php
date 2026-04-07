<?php
/**
 * UI Configuration and Sidebar Management Functions
 * 
 * Handles the application's dynamic UI settings, including sidebar structure,
 * custom module discovery, auto-scaffolding, and user preferences for
 * display positions and visibility.
 */

/**
 * Returns the default hardcoded sidebar structure
 */
function itm_sidebar_base_structure() {
    return [
        [
            'id' => 'dashboard',
            'title' => '📊 Dashboard',
            'items' => [
                ['id' => 'dashboard_link', 'label' => '📈 Dashboard', 'href' => 'dashboard.php', 'match_page' => 'dashboard.php'],
                ['id' => 'settings', 'label' => '⚙️ Settings', 'href' => 'modules/settings/', 'match_dir' => 'settings'],
            ],
        ],
        [
            'id' => 'management',
            'title' => '🏢 Management',
            'items' => [
                ['id' => 'equipment', 'label' => '🖥️ Equipment', 'href' => 'modules/equipment/', 'match_dir' => 'equipment'],
                ['id' => 'is_workstation', 'label' => '💻 Is Workstation', 'href' => 'modules/is_workstation/', 'match_dir' => 'is_workstation'],
                ['id' => 'is_server', 'label' => '🖥️ Is Server', 'href' => 'modules/is_server/', 'match_dir' => 'is_server'],
                ['id' => 'is_switch', 'label' => '🔀 Is Switch', 'href' => 'modules/is_switch/', 'match_dir' => 'is_switch'],
                ['id' => 'is_printer', 'label' => '🖨️ Is Printer', 'href' => 'modules/is_printer/', 'match_dir' => 'is_printer'],
                ['id' => 'is_pos', 'label' => '🏧 Is POS', 'href' => 'modules/is_pos/', 'match_dir' => 'is_pos'],
                ['id' => 'switch_ports', 'label' => '🖧 Switch Ports', 'href' => 'modules/switch_ports/', 'match_dir' => 'switch_ports'],
                ['id' => 'tickets', 'label' => '🎟️ Tickets', 'href' => 'modules/tickets/', 'match_dir' => 'tickets'],
            ],
        ],
        [
            'id' => 'employee',
            'title' => '👤 Employee',
            'items' => [
                ['id' => 'employees', 'label' => '👤 Employees', 'href' => 'modules/employees/', 'match_dir' => 'employees'],
                ['id' => 'employee_system_access', 'label' => '🔐 Employee System Access', 'href' => 'modules/employee_system_access/', 'match_dir' => 'employee_system_access'],
                ['id' => 'system_access', 'label' => '🛡️ System Access', 'href' => 'modules/system_access/', 'match_dir' => 'system_access'],
                ['id' => 'departments', 'label' => '🏢 Departments', 'href' => 'modules/departments/', 'match_dir' => 'departments'],
            ],
        ],
        [
            'id' => 'admin',
            'title' => '🧰 Admin',
            'items' => [
                ['id' => 'inventory', 'label' => '📦 Inventory', 'href' => 'modules/inventory/', 'match_dir' => 'inventory'],
                ['id' => 'users', 'label' => '👥 Users', 'href' => 'modules/users/', 'match_dir' => 'users'],
                ['id' => 'companies', 'label' => '🌍 Companies', 'href' => 'modules/companies/', 'match_dir' => 'companies'],
            ],
        ],
        [
            'id' => 'reference_data',
            'title' => '🗂️ Reference Data',
            'items' => [
                ['id' => 'it_locations', 'label' => '📍 IT Locations', 'href' => 'modules/it_locations/', 'match_dir' => 'it_locations'],
                ['id' => 'location_types', 'label' => '🧭 Location Types', 'href' => 'modules/location_types/', 'match_dir' => 'location_types'],
                ['id' => 'equipment_types', 'label' => '🖥️ Equipment Types', 'href' => 'modules/equipment_types/', 'match_dir' => 'equipment_types'],
                ['id' => 'equipment_statuses', 'label' => '✅ Equipment Statuses', 'href' => 'modules/equipment_statuses/', 'match_dir' => 'equipment_statuses'],
                ['id' => 'manufacturers', 'label' => '🏭 Manufacturers', 'href' => 'modules/manufacturers/', 'match_dir' => 'manufacturers'],
                ['id' => 'suppliers', 'label' => '🚚 Suppliers', 'href' => 'modules/suppliers/', 'match_dir' => 'suppliers'],
                ['id' => 'supplier_statuses', 'label' => '🟢 Supplier Statuses', 'href' => 'modules/supplier_statuses/', 'match_dir' => 'supplier_statuses'],
                ['id' => 'racks', 'label' => '🗄️ Racks', 'href' => 'modules/racks/', 'match_dir' => 'racks'],
                ['id' => 'idfs', 'label' => '🗄️ IDFs', 'href' => 'modules/idfs/', 'match_dir' => 'idfs'],
                ['id' => 'rack_statuses', 'label' => '📶 Rack Statuses', 'href' => 'modules/rack_statuses/', 'match_dir' => 'rack_statuses'],
                ['id' => 'switch_status', 'label' => '📡 Switch Status', 'href' => 'modules/switch_status/', 'match_dir' => 'switch_status'],
                ['id' => 'cable_colors', 'label' => '🎨 Cable Colors', 'href' => 'modules/cable_colors/', 'match_dir' => 'cable_colors'],
                ['id' => 'ticket_categories', 'label' => '🏷️ Ticket Categories', 'href' => 'modules/ticket_categories/', 'match_dir' => 'ticket_categories'],
                ['id' => 'ticket_statuses', 'label' => '🚦 Ticket Statuses', 'href' => 'modules/ticket_statuses/', 'match_dir' => 'ticket_statuses'],
                ['id' => 'ticket_priorities', 'label' => '🔥 Ticket Priorities', 'href' => 'modules/ticket_priorities/', 'match_dir' => 'ticket_priorities'],
                ['id' => 'employee_statuses', 'label' => '🧑‍💼 Employee Statuses', 'href' => 'modules/employee_statuses/', 'match_dir' => 'employee_statuses'],
                ['id' => 'audit_logs', 'label' => '🧾 Audit Logs', 'href' => 'modules/audit_logs/', 'match_dir' => 'audit_logs'],
            ],
        ],
    ];
}

/**
 * Formats a table name into a human-readable title
 */
function itm_sidebar_humanize_table_name($tableName) {
    $label = str_replace('_', ' ', (string)$tableName);
    $label = trim($label);
    if ($label === '') {
        return '';
    }

    return ucwords($label);
}

/**
 * Maps known equipment type names to equipment flag fields.
 */
function itm_equipment_type_flag_field($typeName) {
    $normalized = strtolower(trim((string)$typeName));
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
    $normalized = trim((string)$normalized, '_');

    $map = [
        'workstation' => 'is_workstation',
        'server' => 'is_server',
        'switch' => 'is_switch',
        'printer' => 'is_printer',
        'pos' => 'is_pos',
    ];

    return $map[$normalized] ?? '';
}

/**
 * Creates module wrappers that delegate to equipment/index.php for known types.
 */
function itm_ensure_equipment_type_module_scaffold($typeName) {
    $flagField = itm_equipment_type_flag_field($typeName);
    $moduleName = itm_equipment_type_sidebar_item_id($typeName);
    if ($moduleName === '') {
        return false;
    }

    $moduleTitleMap = [
        'is_workstation' => '💻 Is Workstation',
        'is_server' => '🖥️ Is Server',
        'is_switch' => '🔀 Is Switch',
        'is_printer' => '🖨️ Is Printer',
        'is_pos' => '🏧 Is POS',
    ];
    $searchPlaceholderMap = [
        'is_workstation' => 'Use SQL wildcards, e.g. %%desk%%',
        'is_server' => 'Use SQL wildcards, e.g. %%srv%%',
        'is_switch' => 'Use SQL wildcards, e.g. %%sw%%',
        'is_printer' => 'Use SQL wildcards, e.g. %%print%%',
        'is_pos' => 'Use SQL wildcards, e.g. %%pos%%',
    ];

    $displayTypeName = trim((string)$typeName);
    if ($displayTypeName === '') {
        $displayTypeName = itm_sidebar_humanize_table_name($moduleName);
    }

    $moduleTitle = $moduleTitleMap[$moduleName] ?? ('🖥️ Is ' . $displayTypeName);
    $searchPlaceholder = $searchPlaceholderMap[$flagField] ?? 'Use SQL wildcards, e.g. %%asset%%';

    $modulesRoot = dirname(__DIR__) . '/modules';
    $moduleDir = $modulesRoot . '/' . $moduleName;
    if (!is_dir($moduleDir) && !mkdir($moduleDir, 0775, true) && !is_dir($moduleDir)) {
        return false;
    }
    $fileContents = [];

    $indexContent = "<?php\n";
    $indexContent .= '$equipmentModuleTitle = ' . var_export($moduleTitle, true) . ";\n";
    $indexContent .= '$equipmentFlagField = ' . var_export($flagField, true) . ";\n";
    if ($flagField === '') {
        $indexContent .= '$equipmentTypeNameFilter = ' . var_export($displayTypeName, true) . ";\n";
    }
    $indexContent .= '$equipmentSearchPlaceholder = ' . var_export($searchPlaceholder, true) . ";\n";
    $indexContent .= '$equipmentModuleBasePath = ' . var_export('../equipment/', true) . ";\n";
    $indexContent .= '$equipmentViewPath = ' . var_export('', true) . ";\n";
    $indexContent .= '$equipmentEditPath = ' . var_export('../equipment/', true) . ";\n";
    $indexContent .= '$equipmentAllowCreate = false;' . "\n";
    $indexContent .= '$equipmentAllowDelete = false;' . "\n";
    $indexContent .= '$equipmentAllowImport = false;' . "\n";
    $indexContent .= "require '../equipment/index.php';\n";
    $fileContents['index.php'] = $indexContent;

    $viewContent = "<?php\n";
    $viewContent .= '$equipmentRequiredFlagField = ' . var_export($flagField, true) . ";\n";
    $viewContent .= '$equipmentViewBackPath = ' . var_export('index.php', true) . ";\n";
    $viewContent .= '$equipmentViewEditPath = ' . var_export('edit.php', true) . ";\n";
    $viewContent .= "require '../equipment/view.php';\n";
    $fileContents['view.php'] = $viewContent;

    $fileContents['edit.php'] = "<?php\nrequire '../equipment/edit.php';\n";
    $fileContents['delete.php'] = "<?php\nrequire '../equipment/delete.php';\n";
    $fileContents['list_all.php'] = "<?php\nrequire '../equipment/list_all.php';\n";
    $fileContents['view_all.php'] = "<?php\nrequire 'list_all.php';\n";

    $createdFiles = [];
    foreach ($fileContents as $fileName => $content) {
        $targetPath = $moduleDir . '/' . $fileName;
        if (is_file($targetPath)) {
            continue;
        }
        if (file_put_contents($targetPath, $content) === false) {
            return false;
        }
        $createdFiles[] = $fileName;
    }

    if (!empty($createdFiles) && function_exists('itm_log_audit') && isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        itm_log_audit(
            $GLOBALS['conn'],
            'modules/' . $moduleName,
            0,
            'INSERT',
            null,
            ['created_files' => $createdFiles]
        );
    }

    return is_file($moduleDir . '/index.php');
}

/**
 * Automatically creates a new module directory and CRUD files
 * 
 * Uses the 'manufacturers' module as a template for new database tables
 * that don't yet have an associated UI module.
 */
function itm_auto_create_module_scaffold($moduleName) {
    $moduleName = trim((string)$moduleName);
    if ($moduleName === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $moduleName)) {
        return false;
    }

    $modulesRoot = dirname(__DIR__) . '/modules';
    $moduleDir = $modulesRoot . '/' . $moduleName;
    $templateDir = $modulesRoot . '/manufacturers';
    if (!is_dir($templateDir)) {
        return false;
    }

    if (!is_dir($moduleDir) && !mkdir($moduleDir, 0775, true) && !is_dir($moduleDir)) {
        return false;
    }

    $templateFiles = glob($templateDir . '/*.php') ?: [];
    foreach ($templateFiles as $templatePath) {
        $fileName = basename($templatePath);
        $targetPath = $moduleDir . '/' . $fileName;
        if (is_file($targetPath)) {
            continue;
        }

        $action = pathinfo($fileName, PATHINFO_FILENAME);
        $title = itm_sidebar_humanize_table_name($moduleName);
        $stub = "<?php\n";
        $stub .= '$crud_table = ' . var_export($moduleName, true) . ";\n";
        $stub .= '$crud_title = ' . var_export($title, true) . ";\n";
        $stub .= '$crud_action = ' . var_export($action, true) . ";\n";
        $stub .= "require __DIR__ . '/../manufacturers/" . $fileName . "';\n";

        if (file_put_contents($targetPath, $stub) === false) {
            return false;
        }
    }

    return is_file($moduleDir . '/index.php');
}

/**
 * Returns the complete sidebar structure, including auto-discovered modules
 */
function itm_sidebar_structure($conn = null) {
    // Static cache to avoid redundant filesystem and DB scans in a single request
    static $itm_sidebar_cache = null;
    if ($itm_sidebar_cache !== null) {
        return $itm_sidebar_cache;
    }

    $structure = itm_sidebar_base_structure();
    $existingItemIds = [];
    foreach ($structure as $section) {
        foreach (($section['items'] ?? []) as $item) {
            $existingItemIds[$item['id']] = true;
        }
    }

    if ($conn === null && isset($GLOBALS['conn']) && is_object($GLOBALS['conn'])) {
        $conn = $GLOBALS['conn'];
    }

    $moduleNames = [];
    $modulesRoot = dirname(__DIR__) . '/modules';
    // Discover modules by scanning the filesystem
    if (is_dir($modulesRoot)) {
        $moduleDirs = glob($modulesRoot . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($moduleDirs as $moduleDir) {
            $moduleName = basename($moduleDir);
            if ($moduleName === '' || isset($existingItemIds[$moduleName])) {
                continue;
            }

            if (is_file($moduleDir . '/index.php')) {
                $moduleNames[$moduleName] = true;
            }
        }
    }

    // Discover modules by scanning database tables and auto-scaffolding if needed
    if ($conn) {
        $equipmentTypeRes = mysqli_query($conn, 'SELECT name FROM equipment_types');
        if ($equipmentTypeRes) {
            while ($equipmentTypeRow = mysqli_fetch_assoc($equipmentTypeRes)) {
                $typeName = (string)($equipmentTypeRow['name'] ?? '');
                itm_ensure_equipment_type_module_scaffold($typeName);
                $equipmentTypeModuleName = itm_equipment_type_sidebar_item_id($typeName);
                if ($equipmentTypeModuleName !== '') {
                    $equipmentTypeModuleIndex = $modulesRoot . '/' . $equipmentTypeModuleName . '/index.php';
                    if (is_file($equipmentTypeModuleIndex) && !isset($existingItemIds[$equipmentTypeModuleName])) {
                        $moduleNames[$equipmentTypeModuleName] = true;
                    }
                }
            }
        }

        $tablesRes = mysqli_query($conn, 'SHOW TABLES');
        if ($tablesRes) {
            while ($tableRow = mysqli_fetch_array($tablesRes)) {
                $table = isset($tableRow[0]) ? (string)$tableRow[0] : '';
                if ($table === '' || isset($existingItemIds[$table])) {
                    continue;
                }

                $moduleIndex = $modulesRoot . '/' . $table . '/index.php';
                if (!is_file($moduleIndex)) {
                    itm_auto_create_module_scaffold($table);
                }

                if (is_file($moduleIndex)) {
                    $moduleNames[$table] = true;
                }
            }
        }
    }

    $discoveredEquipmentTypeItems = [];
    $discoveredItems = [];
    foreach (array_keys($moduleNames) as $moduleName) {
        $item = [
            'id' => $moduleName,
            'label' => '🧩 ' . itm_sidebar_humanize_table_name($moduleName),
            'href' => 'modules/' . $moduleName . '/',
            'match_dir' => $moduleName,
        ];
        if (strpos($moduleName, 'is_') === 0) {
            $typeLabel = trim(preg_replace('/^is[_\s-]*/i', '', (string)$moduleName));
            $item['label'] = ' Is ' . itm_sidebar_humanize_table_name($typeLabel);
            $discoveredEquipmentTypeItems[] = $item;
            continue;
        }
        $discoveredItems[] = $item;
    }

    if (!$discoveredItems && !$discoveredEquipmentTypeItems) {
        return $structure;
    }

    // Sort newly discovered items alphabetically
    usort($discoveredItems, static function ($a, $b) {
        return strcmp($a['label'], $b['label']);
    });
    usort($discoveredEquipmentTypeItems, static function ($a, $b) {
        return strcmp($a['label'], $b['label']);
    });

    // Append discovered items to their target sections
    foreach ($structure as &$section) {
        if (($section['id'] ?? '') === 'management' && $discoveredEquipmentTypeItems) {
            $section['items'] = array_merge($section['items'], $discoveredEquipmentTypeItems);
            continue;
        }
        if (($section['id'] ?? '') === 'reference_data') {
            $section['items'] = array_merge($section['items'], $discoveredItems);
        }
    }
    unset($section);

    $itm_sidebar_cache = $structure;
    return $structure;
}

/**
 * Returns default visibility settings (all visible)
 */
function itm_default_sidebar_visibility() {
    $visibility = [];
    foreach (itm_sidebar_structure() as $section) {
        $visibility[$section['id']] = 1;
        foreach ($section['items'] as $item) {
            $visibility[$item['id']] = 1;
        }
    }
    return $visibility;
}

/**
 * Returns default section order
 */
function itm_default_sidebar_main_order() {
    return array_map(static function ($section) {
        return $section['id'];
    }, itm_sidebar_structure());
}

/**
 * Returns default submenu item order
 */
function itm_default_sidebar_submenu_order() {
    $submenuOrder = [];
    foreach (itm_sidebar_structure() as $section) {
        $submenuOrder[$section['id']] = array_map(static function ($item) {
            return $item['id'];
        }, $section['items']);
    }
    return $submenuOrder;
}

/**
 * Returns a flat mapping of all menu item IDs to their configurations
 */
function itm_sidebar_item_catalog() {
    $catalog = [];
    foreach (itm_sidebar_structure() as $section) {
        foreach ($section['items'] as $item) {
            $catalog[$item['id']] = $item;
        }
    }
    return $catalog;
}

/**
 * Retrieves the sidebar label for a given module directory
 */
function itm_sidebar_label_for_module($moduleDir) {
    $moduleDir = trim((string)$moduleDir);
    if ($moduleDir === '') {
        return null;
    }

    foreach (itm_sidebar_item_catalog() as $item) {
        if (($item['match_dir'] ?? '') === $moduleDir) {
            return (string)($item['label'] ?? '');
        }
    }

    return null;
}

/**
 * Returns a mapping of items to their parent sections
 */
function itm_sidebar_default_item_parent_map() {
    $map = [];
    foreach (itm_sidebar_structure() as $section) {
        foreach ($section['items'] as $item) {
            $map[$item['id']] = $section['id'];
        }
    }
    return $map;
}

/**
 * Returns the hardcoded default UI configuration
 */
function itm_ui_config_defaults() {
    return [
        'table_actions_position' => 'left_right',
        'new_button_position' => 'left_right',
        'export_buttons_position' => 'left_right',
        'back_save_position' => 'left_right',
        'enable_all_error_reporting' => 1,
        'enable_audit_logs' => 1,
        'records_per_page' => '25',
        'equipment_type_sidebar_visibility' => [],
        'sidebar_visibility' => itm_default_sidebar_visibility(),
        'sidebar_main_order' => itm_default_sidebar_main_order(),
        'sidebar_submenu_order' => itm_default_sidebar_submenu_order(),
    ];
}

/**
 * Builds a deterministic sidebar item id from an equipment type name.
 */
function itm_equipment_type_sidebar_item_id($typeName) {
    $normalized = strtolower(trim((string)$typeName));
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
    $normalized = trim((string)$normalized, '_');
    if ($normalized === '') {
        return '';
    }

    if (strpos($normalized, 'is_') !== 0) {
        $normalized = 'is_' . $normalized;
    }

    return $normalized;
}

/**
 * Returns the list of valid position values for UI elements
 */
function itm_ui_allowed_positions() {
    return [
        'table_actions_position' => ['left_right', 'left', 'right'],
        'new_button_position' => ['left_right', 'left', 'right'],
        'export_buttons_position' => [
            'left_right', 'left', 'right', 'bottom_right', 'bottom_left', 'top_right', 'top_left', 'top_bottom_right', 'top_bottom_left',
        ],
        'back_save_position' => [
            'left_right', 'left', 'right', 'bottom_right', 'bottom_left', 'top_right', 'top_left', 'top_bottom_right', 'top_bottom_left',
        ],
    ];
}

/**
 * Normalizes boolean-ish flags to 1 or 0
 */
function itm_normalize_flag($value) {
    return ((string)$value === '1' || $value === 1 || $value === true) ? 1 : 0;
}

/**
 * Ensures the ui_configuration table exists and has the current schema
 */
function itm_ensure_ui_configuration_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `ui_configuration` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `table_actions_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `new_button_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `export_buttons_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `back_save_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `enable_all_error_reporting` TINYINT(1) NOT NULL DEFAULT 1,
        `enable_audit_logs` TINYINT(1) NOT NULL DEFAULT 1,
        `records_per_page` VARCHAR(10) NOT NULL DEFAULT '25',
        `equipment_type_sidebar_visibility` LONGTEXT NULL,
        `sidebar_visibility` LONGTEXT NULL,
        `sidebar_main_order` LONGTEXT NULL,
        `sidebar_submenu_order` LONGTEXT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_ui_configuration_company` (`company_id`),
        CONSTRAINT `fk_ui_configuration_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $sql) !== true) {
        return false;
    }

    // Add missing columns if they don't exist
    $columns = [
        'enable_all_error_reporting' => "ALTER TABLE `ui_configuration` ADD COLUMN `enable_all_error_reporting` TINYINT(1) NOT NULL DEFAULT 1 AFTER `back_save_position`",
        'enable_audit_logs' => "ALTER TABLE `ui_configuration` ADD COLUMN `enable_audit_logs` TINYINT(1) NOT NULL DEFAULT 1 AFTER `enable_all_error_reporting`",
        'records_per_page' => "ALTER TABLE `ui_configuration` ADD COLUMN `records_per_page` VARCHAR(10) NOT NULL DEFAULT '25' AFTER `enable_audit_logs`",
        'equipment_type_sidebar_visibility' => "ALTER TABLE `ui_configuration` ADD COLUMN `equipment_type_sidebar_visibility` LONGTEXT NULL AFTER `records_per_page`",
        'sidebar_visibility' => "ALTER TABLE `ui_configuration` ADD COLUMN `sidebar_visibility` LONGTEXT NULL AFTER `back_save_position`",
        'sidebar_main_order' => "ALTER TABLE `ui_configuration` ADD COLUMN `sidebar_main_order` LONGTEXT NULL AFTER `sidebar_visibility`",
        'sidebar_submenu_order' => "ALTER TABLE `ui_configuration` ADD COLUMN `sidebar_submenu_order` LONGTEXT NULL AFTER `sidebar_main_order`",
    ];

    foreach ($columns as $column => $alterSql) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM `ui_configuration` LIKE '" . mysqli_real_escape_string($conn, $column) . "'");
        if (!$check) {
            return false;
        }
        if (mysqli_num_rows($check) === 0 && mysqli_query($conn, $alterSql) !== true) {
            return false;
        }
    }

    return itm_ensure_sidebar_layout_table($conn);
}

/**
 * Ensures the sidebar_layout junction table exists
 */
function itm_ensure_sidebar_layout_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `sidebar_layout` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `entry_type` ENUM('section','item') NOT NULL,
        `entry_id` VARCHAR(100) NOT NULL,
        `section_id` VARCHAR(100) NULL,
        `display_order` INT NOT NULL DEFAULT 0,
        `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_sidebar_layout_entry` (`company_id`, `entry_type`, `entry_id`),
        KEY `idx_sidebar_layout_company_type_order` (`company_id`, `entry_type`, `display_order`),
        CONSTRAINT `fk_sidebar_layout_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    return mysqli_query($conn, $sql) === true;
}

/**
 * Fetches the UI configuration for a specific company
 */
function itm_get_ui_configuration($conn, $company_id) {
    $defaults = itm_ui_config_defaults();
    $company_id = (int)$company_id;

    if ($company_id <= 0 || !itm_ensure_ui_configuration_table($conn)) {
        return $defaults;
    }

    // Retrieve settings from the database
    $sql = 'SELECT table_actions_position, new_button_position, export_buttons_position, back_save_position, enable_all_error_reporting, enable_audit_logs, records_per_page, equipment_type_sidebar_visibility, sidebar_visibility, sidebar_main_order, sidebar_submenu_order FROM ui_configuration WHERE company_id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $defaults;
    }

    mysqli_stmt_bind_param($stmt, 'i', $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        // Fallback to layout configuration if main config missing
        $layoutConfig = itm_get_sidebar_layout_config($conn, $company_id);
        if ($layoutConfig !== null) {
            $defaults['sidebar_visibility'] = $layoutConfig['sidebar_visibility'];
            $defaults['sidebar_main_order'] = $layoutConfig['sidebar_main_order'];
            $defaults['sidebar_submenu_order'] = $layoutConfig['sidebar_submenu_order'];
        }
        return $defaults;
    }

    $config = itm_normalize_ui_configuration($row);
    // Overlay detailed layout configuration
    $layoutConfig = itm_get_sidebar_layout_config($conn, $company_id);
    if ($layoutConfig !== null) {
        $config['sidebar_visibility'] = $layoutConfig['sidebar_visibility'];
        $config['sidebar_main_order'] = $layoutConfig['sidebar_main_order'];
        $config['sidebar_submenu_order'] = $layoutConfig['sidebar_submenu_order'];
    }

    return $config;
}

/**
 * Validates and normalizes configuration values
 */
function itm_normalize_ui_configuration($values) {
    $defaults = itm_ui_config_defaults();
    $allowed = itm_ui_allowed_positions();

    // Sanitize position settings
    foreach ($allowed as $key => $options) {
        $value = isset($values[$key]) ? (string)$values[$key] : $defaults[$key];
        $values[$key] = in_array($value, $options, true) ? $value : $defaults[$key];
    }

    // Normalize complex JSON structures
    $values['sidebar_visibility'] = itm_normalize_sidebar_visibility($values['sidebar_visibility'] ?? null);
    $values['sidebar_main_order'] = itm_normalize_sidebar_main_order($values['sidebar_main_order'] ?? null);
    $values['sidebar_submenu_order'] = itm_normalize_sidebar_submenu_order($values['sidebar_submenu_order'] ?? null);
    $values['enable_all_error_reporting'] = itm_normalize_flag($values['enable_all_error_reporting'] ?? $defaults['enable_all_error_reporting']);
    $values['enable_audit_logs'] = itm_normalize_flag($values['enable_audit_logs'] ?? $defaults['enable_audit_logs']);
    $values['equipment_type_sidebar_visibility'] = itm_normalize_equipment_type_sidebar_visibility($values['equipment_type_sidebar_visibility'] ?? []);

    // Validate records per page
    $recordsPerPage = strtolower((string)($values['records_per_page'] ?? $defaults['records_per_page']));
    if ($recordsPerPage === 'all') {
        $values['records_per_page'] = 'all';
    } elseif (ctype_digit($recordsPerPage) && (int)$recordsPerPage > 0 && (int)$recordsPerPage <= 1000000) {
        $values['records_per_page'] = (string)((int)$recordsPerPage);
    } else {
        $values['records_per_page'] = $defaults['records_per_page'];
    }

    return $values;
}

/**
 * Normalizes equipment type -> visibility mapping.
 */
function itm_normalize_equipment_type_sidebar_visibility($raw) {
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }

    $normalized = [];
    foreach ($raw as $itemId => $visible) {
        $safeId = trim((string)$itemId);
        if ($safeId === '') {
            continue;
        }
        $normalized[$safeId] = ((string)$visible === '0' || $visible === 0 || $visible === false) ? 0 : 1;
    }

    return $normalized;
}

/**
 * Normalizes sidebar visibility mapping
 */
function itm_normalize_sidebar_visibility($raw) {
    $defaults = itm_default_sidebar_visibility();
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        $raw = [];
    }

    foreach ($defaults as $id => $defaultValue) {
        $value = $raw[$id] ?? $defaultValue;
        $defaults[$id] = ((string)$value === '0' || $value === 0 || $value === false) ? 0 : 1;
    }

    return $defaults;
}

/**
 * Normalizes the order of top-level sidebar sections
 */
function itm_normalize_sidebar_main_order($raw) {
    $default = itm_default_sidebar_main_order();
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return $default;
    }

    $seen = [];
    $order = [];
    foreach ($raw as $id) {
        $id = (string)$id;
        if (in_array($id, $default, true) && !isset($seen[$id])) {
            $order[] = $id;
            $seen[$id] = true;
        }
    }
    // Append missing default items at the end
    foreach ($default as $id) {
        if (!isset($seen[$id])) {
            $order[] = $id;
        }
    }
    return $order;
}

/**
 * Normalizes the order of submenu items within their sections
 */
function itm_normalize_sidebar_submenu_order($raw) {
    $default = itm_default_sidebar_submenu_order();
    $catalog = itm_sidebar_item_catalog();
    $defaultParent = itm_sidebar_default_item_parent_map();

    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return $default;
    }

    $normalized = [];
    $assigned = [];

    // Process explicitly ordered items
    foreach ($default as $sectionId => $defaultIds) {
        $sectionRaw = $raw[$sectionId] ?? [];
        if (!is_array($sectionRaw)) {
            $sectionRaw = [];
        }

        $sectionOrder = [];
        foreach ($sectionRaw as $id) {
            $id = (string)$id;
            if (isset($catalog[$id]) && !isset($assigned[$id])) {
                $sectionOrder[] = $id;
                $assigned[$id] = true;
            }
        }

        $normalized[$sectionId] = $sectionOrder;
    }

    // Assign un-ordered items to their default sections
    foreach (array_keys($catalog) as $itemId) {
        if (isset($assigned[$itemId])) {
            continue;
        }
        $targetSection = $defaultParent[$itemId] ?? array_key_first($default);
        if ($targetSection === null || !isset($normalized[$targetSection])) {
            $targetSection = array_key_first($normalized);
        }
        if ($targetSection === null) {
            continue;
        }
        $normalized[$targetSection][] = $itemId;
        $assigned[$itemId] = true;
    }

    return $normalized;
}

/**
 * Saves UI configuration to the database
 */
function itm_save_ui_configuration($conn, $company_id, $input) {
    $company_id = (int)$company_id;
    if ($company_id <= 0 || !itm_ensure_ui_configuration_table($conn)) {
        return false;
    }

    $config = itm_normalize_ui_configuration($input);

    $sql = 'INSERT INTO ui_configuration (company_id, table_actions_position, new_button_position, export_buttons_position, back_save_position, enable_all_error_reporting, enable_audit_logs, records_per_page, equipment_type_sidebar_visibility, sidebar_visibility, sidebar_main_order, sidebar_submenu_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                table_actions_position = VALUES(table_actions_position),
                new_button_position = VALUES(new_button_position),
                export_buttons_position = VALUES(export_buttons_position),
                back_save_position = VALUES(back_save_position),
                enable_all_error_reporting = VALUES(enable_all_error_reporting),
                enable_audit_logs = VALUES(enable_audit_logs),
                records_per_page = VALUES(records_per_page),
                equipment_type_sidebar_visibility = VALUES(equipment_type_sidebar_visibility),
                sidebar_visibility = VALUES(sidebar_visibility),
                sidebar_main_order = VALUES(sidebar_main_order),
                sidebar_submenu_order = VALUES(sidebar_submenu_order)';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    $sidebarVisibility = json_encode($config['sidebar_visibility']);
    $sidebarMainOrder = json_encode($config['sidebar_main_order']);
    $sidebarSubmenuOrder = json_encode($config['sidebar_submenu_order']);
    $equipmentTypeSidebarVisibility = json_encode($config['equipment_type_sidebar_visibility']);

    mysqli_stmt_bind_param(
        $stmt,
        'issssiisssss',
        $company_id,
        $config['table_actions_position'],
        $config['new_button_position'],
        $config['export_buttons_position'],
        $config['back_save_position'],
        $config['enable_all_error_reporting'],
        $config['enable_audit_logs'],
        $config['records_per_page'],
        $equipmentTypeSidebarVisibility,
        $sidebarVisibility,
        $sidebarMainOrder,
        $sidebarSubmenuOrder
    );

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        return false;
    }

    return itm_save_sidebar_layout($conn, $company_id, $config);
}

/**
 * Retrieves granular sidebar layout configuration
 */
function itm_get_sidebar_layout_config($conn, $company_id) {
    $company_id = (int)$company_id;
    if ($company_id <= 0 || !itm_ensure_sidebar_layout_table($conn)) {
        return null;
    }

    $sql = 'SELECT entry_type, entry_id, section_id, display_order, is_visible
            FROM sidebar_layout
            WHERE company_id = ?
            ORDER BY entry_type ASC, display_order ASC, id ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);

    if (!$rows) {
        return null;
    }

    $visibility = [];
    $mainOrder = [];
    $submenuOrder = [];

    // Reconstruct hierarchical configuration from flat database rows
    foreach ($rows as $row) {
        $entryType = (string)($row['entry_type'] ?? '');
        $entryId = (string)($row['entry_id'] ?? '');
        $sectionId = (string)($row['section_id'] ?? '');
        $visible = ((int)($row['is_visible'] ?? 1) === 0) ? 0 : 1;

        if ($entryId === '') {
            continue;
        }

        $visibility[$entryId] = $visible;
        if ($entryType === 'section') {
            $mainOrder[] = $entryId;
            if (!isset($submenuOrder[$entryId])) {
                $submenuOrder[$entryId] = [];
            }
            continue;
        }

        if (!isset($submenuOrder[$sectionId])) {
            $submenuOrder[$sectionId] = [];
        }
        $submenuOrder[$sectionId][] = $entryId;
    }

    return [
        'sidebar_visibility' => itm_normalize_sidebar_visibility($visibility),
        'sidebar_main_order' => itm_normalize_sidebar_main_order($mainOrder),
        'sidebar_submenu_order' => itm_normalize_sidebar_submenu_order($submenuOrder),
    ];
}

/**
 * Transforms configuration structure into flat database rows for storage
 */
function itm_sidebar_layout_rows_from_config($config) {
    $sidebarStructure = itm_sidebar_structure();
    $defaultParentMap = itm_sidebar_default_item_parent_map();
    $catalog = itm_sidebar_item_catalog();
    $rows = [];
    $order = 0;

    // Build section rows
    foreach ($config['sidebar_main_order'] as $sectionId) {
        $rows[] = [
            'entry_type' => 'section',
            'entry_id' => $sectionId,
            'section_id' => null,
            'display_order' => $order++,
            'is_visible' => ($config['sidebar_visibility'][$sectionId] ?? 1) === 0 ? 0 : 1,
        ];
    }

    // Build item rows
    foreach ($config['sidebar_submenu_order'] as $sectionId => $items) {
        if (!is_array($items)) {
            continue;
        }
        $itemOrder = 0;
        foreach ($items as $itemId) {
            if (!isset($catalog[$itemId])) {
                continue;
            }
            $rows[] = [
                'entry_type' => 'item',
                'entry_id' => $itemId,
                'section_id' => $sectionId,
                'display_order' => $itemOrder++,
                'is_visible' => ($config['sidebar_visibility'][$itemId] ?? 1) === 0 ? 0 : 1,
            ];
        }
    }

    // Ensure all known sections and items are included, even if not in the config
    $knownRows = [];
    foreach ($rows as $row) {
        $knownRows[$row['entry_type'] . ':' . $row['entry_id']] = true;
    }

    foreach ($sidebarStructure as $section) {
        if (!isset($knownRows['section:' . $section['id']])) {
            $rows[] = [
                'entry_type' => 'section',
                'entry_id' => $section['id'],
                'section_id' => null,
                'display_order' => $order++,
                'is_visible' => 1,
            ];
        }
    }

    foreach (array_keys($catalog) as $itemId) {
        if (isset($knownRows['item:' . $itemId])) {
            continue;
        }
        $parent = $defaultParentMap[$itemId] ?? '';
        $sectionItems = $config['sidebar_submenu_order'][$parent] ?? [];
        $rows[] = [
            'entry_type' => 'item',
            'entry_id' => $itemId,
            'section_id' => $parent,
            'display_order' => is_array($sectionItems) ? count($sectionItems) : 0,
            'is_visible' => ($config['sidebar_visibility'][$itemId] ?? 1) === 0 ? 0 : 1,
        ];
    }

    return $rows;
}

/**
 * Synchronizes the sidebar_layout table with the provided configuration
 */
function itm_save_sidebar_layout($conn, $company_id, $config) {
    $company_id = (int)$company_id;
    if ($company_id <= 0 || !itm_ensure_sidebar_layout_table($conn)) {
        return false;
    }

    $rows = itm_sidebar_layout_rows_from_config($config);

    mysqli_begin_transaction($conn);
    // Clear old layout
    $deleteStmt = mysqli_prepare($conn, 'DELETE FROM sidebar_layout WHERE company_id = ?');
    if (!$deleteStmt) {
        mysqli_rollback($conn);
        return false;
    }
    mysqli_stmt_bind_param($deleteStmt, 'i', $company_id);
    $deleteOk = mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);
    if (!$deleteOk) {
        mysqli_rollback($conn);
        return false;
    }

    // Insert new layout row by row
    $insertStmt = mysqli_prepare(
        $conn,
        'INSERT INTO sidebar_layout (company_id, entry_type, entry_id, section_id, display_order, is_visible) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$insertStmt) {
        mysqli_rollback($conn);
        return false;
    }

    foreach ($rows as $row) {
        $entryType = (string)$row['entry_type'];
        $entryId = (string)$row['entry_id'];
        $sectionId = isset($row['section_id']) ? (string)$row['section_id'] : null;
        $displayOrder = (int)$row['display_order'];
        $isVisible = (int)$row['is_visible'];
        mysqli_stmt_bind_param($insertStmt, 'isssii', $company_id, $entryType, $entryId, $sectionId, $displayOrder, $isVisible);
        if (!mysqli_stmt_execute($insertStmt)) {
            mysqli_stmt_close($insertStmt);
            mysqli_rollback($conn);
            return false;
        }
    }

    mysqli_stmt_close($insertStmt);
    return mysqli_commit($conn);
}

/**
 * Resolves the final integer value for records per page
 */
function itm_resolve_records_per_page($uiConfig) {
    $raw = strtolower((string)($uiConfig['records_per_page'] ?? '25'));
    if ($raw === 'all') {
        return 1000000; // Effectively "all" for most use cases
    }

    if (ctype_digit($raw)) {
        $value = (int)$raw;
        if ($value > 0 && $value <= 1000000) {
            return $value;
        }
    }

    return 25; // Standard fallback
}
