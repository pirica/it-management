<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin();

$nl = itm_script_output_nl();
$failures = 0;

if (!$conn instanceof mysqli) {
    echo '[FAIL] Database connection is required.' . $nl;
    exit(1);
}

/**
 * @return int Company id or 0
 */
function vss_create_disposable_company(mysqli $conn, string $label): int
{
    // Why: companies INSERT audit trigger requires a valid @app_company_id before the new row exists.
    mysqli_query($conn, 'SET @app_company_id = 1');
    mysqli_query($conn, 'SET @app_employee_id = 1');

    $companyName = 'SampleSeedVerify-' . $label . '-' . bin2hex(random_bytes(4));
    $incode = strtoupper(substr(md5($companyName), 0, 6));
    $stmt = mysqli_prepare($conn, 'INSERT INTO companies (company, incode, active) VALUES (?, ?, 1)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $companyName, $incode);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $companyId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $companyId;
}

function vss_purge_company_table(mysqli $conn, string $table, int $companyId): void
{
    if (!itm_is_safe_identifier($table) || $companyId <= 0) {
        return;
    }
    if (!itm_table_has_column($conn, $table, 'company_id')) {
        return;
    }
    mysqli_query($conn, 'DELETE FROM `' . str_replace('`', '``', $table) . '` WHERE company_id = ' . (int)$companyId);
}

function vss_delete_company(mysqli $conn, int $companyId): void
{
    if ($companyId <= 0) {
        return;
    }
    $stmt = mysqli_prepare($conn, 'DELETE FROM companies WHERE id = ?');
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$disposableIds = [];
$companyA = vss_create_disposable_company($conn, 'A');
$companyB = vss_create_disposable_company($conn, 'B');
if ($companyA <= 0 || $companyB <= 0) {
    echo '[FAIL] Could not create disposable companies.' . $nl;
    exit(1);
}
$disposableIds[] = $companyA;
$disposableIds[] = $companyB;

foreach ([$companyA => 'A', $companyB => 'B'] as $companyId => $label) {
    vss_purge_company_table($conn, 'workstation_ram', $companyId);
    $seedErr = '';
    $inserted = itm_seed_table_from_database_sql($conn, 'workstation_ram', $companyId, $seedErr);
    if ($inserted < 1) {
        echo '[FAIL] workstation_ram seed for company ' . $label . ' (' . $companyId . '): ' . $seedErr . $nl;
        $failures++;
        continue;
    }

    $res = mysqli_query($conn, 'SELECT company_id, name FROM workstation_ram WHERE company_id = ' . (int)$companyId . ' ORDER BY name ASC');
    $names = [];
    $wrongTenant = false;
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        if ((int)($row['company_id'] ?? 0) !== $companyId) {
            $wrongTenant = true;
        }
        $names[] = (string)($row['name'] ?? '');
    }

    if ($wrongTenant) {
        echo '[FAIL] workstation_ram rows leaked across tenants for company ' . $label . '.' . $nl;
        $failures++;
    } elseif (!in_array('4 GB', $names, true) || !in_array('8 GB', $names, true)) {
        echo '[FAIL] workstation_ram template names missing for company ' . $label . ' (' . $companyId . ').' . $nl;
        $failures++;
    } else {
        echo '[PASS] workstation_ram seeded for arbitrary company ' . $label . ' (' . $companyId . ') with ' . count($names) . ' rows.' . $nl;
    }
}

// Duplicate guard: second seed on non-empty table should not inflate row count.
$beforeRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM workstation_ram WHERE company_id = ' . (int)$companyA);
$beforeRow = $beforeRes ? mysqli_fetch_assoc($beforeRes) : null;
$beforeCount = (int)($beforeRow['c'] ?? 0);
$seedErr = '';
itm_seed_table_from_database_sql($conn, 'workstation_ram', $companyA, $seedErr);
$afterRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM workstation_ram WHERE company_id = ' . (int)$companyA);
$afterRow = $afterRes ? mysqli_fetch_assoc($afterRes) : null;
$afterCount = (int)($afterRow['c'] ?? 0);
if ($afterCount !== $beforeCount) {
    echo '[FAIL] Duplicate seed changed workstation_ram row count (' . $beforeCount . ' -> ' . $afterCount . ').' . $nl;
    $failures++;
} else {
    echo '[PASS] Re-seed skipped duplicates for company A (' . $companyA . ').' . $nl;
}

// FK parent chain: employee_positions for disposable company B (depends on departments).
foreach (['employee_positions', 'departments'] as $purgeTable) {
    vss_purge_company_table($conn, $purgeTable, $companyB);
}
$seedErr = '';
$positionInserted = itm_seed_table_from_database_sql($conn, 'employee_positions', $companyB, $seedErr);
if ($positionInserted < 1) {
    echo '[FAIL] employee_positions seed with FK parents for company B: ' . $seedErr . $nl;
    $failures++;
} else {
    $fkRes = mysqli_query(
        $conn,
        'SELECT ep.company_id AS position_company, d.company_id AS dept_company
         FROM employee_positions ep
         INNER JOIN departments d ON d.id = ep.department_id
         WHERE ep.company_id = ' . (int)$companyB . ' LIMIT 1'
    );
    $fkRow = $fkRes ? mysqli_fetch_assoc($fkRes) : null;
    if (!is_array($fkRow) || (int)$fkRow['position_company'] !== $companyB || (int)$fkRow['dept_company'] !== $companyB) {
        echo '[FAIL] employee_positions FK parents not scoped to tenant ' . $companyB . '.' . $nl;
        $failures++;
    } else {
        echo '[PASS] employee_positions seeded with tenant-scoped FK parents for company B (' . $companyB . ').' . $nl;
    }
}

// Random fallback helper (direct call — every table now has db/02_data_sample.sql templates).
$fallbackTable = 'gl_accounts';
vss_purge_company_table($conn, $fallbackTable, $companyA);
$seedErr = '';
if (!function_exists('itm_seed_insert_random_fallback_row')) {
    echo '[FAIL] itm_seed_insert_random_fallback_row is not available.' . $nl;
    $failures++;
} else {
    $fallbackCount = itm_seed_insert_random_fallback_row($conn, $fallbackTable, $companyA, $seedErr);
    if ($fallbackCount !== 1) {
        echo '[FAIL] Random fallback expected 1 row for ' . $fallbackTable . ', got ' . $fallbackCount . ': ' . $seedErr . $nl;
        $failures++;
    } else {
        $scopeRes = mysqli_query(
            $conn,
            'SELECT COUNT(*) AS c FROM `' . str_replace('`', '``', $fallbackTable) . '` WHERE company_id = ' . (int)$companyA
        );
        $scopeRow = $scopeRes ? mysqli_fetch_assoc($scopeRes) : null;
        $scopedCount = (int)($scopeRow['c'] ?? 0);
        if ($scopedCount !== 1) {
            echo '[FAIL] Random fallback row for ' . $fallbackTable . ' not scoped to company A (' . $companyA . ').' . $nl;
            $failures++;
        } else {
            echo '[PASS] Random fallback inserted 1 tenant-scoped row for ' . $fallbackTable . ' on company A (' . $companyA . ').' . $nl;
        }
    }
}

// backup_tape_log: ensure Server equipment exists (re-type wrong-type row or minimal insert).
$companyC = vss_create_disposable_company($conn, 'C');
if ($companyC <= 0) {
    echo '[FAIL] Could not create disposable company C for backup_tape_log.' . $nl;
    $failures++;
} else {
    $disposableIds[] = $companyC;
    foreach (['backup_tape_log', 'equipment', 'equipment_types', 'equipment_statuses'] as $purgeTable) {
        vss_purge_company_table($conn, $purgeTable, $companyC);
    }

    $switchTypeId = 0;
    $switchStmt = mysqli_prepare(
        $conn,
        "INSERT INTO equipment_types (company_id, name, code, active) VALUES (?, 'Switch', 'SW', 1)"
    );
    if ($switchStmt) {
        mysqli_stmt_bind_param($switchStmt, 'i', $companyC);
        mysqli_stmt_execute($switchStmt);
        $switchTypeId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($switchStmt);
    }

    $statusId = 0;
    $statusStmt = mysqli_prepare(
        $conn,
        "INSERT INTO equipment_statuses (company_id, name, created_at) VALUES (?, 'Active', '2026-01-01 00:00:01')"
    );
    if ($statusStmt) {
        mysqli_stmt_bind_param($statusStmt, 'i', $companyC);
        mysqli_stmt_execute($statusStmt);
        $statusId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($statusStmt);
    }

    if ($switchTypeId <= 0 || $statusId <= 0) {
        echo '[FAIL] Could not seed equipment lookup rows for backup_tape_log test company C.' . $nl;
        $failures++;
    } else {
        $equipStmt = mysqli_prepare(
            $conn,
            "INSERT INTO equipment (company_id, equipment_type_id, name, hostname, status_id, active) VALUES (?, ?, 'Primary File Server', 'srv-wrong-type', ?, 1)"
        );
        if ($equipStmt) {
            mysqli_stmt_bind_param($equipStmt, 'iii', $companyC, $switchTypeId, $statusId);
            mysqli_stmt_execute($equipStmt);
            mysqli_stmt_close($equipStmt);
        }

        $seedErr = '';
        $btlInserted = itm_seed_table_from_database_sql($conn, 'backup_tape_log', $companyC, $seedErr);
        $serverId = function_exists('itm_seed_find_server_equipment_id')
            ? itm_seed_find_server_equipment_id($conn, $companyC)
            : 0;

        if ($btlInserted < 1 || $serverId <= 0) {
            echo '[FAIL] backup_tape_log seed for company C (' . $companyC . '): inserted=' . $btlInserted
                . ' server_id=' . $serverId . ' err=' . $seedErr . $nl;
            $failures++;
        } else {
            $today = date('Y-m-d');
            $dateStmt = mysqli_prepare(
                $conn,
                'SELECT log_date FROM backup_tape_log WHERE company_id = ? AND server_id = ? LIMIT 1'
            );
            $logDate = '';
            if ($dateStmt) {
                mysqli_stmt_bind_param($dateStmt, 'ii', $companyC, $serverId);
                mysqli_stmt_execute($dateStmt);
                $dateRow = mysqli_fetch_assoc(mysqli_stmt_get_result($dateStmt));
                mysqli_stmt_close($dateStmt);
                $logDate = (string)($dateRow['log_date'] ?? '');
            }
            if ($logDate !== $today) {
                echo '[FAIL] backup_tape_log sample log_date expected ' . $today . ', got ' . $logDate . $nl;
                $failures++;
            } else {
                echo '[PASS] backup_tape_log seeded Server and today row for company C (' . $companyC . ').' . $nl;
            }
        }
    }
}

// switch_ports: Core Switch equipment + 24 RJ45 rows (not backup_tape_log).
$companyD = vss_create_disposable_company($conn, 'D');
if ($companyD <= 0) {
    echo '[FAIL] Could not create disposable company D for switch_ports.' . $nl;
    $failures++;
} else {
    $disposableIds[] = $companyD;
    foreach (['switch_ports', 'switch_port_types', 'equipment', 'equipment_types', 'equipment_statuses', 'equipment_rj45', 'switch_status', 'cable_colors'] as $purgeTable) {
        vss_purge_company_table($conn, $purgeTable, $companyD);
    }

    $seedErr = '';
    $portsInserted = itm_seed_table_from_database_sql($conn, 'switch_ports', $companyD, $seedErr);
    $switchId = function_exists('itm_seed_find_switch_equipment_id')
        ? itm_seed_find_switch_equipment_id($conn, $companyD)
        : 0;
    $portCount = ($switchId > 0 && function_exists('itm_seed_count_switch_rj45_ports'))
        ? itm_seed_count_switch_rj45_ports($conn, $companyD, $switchId)
        : 0;

    if ($portsInserted < 24 || $switchId <= 0 || $portCount !== 24) {
        echo '[FAIL] switch_ports seed for company D (' . $companyD . '): inserted=' . $portsInserted
            . ' switch_id=' . $switchId . ' rj45_ports=' . $portCount . ' err=' . $seedErr . $nl;
        $failures++;
    } else {
        echo '[PASS] switch_ports seeded Core Switch with 24 RJ45 ports for company D (' . $companyD . ').' . $nl;
    }
}

foreach ($disposableIds as $disposeId) {
    vss_purge_company_table($conn, 'workstation_ram', $disposeId);
    vss_purge_company_table($conn, 'employee_positions', $disposeId);
    vss_purge_company_table($conn, 'departments', $disposeId);
    vss_purge_company_table($conn, 'backup_tape_log', $disposeId);
    vss_purge_company_table($conn, 'switch_port_types', $disposeId);
    vss_purge_company_table($conn, 'switch_ports', $disposeId);
    vss_purge_company_table($conn, 'switch_status', $disposeId);
    vss_purge_company_table($conn, 'cable_colors', $disposeId);
    vss_purge_company_table($conn, 'equipment_rj45', $disposeId);
    vss_purge_company_table($conn, 'equipment', $disposeId);
    vss_purge_company_table($conn, 'equipment_types', $disposeId);
    vss_purge_company_table($conn, 'equipment_statuses', $disposeId);
    vss_purge_company_table($conn, $fallbackTable, $disposeId);
    vss_delete_company($conn, $disposeId);
}

if ($failures > 0) {
    echo $nl . '[FAIL] ' . $failures . ' check(s) failed.' . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . '[OK] Sample data seed verification passed.' . $nl;
itm_script_output_end();
exit(0);
