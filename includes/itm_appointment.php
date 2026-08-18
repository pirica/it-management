<?php
/**
 * Appointment scheduling helpers (slots, settings, business hours).
 */
require_once __DIR__ . '/itm_appointment_allowed_types.php';

if (!function_exists('itm_appointment_business_hours_day_bookable')) {
    function itm_appointment_business_hours_day_bookable(?array $businessHourRow, array $allTypeRows = []): bool
    {
        if (!$businessHourRow || (int)($businessHourRow['is_closed'] ?? 0) === 1) {
            return false;
        }
        if ((int)($businessHourRow['active'] ?? 1) !== 1) {
            return false;
        }
        if (!empty($allTypeRows)) {
            return itm_appointment_hour_allows_any_type($businessHourRow, $allTypeRows);
        }
        return (int)($businessHourRow['allows_in_person'] ?? 0) === 1
            || (int)($businessHourRow['allows_remote'] ?? 0) === 1;
    }
}

if (!function_exists('itm_appointment_day_allows_modality')) {
    /**
     * Per-weekday business hours (In Person / Remote columns on appointment_business_hours).
     */
    function itm_appointment_day_allows_modality(?array $businessHourRow, string $typeName): bool
    {
        if (!$businessHourRow || (int)($businessHourRow['is_closed'] ?? 0) === 1) {
            return false;
        }
        if ((int)($businessHourRow['active'] ?? 1) !== 1) {
            return false;
        }
        if ($typeName === 'remote') {
            return (int)($businessHourRow['allows_remote'] ?? 0) === 1;
        }
        if ($typeName === 'in_person') {
            return (int)($businessHourRow['allows_in_person'] ?? 0) === 1;
        }
        $map = itm_appointment_hour_allowed_types_map($businessHourRow);
        return !empty($map[$typeName]);
    }
}

if (!function_exists('itm_appointment_modality_for_date')) {
    /**
     * @return array{in_person:bool,remote:bool}
     */
    function itm_appointment_modality_for_date(mysqli $conn, int $companyId, string $dateYmd): array
    {
        $settings = itm_appointment_load_settings($conn, $companyId);
        $hoursByDay = itm_appointment_load_business_hours($conn, $companyId);
        $dow = -1;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            $ts = strtotime($dateYmd);
            if ($ts) {
                $dow = (int)date('w', $ts);
            }
        }
        $bh = $dow >= 0 ? ($hoursByDay[$dow] ?? null) : null;
        if (!$settings) {
            return ['in_person' => false, 'remote' => false];
        }
        return [
            'in_person' => itm_appointment_day_allows_modality($bh, 'in_person'),
            'remote' => itm_appointment_day_allows_modality($bh, 'remote'),
        ];
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

if (!function_exists('itm_appointment_settings_default_modality_name')) {
    /**
     * Tenant default modality when both in_person and remote are allowed (DB: appointment_settings.default_appointment_modality).
     */
    function itm_appointment_settings_default_modality_name(?array $settings): string
    {
        $name = strtolower(trim((string)($settings['default_appointment_modality'] ?? 'remote')));
        return in_array($name, ['in_person', 'remote'], true) ? $name : 'remote';
    }
}

if (!function_exists('itm_appointment_pick_modality_for_day')) {
    /**
     * @param array{in_person?:bool,remote?:bool} $dayFlags
     */
    function itm_appointment_pick_modality_for_day(array $dayFlags, ?array $settings): string
    {
        $allowsInPerson = !empty($dayFlags['in_person']);
        $allowsRemote = !empty($dayFlags['remote']);
        if ($allowsRemote && $allowsInPerson) {
            return itm_appointment_settings_default_modality_name($settings);
        }
        if ($allowsRemote) {
            return 'remote';
        }
        if ($allowsInPerson) {
            return 'in_person';
        }
        return itm_appointment_settings_default_modality_name($settings);
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
        $allTypes = itm_appointment_load_appointment_types($conn, $companyId);
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
            $allows = itm_appointment_business_hours_day_bookable($bh, $allTypes);
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
                'allows_in_person' => itm_appointment_day_allows_modality($bh, 'in_person'),
                'allows_remote' => itm_appointment_day_allows_modality($bh, 'remote'),
                'allowed_types' => itm_appointment_day_allowed_types_for_booking($bh, $allTypes),
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
        $sql = 'SELECT id, name, label FROM appointment_type WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY name ASC';
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

if (!function_exists('itm_appointment_regression_sample_business_hours_by_dow')) {
    /**
     * Company 1 canonical business hours (In Person / Remote columns) for regression tests.
     *
     * @return array<int, array{display_label:string,is_closed:int,allows_in_person:int,allows_remote:int}>
     */
    function itm_appointment_regression_sample_business_hours_by_dow(): array
    {
        return [
            0 => ['display_label' => 'Sun', 'is_closed' => 1, 'allows_in_person' => 0, 'allows_remote' => 0],
            1 => ['display_label' => 'Mon', 'is_closed' => 0, 'allows_in_person' => 1, 'allows_remote' => 1],
            2 => ['display_label' => 'Tue', 'is_closed' => 0, 'allows_in_person' => 1, 'allows_remote' => 1],
            3 => ['display_label' => 'Wed', 'is_closed' => 0, 'allows_in_person' => 0, 'allows_remote' => 1],
            4 => ['display_label' => 'Thu', 'is_closed' => 0, 'allows_in_person' => 1, 'allows_remote' => 1],
            5 => ['display_label' => 'Fri', 'is_closed' => 0, 'allows_in_person' => 1, 'allows_remote' => 1],
            6 => ['display_label' => 'Sat', 'is_closed' => 1, 'allows_in_person' => 0, 'allows_remote' => 0],
        ];
    }
}

if (!function_exists('itm_appointment_regression_expected_modality_for_dow')) {
    /**
     * Effective booking modality for canonical sample settings + business hours row.
     *
     * @return array{in_person:bool,remote:bool}
     */
    function itm_appointment_regression_expected_modality_for_dow(int $dayOfWeek): array
    {
        $sampleHours = itm_appointment_regression_sample_business_hours_by_dow();
        $bh = $sampleHours[$dayOfWeek] ?? null;
        $bhRow = $bh ? [
            'is_closed' => $bh['is_closed'],
            'allows_in_person' => $bh['allows_in_person'],
            'allows_remote' => $bh['allows_remote'],
            'active' => 1,
        ] : null;
        return [
            'in_person' => itm_appointment_day_allows_modality($bhRow, 'in_person'),
            'remote' => itm_appointment_day_allows_modality($bhRow, 'remote'),
        ];
    }
}

if (!function_exists('itm_appointment_regression_collect_company_modality_sample_errors')) {
    /**
     * Compare live tenant rows to canonical company 1 sample (seeds + verify_appointment.php).
     *
     * @return list<string>
     */
    function itm_appointment_regression_collect_company_modality_sample_errors(mysqli $conn, int $companyId): array
    {
        if ($companyId !== 1) {
            return [];
        }
        $errors = [];
        $settings = itm_appointment_load_settings($conn, $companyId);
        if (!$settings) {
            $errors[] = 'appointment_settings missing for company 1';
            return $errors;
        }

        $hours = itm_appointment_load_business_hours($conn, $companyId);
        foreach (itm_appointment_regression_sample_business_hours_by_dow() as $dow => $expected) {
            $row = $hours[$dow] ?? null;
            if (!$row) {
                $errors[] = 'appointment_business_hours missing day_of_week ' . $dow;
                continue;
            }
            foreach (['is_closed', 'allows_in_person', 'allows_remote'] as $col) {
                if ((int)($row[$col] ?? -1) !== (int)$expected[$col]) {
                    $errors[] = 'appointment_business_hours day ' . $dow . ' ' . $col . ' expected ' . $expected[$col] . ', got ' . ($row[$col] ?? 'null');
                }
            }
        }

        $dateProbes = [
            '2026-01-04' => 0,
            '2026-01-05' => 1,
            '2026-01-07' => 3,
        ];
        foreach ($dateProbes as $dateYmd => $dow) {
            $expected = itm_appointment_regression_expected_modality_for_dow($dow);
            $actual = itm_appointment_modality_for_date($conn, $companyId, $dateYmd);
            foreach (['in_person', 'remote'] as $flag) {
                if (!empty($expected[$flag]) !== !empty($actual[$flag])) {
                    $errors[] = 'modality for ' . $dateYmd . ' ' . $flag . ' expected ' . ($expected[$flag] ? '1' : '0') . ', got ' . (!empty($actual[$flag]) ? '1' : '0');
                }
            }
        }

        $week = itm_appointment_build_week_slots($conn, $companyId, '2026-01-04');
        foreach ($week['days'] as $day) {
            $dow = (int)($day['day_of_week'] ?? -1);
            $expected = itm_appointment_regression_expected_modality_for_dow($dow);
            $actualIn = !empty($day['allows_in_person']);
            $actualRemote = !empty($day['allows_remote']);
            if ($actualIn !== !empty($expected['in_person'])) {
                $errors[] = 'week_slots ' . ($day['date'] ?? '') . ' allows_in_person expected ' . ($expected['in_person'] ? '1' : '0');
            }
            if ($actualRemote !== !empty($expected['remote'])) {
                $errors[] = 'week_slots ' . ($day['date'] ?? '') . ' allows_remote expected ' . ($expected['remote'] ? '1' : '0');
            }
        }

        return $errors;
    }
}
