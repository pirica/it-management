<?php
/**
 * Calendar Module - Index
 *
 * Integrated calendar view showing Events, Ticket Deadlines, and Equipment Certificate Expiries.
 */

require '../../config/config.php';

$current_date_param = $_GET['date'] ?? date('Y-m-d');
$current_time = strtotime($current_date_param);
if (!$current_time) {
    $current_time = time();
    $current_date_param = date('Y-m-d');
}

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m', $current_time);
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y', $current_time);

// Month navigation logic
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

$month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
$days_in_month = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
$first_day_of_month = (int)date('N', mktime(0, 0, 0, $month, 1, $year)); // 1 (Mon) to 7 (Sun)

// Fetch Events for the month
$events = [];
$start_range = "$year-$month-01";
$end_range = "$year-$month-$days_in_month";

$sql_events = "SELECT e.*, ec.name as category_name, ec.color as category_color
               FROM events e
               LEFT JOIN event_categories ec ON e.category_id = ec.id
               WHERE e.company_id = ? AND e.active = 1
               AND NOT (DATE(COALESCE(e.end_datetime, e.start_datetime)) < ? OR DATE(e.start_datetime) > ?)";

$stmt = mysqli_prepare($conn, $sql_events);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iss', $company_id, $start_range, $end_range);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $start_dt = strtotime($row['start_datetime']);
        $end_dt = $row['end_datetime'] ? strtotime($row['end_datetime']) : $start_dt;

        $curr = $start_dt;
        while ($curr <= $end_dt) {
            $d = date('Y-m-d', $curr);
            // Only add if within the current month view
            if ($d >= $start_range && $d <= $end_range) {
                $events[$d][] = [
                    'type' => 'event',
                    'title' => $row['title'],
                    'color' => $row['category_color'] ?: '#3b82f6',
                    'icon' => '📅',
                    'data' => $row
                ];
            }
            $curr = strtotime('+1 day', $curr);
        }
    }
    mysqli_stmt_close($stmt);
}

// Fetch Tickets (Tasks) for the month
$sql_tickets = "SELECT t.id, t.title, t.due_date, ts.color as status_color, tp.color as priority_color
               FROM tickets t
               LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
               LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
               WHERE t.company_id = ? AND t.due_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql_tickets);
if ($stmt) {
    $start_range = "$year-$month-01";
    $end_range = "$year-$month-$days_in_month";
    mysqli_stmt_bind_param($stmt, 'iss', $company_id, $start_range, $end_range);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $d = $row['due_date'];
        // Use priority color if available, else status color, else default red
        $c = $row['priority_color'] ?: ($row['status_color'] ?: '#ef4444');
        $events[$d][] = [
            'type' => 'ticket',
            'title' => "Ticket: " . $row['title'],
            'color' => $c,
            'icon' => '🎟️',
            'id' => $row['id']
        ];
    }
    mysqli_stmt_close($stmt);
}

// Fetch Equipment Certificate Expiries
$sql_equip = "SELECT id, name, certificate_expiry FROM equipment WHERE company_id = ? AND certificate_expiry BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql_equip);
if ($stmt) {
    $start_range = "$year-$month-01";
    $end_range = "$year-$month-$days_in_month";
    mysqli_stmt_bind_param($stmt, 'iss', $company_id, $start_range, $end_range);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $d = $row['certificate_expiry'];
        $events[$d][] = [
            'type' => 'equipment',
            'title' => "Cert. Expiry: " . $row['name'],
            'color' => '#f59e0b',
            'icon' => '📜',
            'id' => $row['id']
        ];
    }
    mysqli_stmt_close($stmt);
}

// Data for the side panel (selected day)
$selected_day_events = $events[$current_date_param] ?? [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .calendar-container {
            display: flex;
            gap: 20px;
            height: calc(100vh - 150px);
        }
        .calendar-side-panel {
            width: 300px;
            background: var(--card-bg, #0d1117);
            border: 1px solid var(--border-color, #30363d);
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .calendar-main {
            flex: 1;
            background: var(--card-bg, #0d1117);
            border: 1px solid var(--border-color, #30363d);
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            flex: 1;
            border-top: 1px solid var(--border-color, #30363d);
            border-left: 1px solid var(--border-color, #30363d);
        }
        .calendar-day-head {
            padding: 10px;
            text-align: center;
            font-weight: bold;
            border-right: 1px solid var(--border-color, #30363d);
            border-bottom: 1px solid var(--border-color, #30363d);
            background: var(--header-bg, #161b22);
        }
        .calendar-day {
            min-height: 80px;
            padding: 5px;
            border-right: 1px solid var(--border-color, #30363d);
            border-bottom: 1px solid var(--border-color, #30363d);
            position: relative;
            cursor: pointer;
            transition: background 0.2s;
        }
        .calendar-day:hover {
            background: rgba(255,255,255,0.05);
        }
        .calendar-day.today {
            background: rgba(59, 130, 246, 0.1);
        }
        .calendar-day.selected {
            border: 2px solid #3b82f6;
            margin: -1px;
            z-index: 1;
        }
        .calendar-day.other-month {
            opacity: 0.3;
        }
        .day-number {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }
        .event-dot-container {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
        }
        .event-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        .side-panel-date {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .side-panel-events-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .side-event-item {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.03);
            border-left: 4px solid #3b82f6;
        }
        .empty-state {
            text-align: center;
            margin-top: 50px;
            color: #8b949e;
        }
        .calendar-nav-btn {
            background: none;
            border: 1px solid var(--border-color, #30363d);
            color: var(--text-color, #c9d1d9);
            padding: 5px 15px;
            border-radius: 6px;
            cursor: pointer;
        }
        .calendar-nav-btn:hover {
            background: var(--btn-hover-bg, #30363d);
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div class="calendar-container">
                <!-- Side Panel -->
                <div class="calendar-side-panel">
                    <div class="side-panel-date"><?php echo date('l, d F', strtotime($current_date_param)); ?></div>
                    <p style="color: #8b949e; font-size: 0.9rem; margin-bottom: 20px;">
                        <?php echo count($selected_day_events); ?> events scheduled for the day
                    </p>

                    <div class="side-panel-events-list">
                        <?php if ($selected_day_events): ?>
                            <?php foreach ($selected_day_events as $ev): ?>
                                <div class="side-event-item" style="border-left-color: <?php echo sanitize($ev['color']); ?>;">
                                    <div style="font-weight: bold;"><?php echo sanitize($ev['icon']); ?> <?php echo sanitize($ev['title']); ?></div>
                                    <?php if ($ev['type'] === 'event' && !empty($ev['data']['description'])): ?>
                                        <div style="font-size: 0.85rem; margin-top: 5px; opacity: 0.8;"><?php echo sanitize($ev['data']['description']); ?></div>
                                    <?php endif; ?>
                                    <div style="margin-top: 8px;">
                                        <?php if ($ev['type'] === 'event'): ?>
                                            <a href="../events/view.php?id=<?php echo $ev['data']['id']; ?>" class="btn btn-sm">View</a>
                                        <?php elseif ($ev['type'] === 'ticket'): ?>
                                            <a href="../tickets/view.php?id=<?php echo $ev['id']; ?>" class="btn btn-sm">View Ticket</a>
                                        <?php elseif ($ev['type'] === 'equipment'): ?>
                                            <a href="../equipment/view.php?id=<?php echo $ev['id']; ?>" class="btn btn-sm">View Asset</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div style="font-size: 3rem; margin-bottom: 10px;">🕒</div>
                                <p>There are no events on this day</p>
                                <a href="../events/create.php?start_date=<?php echo $current_date_param; ?>" class="btn btn-primary" style="margin-top: 10px;">Add a new event</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Main Calendar -->
                <div class="calendar-main">
                    <div class="calendar-header">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>&date=<?php echo $current_date_param; ?>" class="calendar-nav-btn">&lt;</a>
                            <a href="?date=<?php echo date('Y-m-d'); ?>" class="calendar-nav-btn">Today</a>
                            <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>&date=<?php echo $current_date_param; ?>" class="calendar-nav-btn">&gt;</a>
                        </div>
                        <div style="text-align: right;">
                            <h2 style="margin: 0;"><?php echo $month_name . ' ' . $year; ?></h2>
                            <p style="margin: 0; font-size: 0.85rem; color: #8b949e;">View monthly events and tasks</p>
                        </div>
                    </div>

                    <div class="calendar-grid">
                        <div class="calendar-day-head">Monday</div>
                        <div class="calendar-day-head">Tuesday</div>
                        <div class="calendar-day-head">Wednesday</div>
                        <div class="calendar-day-head">Thursday</div>
                        <div class="calendar-day-head">Friday</div>
                        <div class="calendar-day-head">Saturday</div>
                        <div class="calendar-day-head">Sunday</div>

                        <?php
                        // Empty cells before the first day
                        $prev_month_days = (int)date('t', mktime(0, 0, 0, $prev_month, 1, $prev_year));
                        for ($i = 1; $i < $first_day_of_month; $i++) {
                            $day_num = $prev_month_days - ($first_day_of_month - $i - 1);
                            echo '<div class="calendar-day other-month"><span class="day-number">' . $day_num . '</span></div>';
                        }

                        // Days of the current month
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date_string = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            $is_today = ($date_string === date('Y-m-d'));
                            $is_selected = ($date_string === $current_date_param);
                            $day_events = $events[$date_string] ?? [];

                            $classes = 'calendar-day';
                            if ($is_today) $classes .= ' today';
                            if ($is_selected) $classes .= ' selected';

                            echo '<div class="' . $classes . '" onclick="location.href=\'?month=' . $month . '&year=' . $year . '&date=' . $date_string . '\'">';
                            echo '<span class="day-number">' . $day . '</span>';
                            echo '<div class="event-dot-container">';
                            foreach ($day_events as $ev) {
                                echo '<div class="event-dot" style="background-color: ' . $ev['color'] . ';" title="' . sanitize($ev['title']) . '"></div>';
                            }
                            echo '</div>';
                            echo '</div>';
                        }

                        // Empty cells after the last day
                        $last_day_of_month = date('N', mktime(0, 0, 0, $month, $days_in_month, $year));
                        for ($i = 1; $i <= (7 - $last_day_of_month); $i++) {
                            echo '<div class="calendar-day other-month"><span class="day-number">' . $i . '</span></div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
