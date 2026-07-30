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

if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'planning_grid') {
    header('Content-Type: application/json; charset=utf-8');
    $anchor = $_GET['anchor'] ?? date('Y-m-d');
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $anchor)) {
        $anchor = itm_parse_date_input($anchor) ?: date('Y-m-d');
    }
    $hotelId = (int) ($_GET['hotel_id'] ?? 0);
    $typeId = (int) ($_GET['room_type_id'] ?? 0);
    $floor = trim((string) ($_GET['floor'] ?? ''));
    $days = (int) ($_GET['days'] ?? 14);
    $grid = itm_hotel_booking_planning_grid_rows($conn, $company_id, $anchor, $hotelId, $typeId, $floor, $days);
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
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $anchorInput)) {
        $parsed = itm_parse_date_input($anchorInput);
        if ($parsed) {
            $anchorDate = $parsed;
        }
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchorInput)) {
        $anchorDate = $anchorInput;
    }
}
$filterHotel = (int) ($_GET['hotel_id'] ?? 0);
$filterType = (int) ($_GET['room_type_id'] ?? 0);
$filterFloor = trim((string) ($_GET['floor'] ?? ''));
$planDays = max(7, min(21, (int) ($_GET['days'] ?? 14)));

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

$grid = itm_hotel_booking_planning_grid_rows($conn, $company_id, $anchorDate, $filterHotel, $filterType, $filterFloor, $planDays);
$bookingsByRoom = [];
foreach ($grid['bookings'] as $b) {
    $rid = (int) $b['room_id'];
    if (!isset($bookingsByRoom[$rid])) {
        $bookingsByRoom[$rid] = [];
    }
    $bookingsByRoom[$rid][] = $b;
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

$crud_title = itm_crud_apply_module_icon_to_browser_title($conn, $company_id, $employee_id, 'hotel_bookings', $crud_title);
require '../../includes/header.php';
?>
<link rel="stylesheet" href="css/hotel-bookings.css">
<?php
$dayHeaders = [];
$cursor = DateTime::createFromFormat('Y-m-d', $grid['range_start']);
for ($i = 0; $i < $planDays; $i++) {
    $dayHeaders[] = (clone $cursor)->modify('+' . $i . ' days');
}
?>
<div class="container">
<div class="main-content">
<div class="content">
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
<label>Anchor <input type="text" name="anchor" value="<?php echo sanitize(itm_format_date_display($anchorDate)); ?>" placeholder="dd/mm/yyyy"></label>
<label>Hotel
<select name="hotel_id">
<option value="0">All</option>
<?php foreach ($hotels as $h): ?>
<option value="<?php echo (int) $h['id']; ?>" <?php echo $filterHotel === (int) $h['id'] ? 'selected' : ''; ?>><?php echo sanitize($h['name']); ?></option>
<?php endforeach; ?>
</select>
</label>
<label>Days <input type="number" name="days" min="7" max="21" value="<?php echo (int) $planDays; ?>" style="width:64px"></label>
<button type="submit" class="btn btn-sm" title="Search">🔎</button>
</form>
<div class="hb-toolbar">
<a class="btn btn-sm" href="create.php" title="Quick booking">➕</a>
</div>
<div class="hb-plan-grid-wrap">
<table class="hb-plan-grid">
<thead>
<tr>
<th class="hb-plan-room-col">Room</th>
<th>HK</th>
<th>Type</th>
<?php foreach ($dayHeaders as $d): ?>
<th class="hb-plan-day"><?php echo sanitize($d->format('D d/m')); ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach ($grid['rooms'] as $room): ?>
<tr>
<td class="hb-plan-room-col"><?php echo sanitize($room['room_number']); ?></td>
<td><?php
$hkColor = $room['hk_color'] ?? '#6c757d';
?><span class="hb-hk-badge" style="background:<?php echo sanitize($hkColor); ?>"><?php echo sanitize($room['hk_name'] ?? '—'); ?></span></td>
<td><?php echo sanitize($room['type_code'] ?? $room['type_name']); ?></td>
<?php
$roomBookings = $bookingsByRoom[(int) $room['id']] ?? [];
$startTs = strtotime($grid['range_start']);
foreach ($dayHeaders as $di => $d):
    $dayYmd = $d->format('Y-m-d');
    $bar = null;
    foreach ($roomBookings as $rb) {
        if ($rb['check_in'] <= $dayYmd && $rb['check_out'] > $dayYmd) {
            $bar = $rb;
            break;
        }
    }
?>
<td class="hb-plan-day"><?php if ($bar && $dayYmd === $bar['check_in']): ?>
<span class="hb-plan-bar" title="<?php echo sanitize($bar['customer_name']); ?>"><?php echo sanitize($bar['customer_name']); ?></span>
<?php endif; ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
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
<td><?php echo sanitize(itm_format_date_display($br['check_in'])); ?></td>
<td><?php echo sanitize(itm_format_date_display($br['check_out'])); ?></td>
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
</div>
</div>
</div>
<?php require '../../includes/footer.php'; ?>
