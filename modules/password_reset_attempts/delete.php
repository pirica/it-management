<?php
// Why: Security attempts are now unified in modules/attempts to keep all brute-force telemetry in one place.
$itm_attempts_target = '../attempts/' . basename(__FILE__);
if (!empty($_SERVER['QUERY_STRING'])) {
    $itm_attempts_target .= '?' . $_SERVER['QUERY_STRING'];
}
header('Location: ' . $itm_attempts_target);
exit;
