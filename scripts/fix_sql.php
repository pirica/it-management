<?php
/**
 * Fix cable_colors and switch_port_types table structure in db/.
 *
 * Browser: dry-run by default; ?apply=1 (Admin) writes db/01_schema.sql.
 * CLI: php scripts/fix_sql.php then php scripts/fix_sql.php --apply
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

$boot = itm_apply_script_bootstrap('Fix SQL (Cable Colors & Switch Port Types)');
$nl = $boot['nl'];

$sqlPath = itm_database_sql_schema_path();
$content = file_get_contents($sqlPath);
$original = $content;

// Fix cable_colors table structure if already updated
if (strpos($content, 'CREATE TABLE `cable_colors`') !== false) {
    if (strpos($content, '`active` tinyint NOT NULL DEFAULT \'1\'') === false) {
        $content = preg_replace(
            '/(`comments` varchar\(255\) [^,]+,)/',
            "$1\n  `active` tinyint NOT NULL DEFAULT '1',",
            $content
        );
    }

    $content = preg_replace(
        '/INSERT INTO `cable_colors` \(`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`\)/',
        "INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `active`, `created_at`)",
        $content
    );

    $content = preg_replace(
        '/(INSERT INTO `cable_colors` .*? VALUES \(.*? \', \')(2026)/',
        "$1'1', '$2",
        $content
    );
}

if (strpos($content, 'CREATE TABLE `switch_port_types`') !== false) {
    if (strpos($content, '`active` tinyint NOT NULL DEFAULT \'1\'') === false) {
        $content = preg_replace(
            '/(`type` varchar\(20\) [^,]+,)/',
            "$1\n  `active` tinyint NOT NULL DEFAULT '1',",
            $content
        );
    }

    $content = str_replace('`active`, `active`', '`active`', $content);

    $content = preg_replace(
        '/INSERT INTO `switch_port_types` \(`company_id`, `id`, `type`, `created_at`\)/',
        "INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `active`, `created_at`)",
        $content
    );

    $content = preg_replace(
        '/(INSERT INTO `switch_port_types` .*? VALUES \(\'[^\']+\', \'[^\']+\', \'[^\']+\', )(?!\'1\', )(\'2026)/',
        "$1'1', $2",
        $content
    );
}

$changed = ($content !== $original);

if ($boot['apply']) {
    if ($changed) {
        file_put_contents($sqlPath, $content);
        echo 'Updated db/01_schema.sql for cable_colors and switch_port_types.' . $nl;
    } else {
        echo 'Nothing to change in db/01_schema.sql.' . $nl;
    }
} elseif ($changed) {
    echo 'Would update db/01_schema.sql for cable_colors and switch_port_types.' . $nl;
} else {
    echo 'Dry-run: db/01_schema.sql already compliant for cable_colors and switch_port_types.' . $nl;
}

itm_apply_script_finish_hint($boot['apply'], $boot['is_cli'], $changed ? 1 : 0, $nl, 'fix_sql.php');
itm_script_output_end();
