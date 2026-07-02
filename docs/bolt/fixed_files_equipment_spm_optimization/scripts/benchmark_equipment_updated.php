<?php
define('ITM_CLI_SCRIPT', true);
require 'config/config.php';

if (!$conn) {
    die("Database connection failed\n");
}

$company_id = 1; // Default test company

// 1. Setup: Insert 20 dummy switches
echo "Setting up 20 dummy switches...\n";
$dummyIds = [];
for ($i = 1; $i <= 20; $i++) {
    $name = "Benchmark Switch $i";
    $sql = "INSERT INTO equipment (company_id, name, equipment_type_id, status_id)
            SELECT $company_id, '$name', id, (SELECT id FROM equipment_statuses WHERE company_id = $company_id LIMIT 1)
            FROM equipment_types WHERE company_id = $company_id AND LOWER(name) LIKE '%switch%' LIMIT 1";
    if (mysqli_query($conn, $sql)) {
        $dummyIds[] = mysqli_insert_id($conn);
    }
}
echo "Inserted " . count($dummyIds) . " dummy switches.\n";

// 2. Benchmark logic
function get_mysql_questions($conn) {
    $res = mysqli_query($conn, "SHOW SESSION STATUS LIKE 'Questions'");
    $row = mysqli_fetch_assoc($res);
    return (int)$row['Value'];
}

echo "Starting benchmark (Optimized)...\n";

$start_queries = get_mysql_questions($conn);
$start_time = microtime(true);

// --- SIMULATED LOGIC FROM optimized modules/equipment/index.php ---

// Logic to check column exists
$hasSwitchFiberPortLabelColumn = false;
$hasSwitchFiberPortLabelColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM `equipment` LIKE 'switch_fiber_port_label'");
if ($hasSwitchFiberPortLabelColumnRes && mysqli_num_rows($hasSwitchFiberPortLabelColumnRes) > 0) {
    $hasSwitchFiberPortLabelColumn = true;
}
$switchFiberPortLabelSelect = $hasSwitchFiberPortLabelColumn
    ? "COALESCE(e.switch_fiber_port_label, '')"
    : "''";

// Lightweight query for picker
$switches = [];
$switchResult = mysqli_query(
    $conn,
    "SELECT e.id, e.name
     FROM equipment e
     INNER JOIN equipment_types et ON et.id = e.equipment_type_id
     WHERE e.company_id = $company_id
       AND LOWER(TRIM(et.name)) LIKE '%switch%'
     ORDER BY e.name ASC"
);
while ($switchResult && ($row = mysqli_fetch_assoc($switchResult))) {
    $switches[] = $row;
}

// Full lookup (Simulate spm=1 for the selected switch)
$selectedSwitchData = null;
$showSwitchPortManager = true;
$hasSelectedSwitch = count($switches) > 0;
$selectedSwitchId = $hasSelectedSwitch ? $switches[0]['id'] : 0;

if ($hasSelectedSwitch && $showSwitchPortManager) {
    $selectedSwitchSql = "SELECT e.id, e.name, COALESCE(e.hostname, '') AS hostname,
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
         WHERE e.id = $selectedSwitchId AND e.company_id = $company_id
         LIMIT 1";
    $selectedSwitchRes = mysqli_query($conn, $selectedSwitchSql);
    if ($selectedSwitchRes) {
        $selectedSwitchData = mysqli_fetch_assoc($selectedSwitchRes);
    }
}

// --- END SIMULATED LOGIC ---

$end_time = microtime(true);
$end_queries = get_mysql_questions($conn);

$total_time = ($end_time - $start_time) * 1000;
$total_queries = $end_queries - $start_queries - 1;

echo "Benchmark results (Optimized):\n";
echo "Execution time: " . number_format($total_time, 2) . " ms\n";
echo "Query count: $total_queries\n";
echo "Total switches loaded: " . count($switches) . "\n";

// 3. Cleanup
echo "Cleaning up dummy switches...\n";
if (!empty($dummyIds)) {
    $ids = implode(',', $dummyIds);
    mysqli_query($conn, "DELETE FROM equipment WHERE id IN ($ids)");
}
echo "Done.\n";
