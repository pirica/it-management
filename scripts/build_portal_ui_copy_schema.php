<?php
/**
 * Emit portal_ui_* column DDL for db/01_schema.sql patch review.
 * CLI: php scripts/build_portal_ui_copy_schema.php
 */
require_once dirname(__DIR__) . '/includes/itm_hotel_booking_portal_ui_copy.php';

$lines = [];
foreach (itm_hotel_booking_portal_ui_copy_registry() as $row) {
    $ddl = itm_hotel_booking_portal_ui_copy_sql_column_definition($row);
    if ($ddl !== '') {
        $lines[] = $ddl;
    }
}

echo '-- portal_ui_* columns: ' . count($lines) . "\n";
echo implode("\n", $lines) . "\n";
