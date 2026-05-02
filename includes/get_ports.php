<?php
/**
 * Switch Port Fetcher API
 * 
 * An AJAX endpoint that retrieves all ports for a specific network switch.
 * Handles automatic "seeding" of missing ports based on switch capacity.
 * Returns ports, available statuses, colors, and VLAN configurations.
 */

require '../config/config.php';

header('Content-Type: application/json; charset=utf-8');
// Disable direct error output to avoid corrupting JSON response
ini_set('display_errors', '0');

// Access Control: Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// CSRF Validation (checks multiple possible header/body locations)
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode((string)$rawInput, true);
if (!is_array($jsonInput)) {
    $jsonInput = [];
}

$csrfToken = (string)($jsonInput['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')));
if (!itm_validate_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Ensure the user has a valid company context
if ($company_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

/**
 * Fetches a simple ID-Label map from a lookup table
 */
function fetch_lookup_map(mysqli $conn, string $table, string $labelColumn): array
{
    $rows = [];
    if (!itm_is_safe_identifier($table) || !itm_is_safe_identifier($labelColumn)) {
        return $rows;
    }

    $hasCompanyId = itm_table_has_column($conn, $table, 'company_id');
    $companyId = isset($GLOBALS['company_id']) ? (int)$GLOBALS['company_id'] : 0;

    $res = false;
    // Prefer company-specific lookup values
    if ($hasCompanyId && $companyId > 0) {
        $sql = "SELECT id, `{$labelColumn}` AS label";
        if ($table === 'cable_colors' && itm_table_has_column($conn, $table, 'hex_color')) {
            $sql .= ", `hex_color`";
        }
        $sql .= " FROM `{$table}` WHERE company_id = ? ORDER BY id ASC";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    // Fallback to global values if no company-specific values found
    if (!$res || mysqli_num_rows($res) === 0) {
        $sql = "SELECT id, `{$labelColumn}` AS label";
        if ($table === 'cable_colors' && itm_table_has_column($conn, $table, 'hex_color')) {
            $sql .= ", `hex_color`";
        }
        $sql .= " FROM `{$table}` ORDER BY id ASC";
        $res = mysqli_query($conn, $sql);
    }

    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $entry = ['id' => (int)$row['id'], 'name' => (string)$row['label']];
        if ($table === 'cable_colors' && array_key_exists('hex_color', $row)) {
            $entry['hex_color'] = (string)($row['hex_color'] ?? '');
        }
        $rows[] = $entry;
    }
    return $rows;
}

/**
 * Fetches VLAN configurations for a company
 */
function fetch_company_vlans(mysqli $conn, int $companyId): array
{
    $rows = [];
    $sql = 'SELECT id, vlan_name, vlan_color FROM vlans WHERE company_id = ? ORDER BY vlan_number ASC, id ASC';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $rows;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    if (mysqli_stmt_execute($stmt)) {
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['vlan_name'],
                'color' => (string)($row['vlan_color'] ?? ''),
            ];
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
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
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
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
            $tableCheckRes = mysqli_stmt_get_result($tableCheckStmt);
            $tableExists = $tableCheckRes && mysqli_num_rows($tableCheckRes) > 0;
            mysqli_stmt_close($tableCheckStmt);
        }
    }

    if (!$tableExists) {
        return '';
    }

    $hasCompanyId = itm_table_has_column($conn, 'equipment_fiber', 'company_id');
    $orderSql = "ORDER BY
        CASE
            WHEN LOWER(name) LIKE '%sfp+%' THEN 1
            WHEN LOWER(name) LIKE '%sfp%' THEN 0
            ELSE 2
        END,
        id ASC
        LIMIT 1";

    if ($hasCompanyId && $companyId > 0) {
        $tenantSql = "SELECT name FROM equipment_fiber WHERE company_id = ? {$orderSql}";
        $tenantStmt = mysqli_prepare($conn, $tenantSql);
        if ($tenantStmt) {
            mysqli_stmt_bind_param($tenantStmt, 'i', $companyId);
            mysqli_stmt_execute($tenantStmt);
            $tenantRes = mysqli_stmt_get_result($tenantStmt);
            $tenantRow = $tenantRes ? mysqli_fetch_assoc($tenantRes) : null;
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

    if (strpos($normalized, 'sfp') !== false && strpos($normalized, 'plus') !== false) {
        return 'sfp_plus';
    }
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
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
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
$hasVlanId = itm_table_has_column($conn, 'switch_ports', 'vlan_id');
$hasLegacyNumberPort = itm_table_has_column($conn, 'equipment', 'numberport');
$hasSwitchFiberCountId = itm_table_has_column($conn, 'equipment', 'switch_fiber_count_id');
$hasSwitchFiberPortLabelColumn = itm_table_has_column($conn, 'equipment', 'switch_fiber_port_label');
$isNumericPortTypeColumn = $hasPortType ? switch_ports_port_type_is_numeric($conn) : false;

$hasPortTypesTable = false;
$stmtTable = mysqli_prepare($conn, "SHOW TABLES LIKE 'switch_port_types'");
if ($stmtTable) {
    mysqli_stmt_execute($stmtTable);
    $resTable = mysqli_stmt_get_result($stmtTable);
    $hasPortTypesTable = $resTable && mysqli_num_rows($resTable) > 0;
    mysqli_stmt_close($stmtTable);
}

$availablePortTypes = $hasPortTypesTable
    ? fetch_available_port_types($conn)
    : ['rj45', 'sfp', 'sfp_plus'];
if (!in_array('rj45', $availablePortTypes, true)) {
    $availablePortTypes[] = 'rj45';
}

// Schema validation
if (!$hasStatusId || !$hasColorId) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'switch_ports schema is missing status_id/color_id columns']);
    exit;
}

// Pre-fetch lookups
$statuses = fetch_lookup_map($conn, 'switch_status', 'status');
$colors = fetch_lookup_map($conn, 'cable_colors', 'color_name');
$vlans = fetch_company_vlans($conn, (int)$company_id);
$fiberPorts = fetch_lookup_map($conn, 'equipment_fiber', 'name');
$fiberPatches = fetch_lookup_map($conn, 'equipment_fiber_patch', 'name');
$fiberRacks = fetch_lookup_map($conn, 'equipment_fiber_rack', 'name');
$idfOptions = fetch_lookup_map($conn, 'idfs', 'idf_code');
$rackOptions = fetch_lookup_map($conn, 'racks', 'name');
$locationOptions = fetch_lookup_map($conn, 'it_locations', 'name');
if (empty($statuses) || empty($colors)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'switch_status/cable_colors lookup tables are empty']);
    exit;
}

$defaultStatusId = lookup_id_by_name($statuses, 'Unknown');
$defaultColorId = lookup_id_by_name($colors, 'grey', lookup_id_by_name($colors, 'gray'));

$switchId = (int)($jsonInput['switch_id'] ?? ($_POST['switch_id'] ?? 0));
if ($switchId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing switch id']);
    exit;
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
        $switchRes = mysqli_stmt_get_result($switchStmt);
        $switch = $switchRes ? mysqli_fetch_assoc($switchRes) : null;
    }
    mysqli_stmt_close($switchStmt);
}
if (!$switch) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Switch not found']);
    exit;
}

// Calculate RJ45 port count
$rj45Count = (int)($switch['legacy_numberport'] ?? 0);
if ($rj45Count <= 0) {
    $rj45Count = (int)preg_replace('/\D+/', '', (string)$switch['rj45_name']);
}
if ($rj45Count <= 0) {
    $rj45Count = 24; // Standard fallback
}

// Calculate SFP/SFP+ port counts
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
$hasSfpPlusLabel = strpos($fiberHint, 'sfp+') !== false;
$hasSfpLabel = strpos($fiberHint, 'sfp') !== false;
$sfpCount = $hasSfpPlusLabel ? 0 : ($hasSfpLabel ? $fiberCount : 0);
$sfpPlusCount = $hasSfpPlusLabel ? $fiberCount : 0;
if (!in_array('sfp', $availablePortTypes, true)) { $sfpCount = 0; }
if (!in_array('sfp_plus', $availablePortTypes, true)) { $sfpPlusCount = 0; }

/**
 * Automatically creates missing port records or removes excess port records
 */
function seed_ports(mysqli $conn, int $companyId, int $switchId, string $portType, int $count, bool $hasEquipmentId, bool $hasPortType, bool $isNumericPortTypeColumn, array $portTypeIdMap, int $defaultStatusId, int $defaultColorId): void
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
                $existingRes = mysqli_stmt_get_result($existingStmt);
                while ($existingRes && ($existingRow = mysqli_fetch_assoc($existingRes))) {
                    $portNumber = (int)($existingRow['port_number'] ?? 0);
                    if ($portNumber > 0) {
                        $existingPortNumbers[$portNumber] = true;
                    }
                }
            }
            mysqli_stmt_close($existingStmt);
        }

        // Insert missing ports
        $insertSql = 'INSERT INTO switch_ports (company_id, equipment_id, port_type, port_number, to_patch_port, status_id, color_id, comments) VALUES (?, ?, ?, ?, ?, ?, ?, "")';
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
                mysqli_stmt_bind_param($insertStmt, 'iiiiiii', $companyId, $switchId, $portTypeFilter, $n, $label, $defaultStatusId, $defaultColorId);
            } else {
                mysqli_stmt_bind_param($insertStmt, 'iisiiii', $companyId, $switchId, $portTypeFilter, $n, $label, $defaultStatusId, $defaultColorId);
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
            $existsRes = mysqli_stmt_get_result($existsStmt);
            $existingCount = (int)(($existsRes ? mysqli_fetch_assoc($existsRes) : [])['c'] ?? 0);
        }
        mysqli_stmt_close($existsStmt);
    }
    if ($existingCount >= $count) {
        return;
    }

    $insertSql = 'INSERT INTO switch_ports (company_id, port_number, to_patch_port, status_id, color_id, comments) VALUES (?, ?, ?, ?, ?, "")';
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if (!$insertStmt) {
        return;
    }
    for ($n = $existingCount + 1; $n <= $count; $n++) {
        $label = 'RJ45 ' . $n;
        mysqli_stmt_bind_param($insertStmt, 'iisii', $companyId, $n, $label, $defaultStatusId, $defaultColorId);
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
seed_ports($conn, (int)$company_id, $switchId, 'rj45', $rj45Count, $hasEquipmentId, $hasPortType, $isNumericPortTypeColumn, $portTypeIdMap, $defaultStatusId, $defaultColorId);
seed_ports($conn, (int)$company_id, $switchId, 'sfp', $sfpCount, $hasEquipmentId, $hasPortType, $isNumericPortTypeColumn, $portTypeIdMap, $defaultStatusId, $defaultColorId);
seed_ports($conn, (int)$company_id, $switchId, 'sfp_plus', $sfpPlusCount, $hasEquipmentId, $hasPortType, $isNumericPortTypeColumn, $portTypeIdMap, $defaultStatusId, $defaultColorId);
remove_duplicate_ports($conn, (int)$company_id, $switchId, $hasEquipmentId, $hasPortType);

// Fetch all port details including status, color, and VLAN
if ($hasEquipmentId && $hasPortType) {
    $vlanSelect = $hasVlanId ? ', sp.vlan_id, v.vlan_name, v.vlan_color' : ', NULL AS vlan_id, NULL AS vlan_name, NULL AS vlan_color';
    $portTypeSelectSql = $isNumericPortTypeColumn ? "COALESCE(spt.type, 'RJ45')" : 'sp.port_type';
    $portTypeJoinSql = $isNumericPortTypeColumn ? 'LEFT JOIN switch_port_types spt ON spt.id = sp.port_type' : '';
    $sql = "SELECT sp.id, {$portTypeSelectSql} AS port_type, sp.port_number, sp.to_patch_port, sp.to_patch_port AS label, ss.status, sc.color_name AS color, COALESCE(sc.hex_color, '') AS color_hex, COALESCE(sp.to_idf_id, sp.idf_id) AS to_idf_id, COALESCE(sp.to_rack_id, sp.rack_id) AS to_rack_id, COALESCE(sp.to_location_id, sp.location_id) AS to_location_id, i.idf_code, sp.comments{$vlanSelect},
                   sp.fiber_port_id, sp.fiber_patch_id, sp.fiber_rack_id,
                   COALESCE(ef.name, '') AS fiber_port_name,
                   COALESCE(efp.name, '') AS fiber_patch_name,
                   COALESCE(efr.name, '') AS fiber_rack_name
            FROM switch_ports sp
            LEFT JOIN switch_status ss ON ss.id = sp.status_id
            LEFT JOIN cable_colors sc ON sc.id = sp.color_id
            LEFT JOIN vlans v ON v.id = sp.vlan_id
            LEFT JOIN idfs i ON i.id = COALESCE(sp.to_idf_id, sp.idf_id)
            LEFT JOIN equipment_fiber ef ON ef.id = sp.fiber_port_id
            LEFT JOIN equipment_fiber_patch efp ON efp.id = sp.fiber_patch_id
            LEFT JOIN equipment_fiber_rack efr ON efr.id = sp.fiber_rack_id
            {$portTypeJoinSql}
            WHERE sp.company_id = ?
              AND sp.equipment_id = ?
            ORDER BY FIELD(LOWER({$portTypeSelectSql}), 'rj45', 'sfp', 'sfp+'), sp.port_number ASC";
    $stmt = mysqli_prepare($conn, $sql);
    $result = false;
    if ($stmt) {
        $companyId = (int)$company_id;
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $switchId);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
        }
    }
} else {
    // Legacy fallback query
    $vlanSelect = $hasVlanId ? ', sp.vlan_id, v.vlan_name, v.vlan_color' : ', NULL AS vlan_id, NULL AS vlan_name, NULL AS vlan_color';
    $sql = "SELECT sp.id, 'rj45' AS port_type, sp.port_number, sp.to_patch_port, sp.to_patch_port AS label, ss.status, sc.color_name AS color, COALESCE(sc.hex_color, '') AS color_hex, COALESCE(sp.to_idf_id, sp.idf_id) AS to_idf_id, COALESCE(sp.to_rack_id, sp.rack_id) AS to_rack_id, COALESCE(sp.to_location_id, sp.location_id) AS to_location_id, i.idf_code, sp.comments{$vlanSelect},
                   sp.fiber_port_id, sp.fiber_patch_id, sp.fiber_rack_id,
                   COALESCE(ef.name, '') AS fiber_port_name,
                   COALESCE(efp.name, '') AS fiber_patch_name,
                   COALESCE(efr.name, '') AS fiber_rack_name
            FROM switch_ports sp
            LEFT JOIN switch_status ss ON ss.id = sp.status_id
            LEFT JOIN cable_colors sc ON sc.id = sp.color_id
            LEFT JOIN vlans v ON v.id = sp.vlan_id
            LEFT JOIN idfs i ON i.id = COALESCE(sp.to_idf_id, sp.idf_id)
            LEFT JOIN equipment_fiber ef ON ef.id = sp.fiber_port_id
            LEFT JOIN equipment_fiber_patch efp ON efp.id = sp.fiber_patch_id
            LEFT JOIN equipment_fiber_rack efr ON efr.id = sp.fiber_rack_id
            WHERE sp.company_id = ?
            ORDER BY sp.port_number ASC";
    $stmt = mysqli_prepare($conn, $sql);
    $result = false;
    if ($stmt) {
        $companyId = (int)$company_id;
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
        }
    }
}

if (!$result) {
    if ($stmt) { mysqli_stmt_close($stmt); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}

$ports = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['port_type'] = normalize_port_type((string)($row['port_type'] ?? 'rj45'));
    if (!array_key_exists('label', $row) && array_key_exists('to_patch_port', $row)) {
        $row['label'] = (string)$row['to_patch_port'];
    }
    $ports[] = $row;
}
if ($stmt) { mysqli_stmt_close($stmt); }

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

    for ($n = 1; $n <= $sfpPlusCount; $n++) {
        $ports[] = [
            'id' => 'virtual-sfp-plus-' . $n,
            'port_type' => 'sfp_plus',
            'port_number' => $n,
            'label' => 'SFP+ ' . $n,
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

// Return final JSON payload
echo json_encode([
    'success' => true,
    'ports' => $ports,
    'statuses' => $statuses,
    'colors' => $colors,
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
        'sfp_plus' => $sfpPlusCount,
    ],
    'port_types' => $availablePortTypes,
]);
