<?php
/**
 * Generic job queue regression checks.
 * CLI: php scripts/verify_job_queue.php
 */
declare(strict_types=1);

function itm_script_browser_how_to_use(): string
{
    return <<<'ITM'
<code>php scripts/verify_job_queue.php</code> — validates <code>job_queue</code> schema, enqueue/claim/complete/fail/retry, and handler registration.
ITM;
}

define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';
require_once ROOT_PATH . 'includes/itm_job_queue.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Verify Job Queue');
$nl = itm_script_output_nl();
$failures = 0;

function jq_fail(string $msg): void
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $msg, 'fail') . $nl;
}

function jq_pass(string $msg): void
{
    global $nl;
    echo colorText('[PASS] ' . $msg, 'pass') . $nl;
}

$res = mysqli_query($conn, "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'job_queue'");
$row = $res ? mysqli_fetch_assoc($res) : null;
if ((int)($row['c'] ?? 0) < 1) {
    jq_fail('Missing table job_queue');
} else {
    jq_pass('Table job_queue exists');
}

$expectedTypes = ['webhook_delivery', 'scheduled_report', 'network_discovery', 'license_compliance', 'email_send'];
$registered = itm_job_queue_job_types();
foreach ($expectedTypes as $type) {
    if (!in_array($type, $registered, true)) {
        jq_fail('Missing job type: ' . $type);
    }
}
if ($failures === 0) {
    jq_pass('All handler job types registered');
}

$enqueue = itm_job_queue_enqueue($conn, 1, 'email_send', [
    'to' => 'verify-job-queue@example.com',
    'subject' => 'Job queue verify',
    'body' => '<p>Disposable verify row</p>',
], 7, 3);
if (empty($enqueue['ok']) || (int)($enqueue['id'] ?? 0) <= 0) {
    jq_fail('Enqueue failed: ' . (string)($enqueue['error'] ?? 'unknown'));
} else {
    jq_pass('Enqueue email_send job');
}
$jobId = (int)($enqueue['id'] ?? 0);

$claimed = itm_job_queue_claim_pending($conn, 5, 1, 'email_send');
$found = false;
foreach ($claimed as $claimedRow) {
    if ((int)($claimedRow['id'] ?? 0) === $jobId) {
        $found = true;
        break;
    }
}
if (!$found) {
    jq_fail('Claim did not return enqueued job');
} else {
    jq_pass('Claim pending job');
}

itm_job_queue_mark_failed($conn, $jobId, 'Verify intentional failure', true);
mysqli_query($conn, 'UPDATE job_queue SET scheduled_at = NOW() WHERE id = ' . $jobId);
$afterFail = itm_job_queue_fetch_by_id($conn, $jobId);
if (!$afterFail || ($afterFail['status'] ?? '') !== 'pending' || (int)($afterFail['attempts'] ?? 0) !== 1) {
    jq_fail('Fail with retry should requeue as pending with attempts=1');
} else {
    jq_pass('Fail with backoff requeues job');
}

for ($i = 0; $i < 3; $i++) {
    $row = itm_job_queue_fetch_by_id($conn, $jobId);
    if ($row && ($row['status'] ?? '') === 'pending') {
        mysqli_query($conn, 'UPDATE job_queue SET scheduled_at = NOW() WHERE id = ' . $jobId);
        itm_job_queue_claim_pending($conn, 1, 1, 'email_send');
        itm_job_queue_mark_failed($conn, $jobId, 'Verify fail ' . $i, true);
        mysqli_query($conn, 'UPDATE job_queue SET scheduled_at = NOW() WHERE id = ' . $jobId);
    }
}
$dead = itm_job_queue_fetch_by_id($conn, $jobId);
if (!$dead || ($dead['status'] ?? '') !== 'failed') {
    jq_fail('Job should be failed after max_attempts');
} else {
    jq_pass('Fail exhausts max_attempts');
}

$retry = itm_job_queue_retry_failed($conn, $jobId);
if (empty($retry['ok'])) {
    jq_fail('Manual retry failed: ' . (string)($retry['error'] ?? ''));
} else {
    jq_pass('Manual retry resets failed job to pending');
}

itm_job_queue_mark_done($conn, $jobId);
$done = itm_job_queue_fetch_by_id($conn, $jobId);
if (!$done || ($done['status'] ?? '') !== 'done') {
    jq_fail('mark_done should set status done');
} else {
    jq_pass('mark_done sets status done');
}

mysqli_query($conn, 'DELETE FROM job_queue WHERE id = ' . $jobId);

$lockA = itm_job_queue_acquire_worker_lock($conn, 0);
if (!$lockA) {
    jq_fail('Worker GET_LOCK acquire failed');
} else {
    itm_job_queue_release_worker_lock($conn);
    $lockAfterRelease = itm_job_queue_acquire_worker_lock($conn, 0);
    if (!$lockAfterRelease) {
        jq_fail('Worker GET_LOCK should re-acquire after release');
    } else {
        jq_pass('Worker GET_LOCK acquire and release');
    }
    itm_job_queue_release_worker_lock($conn);
}

itm_script_output_end($failures > 0 ? 1 : 0);
