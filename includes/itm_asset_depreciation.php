<?php
/**
 * Equipment asset lifecycle stages and straight-line depreciation helpers.
 */

if (!function_exists('itm_asset_lifecycle_stages')) {
    function itm_asset_lifecycle_stages()
    {
        return [
            'procurement' => 'Procurement',
            'in_service' => 'In service',
            'maintenance' => 'Maintenance',
            'retired' => 'Retired',
            'disposed' => 'Disposed',
        ];
    }
}

if (!function_exists('itm_asset_depreciation_months_elapsed')) {
    function itm_asset_depreciation_months_elapsed($startDate, $asOf = null)
    {
        $startDate = trim((string) $startDate);
        if ($startDate === '') {
            return 0;
        }
        try {
            $start = new DateTimeImmutable($startDate);
            if ($asOf instanceof DateTimeImmutable) {
                $end = $asOf;
            } elseif ($asOf instanceof DateTimeInterface) {
                $end = new DateTimeImmutable($asOf->format('Y-m-d H:i:s'));
            } else {
                $end = new DateTimeImmutable('today');
            }
        } catch (Exception $e) {
            return 0;
        }
        if ($end < $start) {
            return 0;
        }
        $yearDiff = (int) $end->format('Y') - (int) $start->format('Y');
        $monthDiff = (int) $end->format('n') - (int) $start->format('n');
        return max(0, ($yearDiff * 12) + $monthDiff);
    }
}

if (!function_exists('itm_asset_depreciation_compute_book_value')) {
    function itm_asset_depreciation_compute_book_value(array $equipmentRow, $asOf = null)
    {
        $purchaseCost = (float) ($equipmentRow['purchase_cost'] ?? 0);
        $salvage = (float) ($equipmentRow['salvage_value'] ?? 0);
        $lifeMonths = (int) ($equipmentRow['useful_life_months'] ?? 0);
        $startDate = (string) ($equipmentRow['depreciation_start_date'] ?? '');

        if ($purchaseCost <= 0 || $lifeMonths <= 0 || $startDate === '') {
            return [
                'book_value' => $purchaseCost,
                'monthly_depreciation' => 0.0,
                'months_elapsed' => 0,
                'fully_depreciated' => false,
            ];
        }

        $depreciable = max(0, $purchaseCost - $salvage);
        $monthly = $depreciable / $lifeMonths;
        $monthsElapsed = itm_asset_depreciation_months_elapsed($startDate, $asOf);
        $accumulated = min($depreciable, $monthly * $monthsElapsed);
        $bookValue = max($salvage, $purchaseCost - $accumulated);

        return [
            'book_value' => round($bookValue, 2),
            'monthly_depreciation' => round($monthly, 2),
            'months_elapsed' => $monthsElapsed,
            'fully_depreciated' => $monthsElapsed >= $lifeMonths,
        ];
    }
}

if (!function_exists('itm_asset_lifecycle_log_event')) {
    function itm_asset_lifecycle_log_event($conn, $companyId, $equipmentId, $eventType, $notes = '', array $payload = [], $employeeId = null)
    {
        $companyId = (int) $companyId;
        $equipmentId = (int) $equipmentId;
        $eventType = trim((string) $eventType);
        if ($companyId <= 0 || $equipmentId <= 0 || $eventType === '') {
            return false;
        }
        $payloadJson = $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $employeeId = $employeeId !== null ? (int) $employeeId : (int) ($_SESSION['employee_id'] ?? 0);
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO equipment_lifecycle_events
             (company_id, equipment_id, event_type, event_date, notes, payload_json, created_by, active, created_at)
             VALUES (?, ?, ?, CURDATE(), NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, 0), 1, NOW())'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iisssi', $companyId, $equipmentId, $eventType, $notes, $payloadJson, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return (bool) $ok;
    }
}

if (!function_exists('itm_asset_lifecycle_fetch_timeline')) {
    function itm_asset_lifecycle_fetch_timeline($conn, $companyId, $equipmentId, $limit = 50)
    {
        $companyId = (int) $companyId;
        $equipmentId = (int) $equipmentId;
        $limit = max(1, min(200, (int) $limit));
        $stmt = mysqli_prepare(
            $conn,
            'SELECT e.*, emp.first_name, emp.last_name, emp.username
             FROM equipment_lifecycle_events e
             LEFT JOIN employees emp ON emp.id = e.created_by AND emp.company_id = e.company_id
             WHERE e.company_id = ? AND e.equipment_id = ? AND e.deleted_at IS NULL
             ORDER BY e.event_date DESC, e.id DESC
             LIMIT ?'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $equipmentId, $limit);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_asset_depreciation_run_monthly_snapshots')) {
    function itm_asset_depreciation_run_monthly_snapshots($conn, $companyId = null)
    {
        $sql = "SELECT id, company_id, name, purchase_cost, salvage_value, useful_life_months, depreciation_start_date, lifecycle_stage
                FROM equipment
                WHERE deleted_at IS NULL AND active = 1
                  AND depreciation_start_date IS NOT NULL AND useful_life_months > 0 AND purchase_cost > 0";
        $types = '';
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND company_id = ?';
            $types = 'i';
            $params[] = (int) $companyId;
        }
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['processed' => 0, 'logged' => 0];
        }
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $summary = ['processed' => 0, 'logged' => 0];
        $monthKey = date('Y-m');
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $summary['processed']++;
            $calc = itm_asset_depreciation_compute_book_value($row);
            $payload = [
                'month' => $monthKey,
                'book_value' => $calc['book_value'],
                'monthly_depreciation' => $calc['monthly_depreciation'],
                'months_elapsed' => $calc['months_elapsed'],
            ];
            $notes = 'Monthly depreciation snapshot';
            if (itm_asset_lifecycle_log_event(
                $conn,
                (int) $row['company_id'],
                (int) $row['id'],
                'depreciation_snapshot',
                $notes,
                $payload,
                null
            )) {
                $summary['logged']++;
            }
        }
        mysqli_stmt_close($stmt);
        return $summary;
    }
}
