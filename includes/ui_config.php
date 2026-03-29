<?php

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
                ['id' => 'workstations', 'label' => '💻 Workstations', 'href' => 'modules/workstations/', 'match_dir' => 'workstations'],
                ['id' => 'switch_ports', 'label' => '🔌 Switch Ports', 'href' => 'modules/switch_ports/', 'match_dir' => 'switch_ports'],
                ['id' => 'tickets', 'label' => '🎫 Tickets', 'href' => 'modules/tickets/', 'match_dir' => 'tickets'],
            ],
        ],
        [
            'id' => 'employee',
            'title' => '👤 Employee',
            'items' => [
                ['id' => 'employees', 'label' => '👤 Employees', 'href' => 'modules/employees/', 'match_dir' => 'employees'],
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
                ['id' => 'rack_statuses', 'label' => '📶 Rack Statuses', 'href' => 'modules/rack_statuses/', 'match_dir' => 'rack_statuses'],
                ['id' => 'switch_status', 'label' => '📡 Switch Status', 'href' => 'modules/switch_status/', 'match_dir' => 'switch_status'],
                ['id' => 'switch_cablecolors', 'label' => '🎨 Switch Cable Colors', 'href' => 'modules/switch_cablecolors/', 'match_dir' => 'switch_cablecolors'],
                ['id' => 'ticket_categories', 'label' => '🏷️ Ticket Categories', 'href' => 'modules/ticket_categories/', 'match_dir' => 'ticket_categories'],
                ['id' => 'ticket_statuses', 'label' => '🚦 Ticket Statuses', 'href' => 'modules/ticket_statuses/', 'match_dir' => 'ticket_statuses'],
                ['id' => 'ticket_priorities', 'label' => '🔥 Ticket Priorities', 'href' => 'modules/ticket_priorities/', 'match_dir' => 'ticket_priorities'],
                ['id' => 'employee_statuses', 'label' => '🧑‍💼 Employee Statuses', 'href' => 'modules/employee_statuses/', 'match_dir' => 'employee_statuses'],
            ],
        ],
    ];
}

function itm_sidebar_humanize_table_name($tableName) {
    $label = str_replace('_', ' ', (string)$tableName);
    $label = trim($label);
    if ($label === '') {
        return '';
    }

    return ucwords($label);
}

function itm_sidebar_structure($conn = null) {
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

    if ($conn) {
        $tablesRes = mysqli_query($conn, 'SHOW TABLES');
        if ($tablesRes) {
            while ($tableRow = mysqli_fetch_array($tablesRes)) {
                $table = isset($tableRow[0]) ? (string)$tableRow[0] : '';
                if ($table === '' || isset($existingItemIds[$table])) {
                    continue;
                }

                $moduleIndex = $modulesRoot . '/' . $table . '/index.php';
                if (is_file($moduleIndex)) {
                    $moduleNames[$table] = true;
                }
            }
        }
    }

    $discoveredItems = [];
    foreach (array_keys($moduleNames) as $moduleName) {
        $discoveredItems[] = [
            'id' => $moduleName,
            'label' => '🧩 ' . itm_sidebar_humanize_table_name($moduleName),
            'href' => 'modules/' . $moduleName . '/',
            'match_dir' => $moduleName,
        ];
    }

    if (!$discoveredItems) {
        return $structure;
    }

    usort($discoveredItems, static function ($a, $b) {
        return strcmp($a['label'], $b['label']);
    });

    foreach ($structure as &$section) {
        if (($section['id'] ?? '') === 'reference_data') {
            $section['items'] = array_merge($section['items'], $discoveredItems);
            break;
        }
    }
    unset($section);

    return $structure;
}

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

function itm_default_sidebar_main_order() {
    return array_map(static function ($section) {
        return $section['id'];
    }, itm_sidebar_structure());
}

function itm_default_sidebar_submenu_order() {
    $submenuOrder = [];
    foreach (itm_sidebar_structure() as $section) {
        $submenuOrder[$section['id']] = array_map(static function ($item) {
            return $item['id'];
        }, $section['items']);
    }
    return $submenuOrder;
}

function itm_sidebar_item_catalog() {
    $catalog = [];
    foreach (itm_sidebar_structure() as $section) {
        foreach ($section['items'] as $item) {
            $catalog[$item['id']] = $item;
        }
    }
    return $catalog;
}

function itm_sidebar_default_item_parent_map() {
    $map = [];
    foreach (itm_sidebar_structure() as $section) {
        foreach ($section['items'] as $item) {
            $map[$item['id']] = $section['id'];
        }
    }
    return $map;
}

function itm_ui_config_defaults() {
    return [
        'table_actions_position' => 'left_right',
        'new_button_position' => 'left_right',
        'export_buttons_position' => 'left_right',
        'back_save_position' => 'left_right',
        'sidebar_visibility' => itm_default_sidebar_visibility(),
        'sidebar_main_order' => itm_default_sidebar_main_order(),
        'sidebar_submenu_order' => itm_default_sidebar_submenu_order(),
    ];
}

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

function itm_ensure_ui_configuration_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `ui_configuration` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `table_actions_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `new_button_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `export_buttons_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `back_save_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
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

    $columns = [
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

function itm_get_ui_configuration($conn, $company_id) {
    $defaults = itm_ui_config_defaults();
    $company_id = (int)$company_id;

    if ($company_id <= 0 || !itm_ensure_ui_configuration_table($conn)) {
        return $defaults;
    }

    $sql = 'SELECT table_actions_position, new_button_position, export_buttons_position, back_save_position, sidebar_visibility, sidebar_main_order, sidebar_submenu_order FROM ui_configuration WHERE company_id = ? LIMIT 1';
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
        $layoutConfig = itm_get_sidebar_layout_config($conn, $company_id);
        if ($layoutConfig !== null) {
            $defaults['sidebar_visibility'] = $layoutConfig['sidebar_visibility'];
            $defaults['sidebar_main_order'] = $layoutConfig['sidebar_main_order'];
            $defaults['sidebar_submenu_order'] = $layoutConfig['sidebar_submenu_order'];
        }
        return $defaults;
    }

    $config = itm_normalize_ui_configuration($row);
    $layoutConfig = itm_get_sidebar_layout_config($conn, $company_id);
    if ($layoutConfig !== null) {
        $config['sidebar_visibility'] = $layoutConfig['sidebar_visibility'];
        $config['sidebar_main_order'] = $layoutConfig['sidebar_main_order'];
        $config['sidebar_submenu_order'] = $layoutConfig['sidebar_submenu_order'];
    }

    return $config;
}

function itm_normalize_ui_configuration($values) {
    $defaults = itm_ui_config_defaults();
    $allowed = itm_ui_allowed_positions();

    foreach ($allowed as $key => $options) {
        $value = isset($values[$key]) ? (string)$values[$key] : $defaults[$key];
        $values[$key] = in_array($value, $options, true) ? $value : $defaults[$key];
    }

    $values['sidebar_visibility'] = itm_normalize_sidebar_visibility($values['sidebar_visibility'] ?? null);
    $values['sidebar_main_order'] = itm_normalize_sidebar_main_order($values['sidebar_main_order'] ?? null);
    $values['sidebar_submenu_order'] = itm_normalize_sidebar_submenu_order($values['sidebar_submenu_order'] ?? null);

    return $values;
}

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
    foreach ($default as $id) {
        if (!isset($seen[$id])) {
            $order[] = $id;
        }
    }
    return $order;
}

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

function itm_save_ui_configuration($conn, $company_id, $input) {
    $company_id = (int)$company_id;
    if ($company_id <= 0 || !itm_ensure_ui_configuration_table($conn)) {
        return false;
    }

    $config = itm_normalize_ui_configuration($input);

    $sql = 'INSERT INTO ui_configuration (company_id, table_actions_position, new_button_position, export_buttons_position, back_save_position, sidebar_visibility, sidebar_main_order, sidebar_submenu_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                table_actions_position = VALUES(table_actions_position),
                new_button_position = VALUES(new_button_position),
                export_buttons_position = VALUES(export_buttons_position),
                back_save_position = VALUES(back_save_position),
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

    mysqli_stmt_bind_param(
        $stmt,
        'isssssss',
        $company_id,
        $config['table_actions_position'],
        $config['new_button_position'],
        $config['export_buttons_position'],
        $config['back_save_position'],
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

function itm_sidebar_layout_rows_from_config($config) {
    $sidebarStructure = itm_sidebar_structure();
    $defaultParentMap = itm_sidebar_default_item_parent_map();
    $catalog = itm_sidebar_item_catalog();
    $rows = [];
    $order = 0;

    foreach ($config['sidebar_main_order'] as $sectionId) {
        $rows[] = [
            'entry_type' => 'section',
            'entry_id' => $sectionId,
            'section_id' => null,
            'display_order' => $order++,
            'is_visible' => ($config['sidebar_visibility'][$sectionId] ?? 1) === 0 ? 0 : 1,
        ];
    }

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

function itm_save_sidebar_layout($conn, $company_id, $config) {
    $company_id = (int)$company_id;
    if ($company_id <= 0 || !itm_ensure_sidebar_layout_table($conn)) {
        return false;
    }

    $rows = itm_sidebar_layout_rows_from_config($config);

    mysqli_begin_transaction($conn);
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
