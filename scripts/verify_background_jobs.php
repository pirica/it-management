<?php
/**
 * Regression checks for background_jobs queue and network discovery job wiring.
 *
 * Usage: php scripts/verify_background_jobs.php
 */
declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_background_jobs.php</code> — schema, enqueue dedupe, process batch hook. Run when changing <code>includes/itm_background_jobs.php</code> or discovery job handlers.
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once ROOT_PATH . 'includes/itm_background_jobs.php';
require_once ROOT_PATH . 'includes/itm_network_discovery.php';

itm_script_output_begin('Background Jobs Verification');
$nl = itm_script_output_nl();
$failures = 0;

function bg_verify_fail(string $message): void
{
    global $failures, $nl;
    $failures++;
    echo itm_script_format_status_line('[FAIL] ' . $message) . $nl;
}

function bg_verify_pass(string $message): void
{
    global $nl;
    echo itm_script_format_status_line('[PASS] ' . $message) . $nl;
}

if (!($conn instanceof mysqli)) {
    bg_verify_fail('Database connection unavailable.');
    itm_script_output_end();
    exit(1);
}

$res = mysqli_query($conn, "SHOW TABLES LIKE 'background_jobs'");
if (!$res || mysqli_num_rows($res) === 0) {
    bg_verify_fail('Missing table background_jobs');
} else {
    bg_verify_pass('Table background_jobs exists');
}

$cols = [];
$colRes = mysqli_query($conn, 'SHOW COLUMNS FROM network_discovery_profiles');
while ($colRes && ($colRow = mysqli_fetch_assoc($colRes))) {
    $cols[] = (string)($colRow['Field'] ?? '');
}
if (in_array('scan_in_progress', $cols, true) || in_array('scan_offset', $cols, true)) {
    bg_verify_fail('network_discovery_profiles still has legacy scan_* columns');
} else {
    bg_verify_pass('Profile scan state lives on background_jobs');
}

if (!function_exists('itm_background_jobs_enqueue') || !function_exists('itm_network_discovery_enqueue_profile_scan')) {
    bg_verify_fail('Missing queue helpers');
} else {
    bg_verify_pass('Queue helpers registered');
}

$companyId = 1;
$subnetId = 0;
$subnetRes = mysqli_query($conn, 'SELECT id FROM ip_subnets WHERE company_id = ' . (int)$companyId . ' AND deleted_at IS NULL LIMIT 1');
if ($subnetRes && ($subnetRow = mysqli_fetch_assoc($subnetRes))) {
    $subnetId = (int)($subnetRow['id'] ?? 0);
}
if ($subnetId <= 0) {
    bg_verify_fail('No ip_subnets row for company 1');
    itm_script_output_end();
    exit(1);
}

$token = 'bg-verify-' . bin2hex(random_bytes(3));
$save = itm_network_discovery_save_profile($conn, $companyId, [
    'name' => 'Verify BG ' . $token,
    'schedule_cron' => '0 4 * * *',
    'snmp_enabled' => 0,
    'enabled' => 1,
    'auto_create_policy' => 'review',
    'subnet_ids' => [$subnetId],
], 1);
if (empty($save['ok'])) {
    bg_verify_fail('Profile save failed: ' . ($save['error'] ?? ''));
    itm_script_output_end();
    exit(1);
}
$profileId = (int)($save['id'] ?? 0);
bg_verify_pass('Profile saved id=' . $profileId);

$enqueue1 = itm_network_discovery_enqueue_profile_scan($conn, $profileId, 1);
if (empty($enqueue1['ok'])) {
    bg_verify_fail('Enqueue failed: ' . ($enqueue1['error'] ?? ''));
} else {
    $jobId = (int)($enqueue1['id'] ?? 0);
    bg_verify_pass('Enqueue job id=' . $jobId);
    $enqueue2 = itm_network_discovery_enqueue_profile_scan($conn, $profileId, 1);
    if (empty($enqueue2['ok']) || empty($enqueue2['skipped'])) {
        bg_verify_fail('Duplicate enqueue should skip');
    } else {
        bg_verify_pass('Duplicate enqueue skipped');
    }
    if ($jobId > 0) {
        mysqli_query($conn, 'DELETE FROM background_jobs WHERE id = ' . $jobId);
    }
}

mysqli_query($conn, 'DELETE FROM network_discovery_staging WHERE profile_id = ' . $profileId);
mysqli_query($conn, 'DELETE FROM background_jobs WHERE CAST(JSON_UNQUOTE(JSON_EXTRACT(payload_json, \'$.profile_id\')) AS UNSIGNED) = ' . $profileId);
mysqli_query($conn, 'DELETE FROM network_discovery_profiles WHERE id = ' . $profileId);

if ($failures > 0) {
    bg_verify_fail('Total failures: ' . $failures);
    itm_script_output_end();
    exit(1);
}

bg_verify_pass('All background job checks passed');
itm_script_output_end();
exit(0);
