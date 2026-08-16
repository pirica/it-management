<?php
/**
 * Admin-only phpinfo() for the active Apache PHP runtime (System Status helper).
 */


/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
GET <code>scripts/system_status_phpinfo.php</code> (Admin session)
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$nl = itm_script_output_nl();

itm_script_require_admin_script_or_exit($conn);

phpinfo();

itm_script_output_end();
