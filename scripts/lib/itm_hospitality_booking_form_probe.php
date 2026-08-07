<?php
/**
 * CLI probe: render hotel_bookings create.php and validate rate-plan quick-add HTML contract.
 *
 * Usage: php scripts/lib/itm_hospitality_booking_form_probe.php [create|edit]
 */
define('ITM_CLI_SCRIPT', true);

require_once __DIR__ . '/itm_script_stdio.php';

$mode = isset($argv[1]) ? trim((string) $argv[1]) : 'create';
if (!in_array($mode, ['create', 'edit'], true)) {
    itm_script_write_stderr("Usage: php itm_hospitality_booking_form_probe.php [create|edit]\n");
    exit(2);
}

$repoRoot = dirname(__DIR__, 2);
chdir($repoRoot);
require $repoRoot . '/config/config.php';
require $repoRoot . '/modules/hotel_bookings/includes/hb_booking_form.php';

$_SESSION['employee_id'] = 1;
$_SESSION['login_employee_id'] = 1;
$_SESSION['company_id'] = 1;
$_SESSION['company_name'] = 'TechCorp Global';
$_SESSION['username'] = 'Admin';
$_SESSION['role_name'] = 'admin';

$entry = $mode === 'edit' ? 'edit.php' : 'create.php';
$entryPath = $repoRoot . '/modules/hotel_bookings/' . $entry;

if ($mode === 'edit') {
  $bid = 0;
  $res = mysqli_query($conn, 'SELECT id FROM hotel_bookings WHERE company_id = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
  if ($res && ($row = mysqli_fetch_assoc($res))) {
    $bid = (int) ($row['id'] ?? 0);
  }
  if ($bid < 1) {
    $roomRes = mysqli_query($conn, 'SELECT id FROM hotel_booking_rooms WHERE company_id = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
    $custRes = mysqli_query($conn, 'SELECT id FROM customers WHERE company_id = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
    $roomRow = $roomRes ? mysqli_fetch_assoc($roomRes) : null;
    $custRow = $custRes ? mysqli_fetch_assoc($custRes) : null;
    $roomId = (int) ($roomRow['id'] ?? 0);
    $custId = (int) ($custRow['id'] ?? 0);
    if ($roomId > 0 && $custId > 0) {
      $checkIn = date('Y-m-d', strtotime('+7 days'));
      $checkOut = date('Y-m-d', strtotime('+8 days'));
      $ins = mysqli_prepare($conn, 'INSERT INTO hotel_bookings (company_id, customer_id, room_id, check_in, check_out, payment_amount, active, created_at) VALUES (1, ?, ?, ?, ?, 0, 1, NOW())');
      if ($ins) {
        mysqli_stmt_bind_param($ins, 'iiss', $custId, $roomId, $checkIn, $checkOut);
        if (mysqli_stmt_execute($ins)) {
          $bid = (int) mysqli_insert_id($conn);
        }
        mysqli_stmt_close($ins);
      }
    }
  }
  if ($bid < 1) {
    itm_script_write_stderr( "No hotel_bookings row and probe insert failed for company 1\n");
    exit(1);
  }
  $_GET['id'] = $bid;
  $probeBookingId = $bid;
}

$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PHP_SELF'] = '/it-management/modules/hotel_bookings/' . $entry;

$issues = [];
set_error_handler(static function ($errno, $errstr, $file, $line) use (&$issues) {
    if ($errno & (E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE | E_ERROR | E_USER_ERROR)) {
        $issues[] = trim((string) $errstr) . ' in ' . $file . ':' . $line;
    }
    return true;
});

ob_start();
try {
    chdir(dirname($entryPath));
    include $entryPath;
} catch (Throwable $e) {
    $issues[] = 'Throwable: ' . $e->getMessage();
}
$html = ob_get_clean();
restore_error_handler();

if (!empty($issues)) {
    foreach (array_slice($issues, 0, 5) as $issue) {
        itm_script_write_stderr( $issue . "\n");
    }
    exit(1);
}

if (strpos($html, 'id="hb-booking-portal-rate-plan-id"') === false) {
    itm_script_write_stderr( "{$entry}: missing portal rate plan select\n");
    exit(1);
}
if (strpos($html, 'value="__add_new__"') === false) {
    itm_script_write_stderr( "{$entry}: missing __add_new__ option\n");
    exit(1);
}
if (strpos($html, 'id="hb-rate-plan-modal"') === false) {
    itm_script_write_stderr( "{$entry}: missing rate plan modal\n");
    exit(1);
}
$modalPos = strpos($html, 'id="hb-rate-plan-modal"');
$scriptPos = strpos($html, 'hotel-bookings-rate-plan-select.js');
if ($scriptPos === false) {
    itm_script_write_stderr( "{$entry}: missing hotel-bookings-rate-plan-select.js script tag\n");
    exit(1);
}
if ($modalPos === false || $modalPos > $scriptPos) {
    itm_script_write_stderr( "{$entry}: modal must appear before rate-plan select script tag\n");
    exit(1);
}

if (isset($probeBookingId) && $probeBookingId > 0) {
    mysqli_query($conn, 'DELETE FROM hotel_bookings WHERE id = ' . (int) $probeBookingId . ' AND company_id = 1 LIMIT 1');
}

exit(0);
