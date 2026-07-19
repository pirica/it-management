<?php
/**
 * UI Configuration and Sidebar Management Functions
 *
 * Handles the application's dynamic UI settings, including sidebar structure,
 * custom module discovery, auto-scaffolding, and user preferences for
 * display positions and visibility.
 */

require_once dirname(__DIR__) . '/includes/bootstrap_helpers.php';

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
                ['id' => 'reports', 'label' => '📊 Reports Hub', 'href' => 'modules/reports/', 'match_dir' => 'reports'],
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
                ['id' => 'tickets', 'label' => '🎟️ Tickets', 'href' => 'modules/tickets/', 'match_dir' => 'tickets'],
                ['id' => 'license_management', 'label' => '📄 License Management', 'href' => 'modules/license_management/', 'match_dir' => 'license_management'],
            ],
        ],
        [
            'id' => 'ipam',
            'title' => '🌐 IPAM',
            'items' => [
                ['id' => 'vlans', 'label' => '🏷️ VLANs', 'href' => 'modules/vlans/', 'match_dir' => 'vlans'],
                ['id' => 'ip_subnets', 'label' => '🧭 IP Subnets', 'href' => 'modules/ip_subnets/', 'match_dir' => 'ip_subnets'],
                ['id' => 'ip_addresses', 'label' => '📍 IP Addresses', 'href' => 'modules/ip_addresses/', 'match_dir' => 'ip_addresses'],
            ],
        ],
        [
            'id' => 'employee',
            'title' => '👤 Employee',
            'items' => [
                ['id' => 'passwords', 'label' => '🔑 Passwords', 'href' => 'modules/passwords/', 'match_dir' => 'passwords'],
                ['id' => 'contacts', 'label' => '📓 Contacts', 'href' => 'modules/contacts/', 'match_dir' => 'contacts'],
                ['id' => 'employees', 'label' => '👤 Employees', 'href' => 'modules/employees/', 'match_dir' => 'employees'],
                ['id' => 'birthdays', 'label' => '🎉 Birthdays', 'href' => 'modules/birthdays/', 'match_dir' => 'birthdays'],
                ['id' => 'resignations', 'label' => '📋 Resignations', 'href' => 'modules/resignations/', 'match_dir' => 'resignations'],
                ['id' => 'employee_system_access', 'label' => '🔐 Employee System Access', 'href' => 'modules/employee_system_access/', 'match_dir' => 'employee_system_access'],
                ['id' => 'system_access', 'label' => '🛡️ System Access', 'href' => 'modules/system_access/', 'match_dir' => 'system_access'],
                ['id' => 'private_contacts', 'label' => '👤 Private Contacts', 'href' => 'modules/private_contacts/', 'match_dir' => 'private_contacts'],
                ['id' => 'bookmarks', 'label' => '🔗 Bookmarks', 'href' => 'modules/bookmarks/', 'match_dir' => 'bookmarks'],
                ['id' => 'departments', 'label' => '🏢 Departments', 'href' => 'modules/departments/', 'match_dir' => 'departments'],
                ['id' => 'employee_assignment_history', 'label' => '📝 Assignment History', 'href' => 'modules/employee_assignment_history/', 'match_dir' => 'employee_assignment_history'],
                ['id' => 'explorer', 'label' => '🌐 Explorer', 'href' => 'modules/explorer/', 'match_dir' => 'explorer'],
                ['id' => 'request_password', 'label' => '🔑 Request Password', 'href' => 'modules/request_password/', 'match_dir' => 'request_password'],
            ],
        ],
        [
            'id' => 'budgeting',
            'title' => '💰 Budgeting',
            'items' => [
                ['id' => 'budget_categories', 'label' => '📚 Budget Categories', 'href' => 'modules/budget_categories/', 'match_dir' => 'budget_categories'],
                ['id' => 'cost_centers', 'label' => '🧾 Cost Centers', 'href' => 'modules/cost_centers/', 'match_dir' => 'cost_centers'],
                ['id' => 'gl_accounts', 'label' => '📚 GL Accounts', 'href' => 'modules/gl_accounts/', 'match_dir' => 'gl_accounts'],
                ['id' => 'annual_budgets', 'label' => '📅 Annual Budget', 'href' => 'modules/annual_budgets/', 'match_dir' => 'annual_budgets'],
                ['id' => 'monthly_budgets', 'label' => '📆 Monthly Budget', 'href' => 'modules/monthly_budgets/', 'match_dir' => 'monthly_budgets'],
                ['id' => 'forecast_revisions', 'label' => '📈 Forecast Revisions', 'href' => 'modules/forecast_revisions/', 'match_dir' => 'forecast_revisions'],
                ['id' => 'forecast_revisions_status', 'label' => '📊 Forecast Revisions Status', 'href' => 'modules/forecast_revisions_status/', 'match_dir' => 'forecast_revisions_status'],
                ['id' => 'approvals', 'label' => '✅ Forecast Approvals', 'href' => 'modules/approvals/', 'match_dir' => 'approvals'],
                ['id' => 'approvals_stage', 'label' => '🪜 Approval Stages', 'href' => 'modules/approvals_stage/', 'match_dir' => 'approvals_stage'],
                ['id' => 'expenses', 'label' => '💸 Expenses', 'href' => 'modules/expenses/', 'match_dir' => 'expenses'],
                ['id' => 'budget_report', 'label' => '📑 Budget Report', 'href' => 'modules/budget_report/', 'match_dir' => 'budget_report'],
            ],
        ],
        [
            'id' => 'planning',
            'title' => '🗓️ Planning',
            'items' => [
                ['id' => 'notes', 'label' => '📋 Notes', 'href' => 'modules/notes/', 'match_dir' => 'notes'],
                ['id' => 'todo', 'label' => '📝 To-Do', 'href' => 'modules/todo/', 'match_dir' => 'todo'],
                ['id' => 'calendar', 'label' => '📅 Calendar', 'href' => 'modules/calendar/', 'match_dir' => 'calendar'],
                ['id' => 'events', 'label' => '📅 Events', 'href' => 'modules/events/', 'match_dir' => 'events'],
                ['id' => 'alerts', 'label' => '📢 Alerts', 'href' => 'modules/alerts/', 'match_dir' => 'alerts'],
                ['id' => 'event_categories', 'label' => '🏷️ Event Categories', 'href' => 'modules/event_categories/', 'match_dir' => 'event_categories'],
                ['id' => 'patches_updates', 'label' => '🛠️ Patches Updates', 'href' => 'modules/patches_updates/', 'match_dir' => 'patches_updates'],
                ['id' => 'knowledge_base', 'label' => '🧩 Knowledge Base', 'href' => 'modules/knowledge_base/', 'match_dir' => 'knowledge_base'],
            ],
        ],
        [
            'id' => 'admin',
            'title' => '🧰 Admin',
            'items' => [
                ['id' => 'inventory_items', 'label' => '📦 Inventory', 'href' => 'modules/inventory_items/', 'match_dir' => 'inventory_items'],
                ['id' => 'visitors_access_log', 'label' => '📝 Visitors Access Log', 'href' => 'modules/visitors_access_log/', 'match_dir' => 'visitors_access_log'],
                ['id' => 'backup_tape_log', 'label' => '📼 Backup Tape Log', 'href' => 'modules/backup_tape_log/', 'match_dir' => 'backup_tape_log'],
                ['id' => 'companies', 'label' => '🌍 Companies', 'href' => 'modules/companies/', 'match_dir' => 'companies'],
                ['id' => 'company_module_access', 'label' => '🧩 Company Module Access', 'href' => 'modules/company_module_access/', 'match_dir' => 'company_module_access'],
                ['id' => 'roles_permissions', 'label' => '🛡️ Roles & Permissions', 'href' => 'modules/roles_permissions/', 'match_dir' => 'roles_permissions'],
                ['id' => 'emails', 'label' => '📧 Email Management', 'href' => 'modules/emails/', 'match_dir' => 'emails'],
                ['id' => 'import', 'label' => '📥 Bulk Import', 'href' => 'modules/import/', 'match_dir' => 'import'],
                ['id' => 'ops_report', 'label' => '📋 Ops Report', 'href' => 'modules/ops_report/', 'match_dir' => 'ops_report'],
            ],
        ],
        [
            'id' => 'reference_data',
            'title' => '🗂️ Reference Data',
            'items' => [
                ['id' => 'it_locations', 'label' => '📍 IT Locations', 'href' => 'modules/it_locations/', 'match_dir' => 'it_locations'],
                ['id' => 'floor_plans', 'label' => '🗺️ Floor Plans', 'href' => 'modules/floor_plans/', 'match_dir' => 'floor_plans'],
                ['id' => 'floor_designer', 'label' => '🎨 Floor Designer', 'href' => 'modules/floor_designer/', 'match_dir' => 'floor_designer'],
                ['id' => 'location_types', 'label' => '🧭 Location Types', 'href' => 'modules/location_types/', 'match_dir' => 'location_types'],
                ['id' => 'equipment_types', 'label' => '🖥️ Equipment Types', 'href' => 'modules/equipment_types/', 'match_dir' => 'equipment_types'],
                ['id' => 'equipment_statuses', 'label' => '✅ Equipment Statuses', 'href' => 'modules/equipment_statuses/', 'match_dir' => 'equipment_statuses'],
                ['id' => 'manufacturers', 'label' => '🏭 Manufacturers', 'href' => 'modules/manufacturers/', 'match_dir' => 'manufacturers'],
                ['id' => 'catalogs', 'label' => '🗃️ Catalogs', 'href' => 'modules/catalogs/', 'match_dir' => 'catalogs'],
                ['id' => 'suppliers', 'label' => '🚚 Suppliers', 'href' => 'modules/suppliers/', 'match_dir' => 'suppliers'],
                ['id' => 'supplier_statuses', 'label' => '🟢 Supplier Statuses', 'href' => 'modules/supplier_statuses/', 'match_dir' => 'supplier_statuses'],
                ['id' => 'racks', 'label' => '🗄️ Racks', 'href' => 'modules/racks/', 'match_dir' => 'racks'],
                ['id' => 'idfs', 'label' => '🗄️ IDFs', 'href' => 'modules/idfs/', 'match_dir' => 'idfs'],
                ['id' => 'rack_planner', 'label' => '🗄️ Rack Planner', 'href' => 'modules/rack_planner/', 'match_dir' => 'rack_planner'],
                ['id' => 'rack_statuses', 'label' => '📶 Rack Statuses', 'href' => 'modules/rack_statuses/', 'match_dir' => 'rack_statuses'],
                ['id' => 'switch_ports', 'label' => '🖧 Switch Ports', 'href' => 'modules/switch_ports/', 'match_dir' => 'switch_ports'],
                ['id' => 'switch_status', 'label' => '📡 Switch Status', 'href' => 'modules/switch_status/', 'match_dir' => 'switch_status'],
                ['id' => 'cable_colors', 'label' => '🎨 Cable Colors', 'href' => 'modules/cable_colors/', 'match_dir' => 'cable_colors'],
                ['id' => 'ticket_categories', 'label' => '🏷️ Ticket Categories', 'href' => 'modules/ticket_categories/', 'match_dir' => 'ticket_categories'],
                ['id' => 'ticket_statuses', 'label' => '🚦 Ticket Statuses', 'href' => 'modules/ticket_statuses/', 'match_dir' => 'ticket_statuses'],
                ['id' => 'ticket_priorities', 'label' => '🔥 Ticket Priorities', 'href' => 'modules/ticket_priorities/', 'match_dir' => 'ticket_priorities'],
                ['id' => 'employee_statuses', 'label' => '🧑‍💼 Employee Statuses', 'href' => 'modules/employee_statuses/', 'match_dir' => 'employee_statuses'],
                ['id' => 'employee_positions', 'label' => '🪪 Positions Titles', 'href' => 'modules/employee_positions/', 'match_dir' => 'employee_positions'],
                ['id' => 'approver_type', 'label' => '🧩 Approver Type', 'href' => 'modules/approver_type/', 'match_dir' => 'approver_type'],
                ['id' => 'approvers', 'label' => '✅ Approvers', 'href' => 'modules/approvers/', 'match_dir' => 'approvers'],
                ['id' => 'audit_logs', 'label' => '🧾 Audit Logs', 'href' => 'modules/audit_logs/', 'match_dir' => 'audit_logs'],
                ['id' => 'org_chart', 'label' => '📈 Org Chart', 'href' => 'modules/org_chart/', 'match_dir' => 'org_chart'],
                ['id' => 'it_settings', 'label' => '⚙️ IT Settings', 'href' => 'modules/it_settings/', 'match_dir' => 'it_settings'],
            ],
        ],
    ];
}


/**
 * PHP 7.2-compatible replacement for array_key_first.
 */
function itm_array_key_first($array) {
    if (!is_array($array) || empty($array)) {
        return null;
    }

    foreach ($array as $key => $_value) {
        return $key;
    }

    return null;
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
 * Canonical sidebar labels (with emoji) for modules that should not use generic discovery text.
 */
function itm_sidebar_module_default_label($moduleName) {
    $moduleName = trim((string)$moduleName);
    $labels = [
        'notes' => '📋 Notes',
        'passwords' => '🔑 Passwords',
        'inventory_items' => '📦 Inventory',
        'patches_updates' => '🛠️ Patches Updates',
        'expiring' => '⏳ Expiring',
        'warranty_types' => '🛡️ Warranty Types',
        'note_labels' => '🏷️ Note Labels',
        'modules_registry' => '🧩 Modules Registry',
        'emails' => '📧 Email Management',
        'import' => '📥 Bulk Import',
        'knowledge_base' => '🧩 Knowledge Base',
        'it_settings' => '⚙️ IT Settings',
        'request_password' => '🔑 Request Password',
        'license_management' => '📄 License Management',
        'ops_report' => '📋 Ops Report',
        'reports' => '📊 Reports Hub',
    ];

    return $labels[$moduleName] ?? null;
}

/**
 * Sidebar prefix for modules auto-scaffolded (new DB table, no bespoke UI yet).
 */
function itm_sidebar_auto_scaffolded_module_emoji() {
    return '⚠️';
}

/**
 * Module folder names never removed by standard-CRUD-scaffold QA cleanup.
 *
 * @return string[]
 */
function itm_standard_crud_scaffold_preserved_module_names() {
    return [
        'manufacturers',
    ];
}

/**
 * Applies standard flattened CRUD defaults to a copied PHP file for another module table.
 */
function itm_apply_standard_crud_template_to_module_content($moduleName, $templateContent) {
    $moduleName = trim((string)$moduleName);
    if ($moduleName === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $moduleName)) {
        return (string)$templateContent;
    }

    $title = itm_sidebar_humanize_table_name($moduleName);
    $content = str_replace(["\r\n", "\r"], "\n", (string)$templateContent);

    $content = preg_replace(
        '/\$crud_table = \'manufacturers\';/',
        '$crud_table = ' . var_export($moduleName, true) . ';',
        $content
    );
    $content = preg_replace(
        '/\$crud_table = \$crud_table \?\? \'manufacturers\';/',
        '$crud_table = $crud_table ?? ' . var_export($moduleName, true) . ';',
        $content
    );
    $content = preg_replace(
        '/\$crud_title = \'Manufacturers\';/',
        '$crud_title = ' . var_export($title, true) . ';',
        $content
    );
    $content = preg_replace(
        '/\$crud_title = \$crud_title \?\? \'Manufacturers\';/',
        '$crud_title = $crud_title ?? ' . var_export($title, true) . ';',
        $content
    );

    return $content;
}

/**
 * Copies standard CRUD PHP files into modules/{name}/ with table/title substitutions.
 */
function itm_materialize_standard_crud_module_files($moduleName, $overwriteExisting = false) {
    $moduleName = trim((string)$moduleName);
    if ($moduleName === '' || $moduleName === 'manufacturers' || !preg_match('/^[a-zA-Z0-9_]+$/', $moduleName)) {
        return false;
    }
    if (itm_sidebar_module_is_hidden($moduleName)) {
        return false;
    }

    $modulesRoot = dirname(__DIR__) . '/modules';
    $templateDir = $modulesRoot . '/manufacturers';
    $moduleDir = $modulesRoot . '/' . $moduleName;
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
        if (is_file($targetPath) && !$overwriteExisting) {
            continue;
        }

        $templateContent = @file_get_contents($templatePath);
        if ($templateContent === false) {
            return false;
        }

        $moduleContent = itm_apply_standard_crud_template_to_module_content($moduleName, $templateContent);
        if (itm_module_php_file_delegates_to_manufacturers_module($moduleContent)) {
            return false;
        }

        if (file_put_contents($targetPath, $moduleContent) === false) {
            return false;
        }
    }

    return is_file($moduleDir . '/index.php');
}

/**
 * True when a PHP file still uses the forbidden cross-module template delegate.
 */
function itm_module_php_file_delegates_to_manufacturers_module($content) {
    return strpos((string)$content, "require __DIR__ . '/../manufacturers/") !== false;
}

/**
 * True when every PHP entry file is a legacy thin delegate to standard CRUD template.
 */
function itm_module_php_file_is_standard_crud_stub($content, $fileName) {
    // Real stub files are < 500 bytes. Bail before any string copy on large
    // input to avoid OOM in the sidebar scaffold scan (#ui_config-memory).
    if (!is_string($content) || strlen($content) > 2048) {
        return false;
    }
    $content = str_replace(["\r\n", "\r"], "\n", $content);
    $content = trim($content);
    if ($content === '' || strpos($content, 'config/config.php') !== false) {
        return false;
    }

    $escapedFile = preg_quote((string)$fileName, '/');
    return (bool)preg_match(
        '/^<\?php\n\$crud_table = \'[^\']+\';\n\$crud_title = \'[^\']*\';\n\$crud_action = \'[^\']+\';\nrequire __DIR__ \. \'\/\.\.\/manufacturers\/' . $escapedFile . '\';\s*$/s',
        $content
    );
}

/**
 * Detects legacy standard-CRUD-scaffold delegate stub folders (safe to remove after QA).
 */
function itm_module_dir_is_standard_crud_scaffold($modulesRoot, $moduleDirName) {
    $moduleDirName = trim((string)$moduleDirName);
    if ($moduleDirName === '' || in_array($moduleDirName, itm_standard_crud_scaffold_preserved_module_names(), true)) {
        return false;
    }

    $modulesRoot = rtrim((string)$modulesRoot, '/\\');
    $moduleDir = $modulesRoot . '/' . $moduleDirName;
    if (!is_dir($moduleDir)) {
        return false;
    }

    $phpFiles = glob($moduleDir . '/*.php') ?: [];
    if ($phpFiles === []) {
        return false;
    }

    foreach ($phpFiles as $phpFile) {
        $fileName = basename($phpFile);
        // Skip anything that cannot possibly be a stub; prevents OOM when a
        // large non-stub file (dump, export, cache) lives in modules/*/.
        $size = @filesize($phpFile);
        if ($size === false || $size > 2048) {
            return false;
        }
        $content = @file_get_contents($phpFile, false, null, 0, 2048);
        if ($content === false || !itm_module_php_file_is_standard_crud_stub($content, $fileName)) {
            return false;
        }
    }

    return true;
}

/**
 * Removes orphan standard-CRUD-scaffold dirs (MBQA / auto-scaffold pollution).
 */
function itm_remove_standard_crud_scaffold_module_dirs($modulesRoot) {
    $modulesRoot = rtrim((string)$modulesRoot, '/\\');
    if ($modulesRoot === '' || !is_dir($modulesRoot)) {
        return 0;
    }

    $removed = 0;
    $moduleDirs = glob($modulesRoot . '/*', GLOB_ONLYDIR) ?: [];
    foreach ($moduleDirs as $moduleDir) {
        $moduleDirName = basename($moduleDir);
        if (!itm_module_dir_is_standard_crud_scaffold($modulesRoot, $moduleDirName)) {
            continue;
        }

        $files = glob($moduleDir . '/*') ?: [];
        foreach ($files as $filePath) {
            if (is_file($filePath)) {
                @unlink($filePath);
            }
        }
        if (@rmdir($moduleDir)) {
            $removed++;
        }
    }

    return $removed;
}

/**
 * Builds sidebar label text for auto-discovered modules (canonical map, scaffold emoji, or generic prefix).
 */
function itm_sidebar_build_discovered_module_label($moduleName, $moduleMeta = []) {
    $defaultLabel = itm_sidebar_module_default_label($moduleName);
    if ($defaultLabel !== null) {
        return $defaultLabel;
    }

    $humanName = itm_sidebar_humanize_table_name($moduleName);
    $emoji = trim((string)($moduleMeta['emoji'] ?? ''));
    if ($emoji === '') {
        $emoji = '🧩';
    }

    return trim($emoji . ' ' . $humanName);
}

/**
 * Returns a default emoji for known equipment type names.
 */
function itm_equipment_type_default_emoji($typeName) {
    $normalized = strtolower(trim((string)$typeName));
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
    $normalized = trim((string)$normalized, '_');

    $map = [
        'access_point' => '📶',
        'cctv' => '🎥',
        'firewall' => '🔥',
        'phone' => '📞',
        'port_patch_panel' => '➰',
        'pos' => '🏧',
        'printer' => '🖨️',
        'router' => '🛜',
        'server' => '🖥️',
        'switch' => '🔀',
        'workstation' => '💻',
    ];

    return $map[$normalized] ?? '';
}

/**
 * Maps known equipment type names to equipment flag fields.
 */
function itm_equipment_type_flag_field($typeName) {
    return '';
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
    $searchPlaceholder = $searchPlaceholderMap[$moduleName] ?? 'Use SQL wildcards, e.g. %%asset%%';

    $modulesRoot = dirname(__DIR__) . '/modules';
    $moduleDir = $modulesRoot . '/' . $moduleName;
    if (!is_dir($moduleDir) && !mkdir($moduleDir, 0775, true) && !is_dir($moduleDir)) {
        return false;
    }
    $fileContents = [];

    $indexContent = "<?php\n";
    $indexContent .= '$equipmentModuleTitle = ' . var_export($moduleTitle, true) . ";\n";
    $indexContent .= '$equipmentFlagField = \'\';' . "\n";
    $indexContent .= '$equipmentTypeNameFilter = ' . var_export($displayTypeName, true) . ";\n";
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
    $viewContent .= '$equipmentRequiredFlagField = \'\';' . "\n";
    $viewContent .= '$equipmentTypeNameFilter = ' . var_export($displayTypeName, true) . ";\n";
    $viewContent .= '$equipmentViewBackPath = ' . var_export('index.php', true) . ";\n";
    $viewContent .= '$equipmentViewEditPath = ' . var_export('edit.php', true) . ";\n";
    $viewContent .= "require '../equipment/view.php';\n";
    $fileContents['view.php'] = $viewContent;

    $fileContents['edit.php'] = "<?php\nrequire '../equipment/edit.php';\n";
    $fileContents['create.php'] = "<?php\nrequire '../equipment/create.php';\n";
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
 * Automatically creates a new module directory and CRUD files.
 *
 * Copies standard flattened CRUD into modules/{table}/ (no cross-module require).
 * Sidebar labels for these modules use itm_sidebar_auto_scaffolded_module_emoji() (⚠️).
 */
function itm_auto_create_module_scaffold($moduleName) {
    return itm_materialize_standard_crud_module_files($moduleName, false);
}

/**
 * Module IDs excluded from auto-discovery and the sidebar (managed inside other modules).
 */
function itm_sidebar_excluded_module_ids() {
    return [
        'floor_plan_folders',
        'floor_plan_item_tags',
        'floor_plan_tags',
        'password_folders',
        'password_entries',
    ];
}

function itm_sidebar_module_is_hidden($moduleName) {
    $moduleName = trim((string)$moduleName);
    if ($moduleName === '') {
        return false;
    }
    if (in_array($moduleName, itm_sidebar_excluded_module_ids(), true)) {
        return true;
    }

    // Why: QR share session tables are internal to parent modules (notes, passwords, bookmarks, todo, events).
    return (bool)preg_match('/_share_sessions$/', $moduleName);
}

/**
 * Returns the complete sidebar structure, including auto-discovered modules
 */
function itm_sidebar_structure($conn = null, $forceRefresh = false) {
    // Static cache to avoid redundant filesystem and DB scans in a single request
    static $itm_sidebar_cache = null;
    if ($forceRefresh) {
        $itm_sidebar_cache = null;
    }
    if ($itm_sidebar_cache !== null) {
        return $itm_sidebar_cache;
    }

    static $is_building = false;
    if ($is_building) {
        return itm_sidebar_base_structure();
    }
    $is_building = true;

    $structure = itm_sidebar_base_structure();
    $existingItemIds = [];
    foreach ($structure as $section) {
        foreach (($section['items'] ?? []) as $item) {
            $existingItemIds[$item['id']] = true;
        }
    }

    if ($conn === null && isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
        $conn = $GLOBALS['conn'];
    }

    $moduleNames = [];
    $modulesRoot = dirname(__DIR__) . '/modules';
    // Discover modules by scanning the filesystem
    if (is_dir($modulesRoot)) {
        $moduleDirs = glob($modulesRoot . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($moduleDirs as $moduleDir) {
            $moduleName = basename($moduleDir);
            if ($moduleName === '' || isset($existingItemIds[$moduleName]) || itm_sidebar_module_is_hidden($moduleName)) {
                continue;
            }

            if (is_file($moduleDir . '/index.php')) {
                $scaffoldEmoji = '';
                $moduleNames[$moduleName] = ['emoji' => $scaffoldEmoji];
                // Why: itm_module_dir_is_standard_crud_scaffold() calls file_get_contents() on every
                // .php file in the module dir. With 140+ modules this is 800+ file reads per request.
                // The scaffold emoji is cosmetic only; skip the per-file scan entirely on sidebar renders.
                // Scaffold detection is still available on-demand via scripts/check_*.php.
                $moduleNames[$moduleName] = ['emoji' => ''];
            }
        }
    }

    // Discover modules by scanning database tables and auto-scaffolding if needed.
    // Why: SHOW TABLES + per-table file writes ran on every sidebar render when auto-scaffolding
    // was unconditional, causing 512 MB+ memory exhaustion on large schemas (122+ tables).
    // Gate behind enable_auto_scaffolding (default off) so heavy scaffold I/O only runs
    // when an admin explicitly enables it in Settings → UI Configuration.
    $autoScaffoldingEnabled = false;
    if ($conn instanceof mysqli && isset($GLOBALS['ui_config']) && is_array($GLOBALS['ui_config'])) {
        $autoScaffoldingEnabled = !empty($GLOBALS['ui_config']['enable_auto_scaffolding']);
    }
    if ($conn instanceof mysqli) {
        $itmCompanyId = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;
        $itmUiConfig = itm_get_ui_configuration($conn, $itmCompanyId);
        $enableAutoScaffolding = (int)($itmUiConfig['enable_auto_scaffolding'] ?? 0) === 1;
        $autoScaffoldingEnabled = $enableAutoScaffolding;

        $hasEquipmentTypeEditEmoji = itm_table_has_column($conn, 'equipment_types', 'field_edit_emoji');
        $equipmentTypeSelectFields = $hasEquipmentTypeEditEmoji ? 'name, field_edit_emoji' : 'name';
        $equipmentTypeRes = mysqli_query($conn, 'SELECT ' . $equipmentTypeSelectFields . ' FROM equipment_types');
        if ($equipmentTypeRes) {
            while ($equipmentTypeRow = mysqli_fetch_assoc($equipmentTypeRes)) {
                $typeName = (string)($equipmentTypeRow['name'] ?? '');
                $typeEmoji = trim((string)($equipmentTypeRow['field_edit_emoji'] ?? ''));
                if ($autoScaffoldingEnabled) {
                    itm_ensure_equipment_type_module_scaffold($typeName);
                }
                $equipmentTypeModuleName = itm_equipment_type_sidebar_item_id($typeName);
                if ($equipmentTypeModuleName !== '') {
                    $equipmentTypeModuleIndex = $modulesRoot . '/' . $equipmentTypeModuleName . '/index.php';
                    if (is_file($equipmentTypeModuleIndex) && !isset($existingItemIds[$equipmentTypeModuleName])) {
                        if ($typeEmoji === '') {
                            $typeEmoji = itm_equipment_type_default_emoji($typeName);
                        }
                        $moduleNames[$equipmentTypeModuleName] = ['emoji' => $typeEmoji];
                    }
                }
            }
        }

                if ($autoScaffoldingEnabled) {
            // Why: SHOW TABLES + auto-scaffold file writes are only safe to run on-demand,
            // not on every page load. Controlled by enable_auto_scaffolding in ui_configuration.
            $tablesRes = mysqli_query($conn, 'SHOW TABLES');
            if ($tablesRes) {
                while ($tableRow = mysqli_fetch_array($tablesRes)) {
                    $table = isset($tableRow[0]) ? (string)$tableRow[0] : '';
                    if ($table === '' || isset($existingItemIds[$table]) || itm_sidebar_module_is_hidden($table)) {
                        continue;
                    }

                    $moduleIndex = $modulesRoot . '/' . $table . '/index.php';
                    $scaffoldedNow = false;
                    if (!is_file($moduleIndex)) {
                        $scaffoldedNow = itm_auto_create_module_scaffold($table);
                    }

                    if (is_file($moduleIndex)) {
                        $scaffoldEmoji = $scaffoldedNow ? itm_sidebar_auto_scaffolded_module_emoji() : '';
                        $moduleNames[$table] = ['emoji' => $scaffoldEmoji];
                    }
            }
        }
    }
    }

    if ($conn instanceof mysqli && function_exists('itm_merge_registry_modules_into_sidebar_discovery')) {
        itm_merge_registry_modules_into_sidebar_discovery($conn, $moduleNames, $existingItemIds);
    }

    // Why: New MySQL tables, module folders, and registry rows must register for live sidebar access without a manual sync visit.
    if ($conn instanceof mysqli && function_exists('itm_ensure_registry_rows_for_module_slugs') && $moduleNames) {
        itm_ensure_registry_rows_for_module_slugs($conn, array_keys($moduleNames));
    }

    $discoveredEquipmentTypeItems = [];
    $discoveredItems = [];
    foreach ($moduleNames as $moduleName => $moduleMeta) {
        if (itm_sidebar_module_is_hidden($moduleName)) {
            continue;
        }
        $item = [
            'id' => $moduleName,
            'label' => itm_sidebar_build_discovered_module_label($moduleName, $moduleMeta),
            'href' => 'modules/' . $moduleName . '/',
            'match_dir' => $moduleName,
        ];
        if (!empty($moduleMeta['from_registry'])) {
            $registryName = trim((string)($moduleMeta['registry_name'] ?? ''));
            if ($registryName === '') {
                $registryName = itm_sidebar_humanize_table_name($moduleName);
            }
            $registryIcon = trim((string)($moduleMeta['emoji'] ?? ''));
            if ($registryIcon === '') {
                $registryIcon = itm_sidebar_auto_scaffolded_module_emoji();
            }
            $item['label'] = trim($registryIcon . ' ' . $registryName);
        }
        if (strpos($moduleName, 'is_') === 0) {
            $typeLabel = trim(preg_replace('/^is[_\s-]*/i', '', (string)$moduleName));
            $moduleEmoji = trim((string)($moduleMeta['emoji'] ?? ''));
            if ($moduleEmoji === '') {
                $moduleEmoji = itm_equipment_type_default_emoji($typeLabel);
            }
            $item['label'] = trim($moduleEmoji . ' Is ' . itm_sidebar_humanize_table_name($typeLabel));
            $discoveredEquipmentTypeItems[] = $item;
            continue;
        }
        $discoveredItems[] = $item;
    }

    if (!$discoveredItems && !$discoveredEquipmentTypeItems) {
        $is_building = false;
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

    $is_building = false;
    $itm_sidebar_cache = $structure;
    return $structure;
}

/**
 * @return int
 */
function itm_sidebar_structure_slug_count($conn, $slug, $forceRefresh = false)
{
    $slug = trim((string)$slug);
    if ($slug === '') {
        return 0;
    }

    $count = 0;
    foreach (itm_sidebar_structure($conn, $forceRefresh) as $section) {
        foreach (($section['items'] ?? []) as $item) {
            if ((string)($item['id'] ?? '') === $slug) {
                $count++;
            }
        }
    }

    return $count;
}

/**
 * Returns whether a module slug appears anywhere in the sidebar structure.
 */
function itm_sidebar_structure_contains_slug($conn, $slug, $forceRefresh = false) {
    $slug = trim((string)$slug);
    if ($slug === '') {
        return false;
    }

    foreach (itm_sidebar_structure($conn, $forceRefresh) as $section) {
        foreach (($section['items'] ?? []) as $item) {
            if ((string)($item['id'] ?? '') === $slug) {
                return true;
            }
        }
    }

    return false;
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
        'table_actions_position' => 'left',
        'new_button_position' => 'left',
        'export_buttons_position' => 'left',
        'back_save_position' => 'left',
        'enable_all_error_reporting' => 1,
        'enable_audit_logs' => 1,
        'enable_chatbot' => 1,
        'enable_auto_scaffolding' => 0,
        'records_per_page' => '25',
        'app_name' => '⚙️ IT Controls',
        'favicon_path' => '',
        'equipment_type_sidebar_visibility' => [],
        'module_icon_overrides' => [],
        'sidebar_visibility' => itm_default_sidebar_visibility(),
        'sidebar_main_order' => itm_default_sidebar_main_order(),
        'sidebar_submenu_order' => itm_default_sidebar_submenu_order(),
    ];
}

/**
 * Resolves the user-configured application name with a safe fallback.
 */
function itm_ui_config_app_name($uiConfig = null) {
    $defaults = itm_ui_config_defaults();
       $fallback = (string)($defaults['app_name'] ?? '⚙️ IT Controls');

    if (!is_array($uiConfig)) {
        return $fallback;
    }

    $appName = trim((string)($uiConfig['app_name'] ?? ''));
    return $appName !== '' ? $appName : $fallback;
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

    if (strlen($normalized) > 191) {
        // Why: employee_sidebar_preferences.entry_id is VARCHAR(191); keep IDs deterministic but storage-safe.
        $normalized = substr($normalized, 0, 174) . '_' . substr(sha1($normalized), 0, 16);
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
function itm_ensure_ui_configuration_table($conn, &$report = null) {
    if (!($conn instanceof mysqli)) {
        return false;
    }

    // Why: Schema ensure issues 20+ SHOW TABLES/COLUMNS/INDEX queries; cache success for this request only.
    static $itmUiConfigurationTableReady = false;
    if ($report === null && $itmUiConfigurationTableReady) {
        return true;
    }

    $tableExistsRes = mysqli_query($conn, "SHOW TABLES LIKE 'ui_configuration'");
    if (!$tableExistsRes) {
        return false;
    }
    $tableExistedBefore = mysqli_num_rows($tableExistsRes) > 0;

$sql = "CREATE TABLE IF NOT EXISTS `ui_configuration` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `table_actions_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `new_button_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `export_buttons_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `back_save_position` VARCHAR(30) NOT NULL DEFAULT 'left_right',
        `enable_all_error_reporting` TINYINT(1) NOT NULL DEFAULT 1,
        `enable_audit_logs` TINYINT(1) NOT NULL DEFAULT 1,
        `enable_chatbot` TINYINT(1) NOT NULL DEFAULT 1,
        `enable_auto_scaffolding` TINYINT(1) NOT NULL DEFAULT 0,
        `records_per_page` VARCHAR(10) NOT NULL DEFAULT '25',
        `app_name` VARCHAR(191) NOT NULL DEFAULT '⚙️ IT Controls',
        `favicon_path` VARCHAR(255) NOT NULL DEFAULT '',
        `equipment_type_sidebar_visibility` LONGTEXT NULL,
        `module_icon_overrides` LONGTEXT NULL,
        `api_key` VARCHAR(191) NOT NULL DEFAULT '',
        `api_key_is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `api_key_last_used_at` TIMESTAMP NULL DEFAULT NULL,
        `rate_limit_window_start` INT NOT NULL DEFAULT 0,
        `rate_limit_request_count` INT NOT NULL DEFAULT 0,
        `rate_limit_enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `tier` ENUM('Free','Basic','Pro','Enterprise') NOT NULL DEFAULT 'Free',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

        UNIQUE KEY `uq_ui_configuration_company_employee` (`company_id`, `employee_id`),

        CONSTRAINT `fk_ui_configuration_employee`
            FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,

        CONSTRAINT `fk_ui_configuration_company`
            FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE

    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";


    if (mysqli_query($conn, $sql) !== true) {
        return false;
    }

    $localReport = [
        'created_tables' => [],
        'verified_tables' => [],
        'added_columns' => [],
    ];
    if ($tableExistedBefore) {
        $localReport['verified_tables'][] = 'ui_configuration';
    } else {
        $localReport['created_tables'][] = 'ui_configuration';
    }

    // Add missing columns if they don't exist
    $columns = [
        'employee_id' => "ALTER TABLE `ui_configuration` ADD COLUMN `employee_id` INT NOT NULL DEFAULT 0 AFTER `company_id`",
        'enable_all_error_reporting' => "ALTER TABLE `ui_configuration` ADD COLUMN `enable_all_error_reporting` TINYINT(1) NOT NULL DEFAULT 1 AFTER `back_save_position`",
        'enable_audit_logs' => "ALTER TABLE `ui_configuration` ADD COLUMN `enable_audit_logs` TINYINT(1) NOT NULL DEFAULT 1 AFTER `enable_all_error_reporting`",
        'enable_chatbot' => "ALTER TABLE `ui_configuration` ADD COLUMN `enable_chatbot` TINYINT(1) NOT NULL DEFAULT 1 AFTER `enable_audit_logs`",
        'enable_auto_scaffolding' => "ALTER TABLE `ui_configuration` ADD COLUMN `enable_auto_scaffolding` TINYINT(1) NOT NULL DEFAULT 0 AFTER `enable_chatbot`",
        'records_per_page' => "ALTER TABLE `ui_configuration` ADD COLUMN `records_per_page` VARCHAR(10) NOT NULL DEFAULT '25' AFTER `enable_auto_scaffolding`",
        'app_name' => "ALTER TABLE `ui_configuration` ADD COLUMN `app_name` VARCHAR(191) NOT NULL DEFAULT '⚙️ IT Controls' AFTER `records_per_page`",
        'favicon_path' => "ALTER TABLE `ui_configuration` ADD COLUMN `favicon_path` VARCHAR(255) NOT NULL DEFAULT '' AFTER `app_name`",
        'equipment_type_sidebar_visibility' => "ALTER TABLE `ui_configuration` ADD COLUMN `equipment_type_sidebar_visibility` LONGTEXT NULL AFTER `favicon_path`",
        'module_icon_overrides' => "ALTER TABLE `ui_configuration` ADD COLUMN `module_icon_overrides` LONGTEXT NULL AFTER `equipment_type_sidebar_visibility`",
        'api_key' => "ALTER TABLE `ui_configuration` ADD COLUMN `api_key` VARCHAR(191) NOT NULL DEFAULT '' AFTER `module_icon_overrides`",
        'api_key_is_active' => "ALTER TABLE `ui_configuration` ADD COLUMN `api_key_is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `api_key`",
        'api_key_last_used_at' => "ALTER TABLE `ui_configuration` ADD COLUMN `api_key_last_used_at` TIMESTAMP NULL DEFAULT NULL AFTER `api_key_is_active`",
        'rate_limit_window_start' => "ALTER TABLE `ui_configuration` ADD COLUMN `rate_limit_window_start` INT NOT NULL DEFAULT 0 AFTER `api_key_last_used_at`",
        'rate_limit_request_count' => "ALTER TABLE `ui_configuration` ADD COLUMN `rate_limit_request_count` INT NOT NULL DEFAULT 0 AFTER `rate_limit_window_start`",
        'rate_limit_enabled' => "ALTER TABLE `ui_configuration` ADD COLUMN `rate_limit_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `rate_limit_request_count`",
        'tier' => "ALTER TABLE `ui_configuration` ADD COLUMN `tier` ENUM('Free','Basic','Pro','Enterprise') NOT NULL DEFAULT 'Free' AFTER `rate_limit_enabled`",
    ];

    foreach ($columns as $column => $alterSql) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM `ui_configuration` LIKE '" . mysqli_real_escape_string($conn, $column) . "'");
        if (!$check) {
            return false;
        }
        if (mysqli_num_rows($check) === 0 && mysqli_query($conn, $alterSql) !== true) {
            return false;
        }
        if (mysqli_num_rows($check) === 0) {
            $localReport['added_columns'][] = 'ui_configuration.' . $column;
        }
    }

    // Why: Sidebar layout is now normalized in employee_sidebar_preferences; remove deprecated JSON blob columns.
    $legacySidebarColumns = ['sidebar_visibility', 'sidebar_main_order', 'sidebar_submenu_order'];
    foreach ($legacySidebarColumns as $legacyColumn) {
        $legacyColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM `ui_configuration` LIKE '" . mysqli_real_escape_string($conn, $legacyColumn) . "'");
        if (!$legacyColumnCheck) {
            return false;
        }
        if (mysqli_num_rows($legacyColumnCheck) > 0) {
            $dropColumnSql = "ALTER TABLE `ui_configuration` DROP COLUMN `" . $legacyColumn . "`";
            if (mysqli_query($conn, $dropColumnSql) !== true) {
                return false;
            }
        }
    }

    // Why: UI settings must be isolated per user within each company.
    $legacyUniqueRes = mysqli_query($conn, "SHOW INDEX FROM `ui_configuration` WHERE Key_name = 'uq_ui_configuration_company'");
    if ($legacyUniqueRes && mysqli_num_rows($legacyUniqueRes) > 0) {
        if (mysqli_query($conn, 'ALTER TABLE `ui_configuration` DROP INDEX `uq_ui_configuration_company`') !== true) {
            return false;
        }
    }

    $newUniqueRes = mysqli_query($conn, "SHOW INDEX FROM `ui_configuration` WHERE Key_name = 'uq_ui_configuration_company_employee'");
    if (!$newUniqueRes) {
        return false;
    }
    if (mysqli_num_rows($newUniqueRes) === 0) {
        // Why: Legacy installs can contain duplicate company/employee rows (commonly employee_id=0),
        // which blocks the unique key migration and causes all future saves to fail.
        $dupPairsRes = mysqli_query(
            $conn,
            'SELECT company_id, employee_id
             FROM ui_configuration
             GROUP BY company_id, employee_id
             HAVING COUNT(*) > 1'
        );
        if ($dupPairsRes === false) {
            return false;
        }

        while ($dupPair = mysqli_fetch_assoc($dupPairsRes)) {
            $dupCompanyId = (int)($dupPair['company_id'] ?? 0);
            $dupUserId = (int)($dupPair['employee_id'] ?? 0);

            $dupRowsRes = mysqli_query(
                $conn,
                'SELECT id
                 FROM ui_configuration
                 WHERE company_id = ' . $dupCompanyId . ' AND employee_id = ' . $dupUserId . '
                 ORDER BY updated_at DESC, id DESC'
            );
            if ($dupRowsRes === false) {
                return false;
            }

            $keepFirst = true;
            $deleteIds = [];
            while ($dupRow = mysqli_fetch_assoc($dupRowsRes)) {
                $rowId = (int)($dupRow['id'] ?? 0);
                if ($rowId <= 0) {
                    continue;
                }
                if ($keepFirst) {
                    $keepFirst = false;
                    continue;
                }
                $deleteIds[] = $rowId;
            }

            if (!empty($deleteIds)) {
                $deleteSql = 'DELETE FROM ui_configuration WHERE id IN (' . implode(',', $deleteIds) . ')';
                if (mysqli_query($conn, $deleteSql) !== true) {
                    return false;
                }
            }
        }

        if (mysqli_query($conn, 'ALTER TABLE `ui_configuration` ADD UNIQUE KEY `uq_ui_configuration_company_employee` (`company_id`, `employee_id`)') !== true) {
            return false;
        }
    }


    if (!itm_ensure_employee_sidebar_preferences_table($conn, $localReport)) {
        return false;
    }

    if (is_array($report)) {
        foreach (['created_tables', 'verified_tables', 'added_columns'] as $reportKey) {
            if (!isset($report[$reportKey]) || !is_array($report[$reportKey])) {
                $report[$reportKey] = [];
            }
            foreach ($localReport[$reportKey] as $reportItem) {
                if (!in_array($reportItem, $report[$reportKey], true)) {
                    $report[$reportKey][] = $reportItem;
                }
            }
        }
    }

    if ($report === null) {
        $itmUiConfigurationTableReady = true;
    }

    return true;
}

/**
 * Fetches the UI configuration for a specific company
 */
function itm_get_ui_configuration($conn, $company_id, $user_id = null, $clearCache = false) {
    // Why: Sidebar rendering calls this repeatedly; static cache avoids hundreds of redundant queries.
    static $itm_ui_config_cache = [];
    if ($clearCache) {
        $itm_ui_config_cache = [];
        return null;
    }

    $defaults = itm_ui_config_defaults();
    $company_id = (int)$company_id;
    if ($user_id === null) {
        $user_id = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : 0;
    }
    $user_id = (int)$user_id;

    $cacheKey = $company_id . ":" . $user_id;
    if (isset($itm_ui_config_cache[$cacheKey])) {
        return $itm_ui_config_cache[$cacheKey];
    }

    if ($company_id <= 0 || $user_id <= 0 || !itm_ensure_ui_configuration_table($conn)) {
        return $defaults;
    }

    // Retrieve settings from the database
    $sql = 'SELECT table_actions_position, new_button_position, export_buttons_position, back_save_position, enable_all_error_reporting, enable_audit_logs, enable_chatbot, enable_auto_scaffolding, records_per_page, app_name, favicon_path, equipment_type_sidebar_visibility, module_icon_overrides, api_key, api_key_is_active, api_key_last_used_at, rate_limit_window_start, rate_limit_request_count, rate_limit_enabled, tier FROM ui_configuration WHERE company_id = ? AND employee_id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $defaults;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $company_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$row) {
        // Fallback to layout configuration if main config missing
        $layoutConfig = itm_get_employee_sidebar_preferences_config($conn, $company_id, $user_id);
        if ($layoutConfig !== null) {
            $defaults['sidebar_visibility'] = itm_normalize_sidebar_visibility($layoutConfig['sidebar_visibility']);
            $defaults['sidebar_main_order'] = itm_normalize_sidebar_main_order($layoutConfig['sidebar_main_order']);
            $defaults['sidebar_submenu_order'] = itm_normalize_sidebar_submenu_order($layoutConfig['sidebar_submenu_order']);
        }
        $itm_ui_config_cache[$cacheKey] = $defaults;
        return $defaults;
    }

    $config = itm_normalize_ui_configuration($row);
    // Overlay detailed layout configuration
    $layoutConfig = itm_get_employee_sidebar_preferences_config($conn, $company_id, $user_id);
    if ($layoutConfig !== null) {
        $config['sidebar_visibility'] = itm_normalize_sidebar_visibility($layoutConfig['sidebar_visibility']);
        $config['sidebar_main_order'] = itm_normalize_sidebar_main_order($layoutConfig['sidebar_main_order']);
        $config['sidebar_submenu_order'] = itm_normalize_sidebar_submenu_order($layoutConfig['sidebar_submenu_order']);
    }

    $itm_ui_config_cache[$cacheKey] = $config;
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
    $values['enable_chatbot'] = itm_normalize_flag($values['enable_chatbot'] ?? $defaults['enable_chatbot']);
    $values['enable_auto_scaffolding'] = itm_normalize_flag($values['enable_auto_scaffolding'] ?? $defaults['enable_auto_scaffolding']);
    $values['equipment_type_sidebar_visibility'] = itm_normalize_equipment_type_sidebar_visibility($values['equipment_type_sidebar_visibility'] ?? []);
    $values['module_icon_overrides'] = itm_normalize_module_icon_overrides($values['module_icon_overrides'] ?? []);

    // Validate records per page
    $recordsPerPage = strtolower((string)($values['records_per_page'] ?? $defaults['records_per_page']));
    $appName = trim((string)($values['app_name'] ?? $defaults['app_name']));
    if ($appName === '') {
        $appName = (string)$defaults['app_name'];
    }
    if (function_exists('mb_substr')) {
        $appName = mb_substr($appName, 0, 191);
    } else {
        $appName = substr($appName, 0, 191);
    }
    $values['app_name'] = $appName;

    $faviconPath = trim((string)($values['favicon_path'] ?? ''));
    $values['favicon_path'] = itm_normalize_ui_config_favicon_path($faviconPath);

    if ($recordsPerPage === 'all') {
        $values['records_per_page'] = 'all';
    } elseif (ctype_digit($recordsPerPage) && (int)$recordsPerPage > 0 && (int)$recordsPerPage <= 1000000) {
        $values['records_per_page'] = (string)((int)$recordsPerPage);
    } else {
        $values['records_per_page'] = $defaults['records_per_page'];
    }

    $values['api_key'] = trim((string)($values['api_key'] ?? ''));
    if (strlen($values['api_key']) > 191) {
        $values['api_key'] = substr($values['api_key'], 0, 191);
    }
    $values['api_key_is_active'] = itm_normalize_flag($values['api_key_is_active'] ?? 1);
    $values['rate_limit_window_start'] = max(0, (int)($values['rate_limit_window_start'] ?? 0));
    $values['rate_limit_request_count'] = max(0, (int)($values['rate_limit_request_count'] ?? 0));
    $values['rate_limit_enabled'] = itm_normalize_flag($values['rate_limit_enabled'] ?? 1);
    $values['tier'] = function_exists('itm_api_normalize_tier')
        ? itm_api_normalize_tier($values['tier'] ?? 'Free')
        : 'Free';

    return $values;
}

/**
 * Normalizes stored favicon paths so only project-local image references are persisted.
 */
function itm_normalize_ui_config_favicon_path($rawPath) {
    $path = trim((string)$rawPath);
    if ($path === '') {
        return '';
    }

    $path = str_replace('\\', '/', $path);
    $path = ltrim($path, '/');
    if (strpos($path, '..') !== false) {
        return '';
    }

    if (!preg_match('/^images\/favicons\/[a-z0-9._-]+\.ico$/i', $path)) {
        return '';
    }

    return $path;
}

/**
 * Canonical per-tenant favicon path written by Settings upload (company-scoped .ico).
 */
function itm_ui_config_canonical_favicon_relative_path($companyId) {
    $companyId = (int) $companyId;
    if ($companyId <= 0) {
        return '';
    }

    return 'images/favicons/company_' . $companyId . '.ico';
}

/**
 * Resolves a usable favicon relative path: stored ui_configuration path first, then canonical on-disk file.
 */
function itm_ui_config_resolve_favicon_relative_path($uiConfig, $companyId = 0) {
    if (!is_array($uiConfig)) {
        $uiConfig = [];
    }

    if ($companyId <= 0 && isset($_SESSION['company_id'])) {
        $companyId = (int) $_SESSION['company_id'];
    }

    $stored = itm_normalize_ui_config_favicon_path($uiConfig['favicon_path'] ?? '');
    if ($stored !== '') {
        $storedAbs = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $stored);
        if (is_file($storedAbs)) {
            return $stored;
        }
    }

    $canonical = itm_ui_config_canonical_favicon_relative_path($companyId);
    if ($canonical === '') {
        return '';
    }

    $canonicalAbs = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $canonical);
    if (is_file($canonicalAbs)) {
        return $canonical;
    }

    return '';
}

/**
 * Persists canonical favicon_path when Settings upload file exists but the row path is empty.
 */
function itm_ui_config_sync_favicon_path_from_disk($conn, $companyId, $employeeId) {
    if (!($conn instanceof mysqli)) {
        return false;
    }

    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    if ($companyId <= 0 || $employeeId <= 0) {
        return false;
    }

    $canonical = itm_ui_config_canonical_favicon_relative_path($companyId);
    if ($canonical === '') {
        return false;
    }

    $canonicalAbs = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $canonical);
    if (!is_file($canonicalAbs)) {
        return false;
    }

    $sql = "UPDATE ui_configuration
            SET favicon_path = ?
            WHERE company_id = ?
              AND employee_id = ?
              AND (favicon_path IS NULL OR favicon_path = '')
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'sii', $canonical, $companyId, $employeeId);
    $ok = mysqli_stmt_execute($stmt);
    $affected = $ok ? mysqli_stmt_affected_rows($stmt) : 0;
    mysqli_stmt_close($stmt);

    if ($affected > 0 && function_exists('itm_get_ui_configuration')) {
        itm_get_ui_configuration(null, 0, 0, true);
    }

    return $affected > 0;
}

/**
 * Resolves the favicon URL for the active company configuration.
 */
function itm_ui_config_favicon_url($uiConfig = null, $companyId = 0) {
    if (!is_array($uiConfig)) {
        return '';
    }

    if ($companyId <= 0 && isset($_SESSION['company_id'])) {
        $companyId = (int) $_SESSION['company_id'];
    }

    $relative = itm_ui_config_resolve_favicon_relative_path($uiConfig, $companyId);
    if ($relative === '') {
        return '';
    }

    $absolutePath = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($absolutePath)) {
        return '';
    }

    $version = (int) @filemtime($absolutePath);
    $suffix = $version > 0 ? ('?v=' . $version) : '';

    return BASE_URL . $relative . $suffix;
}

if (!function_exists('itm_render_head_favicon_link')) {
    /**
     * Server-side favicon for <head> — required for correct browser tab icon on first paint.
     *
     * Why: header.php injects favicon via DOMContentLoaded JS only; without this link the tab
     * shows the generic globe until JavaScript runs (or forever when head is parsed early).
     */
    function itm_render_head_favicon_link($faviconUrl = null, $uiConfig = null): string
    {
        if (is_array($faviconUrl)) {
            $uiConfig = $faviconUrl;
            $faviconUrl = null;
        }

        if ($faviconUrl === null || trim((string) $faviconUrl) === '') {
            if (!is_array($uiConfig) || $uiConfig === []) {
                global $ui_config;
                if (is_array($ui_config ?? null) && $ui_config !== []) {
                    $uiConfig = $ui_config;
                }
            }
            $faviconUrl = itm_ui_config_favicon_url(is_array($uiConfig) ? $uiConfig : []);
        }

        $faviconUrl = trim((string) $faviconUrl);
        if ($faviconUrl === '') {
            return '';
        }

        return '<link rel="icon" type="image/x-icon" href="'
            . htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8')
            . '">' . "\n";
    }
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
 * Normalizes per-user sidebar module icon overrides (slug => emoji).
 */
function itm_normalize_module_icon_overrides($raw) {
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }

    $normalized = [];
    foreach ($raw as $moduleSlug => $icon) {
        $safeSlug = trim((string)$moduleSlug);
        if ($safeSlug === '') {
            continue;
        }
        $iconValue = trim((string)$icon);
        if ($iconValue === '') {
            continue;
        }
        if (function_exists('mb_substr')) {
            $iconValue = mb_substr($iconValue, 0, 16);
        } else {
            $iconValue = substr($iconValue, 0, 16);
        }
        $normalized[$safeSlug] = $iconValue;
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
        $targetSection = $defaultParent[$itemId] ?? itm_array_key_first($default);
        if ($targetSection === null || !isset($normalized[$targetSection])) {
            $targetSection = itm_array_key_first($normalized);
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
function itm_save_ui_configuration($conn, $company_id, $input, $user_id = null) {
    $company_id = (int)$company_id;
    if ($user_id === null) {
        $user_id = isset($_SESSION['employee_id']) ? (int)$_SESSION['employee_id'] : 0;
    }
    $user_id = (int)$user_id;
    if ($company_id <= 0 || $user_id <= 0 || !itm_ensure_ui_configuration_table($conn)) {
        return false;
    }

    $config = itm_normalize_ui_configuration($input);

    $sql = 'INSERT INTO ui_configuration (company_id, employee_id, table_actions_position, new_button_position, export_buttons_position, back_save_position, enable_all_error_reporting, enable_audit_logs, enable_chatbot, enable_auto_scaffolding, records_per_page, app_name, favicon_path, equipment_type_sidebar_visibility, module_icon_overrides)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                table_actions_position = VALUES(table_actions_position),
                new_button_position = VALUES(new_button_position),
                export_buttons_position = VALUES(export_buttons_position),
                back_save_position = VALUES(back_save_position),
                enable_all_error_reporting = VALUES(enable_all_error_reporting),
                enable_audit_logs = VALUES(enable_audit_logs),
                enable_chatbot = VALUES(enable_chatbot),
                enable_auto_scaffolding = VALUES(enable_auto_scaffolding),
                records_per_page = VALUES(records_per_page),
                app_name = VALUES(app_name),
                favicon_path = VALUES(favicon_path),
                equipment_type_sidebar_visibility = VALUES(equipment_type_sidebar_visibility),
                module_icon_overrides = VALUES(module_icon_overrides)';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    $equipmentTypeSidebarVisibility = json_encode($config['equipment_type_sidebar_visibility']);
    $moduleIconOverrides = json_encode($config['module_icon_overrides'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    mysqli_stmt_bind_param(
        $stmt,
        'iissssiiiisssss',
        $company_id,
        $user_id,
        $config['table_actions_position'],
        $config['new_button_position'],
        $config['export_buttons_position'],
        $config['back_save_position'],
        $config['enable_all_error_reporting'],
        $config['enable_audit_logs'],
        $config['enable_chatbot'],
        $config['enable_auto_scaffolding'],
        $config['records_per_page'],
        $config['app_name'],
        $config['favicon_path'],
        $equipmentTypeSidebarVisibility,
        $moduleIconOverrides
    );

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        return false;
    }

    if (function_exists("itm_has_module_access_bust_cache")) {
        itm_has_module_access_bust_cache();
    } else {
        itm_get_ui_configuration(null, 0, 0, true);
    }
    $sidebarPreferencesSaved = itm_save_employee_sidebar_preferences($conn, $company_id, $user_id, $config);
    if (!$sidebarPreferencesSaved) {
        // Why: UI configuration updates (positions, app name, toggles) must remain saveable
        // even when legacy sidebar preference data cannot be migrated in one request.
        error_log('itm_save_ui_configuration: sidebar preferences sync failed for company_id=' . $company_id . ', user_id=' . $user_id);
    }

    return true;
}

/**
 * Ensures the employee_sidebar_preferences table exists for per-user relational sidebar preferences.
 */
function itm_ensure_employee_sidebar_preferences_table($conn, &$report = null) {
    if (!($conn instanceof mysqli)) {
        return false;
    }

    // Why: Called from ui_configuration ensure and sidebar preference reads; skip repeat metadata scans.
    static $itmEmployeeSidebarPreferencesTableReady = false;
    if ($report === null && $itmEmployeeSidebarPreferencesTableReady) {
        return true;
    }

    $tableExistsRes = mysqli_query($conn, "SHOW TABLES LIKE 'employee_sidebar_preferences'");
    if (!$tableExistsRes) {
        return false;
    }
    $tableExistedBefore = mysqli_num_rows($tableExistsRes) > 0;

    $sql = "CREATE TABLE IF NOT EXISTS `employee_sidebar_preferences` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT NOT NULL,
        `employee_id` INT NOT NULL,
        `entry_type` ENUM('section','item') NOT NULL,
        `entry_id` VARCHAR(191) NOT NULL,
        `section_id` VARCHAR(191) NULL,
        `display_order` INT NOT NULL DEFAULT 0,
        `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
        `active` TINYINT DEFAULT '1',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_employee_sidebar_pref_entry` (`company_id`, `employee_id`, `entry_type`, `entry_id`),
        KEY `idx_employee_sidebar_pref_company_employee_type_order` (`company_id`, `employee_id`, `entry_type`, `display_order`),
        CONSTRAINT `fk_employee_sidebar_pref_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (mysqli_query($conn, $sql) !== true) {
        return false;
    }

    $legacyLengthColumns = [
        'entry_id' => 'VARCHAR(191) NOT NULL',
        'section_id' => 'VARCHAR(191) NULL DEFAULT NULL',
    ];
    foreach ($legacyLengthColumns as $columnName => $columnDefinition) {
        $columnCheckSql = "SHOW COLUMNS FROM `employee_sidebar_preferences` LIKE '" . mysqli_real_escape_string($conn, $columnName) . "'";
        $columnRes = mysqli_query($conn, $columnCheckSql);
        if ($columnRes === false) {
            return false;
        }
        $columnMeta = mysqli_fetch_assoc($columnRes);

        $columnType = strtolower((string)($columnMeta['Type'] ?? ''));
        if ($columnType === 'varchar(100)') {
            $alterSql = 'ALTER TABLE `employee_sidebar_preferences` MODIFY `' . $columnName . '` ' . $columnDefinition;
            if (!itm_run_query($conn, $alterSql)) {
                return false;
            }
            if (is_array($report)) {
                if (!isset($report['added_columns']) || !is_array($report['added_columns'])) {
                    $report['added_columns'] = [];
                }
                $report['added_columns'][] = 'employee_sidebar_preferences.' . $columnName . ' widened to 191 chars';
            }
        }
    }

    // Why: Legacy installs may keep old enum/nullability definitions that reject current insert payloads.
    $entryTypeRes = mysqli_query($conn, "SHOW COLUMNS FROM `employee_sidebar_preferences` LIKE 'entry_type'");
    if ($entryTypeRes === false) {
        return false;
    }
    $entryTypeMeta = mysqli_fetch_assoc($entryTypeRes);
    $entryTypeRaw = strtolower((string)($entryTypeMeta['Type'] ?? ''));
    $hasExpectedEntryEnum = ($entryTypeRaw === "enum('section','item')" || $entryTypeRaw === "enum('item','section')");
    if (!$hasExpectedEntryEnum) {
        if (!itm_run_query($conn, "ALTER TABLE `employee_sidebar_preferences` MODIFY `entry_type` ENUM('section','item') NOT NULL")) {
            return false;
        }
    }

    $sectionIdRes = mysqli_query($conn, "SHOW COLUMNS FROM `employee_sidebar_preferences` LIKE 'section_id'");
    if ($sectionIdRes === false) {
        return false;
    }
    $sectionIdMeta = mysqli_fetch_assoc($sectionIdRes);
    $sectionIdType = strtolower((string)($sectionIdMeta['Type'] ?? ''));
    $sectionIdAllowsNull = strtoupper((string)($sectionIdMeta['Null'] ?? 'NO')) === 'YES';
    if ($sectionIdType !== 'varchar(191)' || !$sectionIdAllowsNull) {
        if (!itm_run_query($conn, 'ALTER TABLE `employee_sidebar_preferences` MODIFY `section_id` VARCHAR(191) NULL DEFAULT NULL')) {
            return false;
        }
    }

    if (!itm_ensure_employee_sidebar_preferences_audit_triggers($conn)) {
        return false;
    }

    if (is_array($report)) {
        if (!isset($report['created_tables']) || !is_array($report['created_tables'])) {
            $report['created_tables'] = [];
        }
        if (!isset($report['verified_tables']) || !is_array($report['verified_tables'])) {
            $report['verified_tables'] = [];
        }
        $bucketKey = $tableExistedBefore ? 'verified_tables' : 'created_tables';
        if (!in_array('employee_sidebar_preferences', $report[$bucketKey], true)) {
            $report[$bucketKey][] = 'employee_sidebar_preferences';
        }
    }

    if ($report === null) {
        $itmEmployeeSidebarPreferencesTableReady = true;
    }

    return true;
}

/**
 * Ensures sidebar preference audit triggers target the current audit_logs column names.
 */
function itm_ensure_employee_sidebar_preferences_audit_triggers($conn) {
    if (!($conn instanceof mysqli)) {
        return false;
    }
    $triggerNames = [
        'trg_employee_sidebar_preferences_audit_insert',
        'trg_employee_sidebar_preferences_audit_update',
        'trg_employee_sidebar_preferences_audit_delete',
    ];

    $needsRebuild = false;
    $existingTriggers = [];
    foreach ($triggerNames as $triggerName) {
        $triggerSql = "SHOW TRIGGERS WHERE `Trigger` = '" . mysqli_real_escape_string($conn, $triggerName) . "'";
        $triggerRes = mysqli_query($conn, $triggerSql);
        if ($triggerRes === false) {
            return false;
        }
        $triggerMeta = mysqli_fetch_assoc($triggerRes);
        if (!$triggerMeta) {
            $needsRebuild = true;
            continue;
        }
        $existingTriggers[$triggerName] = $triggerMeta;
        $actionStatement = (string)($triggerMeta['Statement'] ?? '');
        if (strpos($actionStatement, '`username`') !== false || strpos($actionStatement, '`user_email`') !== false || strpos($actionStatement, '\'user_id\'') !== false) {
            $needsRebuild = true;
        }
    }

    if (!$needsRebuild) {
        return true;
    }

    foreach ($triggerNames as $triggerName) {
        if (!itm_run_query($conn, 'DROP TRIGGER IF EXISTS `' . $triggerName . '`')) {
            return false;
        }
    }

    $createInsertTrigger = "CREATE TRIGGER `trg_employee_sidebar_preferences_audit_insert` AFTER INSERT ON `employee_sidebar_preferences` FOR EACH ROW BEGIN
        INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
        VALUES (COALESCE(@app_company_id, NEW.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_sidebar_preferences', COALESCE(NEW.`id`, 0), 'INSERT', NULL, JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'entry_type', NEW.`entry_type`, 'entry_id', NEW.`entry_id`, 'section_id', NEW.`section_id`, 'display_order', NEW.`display_order`, 'is_visible', NEW.`is_visible`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
    END";
    if (!itm_run_query($conn, $createInsertTrigger)) {
        return false;
    }

    $createUpdateTrigger = "CREATE TRIGGER `trg_employee_sidebar_preferences_audit_update` AFTER UPDATE ON `employee_sidebar_preferences` FOR EACH ROW BEGIN
        INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
        VALUES (COALESCE(@app_company_id, NEW.`company_id`, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_sidebar_preferences', COALESCE(NEW.`id`, OLD.`id`, 0), 'UPDATE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'entry_type', OLD.`entry_type`, 'entry_id', OLD.`entry_id`, 'section_id', OLD.`section_id`, 'display_order', OLD.`display_order`, 'is_visible', OLD.`is_visible`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), JSON_OBJECT('id', NEW.`id`, 'company_id', NEW.`company_id`, 'employee_id', NEW.`employee_id`, 'entry_type', NEW.`entry_type`, 'entry_id', NEW.`entry_id`, 'section_id', NEW.`section_id`, 'display_order', NEW.`display_order`, 'is_visible', NEW.`is_visible`, 'active', NEW.`active`, 'created_at', NEW.`created_at`, 'updated_at', NEW.`updated_at`), @app_ip_address, @app_user_agent);
    END";
    if (!itm_run_query($conn, $createUpdateTrigger)) {
        return false;
    }

    $createDeleteTrigger = "CREATE TRIGGER `trg_employee_sidebar_preferences_audit_delete` AFTER DELETE ON `employee_sidebar_preferences` FOR EACH ROW BEGIN
        INSERT INTO `audit_logs` (`company_id`, `employee_id`, `actor_username`, `actor_email`, `table_name`, `record_id`, `action`, `old_values`, `new_values`, `ip_address`, `user_agent`)
        VALUES (COALESCE(@app_company_id, OLD.`company_id`, 0), @app_employee_id, @app_username, @app_email, 'employee_sidebar_preferences', COALESCE(OLD.`id`, 0), 'DELETE', JSON_OBJECT('id', OLD.`id`, 'company_id', OLD.`company_id`, 'employee_id', OLD.`employee_id`, 'entry_type', OLD.`entry_type`, 'entry_id', OLD.`entry_id`, 'section_id', OLD.`section_id`, 'display_order', OLD.`display_order`, 'is_visible', OLD.`is_visible`, 'active', OLD.`active`, 'created_at', OLD.`created_at`, 'updated_at', OLD.`updated_at`), NULL, @app_ip_address, @app_user_agent);
    END";
    if (!itm_run_query($conn, $createDeleteTrigger)) {
        return false;
    }

    return true;
}

/**
 * Reconciles legacy inventory sidebar rows onto inventory_items (Admin, 📦 label).
 */
function itm_reconcile_employee_sidebar_preferences_inventory($conn, $company_id, $user_id) {
    if (!($conn instanceof mysqli)) {
        return false;
    }
    $company_id = (int)$company_id;
    $user_id = (int)$user_id;
    if ($company_id <= 0 || $user_id <= 0) {
        return false;
    }

    $entryType = 'item';
    $targetItemId = 'inventory_items';
    $legacyItemId = 'inventory';
    $targetSection = 'admin';

    mysqli_begin_transaction($conn);

    $renameSql = 'UPDATE employee_sidebar_preferences
                  SET entry_id = ?
                  WHERE company_id = ? AND employee_id = ? AND entry_type = ? AND entry_id = ? AND active = 1';
    $renameStmt = mysqli_prepare($conn, $renameSql);
    if ($renameStmt) {
        mysqli_stmt_bind_param($renameStmt, 'siiss', $targetItemId, $company_id, $user_id, $entryType, $legacyItemId);
        mysqli_stmt_execute($renameStmt);
        mysqli_stmt_close($renameStmt);
    }

    $selectSql = 'SELECT id, section_id, display_order
                  FROM employee_sidebar_preferences
                  WHERE company_id = ? AND employee_id = ? AND entry_type = ? AND entry_id = ? AND active = 1
                  ORDER BY id ASC';
    $selectStmt = mysqli_prepare($conn, $selectSql);
    if (!$selectStmt) {
        mysqli_rollback($conn);
        return false;
    }
    mysqli_stmt_bind_param($selectStmt, 'iiss', $company_id, $user_id, $entryType, $targetItemId);
    mysqli_stmt_execute($selectStmt);
    $selectResult = mysqli_stmt_get_result($selectStmt);
    $itemRows = $selectResult ? mysqli_fetch_all($selectResult, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($selectStmt);

    if ($itemRows) {
        $primaryId = (int)($itemRows[0]['id'] ?? 0);
        $currentSection = (string)($itemRows[0]['section_id'] ?? '');
        $currentOrder = (int)($itemRows[0]['display_order'] ?? 0);

        if (count($itemRows) > 1 && $primaryId > 0) {
            $deleteSql = 'DELETE FROM employee_sidebar_preferences WHERE company_id = ? AND employee_id = ? AND id = ?';
            $deleteStmt = mysqli_prepare($conn, $deleteSql);
            if ($deleteStmt) {
                foreach (array_slice($itemRows, 1) as $row) {
                    $duplicateId = (int)($row['id'] ?? 0);
                    if ($duplicateId <= 0) {
                        continue;
                    }
                    mysqli_stmt_bind_param($deleteStmt, 'iii', $company_id, $user_id, $duplicateId);
                    mysqli_stmt_execute($deleteStmt);
                }
                mysqli_stmt_close($deleteStmt);
            }
        }

        if ($primaryId > 0 && $currentSection !== $targetSection) {
            $anchorSql = 'SELECT display_order
                          FROM employee_sidebar_preferences
                          WHERE company_id = ? AND employee_id = ? AND entry_type = ? AND entry_id = ? AND section_id = ? AND active = 1
                          ORDER BY id ASC
                          LIMIT 1';
            $anchorItem = 'employees';
            $anchorStmt = mysqli_prepare($conn, $anchorSql);
            $targetOrder = $currentOrder;
            if ($anchorStmt) {
                mysqli_stmt_bind_param($anchorStmt, 'iisss', $company_id, $user_id, $entryType, $anchorItem, $targetSection);
                mysqli_stmt_execute($anchorStmt);
                $anchorResult = mysqli_stmt_get_result($anchorStmt);
                $anchorRow = $anchorResult ? mysqli_fetch_assoc($anchorResult) : null;
                mysqli_stmt_close($anchorStmt);
                if (is_array($anchorRow) && isset($anchorRow['display_order'])) {
                    $targetOrder = max(0, (int)$anchorRow['display_order'] - 1);
                } else {
                    $targetOrder = 0;
                }
            }

            $updateSql = 'UPDATE employee_sidebar_preferences
                          SET section_id = ?, display_order = ?
                          WHERE company_id = ? AND employee_id = ? AND id = ?';
            $updateStmt = mysqli_prepare($conn, $updateSql);
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, 'siiii', $targetSection, $targetOrder, $company_id, $user_id, $primaryId);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }
        }
    }

    return mysqli_commit($conn);
}

/**
 * Reconciles persisted legacy rows for switch_ports in employee_sidebar_preferences.
 */
function itm_reconcile_employee_sidebar_preferences_switch_ports($conn, $company_id, $user_id) {
    if (!($conn instanceof mysqli)) {
        return false;
    }
    $company_id = (int)$company_id;
    $user_id = (int)$user_id;
    if ($company_id <= 0 || $user_id <= 0) {
        return false;
    }

    $entryType = 'item';
    $itemId = 'switch_ports';
    $anchorItem = 'switch_status';
    $targetSection = 'reference_data';

    $selectSql = 'SELECT id, section_id, display_order
                  FROM employee_sidebar_preferences
                  WHERE company_id = ? AND employee_id = ? AND entry_type = ? AND entry_id = ? AND active = 1
                  ORDER BY id ASC';
    $selectStmt = mysqli_prepare($conn, $selectSql);
    if (!$selectStmt) {
        return false;
    }
    mysqli_stmt_bind_param($selectStmt, 'iiss', $company_id, $user_id, $entryType, $itemId);
    mysqli_stmt_execute($selectStmt);
    $selectResult = mysqli_stmt_get_result($selectStmt);
    $itemRows = $selectResult ? mysqli_fetch_all($selectResult, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($selectStmt);

    if (!$itemRows) {
        return true;
    }

    $primaryId = (int)($itemRows[0]['id'] ?? 0);
    $currentSection = (string)($itemRows[0]['section_id'] ?? '');
    $currentOrder = (int)($itemRows[0]['display_order'] ?? 0);
    if ($primaryId <= 0) {
        return false;
    }

    mysqli_begin_transaction($conn);

    // Why: duplicated rows can survive manual SQL edits; keep one deterministic row before repositioning.
    if (count($itemRows) > 1) {
        $deleteSql = 'DELETE FROM employee_sidebar_preferences WHERE company_id = ? AND employee_id = ? AND id = ?';
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if (!$deleteStmt) {
            mysqli_rollback($conn);
            return false;
        }
        foreach (array_slice($itemRows, 1) as $row) {
            $duplicateId = (int)($row['id'] ?? 0);
            if ($duplicateId <= 0) {
                continue;
            }
            mysqli_stmt_bind_param($deleteStmt, 'iii', $company_id, $user_id, $duplicateId);
            if (!mysqli_stmt_execute($deleteStmt)) {
                mysqli_stmt_close($deleteStmt);
                mysqli_rollback($conn);
                return false;
            }
        }
        mysqli_stmt_close($deleteStmt);
    }

    $anchorSql = 'SELECT display_order
                  FROM employee_sidebar_preferences
                  WHERE company_id = ? AND employee_id = ? AND entry_type = ? AND entry_id = ? AND section_id = ? AND active = 1
                  ORDER BY id ASC
                  LIMIT 1';
    $anchorStmt = mysqli_prepare($conn, $anchorSql);
    if (!$anchorStmt) {
        mysqli_rollback($conn);
        return false;
    }
    mysqli_stmt_bind_param($anchorStmt, 'iisss', $company_id, $user_id, $entryType, $anchorItem, $targetSection);
    mysqli_stmt_execute($anchorStmt);
    $anchorResult = mysqli_stmt_get_result($anchorStmt);
    $anchorRow = $anchorResult ? mysqli_fetch_assoc($anchorResult) : null;
    mysqli_stmt_close($anchorStmt);

    if (is_array($anchorRow) && isset($anchorRow['display_order'])) {
        $targetOrder = (int)$anchorRow['display_order'];
    } else {
        $maxSql = 'SELECT COALESCE(MAX(display_order), -1) AS max_order
                   FROM employee_sidebar_preferences
                   WHERE company_id = ? AND employee_id = ? AND entry_type = ? AND section_id = ? AND active = 1';
        $maxStmt = mysqli_prepare($conn, $maxSql);
        if (!$maxStmt) {
            mysqli_rollback($conn);
            return false;
        }
        mysqli_stmt_bind_param($maxStmt, 'iiss', $company_id, $user_id, $entryType, $targetSection);
        mysqli_stmt_execute($maxStmt);
        $maxResult = mysqli_stmt_get_result($maxStmt);
        $maxRow = $maxResult ? mysqli_fetch_assoc($maxResult) : null;
        mysqli_stmt_close($maxStmt);
        $targetOrder = isset($maxRow['max_order']) ? ((int)$maxRow['max_order'] + 1) : 0;
    }

    if ($currentSection !== $targetSection || $currentOrder !== $targetOrder) {
        $shiftSql = 'UPDATE employee_sidebar_preferences
                     SET display_order = display_order + 1
                     WHERE company_id = ? AND employee_id = ? AND entry_type = ? AND section_id = ? AND active = 1 AND id <> ? AND display_order >= ?';
        $shiftStmt = mysqli_prepare($conn, $shiftSql);
        if (!$shiftStmt) {
            mysqli_rollback($conn);
            return false;
        }
        mysqli_stmt_bind_param($shiftStmt, 'iissii', $company_id, $user_id, $entryType, $targetSection, $primaryId, $targetOrder);
        if (!mysqli_stmt_execute($shiftStmt)) {
            mysqli_stmt_close($shiftStmt);
            mysqli_rollback($conn);
            return false;
        }
        mysqli_stmt_close($shiftStmt);

        $updateSql = 'UPDATE employee_sidebar_preferences
                      SET section_id = ?, display_order = ?
                      WHERE company_id = ? AND employee_id = ? AND id = ? AND entry_type = ? AND entry_id = ?';
        $updateStmt = mysqli_prepare($conn, $updateSql);
        if (!$updateStmt) {
            mysqli_rollback($conn);
            return false;
        }
        mysqli_stmt_bind_param($updateStmt, 'siiiiss', $targetSection, $targetOrder, $company_id, $user_id, $primaryId, $entryType, $itemId);
        if (!mysqli_stmt_execute($updateStmt)) {
            mysqli_stmt_close($updateStmt);
            mysqli_rollback($conn);
            return false;
        }
        mysqli_stmt_close($updateStmt);
    }

    return mysqli_commit($conn);
}

/**
 * Retrieves per-user relational sidebar preferences.
 */
function itm_get_employee_sidebar_preferences_config($conn, $company_id, $user_id) {
    if (!($conn instanceof mysqli)) {
        return null;
    }
    $company_id = (int)$company_id;
    $user_id = (int)$user_id;
    if ($company_id <= 0 || $user_id <= 0 || !itm_ensure_employee_sidebar_preferences_table($conn)) {
        return null;
    }
    itm_reconcile_employee_sidebar_preferences_switch_ports($conn, $company_id, $user_id);
    itm_reconcile_employee_sidebar_preferences_inventory($conn, $company_id, $user_id);

    $sql = 'SELECT entry_type, entry_id, section_id, display_order, is_visible
            FROM employee_sidebar_preferences
            WHERE company_id = ? AND employee_id = ? AND active = 1
            ORDER BY entry_type ASC, display_order ASC, id ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $company_id, $user_id);
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

/**
 * Seeds default sidebar layout rows for QA sample data and empty tenants.
 * Why: database.sql uses INSERT…SELECT for this table; itm_parse_database_sql_inserts only reads INSERT…VALUES.
 *
 * @return int Rows for the company after seed (0 on failure)
 */
function itm_seed_default_employee_sidebar_preferences_for_company($conn, $company_id, $user_id = 1, &$error = '') {
    $error = '';
    if (!($conn instanceof mysqli)) {
        $error = 'Invalid database connection.';
        return 0;
    }
    $company_id = (int)$company_id;
    $user_id = (int)$user_id;
    if ($company_id <= 0 || $user_id <= 0) {
        $error = 'Invalid company or user for sidebar seed.';
        return 0;
    }

    if (!function_exists('itm_ui_config_defaults') || !function_exists('itm_save_employee_sidebar_preferences')) {
        $error = 'Sidebar seed helpers unavailable.';
        return 0;
    }

    $defaults = itm_ui_config_defaults();
    $layoutConfig = [
        'sidebar_visibility' => $defaults['sidebar_visibility'],
        'sidebar_main_order' => $defaults['sidebar_main_order'],
        'sidebar_submenu_order' => $defaults['sidebar_submenu_order'],
    ];

    if (!itm_save_employee_sidebar_preferences($conn, $company_id, $user_id, $layoutConfig)) {
        $error = 'Could not save default sidebar preferences.';
        return 0;
    }

    $countSql = 'SELECT COUNT(*) AS total_rows FROM employee_sidebar_preferences WHERE company_id=' . $company_id;
    $countRes = mysqli_query($conn, $countSql);
    if ($countRes && ($countRow = mysqli_fetch_assoc($countRes))) {
        return (int)($countRow['total_rows'] ?? 0);
    }

    return 0;
}

/**
 * Synchronizes per-user relational sidebar preferences.
 */
function itm_save_employee_sidebar_preferences($conn, $company_id, $user_id, $config) {
    $company_id = (int)$company_id;
    $user_id = (int)$user_id;
    if ($company_id <= 0 || $user_id <= 0 || !itm_ensure_employee_sidebar_preferences_table($conn)) {
        return false;
    }

    $rows = itm_sidebar_layout_rows_from_config($config);

    mysqli_begin_transaction($conn);
    $deleteStmt = mysqli_prepare($conn, 'DELETE FROM employee_sidebar_preferences WHERE company_id = ? AND employee_id = ?');
    if (!$deleteStmt) {
        mysqli_rollback($conn);
        return false;
    }
    mysqli_stmt_bind_param($deleteStmt, 'ii', $company_id, $user_id);
    $deleteOk = mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);
    if (!$deleteOk) {
        mysqli_rollback($conn);
        return false;
    }

    $insertStmt = mysqli_prepare(
        $conn,
        'INSERT INTO employee_sidebar_preferences (company_id, employee_id, entry_type, entry_id, section_id, display_order, is_visible, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );
    if (!$insertStmt) {
        mysqli_rollback($conn);
        return false;
    }

    foreach ($rows as $row) {
        $entryType = (string)$row['entry_type'];
        $entryId = (string)$row['entry_id'];
        $sectionId = isset($row['section_id']) ? (string)$row['section_id'] : null;
        if ($entryId === '') {
            continue;
        }
        if (strlen($entryId) > 191) {
            $entryId = substr($entryId, 0, 174) . '_' . substr(sha1($entryId), 0, 16);
        }
        if ($sectionId !== null && strlen($sectionId) > 191) {
            $sectionId = substr($sectionId, 0, 174) . '_' . substr(sha1($sectionId), 0, 16);
        }
        $displayOrder = (int)$row['display_order'];
        $isVisible = (int)$row['is_visible'];
        mysqli_stmt_bind_param($insertStmt, 'iisssii', $company_id, $user_id, $entryType, $entryId, $sectionId, $displayOrder, $isVisible);
        if (!mysqli_stmt_execute($insertStmt)) {
            error_log('itm_save_employee_sidebar_preferences insert failed: ' . mysqli_stmt_error($insertStmt));
            mysqli_stmt_close($insertStmt);
            mysqli_rollback($conn);
            return false;
        }
    }

    mysqli_stmt_close($insertStmt);
    return mysqli_commit($conn);
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
