<?php
/**
 * Generic background job queue — enqueue, claim, dispatch, retry with backoff.
 *
 * Worker: scripts/run_job_queue.php (schedule every minute).
 * Admin UI: modules/job_queue/ (read-only list + manual retry).
 */

declare(strict_types=1);

if (!function_exists('itm_job_queue_worker_lock_name')) {
    function itm_job_queue_worker_lock_name(): string
    {
        return 'itm_job_queue_worker';
    }
}

if (!function_exists('itm_job_queue_job_types')) {
    /** @return string[] */
    function itm_job_queue_job_types(): array
    {
        return [
            'webhook_delivery',
            'scheduled_report',
            'network_discovery',
            'license_compliance',
            'email_send',
        ];
    }
}

if (!function_exists('itm_job_queue_table_exists')) {
    function itm_job_queue_table_exists(mysqli $conn): bool
    {
        $res = mysqli_query($conn, "SHOW TABLES LIKE 'job_queue'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('itm_job_queue_normalize_error')) {
    function itm_job_queue_normalize_error(string $error): string
    {
        $error = trim($error);
        if (strlen($error) > 500) {
            return substr($error, 0, 497) . '…';
        }
        return $error;
    }
}

if (!function_exists('itm_job_queue_backoff_seconds')) {
    function itm_job_queue_backoff_seconds(int $attempts): int
    {
        $attempts = max(1, $attempts);
        return min(3600, 60 * $attempts);
    }
}

if (!function_exists('itm_job_queue_acquire_worker_lock')) {
    function itm_job_queue_acquire_worker_lock(mysqli $conn, int $timeoutSeconds = 0): bool
    {
        $name = itm_job_queue_worker_lock_name();
        $stmt = mysqli_prepare($conn, 'SELECT GET_LOCK(?, ?) AS acquired');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'si', $name, $timeoutSeconds);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row && (int)($row['acquired'] ?? 0) === 1;
    }
}

if (!function_exists('itm_job_queue_release_worker_lock')) {
    function itm_job_queue_release_worker_lock(mysqli $conn): void
    {
        $name = itm_job_queue_worker_lock_name();
        $stmt = mysqli_prepare($conn, 'SELECT RELEASE_LOCK(?)');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $name);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

if (!function_exists('itm_job_queue_enqueue')) {
    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string, id?: int}
     */
    function itm_job_queue_enqueue(
        mysqli $conn,
        ?int $companyId,
        string $jobType,
        array $payload,
        int $priority = 5,
        int $maxAttempts = 5,
        ?string $scheduledAt = null,
        int $createdBy = 0
    ): array {
        $jobType = trim($jobType);
        if ($jobType === '' || !in_array($jobType, itm_job_queue_job_types(), true)) {
            return ['ok' => false, 'error' => 'Unsupported job type.'];
        }
        if (!itm_job_queue_table_exists($conn)) {
            return ['ok' => false, 'error' => 'job_queue table is not installed.'];
        }
        if ($companyId !== null && $companyId <= 0) {
            return ['ok' => false, 'error' => 'Invalid company_id.'];
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            return ['ok' => false, 'error' => 'Could not encode job payload.'];
        }

        $priority = max(1, min(10, $priority));
        $maxAttempts = max(1, min(20, $maxAttempts));
        $scheduledAtSql = $scheduledAt !== null && trim($scheduledAt) !== ''
            ? trim($scheduledAt)
            : date('Y-m-d H:i:s');

        if ($companyId === null) {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO job_queue
                 (company_id, job_type, payload_json, status, priority, attempts, max_attempts,
                  scheduled_at, created_by, active, created_at)
                 VALUES (NULL, ?, ?, \'pending\', ?, 0, ?, ?, ?, 1, NOW())'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not prepare job insert.'];
            }
            mysqli_stmt_bind_param($stmt, 'ssiisi', $jobType, $payloadJson, $priority, $maxAttempts, $scheduledAtSql, $createdBy);
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO job_queue
                 (company_id, job_type, payload_json, status, priority, attempts, max_attempts,
                  scheduled_at, created_by, active, created_at)
                 VALUES (?, ?, ?, \'pending\', ?, 0, ?, ?, ?, 1, NOW())'
            );
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not prepare job insert.'];
            }
            mysqli_stmt_bind_param($stmt, 'issiisi', $companyId, $jobType, $payloadJson, $priority, $maxAttempts, $scheduledAtSql, $createdBy);
        }

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return ['ok' => false, 'error' => 'Job insert failed.'];
        }
        $id = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return ['ok' => true, 'id' => $id];
    }
}

if (!function_exists('itm_job_queue_fetch_by_id')) {
    function itm_job_queue_fetch_by_id(mysqli $conn, int $jobId, int $companyId = 0): ?array
    {
        if ($jobId <= 0 || !itm_job_queue_table_exists($conn)) {
            return null;
        }
        $sql = 'SELECT jq.*, c.company AS company_name
                FROM job_queue jq
                LEFT JOIN companies c ON c.id = jq.company_id
                WHERE jq.id = ? AND jq.deleted_at IS NULL';
        if ($companyId > 0) {
            $sql .= ' AND jq.company_id = ' . (int)$companyId;
        }
        $sql .= ' LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $jobId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_job_queue_recover_stale_running')) {
    function itm_job_queue_recover_stale_running(mysqli $conn, int $staleMinutes = 30): int
    {
        if (!itm_job_queue_table_exists($conn)) {
            return 0;
        }
        $minutes = max(5, $staleMinutes);
        $sql = "UPDATE job_queue
                SET status = 'pending', started_at = NULL, updated_at = NOW(),
                    last_error = CONCAT(IFNULL(last_error, ''), ' [recovered stale running]')
                WHERE deleted_at IS NULL AND status = 'running'
                  AND started_at IS NOT NULL
                  AND started_at < DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE)";
        mysqli_query($conn, $sql);
        return mysqli_affected_rows($conn);
    }
}

if (!function_exists('itm_job_queue_claim_pending')) {
    /**
     * Claim due jobs using FOR UPDATE SKIP LOCKED when supported, else optimistic UPDATE.
     *
     * @return array<int, array<string, mixed>>
     */
    function itm_job_queue_claim_pending(mysqli $conn, int $limit = 20, int $companyFilter = 0, string $jobType = ''): array
    {
        if (!itm_job_queue_table_exists($conn)) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $claimed = [];

        mysqli_begin_transaction($conn);
        $sql = "SELECT id FROM job_queue
                WHERE deleted_at IS NULL AND active = 1 AND status = 'pending'
                  AND scheduled_at <= NOW()";
        if ($companyFilter > 0) {
            $sql .= ' AND company_id = ' . (int)$companyFilter;
        }
        if ($jobType !== '') {
            $sql .= " AND job_type = '" . mysqli_real_escape_string($conn, $jobType) . "'";
        }
        $sql .= ' ORDER BY priority DESC, scheduled_at ASC, id ASC LIMIT ' . (int)$limit . ' FOR UPDATE SKIP LOCKED';

        $res = mysqli_query($conn, $sql);
        if (!$res) {
            mysqli_rollback($conn);
            return itm_job_queue_claim_pending_fallback($conn, $limit, $companyFilter, $jobType);
        }

        $ids = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $ids[] = (int)($row['id'] ?? 0);
        }
        mysqli_free_result($res);

        foreach ($ids as $jobId) {
            if ($jobId <= 0) {
                continue;
            }
            $upd = mysqli_prepare(
                $conn,
                "UPDATE job_queue SET status = 'running', started_at = IFNULL(started_at, NOW()), updated_at = NOW()
                 WHERE id = ? AND status = 'pending' AND deleted_at IS NULL"
            );
            if (!$upd) {
                continue;
            }
            mysqli_stmt_bind_param($upd, 'i', $jobId);
            mysqli_stmt_execute($upd);
            $affected = mysqli_stmt_affected_rows($upd);
            mysqli_stmt_close($upd);
            if ($affected > 0) {
                $row = itm_job_queue_fetch_by_id($conn, $jobId);
                if ($row) {
                    $claimed[] = $row;
                }
            }
        }

        mysqli_commit($conn);
        return $claimed;
    }
}

if (!function_exists('itm_job_queue_claim_pending_fallback')) {
  /**
   * @return array<int, array<string, mixed>>
   */
    function itm_job_queue_claim_pending_fallback(mysqli $conn, int $limit, int $companyFilter, string $jobType): array
    {
        $sql = "SELECT id FROM job_queue
                WHERE deleted_at IS NULL AND active = 1 AND status = 'pending'
                  AND scheduled_at <= NOW()";
        if ($companyFilter > 0) {
            $sql .= ' AND company_id = ' . (int)$companyFilter;
        }
        if ($jobType !== '') {
            $sql .= " AND job_type = '" . mysqli_real_escape_string($conn, $jobType) . "'";
        }
        $sql .= ' ORDER BY priority DESC, scheduled_at ASC, id ASC LIMIT ' . (int)$limit;

        $claimed = [];
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $jobId = (int)($row['id'] ?? 0);
            $upd = mysqli_prepare(
                $conn,
                "UPDATE job_queue SET status = 'running', started_at = IFNULL(started_at, NOW()), updated_at = NOW()
                 WHERE id = ? AND status = 'pending' AND deleted_at IS NULL"
            );
            if ($upd) {
                mysqli_stmt_bind_param($upd, 'i', $jobId);
                mysqli_stmt_execute($upd);
                $affected = mysqli_stmt_affected_rows($upd);
                mysqli_stmt_close($upd);
                if ($affected > 0) {
                    $full = itm_job_queue_fetch_by_id($conn, $jobId);
                    if ($full) {
                        $claimed[] = $full;
                    }
                }
            }
        }
        return $claimed;
    }
}

if (!function_exists('itm_job_queue_mark_done')) {
    function itm_job_queue_mark_done(mysqli $conn, int $jobId): bool
    {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE job_queue SET status = 'done', finished_at = NOW(), last_error = NULL, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL"
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $jobId);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_job_queue_mark_failed')) {
    function itm_job_queue_mark_failed(mysqli $conn, int $jobId, string $error, bool $retry = true): bool
    {
        $error = itm_job_queue_normalize_error($error);
        $job = itm_job_queue_fetch_by_id($conn, $jobId);
        if (!$job) {
            return false;
        }
        $attempts = (int)($job['attempts'] ?? 0) + 1;
        $maxAttempts = max(1, (int)($job['max_attempts'] ?? 5));

        if ($retry && $attempts < $maxAttempts) {
            $backoff = itm_job_queue_backoff_seconds($attempts);
            $nextRun = date('Y-m-d H:i:s', time() + $backoff);
            $status = 'pending';
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE job_queue
                 SET status = ?, attempts = ?, last_error = ?, scheduled_at = ?, started_at = NULL, updated_at = NOW()
                 WHERE id = ? AND deleted_at IS NULL"
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'sissi', $status, $attempts, $error, $nextRun, $jobId);
        } else {
            $status = 'failed';
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE job_queue
                 SET status = ?, attempts = ?, last_error = ?, finished_at = NOW(), updated_at = NOW()
                 WHERE id = ? AND deleted_at IS NULL"
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'sisi', $status, $attempts, $error, $jobId);
        }
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_job_queue_retry_failed')) {
    function itm_job_queue_retry_failed(mysqli $conn, int $jobId, int $employeeId = 0): array
    {
        $job = itm_job_queue_fetch_by_id($conn, $jobId);
        if (!$job) {
            return ['ok' => false, 'error' => 'Job not found.'];
        }
        $status = (string)($job['status'] ?? '');
        if (!in_array($status, ['failed', 'done'], true)) {
            return ['ok' => false, 'error' => 'Only failed or done jobs can be retried manually.'];
        }
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE job_queue
             SET status = 'pending', attempts = 0, last_error = NULL,
                 scheduled_at = NOW(), started_at = NULL, finished_at = NULL,
                 updated_by = ?, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL"
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not prepare retry update.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $jobId);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'Retry update failed.'];
    }
}

if (!function_exists('itm_job_queue_decode_payload')) {
    /** @return array<string, mixed> */
    function itm_job_queue_decode_payload(array $job): array
    {
        $decoded = json_decode((string)($job['payload_json'] ?? '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('itm_job_queue_handle_webhook_delivery')) {
    /** @return array{ok: bool, error?: string} */
    function itm_job_queue_handle_webhook_delivery(mysqli $conn, array $job): array
    {
        if (!function_exists('itm_webhook_queue_deliver_row')) {
            require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
        }
        $payload = itm_job_queue_decode_payload($job);
        $deliveryId = (int)($payload['delivery_id'] ?? 0);
        if ($deliveryId <= 0) {
            return ['ok' => false, 'error' => 'Missing delivery_id in payload.'];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT d.*, w.target_url, w.secret_encrypted
             FROM integration_webhook_deliveries d
             INNER JOIN integration_webhooks w ON w.id = d.webhook_id AND w.company_id = d.company_id
             WHERE d.id = ? AND d.deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Delivery lookup failed.'];
        }
        mysqli_stmt_bind_param($stmt, 'i', $deliveryId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'error' => 'Webhook delivery row not found.'];
        }
        $webhookRow = [
            'target_url' => $row['target_url'],
            'secret_encrypted' => $row['secret_encrypted'],
        ];
        $result = itm_webhook_queue_deliver_row($conn, $row, $webhookRow);
        if (!empty($result['success'])) {
            return ['ok' => true];
        }
        $status = (string)($result['status'] ?? 'failed');
        if ($status === 'dead') {
            return ['ok' => false, 'error' => 'Webhook delivery exhausted retries.', 'retry' => false];
        }
        return ['ok' => false, 'error' => 'Webhook delivery failed (HTTP ' . (int)($result['http_code'] ?? 0) . ').'];
    }
}

if (!function_exists('itm_job_queue_handle_scheduled_report')) {
    /** @return array{ok: bool, error?: string} */
    function itm_job_queue_handle_scheduled_report(mysqli $conn, array $job): array
    {
        if (!function_exists('itm_scheduled_reports_send_row')) {
            require_once ROOT_PATH . 'includes/itm_scheduled_reports.php';
        }
        $payload = itm_job_queue_decode_payload($job);
        $reportId = (int)($payload['scheduled_report_id'] ?? 0);
        $companyId = (int)($job['company_id'] ?? $payload['company_id'] ?? 0);
        if ($reportId <= 0 || $companyId <= 0) {
            return ['ok' => false, 'error' => 'Missing scheduled_report_id or company_id.'];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM scheduled_reports WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Report lookup failed.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $reportId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'error' => 'Scheduled report not found.', 'retry' => false];
        }
        $result = itm_scheduled_reports_send_row($conn, $row, true);
        if (!empty($result['ok'])) {
            return ['ok' => true];
        }
        return ['ok' => false, 'error' => (string)($result['error'] ?? 'Send failed.')];
    }
}

if (!function_exists('itm_job_queue_handle_network_discovery')) {
    /** @return array{ok: bool, error?: string} */
    function itm_job_queue_handle_network_discovery(mysqli $conn, array $job): array
    {
        if (!function_exists('itm_network_discovery_enqueue_profile_scan')) {
            require_once ROOT_PATH . 'includes/itm_network_discovery.php';
        }
        if (!function_exists('itm_background_jobs_enqueue')) {
            require_once ROOT_PATH . 'includes/itm_background_jobs.php';
        }
        $payload = itm_job_queue_decode_payload($job);
        $profileId = (int)($payload['profile_id'] ?? 0);
        $employeeId = (int)($payload['employee_id'] ?? 0);
        $companyId = (int)($job['company_id'] ?? 0);
        if ($profileId <= 0 || $companyId <= 0) {
            return ['ok' => false, 'error' => 'Missing profile_id or company_id.', 'retry' => false];
        }

        if (empty($payload['background_job_seeded'])) {
            $enqueue = itm_network_discovery_enqueue_profile_scan($conn, $profileId, $employeeId);
            if (empty($enqueue['ok']) && empty($enqueue['skipped'])) {
                return ['ok' => false, 'error' => (string)($enqueue['error'] ?? 'Could not enqueue discovery scan.')];
            }
        }

        $iterations = 0;
        $maxIterations = 500;
        while ($iterations < $maxIterations) {
            $iterations++;
            $bgJob = itm_background_jobs_find_active_profile_scan($conn, $companyId, $profileId);
            if (!$bgJob) {
                return ['ok' => true];
            }
            $result = itm_network_discovery_process_background_job($conn, $bgJob);
            if (empty($result['ok'])) {
                return ['ok' => false, 'error' => (string)($result['error'] ?? 'Discovery batch failed.')];
            }
            if (!empty($result['complete'])) {
                return ['ok' => true];
            }
        }
        return ['ok' => false, 'error' => 'Discovery scan exceeded worker iteration limit; will retry.'];
    }
}

if (!function_exists('itm_job_queue_handle_license_compliance')) {
    /** @return array{ok: bool, error?: string} */
    function itm_job_queue_handle_license_compliance(mysqli $conn, array $job): array
    {
        $companyId = (int)($job['company_id'] ?? 0);
        if ($companyId <= 0) {
            return ['ok' => false, 'error' => 'Missing company_id.', 'retry' => false];
        }
        if (!function_exists('itm_software_license_tables_ready')) {
            require_once ROOT_PATH . 'includes/itm_software_license_link.php';
        }
        if (!itm_software_license_tables_ready($conn)) {
            return ['ok' => false, 'error' => 'License link tables not installed.', 'retry' => false];
        }

        $sql = 'SELECT lm.id, lm.name, lm.quantity,
                       (SELECT COUNT(DISTINCT sll.software_id)
                        FROM software_license_links sll
                        INNER JOIN software s ON s.id = sll.software_id AND s.company_id = lm.company_id
                        WHERE sll.company_id = lm.company_id AND sll.license_id = lm.id AND sll.deleted_at IS NULL) AS linked_count
                FROM license_management lm
                WHERE lm.company_id = ? AND lm.deleted_at IS NULL AND lm.active = 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Compliance query failed.'];
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $violations = 0;
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $qty = (int)($row['quantity'] ?? 0);
            $linked = (int)($row['linked_count'] ?? 0);
            if ($qty > 0 && $linked > $qty) {
                $violations++;
            }
        }
        mysqli_stmt_close($stmt);

        if ($violations > 0 && function_exists('itm_log_audit')) {
            itm_log_audit(
                $conn,
                'job_queue',
                (int)($job['id'] ?? 0),
                'license_compliance_scan',
                null,
                ['violations' => $violations, 'company_id' => $companyId]
            );
        }
        return ['ok' => true];
    }
}

if (!function_exists('itm_job_queue_handle_email_send')) {
    /** @return array{ok: bool, error?: string} */
    function itm_job_queue_handle_email_send(mysqli $conn, array $job): array
    {
        if (!function_exists('itm_send_email')) {
            require_once ROOT_PATH . 'includes/itm_email.php';
        }
        $payload = itm_job_queue_decode_payload($job);
        $to = trim((string)($payload['to'] ?? ''));
        $subject = trim((string)($payload['subject'] ?? ''));
        $body = (string)($payload['body'] ?? '');
        $companyId = (int)($job['company_id'] ?? $payload['company_id'] ?? 0);
        if ($to === '' || $subject === '' || $companyId <= 0) {
            return ['ok' => false, 'error' => 'Missing to, subject, or company_id.', 'retry' => false];
        }
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        $sent = itm_send_email($to, $subject, $body, $companyId, $options);
        if (!$sent) {
            return ['ok' => false, 'error' => 'Email send failed.'];
        }
        return ['ok' => true];
    }
}

if (!function_exists('itm_job_queue_dispatch_row')) {
    /** @return array{ok: bool, error?: string, retry?: bool} */
    function itm_job_queue_dispatch_row(mysqli $conn, array $job): array
    {
        $type = (string)($job['job_type'] ?? '');
        switch ($type) {
            case 'webhook_delivery':
                return itm_job_queue_handle_webhook_delivery($conn, $job);
            case 'scheduled_report':
                return itm_job_queue_handle_scheduled_report($conn, $job);
            case 'network_discovery':
                return itm_job_queue_handle_network_discovery($conn, $job);
            case 'license_compliance':
                return itm_job_queue_handle_license_compliance($conn, $job);
            case 'email_send':
                return itm_job_queue_handle_email_send($conn, $job);
            default:
                return ['ok' => false, 'error' => 'Unknown job type: ' . $type, 'retry' => false];
        }
    }
}

if (!function_exists('itm_job_queue_process')) {
    /**
     * @return array{processed: int, done: int, failed: int, requeued: int, recovered: int, errors: array<int, string>}
     */
    function itm_job_queue_process(mysqli $conn, int $limit = 20, int $companyFilter = 0, string $jobType = ''): array
    {
        $summary = [
            'processed' => 0,
            'done' => 0,
            'failed' => 0,
            'requeued' => 0,
            'recovered' => 0,
            'errors' => [],
        ];
        if (!itm_job_queue_table_exists($conn)) {
            $summary['errors'][] = 'job_queue table is not installed.';
            return $summary;
        }

        $summary['recovered'] = itm_job_queue_recover_stale_running($conn);
        $jobs = itm_job_queue_claim_pending($conn, $limit, $companyFilter, $jobType);
        foreach ($jobs as $job) {
            $summary['processed']++;
            $jobId = (int)($job['id'] ?? 0);
            $result = itm_job_queue_dispatch_row($conn, $job);
            if (!empty($result['ok'])) {
                itm_job_queue_mark_done($conn, $jobId);
                $summary['done']++;
                continue;
            }
            $retry = !array_key_exists('retry', $result) || !empty($result['retry']);
            $failedBefore = itm_job_queue_fetch_by_id($conn, $jobId);
            itm_job_queue_mark_failed($conn, $jobId, (string)($result['error'] ?? 'Job failed.'), $retry);
            $failedAfter = itm_job_queue_fetch_by_id($conn, $jobId);
            if ($failedAfter && ($failedAfter['status'] ?? '') === 'pending') {
                $summary['requeued']++;
            } else {
                $summary['failed']++;
            }
            $summary['errors'][] = 'Job #' . $jobId . ': ' . (string)($result['error'] ?? 'failed');
            unset($failedBefore);
        }
        return $summary;
    }
}

if (!function_exists('itm_job_queue_process_with_lock')) {
    /**
     * @return array{processed: int, done: int, failed: int, requeued: int, recovered: int, errors: array<int, string>, skipped_lock?: bool}
     */
    function itm_job_queue_process_with_lock(mysqli $conn, int $limit = 20, int $companyFilter = 0, string $jobType = ''): array
    {
        if (!itm_job_queue_acquire_worker_lock($conn, 0)) {
            return [
                'processed' => 0,
                'done' => 0,
                'failed' => 0,
                'requeued' => 0,
                'recovered' => 0,
                'errors' => [],
                'skipped_lock' => true,
            ];
        }
        try {
            return itm_job_queue_process($conn, $limit, $companyFilter, $jobType);
        } finally {
            itm_job_queue_release_worker_lock($conn);
        }
    }
}
