<?php
/**
 * Tickets sample-data parent seeding (shared by index.php and CLI regression tests).
 */

/**
 * Sample ticket codes from database.sql that must link to Primary File Server.
 *
 * @return string[]
 */
function tickets_sample_ticket_external_codes(): array
{
    return ['TCK-0001'];
}

/**
 * Recursively seed database.sql parents required by a target table.
 */
function tickets_seed_database_sql_parents_for_table(mysqli $conn, string $table, int $companyId, array &$visited = []): void
{
    if ($companyId <= 0 || !itm_is_safe_identifier($table) || isset($visited[$table])) {
        return;
    }
    $visited[$table] = true;

    if (!function_exists('itm_table_outbound_fk_map') || !function_exists('itm_seed_table_from_database_sql')) {
        return;
    }

    foreach (itm_table_outbound_fk_map($conn, $table) as $fkMeta) {
        $parentTable = (string)($fkMeta['REFERENCED_TABLE_NAME'] ?? '');
        if ($parentTable === '' || !itm_is_safe_identifier($parentTable)) {
            continue;
        }
        if (in_array($parentTable, ['companies', 'employees'], true)) {
            continue;
        }

        tickets_seed_database_sql_parents_for_table($conn, $parentTable, $companyId, $visited);

        $parentErr = '';
        itm_seed_table_from_database_sql($conn, $parentTable, $companyId, $parentErr);
    }
}

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

    tickets_seed_sample_equipment($conn, $companyId);
}

/**
 * Ensure Primary File Server exists so sample ticket equipment_id can link for delete/in-use tests.
 */
function tickets_seed_sample_equipment(mysqli $conn, int $companyId): void
{
    if ($companyId <= 0) {
        return;
    }

    if (tickets_sample_primary_file_server_id($conn, $companyId) > 0) {
        return;
    }

    if (function_exists('itm_seed_table_from_database_sql')) {
        tickets_seed_database_sql_parents_for_table($conn, 'equipment', $companyId);
        $seedErr = '';
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
        . " AND name = 'Primary File Server' AND deleted_at IS NULL ORDER BY id ASC LIMIT 1"
    );
    $row = ($res) ? mysqli_fetch_assoc($res) : null;

    return is_array($row) ? (int)($row['id'] ?? 0) : 0;
}

/**
 * Link sample tickets to Primary File Server (null, zero, stale, or wrong FK remap ids).
 */
function tickets_repair_sample_equipment_links(mysqli $conn, int $companyId): int
{
    $equipmentId = tickets_sample_primary_file_server_id($conn, $companyId);
    if ($equipmentId <= 0) {
        return 0;
    }

    $updatedTotal = 0;
    foreach (tickets_sample_ticket_external_codes() as $externalCode) {
        if (!is_string($externalCode) || $externalCode === '') {
            continue;
        }

        $stmt = mysqli_prepare(
            $conn,
            'UPDATE tickets SET equipment_id = ? WHERE company_id = ? AND ticket_external_code = ?'
            . ' AND (equipment_id IS NULL OR equipment_id = 0 OR equipment_id <> ?)'
        );
        if (!$stmt) {
            continue;
        }

        mysqli_stmt_bind_param($stmt, 'iisi', $equipmentId, $companyId, $externalCode, $equipmentId);
        mysqli_stmt_execute($stmt);
        $updatedTotal += max(0, mysqli_affected_rows($conn));
        mysqli_stmt_close($stmt);
    }

    return $updatedTotal;
}
