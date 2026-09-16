<?php
/**
 * Back-compat alias for browser + CLI script entry.
 *
 * Why: prefer scripts/lib/itm_script_regression_entry.php — same contract (ITM_CLI_SCRIPT on CLI
 * only; Administrator required in the browser after config.php loads).
 */
require_once __DIR__ . '/itm_script_regression_entry.php';
