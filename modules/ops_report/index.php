<?php
/**
 * Ops Report Module
 *
 * Daily operations report with hotel figures, F&B covers, walk-round checks,
 * guest experience, courtesy calls, and butler service rows.
 */

require_once '../../config/config.php';

$crud_table = 'ops_report';
$crud_title = 'Ops Report';
$crud_action = $crud_action ?? 'index';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$is_admin = itm_is_admin($conn, $user_id);

if (!function_exists('opr_format_date')) {
    function opr_format_date($dateStr) {
        if (!$dateStr) {
            return '—';
        }
        return date('d.m.y', strtotime($dateStr));
    }
}

if (!function_exists('opr_format_money')) {
    function opr_format_money($value) {
        if ($value === null || $value === '') {
            return '—';
        }
        return number_format((float)$value, 2, '.', ',') . ' €';
    }
}

if (!function_exists('opr_is_editable_date')) {
    // Why: Non-admins may edit today and yesterday; D-2 and older require admin.
    function opr_is_editable_date($dateStr, $isAdmin) {
        if ($isAdmin) {
            return true;
        }
        if (!$dateStr) {
            return false;
        }
        $cutoff = date('Y-m-d', strtotime('-2 days'));
        return date('Y-m-d', strtotime($dateStr)) > $cutoff;
    }
}

if (!function_exists('opr_default_fb_outlets')) {
    function opr_default_fb_outlets() {
        return [
            'OLIVEIRA BRASSERIE',
            'IN-ROOM DINING',
            'THE NEST COCKTAILS & BAR',
            'SERENO POOL BAR',
            'GUSTO RESTAURANT',
        ];
    }
}

if (!function_exists('opr_default_walk_areas')) {
    function opr_default_walk_areas() {
        return [
            'Premises',
            'Guest Corridors',
            'Pools & Gardens',
            'Kids Area & Tennis Courts',
            'Hotel Main Entrance',
            'Guests Garage',
            'Hotel Pools & Garden Checks',
            'Indoor Pool',
            'Garden Lights',
        ];
    }
}

if (!function_exists('opr_default_ui_json')) {
    // Why: All visible labels and headings are stored per report, not hardcoded in PHP.
    function opr_default_ui_json() {
        return [
            'page_title' => 'Daily Operations Report',
            'subtitle' => 'Duty Manager & Guest Relations | Daily Reports',
            'locked_notice' => '(read-only — D-2 or older; admin may edit)',
            'titles' => [
                'browser_module' => 'Ops Report',
                'browser_suffix' => 'IT Management',
                'export_sheet' => 'Ops Report',
                'export_filename_prefix' => 'Ops_Report',
            ],
            'sections' => [
                'duty_managers' => 'Duty Managers Team',
                'hotel_figures' => 'Hotel Figures & Revenue',
                'fb_overview' => 'Food & Beverage Overview',
                'walk_round' => 'Hotel Walk-Round Check',
                'guest_experience' => 'Guest Experience Report',
                'courtesy_calls' => 'Courtesy Calls',
                'butler' => 'Suites Butler Service',
                'night_shift' => 'Night Shift (23h00 – 07h30)',
            ],
            'fields' => [
                'today_shift' => "Today's Shift",
                'tomorrow_shift' => "Tomorrow's Shift",
                'occupancy_pct' => 'Occupancy (%)',
                'occupied_rooms' => 'Occupied Rooms',
                'total_pax' => 'Total Pax',
                'average_daily_rate' => 'Average Daily Rate (€)',
                'revpar' => 'RevPAR (€)',
                'room_revenue' => 'Room Revenue (€)',
                'fb_revenue' => 'F&B Revenue (€)',
                'spa_revenue' => 'Spa Revenue (€)',
                'kids_club_revenue' => 'Kids Club (€)',
                'fo_upgrade_rooms' => 'FO | Upgrade Rooms (€)',
                'total_revenue' => 'TOTAL REVENUE (€)',
                'stay_score_target' => 'Stay Score Target',
                'stay_score_ytd' => 'Stay Score YTD',
                'hsk_revenue' => 'HSK Revenue (€)',
                'stay_experience_comment' => 'Stay Experience — Comment of the day',
                'welcomes_notes' => 'Welcomes',
            ],
            'tables' => [
                'fb_outlet' => [
                    'outlet_name' => 'Outlet',
                    'covers_breakfast' => 'Breakfast',
                    'covers_lunch' => 'Lunch',
                    'covers_dinner' => 'Dinner',
                    'covers_dado' => 'DADO',
                    'covers_pool' => 'POOL',
                    'covers_brunch' => 'BRUNCH',
                ],
                'walk_round' => [
                    'area_name' => 'Area',
                    'early_shift' => 'Early Shift',
                    'late_shift' => 'Late Shift',
                ],
                'guest_experience' => [
                    'ref_id' => 'ID',
                    'guest_name' => 'Guest Name',
                    'room_number' => 'Room',
                    'time_reported' => 'Time',
                    'checkout_date' => 'Check Out',
                    'feedback' => 'Feedback',
                    'action_taken' => 'Actions Taken',
                    'case_closed' => 'Closed',
                    'monitor' => 'Monitor',
                ],
                'courtesy_call' => [
                    'guest_name' => 'Guest Name',
                    'room_number' => 'Room',
                    'time_reported' => 'Time',
                    'checkout_date' => 'Check Out',
                    'notes' => 'Notes',
                    'action_taken' => 'Actions Taken',
                    'case_closed' => 'Closed',
                    'monitor' => 'Monitor',
                ],
                'butler' => [
                    'room_number' => 'Room',
                    'notes' => 'Notes',
                ],
                'night_shift' => [
                    'guest_name' => 'Guest Name',
                    'notes' => 'Notes',
                ],
            ],
            'buttons' => [
                'add_fb_outlet' => '➕ Add F&B outlet row',
                'add_walk_round' => '➕ Add walk-round row',
                'add_guest_experience' => '➕ Add guest experience row',
                'add_courtesy_call' => '➕ Add courtesy call row',
                'add_butler' => '➕ Add butler row',
                'add_night_shift' => '➕ Add night shift row',
                'add_hotel_figure' => '➕ Add hotel figure field',
            ],
            'controls' => [
                'actions' => 'Actions',
            ],
            'defaults' => [
                'new_fb_outlet' => 'New Outlet',
                'new_walk_area' => 'New Area',
                'new_hotel_figure' => 'New Field',
            ],
        ];
    }
}

if (!function_exists('opr_resolve_ui_json')) {
    function opr_resolve_ui_json($report) {
        $defaults = opr_default_ui_json();
        if (empty($report['report_ui_json'])) {
            return $defaults;
        }
        $decoded = json_decode((string)$report['report_ui_json'], true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        return array_replace_recursive($defaults, $decoded);
    }
}

if (!function_exists('opr_ui_get')) {
    function opr_ui_get($ui, $path) {
        $parts = explode('.', (string)$path);
        $cur = $ui;
        foreach ($parts as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return '';
            }
            $cur = $cur[$part];
        }
        return is_scalar($cur) ? (string)$cur : '';
    }
}

if (!function_exists('opr_ui_set')) {
    function opr_ui_set(&$ui, $path, $value) {
        $parts = explode('.', (string)$path);
        $last = array_pop($parts);
        $cur = &$ui;
        foreach ($parts as $part) {
            if (!isset($cur[$part]) || !is_array($cur[$part])) {
                $cur[$part] = [];
            }
            $cur = &$cur[$part];
        }
        $cur[$last] = $value;
        return $ui;
    }
}

if (!function_exists('opr_is_allowed_ui_path')) {
    function opr_is_allowed_ui_path($path) {
        if ($path !== 'controls.actions' && strpos((string)$path, 'controls.') === 0) {
            return false;
        }
        $root = explode('.', (string)$path)[0];
        $allowed = ['page_title', 'subtitle', 'locked_notice', 'titles', 'sections', 'fields', 'tables', 'buttons', 'controls', 'defaults'];
        return in_array($root, $allowed, true);
    }
}

if (!function_exists('opr_persist_ui_json')) {
    function opr_persist_ui_json($conn, $reportId, $companyId, $ui) {
        $encoded = json_encode($ui, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = mysqli_prepare($conn, 'UPDATE ops_report SET report_ui_json = ? WHERE id = ? AND company_id = ?');
        mysqli_stmt_bind_param($stmt, 'sii', $encoded, $reportId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return (bool)$ok;
    }
}

if (!function_exists('opr_render_editable_ui_text')) {
    // Why: Section titles, labels, and table headers blur-save into report_ui_json.
    function opr_render_editable_ui_text($text, $canEdit, $jsonPath, $tag = 'span', $extraClass = '') {
        $safe = sanitize((string)$text);
        $pathAttr = sanitize($jsonPath);
        $classList = trim('inline-editable-ui ' . $extraClass);
        if ($canEdit) {
            $inputClass = 'edit-input-ui form-control';
            if ($tag === 'label') {
                $inputClass .= ' opr-ui-label-input';
            }
            if ($tag === 'h1') {
                return '<h1 class="opr-ui-heading ' . sanitize($classList) . '" data-scope="report_ui" data-json-path="' . $pathAttr . '">'
                    . '<input type="text" class="' . $inputClass . '" value="' . $safe . '"></h1>';
            }
            if ($tag === 'h2') {
                return '<h2 class="opr-ui-heading ' . sanitize($classList) . '" data-scope="report_ui" data-json-path="' . $pathAttr . '">'
                    . '<input type="text" class="' . $inputClass . '" value="' . $safe . '"></h2>';
            }
            if ($tag === 'th') {
                return '<th class="' . sanitize($classList) . '" data-scope="report_ui" data-json-path="' . $pathAttr . '">'
                    . '<input type="text" class="' . $inputClass . '" value="' . $safe . '"></th>';
            }
            if ($tag === 'strong') {
                return '<strong class="' . sanitize($classList) . '" data-scope="report_ui" data-json-path="' . $pathAttr . '">'
                    . '<input type="text" class="' . $inputClass . '" value="' . $safe . '"></strong>';
            }
            return '<' . $tag . ' class="' . sanitize($classList) . '" data-scope="report_ui" data-json-path="' . $pathAttr . '">'
                . '<input type="text" class="' . $inputClass . '" value="' . $safe . '"></' . $tag . '>';
        }
        if ($tag === 'th') {
            return '<th class="' . sanitize($extraClass) . '">' . $safe . '</th>';
        }
        if ($tag === 'h1') {
            return '<h1 class="opr-ui-heading ' . sanitize($extraClass) . '">' . $safe . '</h1>';
        }
        if ($tag === 'h2') {
            return '<h2 class="opr-ui-heading ' . sanitize($extraClass) . '">' . $safe . '</h2>';
        }
        if ($tag === 'strong') {
            return '<strong class="' . sanitize($extraClass) . '">' . $safe . '</strong>';
        }
        return '<' . $tag . ' class="' . sanitize($extraClass) . '">' . $safe . '</' . $tag . '>';
    }
}

if (!function_exists('opr_ensure_report')) {
    function opr_ensure_report($conn, $company_id, $report_date) {
        $stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report WHERE company_id = ? AND report_date = ? AND active = 1 LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'is', $company_id, $report_date);
        mysqli_stmt_execute($stmt);
        $report = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($report) {
            return $report;
        }

        $uiJson = json_encode(opr_default_ui_json(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = mysqli_prepare($conn, 'INSERT INTO ops_report (company_id, report_date, stay_score_target, report_ui_json, active) VALUES (?, ?, ?, ?, 1)');
        $defaultTarget = '95.0%';
        mysqli_stmt_bind_param($stmt, 'isss', $company_id, $report_date, $defaultTarget, $uiJson);
        mysqli_stmt_execute($stmt);
        $reportId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $sort = 0;
        foreach (opr_default_fb_outlets() as $outlet) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO ops_report_fb_outlet (company_id, ops_report_id, outlet_name, sort_order, active) VALUES (?, ?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $reportId, $outlet, $sort);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $sort++;
        }

        $sort = 0;
        foreach (opr_default_walk_areas() as $area) {
            $stmt = mysqli_prepare($conn, 'INSERT INTO ops_report_walk_round (company_id, ops_report_id, area_name, sort_order, active) VALUES (?, ?, ?, ?, 1)');
            mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $reportId, $area, $sort);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $sort++;
        }

        $stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report WHERE id = ? AND company_id = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ii', $reportId, $company_id);
        mysqli_stmt_execute($stmt);
        $report = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $report;
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

if (!function_exists('opr_render_editable_field')) {
    // Why: Editable dates show inputs directly; locked dates show read-only text only.
    function opr_render_editable_field($value, $canEdit, $multiline = false) {
        if ($canEdit) {
            if ($multiline) {
                return '<textarea class="edit-input form-control" rows="2">' . sanitize((string)($value ?? '')) . '</textarea>';
            }
            return '<input type="text" class="edit-input form-control" value="' . sanitize((string)($value ?? '')) . '">';
        }
        $text = ($value !== '' && $value !== null) ? sanitize($value) : '—';
        return '<span class="display-val">' . $text . '</span>';
    }
}

if (!function_exists('opr_json_response')) {
    function opr_json_response($payload) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$selected_day = (int)($_GET['day'] ?? date('j'));
$selected_month = (int)($_GET['month'] ?? date('n'));
$selected_year = (int)($_GET['year'] ?? date('Y'));
$days_in_month = (int)date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year));
if ($selected_day < 1) {
    $selected_day = 1;
}
if ($selected_day > $days_in_month) {
    $selected_day = $days_in_month;
}
$selected_date = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $selected_day);
$can_edit_report = opr_is_editable_date($selected_date, $is_admin);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_inline_edit'])) {
    itm_require_post_csrf();

    $report_date = trim((string)($_POST['report_date'] ?? ''));
    if (!opr_is_editable_date($report_date, $is_admin)) {
        opr_json_response(['success' => false, 'message' => 'This report date is locked (D-2 or older). Admin role required.']);
    }

    $report = opr_ensure_report($conn, $company_id, $report_date);
    $report_id = (int)$report['id'];
    $scope = trim((string)($_POST['scope'] ?? 'report'));
    $field = trim((string)($_POST['field'] ?? ''));
    $value = trim((string)($_POST['value'] ?? ''));
    $row_id = (int)($_POST['row_id'] ?? 0);

    if ($scope === 'report_ui') {
        $jsonPath = trim((string)($_POST['json_path'] ?? ''));
        if ($jsonPath === '' || !opr_is_allowed_ui_path($jsonPath)) {
            opr_json_response(['success' => false, 'message' => 'Invalid UI path.']);
        }
        $ui = opr_resolve_ui_json($report);
        opr_ui_set($ui, $jsonPath, $value);
        $ok = opr_persist_ui_json($conn, $report_id, $company_id, $ui);
        opr_json_response(['success' => $ok, 'id' => $report_id, 'message' => $ok ? 'Saved.' : 'Save failed.']);
    }

    if ($scope === 'report') {
        if (!in_array($field, opr_report_fields(), true)) {
            opr_json_response(['success' => false, 'message' => 'Invalid field.']);
        }
        $sql = 'UPDATE ops_report SET `' . $field . '` = ? WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sii', $value, $report_id, $company_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        opr_json_response(['success' => (bool)$ok, 'id' => $report_id, 'message' => $ok ? 'Saved.' : 'Save failed.']);
    }

    $map = opr_child_table_map();
    if (!isset($map[$scope])) {
        opr_json_response(['success' => false, 'message' => 'Invalid scope.']);
    }
    if (!in_array($field, $map[$scope]['fields'], true)) {
        opr_json_response(['success' => false, 'message' => 'Invalid child field.']);
    }
    if ($row_id <= 0) {
        opr_json_response(['success' => false, 'message' => 'Invalid row.']);
    }

    $table = $map[$scope]['table'];
    $sql = 'UPDATE `' . $table . '` SET `' . $field . '` = ? WHERE id = ? AND company_id = ? AND ops_report_id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'siii', $value, $row_id, $company_id, $report_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    opr_json_response(['success' => (bool)$ok, 'id' => $row_id, 'message' => $ok ? 'Saved.' : 'Save failed.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_row'])) {
    itm_require_post_csrf();

    $report_date = trim((string)($_POST['report_date'] ?? ''));
    if (!opr_is_editable_date($report_date, $is_admin)) {
        opr_json_response(['success' => false, 'message' => 'This report date is locked (D-2 or older). Admin role required.']);
    }

    $scope = trim((string)($_POST['scope'] ?? ''));
    $map = opr_child_table_map();
    if (!isset($map[$scope])) {
        opr_json_response(['success' => false, 'message' => 'Invalid scope.']);
    }

    $report = opr_ensure_report($conn, $company_id, $report_date);
    $report_id = (int)$report['id'];
    $table = $map[$scope]['table'];
    $uiResolved = opr_resolve_ui_json($report);

    $stmt = mysqli_prepare($conn, 'SELECT COALESCE(MAX(sort_order), -1) + 1 AS next_sort FROM `' . $table . '` WHERE ops_report_id = ? AND company_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
    mysqli_stmt_execute($stmt);
    $sortRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $sort = (int)($sortRow['next_sort'] ?? 0);

    if ($scope === 'fb_outlet') {
        $label = opr_ui_get($uiResolved, 'defaults.new_fb_outlet') ?: 'New Outlet';
        $sql = 'INSERT INTO ops_report_fb_outlet (company_id, ops_report_id, outlet_name, sort_order, active) VALUES (?, ?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $report_id, $label, $sort);
    } elseif ($scope === 'walk_round') {
        $label = opr_ui_get($uiResolved, 'defaults.new_walk_area') ?: 'New Area';
        $sql = 'INSERT INTO ops_report_walk_round (company_id, ops_report_id, area_name, sort_order, active) VALUES (?, ?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $report_id, $label, $sort);
    } elseif ($scope === 'courtesy_call') {
        $sql = 'INSERT INTO ops_report_courtesy_call (company_id, ops_report_id, sort_order, active) VALUES (?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $company_id, $report_id, $sort);
    } elseif ($scope === 'guest_experience') {
        $sql = 'INSERT INTO ops_report_guest_experience (company_id, ops_report_id, sort_order, active) VALUES (?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $company_id, $report_id, $sort);
    } elseif ($scope === 'butler') {
        $sql = 'INSERT INTO ops_report_butler (company_id, ops_report_id, sort_order, active) VALUES (?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $company_id, $report_id, $sort);
    } elseif ($scope === 'night_shift') {
        $sql = 'INSERT INTO ops_report_night_shift (company_id, ops_report_id, sort_order, active) VALUES (?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iii', $company_id, $report_id, $sort);
    } elseif ($scope === 'hotel_figure') {
        $label = opr_ui_get($uiResolved, 'defaults.new_hotel_figure') ?: 'New Field';
        $sql = 'INSERT INTO ops_report_hotel_figure (company_id, ops_report_id, field_label, sort_order, active) VALUES (?, ?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iisi', $company_id, $report_id, $label, $sort);
    } else {
        opr_json_response(['success' => false, 'message' => 'Invalid scope.']);
    }

    $ok = mysqli_stmt_execute($stmt);
    $newId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    opr_json_response(['success' => (bool)$ok, 'id' => $newId, 'message' => $ok ? 'Row added.' : 'Add failed.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_row'])) {
    itm_require_post_csrf();

    $report_date = trim((string)($_POST['report_date'] ?? ''));
    if (!opr_is_editable_date($report_date, $is_admin)) {
        opr_json_response(['success' => false, 'message' => 'This report date is locked (D-2 or older). Admin role required.']);
    }

    $scope = trim((string)($_POST['scope'] ?? ''));
    $row_id = (int)($_POST['row_id'] ?? 0);
    $map = opr_child_table_map();
    if (!isset($map[$scope]) || $row_id <= 0) {
        opr_json_response(['success' => false, 'message' => 'Invalid delete request.']);
    }

    $report = opr_ensure_report($conn, $company_id, $report_date);
    $report_id = (int)$report['id'];
    $table = $map[$scope]['table'];
    $sql = 'DELETE FROM `' . $table . '` WHERE id = ? AND company_id = ? AND ops_report_id = ?';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $row_id, $company_id, $report_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    opr_json_response(['success' => (bool)$ok, 'message' => $ok ? 'Row deleted.' : 'Delete failed.']);
}

if ($crud_action === 'delete') {
    // Why: Server-side RBAC before CSRF/delete SQL (UI-only hiding is not enough).
    itm_require_crud_role_module_permission($conn, 'delete', 'ops_report');

    itm_require_post_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($conn, 'SELECT report_date FROM ops_report WHERE id = ? AND company_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($row && opr_is_editable_date($row['report_date'], $is_admin)) {
        $stmt = mysqli_prepare($conn, 'DELETE FROM ops_report WHERE id = ? AND company_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['crud_success'] = 'Report deleted.';
    } else {
        $_SESSION['crud_error'] = 'This report date is locked (D-2 or older). Admin role required.';
    }
    header('Location: index.php?day=' . (int)$selected_day . '&month=' . (int)$selected_month . '&year=' . (int)$selected_year);
    exit;
}

$report = opr_ensure_report($conn, $company_id, $selected_date);
$report_id = (int)$report['id'];

$ui_json = opr_resolve_ui_json($report);
if (empty($report['report_ui_json'])) {
    opr_persist_ui_json($conn, $report_id, $company_id, $ui_json);
    $report['report_ui_json'] = json_encode($ui_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

$fb_outlets = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_fb_outlet WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $fb_outlets[] = $row;
}
mysqli_stmt_close($stmt);

$walk_rounds = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_walk_round WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $walk_rounds[] = $row;
}
mysqli_stmt_close($stmt);

$courtesy_calls = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_courtesy_call WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $courtesy_calls[] = $row;
}
mysqli_stmt_close($stmt);

$guest_experiences = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_guest_experience WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $guest_experiences[] = $row;
}
mysqli_stmt_close($stmt);

$butler_rows = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_butler WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $butler_rows[] = $row;
}
mysqli_stmt_close($stmt);

$night_shift_rows = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_night_shift WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $night_shift_rows[] = $row;
}
mysqli_stmt_close($stmt);

$hotel_figure_rows = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM ops_report_hotel_figure WHERE ops_report_id = ? AND company_id = ? AND active = 1 ORDER BY sort_order ASC, id ASC');
mysqli_stmt_bind_param($stmt, 'ii', $report_id, $company_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $hotel_figure_rows[] = $row;
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, 'SELECT company, unit_no FROM companies WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $company_id);
mysqli_stmt_execute($stmt);
$company_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$metric_fields = [
    'occupancy_pct', 'occupied_rooms', 'total_pax', 'average_daily_rate', 'revpar',
    'room_revenue', 'fb_revenue', 'spa_revenue', 'kids_club_revenue', 'fo_upgrade_rooms',
    'total_revenue', 'stay_score_target', 'stay_score_ytd', 'hsk_revenue',
];

$editClass = $can_edit_report ? 'inline-editable' : 'opr-readonly';
$uiEditClass = $can_edit_report ? 'inline-editable-ui' : '';
$lockedNotice = $can_edit_report ? '' : ' ' . opr_ui_get($ui_json, 'locked_notice');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= sanitize(opr_ui_get($ui_json, 'titles.browser_module')) ?> - <?= sanitize(opr_ui_get($ui_json, 'titles.browser_suffix')) ?></title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .opr-controls { display:flex; gap:10px; align-items:flex-end; margin-bottom:20px; flex-wrap:wrap; }
        .opr-section { margin-bottom:24px; }
        .opr-section h2 { margin:0 0 10px; font-size:1.1rem; }
        .opr-section-spaced { margin-top:20px; }
        .opr-table th { white-space:nowrap; font-size:12px; }
        .opr-readonly { background-color: var(--bg-secondary); }
        .opr-today { background-color: rgba(var(--primary-rgb), 0.08); }
        .opr-header-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
        .opr-metric-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:10px; }
        .opr-metric { border:1px solid var(--border-color); border-radius:6px; padding:8px; }
        .opr-metric label { display:block; font-size:11px; color:var(--text-secondary); margin-bottom:4px; }
        .inline-editable .edit-input { width:100%; min-width:48px; }
        .opr-table .edit-input { padding:4px 6px; font-size:12px; }
        .opr-metric .edit-input { margin-top:4px; }
        .opr-metric-custom-head { display:flex; justify-content:space-between; align-items:flex-start; gap:4px; margin-bottom:4px; }
        .opr-custom-label-wrap .edit-input { font-size:11px; color:var(--text-secondary); border:none; background:transparent; padding:0; width:100%; margin-top:0; }
        .opr-metric-delete { padding:2px 6px; line-height:1; flex-shrink:0; }
        .opr-ui-heading { margin:0 0 10px; font-size:1.1rem; }
        .opr-ui-heading .edit-input-ui { font-size:1.1rem; font-weight:600; border:none; background:transparent; padding:0; }
        .opr-ui-label-input { font-size:11px; border:none; background:transparent; padding:0; color:var(--text-secondary); }
        .opr-table th .edit-input-ui { font-size:12px; border:none; background:transparent; padding:0; width:100%; min-width:40px; }
        .opr-page-title .edit-input-ui { font-size:1.5rem; font-weight:600; border:none; background:transparent; padding:0; width:auto; min-width:200px; }
        .opr-title-meta .edit-input-ui { font-size:12px; border:none; background:transparent; padding:0; min-width:72px; color:var(--text-secondary); }
        .opr-subtitle .edit-input-ui { border:none; background:transparent; padding:0; width:100%; color:var(--text-secondary); }
        .opr-company-block .edit-input-ui { border:none; background:transparent; padding:0; min-width:120px; }
        .opr-btn-label .edit-input-ui { border:none; background:transparent; padding:0; min-width:80px; }
        @media print {
            @page { size: landscape; margin: 1cm; }
            .opr-controls, .opr-no-print, .btn { display:none !important; }
            .card { border:none !important; box-shadow:none !important; }
        }
        @media (max-width: 900px) {
            .opr-header-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .opr-section { overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%; }
            .opr-table { min-width: 640px; }
            .opr-table th { white-space: normal; }
            .opr-page-title .edit-input-ui { min-width: 0; width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h1 style="margin:0;" class="opr-page-title">
                        <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'page_title'), $can_edit_report, 'page_title', 'span', 'opr-page-title') ?>
                        <?= sanitize(opr_format_date($selected_date)) ?>
                    </h1>
                    <p style="margin:8px 0 0; color:var(--text-secondary);" class="opr-subtitle">
                        <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'subtitle'), $can_edit_report, 'subtitle', 'span', 'opr-subtitle') ?><?= sanitize($lockedNotice) ?>
                    </p>
                    <div class="opr-title-meta opr-no-print" style="margin-top:8px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; font-size:12px; color:var(--text-secondary);">
                        <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'titles.browser_module'), $can_edit_report, 'titles.browser_module', 'span', 'opr-title-meta') ?>
                        <span aria-hidden="true">-</span>
                        <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'titles.browser_suffix'), $can_edit_report, 'titles.browser_suffix', 'span', 'opr-title-meta') ?>
                        <span aria-hidden="true">|</span>
                        <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'titles.export_sheet'), $can_edit_report, 'titles.export_sheet', 'span', 'opr-title-meta') ?>
                        <span aria-hidden="true">|</span>
                        <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'titles.export_filename_prefix'), $can_edit_report, 'titles.export_filename_prefix', 'span', 'opr-title-meta') ?>
                    </div>
                </div>
                <div style="text-align:right;" class="opr-company-block">
                    <strong>Company:</strong> <?= sanitize($company_info['company'] ?? '') ?>
                </div>
            </div>

            <div class="card opr-controls opr-no-print">
                <form method="GET" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                    <div class="form-group" style="margin:0;">
                        <label>Day</label>
                        <select name="day" class="form-control" onchange="this.form.submit()">
                            <?php for ($d = 1; $d <= $days_in_month; $d++): ?>
                                <option value="<?= $d ?>" <?= $d === $selected_day ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Month</label>
                        <select name="month" class="form-control" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $m === $selected_month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Year</label>
                        <select name="year" class="form-control" onchange="this.form.submit()">
                            <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
                                <option value="<?= $y ?>" <?= $y === $selected_year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Go</button>
                </form>
                <div style="flex-grow:1;"></div>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-success" onclick="exportOPR('xlsx')">📗 Export Excel</button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="exportOPR('pdf')">📄 Export PDF</button>
                </div>
            </div>

            <div class="card opr-section" id="opr-report-root" data-report-id="<?= $report_id ?>" data-report-date="<?= sanitize($selected_date) ?>" data-can-edit="<?= $can_edit_report ? '1' : '0' ?>" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'sections.duty_managers'), $can_edit_report, 'sections.duty_managers', 'h2') ?>
                <div class="opr-header-grid">
                    <div class="opr-metric <?= $editClass ?>" data-scope="report" data-field="today_shift">
                        <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'fields.today_shift'), $can_edit_report, 'fields.today_shift', 'label') ?>
                        <?= opr_render_editable_field($report['today_shift'] ?? '', $can_edit_report, true) ?>
                    </div>
                    <div class="opr-metric <?= $editClass ?>" data-scope="report" data-field="tomorrow_shift">
                        <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'fields.tomorrow_shift'), $can_edit_report, 'fields.tomorrow_shift', 'label') ?>
                        <?= opr_render_editable_field($report['tomorrow_shift'] ?? '', $can_edit_report, true) ?>
                    </div>
                </div>

                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'sections.hotel_figures'), $can_edit_report, 'sections.hotel_figures', 'h2') ?>
                <div class="opr-metric-grid" id="opr-hotel-figures-grid">
                    <?php foreach ($metric_fields as $field): ?>
                    <div class="opr-metric <?= $editClass ?>" data-scope="report" data-field="<?= $field ?>">
                        <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'fields.' . $field), $can_edit_report, 'fields.' . $field, 'label') ?>
                        <?= opr_render_editable_field($report[$field] ?? '', $can_edit_report) ?>
                    </div>
                    <?php endforeach; ?>
                    <?php foreach ($hotel_figure_rows as $row): ?>
                    <div class="opr-metric opr-metric-custom" data-row-id="<?= (int)$row['id'] ?>" data-scope="hotel_figure">
                        <div class="opr-metric-custom-head">
                            <div class="<?= $editClass ?> opr-custom-label-wrap" data-field="field_label">
                                <?= opr_render_editable_field($row['field_label'] ?? '', $can_edit_report) ?>
                            </div>
                            <?php if ($can_edit_report): ?>
                            <button type="button" class="btn btn-sm btn-danger opr-delete-row opr-metric-delete opr-no-print" data-scope="hotel_figure" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                            <?php endif; ?>
                        </div>
                        <div class="<?= $editClass ?>" data-field="field_value">
                            <?= opr_render_editable_field($row['field_value'] ?? '', $can_edit_report) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print opr-btn-label" data-add-scope="hotel_figure"><?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'buttons.add_hotel_figure'), $can_edit_report, 'buttons.add_hotel_figure', 'span', 'opr-btn-label') ?></button>
                <?php endif; ?>
                <div class="opr-metric <?= $editClass ?>" data-scope="report" data-field="stay_experience_comment" style="margin-top:10px;">
                    <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'fields.stay_experience_comment'), $can_edit_report, 'fields.stay_experience_comment', 'label') ?>
                    <?= opr_render_editable_field($report['stay_experience_comment'] ?? '', $can_edit_report, true) ?>
                </div>

                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'sections.fb_overview'), $can_edit_report, 'sections.fb_overview', 'h2', 'opr-section-spaced') ?>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-fb-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <?php foreach (['outlet_name', 'covers_breakfast', 'covers_lunch', 'covers_dinner', 'covers_dado', 'covers_pool', 'covers_brunch'] as $col): ?>
                                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'tables.fb_outlet.' . $col), $can_edit_report, 'tables.fb_outlet.' . $col, 'th') ?>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?><?= str_replace('<th class="', '<th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1 ', opr_render_editable_ui_text(opr_ui_get($ui_json, 'controls.actions'), true, 'controls.actions', 'th', 'opr-no-print itm-actions-cell')) ?><?php else: ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1"><?= sanitize(opr_ui_get($ui_json, 'controls.actions')) ?></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fb_outlets as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="fb_outlet">
                                <?php foreach (['outlet_name', 'covers_breakfast', 'covers_lunch', 'covers_dinner', 'covers_dado', 'covers_pool', 'covers_brunch'] as $field): ?>
                                <td class="<?= $editClass ?>" data-field="<?= $field ?>">
                                    <?= opr_render_editable_field($row[$field] ?? '', $can_edit_report) ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="fb_outlet" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print opr-btn-label" data-add-scope="fb_outlet"><?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'buttons.add_fb_outlet'), $can_edit_report, 'buttons.add_fb_outlet', 'span', 'opr-btn-label') ?></button>
                <?php endif; ?>

                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'sections.walk_round'), $can_edit_report, 'sections.walk_round', 'h2', 'opr-section-spaced') ?>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-walk-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <?php foreach (['area_name', 'early_shift', 'late_shift'] as $col): ?>
                                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'tables.walk_round.' . $col), $can_edit_report, 'tables.walk_round.' . $col, 'th') ?>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?><?= str_replace('<th class="', '<th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1 ', opr_render_editable_ui_text(opr_ui_get($ui_json, 'controls.actions'), true, 'controls.actions', 'th', 'opr-no-print itm-actions-cell')) ?><?php else: ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1"><?= sanitize(opr_ui_get($ui_json, 'controls.actions')) ?></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($walk_rounds as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="walk_round">
                                <?php foreach (['area_name', 'early_shift', 'late_shift'] as $field): ?>
                                <td class="<?= $editClass ?>" data-field="<?= $field ?>">
                                    <?= opr_render_editable_field($row[$field] ?? '', $can_edit_report) ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="walk_round" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print opr-btn-label" data-add-scope="walk_round"><?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'buttons.add_walk_round'), $can_edit_report, 'buttons.add_walk_round', 'span', 'opr-btn-label') ?></button>
                <?php endif; ?>

                <div class="opr-metric <?= $editClass ?>" data-scope="report" data-field="welcomes_notes" style="margin-top:16px;">
                    <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'fields.welcomes_notes'), $can_edit_report, 'fields.welcomes_notes', 'label') ?>
                    <?= opr_render_editable_field($report['welcomes_notes'] ?? '', $can_edit_report, true) ?>
                </div>

                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'sections.guest_experience'), $can_edit_report, 'sections.guest_experience', 'h2', 'opr-section-spaced') ?>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-guest-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <?php foreach (['ref_id', 'guest_name', 'room_number', 'time_reported', 'checkout_date', 'feedback', 'action_taken', 'case_closed', 'monitor'] as $col): ?>
                                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'tables.guest_experience.' . $col), $can_edit_report, 'tables.guest_experience.' . $col, 'th') ?>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?><?= str_replace('<th class="', '<th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1 ', opr_render_editable_ui_text(opr_ui_get($ui_json, 'controls.actions'), true, 'controls.actions', 'th', 'opr-no-print itm-actions-cell')) ?><?php else: ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1"><?= sanitize(opr_ui_get($ui_json, 'controls.actions')) ?></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($guest_experiences as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="guest_experience">
                                <?php foreach (['ref_id', 'guest_name', 'room_number', 'time_reported', 'checkout_date', 'feedback', 'action_taken', 'case_closed', 'monitor'] as $field): ?>
                                <td class="<?= $editClass ?>" data-field="<?= $field ?>">
                                    <?= opr_render_editable_field($row[$field] ?? '', $can_edit_report, in_array($field, ['feedback', 'action_taken'], true)) ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="guest_experience" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print opr-btn-label" data-add-scope="guest_experience"><?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'buttons.add_guest_experience'), $can_edit_report, 'buttons.add_guest_experience', 'span', 'opr-btn-label') ?></button>
                <?php endif; ?>

                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'sections.courtesy_calls'), $can_edit_report, 'sections.courtesy_calls', 'h2', 'opr-section-spaced') ?>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-courtesy-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <?php foreach (['guest_name', 'room_number', 'time_reported', 'checkout_date', 'notes', 'action_taken', 'case_closed', 'monitor'] as $col): ?>
                                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'tables.courtesy_call.' . $col), $can_edit_report, 'tables.courtesy_call.' . $col, 'th') ?>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?><?= str_replace('<th class="', '<th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1 ', opr_render_editable_ui_text(opr_ui_get($ui_json, 'controls.actions'), true, 'controls.actions', 'th', 'opr-no-print itm-actions-cell')) ?><?php else: ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1"><?= sanitize(opr_ui_get($ui_json, 'controls.actions')) ?></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courtesy_calls as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="courtesy_call">
                                <?php foreach (['guest_name', 'room_number', 'time_reported', 'checkout_date', 'notes', 'action_taken', 'case_closed', 'monitor'] as $field): ?>
                                <td class="<?= $editClass ?>" data-field="<?= $field ?>">
                                    <?= opr_render_editable_field($row[$field] ?? '', $can_edit_report, in_array($field, ['notes', 'action_taken'], true)) ?>
                                </td>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="courtesy_call" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print opr-btn-label" data-add-scope="courtesy_call"><?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'buttons.add_courtesy_call'), $can_edit_report, 'buttons.add_courtesy_call', 'span', 'opr-btn-label') ?></button>
                <?php endif; ?>

                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'sections.butler'), $can_edit_report, 'sections.butler', 'h2', 'opr-section-spaced') ?>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-butler-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <?php foreach (['room_number', 'notes'] as $col): ?>
                                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'tables.butler.' . $col), $can_edit_report, 'tables.butler.' . $col, 'th') ?>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?><?= str_replace('<th class="', '<th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1 ', opr_render_editable_ui_text(opr_ui_get($ui_json, 'controls.actions'), true, 'controls.actions', 'th', 'opr-no-print itm-actions-cell')) ?><?php else: ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1"><?= sanitize(opr_ui_get($ui_json, 'controls.actions')) ?></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($butler_rows as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="butler">
                                <td class="<?= $editClass ?>" data-field="room_number">
                                    <?= opr_render_editable_field($row['room_number'] ?? '', $can_edit_report) ?>
                                </td>
                                <td class="<?= $editClass ?>" data-field="notes">
                                    <?= opr_render_editable_field($row['notes'] ?? '', $can_edit_report, true) ?>
                                </td>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="butler" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print opr-btn-label" data-add-scope="butler"><?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'buttons.add_butler'), $can_edit_report, 'buttons.add_butler', 'span', 'opr-btn-label') ?></button>
                <?php endif; ?>

                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'sections.night_shift'), $can_edit_report, 'sections.night_shift', 'h2', 'opr-section-spaced') ?>
                <div style="overflow:auto;">
                    <table class="table opr-table" id="opr-night-shift-table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead>
                            <tr>
                                <?php foreach (['guest_name', 'notes'] as $col): ?>
                                <?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'tables.night_shift.' . $col), $can_edit_report, 'tables.night_shift.' . $col, 'th') ?>
                                <?php endforeach; ?>
                                <?php if ($can_edit_report): ?><?= str_replace('<th class="', '<th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1 ', opr_render_editable_ui_text(opr_ui_get($ui_json, 'controls.actions'), true, 'controls.actions', 'th', 'opr-no-print itm-actions-cell')) ?><?php else: ?><th class="opr-no-print itm-actions-cell" data-itm-actions-origin="1"><?= sanitize(opr_ui_get($ui_json, 'controls.actions')) ?></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($night_shift_rows as $row): ?>
                            <tr data-row-id="<?= (int)$row['id'] ?>" data-scope="night_shift">
                                <td class="<?= $editClass ?>" data-field="guest_name">
                                    <?= opr_render_editable_field($row['guest_name'] ?? '', $can_edit_report) ?>
                                </td>
                                <td class="<?= $editClass ?>" data-field="notes">
                                    <?= opr_render_editable_field($row['notes'] ?? '', $can_edit_report, true) ?>
                                </td>
                                <?php if ($can_edit_report): ?>
                                <td class="opr-no-print itm-actions-cell" data-itm-actions-origin="1">
                                    <button type="button" class="btn btn-sm btn-danger opr-delete-row" data-scope="night_shift" data-row-id="<?= (int)$row['id'] ?>">🗑️</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit_report): ?>
                <button type="button" class="btn btn-sm opr-no-print opr-btn-label" data-add-scope="night_shift"><?= opr_render_editable_ui_text(opr_ui_get($ui_json, 'buttons.add_night_shift'), $can_edit_report, 'buttons.add_night_shift', 'span', 'opr-btn-label') ?></button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="../../js/script.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const root = document.getElementById('opr-report-root');
    if (!root) {
        return;
    }

    const reportDate = root.dataset.reportDate;
    const csrfToken = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;
    const canEdit = root.dataset.canEdit === '1';

    function fieldValue(el) {
        const input = el.querySelector('.edit-input');
        if (input) {
            return input.value.trim();
        }
        const display = el.querySelector('.display-val');
        return display ? display.textContent.trim() : '';
    }

    function uiFieldValue(el) {
        const input = el.querySelector('.edit-input-ui');
        return input ? input.value.trim() : (el.textContent || '').trim();
    }

    function saveUiEdit(el) {
        const jsonPath = el.dataset.jsonPath;
        if (!jsonPath) {
            return;
        }
        const value = uiFieldValue(el);
        const formData = new FormData();
        formData.append('ajax_inline_edit', '1');
        formData.append('csrf_token', csrfToken);
        formData.append('report_date', reportDate);
        formData.append('scope', 'report_ui');
        formData.append('json_path', jsonPath);
        formData.append('field', 'report_ui_json');
        formData.append('value', value);
        formData.append('row_id', '0');

        fetch('index.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Update failed');
                    location.reload();
                    return;
                }
                if (jsonPath.indexOf('titles.browser_') === 0) {
                    syncBrowserTitle();
                }
            })
            .catch(() => {
                alert('Update failed');
            });
    }

    function saveEdit(cell) {
        const rowContainer = cell.closest('tr') || cell.closest('[data-row-id]');
        const scope = cell.dataset.scope || (rowContainer ? rowContainer.dataset.scope : 'report');
        const field = cell.dataset.field;
        if (!field) {
            return;
        }
        const rowId = rowContainer ? (rowContainer.dataset.rowId || '0') : '0';
        const value = fieldValue(cell);

        const formData = new FormData();
        formData.append('ajax_inline_edit', '1');
        formData.append('csrf_token', csrfToken);
        formData.append('report_date', reportDate);
        formData.append('scope', scope);
        formData.append('field', field);
        formData.append('value', value);
        formData.append('row_id', rowId);

        fetch('index.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Update failed');
                    location.reload();
                }
            })
            .catch(() => {
                alert('Update failed');
            });
    }

    if (canEdit) {
        document.querySelectorAll('.inline-editable .edit-input').forEach(input => {
            const cell = input.closest('.inline-editable');
            if (!cell) {
                return;
            }
            input.addEventListener('blur', () => saveEdit(cell));
            if (input.tagName === 'INPUT') {
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        input.blur();
                    }
                });
            }
        });

        document.querySelectorAll('.inline-editable-ui .edit-input-ui').forEach(input => {
            const cell = input.closest('.inline-editable-ui');
            if (!cell) {
                return;
            }
            input.addEventListener('mousedown', (e) => e.stopPropagation());
            input.addEventListener('click', (e) => e.stopPropagation());
            input.addEventListener('blur', () => saveUiEdit(cell));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    input.blur();
                }
            });
        });

        syncBrowserTitle();
    }

    if (!canEdit) {
        return;
    }

    document.querySelectorAll('[data-add-scope]').forEach(btn => {
        btn.addEventListener('click', () => {
            const scope = btn.dataset.addScope;
            const formData = new FormData();
            formData.append('ajax_add_row', '1');
            formData.append('csrf_token', csrfToken);
            formData.append('report_date', reportDate);
            formData.append('scope', scope);
            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Add failed');
                    }
                });
        });
    });

    document.querySelectorAll('.opr-delete-row').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!confirm('Delete this row?')) {
                return;
            }
            const formData = new FormData();
            formData.append('ajax_delete_row', '1');
            formData.append('csrf_token', csrfToken);
            formData.append('report_date', reportDate);
            formData.append('scope', btn.dataset.scope);
            formData.append('row_id', btn.dataset.rowId);
            fetch('index.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Delete failed');
                    }
                });
        });
    });
});

function syncBrowserTitle() {
    const mod = document.querySelector('[data-json-path="titles.browser_module"]');
    const suf = document.querySelector('[data-json-path="titles.browser_suffix"]');
    if (mod && suf) {
        document.title = oprUiFieldValue(mod) + ' - ' + oprUiFieldValue(suf);
    }
}

function oprFieldValue(el) {
    const input = el.querySelector('.edit-input');
    if (input) {
        return input.value.trim();
    }
    const display = el.querySelector('.display-val');
    return display ? display.textContent.trim() : '';
}

function oprUiFieldValue(el) {
    const input = el.querySelector('.edit-input-ui');
    if (input) {
        return input.value.trim();
    }
    return el ? el.textContent.trim() : '';
}

function exportOPR(format) {
    const pageTitleEl = document.querySelector('[data-json-path="page_title"]');
    const pageTitle = pageTitleEl ? oprUiFieldValue(pageTitleEl) : 'Daily Operations Report';
    const dateSuffix = ' <?= opr_format_date($selected_date) ?>';
    if (format === 'pdf') {
        const originalTitle = document.title;
        document.title = pageTitle + dateSuffix;
        window.print();
        document.title = originalTitle;
        return;
    }
    if (typeof XLSX === 'undefined') {
        const script = document.createElement('script');
        script.src = '../../js/vendor/xlsx.full.min.js';
        script.onload = () => doOprXlsxExport();
        document.head.appendChild(script);
    } else {
        doOprXlsxExport();
    }
}

function doOprXlsxExport() {
    const pageTitleEl = document.querySelector('[data-json-path="page_title"]');
    const pageTitle = pageTitleEl ? oprUiFieldValue(pageTitleEl) : 'Daily Operations Report';
    const companyLabel = 'Company:';
    const companyName = <?= json_encode($company_info['company'] ?? '', JSON_UNESCAPED_UNICODE) ?>;

    const todayEl = document.querySelector('[data-field="today_shift"]');
    const tomorrowEl = document.querySelector('[data-field="tomorrow_shift"]');
    const todayLabelEl = document.querySelector('[data-json-path="fields.today_shift"]');
    const tomorrowLabelEl = document.querySelector('[data-json-path="fields.tomorrow_shift"]');
    const hotelFiguresEl = document.querySelector('[data-json-path="sections.hotel_figures"]');

    const data = [
        [pageTitle + ' <?= opr_format_date($selected_date) ?>'],
        [companyLabel, companyName],
        [],
        [todayLabelEl ? oprUiFieldValue(todayLabelEl) : 'Today Shift', todayEl ? oprFieldValue(todayEl) : ''],
        [tomorrowLabelEl ? oprUiFieldValue(tomorrowLabelEl) : 'Tomorrow Shift', tomorrowEl ? oprFieldValue(tomorrowEl) : ''],
        [],
        [hotelFiguresEl ? oprUiFieldValue(hotelFiguresEl) : 'Hotel Figures & Revenue']
    ];

    document.querySelectorAll('#opr-hotel-figures-grid .opr-metric').forEach(metric => {
        let label = '';
        const uiLabelEl = metric.querySelector('[data-json-path^="fields."]');
        const customLabelCell = metric.querySelector('[data-field="field_label"]');
        if (uiLabelEl) {
            label = oprUiFieldValue(uiLabelEl);
        } else if (customLabelCell) {
            label = oprFieldValue(customLabelCell);
        } else if (metric.querySelector('label')) {
            label = metric.querySelector('label').textContent.trim();
        }
        let val = '';
        const valueCell = metric.querySelector('[data-field="field_value"]');
        if (valueCell) {
            val = oprFieldValue(valueCell);
        } else {
            val = oprFieldValue(metric);
        }
        data.push([label, val === '—' ? '' : val]);
    });

    const pushTable = (sectionPath, tableId) => {
        const titleEl = document.querySelector('[data-json-path="' + sectionPath + '"]');
        const title = titleEl ? oprUiFieldValue(titleEl) : '';
        data.push([]);
        data.push([title]);
        const table = document.getElementById(tableId);
        if (!table) return;
        const actionsLabelEl = document.querySelector('[data-json-path="controls.actions"]');
        const actionsLabel = actionsLabelEl ? oprUiFieldValue(actionsLabelEl) : 'Actions';
        const headers = Array.from(table.querySelectorAll('thead th')).map(th => oprUiFieldValue(th) || th.textContent.trim()).filter(t => t !== actionsLabel);
        data.push(headers);
        table.querySelectorAll('tbody tr').forEach(tr => {
            const row = [];
            tr.querySelectorAll('td').forEach((td, idx) => {
                if (headers.length && idx >= headers.length) return;
                let text = oprFieldValue(td);
                row.push(text === '—' ? '' : text);
            });
            if (row.length) data.push(row);
        });
    };

    pushTable('sections.fb_overview', 'opr-fb-table');
    pushTable('sections.walk_round', 'opr-walk-table');
    pushTable('sections.guest_experience', 'opr-guest-table');
    pushTable('sections.courtesy_calls', 'opr-courtesy-table');
    pushTable('sections.butler', 'opr-butler-table');
    pushTable('sections.night_shift', 'opr-night-shift-table');

    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet(data);
    const sheetEl = document.querySelector('[data-json-path="titles.export_sheet"]');
    const prefixEl = document.querySelector('[data-json-path="titles.export_filename_prefix"]');
    const sheetName = sheetEl ? oprUiFieldValue(sheetEl) : 'Ops Report';
    const filePrefix = prefixEl ? oprUiFieldValue(prefixEl) : 'Ops_Report';
    XLSX.utils.book_append_sheet(wb, ws, sheetName);
    XLSX.writeFile(wb, filePrefix + '_<?= (int)$selected_year ?>_<?= (int)$selected_month ?>_<?= (int)$selected_day ?>.xlsx');
}
</script>
</body>
</html>
