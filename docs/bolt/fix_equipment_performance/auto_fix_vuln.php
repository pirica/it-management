<?php
/**
 * Bolt Performance Fix: Equipment Module
 *
 * This script optimizes modules/equipment/index.php by gating expensive
 * and unused database queries behind the Switch Port Manager visibility flag.
 */

$targetFile = dirname(dirname(dirname(__DIR__))) . '/modules/equipment/index.php';
$content = file_get_contents($targetFile);

if ($content === false) {
    die("Error: Unable to read target file.\n");
}

// 1. Optimize $switches and $selectedSwitchData population
// We will move the $switches query and the $selectedSwitchData loop into a block that only runs if $showSwitchPortManager is true.
// But first we need to make sure $visibleSwitchIds is calculated without needing $switches.

$search1 = <<<'PHP'
$isGeneralEquipmentModule = $equipmentTypeNameFilter === '';
$isSwitchTypeFilter = strtolower($equipmentTypeNameFilter) === 'switch';
$enableSwitchPortManager = $isGeneralEquipmentModule || $isSwitchTypeFilter;
$switches = [];
if ($enableSwitchPortManager) {
    $switchResult = mysqli_query(
        $conn,
        "SELECT e.id, e.name, COALESCE(e.hostname, '') AS hostname,
                COALESCE(er.name, '24 ports') AS rj45_name,
                COALESCE(ef.name, '') AS fiber_name,
                COALESCE(e.switch_fiber_id, 0) AS fiber_id,
                COALESCE(efp.name, '') AS fiber_patch_name,
                COALESCE(e.switch_fiber_patch_id, 0) AS fiber_patch_id,
                COALESCE(efr.name, '') AS fiber_rack_name,
                COALESCE(e.switch_fiber_rack_id, 0) AS fiber_rack_id,
                COALESCE(e.switch_fiber_ports_number, 0) AS fiber_ports_number,
                {$switchFiberPortLabelSelect} AS fiber_port_label,
                COALESCE(spnl.name, 'Vertical') AS port_numbering_layout,
                COALESCE(r.name, '') AS rack_name,
                COALESCE(e.rack_id, 0) AS rack_id,
                COALESCE(idf.name, '') AS idf_name,
                COALESCE(e.idf_id, 0) AS idf_id,
                COALESCE(l.name, '') AS location_name,
                COALESCE(e.location_id, 0) AS location_id
         FROM equipment e
         INNER JOIN equipment_types et ON et.id = e.equipment_type_id
         LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
         LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id
         LEFT JOIN equipment_fiber_patch efp ON efp.id = e.switch_fiber_patch_id
         LEFT JOIN equipment_fiber_rack efr ON efr.id = e.switch_fiber_rack_id
         LEFT JOIN switch_port_numbering_layout spnl ON spnl.id = e.switch_port_numbering_layout_id
         LEFT JOIN it_locations l ON l.id = e.location_id AND l.company_id = e.company_id
         LEFT JOIN racks r ON r.id = e.rack_id AND r.company_id = e.company_id
         LEFT JOIN idfs idf ON idf.id = e.idf_id AND idf.company_id = e.company_id
         WHERE e.company_id = $company_id
           AND LOWER(TRIM(et.name)) LIKE '%switch%'
         ORDER BY e.name ASC"
    );
    while ($switchResult && ($row = mysqli_fetch_assoc($switchResult))) {
        $switches[] = $row;
    }
}
$switchIds = array_map(static fn(array $switchItem): int => (int)($switchItem['id'] ?? 0), $switches);
$visibleSwitchIds = [];
foreach ($equipmentRows as $equipmentRow) {
    $equipmentId = (int)($equipmentRow['id'] ?? 0);
    $isSwitchType = str_contains(strtolower(trim((string)($equipmentRow['equipment_type_name'] ?? ''))), 'switch');
    if ($equipmentId > 0 && $isSwitchType && in_array($equipmentId, $switchIds, true)) {
        $visibleSwitchIds[] = $equipmentId;
    }
}
$visibleSwitchIds = array_values(array_unique($visibleSwitchIds));

$selectedSwitchId = isset($_GET['switch_id']) ? (int)$_GET['switch_id'] : 0;
$hasSelectedSwitch = in_array($selectedSwitchId, $visibleSwitchIds, true);
if (!$hasSelectedSwitch && !empty($visibleSwitchIds)) {
    $selectedSwitchId = (int)$visibleSwitchIds[0];
    $hasSelectedSwitch = true;
}
$selectedSwitchData = null;
if ($hasSelectedSwitch) {
    foreach ($switches as $switchItem) {
        if ((int)$switchItem['id'] === $selectedSwitchId) {
            $selectedSwitchData = $switchItem;
            break;
        }
    }
}
$showSwitchPortManager = $hasSelectedSwitch && (string)($_GET['spm'] ?? '') === '1';
PHP;

$replace1 = <<<'PHP'
$isGeneralEquipmentModule = $equipmentTypeNameFilter === '';
$isSwitchTypeFilter = strtolower($equipmentTypeNameFilter) === 'switch';
$enableSwitchPortManager = $isGeneralEquipmentModule || $isSwitchTypeFilter;

// Why: Efficiently identify switches in the current view without a separate company-wide query.
$visibleSwitchIds = [];
foreach ($equipmentRows as $equipmentRow) {
    $equipmentId = (int)($equipmentRow['id'] ?? 0);
    // PHP 7.4: Using strpos() for compatibility.
    $isSwitchType = strpos(strtolower(trim((string)($equipmentRow['equipment_type_name'] ?? ''))), 'switch') !== false;
    if ($equipmentId > 0 && $isSwitchType) {
        $visibleSwitchIds[] = $equipmentId;
    }
}
$visibleSwitchIds = array_values(array_unique($visibleSwitchIds));

$selectedSwitchId = isset($_GET['switch_id']) ? (int)$_GET['switch_id'] : 0;
$hasSelectedSwitch = in_array($selectedSwitchId, $visibleSwitchIds, true);
if (!$hasSelectedSwitch && !empty($visibleSwitchIds)) {
    $selectedSwitchId = (int)$visibleSwitchIds[0];
    $hasSelectedSwitch = true;
}

$showSwitchPortManager = $hasSelectedSwitch && (string)($_GET['spm'] ?? '') === '1';

$switches = [];
$selectedSwitchData = null;
// Why: Only fetch all switches and selected switch details if the Switch Port Manager is active.
if ($showSwitchPortManager && $enableSwitchPortManager) {
    $switchResult = mysqli_query(
        $conn,
        "SELECT e.id, e.name, COALESCE(e.hostname, '') AS hostname,
                COALESCE(er.name, '24 ports') AS rj45_name,
                COALESCE(ef.name, '') AS fiber_name,
                COALESCE(e.switch_fiber_id, 0) AS fiber_id,
                COALESCE(efp.name, '') AS fiber_patch_name,
                COALESCE(e.switch_fiber_patch_id, 0) AS fiber_patch_id,
                COALESCE(efr.name, '') AS fiber_rack_name,
                COALESCE(e.switch_fiber_rack_id, 0) AS fiber_rack_id,
                COALESCE(e.switch_fiber_ports_number, 0) AS fiber_ports_number,
                {$switchFiberPortLabelSelect} AS fiber_port_label,
                COALESCE(spnl.name, 'Vertical') AS port_numbering_layout,
                COALESCE(r.name, '') AS rack_name,
                COALESCE(e.rack_id, 0) AS rack_id,
                COALESCE(idf.name, '') AS idf_name,
                COALESCE(e.idf_id, 0) AS idf_id,
                COALESCE(l.name, '') AS location_name,
                COALESCE(e.location_id, 0) AS location_id
         FROM equipment e
         INNER JOIN equipment_types et ON et.id = e.equipment_type_id
         LEFT JOIN equipment_rj45 er ON er.id = e.switch_rj45_id
         LEFT JOIN equipment_fiber ef ON ef.id = e.switch_fiber_id
         LEFT JOIN equipment_fiber_patch efp ON efp.id = e.switch_fiber_patch_id
         LEFT JOIN equipment_fiber_rack efr ON efr.id = e.switch_fiber_rack_id
         LEFT JOIN switch_port_numbering_layout spnl ON spnl.id = e.switch_port_numbering_layout_id
         LEFT JOIN it_locations l ON l.id = e.location_id AND l.company_id = e.company_id
         LEFT JOIN racks r ON r.id = e.rack_id AND r.company_id = e.company_id
         LEFT JOIN idfs idf ON idf.id = e.idf_id AND idf.company_id = e.company_id
         WHERE e.company_id = $company_id
           AND LOWER(TRIM(et.name)) LIKE '%switch%'
         ORDER BY e.name ASC"
    );
    while ($switchResult && ($row = mysqli_fetch_assoc($switchResult))) {
        $switches[] = $row;
        if ((int)$row['id'] === $selectedSwitchId) {
            $selectedSwitchData = $row;
        }
    }
}
PHP;

$content = str_replace($search1, $replace1, $content);

// 2. Optimize $locationTypeExtraOptions population
$search2 = <<<'PHP'
$locationTypeExtraOptions = [];
$locationTypeSql = "SELECT id, name FROM location_types WHERE company_id = " . (int)$company_id . " ORDER BY name ASC";
$locationTypeRes = mysqli_query($conn, $locationTypeSql);
while ($locationTypeRes && ($locationTypeRow = mysqli_fetch_assoc($locationTypeRes))) {
    $locationTypeExtraOptions[] = [
        'value' => (string)(int)($locationTypeRow['id'] ?? 0),
        'label' => (string)($locationTypeRow['name'] ?? ''),
    ];
}
PHP;

$replace2 = <<<'PHP'
$locationTypeExtraOptions = [];
// Why: Only fetch location types if the Switch Port Manager is active, as they are only used for its "Add Location" modal.
if ($showSwitchPortManager) {
    $locationTypeSql = "SELECT id, name FROM location_types WHERE company_id = " . (int)$company_id . " ORDER BY name ASC";
    $locationTypeRes = mysqli_query($conn, $locationTypeSql);
    while ($locationTypeRes && ($locationTypeRow = mysqli_fetch_assoc($locationTypeRes))) {
        $locationTypeExtraOptions[] = [
            'value' => (string)(int)($locationTypeRow['id'] ?? 0),
            'label' => (string)($locationTypeRow['name'] ?? ''),
        ];
    }
}
PHP;

$content = str_replace($search2, $replace2, $content);

// Ensure directory exists
if (!is_dir('docs/bolt/fixed_files_equipment_performance/fixed_files/modules/equipment/')) {
    mkdir('docs/bolt/fixed_files_equipment_performance/fixed_files/modules/equipment/', 0775, true);
}

// Save to a temporary file first to be used by the generator
file_put_contents('docs/bolt/fixed_files_equipment_performance/fixed_files/modules/equipment/index.php', $content);

echo "Optimized file generated in docs/bolt/fixed_files_equipment_performance/fixed_files/modules/equipment/index.php\n";
