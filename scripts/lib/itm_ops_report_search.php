<?php
/**
 * Ops Report cross-date search helpers (CLI verify + demo seed scripts).
 *
 * Mirrors module logic in modules/ops_report/index.php — keep in sync when search changes.
 */

if (!function_exists('opr_format_date')) {
    function opr_format_date($dateStr) {
        if (!$dateStr) {
            return '—';
        }
        return date('d.m.y', strtotime($dateStr));
    }
}

if (!function_exists('opr_child_table_map')) {
    function opr_child_table_map() {
        return [
            'fb_outlet' => [
                'table' => 'ops_report_fb_outlet',
                'fields' => ['outlet_name', 'covers_breakfast', 'covers_lunch', 'covers_dinner', 'covers_dado', 'covers_pool', 'covers_brunch'],
            ],
            'walk_round' => [
                'table' => 'ops_report_walk_round',
                'fields' => ['area_name', 'early_shift', 'late_shift'],
            ],
            'courtesy_call' => [
                'table' => 'ops_report_courtesy_call',
                'fields' => ['guest_name', 'room_number', 'time_reported', 'checkout_date', 'notes', 'action_taken', 'case_closed', 'monitor'],
            ],
            'guest_experience' => [
                'table' => 'ops_report_guest_experience',
                'fields' => ['ref_id', 'guest_name', 'room_number', 'time_reported', 'checkout_date', 'feedback', 'action_taken', 'case_closed', 'monitor'],
            ],
            'butler' => [
                'table' => 'ops_report_butler',
                'fields' => ['room_number', 'notes'],
            ],
            'night_shift' => [
                'table' => 'ops_report_night_shift',
                'fields' => ['guest_name', 'notes'],
            ],
            'hotel_figure' => [
                'table' => 'ops_report_hotel_figure',
                'fields' => ['field_label', 'field_value'],
            ],
        ];
    }
}

if (!function_exists('opr_report_fields')) {
    function opr_report_fields() {
        return [
            'today_shift', 'tomorrow_shift', 'occupancy_pct', 'occupied_rooms', 'total_pax',
            'average_daily_rate', 'revpar', 'room_revenue', 'fb_revenue', 'spa_revenue',
            'kids_club_revenue', 'fo_upgrade_rooms', 'total_revenue', 'stay_score_target',
            'stay_score_ytd', 'stay_experience_comment', 'hsk_revenue', 'welcomes_notes',
        ];
    }
}

if (!function_exists('opr_search_like_pattern')) {
    function opr_search_like_pattern($search) {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string)$search);
        return '%' . $escaped . '%';
    }
}

if (!function_exists('opr_search_cross_date_section_labels')) {
    function opr_search_cross_date_section_labels() {
        return [
            'report' => 'Duty Managers / Hotel Figures',
            'fb_outlet' => 'Food & Beverage Overview',
            'walk_round' => 'Hotel Walk-Round Check',
            'courtesy_call' => 'Courtesy Calls',
            'guest_experience' => 'Guest Experience Report',
            'butler' => 'Suites Butler Service',
            'night_shift' => 'Night Shift',
            'hotel_figure' => 'Hotel Figures & Revenue',
        ];
    }
}

if (!function_exists('opr_search_normalize_section')) {
    function opr_search_normalize_section($section) {
        $section = trim((string)$section);
        if ($section === '' || $section === 'all') {
            return 'all';
        }
        $allowed = array_keys(opr_search_cross_date_section_labels());
        return in_array($section, $allowed, true) ? $section : 'all';
    }
}

if (!function_exists('opr_search_merge_cross_date_hit')) {
    function opr_search_merge_cross_date_hit(array &$hits, $reportDate, $sectionLabel) {
        if ($reportDate === '' || $reportDate === null) {
            return;
        }
        if (!isset($hits[$reportDate])) {
            $hits[$reportDate] = [];
        }
        if (!in_array($sectionLabel, $hits[$reportDate], true)) {
            $hits[$reportDate][] = $sectionLabel;
        }
    }
}

if (!function_exists('opr_search_cross_date_hits')) {
    function opr_search_cross_date_hits($conn, $company_id, $search, $searchSection = 'all') {
        if ($search === '') {
            return [];
        }
        $searchSection = opr_search_normalize_section($searchSection);
        $pattern = opr_search_like_pattern($search);
        $labels = opr_search_cross_date_section_labels();
        $hits = [];
        $scopes = $searchSection === 'all'
            ? array_merge(['report'], array_keys(opr_child_table_map()))
            : [$searchSection];

        foreach ($scopes as $scope) {
            if ($scope === 'report') {
                $reportConcatParts = [];
                foreach (opr_report_fields() as $field) {
                    if (!itm_is_safe_identifier($field)) {
                        continue;
                    }
                    $reportConcatParts[] = "COALESCE(r.`{$field}`,'')";
                }
                if (empty($reportConcatParts)) {
                    continue;
                }
                $reportConcat = 'CONCAT_WS(\' \', ' . implode(', ', $reportConcatParts) . ')';
                $sql = 'SELECT DISTINCT r.report_date FROM ops_report r WHERE r.company_id = ? AND r.active = 1 AND ' . $reportConcat . ' LIKE ?';
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) {
                    continue;
                }
                mysqli_stmt_bind_param($stmt, 'is', $company_id, $pattern);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($row = mysqli_fetch_assoc($res)) {
                    opr_search_merge_cross_date_hit($hits, $row['report_date'] ?? '', $labels['report']);
                }
                mysqli_stmt_close($stmt);
                continue;
            }

            $map = opr_child_table_map();
            if (!isset($map[$scope])) {
                continue;
            }
            $cfg = $map[$scope];
            $table = $cfg['table'];
            if (!itm_is_safe_identifier($table)) {
                continue;
            }
            $concatParts = [];
            foreach ($cfg['fields'] as $field) {
                if (!itm_is_safe_identifier($field)) {
                    continue;
                }
                $concatParts[] = "COALESCE(c.`{$field}`,'')";
            }
            if (empty($concatParts)) {
                continue;
            }
            $concat = 'CONCAT_WS(\' \', ' . implode(', ', $concatParts) . ')';
            $sql = 'SELECT DISTINCT r.report_date FROM ops_report r'
                . ' INNER JOIN `' . $table . '` c ON c.ops_report_id = r.id AND c.company_id = r.company_id'
                . ' WHERE r.company_id = ? AND r.active = 1 AND c.active = 1 AND ' . $concat . ' LIKE ?';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'is', $company_id, $pattern);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                opr_search_merge_cross_date_hit($hits, $row['report_date'] ?? '', $labels[$scope] ?? $scope);
            }
            mysqli_stmt_close($stmt);
        }

        krsort($hits);
        return $hits;
    }
}

if (!function_exists('opr_search_cross_date_hit_line_text')) {
    // Why: Single canonical plain-text hit line for verify scripts and screenshot assertions.
    function opr_search_cross_date_hit_line_text($search, $reportDate, array $sections) {
        return trim((string)$search)
            . ' | '
            . opr_format_date($reportDate)
            . ' — '
            . implode(', ', $sections);
    }
}

if (!function_exists('opr_report_index_url')) {
    function opr_report_index_url($day, $month, $year, $search = '', $searchScope = 'day', $searchSection = 'all') {
        $query = [
            'day' => (int)$day,
            'month' => (int)$month,
            'year' => (int)$year,
        ];
        if ($search !== '') {
            $query['search'] = $search;
        }
        if ($searchScope === 'all') {
            $query['search_scope'] = 'all';
        }
        $searchSection = opr_search_normalize_section($searchSection);
        if ($searchSection !== 'all') {
            $query['search_section'] = $searchSection;
        }
        return 'index.php?' . http_build_query($query);
    }
}
