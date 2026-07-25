<?php
/**
 * Broad-spectrum SQL cleanup utility for db/01_schema.sql.
 *
 * Browser: dry-run by default; ?apply=1 (Admin) writes db/01_schema.sql.
 * CLI: php scripts/fix_sql_broad.php then php scripts/fix_sql_broad.php --apply
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

$boot = itm_apply_script_bootstrap('Fix SQL Broad');
$nl = $boot['nl'];

$sqlPath = itm_database_sql_schema_path();
$content = file_get_contents($sqlPath);
$original = $content;

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
    'floor_plan_item_tags',
];

$logLines = [];

foreach ($tablesToFix as $table) {
    $tableLog = [];

    $pattern = '/(CREATE TABLE `' . preg_quote($table, '/') . '` \(.*?\)) ENGINE=/s';
    if (preg_match($pattern, $content, $matches)) {
        $tableBlock = $matches[1];
        if (strpos($tableBlock, '`active`') === false) {
            if (preg_match('/  `created_at`/', $tableBlock)) {
                $newBlock = preg_replace('/(  `created_at`)/', "  `active` tinyint NOT NULL DEFAULT '1',\n$1", $tableBlock);
            } else {
                $newBlock = preg_replace('/\s*\)$/', ",\n  `active` tinyint NOT NULL DEFAULT '1'\n)", $tableBlock);
            }
            $content = str_replace($tableBlock, $newBlock, $content);
            $tableLog[] = 'add active column to CREATE TABLE';
        }
    }

    $lines = explode("\n", $content);
    $updatedLines = [];
    $insertFixes = 0;
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
                $insertFixes++;
            }
        }
        $updatedLines[] = $line;
    }
    $content = implode("\n", $updatedLines);
    if ($insertFixes > 0) {
        $tableLog[] = $insertFixes . ' INSERT line(s)';
    }

    $triggerPrefix = "trg_{$table}_audit_";

    $content = preg_replace_callback(
        '/CREATE TRIGGER `' . preg_quote($triggerPrefix, '/') . '(insert|update)`.+?JSON_OBJECT\((.+?)\)/is',
        function ($m) {
            if (strpos($m[2], "'active'") === false) {
                $replacement = trim($m[2]) . ", 'active', NEW.`active`";
                return str_replace($m[2], $replacement, $m[0]);
            }
            return $m[0];
        },
        $content
    );

    $content = preg_replace_callback(
        '/CREATE TRIGGER `' . preg_quote($triggerPrefix, '/') . 'delete`.+?JSON_OBJECT\((.+?)\)/is',
        function ($m) {
            if (strpos($m[2], "'active'") === false) {
                $replacement = trim($m[2]) . ", 'active', OLD.`active`";
                return str_replace($m[2], $replacement, $m[0]);
            }
            return $m[0];
        },
        $content
    );

    if ($tableLog !== []) {
        $logLines[] = $table . ': ' . implode('; ', $tableLog);
    }
}

$changed = ($content !== $original);

if ($logLines !== []) {
    itm_apply_script_echo_list($boot['apply'] ? 'Changes applied' : 'Would change', $logLines);
} else {
    echo ($boot['apply'] ? 'No tables needed updates.' : 'Dry-run: no broad SQL fixes needed.') . $nl;
}

if ($boot['apply']) {
    if ($changed) {
        file_put_contents($sqlPath, $content);
        echo 'Completed broad update of db/01_schema.sql.' . $nl;
    }
} elseif ($changed) {
    echo 'Re-run with --apply or ?apply=1 to write db/01_schema.sql.' . $nl;
}

itm_apply_script_finish_hint($boot['apply'], $boot['is_cli'], $changed ? 1 : 0, $nl, 'fix_sql_broad.php');
itm_script_output_end();
