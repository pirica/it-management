<?php
/**
 * Verification script for Privilege Escalation in Registration Invitations.
 *
 * Why: Confirms if regular users can access the registration invitations module.
 *
 * Browser: open scripts/verify_invitations_escalation.php (login required).
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
<code>php scripts/verify_invitations_escalation.php</code>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
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

if (strpos($content, 'itm_is_admin') === false && strpos($content, 'itm_require_admin') === false) {
    echo colorText("[FAIL] VULNERABLE: $targetScript lacks itm_is_admin() or itm_require_admin() check. Regular users can manage invitations.", 'fail') . $nl;
} else {
    echo colorText("[PASS] SAFE: $targetScript contains itm_is_admin() check.", 'pass') . $nl;
}

$createScript = 'modules/registration_invitations/create.php';
if (file_exists(__DIR__ . '/../' . $createScript)) {
    $createContent = file_get_contents(__DIR__ . '/../' . $createScript);
    if (strpos($createContent, 'itm_is_admin') === false && strpos($createContent, 'itm_require_admin') === false) {
        echo colorText("[FAIL] VULNERABLE: $createScript lacks itm_is_admin() or itm_require_admin() check. Regular users can create privileged invitations.", 'fail') . $nl;
    } else {
        echo colorText("[PASS] SAFE: $createScript contains itm_is_admin() check.", 'pass') . $nl;
    }
}

itm_script_output_end();
