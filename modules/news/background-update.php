#!/usr/bin/env php
<?php
/**
 * Background news cache update worker (spawned from feed/UI refresh paths).
 */

require_once __DIR__ . '/news_feed_bootstrap.php';

$sourceId = isset($argv[1]) ? trim((string)$argv[1]) : 'nvd_cve';
$sourceId = news_source_cache_basename($sourceId);

if (!news_is_update_locked($sourceId)) {
    echo "No lock found for source {$sourceId} - exiting\n";
    exit(1);
}

echo "Starting background update for {$sourceId}...\n";

$success = news_update_cache($sourceId, news_feed_self_url($sourceId));

news_release_lock($sourceId);

if ($success) {
    echo "Background update completed successfully\n";
    exit(0);
}

echo "Background update failed\n";
exit(1);
