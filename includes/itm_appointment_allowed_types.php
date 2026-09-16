<?php
/**
 * Appointment type labels and per-weekday allowed-type maps (appointment_business_hours.allowed_types_json).
 */

if (!function_exists('itm_appointment_type_default_label_for_name')) {
    function itm_appointment_type_default_label_for_name(string $name): string
    {
        if ($name === 'remote') {
            return 'Remote';
        }
        if ($name === 'in_person') {
            return 'In-person';
        }
        return ucwords(str_replace('_', ' ', $name));
    }
}

if (!function_exists('itm_appointment_type_display_label')) {
    function itm_appointment_type_display_label(array $typeRow): string
    {
        $label = trim((string)($typeRow['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        return itm_appointment_type_default_label_for_name((string)($typeRow['name'] ?? ''));
    }
}

if (!function_exists('itm_appointment_types_sort_for_ui')) {
    /**
     * @param array<int, array<string, mixed>> $types
     * @return array<int, array<string, mixed>>
     */
    function itm_appointment_types_sort_for_ui(array $types): array
    {
        $byName = [];
        foreach ($types as $typeRow) {
            $byName[(string)($typeRow['name'] ?? '')] = $typeRow;
        }
        $ordered = [];
        foreach (['in_person', 'remote'] as $coreName) {
            if (isset($byName[$coreName])) {
                $ordered[] = $byName[$coreName];
                unset($byName[$coreName]);
            }
        }
        $rest = array_values($byName);
        usort($rest, static function ($a, $b) {
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        return array_merge($ordered, $rest);
    }
}

if (!function_exists('itm_appointment_hour_allowed_types_map')) {
    /**
     * @return array<string, bool>
     */
    function itm_appointment_hour_allowed_types_map(?array $hourRow): array
    {
        if (!$hourRow) {
            return [];
        }
        $json = trim((string)($hourRow['allowed_types_json'] ?? ''));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $out = [];
                foreach ($decoded as $typeName => $flag) {
                    $out[(string)$typeName] = ((int)$flag === 1 || $flag === true);
                }
                return $out;
            }
        }
        return [
            'in_person' => (int)($hourRow['allows_in_person'] ?? 0) === 1,
            'remote' => (int)($hourRow['allows_remote'] ?? 0) === 1,
        ];
    }
}

if (!function_exists('itm_appointment_encode_allowed_types_json')) {
  /**
   * @param array<string, bool|int> $map
   */
    function itm_appointment_encode_allowed_types_json(array $map): string
    {
        $normalized = [];
        foreach ($map as $typeName => $flag) {
            $normalized[(string)$typeName] = !empty($flag) ? 1 : 0;
        }
        return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('itm_appointment_hour_allowed_types_map_from_post')) {
    /**
     * @param array<int, array<string, mixed>> $typeRows
     * @return array<string, bool>
     */
    function itm_appointment_hour_allowed_types_map_from_post(array $typeRows, array $post): array
    {
        $posted = $post['allowed_type'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }
        $map = [];
        foreach ($typeRows as $typeRow) {
            $name = (string)($typeRow['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $map[$name] = !empty($posted[$name]);
        }
        return $map;
    }
}

if (!function_exists('itm_appointment_hour_legacy_modality_from_map')) {
    /**
     * @param array<string, bool|int> $map
     * @return array{allows_in_person:int,allows_remote:int}
     */
    function itm_appointment_hour_legacy_modality_from_map(array $map): array
    {
        return [
            'allows_in_person' => !empty($map['in_person']) ? 1 : 0,
            'allows_remote' => !empty($map['remote']) ? 1 : 0,
        ];
    }
}

if (!function_exists('itm_appointment_day_allowed_types_for_booking')) {
    /**
     * @param array<int, array<string, mixed>> $allTypeRows
     * @return array<string, bool>
     */
    function itm_appointment_day_allowed_types_for_booking(?array $hourRow, array $allTypeRows): array
    {
        $stored = itm_appointment_hour_allowed_types_map($hourRow);
        $out = [];
        foreach ($allTypeRows as $typeRow) {
            $name = (string)($typeRow['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[$name] = !empty($stored[$name]);
        }
        return $out;
    }
}

if (!function_exists('itm_appointment_hour_allows_any_type')) {
    function itm_appointment_hour_allows_any_type(?array $hourRow, array $allTypeRows): bool
    {
        if (!$hourRow || (int)($hourRow['is_closed'] ?? 0) === 1 || (int)($hourRow['active'] ?? 1) !== 1) {
            return false;
        }
        $flags = itm_appointment_day_allowed_types_for_booking($hourRow, $allTypeRows);
        foreach ($flags as $allowed) {
            if ($allowed) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('itm_appointment_hour_allows_type_name')) {
    function itm_appointment_hour_allows_type_name(?array $hourRow, string $typeName, array $allTypeRows): bool
    {
        if (!$hourRow || (int)($hourRow['is_closed'] ?? 0) === 1 || (int)($hourRow['active'] ?? 1) !== 1) {
            return false;
        }
        $flags = itm_appointment_day_allowed_types_for_booking($hourRow, $allTypeRows);
        return !empty($flags[$typeName]);
    }
}

if (!function_exists('itm_appointment_settings_append_type_to_business_hours')) {
    function itm_appointment_settings_append_type_to_business_hours(mysqli $conn, int $companyId, string $typeName, int $allowedDefault = 0): void
    {
        $companyId = (int)$companyId;
        $typeName = trim($typeName);
        if ($companyId <= 0 || $typeName === '') {
            return;
        }
        $stmt = mysqli_prepare($conn, 'SELECT id, allowed_types_json, allows_in_person, allows_remote FROM appointment_business_hours WHERE company_id = ? AND deleted_at IS NULL');
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $map = itm_appointment_hour_allowed_types_map($row);
            if (array_key_exists($typeName, $map)) {
                continue;
            }
            $map[$typeName] = $allowedDefault === 1;
            $legacy = itm_appointment_hour_legacy_modality_from_map($map);
            $json = itm_appointment_encode_allowed_types_json($map);
            $hourId = (int)$row['id'];
            $upd = mysqli_prepare(
                $conn,
                'UPDATE appointment_business_hours SET allowed_types_json = ?, allows_in_person = ?, allows_remote = ? WHERE id = ? AND company_id = ?'
            );
            if ($upd) {
                mysqli_stmt_bind_param(
                    $upd,
                    'siiii',
                    $json,
                    $legacy['allows_in_person'],
                    $legacy['allows_remote'],
                    $hourId,
                    $companyId
                );
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if (!function_exists('itm_appointment_settings_rename_type_on_business_hours')) {
    function itm_appointment_settings_rename_type_on_business_hours(mysqli $conn, int $companyId, string $oldName, string $newName): void
    {
        $companyId = (int)$companyId;
        $oldName = trim($oldName);
        $newName = trim($newName);
        if ($companyId <= 0 || $oldName === '' || $newName === '' || $oldName === $newName) {
            return;
        }
        $stmt = mysqli_prepare($conn, 'SELECT id, allowed_types_json, allows_in_person, allows_remote FROM appointment_business_hours WHERE company_id = ? AND deleted_at IS NULL');
        if (!$stmt) {
            return;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $map = itm_appointment_hour_allowed_types_map($row);
            if (!array_key_exists($oldName, $map)) {
                continue;
            }
            $map[$newName] = $map[$oldName];
            unset($map[$oldName]);
            $legacy = itm_appointment_hour_legacy_modality_from_map($map);
            $json = itm_appointment_encode_allowed_types_json($map);
            $hourId = (int)$row['id'];
            $upd = mysqli_prepare(
                $conn,
                'UPDATE appointment_business_hours SET allowed_types_json = ?, allows_in_person = ?, allows_remote = ? WHERE id = ? AND company_id = ?'
            );
            if ($upd) {
                mysqli_stmt_bind_param(
                    $upd,
                    'siiii',
                    $json,
                    $legacy['allows_in_person'],
                    $legacy['allows_remote'],
                    $hourId,
                    $companyId
                );
                mysqli_stmt_execute($upd);
                mysqli_stmt_close($upd);
            }
        }
        mysqli_stmt_close($stmt);
    }
}
