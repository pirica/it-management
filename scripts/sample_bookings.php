<?php
/**
 * Seed sample hotel_bookings rows for companies 1–5 (planning status demo).
 *
 * CLI: php scripts/sample_bookings.php [--apply] [--company=1] [--date=2026-08-20]
 * Browser: sample_bookings.php?run=1 (dry-run) or ?run=1&apply=1 (Admin)
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Inserts <strong>10 sample bookings per company</strong> (companies <strong>1–5</strong> by default) with realistic guest names and lifecycle statuses anchored on <strong>today</strong> (or <code>--date=YYYY-MM-DD</code> / <code>?date=</code>).
<br><br>
Statuses: <code>PENDING</code>, <code>CONFIRMED</code>, <code>CANCELLED</code> (future); <code>IN-HOUSE</code>, <code>DUE-IN</code>, <code>DUE-OUT</code>, <code>NO-SHOW</code>, <code>CANCELLED</code> (present); <code>CHECKED-OUT</code>, <code>NO-SHOW</code> (history).
<br><br>
CLI dry-run: <code>php scripts/sample_bookings.php</code><br>
CLI apply: <code>php scripts/sample_bookings.php --apply</code><br>
Browser apply (Admin): <a href="sample_bookings.php?run=1&amp;apply=1">sample_bookings.php?run=1&amp;apply=1</a>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Sample hotel bookings seed', ['skip_db_tests' => false]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$conn = $boot['conn'];
$isCli = $boot['is_cli'];

if (!$conn instanceof mysqli) {
    echo colorText('[FAIL] No database connection.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

require_once dirname(__DIR__) . '/includes/itm_hotel_booking.php';

$argv = $GLOBALS['argv'] ?? [];
$opts = $isCli ? getopt('', ['company:', 'date:']) : [];
$companyFilter = 0;
$todaydate = date('Y-m-d');

if ($isCli) {
    if (isset($opts['company'])) {
        $companyFilter = (int) $opts['company'];
    }
    if (!empty($opts['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $opts['date'])) {
        $todaydate = (string) $opts['date'];
    }
} else {
    if (isset($_GET['company'])) {
        $companyFilter = (int) $_GET['company'];
    }
    $dateInput = trim((string) ($_GET['date'] ?? ''));
    if ($dateInput !== '') {
        $parsed = itm_parse_date_input($dateInput);
        if ($parsed !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $parsed)) {
            $todaydate = $parsed;
        }
    }
}

const ITM_SAMPLE_BOOKINGS_NOTE_PREFIX = '[sample_bookings]';

/**
 * @return array<int, int>
 */
function itm_sample_bookings_target_company_ids($conn, $companyFilter)
{
    $ids = [];
    if ($companyFilter > 0) {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM companies WHERE id = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyFilter);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($row) {
                $ids[] = (int) $row['id'];
            }
        }
        return $ids;
    }
    $stmt = mysqli_prepare($conn, 'SELECT id FROM companies WHERE id BETWEEN 1 AND 5 ORDER BY id ASC');
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $ids[] = (int) ($row['id'] ?? 0);
        }
        mysqli_stmt_close($stmt);
    }
    return array_values(array_filter($ids, static function ($id) {
        return $id > 0;
    }));
}

/**
 * @return array<int, array<string, mixed>>
 */
function itm_sample_bookings_templates($todaydate)
{
    $today = DateTime::createFromFormat('Y-m-d', $todaydate) ?: new DateTime('today');
    $offset = static function ($days) use ($today) {
        $d = clone $today;
        if ($days !== 0) {
            $d->modify(($days > 0 ? '+' : '') . $days . ' days');
        }
        return $d->format('Y-m-d');
    };

    return [
        ['key' => 'pending', 'segment' => 'future', 'status' => 'PENDING', 'check_in' => $offset(7), 'check_out' => $offset(9), 'color' => '#5c6bc0'],
        ['key' => 'confirmed', 'segment' => 'future', 'status' => 'CONFIRMED', 'check_in' => $offset(14), 'check_out' => $offset(16), 'color' => '#3949ab'],
        ['key' => 'cancelled_future', 'segment' => 'future', 'status' => 'CANCELLED', 'check_in' => $offset(21), 'check_out' => $offset(23), 'color' => '#78909c'],
        ['key' => 'in_house', 'segment' => 'present', 'status' => 'IN-HOUSE', 'check_in' => $offset(-3), 'check_out' => $offset(2), 'color' => '#2e7d32'],
        ['key' => 'due_in', 'segment' => 'present', 'status' => 'DUE-IN', 'check_in' => $offset(0), 'check_out' => $offset(2), 'color' => '#00897b'],
        ['key' => 'due_out', 'segment' => 'present', 'status' => 'DUE-OUT', 'check_in' => $offset(-3), 'check_out' => $offset(0), 'color' => '#f9a825'],
        ['key' => 'no_show_present', 'segment' => 'present', 'status' => 'NO-SHOW', 'check_in' => $offset(0), 'check_out' => $offset(1), 'color' => '#c62828'],
        ['key' => 'cancelled_present', 'segment' => 'present', 'status' => 'CANCELLED', 'check_in' => $offset(-1), 'check_out' => $offset(2), 'color' => '#6d4c41'],
        ['key' => 'checked_out', 'segment' => 'history', 'status' => 'CHECKED-OUT', 'check_in' => $offset(-7), 'check_out' => $offset(-1), 'color' => '#455a64'],
        ['key' => 'no_show_history', 'segment' => 'history', 'status' => 'NO-SHOW', 'check_in' => $offset(-10), 'check_out' => $offset(-3), 'color' => '#ad1457'],
    ];
}

/**
 * @return array<int, string>
 */
function itm_sample_bookings_guest_names()
{
    return [
        'Maria Silva', 'João Santos', 'Ana Ferreira', 'Pedro Costa', 'Sofia Oliveira',
        'Miguel Pereira', 'Inês Rodrigues', 'Tiago Martins', 'Beatriz Sousa', 'Rui Fernandes',
    ];
}

/**
 * @return array<int, int>
 */
function itm_sample_bookings_room_ids($conn, $companyId)
{
    $companyId = (int) $companyId;
    $ids = [];
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id FROM hotel_booking_rooms WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY room_number ASC'
    );
    if (!$stmt) {
        return $ids;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $ids[] = (int) ($row['id'] ?? 0);
    }
    mysqli_stmt_close($stmt);
    return array_values(array_filter($ids, static function ($id) {
        return $id > 0;
    }));
}

function itm_sample_bookings_admin_employee_id($conn, $companyId)
{
    $companyId = (int) $companyId;
    $stmt = mysqli_prepare(
        $conn,
        'SELECT e.id FROM employees e
         INNER JOIN employee_roles er ON er.id = e.role_id AND er.company_id = e.company_id
         WHERE e.company_id = ? AND e.deleted_at IS NULL AND LOWER(er.name) = \'admin\'
         ORDER BY e.id ASC LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int) ($row['id'] ?? 0);
}

/**
 * @return array{deleted:int,errors:array<int,string>}
 */
function itm_sample_bookings_delete_existing($conn, $companyId, $apply)
{
    $companyId = (int) $companyId;
    $deleted = 0;
    $errors = [];
    $like = ITM_SAMPLE_BOOKINGS_NOTE_PREFIX . '%';
    $ids = [];
    $find = mysqli_prepare(
        $conn,
        'SELECT id FROM hotel_bookings WHERE company_id = ? AND deleted_at IS NULL AND notes LIKE ?'
    );
    if (!$find) {
        $errors[] = 'company ' . $companyId . ': prepare find failed';
        return ['deleted' => 0, 'errors' => $errors];
    }
    mysqli_stmt_bind_param($find, 'is', $companyId, $like);
    mysqli_stmt_execute($find);
    $res = mysqli_stmt_get_result($find);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $ids[] = (int) ($row['id'] ?? 0);
    }
    mysqli_stmt_close($find);
    if ($ids === []) {
        return ['deleted' => 0, 'errors' => $errors];
    }
    if (!$apply) {
        return ['deleted' => count($ids), 'errors' => $errors];
    }
    foreach ($ids as $bookingId) {
        if ($bookingId < 1) {
            continue;
        }
        if (itm_hotel_booking_last_room_table_ready($conn)) {
            $delLast = mysqli_prepare(
                $conn,
                'DELETE FROM hotel_booking_last_rooms WHERE company_id = ? AND booking_id = ?'
            );
            if ($delLast) {
                mysqli_stmt_bind_param($delLast, 'ii', $companyId, $bookingId);
                mysqli_stmt_execute($delLast);
                mysqli_stmt_close($delLast);
            }
        }
        $del = mysqli_prepare($conn, 'DELETE FROM hotel_bookings WHERE company_id = ? AND id = ?');
        if (!$del) {
            $errors[] = 'company ' . $companyId . ': delete booking ' . $bookingId . ' failed';
            continue;
        }
        mysqli_stmt_bind_param($del, 'ii', $companyId, $bookingId);
        if (mysqli_stmt_execute($del)) {
            $deleted++;
        } else {
            $errors[] = 'company ' . $companyId . ': delete booking ' . $bookingId . ' failed';
        }
        mysqli_stmt_close($del);
    }
    return ['deleted' => $deleted, 'errors' => $errors];
}

/**
 * @return array{ok:bool,error:string}
 */
function itm_sample_bookings_insert_one($conn, $companyId, $employeeId, array $template, $guestName, $roomId, $apply)
{
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $roomId = (int) $roomId;
    $segment = (string) ($template['segment'] ?? '');
    $statusName = (string) ($template['status'] ?? '');
    $checkIn = (string) ($template['check_in'] ?? '');
    $checkOut = (string) ($template['check_out'] ?? '');
    $color = (string) ($template['color'] ?? '#0969da');
    $key = (string) ($template['key'] ?? 'row');

    if ($companyId < 1 || $roomId < 1 || $checkIn === '' || $checkOut === '' || $statusName === '') {
        return ['ok' => false, 'error' => 'invalid template or room'];
    }

    $table = itm_hotel_booking_status_table_for_segment($segment);
    if ($table === null) {
        return ['ok' => false, 'error' => 'unknown segment ' . $segment];
    }
    $statusId = itm_hotel_booking_status_id_by_name($conn, $companyId, $table, $statusName);
    if ($statusId === null) {
        return ['ok' => false, 'error' => 'missing status ' . $statusName . ' on ' . $table];
    }

    $email = 'sample.bookings.c' . $companyId . '.' . $key . '@example.invalid';
    $phone = '+351 9' . str_pad((string) (($companyId * 100) + crc32($key) % 10000), 8, '0', STR_PAD_LEFT);
    $customerId = itm_hotel_booking_ensure_customer_for_portal($conn, $companyId, $email, $guestName, $phone);
    if ($customerId === null || $customerId < 1) {
        return ['ok' => false, 'error' => 'customer insert failed for ' . $guestName];
    }

    if (!$apply) {
        return ['ok' => true, 'error' => ''];
    }

    $futureId = $segment === 'future' ? $statusId : 0;
    $presentId = $segment === 'present' ? $statusId : 0;
    $historyId = $segment === 'history' ? $statusId : 0;
    $nights = max(1, (int) ((strtotime($checkOut) - strtotime($checkIn)) / 86400));
    $paymentAmount = round(75.0 + ($nights * 42.5) + (crc32($guestName) % 120), 2);
    $auth2 = itm_hotel_booking_generate_auth2();
    $guestCode = itm_hotel_booking_generate_guest_confirmation_code($conn, $companyId);
    if ($guestCode === '') {
        return ['ok' => false, 'error' => 'guest confirmation code failed'];
    }
    $notes = ITM_SAMPLE_BOOKINGS_NOTE_PREFIX . ' ' . $statusName . ' — ' . $guestName;
    $active = 1;
    $createdAt = date('Y-m-d H:i:s');

    $ins = mysqli_prepare(
        $conn,
        'INSERT INTO hotel_bookings (
            company_id, customer_id, room_id, check_in, check_out, payment_amount,
            guest_confirmation_code, auth2,
            future_status_id, present_status_id, history_status_id,
            notes, booking_color, active, created_by, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?,
            NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0),
            ?, ?, ?, ?, ?
        )'
    );
    if (!$ins) {
        return ['ok' => false, 'error' => 'prepare insert failed'];
    }
    mysqli_stmt_bind_param(
        $ins,
        'iiissdssiiississ',
        $companyId,
        $customerId,
        $roomId,
        $checkIn,
        $checkOut,
        $paymentAmount,
        $guestCode,
        $auth2,
        $futureId,
        $presentId,
        $historyId,
        $notes,
        $color,
        $active,
        $employeeId,
        $createdAt
    );
    if (!mysqli_stmt_execute($ins)) {
        mysqli_stmt_close($ins);
        return ['ok' => false, 'error' => 'insert failed: ' . mysqli_error($conn)];
    }
    $bookingId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($ins);

    $savedRow = [
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'future_status_id' => $futureId,
        'present_status_id' => $presentId,
        'history_status_id' => $historyId,
    ];
    itm_hotel_booking_sync_last_room_if_detached($conn, $companyId, $bookingId, $employeeId, $savedRow, $roomId);

    return ['ok' => true, 'error' => ''];
}

$companyIds = itm_sample_bookings_target_company_ids($conn, $companyFilter);
if ($companyIds === []) {
    echo colorText('[FAIL] No target companies (use --company=1..5).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

$templates = itm_sample_bookings_templates($todaydate);
$guestNames = itm_sample_bookings_guest_names();
$planned = 0;
$deleted = 0;
$inserted = 0;
$errors = [];

echo colorText('Sample bookings seed (anchor date: ' . $todaydate . ')', 'info') . $nl;
echo ($apply ? colorText('Mode: APPLY (writes enabled)', 'warn') : colorText('Mode: dry-run (no writes)', 'info')) . $nl . $nl;

foreach ($companyIds as $companyId) {
    $rooms = itm_sample_bookings_room_ids($conn, $companyId);
    if ($rooms === []) {
        $errors[] = 'company ' . $companyId . ': no hotel_booking_rooms rows';
        echo colorText('[FAIL] company ' . $companyId . ': no rooms', 'fail') . $nl;
        continue;
    }
    $employeeId = itm_sample_bookings_admin_employee_id($conn, $companyId);
    $purge = itm_sample_bookings_delete_existing($conn, $companyId, $apply);
    $deleted += (int) ($purge['deleted'] ?? 0);
    $errors = array_merge($errors, $purge['errors'] ?? []);

    echo colorText('Company ' . $companyId, 'info') . $nl;
    if ($purge['deleted'] > 0) {
        echo ($apply ? '[APPLY] ' : '[DRY] ') . 'Removed prior sample bookings: ' . (int) $purge['deleted'] . $nl;
    }

    foreach ($templates as $idx => $template) {
        $planned++;
        $guestName = $guestNames[$idx % count($guestNames)];
        $roomId = $rooms[$idx % count($rooms)];
        $label = (string) ($template['status'] ?? '') . ' '
            . (string) ($template['check_in'] ?? '') . '→' . (string) ($template['check_out'] ?? '')
            . ' (' . $guestName . ')';
        $result = itm_sample_bookings_insert_one($conn, $companyId, $employeeId, $template, $guestName, $roomId, $apply);
        if (empty($result['ok'])) {
            $msg = (string) ($result['error'] ?? 'unknown');
            $errors[] = 'company ' . $companyId . ' ' . $label . ': ' . $msg;
            echo colorText('[FAIL] ' . $label . ' — ' . $msg, 'fail') . $nl;
            continue;
        }
        if ($apply) {
            $inserted++;
        }
        echo colorText('[PASS] ' . ($apply ? 'Inserted' : 'Would insert') . ' ' . $label, 'pass') . $nl;
    }
    echo $nl;
}

echo colorText('Summary', 'info') . $nl;
echo 'Companies: ' . count($companyIds) . $nl;
echo 'Planned rows: ' . $planned . $nl;
echo 'Prior sample rows removed: ' . $deleted . $nl;
echo 'Rows inserted: ' . ($apply ? $inserted : 0) . $nl;

if ($errors !== []) {
    echo $nl . colorText('Errors:', 'fail') . $nl;
    foreach ($errors as $err) {
        echo colorText('  - ' . $err, 'fail') . $nl;
    }
}

itm_apply_script_finish_hint($apply, $isCli, $apply ? $inserted : $planned, $nl, 'sample_bookings.php');
itm_script_output_end();

exit($errors === [] ? 0 : 1);
