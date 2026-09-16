<?php

if (!function_exists('idf_ensure_idf_positions_capacity_columns')) {
    /**
     * Why: Rack positions store RJ45/SFP capacity on the position row; legacy DBs still use port_count until migrated.
     */
    function idf_ensure_idf_positions_capacity_columns(mysqli $conn): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        $hasPortCount = function_exists('itm_table_has_column') && itm_table_has_column($conn, 'idf_positions', 'port_count');

        $hasRj45Count = function_exists('itm_table_has_column') && itm_table_has_column($conn, 'idf_positions', 'rj45_count');

        if ($hasPortCount && !$hasRj45Count) {
            mysqli_query(
                $conn,
                "ALTER TABLE `idf_positions`
                 CHANGE COLUMN `port_count` `rj45_count` smallint NOT NULL DEFAULT 0"
            );
            $hasRj45Count = true;
        }

        $hasSfpCount = function_exists('itm_table_has_column') && itm_table_has_column($conn, 'idf_positions', 'sfp_count');

        if (!$hasSfpCount) {
            $afterColumn = $hasRj45Count ? 'rj45_count' : ($hasPortCount ? 'port_count' : 'equipment_id');
            mysqli_query(
                $conn,
                "ALTER TABLE `idf_positions`
                 ADD COLUMN `sfp_count` smallint NOT NULL DEFAULT 0 AFTER `{$afterColumn}`"
            );
        }
    }
}
