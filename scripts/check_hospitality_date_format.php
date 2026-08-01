<?php
/**
 * Hospitality-only date format gate (subset of check_date_format.php).
 */
define('ITM_CLI_SCRIPT', true);

require_once __DIR__ . '/lib/itm_date_format_audit.php';

$fail = itm_date_format_audit_run_helper_contracts();
$fail += itm_date_format_audit_run_hospitality_static();

exit($fail > 0 ? 1 : 0);
