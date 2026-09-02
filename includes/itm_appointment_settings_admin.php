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
            1 => ['display_label' => 'Mon', 'open_time' => '10:00:00', 'close_time' => '18:00:00', 'is_closed' => 0, 'allows_in_person' => 1, 'allows_remote' => 1],
            2 => ['display_label' => 'Tue', 'open_time' => '10:00:00', 'close_time' => '18:00:00', 'is_closed' => 0, 'allows_in_person' => 1, 'allows_remote' => 1],
            3 => ['display_label' => 'Wed', 'open_time' => '10:00:00', 'close_time' => '18:00:00', 'is_closed' => 0, 'allows_in_person' => 0, 'allows_remote' => 1],
            4 => ['display_label' => 'Thu', 'open_time' => '10:00:00', 'close_time' => '18:00:00', 'is_closed' => 0, 'allows_in_person' => 1, 'allows_remote' => 1],
            5 => ['display_label' => 'Fri', 'open_time' => '10:00:00', 'close_time' => '18:00:00', 'is_closed' => 0, 'allows_in_person' => 1, 'allows_remote' => 1],
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
            $sql = 'INSERT INTO appointment_settings (company_id, timezone, slot_duration_minutes, bookable_start_time, bookable_end_time, check_in_end_buffer_minutes, default_appointment_modality, booking_enabled, active, created_by, updated_by)
                    VALUES (?, \'US/Central\', 60, \'09:00:00\', \'14:00:00\', 30, \'remote\', 1, 1, ?, ?)';
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
                    'INSERT INTO appointment_business_hours (company_id, day_of_week, display_label, open_time, close_time, is_closed, allows_in_person, allows_remote, allowed_types_json, active, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
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
                $allowedJson = itm_appointment_encode_allowed_types_json([
                    'in_person' => $allowsInPerson === 1,
                    'remote' => $allowsRemote === 1,
                ]);
                mysqli_stmt_bind_param(
                    $insert,
                    'iisssiiisii',
                    $companyId,
                    $dow,
                    $label,
                    $open,
                    $close,
                    $isClosed,
                    $allowsInPerson,
                    $allowsRemote,
                    $allowedJson,
                    $employeeId,
                    $employeeId
                );
                mysqli_stmt_execute($insert);
                mysqli_stmt_close($insert);
            }
        }

        foreach (['in_person' => 'In-person', 'remote' => 'Remote'] as $typeName => $typeLabel) {
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
            $typeActiveDefault = 1;
            $typeStmt = mysqli_prepare(
                $conn,
                'INSERT INTO appointment_type (company_id, name, label, active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)'
            );
            if (!$typeStmt) {
                continue;
            }
            mysqli_stmt_bind_param($typeStmt, 'issiii', $companyId, $typeName, $typeLabel, $typeActiveDefault, $employeeId, $employeeId);
            mysqli_stmt_execute($typeStmt);
            mysqli_stmt_close($typeStmt);
            itm_appointment_settings_append_type_to_business_hours($conn, $companyId, $typeName, $typeName === 'remote' ? 1 : 0);
        }

        $labelBackfill = mysqli_prepare(
            $conn,
            "UPDATE appointment_type SET label = CASE name WHEN 'in_person' THEN 'In-person' WHEN 'remote' THEN 'Remote' ELSE label END WHERE company_id = ? AND deleted_at IS NULL AND (label = '' OR label IS NULL)"
        );
        if ($labelBackfill) {
            mysqli_stmt_bind_param($labelBackfill, 'i', $companyId);
            mysqli_stmt_execute($labelBackfill);
            mysqli_stmt_close($labelBackfill);
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

if (!function_exists('itm_appointment_settings_visit_reason_name_exists')) {
    /**
     * Case-sensitive duplicate check for live visit-reason names (schema UNIQUE per company).
     */
    function itm_appointment_settings_visit_reason_name_exists(
        mysqli $conn,
        int $companyId,
        string $name,
        int $excludeId = 0
    ): bool {
        $name = trim($name);
        if ($companyId <= 0 || $name === '') {
            return false;
        }
        if ($excludeId > 0) {
            $sql = 'SELECT id FROM appointment_visit_reasons WHERE company_id = ? AND name = ? AND id <> ? AND deleted_at IS NULL LIMIT 1';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'isi', $companyId, $name, $excludeId);
        } else {
            $sql = 'SELECT id FROM appointment_visit_reasons WHERE company_id = ? AND name = ? AND deleted_at IS NULL LIMIT 1';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $name);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row !== null;
    }
}

if (!function_exists('itm_appointment_settings_save_business_hours_bulk')) {
    /**
     * Save all seven weekday rows from the weekly grid POST (creates missing rows).
     *
     * @param array<int|string, array<string, mixed>> $rowsByDow
     */
    function itm_appointment_settings_save_business_hours_bulk(
        mysqli $conn,
        int $companyId,
        int $employeeId,
        array $rowsByDow,
        array $appointmentTypes
    ): bool {
        if ($companyId <= 0) {
            return false;
        }
        $existing = itm_appointment_load_business_hours($conn, $companyId);
        $saved = 0;
        for ($dow = 0; $dow <= 6; $dow++) {
            $row = $rowsByDow[$dow] ?? $rowsByDow[(string)$dow] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string)($row['display_label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $open = trim((string)($row['open_time'] ?? ''));
            $close = trim((string)($row['close_time'] ?? ''));
            $isClosed = !empty($row['is_closed']) ? 1 : 0;
            $isActive = !empty($row['active']) ? 1 : 0;
            $allowedPost = is_array($row['allowed_type'] ?? null) ? $row['allowed_type'] : [];
            $allowedMap = itm_appointment_hour_allowed_types_map_from_post($appointmentTypes, ['allowed_type' => $allowedPost]);
            $legacy = itm_appointment_hour_legacy_modality_from_map($allowedMap);
            $allowsInPerson = (int)$legacy['allows_in_person'];
            $allowsRemote = (int)$legacy['allows_remote'];
            $allowedJson = itm_appointment_encode_allowed_types_json($allowedMap);
            if ($isClosed) {
                $open = null;
                $close = null;
            } else {
                if ($open !== '' && strlen($open) === 5) {
                    $open .= ':00';
                }
                if ($close !== '' && strlen($close) === 5) {
                    $close .= ':00';
                }
            }
            $hourId = (int)($existing[$dow]['id'] ?? 0);
            if ($hourId > 0) {
                $sql = 'UPDATE appointment_business_hours SET display_label = ?, open_time = ?, close_time = ?, is_closed = ?, allows_in_person = ?, allows_remote = ?, allowed_types_json = ?, active = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) {
                    return false;
                }
                mysqli_stmt_bind_param($stmt, 'sssiiisiiii', $label, $open, $close, $isClosed, $allowsInPerson, $allowsRemote, $allowedJson, $isActive, $employeeId, $hourId, $companyId);
            } else {
                $sql = 'INSERT INTO appointment_business_hours (company_id, day_of_week, display_label, open_time, close_time, is_closed, allows_in_person, allows_remote, allowed_types_json, active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) {
                    return false;
                }
                mysqli_stmt_bind_param($stmt, 'iisssiiisiii', $companyId, $dow, $label, $open, $close, $isClosed, $allowsInPerson, $allowsRemote, $allowedJson, $isActive, $employeeId, $employeeId);
            }
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }
            mysqli_stmt_close($stmt);
            $saved++;
        }
        return $saved > 0;
    }
}

if (!function_exists('itm_appointment_settings_reorder_visit_reasons')) {
    /**
     * @param array<int, int> $orderedIds
     */
    function itm_appointment_settings_reorder_visit_reasons(
        mysqli $conn,
        int $companyId,
        int $employeeId,
        array $orderedIds
    ): bool {
        if ($companyId <= 0 || $orderedIds === []) {
            return false;
        }
        $sort = 10;
        foreach ($orderedIds as $reasonId) {
            $reasonId = (int)$reasonId;
            if ($reasonId <= 0) {
                continue;
            }
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE appointment_visit_reasons SET sort_order = ?, updated_by = ? WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
            );
            if (!$stmt) {
                return false;
            }
            mysqli_stmt_bind_param($stmt, 'iiii', $sort, $employeeId, $reasonId, $companyId);
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return false;
            }
            mysqli_stmt_close($stmt);
            $sort += 10;
        }
        return true;
    }
}

if (!function_exists('itm_appointment_settings_load_appointment_types_admin')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_appointment_settings_load_appointment_types_admin(mysqli $conn, int $companyId): array
    {
        $rows = [];
        $sql = 'SELECT id, name, label, active FROM appointment_type WHERE company_id = ? AND deleted_at IS NULL ORDER BY name ASC';
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
