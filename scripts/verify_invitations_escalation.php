<?php
/**
 * Verification script for Privilege Escalation in Registration Invitations.
 *
 * Why: Confirms if regular users can access the registration invitations module.
 *
 * Browser: open scripts/verify_invitations_escalation.php (login required).
 */

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Registration Invitations Escalation Verification');

$nl = itm_script_output_nl();
echo "Verifying Authorization on Registration Invitations module..." . $nl;

$targetScript = 'modules/registration_invitations/index.php';
if (!file_exists(__DIR__ . '/../' . $targetScript)) {
    echo colorText("[WARN] Script $targetScript not found.", 'warn') . $nl;
    exit;
}

$content = file_get_contents(__DIR__ . '/../' . $targetScript);

if (strpos($content, 'itm_is_admin') === false) {
    echo colorText("[FAIL] VULNERABLE: $targetScript lacks itm_is_admin() check. Regular users can manage invitations.", 'fail') . $nl;
} else {
    echo colorText("[PASS] SAFE: $targetScript contains itm_is_admin() check.", 'pass') . $nl;
}

$createScript = 'modules/registration_invitations/create.php';
if (file_exists(__DIR__ . '/../' . $createScript)) {
    $createContent = file_get_contents(__DIR__ . '/../' . $createScript);
    if (strpos($createContent, 'itm_is_admin') === false) {
        echo colorText("[FAIL] VULNERABLE: $createScript lacks itm_is_admin() check. Regular users can create privileged invitations.", 'fail') . $nl;
    } else {
        echo colorText("[PASS] SAFE: $createScript contains itm_is_admin() check.", 'pass') . $nl;
    }
}
