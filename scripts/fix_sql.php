<?php
/**
 * Fix cable_colors and switch_port_types table structure in db/.
 *
 * Browser: dry-run by default; ?apply=1 (Admin) writes db/01_schema.sql.
 * CLI: php scripts/fix_sql.php then php scripts/fix_sql.php --apply
 */
require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';
require_once __DIR__ . '/lib/itm_fix_script_report.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

$boot = itm_apply_script_bootstrap('Fix SQL (Cable Colors & Switch Port Types)');
$nl = $boot['nl'];

$sqlPath = itm_database_sql_schema_path();
$original = file_get_contents($sqlPath);
$content = $original;

$sqlBundleItems = [];

if (strpos($original, 'CREATE TABLE `cable_colors`') !== false) {
    if (preg_match('/CREATE TABLE `cable_colors` \(.*?\) ENGINE=/s', $original, $matches)
        && strpos($matches[0], '`active`') === false
    ) {
        $sqlBundleItems[] = 'db/01_schema.sql: cable_colors CREATE TABLE missing `active`';
    }
    if (strpos($original, 'INSERT INTO `cable_colors` (`company_id`, `id`, `color_name`, `hex_color`, `comments`, `created_at`)') !== false) {
        $sqlBundleItems[] = 'db/01_schema.sql: cable_colors INSERT column list missing `active`';
    }
}

if (strpos($original, 'CREATE TABLE `switch_port_types`') !== false) {
    if (preg_match('/CREATE TABLE `switch_port_types` \(.*?\) ENGINE=/s', $original, $matches)
        && strpos($matches[0], '`active`') === false
    ) {
        $sqlBundleItems[] = 'db/01_schema.sql: switch_port_types CREATE TABLE missing `active`';
    }
    if (strpos($original, 'INSERT INTO `switch_port_types` (`company_id`, `id`, `type`, `created_at`)') !== false) {
        $sqlBundleItems[] = 'db/01_schema.sql: switch_port_types INSERT column list missing `active`';
    }
}

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
$stillNeedsFixes = $changed;
$fixItems = [];

if ($changed && $sqlBundleItems === []) {
    $sqlBundleItems[] = 'db/01_schema.sql: cable_colors and/or switch_port_types active column or INSERT alignment drift';
}

if ($changed) {
    $fixItems[] = 'db/01_schema.sql: patch cable_colors and switch_port_types `active` column + INSERT alignment';
    if ($boot['apply']) {
        file_put_contents($sqlPath, $content);
    }
}

itm_fix_script_report_finish(
    $boot['apply'],
    $boot['is_cli'],
    $stillNeedsFixes,
    $nl,
    'fix_sql.php',
    [itm_fix_script_report_na_item()],
    $sqlBundleItems,
    $fixItems
);

itm_script_output_end();
