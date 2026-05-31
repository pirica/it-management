<?php
/**
 * Calendar Module - Index
 *
 * Integrated calendar view showing Events, Ticket Deadlines, Equipment Certificate Expiries,
 * and Patches Updates.
 *
 * Supports Day, Week, Month, and Year views.
 */

require '../../config/config.php';

// Handle ICS Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ics_file'])) {
    itm_require_post_csrf();
    $file = $_FILES['ics_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($file['tmp_name']);
        // Basic ICS parser
        preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $content, $matches);
        $importedCount = 0;
        foreach ($matches[0] as $vevent) {
            preg_match('/SUMMARY:(.*)/', $vevent, $summaryMatch);
            preg_match('/DESCRIPTION:(.*)/', $vevent, $descMatch);
            preg_match('/DTSTART:(.*)/', $vevent, $startMatch);
            preg_match('/DTEND:(.*)/', $vevent, $endMatch);
            preg_match('/LOCATION:(.*)/', $vevent, $locMatch);

            $title = trim($summaryMatch[1] ?? 'Imported Event');
            $description = trim($descMatch[1] ?? '');
            $location = trim($locMatch[1] ?? '');

            $start_raw = trim($startMatch[1] ?? '');
            $end_raw = trim($endMatch[1] ?? '');

            // Convert ICS date (YYYYMMDDTHHMMSSZ or YYYYMMDD) to MySQL datetime
            $format_date = function($raw) {
                if (!$raw) return null;
                $raw = preg_replace('/[^0-9T]/', '', $raw);
                if (strlen($raw) >= 8) {
                    $y = substr($raw, 0, 4);
                    $m = substr($raw, 4, 2);
                    $d = substr($raw, 6, 2);
                    $date = "$y-$m-$d";
                    if (strpos($raw, 'T') !== false) {
                        $h = substr($raw, 9, 2) ?: '00';
                        $min = substr($raw, 11, 2) ?: '00';
                        $s = substr($raw, 13, 2) ?: '00';
                        return "$date $h:$min:$s";
                    }
                    return "$date 00:00:00";
                }
                return null;
            };

            $start_dt = $format_date($start_raw);
            $end_dt = $format_date($end_raw);

            if ($start_dt) {
                $sql = "INSERT INTO events (company_id, title, description, start_datetime, end_datetime, location, active) VALUES (?, ?, ?, ?, ?, ?, 1)";
                $stmt = mysqli_prepare($conn, $sql);
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'isssss', $company_id, $title, $description, $start_dt, $end_dt, $location);
                    if (mysqli_stmt_execute($stmt)) {
                        $importedCount++;
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
        $_SESSION['calendar_success'] = "Successfully imported $importedCount events.";
    } else {
        $_SESSION['calendar_error'] = "Failed to upload file.";
    }
    header("Location: index.php");
    exit;
}

$view = $_GET['view'] ?? 'month';

// Handle ICS Export
if (isset($_GET['export']) && $_GET['export'] === 'ics') {
    $start_export = date('Y-m-01', strtotime("-1 year"));
    $end_export = date('Y-m-t', strtotime("+1 year"));

    $sql_export = "SELECT e.* FROM events e WHERE e.company_id = ? AND e.active = 1
                   AND e.start_datetime BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $sql_export);
    $ics_content = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPROID:-//IT Management System//Calendar//EN\r\n";
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iss', $company_id, $start_export, $end_export);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $ics_content .= "BEGIN:VEVENT\r\n";
            $ics_content .= "UID:" . $row['id'] . "@it-management\r\n";
            $ics_content .= "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";
            $ics_content .= "DTSTART:" . date('Ymd\THis', strtotime($row['start_datetime'])) . "\r\n";
            if ($row['end_datetime']) {
                $ics_content .= "DTEND:" . date('Ymd\THis', strtotime($row['end_datetime'])) . "\r\n";
            }
            $ics_content .= "SUMMARY:" . str_replace("\r\n", " ", $row['title']) . "\r\n";
            if ($row['description']) {
                $ics_content .= "DESCRIPTION:" . str_replace("\r\n", "\\n", $row['description']) . "\r\n";
            }
            if ($row['location']) {
                $ics_content .= "LOCATION:" . $row['location'] . "\r\n";
            }
            $ics_content .= "END:VEVENT\r\n";
        }
        mysqli_stmt_close($stmt);
    }
    $ics_content .= "END:VCALENDAR";

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="calendar_export_' . date('Ymd') . '.ics"');
    echo $ics_content;
    exit;
}

$current_date_param = $_GET['date'] ?? date('Y-m-d');
$current_time = strtotime($current_date_param);
if (!$current_time) {
    $current_time = time();
    $current_date_param = date('Y-m-d');
}

$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m', $current_time);
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y', $current_time);

// Calculate date range for fetching data
$start_range = '';
$end_range = '';

if ($view === 'day') {
    $start_range = date('Y-m-d', $current_time);
    $end_range = $start_range;
} elseif ($view === 'week') {
    // Week starts on Monday
    $day_of_week = date('N', $current_time);
    $start_time = strtotime("-" . ($day_of_week - 1) . " days", $current_time);
    $start_range = date('Y-m-d', $start_time);
    $end_range = date('Y-m-d', strtotime("+6 days", $start_time));
} elseif ($view === 'year') {
    $start_range = "$year-01-01";
    $end_range = "$year-12-31";
} else {
    // Month view
    $view = 'month';
    $days_in_month = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
    $start_range = "$year-" . sprintf('%02d', $month) . "-01";
    $end_range = "$year-" . sprintf('%02d', $month) . "-$days_in_month";
}

// Fetch all events, tickets, certificates, and patches in range
$events_data = [];

// Events
$sql_events = "SELECT e.*, ec.name as category_name, ec.color as category_color
               FROM events e
               LEFT JOIN event_categories ec ON e.category_id = ec.id
               WHERE e.company_id = ? AND (e.active = 1 OR e.active IS NULL)
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
        // For events spanning multiple days
        $loop_end = strtotime(date('Y-m-d', $end_dt));
        $curr_day = strtotime(date('Y-m-d', $start_dt));

        while ($curr_day <= $loop_end) {
            $d = date('Y-m-d', $curr_day);
            if ($d >= $start_range && $d <= $end_range) {
                $events_data[$d][] = [
                    'type' => 'event',
                    'title' => $row['title'],
                    'color' => $row['category_color'] ?: '#3b82f6',
                    'icon' => '📅',
                    'start' => $row['start_datetime'],
                    'end' => $row['end_datetime'],
                    'id' => $row['id'],
                    'data' => $row
                ];
            }
            $curr_day = strtotime('+1 day', $curr_day);
        }
    }
    mysqli_stmt_close($stmt);
}

// Tickets
$sql_tickets = "SELECT t.id, t.title, t.due_date, ts.color as status_color, tp.color as priority_color
               FROM tickets t
               LEFT JOIN ticket_statuses ts ON t.status_id = ts.id
               LEFT JOIN ticket_priorities tp ON t.priority_id = tp.id
               WHERE t.company_id = ? AND t.due_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql_tickets);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iss', $company_id, $start_range, $end_range);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $d = $row['due_date'];
        $c = $row['priority_color'] ?: ($row['status_color'] ?: '#ef4444');
        $events_data[$d][] = [
            'type' => 'ticket',
            'title' => "Ticket: " . $row['title'],
            'color' => $c,
            'icon' => '🎟️',
            'id' => $row['id']
        ];
    }
    mysqli_stmt_close($stmt);
}

// Equipment Certificates
$sql_equip = "SELECT id, name, certificate_expiry FROM equipment WHERE company_id = ? AND certificate_expiry BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql_equip);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iss', $company_id, $start_range, $end_range);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $d = $row['certificate_expiry'];
        $events_data[$d][] = [
            'type' => 'equipment',
            'title' => "Cert. Expiry: " . $row['name'],
            'color' => '#f59e0b',
            'icon' => '📜',
            'id' => $row['id']
        ];
    }
    mysqli_stmt_close($stmt);
}

// Patches Updates
$sql_patches = "SELECT id, hostname, due_date FROM patches_updates WHERE company_id = ? AND due_date BETWEEN ? AND ?";
$stmt = mysqli_prepare($conn, $sql_patches);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'iss', $company_id, $start_range, $end_range);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $d = $row['due_date'];
        $events_data[$d][] = [
            'type' => 'patch',
            'title' => "Patch: " . ($row['hostname'] ?: 'Unspecified Host'),
            'color' => '#8b5cf6',
            'icon' => '🛠️',
            'id' => $row['id']
        ];
    }
    mysqli_stmt_close($stmt);
}

// Data for side panel (selected day)
$selected_day_events = $events_data[$current_date_param] ?? [];

// Navigation Logic
$prev_month_time = mktime(0, 0, 0, $month - 1, 1, $year);
$next_month_time = mktime(0, 0, 0, $month + 1, 1, $year);

$prev_month_num = date('n', $prev_month_time);
$prev_year_num = date('Y', $prev_month_time);
$next_month_num = date('n', $next_month_time);
$next_year_num = date('Y', $next_month_time);

if ($view === 'day') {
    $prev_link = "view=day&date=" . date('Y-m-d', strtotime('-1 day', $current_time)) . "&month=$month&year=$year";
    $next_link = "view=day&date=" . date('Y-m-d', strtotime('+1 day', $current_time)) . "&month=$month&year=$year";
} elseif ($view === 'week') {
    $prev_link = "view=week&date=" . date('Y-m-d', strtotime('-7 days', $current_time)) . "&month=$month&year=$year";
    $next_link = "view=week&date=" . date('Y-m-d', strtotime('+7 days', $current_time)) . "&month=$month&year=$year";
} elseif ($view === 'year') {
    $prev_link = "view=year&year=" . ($year - 1) . "&date=$current_date_param";
    $next_link = "view=year&year=" . ($year + 1) . "&date=$current_date_param";
} else {
    $prev_link = "month=$prev_month_num&year=$prev_year_num&date=$current_date_param";
    $next_link = "month=$next_month_num&year=$next_year_num&date=$current_date_param";
}

$today_link = "date=" . date('Y-m-d') . "&month=" . date('m') . "&year=" . date('Y');

$errors = [];
if (isset($_SESSION['calendar_error'])) {
    $errors[] = $_SESSION['calendar_error'];
    unset($_SESSION['calendar_error']);
}
$success = $_SESSION['calendar_success'] ?? null;
unset($_SESSION['calendar_success']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .calendar-container { display: flex; gap: 20px; height: calc(100vh - 150px); }
        .calendar-side-panel { width: 320px; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 20px; display: flex; flex-direction: column; overflow-y: auto; }
        .calendar-main { flex: 1; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 20px; display: flex; flex-direction: column; overflow: hidden; }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .calendar-view-selector { display: flex; gap: 5px; background: var(--bg-secondary); padding: 4px; border-radius: 6px; }
        .calendar-view-selector a { padding: 4px 12px; border-radius: 4px; text-decoration: none; color: var(--text-primary); font-size: 0.9rem; }
        .calendar-view-selector a.active { background: var(--accent); color: white; }

        .calendar-grid-wrapper { flex: 1; overflow-y: auto; position: relative; }
        .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); border-top: 1px solid var(--border); border-left: 1px solid var(--border); }
        .calendar-day-head { padding: 10px; text-align: center; font-weight: bold; border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); background: var(--bg-secondary); color: var(--text-primary); }
        .calendar-day { min-height: 100px; padding: 5px; border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); position: relative; cursor: pointer; transition: background 0.2s; color: var(--text-primary); }
        .calendar-day:hover { background: rgba(0,0,0,0.02); }
        [data-theme="dark"] .calendar-day:hover { background: rgba(255,255,255,0.05); }
        .calendar-day.today { background: rgba(9, 105, 218, 0.1); }
        .calendar-day.selected { border: 2px solid var(--accent); margin: -1px; z-index: 1; }
        .calendar-day.other-month { opacity: 0.3; }
        .day-number { font-weight: bold; margin-bottom: 5px; display: block; }
        .event-dot-container { display: flex; flex-wrap: wrap; gap: 2px; }
        .event-dot { width: 6px; height: 6px; border-radius: 50%; }

        /* Day/Week Time Grid */
        .time-grid-container { display: grid; grid-template-columns: 60px 1fr; border-top: 1px solid var(--border); }
        .week-time-grid { grid-template-columns: 60px repeat(7, 1fr); }
        .time-label { padding: 10px 5px; border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); font-size: 0.75rem; color: var(--text-secondary); text-align: right; }
        .time-slot { border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); position: relative; min-height: 50px; }
        .time-event { position: absolute; left: 2px; right: 2px; border-radius: 4px; padding: 2px 5px; font-size: 0.75rem; color: white; overflow: hidden; z-index: 2; border: 1px solid rgba(0,0,0,0.1); cursor: pointer; }

        /* Year View */
        .year-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .mini-month { border: 1px solid var(--border); border-radius: 8px; padding: 10px; background: var(--bg-primary); }
        .mini-month-title { font-weight: bold; margin-bottom: 10px; text-align: center; color: var(--text-primary); }
        .mini-grid { display: grid; grid-template-columns: repeat(7, 1fr); font-size: 0.7rem; }
        .mini-day { text-align: center; padding: 2px; aspect-ratio: 1; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-primary); }
        .mini-day.has-events { background: var(--accent); color: white; }
        .mini-day:hover { background: var(--bg-tertiary); }

        .side-panel-date { font-size: 1.2rem; font-weight: bold; margin-bottom: 5px; color: var(--text-primary); }
        .side-event-item { padding: 10px; border-radius: 6px; margin-bottom: 10px; background: var(--bg-secondary); border-left: 4px solid var(--accent); color: var(--text-primary); }
        .calendar-nav-btn { background: var(--bg-primary); border: 1px solid var(--border); color: var(--text-primary); padding: 5px 15px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.9rem; }
        .calendar-nav-btn:hover { background: var(--bg-secondary); }

        .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem; }
        .alert-success { background: rgba(63, 185, 80, 0.15); border: 1px solid var(--success); color: var(--success); }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo sanitize($success); ?></div>
            <?php endif; ?>

            <div class="calendar-container">
                <!-- Side Panel -->
                <div class="calendar-side-panel">
                    <div style="margin-bottom: 20px;">
                        <a href="../events/create.php?start_date=<?php echo $current_date_param; ?>" class="btn btn-primary w-100">Add a new event</a>
                    </div>

                    <div class="side-panel-date"><?php echo date('l, d F', strtotime($current_date_param)); ?></div>
                    <p style="color: #8b949e; font-size: 0.85rem; margin-bottom: 15px;">
                        <?php echo count($selected_day_events); ?> items scheduled
                    </p>

                    <div class="side-panel-events-list">
                        <?php if ($selected_day_events): ?>
                            <?php foreach ($selected_day_events as $ev): ?>
                                <div class="side-event-item" style="border-left-color: <?php echo sanitize($ev['color']); ?>;">
                                    <div style="font-weight: bold;"><?php echo sanitize($ev['icon']); ?> <?php echo sanitize($ev['title']); ?></div>
                                    <?php if ($ev['type'] === 'event'): ?>
                                        <div style="font-size: 0.75rem; opacity: 0.7; margin-top: 2px;">
                                            <?php echo date('H:i', strtotime($ev['start'])); ?> - <?php echo $ev['end'] ? date('H:i', strtotime($ev['end'])) : 'All day'; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="margin-top: 8px;">
                                        <?php if ($ev['type'] === 'event'): ?>
                                            <a href="../events/view.php?id=<?php echo $ev['id']; ?>" class="btn btn-sm">View</a>
                                        <?php elseif ($ev['type'] === 'ticket'): ?>
                                            <a href="../tickets/view.php?id=<?php echo $ev['id']; ?>" class="btn btn-sm">Ticket</a>
                                        <?php elseif ($ev['type'] === 'equipment'): ?>
                                            <a href="../equipment/view.php?id=<?php echo $ev['id']; ?>" class="btn btn-sm">Asset</a>
                                        <?php elseif ($ev['type'] === 'patch'): ?>
                                            <a href="../patches_updates/view.php?id=<?php echo $ev['id']; ?>" class="btn btn-sm">Patch</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding: 40px 0; color: #8b949e;">
                                <div style="font-size: 2.5rem; margin-bottom: 10px;">🕒</div>
                                <p>Nothing scheduled</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: auto; border-top: 1px solid var(--border); padding-top: 20px;">
                        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h4 style="margin: 0; font-size: 0.9rem;">📅 ICS Tools</h4>
                            <a href="?export=ics" class="btn btn-sm" title="Export to ICS">📤 Export</a>
                        </div>
                        <h4 style="margin-bottom: 10px; font-size: 0.8rem; opacity: 0.8;">📥 Import (.ics)</h4>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo itm_get_csrf_token(); ?>">
                            <input type="file" name="ics_file" accept=".ics" required style="font-size: 0.8rem; margin-bottom: 10px; width: 100%; border: 1px solid var(--border); padding: 5px; border-radius: 4px; background: var(--bg-primary); color: var(--text-primary);">
                            <button type="submit" class="btn btn-sm w-100">Import Events</button>
                        </form>
                    </div>
                </div>

                <!-- Main Calendar -->
                <div class="calendar-main">
                    <div class="calendar-header">
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <a href="?<?php echo $prev_link; ?>" class="calendar-nav-btn" title="🔗 <">&lt;</a>
                            <a href="?<?php echo $today_link; ?>" class="calendar-nav-btn" title="🔗 Today">Today</a>
                            <a href="?<?php echo $next_link; ?>" class="calendar-nav-btn" title="🔗 >">&gt;</a>
                        </div>

                        <div class="calendar-view-selector">
                            <a href="?view=day&date=<?php echo $current_date_param; ?>" class="<?php echo $view === 'day' ? 'active' : ''; ?>">Day</a>
                            <a href="?view=week&date=<?php echo $current_date_param; ?>" class="<?php echo $view === 'week' ? 'active' : ''; ?>">Week</a>
                            <a href="?view=month&date=<?php echo $current_date_param; ?>" class="<?php echo $view === 'month' ? 'active' : ''; ?>">Month</a>
                            <a href="?view=year&date=<?php echo $current_date_param; ?>" class="<?php echo $view === 'year' ? 'active' : ''; ?>">Year</a>
                        </div>

                        <div style="text-align: right;">
                            <?php if ($view === 'year'): ?>
                                <h2 style="margin: 0;"><?php echo $year; ?></h2>
                            <?php elseif ($view === 'day'): ?>
                                <h2 style="margin: 0;"><?php echo date('d F Y', $current_time); ?></h2>
                            <?php else: ?>
                                <h2 style="margin: 0;"><?php echo date('F Y', $current_time); ?></h2>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="calendar-grid-wrapper">
                        <?php if ($view === 'month'): ?>
                            <div class="calendar-grid">
                                <div class="calendar-day-head">Mon</div><div class="calendar-day-head">Tue</div><div class="calendar-day-head">Wed</div>
                                <div class="calendar-day-head">Thu</div><div class="calendar-day-head">Fri</div><div class="calendar-day-head">Sat</div><div class="calendar-day-head">Sun</div>
                                <?php
                                $first_day = (int)date('N', mktime(0, 0, 0, $month, 1, $year));
                                $days_prev = (int)date('t', mktime(0, 0, 0, $month - 1, 1, $year));
                                for ($i = 1; $i < $first_day; $i++) {
                                    echo '<div class="calendar-day other-month"><span class="day-number">' . ($days_prev - $first_day + $i + 1) . '</span></div>';
                                }
                                for ($day = 1; $day <= $days_in_month; $day++) {
                                    $ds = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    $cls = 'calendar-day' . ($ds === date('Y-m-d') ? ' today' : '') . ($ds === $current_date_param ? ' selected' : '');
                                    echo '<div class="' . $cls . '" onclick="location.href=\'?view=month&month=' . $month . '&year=' . $year . '&date=' . $ds . '\'">';
                                    echo '<span class="day-number">' . $day . '</span>';
                                    echo '<div class="event-dot-container">';
                                    foreach ($events_data[$ds] ?? [] as $ev) { echo '<div class="event-dot" style="background-color: ' . $ev['color'] . ';" title="' . sanitize($ev['title']) . '"></div>'; }
                                    echo '</div></div>';
                                }
                                $last_day = date('N', mktime(0, 0, 0, $month, $days_in_month, $year));
                                for ($i = 1; $i <= (7 - $last_day); $i++) { echo '<div class="calendar-day other-month"><span class="day-number">' . $i . '</span></div>'; }
                                ?>
                            </div>

                        <?php elseif ($view === 'day'): ?>
                            <div class="time-grid-container">
                                <?php for ($h = 0; $h < 24; $h++): ?>
                                    <div class="time-label"><?php echo sprintf('%02d:00', $h); ?></div>
                                    <div class="time-slot">
                                        <?php foreach ($selected_day_events as $ev): ?>
                                            <?php if ($ev['type'] === 'event' && !empty($ev['start'])): ?>
                                                <?php
                                                    $st = strtotime($ev['start']);
                                                    $et = $ev['end'] ? strtotime($ev['end']) : ($st + 3600);
                                                    $start_h = (int)date('G', $st);
                                                    $start_m = (int)date('i', $st);
                                                    $duration = ($et - $st) / 3600;
                                                    if ($start_h === $h):
                                                ?>
                                                    <div class="time-event" style="background:<?php echo $ev['color']; ?>; top:<?php echo ($start_m/60)*100; ?>%; height:<?php echo $duration*100; ?>%;" onclick="location.href='../events/view.php?id=<?php echo $ev['id']; ?>'">
                                                        <?php echo sanitize($ev['title']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>

                        <?php elseif ($view === 'week'): ?>
                            <div class="calendar-grid">
                                <div class="calendar-day-head">Time</div>
                                <?php
                                    $day_names = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
                                    $week_days = [];
                                    $monday_time = strtotime("-" . (date('N', $current_time) - 1) . " days", $current_time);
                                    for($i=0;$i<7;$i++) {
                                        $t = strtotime("+$i days", $monday_time);
                                        $week_days[] = date('Y-m-d', $t);
                                        echo '<div class="calendar-day-head">' . $day_names[$i] . ' ' . date('d/m', $t) . '</div>';
                                    }
                                ?>
                            </div>
                            <div class="time-grid-container week-time-grid">
                                <?php for ($h = 0; $h < 24; $h++): ?>
                                    <div class="time-label"><?php echo sprintf('%02d:00', $h); ?></div>
                                    <?php foreach ($week_days as $wd): ?>
                                        <div class="time-slot">
                                            <?php foreach ($events_data[$wd] ?? [] as $ev): ?>
                                                <?php if ($ev['type'] === 'event' && !empty($ev['start'])): ?>
                                                    <?php
                                                        $st = strtotime($ev['start']);
                                                        $start_h = (int)date('G', $st);
                                                        if ($start_h === $h):
                                                    ?>
                                                        <div class="time-event" style="background:<?php echo $ev['color']; ?>;" onclick="location.href='../events/view.php?id=<?php echo $ev['id']; ?>'">
                                                            <?php echo sanitize($ev['title']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endfor; ?>
                            </div>

                        <?php elseif ($view === 'year'): ?>
                            <div class="year-grid">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <div class="mini-month">
                                        <div class="mini-month-title"><?php echo date('F', mktime(0, 0, 0, $m, 1, $year)); ?></div>
                                        <div class="mini-grid">
                                            <?php
                                            $first_mini = date('N', mktime(0,0,0,$m,1,$year));
                                            $days_mini = date('t', mktime(0,0,0,$m,1,$year));
                                            for($i=1;$i<$first_mini;$i++) echo '<div></div>';
                                            for($d=1;$d<=$days_mini;$d++):
                                                $ds = sprintf('%04d-%02d-%02d', $year, $m, $d);
                                                $has = !empty($events_data[$ds]);
                                            ?>
                                                <div class="mini-day <?php echo $has ? 'has-events' : ''; ?>" onclick="location.href='?view=month&date=<?php echo $ds; ?>&month=<?php echo $m; ?>&year=<?php echo $year; ?>'">
                                                    <?php echo $d; ?>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script>
/**
 * Ensures the theme is applied to the calendar elements on page load.
 */
document.addEventListener('DOMContentLoaded', () => {
    if (typeof initTheme === 'function') {
        initTheme();
    }
});
</script>
</body>
</html>
