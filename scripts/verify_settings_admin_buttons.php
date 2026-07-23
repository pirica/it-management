<?php
/**
 * Settings admin toolbar regression checks.
 *
 * CLI: php scripts/verify_settings_admin_buttons.php
 * Browser: scripts/verify_settings_admin_buttons.php
 */

declare(strict_types=1);

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Settings Admin Buttons Verification');

$nl = itm_script_output_nl();
$failures = 0;

function sab_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function sab_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

$settingsPath = ROOT_PATH . 'modules/settings/index.php';
if (!is_file($settingsPath)) {
    sab_verify_fail('Missing modules/settings/index.php');
    itm_script_output_end();
    exit(1);
}

$settingsSource = (string)file_get_contents($settingsPath);

if (strpos($settingsSource, 'itm_is_admin(') === false) {
    sab_verify_fail('settings/index.php must gate admin buttons with itm_is_admin()');
} else {
    sab_verify_pass('settings/index.php uses itm_is_admin()');
}

if (strpos($settingsSource, 'admin.php') === false) {
    sab_verify_fail('settings/index.php must link ADMIN button to admin.php');
} else {
    sab_verify_pass('settings/index.php links to admin.php');
}

if (strpos($settingsSource, 'scripts/scripts.php') === false) {
    sab_verify_fail('settings/index.php must link SCRIPTS button to scripts/scripts.php');
} else {
    sab_verify_pass('settings/index.php links to scripts/scripts.php');
}

if (strpos($settingsSource, '>ADMIN<') === false) {
    sab_verify_fail('settings/index.php must render ADMIN button label');
} else {
    sab_verify_pass('settings/index.php renders ADMIN button');
}

if (strpos($settingsSource, '>SCRIPTS<') === false) {
    sab_verify_fail('settings/index.php must render SCRIPTS button label');
} else {
    sab_verify_pass('settings/index.php renders SCRIPTS button');
}

if (strpos($settingsSource, 'All roles') === false) {
    sab_verify_fail('settings/index.php must render All roles section for enable_chatbot');
} else {
    sab_verify_pass('settings/index.php renders All roles section');
}

if (strpos($settingsSource, 'System (Admin Role only)') === false) {
    sab_verify_fail('settings/index.php must render System (Admin Role only) section');
} else {
    sab_verify_pass('settings/index.php renders System (Admin Role only) section');
}

if (strpos($settingsSource, '$settingsIsAdmin') === false) {
    sab_verify_fail('settings/index.php must define $settingsIsAdmin for system-flag gating');
} else {
    sab_verify_pass('settings/index.php defines $settingsIsAdmin');
}

if (strpos($settingsSource, 'non-admins must not change them via crafted POST') === false) {
    sab_verify_fail('settings/index.php save_ui_config must preserve admin-only system flags for non-admins');
} else {
    sab_verify_pass('settings/index.php preserves admin-only system flags on save');
}

if ($failures > 0) {
    echo $nl . colorText('Verification failed with ' . $failures . ' issue(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo $nl . colorText('All settings admin button checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
