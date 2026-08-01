<?php
/**
 * Hotel bookings hub: planning grid + future/present/history boards.
 */
$crud_table = 'hotel_bookings';
$crud_title = 'Hotel Bookings';
$crud_action = $crud_action ?? 'index';
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

$mode = isset($_GET['mode']) ? (string) $_GET['mode'] : 'planning';
if (!in_array($mode, ['planning', 'future', 'present', 'history'], true)) {
    $mode = 'planning';
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'planning_move') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'POST required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    itm_require_post_csrf();
    $entityType = (string) ($_POST['entity_type'] ?? '');
    $entityId = (int) ($_POST['entity_id'] ?? 0);
    $roomId = (int) ($_POST['room_id'] ?? 0);
    if ($entityType === 'booking') {
        $checkIn = itm_parse_date_input($_POST['check_in'] ?? '') ?: (string) ($_POST['check_in'] ?? '');
        $checkOut = itm_parse_date_input($_POST['check_out'] ?? '') ?: (string) ($_POST['check_out'] ?? '');
        $result = itm_hotel_booking_planning_move_booking($conn, $company_id, $employee_id, $entityId, $roomId, $checkIn, $checkOut);
    } elseif ($entityType === 'maintenance') {
        $fromDate = itm_parse_date_input($_POST['from_date'] ?? '') ?: (string) ($_POST['from_date'] ?? '');
        $throughDate = itm_parse_date_input($_POST['through_date'] ?? '') ?: (string) ($_POST['through_date'] ?? '');
        $result = itm_hotel_booking_planning_move_maintenance($conn, $company_id, $employee_id, $entityId, $roomId, $fromDate, $throughDate);
    } else {
        $result = ['ok' => false, 'error' => 'Invalid entity type.'];
    }
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'hk_rotate') {
    header('Content-Type: application/json; charset=utf-8');
    $roomId = (int) ($_GET['room_id'] ?? $_POST['room_id'] ?? 0);
    $result = itm_hotel_booking_rotate_room_housekeeping_status($conn, $company_id, $roomId, $employee_id);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'planning_grid') {
    header('Content-Type: application/json; charset=utf-8');
    $anchor = $_GET['anchor'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
        $anchor = itm_parse_date_input($anchor) ?: date('Y-m-d');
    }
    $hotelId = (int) ($_GET['hotel_id'] ?? 0);
    $typeId = (int) ($_GET['room_type_id'] ?? 0);
    $floor = trim((string) ($_GET['floor'] ?? ''));
    $days = (int) ($_GET['days'] ?? 14);
    $planSort = isset($_GET['plan_sort']) ? (string) $_GET['plan_sort'] : 'room';
    $planDir = isset($_GET['plan_dir']) ? (string) $_GET['plan_dir'] : 'asc';
    if (!in_array($planSort, ['room', 'hk', 'type'], true)) {
        $planSort = 'room';
    }
    if (!in_array(strtolower($planDir), ['asc', 'desc'], true)) {
        $planDir = 'asc';
    }
    $grid = itm_hotel_booking_planning_grid_rows($conn, $company_id, $anchor, $hotelId, $typeId, $floor, $days, $planSort, $planDir);
    echo json_encode($grid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hb_check_in'])) {
    itm_require_post_csrf();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $inHouseId = itm_hotel_booking_status_id_by_name($conn, $company_id, 'hotel_bookings_present', 'IN-HOUSE');
    if ($bookingId > 0 && $inHouseId) {
        $stmt = mysqli_prepare($conn, 'UPDATE hotel_bookings SET present_status_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iiii', $inHouseId, $employee_id, $bookingId, $company_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    header('Location: index.php?mode=planning&checked_in=1');
    exit;
}

$anchorInput = trim((string) ($_GET['anchor'] ?? ''));
$anchorDate = date('Y-m-d');
if ($anchorInput !== '') {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchorInput)) {
        $anchorDate = $anchorInput;
    } else {
        $parsed = itm_parse_date_input($anchorInput);
        if ($parsed) {
            $anchorDate = $parsed;
        }
    }
}
$filterHotel = (int) ($_GET['hotel_id'] ?? 0);
$filterType = (int) ($_GET['room_type_id'] ?? 0);
$filterFloor = trim((string) ($_GET['floor'] ?? ''));
$planDays = max(7, min(21, (int) ($_GET['days'] ?? 14)));
$planSort = isset($_GET['plan_sort']) ? (string) $_GET['plan_sort'] : 'room';
$planDir = isset($_GET['plan_dir']) ? (string) $_GET['plan_dir'] : 'asc';
if (!in_array($planSort, ['room', 'hk', 'type'], true)) {
    $planSort = 'room';
}
if (!in_array(strtolower($planDir), ['asc', 'desc'], true)) {
    $planDir = 'asc';
}

$hotels = [];
$hstmt = mysqli_prepare($conn, 'SELECT id, name FROM hotel_booking_hotels WHERE company_id = ? AND deleted_at IS NULL ORDER BY name');
if ($hstmt) {
    mysqli_stmt_bind_param($hstmt, 'i', $company_id);
    mysqli_stmt_execute($hstmt);
    $hr = mysqli_stmt_get_result($hstmt);
    while ($hr && ($h = mysqli_fetch_assoc($hr))) {
        $hotels[] = $h;
    }
    mysqli_stmt_close($hstmt);
}

$grid = itm_hotel_booking_planning_grid_rows($conn, $company_id, $anchorDate, $filterHotel, $filterType, $filterFloor, $planDays, $planSort, $planDir);
$bookingsByRoom = [];
foreach ($grid['bookings'] as $b) {
    $rid = (int) $b['room_id'];
    if (!isset($bookingsByRoom[$rid])) {
        $bookingsByRoom[$rid] = [];
    }
    $bookingsByRoom[$rid][] = $b;
}
$maintenanceByRoom = [];
foreach ($grid['maintenance'] ?? [] as $m) {
    $rid = (int) ($m['room_id'] ?? 0);
    if ($rid < 1) {
        continue;
    }
    if (!isset($maintenanceByRoom[$rid])) {
        $maintenanceByRoom[$rid] = [];
    }
    $maintenanceByRoom[$rid][] = $m;
}

function hb_board_list($conn, $companyId, $segment, $limit = 200) {
    $companyId = (int) $companyId;
    $col = $segment . '_status_id';
    $statusTable = itm_hotel_booking_status_table_for_segment($segment);
    $sql = "SELECT b.*, c.name AS customer_name, r.room_number, r.name AS room_name, s.name AS status_name
            FROM hotel_bookings b
            INNER JOIN customers c ON c.id = b.customer_id AND c.company_id = b.company_id
            INNER JOIN hotel_booking_rooms r ON r.id = b.room_id AND r.company_id = b.company_id
            LEFT JOIN `{$statusTable}` s ON s.id = b.{$col}
            WHERE b.company_id = ? AND b.deleted_at IS NULL
            ORDER BY b.check_in DESC LIMIT " . (int) $limit;
    $stmt = mysqli_prepare($conn, $sql);
    $rows = [];
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            if (itm_hotel_booking_resolve_segment($row['check_in'], $row['check_out']) === $segment) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
    }
    return $rows;
}

$boardRows = [];
if ($mode !== 'planning') {
    $boardRows = hb_board_list($conn, $company_id, $mode);
}

function hb_planning_filter_query($anchorDate, $filterHotel, $filterType, $filterFloor, $planDays, $planSort, $planDir) {
    $q = [
        'mode' => 'planning',
        'anchor' => itm_format_hotel_date_display($anchorDate),
        'hotel_id' => (int) $filterHotel,
        'days' => (int) $planDays,
        'plan_sort' => (string) $planSort,
        'plan_dir' => (string) $planDir,
    ];
    if ((int) $filterType > 0) {
        $q['room_type_id'] = (int) $filterType;
    }
    if ((string) $filterFloor !== '') {
        $q['floor'] = (string) $filterFloor;
    }
    return $q;
}

function hb_planning_sort_href($col, $anchorDate, $filterHotel, $filterType, $filterFloor, $planDays, $planSort, $planDir) {
    $nextDir = ($planSort === $col && strtolower($planDir) === 'asc') ? 'desc' : 'asc';
    $q = hb_planning_filter_query($anchorDate, $filterHotel, $filterType, $filterFloor, $planDays, $col, $nextDir);
    return '?' . http_build_query($q);
}

$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_bookings', $crud_title);
require_once ROOT_PATH . 'includes/itm_hospitality_admin_layout.php';
itm_hospitality_admin_layout_begin($crud_title, ['css/hotel-bookings.css']);
$dayHeaders = [];
$cursor = DateTime::createFromFormat('Y-m-d', $grid['range_start']);
for ($i = 0; $i < $planDays; $i++) {
    $dayHeaders[] = (clone $cursor)->modify('+' . $i . ' days');
}
$prevAnchorDate = date('Y-m-d', strtotime($anchorDate . ' -' . $planDays . ' days'));
$nextAnchorDate = date('Y-m-d', strtotime($anchorDate . ' +' . $planDays . ' days'));
$prevAnchorQuery = hb_planning_filter_query($prevAnchorDate, $filterHotel, $filterType, $filterFloor, $planDays, $planSort, $planDir);
$nextAnchorQuery = hb_planning_filter_query($nextAnchorDate, $filterHotel, $filterType, $filterFloor, $planDays, $planSort, $planDir);
?>
<div class="card">
<h1 title="Hotel bookings">🏨</h1>
<div class="hb-mode-tabs">
<a class="btn btn-sm <?php echo $mode === 'planning' ? 'btn-primary' : ''; ?>" href="?mode=planning" title="Room plan">Planning</a>
<a class="btn btn-sm <?php echo $mode === 'future' ? 'btn-primary' : ''; ?>" href="?mode=future" title="Future">Future</a>
<a class="btn btn-sm <?php echo $mode === 'present' ? 'btn-primary' : ''; ?>" href="?mode=present" title="Present">Present</a>
<a class="btn btn-sm <?php echo $mode === 'history' ? 'btn-primary' : ''; ?>" href="?mode=history" title="History">History</a>
<a class="btn btn-sm btn-primary" href="create.php" title="Create">➕</a>
</div>

<?php if ($mode === 'planning'): ?>
<form method="get" class="hb-plan-filters">
<input type="hidden" name="mode" value="planning">
<input type="hidden" name="plan_sort" value="<?php echo sanitize($planSort); ?>">
<input type="hidden" name="plan_dir" value="<?php echo sanitize($planDir); ?>">
<label>Anchor <?php itm_render_hotel_date_input('anchor', 'hb-plan-anchor', $anchorDate); ?></label>
<label>Hotel
<select name="hotel_id">
<option value="0">All</option>
<?php foreach ($hotels as $h): ?>
<option value="<?php echo (int) $h['id']; ?>" <?php echo $filterHotel === (int) $h['id'] ? 'selected' : ''; ?>><?php echo sanitize($h['name']); ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Days <input type="number" name="days" min="7" max="21" value="<?php echo (int) $planDays; ?>" class="hb-plan-days-input"></label>
<label class="hb-plan-search-label"><span aria-hidden="true">&nbsp;</span><button type="submit" class="btn btn-sm" title="Search">🔎</button></label>
</form>
<div class="hb-plan-grid-wrap">
<table class="hb-plan-grid">
<thead>
<tr>
<th class="hb-plan-sticky hb-plan-room-col"><a href="<?php echo sanitize(hb_planning_sort_href('room', $anchorDate, $filterHotel, $filterType, $filterFloor, $planDays, $planSort, $planDir)); ?>" class="hb-plan-sort-link" title="Sort by room">Room<?php if ($planSort === 'room'): ?> <?php echo strtolower($planDir) === 'asc' ? '▲' : '▼'; ?><?php endif; ?></a></th>
<th class="hb-plan-sticky hb-plan-hk-col"><a href="<?php echo sanitize(hb_planning_sort_href('hk', $anchorDate, $filterHotel, $filterType, $filterFloor, $planDays, $planSort, $planDir)); ?>" class="hb-plan-sort-link" title="Sort by HSK status">HSK<?php if ($planSort === 'hk'): ?> <?php echo strtolower($planDir) === 'asc' ? '▲' : '▼'; ?><?php endif; ?></a></th>
<th class="hb-plan-sticky hb-plan-type-col"><a href="<?php echo sanitize(hb_planning_sort_href('type', $anchorDate, $filterHotel, $filterType, $filterFloor, $planDays, $planSort, $planDir)); ?>" class="hb-plan-sort-link" title="Sort by room type">Type<?php if ($planSort === 'type'): ?> <?php echo strtolower($planDir) === 'asc' ? '▲' : '▼'; ?><?php endif; ?></a></th>
<th class="hb-plan-date-nav" title="Previous dates"><a class="btn btn-sm hb-plan-date-arrow" href="?<?php echo sanitize(http_build_query($prevAnchorQuery)); ?>" title="Previous dates">⬅️</a></th>
<?php foreach ($dayHeaders as $d): ?>
<th class="hb-plan-day"><?php echo sanitize($d->format('D ') . itm_format_hotel_date_display($d->format('Y-m-d'))); ?></th>
<?php endforeach; ?>
<th class="hb-plan-date-nav" title="Next dates"><a class="btn btn-sm hb-plan-date-arrow" href="?<?php echo sanitize(http_build_query($nextAnchorQuery)); ?>" title="Next dates">➡️</a></th>
</tr>
</thead>
<tbody>
<?php foreach ($grid['rooms'] as $room): ?>
<tr data-room-id="<?php echo (int) $room['id']; ?>">
<td class="hb-plan-sticky hb-plan-room-col"><?php echo sanitize($room['room_number']); ?></td>
<td class="hb-plan-sticky hb-plan-hk-col hb-plan-hk-cell" title="Double-click to rotate HSK status"><?php
$hkColor = $room['hk_color'] ?? '#6c757d';
$hkLabel = trim((string) ($room['hk_code'] ?? ''));
if ($hkLabel === '') {
    $hkLabel = (string) ($room['hk_name'] ?? '—');
}
?><span class="hb-hk-badge" style="background:<?php echo sanitize($hkColor); ?>"><?php echo sanitize($hkLabel); ?></span></td>
<td class="hb-plan-sticky hb-plan-type-col"><?php echo sanitize($room['type_code'] ?? $room['type_name']); ?></td>
<td class="hb-plan-date-nav hb-plan-date-spacer"></td>
<?php
$roomBookings = $bookingsByRoom[(int) $room['id']] ?? [];
foreach ($dayHeaders as $di => $d):
    $dayYmd = $d->format('Y-m-d');
    $dayMaintenance = itm_hotel_booking_planning_match_maintenance_for_day($maintenanceByRoom[(int) $room['id']] ?? [], $dayYmd);
    $dayBookings = itm_hotel_booking_planning_match_bookings_for_day($roomBookings, $dayYmd);
?>
<td class="hb-plan-day hb-plan-day-cell" data-day-ymd="<?php echo sanitize($dayYmd); ?>"><?php
foreach ($dayMaintenance as $maint):
    $maintCode = strtoupper(trim((string) ($maint['maintenance_status_code'] ?? '')));
    $maintLabel = $maintCode !== '' ? $maintCode : (string) ($maint['maintenance_status_name'] ?? 'Maint');
    $maintColor = itm_hotel_booking_planning_maintenance_bar_color($maint['maintenance_status_code'] ?? '');
    $maintTitle = sanitize($maintLabel . ' — ' . itm_format_hotel_date_display($maint['from_date']) . ' to ' . itm_format_hotel_date_display($maint['through_date']) . ' — double-click to edit, drag to move');
?>
<span class="hb-plan-bar hb-plan-bar-segment-middle hb-plan-maint hb-plan-draggable" draggable="true" style="background:<?php echo sanitize($maintColor); ?>;z-index:0" data-entity-type="maintenance" data-maintenance-id="<?php echo (int) $maint['id']; ?>" data-from-date="<?php echo sanitize((string) $maint['from_date']); ?>" data-through-date="<?php echo sanitize((string) $maint['through_date']); ?>" data-room-id="<?php echo (int) $room['id']; ?>" title="<?php echo $maintTitle; ?>"><?php echo sanitize($maintLabel); ?></span>
<?php endforeach; ?>
<?php foreach ($dayBookings as $match):
    $bar = $match['booking'];
    $segmentClass = $match['segment_class'];
    $barColor = itm_hotel_booking_planning_booking_bar_color((int) ($bar['id'] ?? 0), $bar['booking_color'] ?? '');
    $barTitle = sanitize($bar['customer_name'] . ' — double-click to view, drag to move');
?>
<span class="hb-plan-bar hb-plan-draggable <?php echo sanitize($segmentClass); ?>" draggable="true" style="background:<?php echo sanitize($barColor); ?>;z-index:1" data-entity-type="booking" data-booking-id="<?php echo (int) $bar['id']; ?>" data-check-in="<?php echo sanitize((string) $bar['check_in']); ?>" data-check-out="<?php echo sanitize((string) $bar['check_out']); ?>" data-room-id="<?php echo (int) $room['id']; ?>" title="<?php echo $barTitle; ?>"><?php echo sanitize($bar['customer_name']); ?></span>
<?php endforeach; ?></td>
<?php endforeach; ?>
<td class="hb-plan-date-nav hb-plan-date-spacer"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div id="hb-hk-maint-modal" class="hb-modal-backdrop" hidden role="dialog" aria-modal="true" aria-labelledby="hb-hk-maint-modal-title">
<div class="hb-modal hb-plan-maint-modal">
<div class="hb-plan-maint-modal-head">
<h2 id="hb-hk-maint-modal-title" title="HSK Maintenance">✏️</h2>
<button type="button" class="btn btn-sm" data-hb-maint-modal-close title="Close">✖</button>
</div>
<iframe id="hb-hk-maint-modal-frame" class="hb-plan-maint-modal-frame" title="HSK Maintenance edit" src="about:blank"></iframe>
</div>
</div>
<?php else: ?>
<table class="hb-board-table table">
<thead><tr>
<th>Customer</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Payment</th><th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($boardRows as $br): ?>
<tr>
<td><?php echo sanitize($br['customer_name']); ?></td>
<td><?php echo sanitize($br['room_number'] . ' ' . $br['room_name']); ?></td>
<td><?php echo sanitize(itm_format_hotel_date_display($br['check_in'])); ?></td>
<td><?php echo sanitize(itm_format_hotel_date_display($br['check_out'])); ?></td>
<td><?php echo sanitize($br['status_name'] ?? '—'); ?></td>
<td><?php echo sanitize(number_format((float) $br['payment_amount'], 2)); ?></td>
<td class="itm-actions-cell" data-itm-actions-origin="1">
<a class="btn btn-sm" href="view.php?id=<?php echo (int) $br['id']; ?>" title="View">🔎</a>
<a class="btn btn-sm" href="edit.php?id=<?php echo (int) $br['id']; ?>" title="Edit">✏️</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
<?php if ($mode === 'planning'): ?>
<script>
window.HB_PLANNING_DND = <?php echo json_encode([
    'csrf' => itm_get_csrf_token(),
    'hkMaintEditBase' => '../hotel_booking_housekeeping_maintenance/edit.php',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php endif; ?>
<?php
$layoutEndScripts = $mode === 'planning' ? ['js/hotel-date-input.js', 'js/hotel-bookings-planning.js'] : [];
itm_hospitality_admin_layout_end($layoutEndScripts);
