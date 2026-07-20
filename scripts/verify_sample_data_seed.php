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

// Random fallback when no template exists (use a tenant-scoped table unlikely in sample file).
$fallbackTable = 'approver_type';
$sampleBody = itm_database_sql_read_sample();
$hasTemplate = strpos($sampleBody, 'INSERT INTO `approver_type`') !== false;
if ($hasTemplate) {
    echo '[SKIP] approver_type has templates — fallback covered by empty-template tables in manual QA.' . $nl;
} else {
    vss_purge_company_table($conn, $fallbackTable, $companyA);
    $seedErr = '';
    $fallbackCount = itm_seed_table_from_database_sql($conn, $fallbackTable, $companyA, $seedErr);
    if ($fallbackCount !== 1) {
        echo '[FAIL] Random fallback expected 1 row for ' . $fallbackTable . ', got ' . $fallbackCount . ': ' . $seedErr . $nl;
        $failures++;
    } else {
        echo '[PASS] Random fallback inserted 1 row for ' . $fallbackTable . ' on company A.' . $nl;
    }
}

foreach ($disposableIds as $disposeId) {
    vss_purge_company_table($conn, 'workstation_ram', $disposeId);
    vss_purge_company_table($conn, 'employee_positions', $disposeId);
    vss_purge_company_table($conn, 'departments', $disposeId);
    vss_purge_company_table($conn, 'approver_type', $disposeId);
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
