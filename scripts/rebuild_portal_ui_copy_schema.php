<?php
/**
 * Remove portal_ui_* from hotel_booking_settings and emit four satellite CREATE TABLE blocks.
 * CLI: php scripts/rebuild_portal_ui_copy_schema.php --apply
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/itm_hotel_booking_portal_ui_copy.php';

$apply = in_array('--apply', $argv ?? [], true);
$repoRoot = dirname(__DIR__);

$schemaPath = $repoRoot . '/db/01_schema.sql';
$migrationPath = $repoRoot . '/db/migrations/hotel_booking_portal_ui_copy.sql';

$schema = (string) file_get_contents($schemaPath);
if ($schema === '') {
    fwrite(STDERR, "Cannot read 01_schema.sql\n");
    exit(1);
}

$stripped = preg_replace(
    '/\n  `portal_ui_[^`]+`[^\n]*(?:\n  `portal_ui_[^`]+`[^\n]*)*\n/',
    "\n",
    $schema,
    1,
    $removed
);
if ($removed !== 1) {
    fwrite(STDERR, "portal_ui block not found in 01_schema.sql\n");
    exit(1);
}

$satelliteSql = "\n";
foreach (itm_hotel_booking_portal_ui_copy_storage_tables() as $table) {
    $satelliteSql .= itm_hotel_booking_portal_ui_copy_sql_create_table($table) . "\n\n";
}

$marker = "CREATE TABLE `hotel_booking_settings`";
$pos = strpos($stripped, $marker);
if ($pos === false) {
    fwrite(STDERR, "hotel_booking_settings not found\n");
    exit(1);
}
$afterSettings = strpos($stripped, "\n\nCREATE TABLE ", $pos + 1);
if ($afterSettings === false) {
    fwrite(STDERR, "next table after hotel_booking_settings not found\n");
    exit(1);
}

$newSchema = substr($stripped, 0, $afterSettings) . "\n" . $satelliteSql . substr($stripped, $afterSettings);

$migration = "-- Portal UI copy — four satellite tables (267 portal_ui_* TEXT columns; InnoDB row-size limit).\n"
    . "-- Apply: php scripts/migrate.php --apply\n"
    . "-- Non-destructive to hotel_booking_settings; seeds one row per company per table.\n\n"
    . $satelliteSql;

foreach (itm_hotel_booking_portal_ui_copy_storage_tables() as $table) {
    $migration .= "INSERT INTO `{$table}` (`company_id`, `created_at`)\n"
        . "SELECT c.`id`, NOW() FROM `companies` c\n"
        . "WHERE NOT EXISTS (SELECT 1 FROM `{$table}` t WHERE t.`company_id` = c.`id`);\n\n";
}

if ($apply) {
    file_put_contents($schemaPath, $newSchema);
    file_put_contents($migrationPath, $migration);
    echo "[PASS] Updated db/01_schema.sql and db/migrations/hotel_booking_portal_ui_copy.sql\n";
} else {
    echo "[DRY] Would strip portal_ui from hotel_booking_settings and write 4 satellite tables.\n";
}
