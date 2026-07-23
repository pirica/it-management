#!/usr/bin/env php
<?php
/**
 * Background CVE cache update worker (spawned from feed/UI refresh paths).
 */

require_once __DIR__ . '/cve_feed_bootstrap.php';

if (!cve_is_update_locked()) {
    echo "No lock found - exiting\n";
    exit(1);
}

echo "Starting background update...\n";

$selfUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/modules/cve/feed.php' : '';
$success = cve_update_cache($selfUrl);

cve_release_lock();

if ($success) {
    echo "Background update completed successfully\n";
    exit(0);
}

echo "Background update failed\n";
exit(1);
