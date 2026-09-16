<?php
/**
 * Process pending/failed outbound distribution webhook queue (retries + dead-letter).
 *
 * CLI: php scripts/run_hotel_booking_distribution_webhook_queue.php [--company=1] [--limit=50]
 * Browser: scripts/run_hotel_booking_distribution_webhook_queue.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/run_hotel_booking_distribution_webhook_queue.php</code> — optional <code>--company=1</code>, <code>--limit=50</code>. Browser: <a href="run_hotel_booking_distribution_webhook_queue.php?run=1">run_hotel_booking_distribution_webhook_queue.php?run=1</a> (Administrator).
<p>Retries outbound webhook deliveries with exponential backoff; rows exceeding <code>max_attempts</code> move to <code>dead</code> status.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Hotel booking distribution webhook queue');

$companyId = 0;
$limit = 50;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--company=(\d+)$/', (string) $arg, $m)) {
        $companyId = (int) $m[1];
    }
    if (preg_match('/^--limit=(\d+)$/', (string) $arg, $m)) {
        $limit = (int) $m[1];
    }
}

$results = itm_hotel_booking_distribution_process_webhook_queue($conn, $limit, $companyId);
$fail = 0;
if (empty($results)) {
    echo "[PASS] No pending webhook queue rows.\n";
} else {
    foreach ($results as $row) {
        if (!empty($row['success'])) {
            echo '[PASS] queue_id=' . (int) ($row['queue_id'] ?? 0) . ' http=' . (int) ($row['http_code'] ?? 0) . "\n";
        } else {
            $fail++;
            echo '[FAIL] queue_id=' . (int) ($row['queue_id'] ?? 0) . ' status=' . ($row['status'] ?? 'unknown') . "\n";
        }
    }
}

itm_script_output_end($fail > 0 ? 1 : 0);
