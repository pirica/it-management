<?php
/**
 * Generic tenant-scoped background job queue (PLAT-3 minimal).
 */

declare(strict_types=1);

if (!function_exists('itm_background_job_type_network_discovery_scan')) {
    function itm_background_job_type_network_discovery_scan(): string
    {
        return 'network_discovery_scan';
    }
}

if (!function_exists('itm_background_jobs_table_exists')) {
    function itm_background_jobs_table_exists(mysqli $conn): bool
    {
        $res = mysqli_query($conn, "SHOW TABLES LIKE 'background_jobs'");
        return $res && mysqli_num_rows($res) > 0;
    }
}

if (!function_exists('itm_background_jobs_enqueue')) {
    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error?: string, id?: int}
     */
    function itm_background_jobs_enqueue(
        mysqli $conn,
        int $companyId,
        string $jobType,
        array $payload,
        int $employeeId = 0,
        int $progressTotal = 0
    ): array {
        if ($companyId <= 0 || trim($jobType) === '') {
            return ['ok' => false, 'error' => 'Invalid job enqueue parameters.'];
        }
        if (!itm_background_jobs_table_exists($conn)) {
            return ['ok' => false, 'error' => 'background_jobs table is not installed.'];
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            return ['ok' => false, 'error' => 'Could not encode job payload.'];
        }

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO background_jobs
             (company_id, job_type, payload_json, status, progress_offset, progress_total,
              attempt_count, max_attempts, scheduled_at, next_run_at, created_by, active, created_at)
             VALUES (?, ?, ?, \'pending\', 0, ?, 0, 5, NOW(), NOW(), ?, 1, NOW())'
        );
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Could not prepare job insert.'];
        }
        mysqli_stmt_bind_param($stmt, 'issii', $companyId, $jobType, $payloadJson, $progressTotal, $employeeId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return ['ok' => false, 'error' => 'Job insert failed.'];
        }
        $id = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        return ['ok' => true, 'id' => $id];
    }
}

if (!function_exists('itm_background_jobs_fetch_by_id')) {
    function itm_background_jobs_fetch_by_id(mysqli $conn, int $jobId, int $companyId = 0): ?array
    {
        if ($jobId <= 0) {
            return null;
        }
        $sql = 'SELECT * FROM background_jobs WHERE id = ? AND deleted_at IS NULL';
        if ($companyId > 0) {
            $sql .= ' AND company_id = ' . (int)$companyId;
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

if (!function_exists('itm_background_jobs_claim_pending')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_background_jobs_claim_pending(mysqli $conn, int $limit = 20, int $companyFilter = 0, string $jobType = ''): array
    {
        if (!itm_background_jobs_table_exists($conn)) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $sql = 'SELECT * FROM background_jobs
                WHERE deleted_at IS NULL AND active = 1 AND status = \'pending\'
                  AND (next_run_at IS NULL OR next_run_at <= NOW())';
        if ($companyFilter > 0) {
            $sql .= ' AND company_id = ' . (int)$companyFilter;
        }
        if ($jobType !== '') {
            $sql .= ' AND job_type = \'' . mysqli_real_escape_string($conn, $jobType) . '\'';
        }
        $sql .= ' ORDER BY scheduled_at ASC, id ASC LIMIT ' . (int)$limit;

        $rows = [];
        $res = mysqli_query($conn, $sql);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $jobId = (int)($row['id'] ?? 0);
            $upd = mysqli_prepare(
                $conn,
                'UPDATE background_jobs SET status = \'running\', started_at = IFNULL(started_at, NOW()), updated_at = NOW()
                 WHERE id = ? AND status = \'pending\' AND deleted_at IS NULL'
            );
            if ($upd) {
                mysqli_stmt_bind_param($upd, 'i', $jobId);
                mysqli_stmt_execute($upd);
                $affected = mysqli_stmt_affected_rows($upd);
                mysqli_stmt_close($upd);
                if ($affected > 0) {
                    $row['status'] = 'running';
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }
}

if (!function_exists('itm_background_jobs_mark_progress')) {
    function itm_background_jobs_mark_progress(mysqli $conn, int $jobId, int $offset): bool
    {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE background_jobs SET progress_offset = ?, status = \'pending\', updated_at = NOW() WHERE id = ? AND deleted_at IS NULL'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $offset, $jobId);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_background_jobs_mark_completed')) {
    function itm_background_jobs_mark_completed(mysqli $conn, int $jobId): bool
    {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE background_jobs SET status = \'completed\', completed_at = NOW(), updated_at = NOW() WHERE id = ? AND deleted_at IS NULL'
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

if (!function_exists('itm_background_jobs_mark_failed')) {
    function itm_background_jobs_mark_failed(mysqli $conn, int $jobId, string $error, bool $retry = true): bool
    {
        $error = trim($error);
        if (strlen($error) > 500) {
            $error = substr($error, 0, 497) . '…';
        }
        $job = itm_background_jobs_fetch_by_id($conn, $jobId);
        if (!$job) {
            return false;
        }
        $attempts = (int)($job['attempt_count'] ?? 0) + 1;
        $maxAttempts = (int)($job['max_attempts'] ?? 5);
        if ($retry && $attempts < $maxAttempts) {
            $status = 'pending';
            $backoff = min(3600, 60 * $attempts);
            $nextRun = date('Y-m-d H:i:s', time() + $backoff);
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE background_jobs
                 SET status = ?, attempt_count = ?, last_error = ?, next_run_at = ?, updated_at = NOW()
                 WHERE id = ? AND deleted_at IS NULL'
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'sissi', $status, $attempts, $error, $nextRun, $jobId);
        } else {
            $status = 'failed';
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE background_jobs
                 SET status = ?, attempt_count = ?, last_error = ?, next_run_at = NULL, updated_at = NOW()
                 WHERE id = ? AND deleted_at IS NULL'
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

if (!function_exists('itm_background_jobs_process')) {
    /**
     * @return array{processed: int, completed: int, failed: int, errors: array<int, string>}
     */
    function itm_background_jobs_process(mysqli $conn, int $limit = 20, int $companyFilter = 0, string $jobType = ''): array
    {
        $processed = 0;
        $completed = 0;
        $failed = 0;
        $errors = [];

        if (!function_exists('itm_network_discovery_process_background_job')) {
            require_once ROOT_PATH . 'includes/itm_network_discovery.php';
        }

        $jobs = itm_background_jobs_claim_pending($conn, $limit, $companyFilter, $jobType);
        foreach ($jobs as $job) {
            $processed++;
            $type = (string)($job['job_type'] ?? '');
            $jobId = (int)($job['id'] ?? 0);
            if ($type === itm_background_job_type_network_discovery_scan()) {
                $result = itm_network_discovery_process_background_job($conn, $job);
            } else {
                $result = ['ok' => false, 'error' => 'Unknown job type: ' . $type];
            }
            if (!empty($result['ok']) && !empty($result['complete'])) {
                $completed++;
            } elseif (empty($result['ok'])) {
                $failed++;
                $errors[] = 'Job #' . $jobId . ': ' . (string)($result['error'] ?? 'failed');
            }
        }

        return ['processed' => $processed, 'completed' => $completed, 'failed' => $failed, 'errors' => $errors];
    }
}

if (!function_exists('itm_background_jobs_profile_has_active_scan')) {
    function itm_background_jobs_profile_has_active_scan(mysqli $conn, int $companyId, int $profileId): bool
    {
        if ($companyId <= 0 || $profileId <= 0 || !itm_background_jobs_table_exists($conn)) {
            return false;
        }
        $type = itm_background_job_type_network_discovery_scan();
        $sql = 'SELECT id FROM background_jobs
                WHERE company_id = ? AND job_type = ? AND deleted_at IS NULL
                  AND status IN (\'pending\', \'running\')
                  AND CAST(JSON_UNQUOTE(JSON_EXTRACT(payload_json, \'$.profile_id\')) AS UNSIGNED) = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'isi', $companyId, $type, $profileId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row !== null;
    }
}
