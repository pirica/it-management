<?php
/**
 * Switch Port Fetcher API
 * 
 * An AJAX endpoint that retrieves all ports for a specific network switch.
 * Handles automatic "seeding" of missing ports based on switch capacity.
 * Returns ports, available statuses, colors, and VLAN configurations.
 */

require_once __DIR__ . '/itm_script_entry_guard.php';
if (itm_skip_http_entry_unless_direct(__FILE__)) {
    return;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/switch_port_api_helpers.php';
require_once __DIR__ . '/itm_api_json_response.php';

$company_id = isset($company_id) ? (int)$company_id : 0;

header('Content-Type: application/json; charset=utf-8');
// Disable direct error output to avoid corrupting JSON response
ini_set('display_errors', '0');

// Access Control: Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    itm_api_json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

// CSRF Validation (checks multiple possible header/body locations)
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode((string)$rawInput, true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}
// Why: Tenant scope comes from session only; discard client-supplied company_id if present.
unset($jsonInput['company_id'], $_POST['company_id']);

$csrfToken = (string)($jsonInput['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if (!itm_validate_csrf_token($csrfToken)) {
    itm_api_json_response(['success' => false, 'error' => 'Invalid CSRF token'], 403);
}

// Ensure the user has a valid company context (session via config.php — never from request payload)
if ($company_id <= 0) {
    itm_api_json_response(['success' => false, 'error' => 'Tenant context required (company_id in session).'], 403);
}

/**
 * Fetches all possible port types (RJ45, SFP, etc.) from the catalog
 */
function fetch_available_port_types(mysqli $conn): array
{
    $rows = [];
    $stmt = mysqli_prepare($conn, 'SELECT type FROM switch_port_types ORDER BY id ASC');
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        foreach (itm_mysqli_stmt_fetch_all_assoc($stmt) as $row) {
            $normalized = normalize_port_type((string)($row['type'] ?? ''));
            if ($normalized === '') {
                continue;
            }
            $rows[$normalized] = true;
        }
        mysqli_stmt_close($stmt);
    }
    return array_keys($rows);
}

/**
 * Resolves the preferred default fiber label from equipment_fiber.name
 * using tenant rows first, then global fallback rows.
 */
function fetch_default_fiber_name(mysqli $conn, int $companyId): string
{
    $tableExists = function_exists('itm_table_exists')
        ? (bool)itm_table_exists($conn, 'equipment_fiber')
        : false;
    if (!$tableExists) {
        $tableCheckStmt = mysqli_prepare($conn, "SHOW TABLES LIKE 'equipment_fiber'");
        if ($tableCheckStmt) {
            mysqli_stmt_execute($tableCheckStmt);
            $tableExists = itm_mysqli_stmt_fetch_assoc($tableCheckStmt) !== null;
            mysqli_stmt_close($tableCheckStmt);
        }
    }

    if (!$tableExists) {
        return '';
    }

    $hasCompanyId = itm_table_has_column($conn, 'equipment_fiber', 'company_id');
    $orderSql = "ORDER BY
        CASE
            WHEN LOWER(name) LIKE '%sfp%' THEN 0
            ELSE 1
        END,
        id ASC
        LIMIT 1";

    if ($hasCompanyId && $companyId > 0) {
        $tenantSql = "SELECT name FROM equipment_fiber WHERE company_id = ? {$orderSql}";
        $tenantStmt = mysqli_prepare($conn, $tenantSql);
        if ($tenantStmt) {
            mysqli_stmt_bind_param($tenantStmt, 'i', $companyId);
            mysqli_stmt_execute($tenantStmt);
            $tenantRow = itm_mysqli_stmt_fetch_assoc($tenantStmt);
            mysqli_stmt_close($tenantStmt);
            if (is_array($tenantRow) && trim((string)($tenantRow['name'] ?? '')) !== '') {
                return strtolower(trim((string)$tenantRow['name']));
            }
        }
    }

    $globalRes = mysqli_query($conn, "SELECT name FROM equipment_fiber {$orderSql}");
    $globalRow = $globalRes ? mysqli_fetch_assoc($globalRes) : null;
    if (is_array($globalRow) && trim((string)($globalRow['name'] ?? '')) !== '') {
        return strtolower(trim((string)$globalRow['name']));
    }

    return '';
}

/**
 * Finds an ID in a list of items by name
 */
function lookup_id_by_name(array $items, string $wanted, int $fallback = 0): int
{
    foreach ($items as $item) {
        if (strtolower(trim((string)$item['name'])) === strtolower(trim($wanted))) {
            return (int)$item['id'];
        }
    }
    return $fallback > 0 ? $fallback : (int)($items[0]['id'] ?? 0);
}

/**
 * Normalizes port type strings to standard internal identifiers
 */
function normalize_port_type(string $portType): string
{
    $normalized = strtolower(trim($portType));
    $normalized = str_replace(['+', '-', '/'], [' plus ', ' ', ' '], $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);

    if (strpos($normalized, 'sfp') !== false) {
        return 'sfp';
    }
    return 'rj45';
}

function switch_ports_port_type_is_numeric(mysqli $conn): bool
{
    $sql = "SELECT DATA_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'switch_ports'
              AND COLUMN_NAME = 'port_type'
            LIMIT 1";
    $res = mysqli_query($conn, $sql);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $dataType = strtolower(trim((string)($row['DATA_TYPE'] ?? '')));
    return in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true);
}

function fetch_port_type_id_map(mysqli $conn, int $companyId): array
{
    $map = [];
    $stmt = mysqli_prepare($conn, 'SELECT id, type FROM switch_port_types WHERE company_id = ? ORDER BY id ASC');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        foreach (itm_mysqli_stmt_fetch_all_assoc($stmt) as $row) {
            $normalized = normalize_port_type((string)($row['type'] ?? ''));
            if ($normalized === '' || isset($map[$normalized])) {
                continue;
            }
            $map[$normalized] = (int)($row['id'] ?? 0);
        }
        mysqli_stmt_close($stmt);
    }
    return $map;
}

// Feature Detection: Check for modern schema vs legacy schema
$hasEquipmentId = itm_table_has_column($conn, 'switch_ports', 'equipment_id');
$hasPortType = itm_table_has_column($conn, 'switch_ports', 'port_type');
$hasStatusId = itm_table_has_column($conn, 'switch_ports', 'status_id');
$hasColorId = itm_table_has_column($conn, 'switch_ports', 'color_id');
$hasRj45SpeedId = itm_table_has_column($conn, 'switch_ports', 'rj45_speed_id');
$hasVlanId = itm_table_has_column($conn, 'switch_ports', 'vlan_id');
$hasLegacyNumberPort = itm_table_has_column($conn, 'equipment', 'numberport');
$hasSwitchFiberCountId = itm_table_has_column($conn, 'equipment', 'switch_fiber_count_id');
$hasSwitchFiberPortLabelColumn = itm_table_has_column($conn, 'equipment', 'switch_fiber_port_label');
$isNumericPortTypeColumn = $hasPortType ? switch_ports_port_type_is_numeric($conn) : false;

$hasPortTypesTable = false;
$stmtTable = mysqli_prepare($conn, "SHOW TABLES LIKE 'switch_port_types'");
if ($stmtTable) {
    mysqli_stmt_execute($stmtTable);
    $hasPortTypesTable = itm_mysqli_stmt_fetch_assoc($stmtTable) !== null;
    mysqli_stmt_close($stmtTable);
}

$availablePortTypes = $hasPortTypesTable
    ? fetch_available_port_types($conn)
    : ['rj45', 'sfp'];
if (!in_array('rj45', $availablePortTypes, true)) {
    $availablePortTypes[] = 'rj45';
}

// Schema validation
if (!$hasStatusId || !$hasColorId) {
    itm_api_json_response(['success' => false, 'error' => 'switch_ports schema is missing status_id/color_id columns'], 500);
}

// Pre-fetch lookups
$statuses = fetch_lookup_map($conn, 'switch_status', 'status');
$colors = fetch_lookup_map($conn, 'cable_colors', 'color_name');
$rj45Speeds = fetch_lookup_map($conn, 'rj45_speed', 'cable_type');
$vlans = fetch_company_vlans($conn, (int)$company_id);
$fiberPorts = fetch_lookup_map($conn, 'equipment_fiber', 'name');
$fiberPatches = fetch_lookup_map($conn, 'equipment_fiber_patch', 'name');
$fiberRacks = fetch_lookup_map($conn, 'equipment_fiber_rack', 'name');
$idfOptions = fetch_lookup_map($conn, 'idfs', 'idf_code');
$rackOptions = fetch_lookup_map($conn, 'racks', 'name');
$locationOptions = fetch_lookup_map($conn, 'it_locations', 'name');
if (empty($statuses) || empty($colors)) {
    itm_api_json_response(['success' => false, 'error' => 'switch_status/cable_colors lookup tables are empty'], 500);
}

$defaultStatusId = lookup_id_by_name($statuses, 'Unknown');
$defaultColorId = lookup_id_by_name($colors, 'grey', lookup_id_by_name($colors, 'gray'));

$switchId = (int)($jsonInput['switch_id'] ?? ($_POST['switch_id'] ?? 0));
if ($switchId <= 0) {
    itm_api_json_response(['success' => false, 'error' => 'Missing switch id'], 400);
}

// Fetch switch configuration from the equipment table
$legacyNumberPortSql = $hasLegacyNumberPort ? 'e.numberport AS legacy_numberport,' : 'NULL AS legacy_numberport,';
$fiberCountSelectSql = $hasSwitchFiberCountId ? "COALESCE(efc.name, '0') AS fiber_count," : "COALESCE(e.switch_fiber_ports_number, 0) AS fiber_count,";
$fiberPortLabelSelectSql = $hasSwitchFiberPortLabelColumn ? "COALESCE(e.switch_fiber_port_label, '') AS fiber_port_label" : "'' AS fiber_port_label";
$fiberCountJoinSql = $hasSwitchFiberCountId ? 'LEFT JOIN equipment_fiber_count efc ON efc.id = e.switch_fiber_count_id' : '';
$switchSql = "SELECT e.id, e.name, {$legacyNumberPortSql} COALESCE(er.name, '24 ports') AS rj45_name,
                     COALESCE(ef.name, '') AS fiber_name, {$fiberCountSelectSql}
                     COALESCE(e.switch_fiber_ports_number, 0) AS fiber_ports_number,
                     {$fiberPortLabelSelectSql}
              FROM equipment e
              LEFT JOIN equipment_types et ON et.id = e.equipment_type_id
              LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
              LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id
              {$fiberCountJoinSql}
              WHERE e.id = ?
                AND e.company_id = ?
              LIMIT 1";
$switchStmt = mysqli_prepare($conn, $switchSql);
$switch = null;
if ($switchStmt) {
    $companyId = (int)$company_id;
    mysqli_stmt_bind_param($switchStmt, 'ii', $switchId, $companyId);
    if (mysqli_stmt_execute($switchStmt)) {
        $switch = itm_mysqli_stmt_fetch_assoc($switchStmt);
    }
    mysqli_stmt_close($switchStmt);
}
if (!$switch) {
    itm_api_json_response(['success' => false, 'error' => 'Switch not found'], 404);
}

// Calculate RJ45 port count
$rj45Count = (int)($switch['legacy_numberport'] ?? 0);
if ($rj45Count <= 0) {
    $rj45Count = (int)preg_replace('/\D+/', '', (string)$switch['rj45_name']);
}
if ($rj45Count <= 0) {
    $rj45Count = 24; // Standard fallback
}

// Calculate fiber (SFP/SFP+) port counts as one internal family (`sfp`).
$fiberPortsNumber = (int)preg_replace('/\D+/', '', (string)$switch['fiber_ports_number']);
$legacyFiberCount = (int)preg_replace('/\D+/', '', (string)$switch['fiber_count']);
$fiberCount = $fiberPortsNumber > 0 ? $fiberPortsNumber : $legacyFiberCount;
$fiberName = strtolower(trim((string)$switch['fiber_name']));
$fiberLabel = strtolower(trim((string)($switch['fiber_port_label'] ?? '')));
$fiberHint = trim($fiberLabel . ' ' . $fiberName);
// Why: Legacy records may have Fiber Ports Number configured without a selected Fiber type.
// Resolve the fallback label from equipment_fiber.name so seeding honors catalog values.
if ($fiberCount > 0 && $fiberName === '') {
    $fiberName = fetch_default_fiber_name($conn, (int)$company_id);
    $fiberHint = trim($fiberLabel . ' ' . $fiberName);
}
$hasFiberHint = strpos($fiberHint, 'sfp') !== false;
$sfpCount = $hasFiberHint ? $fiberCount : 0;
if (!in_array('sfp', $availablePortTypes, true)) {
    $sfpCount = 0;
}

$hasManagementIdColumn = false;
$managementIdColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `switch_ports` LIKE 'management_id'");
if ($managementIdColumnRes && mysqli_num_rows($managementIdColumnRes) > 0) {
    $hasManagementIdColumn = true;
}
$defaultManagementId = 0;
if ($hasManagementIdColumn) {
    $stmtUnmanaged = mysqli_prepare(
        $conn,
        "SELECT id
         FROM equipment_environment
         WHERE company_id = ?
           AND LOWER(name) = 'unmanaged'
         ORDER BY id ASC
         LIMIT 1"
    );
    if ($stmtUnmanaged) {
        $companyId = (int)$company_id;
        mysqli_stmt_bind_param($stmtUnmanaged, 'i', $companyId);
        mysqli_stmt_execute($stmtUnmanaged);
        $unmanagedRow = itm_mysqli_stmt_fetch_assoc($stmtUnmanaged);
        mysqli_stmt_close($stmtUnmanaged);
        if ($unmanagedRow) {
            $defaultManagementId = (int)($unmanagedRow['id'] ?? 0);
        }
    }
}

/**
 * Automatically creates missing port records or removes excess port records
 */
function seed_ports(mysqli $conn, int $companyId, int $switchId, string $portType, int $count, bool $hasEquipmentId, bool $hasPortType, bool $isNumericPortTypeColumn, array $portTypeIdMap, int $defaultStatusId, int $defaultColorId, bool $hasManagementIdColumn, int $defaultManagementId): void
{
    if ($count <= 0) {
        return;
    }

    if ($hasEquipmentId && $hasPortType) {
        // Modern schema: Ports are linked to a switch
        $existingPortNumbers = [];
        $portTypeFilter = $isNumericPortTypeColumn ? (int)($portTypeIdMap[$portType] ?? 0) : $portType;
        if ($isNumericPortTypeColumn && (int)$portTypeFilter <= 0) {
            return;
        }
        $existingSql = 'SELECT port_number FROM switch_ports WHERE company_id = ? AND equipment_id = ? AND port_type = ? ORDER BY port_number ASC';
        $existingStmt = mysqli_prepare($conn, $existingSql);
        if ($existingStmt) {
            if ($isNumericPortTypeColumn) {
                mysqli_stmt_bind_param($existingStmt, 'iii', $companyId, $switchId, $portTypeFilter);
            } else {
                mysqli_stmt_bind_param($existingStmt, 'iis', $companyId, $switchId, $portTypeFilter);
            }
            if (mysqli_stmt_execute($existingStmt)) {
                foreach (itm_mysqli_stmt_fetch_all_assoc($existingStmt) as $existingRow) {
                    $portNumber = (int)($existingRow['port_number'] ?? 0);
                    if ($portNumber > 0) {
                        $existingPortNumbers[$portNumber] = true;
                    }
                }
            }
            mysqli_stmt_close($existingStmt);
        }

        // Insert missing ports
        $insertSql = $hasManagementIdColumn
            ? 'INSERT INTO switch_ports (company_id, equipment_id, port_type, port_number, to_patch_port, status_id, color_id, management_id, comments) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), "")'
            : 'INSERT INTO switch_ports (company_id, equipment_id, port_type, port_number, to_patch_port, status_id, color_id, comments) VALUES (?, ?, ?, ?, ?, ?, ?, "")';
        $insertStmt = mysqli_prepare($conn, $insertSql);
        if (!$insertStmt) {
            return;
        }
        for ($n = 1; $n <= $count; $n++) {
            if (isset($existingPortNumbers[$n])) {
                continue;
            }
            $label = strtoupper(str_replace('_', '+', $portType)) . ' ' . $n;
            if ($isNumericPortTypeColumn) {
                if ($hasManagementIdColumn) {
                    mysqli_stmt_bind_param($insertStmt, 'iiiiiiii', $companyId, $switchId, $portTypeFilter, $n, $label, $defaultStatusId, $defaultColorId, $defaultManagementId);
                } else {
                    mysqli_stmt_bind_param($insertStmt, 'iiiiiii', $companyId, $switchId, $portTypeFilter, $n, $label, $defaultStatusId, $defaultColorId);
                }
            } else {
                if ($hasManagementIdColumn) {
                    mysqli_stmt_bind_param($insertStmt, 'iisiiiii', $companyId, $switchId, $portTypeFilter, $n, $label, $defaultStatusId, $defaultColorId, $defaultManagementId);
                } else {
                    mysqli_stmt_bind_param($insertStmt, 'iisiiii', $companyId, $switchId, $portTypeFilter, $n, $label, $defaultStatusId, $defaultColorId);
                }
            }
            mysqli_stmt_execute($insertStmt);
        }
        mysqli_stmt_close($insertStmt);

        // Delete excess ports
        $deleteSql = 'DELETE FROM switch_ports WHERE company_id = ? AND equipment_id = ? AND port_type = ? AND port_number > ?';
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if ($deleteStmt) {
            if ($isNumericPortTypeColumn) {
                mysqli_stmt_bind_param($deleteStmt, 'iiii', $companyId, $switchId, $portTypeFilter, $count);
            } else {
                mysqli_stmt_bind_param($deleteStmt, 'iisi', $companyId, $switchId, $portTypeFilter, $count);
            }
            mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);
        }
        return;
    }

    // Legacy schema: Ports are only scoped by company (deprecated)
    if ($portType !== 'rj45') {
        return;
    }
    $existsSql = 'SELECT COUNT(*) AS c FROM switch_ports WHERE company_id = ?';
    $existsStmt = mysqli_prepare($conn, $existsSql);
    $existingCount = 0;
    if ($existsStmt) {
        mysqli_stmt_bind_param($existsStmt, 'i', $companyId);
        if (mysqli_stmt_execute($existsStmt)) {
            $existsRow = itm_mysqli_stmt_fetch_assoc($existsStmt);
            $existingCount = (int)($existsRow['c'] ?? 0);
        }
        mysqli_stmt_close($existsStmt);
    }
    if ($existingCount >= $count) {
        return;
    }

    $insertSql = $hasManagementIdColumn
        ? 'INSERT INTO switch_ports (company_id, port_number, to_patch_port, status_id, color_id, management_id, comments) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), "")'
        : 'INSERT INTO switch_ports (company_id, port_number, to_patch_port, status_id, color_id, comments) VALUES (?, ?, ?, ?, ?, "")';
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if (!$insertStmt) {
        return;
    }
    for ($n = $existingCount + 1; $n <= $count; $n++) {
        $label = 'RJ45 ' . $n;
        if ($hasManagementIdColumn) {
            mysqli_stmt_bind_param($insertStmt, 'iisiii', $companyId, $n, $label, $defaultStatusId, $defaultColorId, $defaultManagementId);
        } else {
            mysqli_stmt_bind_param($insertStmt, 'iisii', $companyId, $n, $label, $defaultStatusId, $defaultColorId);
        }
        mysqli_stmt_execute($insertStmt);
    }
    mysqli_stmt_close($insertStmt);
}

/**
 * Removes duplicate port records for a specific switch
 */
function remove_duplicate_ports(mysqli $conn, int $companyId, int $switchId, bool $hasEquipmentId, bool $hasPortType): void
{
    if (!$hasEquipmentId || !$hasPortType) {
        return;
    }

    $deleteSql = "DELETE sp_dupe
                  FROM switch_ports sp_dupe
                  INNER JOIN switch_ports sp_keep
                    ON sp_dupe.company_id = sp_keep.company_id
                   AND sp_dupe.equipment_id = sp_keep.equipment_id
                   AND sp_dupe.port_type = sp_keep.port_type
                   AND sp_dupe.port_number = sp_keep.port_number
                   AND sp_dupe.id > sp_keep.id
                  WHERE sp_dupe.company_id = ?
                    AND sp_dupe.equipment_id = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteSql);
    if (!$deleteStmt) {
        return;
    }
    mysqli_stmt_bind_param($deleteStmt, 'ii', $companyId, $switchId);
    mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);
}

// Synchronize port records
$portTypeIdMap = $isNumericPortTypeColumn ? fetch_port_type_id_map($conn, (int)$company_id) : [];
seed_ports($conn, (int)$company_id, $switchId, 'rj45', $rj45Count, $hasEquipmentId, $hasPortType, $isNumericPortTypeColumn, $portTypeIdMap, $defaultStatusId, $defaultColorId, $hasManagementIdColumn, $defaultManagementId);
seed_ports($conn, (int)$company_id, $switchId, 'sfp', $sfpCount, $hasEquipmentId, $hasPortType, $isNumericPortTypeColumn, $portTypeIdMap, $defaultStatusId, $defaultColorId, $hasManagementIdColumn, $defaultManagementId);
remove_duplicate_ports($conn, (int)$company_id, $switchId, $hasEquipmentId, $hasPortType);

// Fetch all port details including status, color, and VLAN
$ports = [];
if ($hasEquipmentId && $hasPortType) {
    $vlanSelect = $hasVlanId ? ', sp.vlan_id, v.vlan_name, v.vlan_color' : ', NULL AS vlan_id, NULL AS vlan_name, NULL AS vlan_color';
    $portTypeSelectSql = $isNumericPortTypeColumn ? "COALESCE(spt.type, 'RJ45')" : 'sp.port_type';
    $portTypeJoinSql = $isNumericPortTypeColumn ? 'LEFT JOIN switch_port_types spt ON spt.id = sp.port_type' : '';
    $rj45SelectSql = $hasRj45SpeedId ? ', sp.rj45_speed_id, COALESCE(rs.cable_type, \'\') AS rj45_cable_type' : ', NULL AS rj45_speed_id, \'\' AS rj45_cable_type';
    $rj45JoinSql = $hasRj45SpeedId ? 'LEFT JOIN rj45_speed rs ON rs.id = sp.rj45_speed_id' : '';
    $sql = "SELECT sp.id, {$portTypeSelectSql} AS port_type, sp.port_number, sp.to_patch_port, sp.to_patch_port AS label, ss.status, sc.color_name AS color, COALESCE(sc.hex_color, '') AS color_hex, COALESCE(sp.to_idf_id, sp.idf_id) AS to_idf_id, COALESCE(sp.to_rack_id, sp.rack_id) AS to_rack_id, COALESCE(sp.to_location_id, sp.location_id) AS to_location_id, i.idf_code, sp.comments{$vlanSelect}{$rj45SelectSql},
                   sp.fiber_port_id, sp.fiber_patch_id, sp.fiber_rack_id,
                   COALESCE(ef.name, '') AS fiber_port_name,
                   COALESCE(efp.name, '') AS fiber_patch_name,
                   COALESCE(efr.name, '') AS fiber_rack_name
            FROM switch_ports sp
            LEFT JOIN switch_status ss ON ss.id = sp.status_id
            LEFT JOIN cable_colors sc ON sc.id = sp.color_id
            LEFT JOIN vlans v ON v.id = sp.vlan_id
            {$rj45JoinSql}
            LEFT JOIN idfs i ON i.id = COALESCE(sp.to_idf_id, sp.idf_id)
            LEFT JOIN equipment_fiber ef ON ef.id = sp.fiber_port_id
            LEFT JOIN equipment_fiber_patch efp ON efp.id = sp.fiber_patch_id
            LEFT JOIN equipment_fiber_rack efr ON efr.id = sp.fiber_rack_id
            {$portTypeJoinSql}
            WHERE sp.company_id = ?
              AND sp.equipment_id = ?
            ORDER BY CASE WHEN LOWER(TRIM(COALESCE({$portTypeSelectSql}, 'RJ45'))) LIKE 'sfp%' THEN 1 ELSE 0 END ASC, sp.port_number ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        itm_api_json_response(['success' => false, 'error' => 'DB error'], 500);
    }
    $companyId = (int)$company_id;
    mysqli_stmt_bind_param($stmt, 'ii', $companyId, $switchId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        itm_api_json_response(['success' => false, 'error' => 'DB error'], 500);
    }
    foreach (itm_mysqli_stmt_fetch_all_assoc($stmt) as $row) {
        $row['port_type'] = normalize_port_type((string)($row['port_type'] ?? 'rj45'));
        if (!array_key_exists('label', $row) && array_key_exists('to_patch_port', $row)) {
            $row['label'] = (string)$row['to_patch_port'];
        }
        $ports[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    // Legacy fallback query
    $vlanSelect = $hasVlanId ? ', sp.vlan_id, v.vlan_name, v.vlan_color' : ', NULL AS vlan_id, NULL AS vlan_name, NULL AS vlan_color';
    $rj45SelectSql = $hasRj45SpeedId ? ', sp.rj45_speed_id, COALESCE(rs.cable_type, \'\') AS rj45_cable_type' : ', NULL AS rj45_speed_id, \'\' AS rj45_cable_type';
    $rj45JoinSql = $hasRj45SpeedId ? 'LEFT JOIN rj45_speed rs ON rs.id = sp.rj45_speed_id' : '';
    $sql = "SELECT sp.id, 'rj45' AS port_type, sp.port_number, sp.to_patch_port, sp.to_patch_port AS label, ss.status, sc.color_name AS color, COALESCE(sc.hex_color, '') AS color_hex, COALESCE(sp.to_idf_id, sp.idf_id) AS to_idf_id, COALESCE(sp.to_rack_id, sp.rack_id) AS to_rack_id, COALESCE(sp.to_location_id, sp.location_id) AS to_location_id, i.idf_code, sp.comments{$vlanSelect}{$rj45SelectSql},
                   sp.fiber_port_id, sp.fiber_patch_id, sp.fiber_rack_id,
                   COALESCE(ef.name, '') AS fiber_port_name,
                   COALESCE(efp.name, '') AS fiber_patch_name,
                   COALESCE(efr.name, '') AS fiber_rack_name
            FROM switch_ports sp
            LEFT JOIN switch_status ss ON ss.id = sp.status_id
            LEFT JOIN cable_colors sc ON sc.id = sp.color_id
            LEFT JOIN vlans v ON v.id = sp.vlan_id
            {$rj45JoinSql}
            LEFT JOIN idfs i ON i.id = COALESCE(sp.to_idf_id, sp.idf_id)
            LEFT JOIN equipment_fiber ef ON ef.id = sp.fiber_port_id
            LEFT JOIN equipment_fiber_patch efp ON efp.id = sp.fiber_patch_id
            LEFT JOIN equipment_fiber_rack efr ON efr.id = sp.fiber_rack_id
            WHERE sp.company_id = ?
            ORDER BY sp.port_number ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        itm_api_json_response(['success' => false, 'error' => 'DB error'], 500);
    }
    $companyId = (int)$company_id;
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        itm_api_json_response(['success' => false, 'error' => 'DB error'], 500);
    }
    foreach (itm_mysqli_stmt_fetch_all_assoc($stmt) as $row) {
        $row['port_type'] = normalize_port_type((string)($row['port_type'] ?? 'rj45'));
        if (!array_key_exists('label', $row) && array_key_exists('to_patch_port', $row)) {
            $row['label'] = (string)$row['to_patch_port'];
        }
        $ports[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// If using legacy schema, SFPs are only "virtual" in memory
if (!($hasEquipmentId && $hasPortType)) {
    for ($n = 1; $n <= $sfpCount; $n++) {
        $ports[] = [
            'id' => 'virtual-sfp-' . $n,
            'port_type' => 'sfp',
            'port_number' => $n,
            'label' => 'SFP ' . $n,
            'status' => 'Unknown',
            'color' => 'grey',
            'color_hex' => '#9ca3af',
            'vlan_id' => null,
            'vlan_name' => null,
            'vlan_color' => null,
            'comments' => '',
        ];
    }

}

itm_api_json_response([
    'success' => true,
    'ports' => $ports,
    'statuses' => $statuses,
    'colors' => $colors,
    'rj45_speeds' => $rj45Speeds,
    'vlans' => $vlans,
    'fiber_ports' => $fiberPorts,
    'fiber_patches' => $fiberPatches,
    'fiber_racks' => $fiberRacks,
    'idfs' => $idfOptions,
    'racks' => $rackOptions,
    'locations' => $locationOptions,
    'layout' => [
        'rj45' => $rj45Count,
        'sfp' => $sfpCount,
    ],
    'port_types' => $availablePortTypes,
], 200);
