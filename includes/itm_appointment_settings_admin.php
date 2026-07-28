<?php
/**
 * Admin helpers for modules/appointment_settings/ (tenant booking configuration).
 */

if (!function_exists('itm_appointment_settings_default_business_hours_rows')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_appointment_settings_default_business_hours_rows(): array
    {
        return [
            0 => ['display_label' => 'Sun', 'open_time' => null, 'close_time' => null, 'is_closed' => 1, 'allows_in_person' => 0, 'allows_remote' => 0],
            1 => ['display_label' => 'Mon', 'open_time' => '10:00:00', 'close_time' => '18:00:00', 'is_closed' => 0, 'allows_in_person' => 0, 'allows_remote' => 0],
            2 => ['display_label' => 'Tue', 'open_time' => '10:00:00', 'close_time' => '18:00:00', 'is_closed' => 0, 'allows_in_person' => 0, 'allows_remote' => 0],
            3 => ['display_label' => 'Wed', 'open_time' => '10:00:00', 'close_time' => '18:00:00', 'is_closed' => 0, 'allows_in_person' => 0, 'allows_remote' => 1],
            4 => ['display_label' => 'Thu', 'open_time' => '10:00:00', 'close_time' => '18:00:00', 'is_closed' => 0, 'allows_in_person' => 0, 'allows_remote' => 1],
            5 => ['display_label' => 'Fri', 'open_time' => '10:00:00', 'close_time' => '18:00:00', 'is_closed' => 0, 'allows_in_person' => 0, 'allows_remote' => 1],
            6 => ['display_label' => 'Sat', 'open_time' => null, 'close_time' => null, 'is_closed' => 1, 'allows_in_person' => 0, 'allows_remote' => 0],
        ];
    }
}

if (!function_exists('itm_appointment_settings_ensure_company_config')) {
    /**
     * Ensure settings, business hours, and appointment types exist for the active company.
     */
    function itm_appointment_settings_ensure_company_config(mysqli $conn, int $companyId, int $employeeId): void
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return;
        }

        $stmt = mysqli_prepare($conn, 'SELECT id FROM appointment_settings WHERE company_id = ? AND deleted_at IS NULL LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $settingsRow = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        if (!$settingsRow) {
            $sql = 'INSERT INTO appointment_settings (company_id, timezone, allow_in_person, allow_remote, in_person_only, slot_duration_minutes, bookable_start_time, bookable_end_time, check_in_end_buffer_minutes, active, created_by, updated_by)
                    VALUES (?, \'US/Central\', 0, 1, 0, 60, \'09:00:00\', \'14:00:00\', 30, 1, ?, ?)';
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iii', $companyId, $employeeId, $employeeId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }

        $countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM appointment_business_hours WHERE company_id = ? AND deleted_at IS NULL');
        mysqli_stmt_bind_param($countStmt, 'i', $companyId);
        mysqli_stmt_execute($countStmt);
        $countRes = mysqli_stmt_get_result($countStmt);
        $countRow = $countRes ? mysqli_fetch_assoc($countRes) : null;
        mysqli_stmt_close($countStmt);
        $hourCount = (int)($countRow['c'] ?? 0);

        if ($hourCount < 7) {
            foreach (itm_appointment_settings_default_business_hours_rows() as $dow => $def) {
                $existsStmt = mysqli_prepare(
                    $conn,
                    'SELECT id FROM appointment_business_hours WHERE company_id = ? AND day_of_week = ? AND deleted_at IS NULL LIMIT 1'
                );
                if (!$existsStmt) {
                    continue;
                }
                mysqli_stmt_bind_param($existsStmt, 'ii', $companyId, $dow);
                mysqli_stmt_execute($existsStmt);
                $existsRes = mysqli_stmt_get_result($existsStmt);
                $exists = $existsRes ? mysqli_fetch_assoc($existsRes) : null;
                mysqli_stmt_close($existsStmt);
                if ($exists) {
                    continue;
                }
                $insert = mysqli_prepare(
                    $conn,
                    'INSERT INTO appointment_business_hours (company_id, day_of_week, display_label, open_time, close_time, is_closed, allows_in_person, allows_remote, active, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
                );
                if (!$insert) {
                    continue;
                }
                $open = $def['open_time'];
                $close = $def['close_time'];
                $isClosed = (int)$def['is_closed'];
                $allowsInPerson = (int)$def['allows_in_person'];
                $allowsRemote = (int)$def['allows_remote'];
                $label = $def['display_label'];
                mysqli_stmt_bind_param(
                    $insert,
                    'iisssiiiii',
                    $companyId,
                    $dow,
                    $label,
                    $open,
                    $close,
                    $isClosed,
                    $allowsInPerson,
                    $allowsRemote,
                    $employeeId,
                    $employeeId
                );
                mysqli_stmt_execute($insert);
                mysqli_stmt_close($insert);
            }
        }

        foreach (['in_person' => 0, 'remote' => 1] as $typeName => $typeActiveDefault) {
            $existsStmt = mysqli_prepare(
                $conn,
                'SELECT id FROM appointment_type WHERE company_id = ? AND name = ? AND deleted_at IS NULL LIMIT 1'
            );
            if (!$existsStmt) {
                continue;
            }
            mysqli_stmt_bind_param($existsStmt, 'is', $companyId, $typeName);
            mysqli_stmt_execute($existsStmt);
            $existsRes = mysqli_stmt_get_result($existsStmt);
            $exists = $existsRes ? mysqli_fetch_assoc($existsRes) : null;
            mysqli_stmt_close($existsStmt);
            if ($exists) {
                continue;
            }
            $typeStmt = mysqli_prepare(
                $conn,
                'INSERT INTO appointment_type (company_id, name, active, created_by, updated_by) VALUES (?, ?, ?, ?, ?)'
            );
            if (!$typeStmt) {
                continue;
            }
            mysqli_stmt_bind_param($typeStmt, 'isiii', $companyId, $typeName, $typeActiveDefault, $employeeId, $employeeId);
            mysqli_stmt_execute($typeStmt);
            mysqli_stmt_close($typeStmt);
        }
    }
}

if (!function_exists('itm_appointment_settings_load_visit_reasons_admin')) {
    /**
     * All non-deleted visit reasons for the settings UI (includes inactive).
     *
     * @return array<int, array<string, mixed>>
     */
    function itm_appointment_settings_load_visit_reasons_admin(mysqli $conn, int $companyId): array
    {
        $rows = [];
        $sql = 'SELECT id, name, sort_order, active FROM appointment_visit_reasons WHERE company_id = ? AND deleted_at IS NULL ORDER BY sort_order ASC, name ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_appointment_settings_load_appointment_types_admin')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_appointment_settings_load_appointment_types_admin(mysqli $conn, int $companyId): array
    {
        $rows = [];
        $sql = 'SELECT id, name, active FROM appointment_type WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}
