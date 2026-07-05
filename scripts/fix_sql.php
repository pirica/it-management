<?php
/**
 * CLI-only: fix cable_colors and switch_port_types table structure in database.sql.
 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    require_once __DIR__ . '/lib/script_browser_nav.php';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>CLI only</title></head><body style="font-family:Segoe UI,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
    echo '<p><strong>CLI only.</strong> This tool must be run from the terminal.</p><pre>php scripts/fix_sql.php</pre></body></html>';
    exit(1);
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/lib/script_cli_output.php';

$nl = itm_script_output_nl();
itm_script_output_begin('Fix SQL (Cable Colors & Switch Port Types)');

$sqlPath = 'database.sql';
$content = file_get_contents($sqlPath);

// Fix cable_colors table structure if already updated
if (strpos($content, 'CREATE TABLE `cable_colors`') !== false) {
    // Ensure active column is there
    if (strpos($content, '`active` tinyint NOT NULL DEFAULT \'1\'') === false) {
        $content = preg_replace(
            '/(`comments` varchar\(255\) [^,]+,)/',
            "$1\n  `active` tinyint NOT NULL DEFAULT '1',",
            $content
        );
    }
    
    // Ensure INSERTS have active column and value '1'
    // First, fix column list
    $content = preg_replace(
        '/INSERT INTO `cable_colors` \(`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`\)/',
        "INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `active`, `created_at`)",
        $content
    );
    
    // Then, fix values
    $content = preg_replace(
        '/(INSERT INTO `cable_colors` .*? VALUES \(.*? \', \')(2026)/',
        "$1'1', '$2",
        $content
    );
}

// Fix switch_port_types table structure
if (strpos($content, 'CREATE TABLE `switch_port_types`') !== false) {
    // Ensure active column is there
    if (strpos($content, '`active` tinyint NOT NULL DEFAULT \'1\'') === false) {
        $content = preg_replace(
            '/(`type` varchar\(20\) [^,]+,)/',
            "$1\n  `active` tinyint NOT NULL DEFAULT '1',",
            $content
        );
    }

    // Clean up any double active columns in INSERT list
    $content = str_replace('`active`, `active`', '`active`', $content);

    // Fix column list if missing active
    $content = preg_replace(
        '/INSERT INTO `switch_port_types` \(`company_id`, `id`, `type`, `created_at`\)/',
        "INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `active`, `created_at`)",
        $content
    );
    
    // Fix values - handle those with 1 already and those without
    $content = preg_replace(
        '/(INSERT INTO `switch_port_types` .*? VALUES \(\'[^\']+\', \'[^\']+\', \'[^\']+\', )(?!\'1\', )(\'2026)/',
        "$1'1', $2",
        $content
    );
}

file_put_contents($sqlPath, $content);
echo "Updated database.sql for cable_colors and switch_port_types" . $nl;

itm_script_output_end();
