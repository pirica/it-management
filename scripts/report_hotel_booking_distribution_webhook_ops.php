<?php
/**
 * Ops report: outbound distribution webhook queue (dead-letter monitoring).
 *
 * CLI: php scripts/report_hotel_booking_distribution_webhook_ops.php [--company=1] [--allow-dead]
 * Browser: scripts/report_hotel_booking_distribution_webhook_ops.php?run=1 (Administrator).
 */

declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
CLI: <code>php scripts/report_hotel_booking_distribution_webhook_ops.php</code> — optional <code>--company=1</code>, <code>--allow-dead</code> (do not fail when dead rows exist). Browser: <a href="report_hotel_booking_distribution_webhook_ops.php?run=1">report_hotel_booking_distribution_webhook_ops.php?run=1</a> (Administrator).
<p>Lists webhook queue counts per channel and recent <code>dead</code> / <code>failed</code> rows. Exit <code>1</code> when any dead-letter rows exist unless <code>--allow-dead</code>.</p>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Hotel distribution webhook ops report');

$companyId = (int) ($_SESSION['company_id'] ?? 1);
$allowDead = false;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--company=(\d+)$/', (string) $arg, $m)) {
        $companyId = (int) $m[1];
    }
    if ($arg === '--allow-dead') {
        $allowDead = true;
    }
}

$fail = 0;
$stats = itm_hotel_booking_distribution_webhook_queue_stats($conn, $companyId, 0);
echo '[INFO] Company ' . $companyId . ' totals: pending=' . (int) $stats['pending']
    . ' failed=' . (int) $stats['failed']
    . ' delivered=' . (int) $stats['delivered']
    . ' dead=' . (int) $stats['dead'] . "\n";

if ((int) ($stats['dead'] ?? 0) > 0) {
    if ($allowDead) {
        echo '[WARN] Dead-letter rows present (--allow-dead).' . "\n";
    } else {
        echo '[FAIL] Dead-letter webhook rows require ops attention.' . "\n";
        $fail++;
    }
} else {
    echo "[PASS] No dead-letter webhook rows.\n";
}

$channelRes = mysqli_query(
    $conn,
    'SELECT id, channel_code, name FROM hotel_booking_distribution_channels
     WHERE company_id = ' . (int) $companyId . ' AND deleted_at IS NULL ORDER BY channel_code ASC'
);
while ($channelRes && ($channel = mysqli_fetch_assoc($channelRes))) {
    $cid = (int) ($channel['id'] ?? 0);
    $cstats = itm_hotel_booking_distribution_webhook_queue_stats($conn, $companyId, $cid);
    if ((int) $cstats['total'] === 0) {
        continue;
    }
    echo '  channel ' . ($channel['channel_code'] ?? '') . ': dead=' . (int) $cstats['dead']
        . ' failed=' . (int) $cstats['failed'] . ' pending=' . (int) $cstats['pending'] . "\n";
}

$deadRows = itm_hotel_booking_distribution_webhook_queue_list($conn, $companyId, 0, 'dead', 25);
foreach ($deadRows as $row) {
    echo '[DEAD] queue_id=' . (int) ($row['id'] ?? 0)
        . ' channel_id=' . (int) ($row['channel_id'] ?? 0)
        . ' http=' . (int) ($row['last_http_code'] ?? 0)
        . ' error=' . substr((string) ($row['last_error'] ?? ''), 0, 120) . "\n";
}

itm_script_output_end($fail > 0 ? 1 : 0);
