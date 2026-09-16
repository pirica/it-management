<?php
/**
 * Regenerate four portal_ui_* satellite CREATE TABLE blocks in db/01_schema.sql
 * and write db/migrations/hotel_booking_portal_ui_copy_gap.sql.
 *
 * CLI: php scripts/apply_portal_ui_copy_gap_schema.php --apply
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/itm_hotel_booking_portal_ui_copy.php';

$apply = in_array('--apply', $argv ?? [], true);
$repoRoot = dirname(__DIR__);
$schemaPath = $repoRoot . '/db/01_schema.sql';
$migrationPath = $repoRoot . '/db/migrations/hotel_booking_portal_ui_copy_gap.sql';

$tables = itm_hotel_booking_portal_ui_copy_storage_tables();
$satelliteSql = '';
foreach ($tables as $table) {
    $satelliteSql .= itm_hotel_booking_portal_ui_copy_sql_create_table($table) . "\n\n";
}

$schema = (string) file_get_contents($schemaPath);
$start = strpos($schema, 'DROP TABLE IF EXISTS `hotel_booking_portal_ui_copy_home`');
if ($start === false) {
    fwrite(STDERR, "hotel_booking_portal_ui_copy_home block not found in 01_schema.sql\n");
    exit(1);
}
$confirmDrop = strpos($schema, 'DROP TABLE IF EXISTS `hotel_booking_portal_ui_copy_confirm`', $start);
if ($confirmDrop === false) {
    fwrite(STDERR, "hotel_booking_portal_ui_copy_confirm block not found\n");
    exit(1);
}
$endParen = strpos($schema, ') ENGINE=InnoDB', $confirmDrop);
if ($endParen === false) {
    fwrite(STDERR, "confirm table ENGINE line not found\n");
    exit(1);
}
$end = strpos($schema, "\n", $endParen);
if ($end === false) {
    $end = strlen($schema);
} else {
    $end++;
}

$newSchema = substr($schema, 0, $start) . $satelliteSql . substr($schema, $end);

$migration = "-- Portal UI copy gap — additional portal_ui_* columns on satellite tables.\n"
    . "-- Apply: php scripts/migrate.php --apply\n\n"
    . $satelliteSql;

foreach ($tables as $table) {
    $migration .= "INSERT INTO `{$table}` (`company_id`, `created_at`)\n"
        . "SELECT c.`id`, NOW() FROM `companies` c\n"
        . "WHERE NOT EXISTS (SELECT 1 FROM `{$table}` t WHERE t.`company_id` = c.`id`);\n\n";
}

if (!$apply) {
    echo '[DRY] Would update db/01_schema.sql and db/migrations/hotel_booking_portal_ui_copy_gap.sql' . PHP_EOL;
    echo 'Registry keys: ' . count(itm_hotel_booking_portal_ui_copy_registry()) . PHP_EOL;
    exit(0);
}

file_put_contents($schemaPath, $newSchema);
file_put_contents($migrationPath, $migration);
echo '[PASS] Updated db/01_schema.sql and db/migrations/hotel_booking_portal_ui_copy_gap.sql' . PHP_EOL;
echo 'Registry keys: ' . count(itm_hotel_booking_portal_ui_copy_registry()) . PHP_EOL;
