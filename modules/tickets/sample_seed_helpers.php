<?php
/**
 * Tickets sample-data parent seeding (shared by index.php and CLI regression tests).
 */

/**
 * Seed ticket lookup parents from database.sql when tenant tables are empty.
 */
function tickets_seed_lookup_parents(mysqli $conn, int $companyId): void
{
    if ($companyId <= 0) {
        return;
    }

    foreach (['ticket_categories', 'ticket_statuses', 'ticket_priorities'] as $parentTable) {
        if (!itm_is_safe_identifier($parentTable)) {
            continue;
        }

        $countRes = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM `' . $parentTable . '` WHERE company_id = ' . (int)$companyId);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        if ((int)($countRow['total'] ?? 0) > 0) {
            continue;
        }

        $parentErr = '';
        if (function_exists('itm_seed_table_from_database_sql')) {
            itm_seed_table_from_database_sql($conn, $parentTable, $companyId, $parentErr);
        }
    }

    tickets_seed_sample_asset_equipment($conn, $companyId);
}

/**
 * Ensure Primary File Server exists so sample ticket asset_id can link for delete/in-use tests.
 */
function tickets_seed_sample_asset_equipment(mysqli $conn, int $companyId): void
{
    if ($companyId <= 0) {
        return;
    }

    $matchRes = mysqli_query(
        $conn,
        "SELECT id FROM equipment WHERE company_id = " . (int)$companyId
        . " AND name = 'Primary File Server' AND active = 1 ORDER BY id ASC LIMIT 1"
    );
    if ($matchRes && mysqli_num_rows($matchRes) > 0) {
        return;
    }

    $seedErr = '';
    if (function_exists('itm_seed_table_from_database_sql')) {
        itm_seed_table_from_database_sql($conn, 'equipment', $companyId, $seedErr);
    }
}

/**
 * Resolve tenant Primary File Server id used by database.sql ticket samples.
 */
function tickets_sample_primary_file_server_id(mysqli $conn, int $companyId): int
{
    if ($companyId <= 0) {
        return 0;
    }

    $res = mysqli_query(
        $conn,
        "SELECT id FROM equipment WHERE company_id = " . (int)$companyId
        . " AND name = 'Primary File Server' AND active = 1 ORDER BY id ASC LIMIT 1"
    );
    $row = ($res) ? mysqli_fetch_assoc($res) : null;

    return is_array($row) ? (int)($row['id'] ?? 0) : 0;
}

/**
 * Link seeded tickets to Primary File Server when asset_id was nulled during FK remap fallback.
 */
function tickets_repair_sample_asset_links(mysqli $conn, int $companyId): int
{
    $equipmentId = tickets_sample_primary_file_server_id($conn, $companyId);
    if ($equipmentId <= 0) {
        return 0;
    }

    $stmt = mysqli_prepare(
        $conn,
        'UPDATE tickets SET asset_id = ? WHERE company_id = ? AND (asset_id IS NULL OR asset_id = 0)'
    );
    if (!$stmt) {
        return 0;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $equipmentId, $companyId);
    mysqli_stmt_execute($stmt);
    $updated = mysqli_affected_rows($conn);
    mysqli_stmt_close($stmt);

    return max(0, $updated);
}
