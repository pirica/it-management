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

        $hasPortCount = false;
        $portCountColRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_positions` LIKE 'port_count'");
        if ($portCountColRes && mysqli_num_rows($portCountColRes) > 0) {
            $hasPortCount = true;
        }

        $hasRj45Count = false;
        $rj45ColRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_positions` LIKE 'rj45_count'");
        if ($rj45ColRes && mysqli_num_rows($rj45ColRes) > 0) {
            $hasRj45Count = true;
        }

        if ($hasPortCount && !$hasRj45Count) {
            mysqli_query(
                $conn,
                "ALTER TABLE `idf_positions`
                 CHANGE COLUMN `port_count` `rj45_count` smallint NOT NULL DEFAULT 0"
            );
            $hasRj45Count = true;
        }

        $hasSfpCount = false;
        $sfpColRes = mysqli_query($conn, "SHOW COLUMNS FROM `idf_positions` LIKE 'sfp_count'");
        if ($sfpColRes && mysqli_num_rows($sfpColRes) > 0) {
            $hasSfpCount = true;
        }

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
