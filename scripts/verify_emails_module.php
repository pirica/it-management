<?php
/**
 * Email Management module regression checks.
 *
 * CLI: php scripts/verify_emails_module.php
 * Browser: scripts/verify_emails_module.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Email Management Verification');

$nl = itm_script_output_nl();
$failures = 0;

function emails_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function emails_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    emails_verify_fail('No database connection.');
    exit(1);
}

$requiredTables = ['emails', 'email_smtp_configurations', 'email_alert_rules'];
foreach ($requiredTables as $table) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
        if ((int)$count !== 1) {
            emails_verify_fail('Missing table: ' . $table);
        } else {
            emails_verify_pass('Table exists: ' . $table);
        }
    }
}

if (!function_exists('itm_send_email')) {
    emails_verify_fail('itm_send_email() helper missing.');
} else {
    emails_verify_pass('itm_send_email() helper loaded.');
}

$registryStmt = mysqli_prepare($conn, 'SELECT id FROM modules_registry WHERE module_slug = ? LIMIT 1');
$slug = 'emails';
if ($registryStmt) {
    mysqli_stmt_bind_param($registryStmt, 's', $slug);
    mysqli_stmt_execute($registryStmt);
    mysqli_stmt_bind_result($registryStmt, $registryId);
    $hasRegistry = mysqli_stmt_fetch($registryStmt);
    mysqli_stmt_close($registryStmt);
    if (!$hasRegistry) {
        emails_verify_fail('modules_registry row missing for emails.');
    } else {
        emails_verify_pass('modules_registry row present for emails.');
    }
}

$companyId = 1;
$defaultCfg = itm_email_get_default_smtp_config($conn, $companyId);
if (!$defaultCfg) {
    emails_verify_fail('No default SMTP configuration seed for company 1.');
} else {
    emails_verify_pass('Default SMTP configuration resolved for company 1.');
}

$rules = itm_email_get_alert_rules($conn, $companyId);
$catalog = itm_email_alert_rule_catalog();
foreach (array_keys($catalog) as $slug) {
    if (!isset($rules[$slug])) {
        emails_verify_fail('Missing alert rule seed: ' . $slug);
    }
}
if ($failures === 0) {
    emails_verify_pass('Alert rule seeds present for company 1.');
}

$logStmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM emails WHERE company_id = ?');
if ($logStmt) {
    mysqli_stmt_bind_param($logStmt, 'i', $companyId);
    mysqli_stmt_execute($logStmt);
    mysqli_stmt_bind_result($logStmt, $logCount);
    mysqli_stmt_fetch($logStmt);
    mysqli_stmt_close($logStmt);
    if ((int)$logCount < 1) {
        emails_verify_fail('Sample send log seed missing for company 1.');
    } else {
        emails_verify_pass('Send log seed present for company 1.');
    }
}

if (is_file(dirname(__DIR__) . '/modules/emails/index.php')) {
    emails_verify_pass('modules/emails/index.php exists.');
} else {
    emails_verify_fail('modules/emails/index.php missing.');
}

$scriptFiles = ['test_email_forgot.php', 'test_register_mail.php', 'run_email_alert_rules.php'];
foreach ($scriptFiles as $scriptFile) {
    if (!is_file(__DIR__ . '/' . $scriptFile)) {
        emails_verify_fail('Missing script: scripts/' . $scriptFile);
    }
}
if ($failures === 0) {
    emails_verify_pass('Email delivery test scripts present.');
}

$today = date('Y-m-d');
$cutoff = date('Y-m-d', strtotime('+30 days'));
$alertSeedCount = 0;
$warrantyStmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) FROM equipment
     WHERE company_id = ? AND active = 1 AND warranty_expiry IS NOT NULL
       AND warranty_expiry >= ? AND warranty_expiry <= ?'
);
if ($warrantyStmt) {
    mysqli_stmt_bind_param($warrantyStmt, 'iss', $companyId, $today, $cutoff);
    mysqli_stmt_execute($warrantyStmt);
    mysqli_stmt_bind_result($warrantyStmt, $warrantyCount);
    mysqli_stmt_fetch($warrantyStmt);
    mysqli_stmt_close($warrantyStmt);
    $alertSeedCount += (int)$warrantyCount;
}
$licenseStmt = mysqli_prepare(
    $conn,
    'SELECT COUNT(*) FROM license_management
     WHERE company_id = ? AND active = 1 AND expiry_date IS NOT NULL
       AND expiry_date >= ? AND expiry_date <= ?'
);
if ($licenseStmt) {
    mysqli_stmt_bind_param($licenseStmt, 'iss', $companyId, $today, $cutoff);
    mysqli_stmt_execute($licenseStmt);
    mysqli_stmt_bind_result($licenseStmt, $licenseCount);
    mysqli_stmt_fetch($licenseStmt);
    mysqli_stmt_close($licenseStmt);
    $alertSeedCount += (int)$licenseCount;
}
if ($alertSeedCount < 1) {
    emails_verify_fail('No warranty/license rows in the 30-day alert window for company 1 (run_email_alert_rules needs seed data).');
} else {
    emails_verify_pass('Alert runner seed data present for company 1 (' . $alertSeedCount . ' row(s) in 30-day window).');
}

if ($failures > 0) {
    echo colorText('Verification finished with ' . $failures . ' failure(s).', 'fail') . $nl;
    exit(1);
}

echo colorText('All email module checks passed.', 'pass') . $nl;
exit(0);
