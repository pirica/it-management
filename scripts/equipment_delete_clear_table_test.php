<?php
/**
 * Regression tests for equipment clear_table and transactional single deletes.
 *
 * Usage (Laragon PHP 7.4+, repository root):
 *   php scripts/equipment_delete_clear_table_test.php
 *
 * Optional env:
 *   ITM_SKIP_DB_TESTS=1   Skip integration cases (static checks still run)
 *   ITM_TEST_COMPANY_ID   Audit bootstrap company (default: 1)
 *
 * Note: equipment type names must stay exactly "Switch" / "Server" (no suffix). Unique names
 * like "Switch itm_eqdct_*" trigger itm_ensure_equipment_type_module_scaffold() and create junk
 * modules/is_switch_itm_eqdct_* directories. Canonical modules/is_switch, is_server, … are kept.
 */

if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    fwrite(STDERR, "This script requires PHP 7.1 or newer.\n");
    exit(1);
}

define('ITM_CLI_SCRIPT', true);

$projectRoot = dirname(__DIR__);
require $projectRoot . '/config/config.php';
require $projectRoot . '/modules/equipment/delete_functions.php';
require __DIR__ . '/lib/equipment_type_modules.php';

function eqdct_is_cli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function eqdct_out($message)
{
    $line = (string)$message;
    echo eqdct_is_cli() ? $line . PHP_EOL : htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "<br>\n";
}

function eqdct_fail($message)
{
    throw new RuntimeException('[FAIL] ' . $message);
}

function eqdct_assert($condition, $message)
{
    if (!$condition) {
        eqdct_fail($message);
    }
    eqdct_out('[PASS] ' . $message);
}

function eqdct_set_audit_context(mysqli $conn, int $companyId, int $userId = 1): void
{
    mysqli_query($conn, 'SET @app_user_id = ' . (int)$userId);
    mysqli_query($conn, 'SET @app_company_id = ' . (int)$companyId);
    mysqli_query($conn, "SET @app_username = 'cli-test'");
    mysqli_query($conn, "SET @app_email = 'cli-test@example.com'");
    mysqli_query($conn, "SET @app_ip_address = '127.0.0.1'");
    mysqli_query($conn, "SET @app_user_agent = 'equipment_delete_clear_table_test'");
}

function eqdct_count_equipment(mysqli $conn, int $companyId): int
{
    $res = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM equipment WHERE company_id=' . (int)$companyId);
    if (!$res) {
        return -1;
    }
    $row = mysqli_fetch_assoc($res);
    return (int)($row['c'] ?? 0);
}

function eqdct_count_switch_ports(mysqli $conn, int $companyId, int $equipmentId): int
{
    $res = mysqli_query(
        $conn,
        'SELECT COUNT(*) AS c FROM switch_ports WHERE company_id=' . (int)$companyId
        . ' AND equipment_id=' . (int)$equipmentId
    );
    if (!$res) {
        return -1;
    }
    $row = mysqli_fetch_assoc($res);
    return (int)($row['c'] ?? 0);
}

function eqdct_insert_company(mysqli $conn, string $name): int
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO companies (company, active) VALUES (?, 1)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 's', $name);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function eqdct_delete_company(mysqli $conn, int $companyId): void
{
    if ($companyId > 0) {
        mysqli_query($conn, 'DELETE FROM companies WHERE id=' . (int)$companyId . ' LIMIT 1');
    }
}

function eqdct_insert_equipment_type(mysqli $conn, int $companyId, string $name): int
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO equipment_types (company_id, name, active) VALUES (?, ?, 1)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $name);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function eqdct_insert_equipment_status(mysqli $conn, int $companyId, string $name): int
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO equipment_statuses (company_id, name) VALUES (?, ?)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $name);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function eqdct_insert_cable_color(mysqli $conn, int $companyId, string $name): int
{
    $hex = '#808080';
    $stmt = mysqli_prepare($conn, 'INSERT INTO cable_colors (company_id, color_name, hex_color) VALUES (?, ?, ?)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'iss', $companyId, $name, $hex);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function eqdct_insert_switch_status(mysqli $conn, int $companyId, int $colorId, string $status): int
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO switch_status (company_id, status, color_id) VALUES (?, ?, ?)');
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'isi', $companyId, $status, $colorId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function eqdct_insert_equipment(
    mysqli $conn,
    int $companyId,
    int $typeId,
    int $statusId,
    string $name
): int {
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO equipment (company_id, equipment_type_id, status_id, name, active) VALUES (?, ?, ?, ?, 1)'
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'iiis', $companyId, $typeId, $statusId, $name);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return 0;
    }
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function eqdct_insert_switch_port_type(mysqli $conn, int $companyId, string $type): bool
{
    $stmt = mysqli_prepare($conn, 'INSERT INTO switch_port_types (company_id, type) VALUES (?, ?)');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $type);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (bool)$ok;
}

function eqdct_insert_switch_port(
    mysqli $conn,
    int $companyId,
    int $equipmentId,
    int $statusId,
    int $colorId,
    int $portNumber
): bool {
    $portType = 'RJ45';
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO switch_ports (company_id, equipment_id, port_type, port_number, status_id, color_id) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iisiii', $companyId, $equipmentId, $portType, $portNumber, $statusId, $colorId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return (bool)$ok;
}

function eqdct_cleanup_test_scaffold_modules(): int
{
    return itm_remove_equipment_regression_test_module_dirs(dirname(__DIR__) . '/modules');
}

function eqdct_run_static_checks(): void
{
    $deletePath = dirname(__DIR__) . '/modules/equipment/delete.php';
    $functionsPath = dirname(__DIR__) . '/modules/equipment/delete_functions.php';
    eqdct_assert(is_file($deletePath), 'delete.php exists');
    eqdct_assert(is_file($functionsPath), 'delete_functions.php exists');

    $deleteSource = (string)file_get_contents($deletePath);
    $functionsSource = (string)file_get_contents($functionsPath);

    eqdct_assert(stripos($deleteSource, 'delete_functions.php') !== false, 'delete.php loads delete_functions.php');
    eqdct_assert(stripos($deleteSource, 'equipment_clear_table_for_company') !== false, 'delete.php calls equipment_clear_table_for_company');
    eqdct_assert(stripos($functionsSource, '$listResult === false') !== false, 'helper fails when equipment SELECT fails');
    eqdct_assert(stripos($functionsSource, 'mysqli_begin_transaction') !== false, 'equipment_delete_record uses transactions');
    eqdct_assert(stripos($functionsSource, 'mysqli_rollback') !== false, 'equipment_delete_record rolls back on errors');
}

function eqdct_run_db_integration(mysqli $conn): void
{
    $bootstrapCompanyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
    if ($bootstrapCompanyId <= 0) {
        $bootstrapCompanyId = 1;
    }
    eqdct_set_audit_context($conn, $bootstrapCompanyId);

    $suffix = 'itm_eqdct_' . gmdate('YmdHis') . '_' . mt_rand(1000, 9999);
    $companyId = eqdct_insert_company($conn, 'ITM Equipment ClearTable ' . $suffix);
    eqdct_assert($companyId > 0, 'created isolated test company');

    try {
        eqdct_set_audit_context($conn, $companyId);

        $switchTypeId = eqdct_insert_equipment_type($conn, $companyId, 'Switch');
        eqdct_assert($switchTypeId > 0, 'seeded switch equipment type');
        $serverTypeId = eqdct_insert_equipment_type($conn, $companyId, 'Server');
        eqdct_assert($serverTypeId > 0, 'seeded server equipment type');
        $statusId = eqdct_insert_equipment_status($conn, $companyId, 'Active ' . $suffix);
        eqdct_assert($statusId > 0, 'seeded equipment status');
        $colorId = eqdct_insert_cable_color($conn, $companyId, 'G' . substr($suffix, -8));
        eqdct_assert($colorId > 0, 'seeded cable color');
        $switchStatusId = eqdct_insert_switch_status($conn, $companyId, $colorId, 'Unknown ' . $suffix);
        eqdct_assert($switchStatusId > 0, 'seeded switch status');
        eqdct_assert(eqdct_insert_switch_port_type($conn, $companyId, 'RJ45'), 'seeded switch_port_types RJ45 row');

        $switchId = eqdct_insert_equipment($conn, $companyId, $switchTypeId, $statusId, 'Switch-' . $suffix);
        eqdct_assert($switchId > 0, 'inserted switch equipment');
        eqdct_assert(eqdct_insert_switch_port($conn, $companyId, $switchId, $switchStatusId, $colorId, 1), 'inserted switch port for switch equipment');
        eqdct_assert(eqdct_count_switch_ports($conn, $companyId, $switchId) === 1, 'switch has one port before clear');

        $clearError = equipment_clear_table_for_company($conn, $companyId);
        eqdct_assert($clearError === null, 'clear_table removes switch and ports: ' . (string)$clearError);
        eqdct_assert(eqdct_count_equipment($conn, $companyId) === 0, 'equipment cleared for test company');
        eqdct_assert(eqdct_count_switch_ports($conn, $companyId, $switchId) === 0, 'switch ports cleared with switch equipment');

        $serverId = eqdct_insert_equipment($conn, $companyId, $serverTypeId, $statusId, 'Server-' . $suffix);
        eqdct_assert($serverId > 0, 'inserted server equipment for usage-block test');
        eqdct_assert(eqdct_insert_switch_port($conn, $companyId, $serverId, $switchStatusId, $colorId, 2), 'linked switch_port blocks server delete via usage check');
        eqdct_assert(eqdct_count_switch_ports($conn, $companyId, $serverId) === 1, 'server has dependent switch_port row');

        $blockedDelete = equipment_delete_record($conn, $companyId, $serverId);
        eqdct_assert($blockedDelete !== null && $blockedDelete !== '', 'server delete fails while switch_ports reference it');
        eqdct_assert(eqdct_count_equipment($conn, $companyId) === 1, 'rollback keeps server equipment row');
        eqdct_assert(eqdct_count_switch_ports($conn, $companyId, $serverId) === 1, 'rollback keeps switch_port row');

        equipment_delete_switch_port_data($conn, $companyId, $serverId);
        $afterPortCleanup = equipment_delete_record($conn, $companyId, $serverId);
        eqdct_assert($afterPortCleanup === null, 'server deletes after port dependency removed: ' . (string)$afterPortCleanup);
        eqdct_assert(eqdct_count_equipment($conn, $companyId) === 0, 'no equipment left after successful delete');
    } finally {
        eqdct_delete_company($conn, $companyId);
        eqdct_out('[PASS] removed temporary test company');
        $scaffoldRemoved = eqdct_cleanup_test_scaffold_modules();
        if ($scaffoldRemoved > 0) {
            eqdct_out('[PASS] removed ' . $scaffoldRemoved . ' accidental equipment-type module folder(s)');
        }
    }
}

if (!eqdct_is_cli()) {
    header('Content-Type: text/html; charset=UTF-8');
    require_once __DIR__ . '/lib/script_browser_nav.php';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Equipment clear_table test</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
}

$failures = 0;
eqdct_out('Equipment clear_table regression');
eqdct_out('PHP ' . PHP_VERSION);

if (eqdct_is_cli()) {
    $preCleaned = eqdct_cleanup_test_scaffold_modules();
    if ($preCleaned > 0) {
        eqdct_out('[PASS] pre-clean removed ' . $preCleaned . ' accidental equipment-type module folder(s)');
    }
}

try {
    eqdct_run_static_checks();
} catch (Throwable $e) {
    eqdct_out($e->getMessage());
    $failures++;
}

$skipDb = getenv('ITM_SKIP_DB_TESTS') === '1' || getenv('ITM_SKIP_DB_TESTS') === 'true';
if (!eqdct_is_cli()) {
    eqdct_out('[SKIP] Database integration (CLI only — browser runs static checks only)');
} elseif ($skipDb) {
    eqdct_out('[SKIP] Database integration (ITM_SKIP_DB_TESTS=1)');
} elseif (!isset($conn) || !($conn instanceof mysqli)) {
    eqdct_out('[SKIP] Database integration (no mysqli connection)');
} else {
    try {
        eqdct_run_db_integration($conn);
    } catch (Throwable $e) {
        eqdct_out($e->getMessage());
        $failures++;
    }
}

if (!eqdct_is_cli()) {
    echo '</body></html>';
}

if ($failures > 0) {
    eqdct_out('');
    eqdct_out('Result: FAILED (' . $failures . ')');
    exit(1);
}

eqdct_out('');
eqdct_out('Result: OK');
exit(0);
