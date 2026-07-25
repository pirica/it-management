<?php
/**
 * Rack Planner regression checks — price source sync and module contract.
 *
 * CLI: php scripts/verify_rack_planner.php
 * Browser: scripts/verify_rack_planner.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once ROOT_PATH . 'modules/rack_planner/includes/functions.php';

itm_script_output_begin('Rack Planner Verification');

$nl = itm_script_output_nl();
$failures = 0;
$companyId = 1;

function rack_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function rack_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

function rack_verify_audit_triggers(mysqli $conn, $table)
{
    $safeTable = mysqli_real_escape_string($conn, (string)$table);
    $res = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS c FROM information_schema.TRIGGERS
         WHERE TRIGGER_SCHEMA = DATABASE()
           AND EVENT_OBJECT_TABLE = '{$safeTable}'
           AND TRIGGER_NAME LIKE 'trg\\_%\\_audit\\_%'"
    );
    $count = $res ? (int)(mysqli_fetch_assoc($res)['c'] ?? 0) : 0;
    if ($count < 3) {
        rack_verify_fail("Missing audit triggers for {$table} (expected 3, found {$count})");
        return;
    }
    rack_verify_pass("Audit triggers present for {$table}");
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn instanceof mysqli) {
    rack_verify_fail('No database connection.');
    exit(1);
}

$res = mysqli_query($conn, "SHOW TABLES LIKE 'rack_planner'");
if (!$res || mysqli_num_rows($res) === 0) {
    rack_verify_fail('Missing table rack_planner.');
} else {
    rack_verify_pass('Table rack_planner exists.');
}

$registryStmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? LIMIT 1');
$slug = 'rack_planner';
if ($registryStmt) {
    mysqli_stmt_bind_param($registryStmt, 's', $slug);
    mysqli_stmt_execute($registryStmt);
    $hasRow = mysqli_stmt_fetch($registryStmt);
    mysqli_stmt_close($registryStmt);
    if (!$hasRow) {
        rack_verify_fail('modules_registry missing rack_planner.');
    } else {
        rack_verify_pass('modules_registry has rack_planner.');
    }
}

if (!function_exists('rack_planner_sync_source_prices_from_layout')) {
    rack_verify_fail('rack_planner_sync_source_prices_from_layout() missing.');
} else {
    rack_verify_pass('rack_planner_sync_source_prices_from_layout() loaded.');
}

$handlersPath = ROOT_PATH . 'modules/rack_planner/includes/handlers.php';
$handlersCode = is_file($handlersPath) ? (string)file_get_contents($handlersPath) : '';
if ($handlersCode === '' || strpos($handlersCode, 'rack_planner_sync_source_prices_from_layout') === false) {
    rack_verify_fail('handlers.php must call rack_planner_sync_source_prices_from_layout on save/autosave.');
} else {
    rack_verify_pass('handlers.php wires price source sync.');
}

rack_verify_audit_triggers($conn, 'rack_planner');

// Why: Disposable rows prove catalog/equipment/idf_unlinked price sync without mutating seed data.
$catalogModel = 'RP-VERIFY-' . bin2hex(random_bytes(4));
$catalogId = 0;
$equipmentId = 0;
$idfPositionId = 0;
$idfToken = '1234-5678';
$positionNo = 240;

$stmt = mysqli_prepare($conn, 'INSERT INTO catalogs (company_id, model, price, active) VALUES (?, ?, ?, 1)');
$seedPrice = 11.11;
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'isd', $companyId, $catalogModel, $seedPrice);
    if (mysqli_stmt_execute($stmt)) {
        $catalogId = (int)mysqli_insert_id($conn);
    }
    mysqli_stmt_close($stmt);
}
if ($catalogId <= 0) {
    rack_verify_fail('Unable to seed disposable catalog row for price sync test.');
} else {
    $layout = ['devices' => [['code' => 'catalog:' . $catalogId, 'price' => 22.22]]];
    $syncOk = rack_planner_sync_source_prices_from_layout($conn, $companyId, $layout);
    $priceRow = null;
    $priceStmt = mysqli_prepare($conn, 'SELECT price FROM catalogs WHERE id = ? AND company_id = ? LIMIT 1');
    if ($priceStmt) {
        mysqli_stmt_bind_param($priceStmt, 'ii', $catalogId, $companyId);
        mysqli_stmt_execute($priceStmt);
        $priceRes = mysqli_stmt_get_result($priceStmt);
        $priceRow = $priceRes ? mysqli_fetch_assoc($priceRes) : null;
        mysqli_stmt_close($priceStmt);
    }
    $syncedPrice = isset($priceRow['price']) ? (float)$priceRow['price'] : 0.0;
    if (!$syncOk || abs($syncedPrice - 22.22) > 0.001) {
        rack_verify_fail('catalog:<id> price sync failed (expected 22.22, got ' . $syncedPrice . ').');
    } else {
        rack_verify_pass('catalog:<id> price sync updates catalogs.price.');
    }
    mysqli_query($conn, 'DELETE FROM catalogs WHERE id = ' . (int)$catalogId);
}

$typeId = 0;
$statusId = 0;
$typeRes = mysqli_query($conn, 'SELECT id FROM equipment_types WHERE company_id = ' . (int)$companyId . ' LIMIT 1');
if ($typeRes && ($typeRow = mysqli_fetch_assoc($typeRes))) {
    $typeId = (int)$typeRow['id'];
}
$statusRes = mysqli_query($conn, 'SELECT id FROM equipment_statuses WHERE company_id = ' . (int)$companyId . ' LIMIT 1');
if ($statusRes && ($statusRow = mysqli_fetch_assoc($statusRes))) {
    $statusId = (int)$statusRow['id'];
}
if ($typeId > 0 && $statusId > 0) {
    $eqName = 'RP-VERIFY-EQ-' . bin2hex(random_bytes(3));
    $eqCost = 5.00;
    $eqStmt = mysqli_prepare(
        $conn,
        'INSERT INTO equipment (company_id, equipment_type_id, name, status_id, purchase_cost, active) VALUES (?, ?, ?, ?, ?, 1)'
    );
    if ($eqStmt) {
        mysqli_stmt_bind_param($eqStmt, 'iisid', $companyId, $typeId, $eqName, $statusId, $eqCost);
        if (mysqli_stmt_execute($eqStmt)) {
            $equipmentId = (int)mysqli_insert_id($conn);
        }
        mysqli_stmt_close($eqStmt);
    }
}
if ($equipmentId <= 0) {
    rack_verify_fail('Unable to seed disposable equipment row for price sync test.');
} else {
    $layout = ['devices' => [['code' => 'equipment:' . $equipmentId, 'price' => 15.75]]];
    $syncOk = rack_planner_sync_source_prices_from_layout($conn, $companyId, $layout);
    $costRow = null;
    $costStmt = mysqli_prepare($conn, 'SELECT purchase_cost FROM equipment WHERE id = ? AND company_id = ? LIMIT 1');
    if ($costStmt) {
        mysqli_stmt_bind_param($costStmt, 'ii', $equipmentId, $companyId);
        mysqli_stmt_execute($costStmt);
        $costRes = mysqli_stmt_get_result($costStmt);
        $costRow = $costRes ? mysqli_fetch_assoc($costRes) : null;
        mysqli_stmt_close($costStmt);
    }
    $syncedCost = isset($costRow['purchase_cost']) ? (float)$costRow['purchase_cost'] : 0.0;
    if (!$syncOk || abs($syncedCost - 15.75) > 0.001) {
        rack_verify_fail('equipment:<id> price sync failed (expected 15.75, got ' . $syncedCost . ').');
    } else {
        rack_verify_pass('equipment:<id> price sync updates equipment.purchase_cost.');
    }
    mysqli_query($conn, 'DELETE FROM equipment WHERE id = ' . (int)$equipmentId);
}

$deviceTypeId = 0;
$deviceTypeRes = mysqli_query($conn, 'SELECT id FROM idf_device_type WHERE company_id = ' . (int)$companyId . ' LIMIT 1');
if ($deviceTypeRes && ($deviceTypeRow = mysqli_fetch_assoc($deviceTypeRes))) {
    $deviceTypeId = (int)$deviceTypeRow['id'];
}
$idfId = 0;
$idfRes = mysqli_query($conn, 'SELECT id FROM idfs WHERE company_id = ' . (int)$companyId . ' LIMIT 1');
if ($idfRes && ($idfRow = mysqli_fetch_assoc($idfRes))) {
    $idfId = (int)$idfRow['id'];
}
if ($deviceTypeId > 0 && $idfId > 0) {
  while ($positionNo <= 250) {
        $probe = mysqli_prepare(
            $conn,
            'SELECT id FROM idf_positions WHERE company_id = ? AND idf_id = ? AND position_no = ? LIMIT 1'
        );
        $exists = false;
        if ($probe) {
            mysqli_stmt_bind_param($probe, 'iii', $companyId, $idfId, $positionNo);
            mysqli_stmt_execute($probe);
            $probeRes = mysqli_stmt_get_result($probe);
            $exists = $probeRes && mysqli_fetch_assoc($probeRes);
            mysqli_stmt_close($probe);
        }
        if (!$exists) {
            break;
        }
        $positionNo++;
    }
    $seedIdfPrice = 9.99;
    $posStmt = mysqli_prepare(
        $conn,
        'INSERT INTO idf_positions (company_id, idf_id, position_no, device_type, device_name, equipment_id, price, active)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
    );
    $deviceName = 'RP Verify Device';
    if ($posStmt) {
        mysqli_stmt_bind_param(
            $posStmt,
            'iiiissd',
            $companyId,
            $idfId,
            $positionNo,
            $deviceTypeId,
            $deviceName,
            $idfToken,
            $seedIdfPrice
        );
        if (mysqli_stmt_execute($posStmt)) {
            $idfPositionId = (int)mysqli_insert_id($conn);
        }
        mysqli_stmt_close($posStmt);
    }
}
if ($idfPositionId <= 0) {
    rack_verify_fail('Unable to seed disposable idf_positions row for idf_unlinked price sync test.');
} else {
    $layout = ['devices' => [['code' => 'idf_unlinked:' . $idfToken, 'price' => 18.50]]];
    $syncOk = rack_planner_sync_source_prices_from_layout($conn, $companyId, $layout);
    $idfPriceRow = null;
    $idfPriceStmt = mysqli_prepare(
        $conn,
        'SELECT price FROM idf_positions WHERE id = ? AND company_id = ? AND equipment_id = ? LIMIT 1'
    );
    if ($idfPriceStmt) {
        mysqli_stmt_bind_param($idfPriceStmt, 'iis', $idfPositionId, $companyId, $idfToken);
        mysqli_stmt_execute($idfPriceStmt);
        $idfPriceRes = mysqli_stmt_get_result($idfPriceStmt);
        $idfPriceRow = $idfPriceRes ? mysqli_fetch_assoc($idfPriceRes) : null;
        mysqli_stmt_close($idfPriceStmt);
    }
    $syncedIdfPrice = isset($idfPriceRow['price']) ? (float)$idfPriceRow['price'] : 0.0;
    if (!$syncOk || abs($syncedIdfPrice - 18.50) > 0.001) {
        rack_verify_fail('idf_unlinked:<token> price sync failed (expected 18.50, got ' . $syncedIdfPrice . ').');
    } else {
        rack_verify_pass('idf_unlinked:<token> price sync updates idf_positions.price.');
    }
    mysqli_query($conn, 'DELETE FROM idf_positions WHERE id = ' . (int)$idfPositionId);
}

if ($failures > 0) {
    echo colorText($failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('All rack_planner checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
