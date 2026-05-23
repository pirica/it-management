<?php
/**
 * Regression: ticket sample data links Primary File Server; equipment delete is blocked.
 *
 * Usage (Laragon PHP 7.4+, repository root):
 *   php scripts/tickets_related_asset_equipment_delete_test.php
 *
 * Optional env:
 *   ITM_SKIP_DB_TESTS=1   Skip MySQL integration (static checks still run)
 *   ITM_TEST_COMPANY_ID   Tenant for seed/delete test (default: 1)
 */

if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    fwrite(STDERR, "This script requires PHP 7.1 or newer.\n");
    exit(1);
}

define('ITM_CLI_SCRIPT', true);

$projectRoot = dirname(__DIR__);
require $projectRoot . '/config/config.php';
require $projectRoot . '/includes/detect_fk_dropdown_ui_risk_lib.php';
require $projectRoot . '/modules/tickets/sample_seed_helpers.php';
require $projectRoot . '/modules/equipment/delete_functions.php';

function tradt_is_cli(): bool
{
    return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function tradt_out($message)
{
    $line = (string)$message;
    echo tradt_is_cli() ? $line . PHP_EOL : htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . "<br>\n";
}

function tradt_fail($message)
{
    throw new RuntimeException('[FAIL] ' . $message);
}

function tradt_assert($condition, $message)
{
    if (!$condition) {
        tradt_fail($message);
    }
    tradt_out('[PASS] ' . $message);
}

function tradt_set_audit_context(mysqli $conn, int $companyId, int $userId = 1): void
{
    mysqli_query($conn, 'SET @app_user_id = ' . (int)$userId);
    mysqli_query($conn, 'SET @app_company_id = ' . (int)$companyId);
    mysqli_query($conn, "SET @app_username = 'cli-test'");
    mysqli_query($conn, "SET @app_email = 'cli-test@example.com'");
    mysqli_query($conn, "SET @app_ip_address = '127.0.0.1'");
    mysqli_query($conn, "SET @app_user_agent = 'tickets_related_asset_equipment_delete_test'");
}

function tradt_run_static_checks(): void
{
    tradt_assert(function_exists('tickets_seed_lookup_parents'), 'tickets_seed_lookup_parents is available');
    tradt_assert(function_exists('tickets_repair_sample_asset_links'), 'tickets_repair_sample_asset_links is available');
    tradt_assert(function_exists('tickets_sample_primary_file_server_id'), 'tickets_sample_primary_file_server_id is available');
    tradt_assert(function_exists('equipment_delete_record'), 'equipment_delete_record is available');

    $equipmentKeys = itm_detect_fk_business_key_columns('equipment', ['name', 'serial_number', 'id']);
    tradt_assert(
        in_array('serial_number', $equipmentKeys, true),
        'equipment FK business-key remap includes serial_number for sample asset_id'
    );
}

function tradt_delete_sample_ticket(mysqli $conn, int $companyId): void
{
    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM tickets WHERE company_id = ? AND ticket_external_code = 'TCK-0001' LIMIT 1"
    );
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function tradt_run_db_integration(mysqli $conn): void
{
    $companyId = (int)(getenv('ITM_TEST_COMPANY_ID') ?: 1);
    if ($companyId <= 0) {
        tradt_fail('ITM_TEST_COMPANY_ID must be a positive integer');
    }

    tradt_set_audit_context($conn, $companyId);

    tickets_seed_lookup_parents($conn, $companyId);

    $equipmentId = tickets_sample_primary_file_server_id($conn, $companyId);
    tradt_assert($equipmentId > 0, 'Primary File Server exists for tenant ' . $companyId);

    tradt_delete_sample_ticket($conn, $companyId);

    $seedError = '';
    $inserted = itm_seed_table_from_database_sql($conn, 'tickets', $companyId, $seedError);
    tradt_assert($inserted > 0, 'ticket sample seed inserted rows: ' . ($seedError !== '' ? $seedError : (string)$inserted));

    $repaired = tickets_repair_sample_asset_links($conn, $companyId);
    tradt_out('[INFO] tickets_repair_sample_asset_links updated ' . (int)$repaired . ' row(s)');

    $linkRes = mysqli_query(
        $conn,
        "SELECT asset_id FROM tickets WHERE company_id = " . (int)$companyId
        . " AND ticket_external_code = 'TCK-0001' LIMIT 1"
    );
    $linkRow = ($linkRes) ? mysqli_fetch_assoc($linkRes) : null;
    $linkedAssetId = is_array($linkRow) ? (int)($linkRow['asset_id'] ?? 0) : 0;
    tradt_assert(
        $linkedAssetId === $equipmentId,
        'sample ticket TCK-0001 asset_id links to Primary File Server (id ' . $equipmentId . ')'
    );

    // Why: FK remap fallback can point sample tickets at the wrong equipment row; repair must fix that too.
    $wrongRes = mysqli_query(
        $conn,
        "SELECT id FROM equipment WHERE company_id = " . (int)$companyId
        . " AND id <> " . (int)$equipmentId . " ORDER BY id ASC LIMIT 1"
    );
    $wrongRow = ($wrongRes) ? mysqli_fetch_assoc($wrongRes) : null;
    $wrongEquipmentId = is_array($wrongRow) ? (int)($wrongRow['id'] ?? 0) : 0;
    if ($wrongEquipmentId > 0) {
        $wrongStmt = mysqli_prepare(
            $conn,
            "UPDATE tickets SET asset_id = ? WHERE company_id = ? AND ticket_external_code = 'TCK-0001' LIMIT 1"
        );
        if ($wrongStmt) {
            mysqli_stmt_bind_param($wrongStmt, 'ii', $wrongEquipmentId, $companyId);
            mysqli_stmt_execute($wrongStmt);
            mysqli_stmt_close($wrongStmt);
        }

        $repairedWrong = tickets_repair_sample_asset_links($conn, $companyId);
        tradt_assert($repairedWrong > 0, 'repair re-links sample ticket after stale wrong asset_id');

        $linkRes = mysqli_query(
            $conn,
            "SELECT asset_id FROM tickets WHERE company_id = " . (int)$companyId
            . " AND ticket_external_code = 'TCK-0001' LIMIT 1"
        );
        $linkRow = ($linkRes) ? mysqli_fetch_assoc($linkRes) : null;
        $linkedAssetId = is_array($linkRow) ? (int)($linkRow['asset_id'] ?? 0) : 0;
        tradt_assert(
            $linkedAssetId === $equipmentId,
            'repair restored Primary File Server link on TCK-0001'
        );
    } else {
        tradt_out('[INFO] skipped stale asset_id repair case (no alternate equipment row)');
    }

    $deleteError = equipment_delete_record($conn, $companyId, $equipmentId);
    tradt_assert($deleteError !== null && $deleteError !== '', 'equipment delete returns an error when linked from a ticket');
    tradt_assert(
        stripos($deleteError, 'Related Asset') !== false || stripos($deleteError, 'in use') !== false,
        'delete error mentions ticket Related Asset / in use: ' . $deleteError
    );

    tradt_delete_sample_ticket($conn, $companyId);
    tradt_out('[PASS] removed temporary TCK-0001 ticket after assertions');
}

if (!tradt_is_cli()) {
    header('Content-Type: text/html; charset=UTF-8');
    require_once __DIR__ . '/lib/script_browser_nav.php';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Tickets Related Asset delete test</title></head><body style="font-family:Segoe UI,system-ui,sans-serif;margin:16px;">';
    itm_script_browser_nav_echo();
}

$failures = 0;
tradt_out('Tickets Related Asset / equipment delete regression');
tradt_out('PHP ' . PHP_VERSION);

try {
    tradt_run_static_checks();
} catch (Throwable $e) {
    tradt_out($e->getMessage());
    $failures++;
}

$skipDb = getenv('ITM_SKIP_DB_TESTS') === '1' || getenv('ITM_SKIP_DB_TESTS') === 'true';
if (!tradt_is_cli()) {
    tradt_out('[SKIP] Database integration (CLI only — browser runs static checks only)');
} elseif ($skipDb) {
    tradt_out('[SKIP] Database integration (ITM_SKIP_DB_TESTS=1)');
} elseif (!isset($conn) || !($conn instanceof mysqli)) {
    tradt_out('[SKIP] Database integration (no mysqli connection)');
} else {
    try {
        tradt_run_db_integration($conn);
    } catch (Throwable $e) {
        tradt_out($e->getMessage());
        $failures++;
    }
}

if (!tradt_is_cli()) {
    echo '</body></html>';
}

if ($failures > 0) {
    tradt_out('');
    tradt_out('Result: FAILED (' . $failures . ')');
    exit(1);
}

tradt_out('');
tradt_out('Result: OK');
exit(0);
