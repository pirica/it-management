<?php
/**
 * Regression checks for Network Discovery v2.
 *
 * Usage: php scripts/verify_network_discovery.php
 */
declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_network_discovery.php</code> — schema, profile save, staging promote/dismiss, CMDB sync hook. Run when changing <code>includes/itm_network_discovery.php</code> or discovery tables.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once ROOT_PATH . 'includes/itm_network_discovery.php';

itm_script_output_begin('Network Discovery Verification');
$nl = itm_script_output_nl();
$failures = 0;

function nd_verify_fail(string $message): void
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function nd_verify_pass(string $message): void
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    nd_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

foreach (['network_discovery_profiles', 'network_discovery_staging'] as $table) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
    if (!$res || mysqli_num_rows($res) === 0) {
        nd_verify_fail("Missing table {$table}");
    } else {
        nd_verify_pass("Table {$table} exists");
    }
}

if (!function_exists('itm_network_discovery_profile_run_batch')) {
    nd_verify_fail('Missing itm_network_discovery_profile_run_batch()');
} else {
    nd_verify_pass('Orchestration helpers registered');
}

$companyId = 1;
$subnetId = 0;
$subnetRes = mysqli_query($conn, 'SELECT id FROM ip_subnets WHERE company_id = ' . (int)$companyId . ' AND deleted_at IS NULL LIMIT 1');
if ($subnetRes && ($subnetRow = mysqli_fetch_assoc($subnetRes))) {
    $subnetId = (int)($subnetRow['id'] ?? 0);
}
if ($subnetId <= 0) {
    nd_verify_fail('No ip_subnets row for company 1');
    itm_script_output_end();
    exit(1);
}

$token = 'nd-verify-' . bin2hex(random_bytes(3));
$profileName = 'Verify Profile ' . $token;
$save = itm_network_discovery_save_profile($conn, $companyId, [
    'name' => $profileName,
    'schedule_cron' => '0 3 * * *',
    'snmp_enabled' => 0,
    'enabled' => 1,
    'auto_create_policy' => 'review',
    'subnet_ids' => [$subnetId],
], 1);
if (empty($save['ok'])) {
    nd_verify_fail('Profile save failed: ' . ($save['error'] ?? ''));
    itm_script_output_end();
    exit(1);
}
$profileId = (int)($save['id'] ?? 0);
nd_verify_pass('Profile saved id=' . $profileId);

$testIp = '198.51.100.' . random_int(10, 200);
$probeJson = json_encode([
    'port_used' => 80,
    'response_ms' => 12.5,
    'subnet_id' => $subnetId,
    'subnet_cidr' => '198.51.100.0/24',
    'equipment_id' => 0,
], JSON_UNESCAPED_UNICODE);

$insStaging = mysqli_prepare(
    $conn,
    'INSERT INTO network_discovery_staging (company_id, profile_id, ip_address, hostname_guess, probe_json, status, created_by, active)
     VALUES (?, ?, ?, ?, ?, \'pending\', 1, 1)'
);
if (!$insStaging) {
    nd_verify_fail('Staging insert prepare failed');
} else {
    $hostname = 'host-' . $token;
    mysqli_stmt_bind_param($insStaging, 'iisss', $companyId, $profileId, $testIp, $hostname, $probeJson);
    mysqli_stmt_execute($insStaging);
    $stagingId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($insStaging);
    if ($stagingId <= 0) {
        nd_verify_fail('Staging insert failed');
    } else {
        nd_verify_pass('Staging row created');
        $promote = itm_network_discovery_promote_staging($conn, $companyId, $stagingId, 1, false);
        if (empty($promote['ok'])) {
            nd_verify_fail('Promote failed: ' . ($promote['error'] ?? ''));
        } else {
            $eqId = (int)($promote['equipment_id'] ?? 0);
            nd_verify_pass('Promote created equipment id=' . $eqId);
            if ($eqId > 0 && function_exists('itm_cmdb_sync_equipment')) {
                require_once ROOT_PATH . 'includes/itm_cmdb.php';
                $ciCheck = mysqli_prepare(
                    $conn,
                    'SELECT id FROM configuration_items WHERE company_id = ? AND source_module = \'equipment\' AND source_record_id = ? LIMIT 1'
                );
                if ($ciCheck) {
                    mysqli_stmt_bind_param($ciCheck, 'ii', $companyId, $eqId);
                    mysqli_stmt_execute($ciCheck);
                    $ciRes = mysqli_stmt_get_result($ciCheck);
                    $ciRow = $ciRes ? mysqli_fetch_assoc($ciRes) : null;
                    mysqli_stmt_close($ciCheck);
                    if (!$ciRow) {
                        nd_verify_fail('CMDB CI missing after promote');
                    } else {
                        nd_verify_pass('CMDB CI exists for promoted equipment');
                    }
                }
            }
            mysqli_query($conn, 'DELETE FROM network_discovery_staging WHERE profile_id = ' . $profileId);
            if ($eqId > 0) {
                mysqli_query($conn, 'UPDATE network_discovery_staging SET promoted_equipment_id = NULL WHERE promoted_equipment_id = ' . $eqId);
                mysqli_query($conn, 'DELETE FROM equipment WHERE id = ' . $eqId . ' AND company_id = ' . $companyId);
            }
        }
    }
}

mysqli_query($conn, 'DELETE FROM network_discovery_staging WHERE profile_id = ' . $profileId);
mysqli_query($conn, 'DELETE FROM network_discovery_profiles WHERE id = ' . $profileId);

if ($failures > 0) {
    nd_verify_fail('Total failures: ' . $failures);
    itm_script_output_end();
    exit(1);
}

nd_verify_pass('All network discovery checks passed');
itm_script_output_end();
exit(0);
