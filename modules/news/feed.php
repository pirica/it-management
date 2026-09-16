<?php
/**
 * News module — RSS endpoint for external consumers.
 */

require_once __DIR__ . '/news_feed_bootstrap.php';

$sourceId = trim((string)($_GET['source'] ?? 'nvd_cve'));
news_handle_feed_request($sourceId);
