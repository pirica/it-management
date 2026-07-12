<?php
/**
 * CLI-only: broad-spectrum SQL cleanup utility for database.sql.
 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> This tool must be run from the terminal.</p><pre>php scripts/fix_sql_broad.php</pre></body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Fix SQL Broad');

$sqlPath = 'database.sql';
$content = file_get_contents($sqlPath);

$tablesToFix = [
    'cable_colors',
    'switch_port_types',
    'workstation_device_types',
    'equipment_fiber_rack',
    'rack_statuses',
    'ui_configuration',
    'location_types',
    'equipment_fiber',
    'equipment_fiber_count',
    'idf_ports',
    'equipment_rj45',
    'idf_links',
    'idf_positions',
    'switch_ports',
    'switch_status',
    'equipment_fiber_patch',
    'equipment_statuses',
    'printer_device_types',
    'warranty_types',
    'workstation_office',
    'workstation_os_types',
    'workstation_os_versions',
    'workstation_ram',
    'employees',
    'tickets',
    'employee_onboarding_requests',
    'employee_system_access',
    'equipment',
    'equipment_environment',
    'switch_port_numbering_layout',
    'role_hierarchy',
    'role_module_permissions',
    'role_assignment_rights',
    'visitors_access_log',
    'patches_updates_level',
    'patches_updates',
    'supplier_statuses',
    'floor_plan_item_tags'
];

foreach ($tablesToFix as $table) {
    echo "Processing table: $table" . $nl;
    
    // 1. Add active column to CREATE TABLE if missing
    $pattern = '/(CREATE TABLE `' . preg_quote($table, '/') . '` \(.*?\)) ENGINE=/s';
    if (preg_match($pattern, $content, $matches)) {
        $tableBlock = $matches[1];
        if (strpos($tableBlock, '`active`') === false) {
            // Find a good place to insert - before created_at or at the end
            if (preg_match('/  `created_at`/', $tableBlock)) {
                $newBlock = preg_replace('/(  `created_at`)/', "  `active` tinyint NOT NULL DEFAULT '1',\n$1", $tableBlock);
            } else {
                $newBlock = preg_replace('/\s*\)$/', ",\n  `active` tinyint NOT NULL DEFAULT '1'\n)", $tableBlock);
            }
            $content = str_replace($tableBlock, $newBlock, $content);
            echo "  Added active column to CREATE TABLE" . $nl;
        }
    }

    // 2. Update INSERT statements
    // This part is tricky with multiple inserts. Let's do it line by line.
    $lines = explode("\n", $content);
    $updatedLines = [];
    foreach ($lines as $line) {
        if (preg_match('/^INSERT INTO `' . preg_quote($table, '/') . '` \((.*?)\) VALUES \((.*?)\);/', $line, $m)) {
            $cols = $m[1];
            $vals = $m[2];
            if (strpos($cols, '`active`') === false) {
                $newCols = str_replace('`created_at`', '`active`, `created_at`', $cols);
                if ($newCols === $cols) {
                    $newCols = $cols . ', `active`';
                    $newVals = $vals . ", '1'";
                } else {
                    $newVals = preg_replace('/(\'202[0-9])/', "'1', $1", $vals);
                }
                $line = "INSERT INTO `$table` ($newCols) VALUES ($newVals);";
                echo "  Updated INSERT statement line" . $nl;
            }
        }
        $updatedLines[] = $line;
    }
    $content = implode("\n", $updatedLines);
    
    // 3. Update Triggers
    $triggerPrefix = "trg_{$table}_audit_";
    
    // insert/update
    $content = preg_replace_callback(
        '/CREATE TRIGGER `' . preg_quote($triggerPrefix, '/') . '(insert|update)`.+?JSON_OBJECT\((.+?)\)/is',
        function($m) {
            if (strpos($m[2], "'active'") === false) {
                $replacement = trim($m[2]) . ", 'active', NEW.`active`";
                return str_replace($m[2], $replacement, $m[0]);
            }
            return $m[0];
        },
        $content
    );
    
    // delete
    $content = preg_replace_callback(
        '/CREATE TRIGGER `' . preg_quote($triggerPrefix, '/') . 'delete`.+?JSON_OBJECT\((.+?)\)/is',
        function($m) {
            if (strpos($m[2], "'active'") === false) {
                $replacement = trim($m[2]) . ", 'active', OLD.`active`";
                return str_replace($m[2], $replacement, $m[0]);
            }
            return $m[0];
        },
        $content
    );
}

file_put_contents($sqlPath, $content);
echo "Completed broad update of database.sql" . $nl;

itm_script_output_end();
