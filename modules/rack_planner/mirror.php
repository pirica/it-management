<?php
/**
 * Rack Planner mirror proxy.
 *
 * Why: Some Apache setups for this legacy app do not serve nested .html files
 * under modules reliably, so we stream the local mirrored file through PHP.
 */

require '../../config/config.php';

$itm_local_mirror = __DIR__ . '/mirror/index-local.html';
$itm_fallback_mirror = __DIR__ . '/mirror/index.html';
$itm_mirror_file = is_file($itm_local_mirror) ? $itm_local_mirror : $itm_fallback_mirror;

if (!is_file($itm_mirror_file) || !is_readable($itm_mirror_file)) {
    http_response_code(404);
    echo 'Rack Planner mirror file not found.';
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
readfile($itm_mirror_file);
exit;
