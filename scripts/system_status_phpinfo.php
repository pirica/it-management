<?php
/**
 * Admin-only phpinfo() for the active Apache PHP runtime (System Status helper).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();

$nl = itm_script_output_nl();

itm_script_require_admin_script_or_exit($conn);

phpinfo();

itm_script_output_end();
