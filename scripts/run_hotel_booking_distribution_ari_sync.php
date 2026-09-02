<?php
/**
 * Push outbound ARI snapshots to all distribution channel webhook_url targets.
 *
 * CLI: php scripts/run_hotel_booking_distribution_ari_sync.php [--company=1] [--days=30]
 * Browser: scripts/run_hotel_booking_distribution_ari_sync.php?run=1 (Administrator).
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/run_hotel_booking_distribution_ari_sync.php</code> — optional <code>--company=1</code>, <code>--days=30</code>. Browser: <a href="run_hotel_booking_distribution_ari_sync.php?run=1">run_hotel_booking_distribution_ari_sync.php?run=1</a> (Administrator).
<p>POSTs ARI snapshots to every active channel with a <code>webhook_url</code> (OpenTravel XML, Booking.com JSON, or OHIP JSON per channel <code>standard</code>).</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Hotel booking distribution ARI sync');

$companyId = 0;
$daysAhead = 30;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--company=(\d+)$/', (string) $arg, $m)) {
        $companyId = (int) $m[1];
    }
    if (preg_match('/^--days=(\d+)$/', (string) $arg, $m)) {
        $daysAhead = (int) $m[1];
    }
}

$results = itm_hotel_booking_distribution_sync_all_channel_ari($conn, $companyId, $daysAhead);
$fail = 0;
if (empty($results)) {
    echo "[PASS] No channels with webhook_url configured (nothing to push).\n";
} else {
    foreach ($results as $idx => $row) {
        if (!empty($row['success'])) {
            echo '[PASS] webhook push hotel_id=' . (int) ($row['hotel_id'] ?? 0) . ' http=' . (int) ($row['http_code'] ?? 0) . "\n";
        } else {
            $fail++;
            echo '[FAIL] webhook push hotel_id=' . (int) ($row['hotel_id'] ?? 0) . ' error=' . ($row['error'] ?? 'unknown') . "\n";
        }
    }
}

itm_script_output_end($fail > 0 ? 1 : 0);
