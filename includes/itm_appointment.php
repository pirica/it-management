<?php
/**
 * Appointment scheduling helpers (slots, settings, business hours).
 */

if (!function_exists('itm_appointment_sync_in_person_only_flag')) {
    function itm_appointment_sync_in_person_only_flag(int $allowInPerson, int $allowRemote): int
    {
        return ($allowInPerson === 1 && $allowRemote !== 1) ? 1 : 0;
    }
}

if (!function_exists('itm_appointment_business_hours_day_bookable')) {
    function itm_appointment_business_hours_day_bookable(?array $businessHourRow): bool
    {
        if (!$businessHourRow || (int)($businessHourRow['is_closed'] ?? 0) === 1) {
            return false;
        }
        return (int)($businessHourRow['allows_in_person'] ?? 0) === 1
            || (int)($businessHourRow['allows_remote'] ?? 0) === 1;
    }
}

if (!function_exists('itm_appointment_settings_allows_modality')) {
    function itm_appointment_settings_allows_modality(?array $settingsRow, string $typeName): bool
    {
        if (!$settingsRow) {
            return false;
        }
        if ($typeName === 'remote') {
            return (int)($settingsRow['allow_remote'] ?? 0) === 1;
        }
        if ($typeName === 'in_person') {
            return (int)($settingsRow['allow_in_person'] ?? 0) === 1;
        }
        return false;
    }
}

if (!function_exists('itm_appointment_build_booking_lock')) {
    function itm_appointment_build_booking_lock(string $dateYmd, string $startTime): string
    {
        return $dateYmd . '#' . substr($startTime, 0, 8);
    }
}

if (!function_exists('itm_appointment_load_settings')) {
    function itm_appointment_load_settings(mysqli $conn, int $companyId): ?array
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return null;
        }
        $sql = 'SELECT * FROM appointment_settings WHERE company_id = ? AND deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_appointment_load_business_hours')) {
    /**
     * @return array<int, array> keyed by day_of_week 0=Sunday … 6=Saturday
     */
    function itm_appointment_load_business_hours(mysqli $conn, int $companyId): array
    {
        $companyId = (int)$companyId;
        $out = [];
        $sql = 'SELECT * FROM appointment_business_hours WHERE company_id = ? AND deleted_at IS NULL ORDER BY day_of_week ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $out;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $out[(int)$row['day_of_week']] = $row;
        }
        mysqli_stmt_close($stmt);
        return $out;
    }
}

if (!function_exists('itm_appointment_day_label_short')) {
    function itm_appointment_day_label_short(int $dayOfWeek): string
    {
        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        return $labels[$dayOfWeek] ?? '';
    }
}

if (!function_exists('itm_appointment_format_time_display')) {
    function itm_appointment_format_time_display(string $time24): string
    {
        $ts = strtotime('1970-01-01 ' . $time24);
        if (!$ts) {
            return $time24;
        }
        return date('h:i A', $ts);
    }
}

if (!function_exists('itm_appointment_slot_label')) {
    function itm_appointment_slot_label(string $startTime, string $endTime): string
    {
        return itm_appointment_format_time_display($startTime) . ' - ' . itm_appointment_format_time_display($endTime);
    }
}

if (!function_exists('itm_appointment_week_start_sunday')) {
    function itm_appointment_week_start_sunday(string $dateYmd): string
    {
        $ts = strtotime($dateYmd);
        if (!$ts) {
            return $dateYmd;
        }
        $dow = (int)date('w', $ts);
        return date('Y-m-d', strtotime('-' . $dow . ' days', $ts));
    }
}

if (!function_exists('itm_appointment_booked_slots_for_range')) {
    /**
     * @return array<string, array<string, true>> date => start_time => true
     */
    function itm_appointment_booked_slots_for_range(mysqli $conn, int $companyId, string $startDate, string $endDate): array
    {
        $companyId = (int)$companyId;
        $booked = [];
        $sql = "SELECT appointment_date, start_time FROM appointments
                WHERE company_id = ? AND deleted_at IS NULL AND status = 'scheduled'
                  AND appointment_date >= ? AND appointment_date <= ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $booked;
        }
        mysqli_stmt_bind_param($stmt, 'iss', $companyId, $startDate, $endDate);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $d = (string)$row['appointment_date'];
            $t = substr((string)$row['start_time'], 0, 8);
            if (!isset($booked[$d])) {
                $booked[$d] = [];
            }
            $booked[$d][$t] = true;
        }
        mysqli_stmt_close($stmt);
        return $booked;
    }
}

if (!function_exists('itm_appointment_build_week_slots')) {
    /**
     * Build slot grid for a week starting Sunday (matches booking modal).
     *
     * @return array{week_start:string,week_end:string,timezone:string,days:array<int,array>}
     */
    function itm_appointment_build_week_slots(mysqli $conn, int $companyId, string $anchorDateYmd): array
    {
        $settings = itm_appointment_load_settings($conn, $companyId);
        $hoursByDay = itm_appointment_load_business_hours($conn, $companyId);
        $weekStart = itm_appointment_week_start_sunday($anchorDateYmd);
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        $booked = itm_appointment_booked_slots_for_range($conn, $companyId, $weekStart, $weekEnd);

        $slotMinutes = (int)($settings['slot_duration_minutes'] ?? 60);
        if ($slotMinutes < 15) {
            $slotMinutes = 60;
        }
        $bookableStart = (string)($settings['bookable_start_time'] ?? '09:00:00');
        $bookableEnd = (string)($settings['bookable_end_time'] ?? '14:00:00');
        $timezone = (string)($settings['timezone'] ?? 'UTC');

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $dateYmd = date('Y-m-d', strtotime($weekStart . ' +' . $i . ' days'));
            $dow = (int)date('w', strtotime($dateYmd));
            $bh = $hoursByDay[$dow] ?? null;
            $allows = itm_appointment_business_hours_day_bookable($bh);
            $slots = [];
            if ($allows) {
                $cursor = strtotime($dateYmd . ' ' . $bookableStart);
                $endTs = strtotime($dateYmd . ' ' . $bookableEnd);
                while ($cursor && $endTs && $cursor < $endTs) {
                    $startTime = date('H:i:s', $cursor);
                    $endSlotTs = $cursor + ($slotMinutes * 60);
                    if ($endSlotTs > $endTs) {
                        break;
                    }
                    $endTime = date('H:i:s', $endSlotTs);
                    $isBooked = !empty($booked[$dateYmd][$startTime]);
                    $slots[] = [
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'label' => itm_appointment_slot_label($startTime, $endTime),
                        'available' => !$isBooked,
                    ];
                    $cursor = $endSlotTs;
                }
            }
            $days[] = [
                'date' => $dateYmd,
                'day_of_week' => $dow,
                'day_label' => itm_appointment_day_label_short($dow),
                'day_number' => (int)date('j', strtotime($dateYmd)),
                'allows_booking' => $allows,
                'slots' => $slots,
            ];
        }

        return [
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'timezone' => $timezone,
            'days' => $days,
        ];
    }
}

if (!function_exists('itm_appointment_load_appointment_types')) {
    /**
     * @return array<int, array{id:int,name:string}>
     */
    function itm_appointment_load_appointment_types(mysqli $conn, int $companyId): array
    {
        $companyId = (int)$companyId;
        $rows = [];
        $sql = 'SELECT id, name FROM appointment_type WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC';
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

if (!function_exists('itm_appointment_resolve_type_id_by_name')) {
    function itm_appointment_resolve_type_id_by_name(mysqli $conn, int $companyId, string $name): int
    {
        $companyId = (int)$companyId;
        $name = trim($name);
        if ($companyId <= 0 || $name === '') {
            return 0;
        }
        $sql = 'SELECT id FROM appointment_type WHERE company_id = ? AND name = ? AND deleted_at IS NULL AND active = 1 LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $name);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return (int)($row['id'] ?? 0);
    }
}

if (!function_exists('itm_appointment_load_visit_reasons')) {
    function itm_appointment_load_visit_reasons(mysqli $conn, int $companyId): array
    {
        $rows = [];
        $sql = 'SELECT id, name FROM appointment_visit_reasons WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY sort_order ASC, name ASC';
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
