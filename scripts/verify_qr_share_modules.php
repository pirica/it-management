<?php
/**
 * CLI: php scripts/verify_qr_share_modules.php
 * Verifies temporary QR/code share sessions for Passwords, Bookmarks, Todo, and Events.
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once ROOT_PATH . 'modules/passwords/passwords_share_helpers.php';
require_once ROOT_PATH . 'modules/bookmarks/bookmarks_share_helpers.php';
require_once ROOT_PATH . 'modules/todo/todo_share_helpers.php';
require_once ROOT_PATH . 'modules/events/events_share_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('QR Share Modules Verification');

$failures = 0;

function qr_share_verify_fail($message)
{
    global $failures;
    $failures++;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function qr_share_verify_pass($message)
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

if (!($conn instanceof mysqli)) {
    qr_share_verify_fail('Database connection unavailable.');
    exit(1);
}

foreach (['password_share_sessions', 'bookmark_share_sessions', 'todo_share_sessions', 'event_share_sessions'] as $tableName) {
    $tableRes = $conn->query("SHOW TABLES LIKE '{$tableName}'");
    if (!$tableRes || $tableRes->num_rows === 0) {
        qr_share_verify_fail("{$tableName} table missing — re-import database.sql.");
        exit(1);
    }
}

$companyId = 1;
$actor = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-qr-share-modules']);
if (!is_array($actor) || empty($actor['id'])) {
    qr_share_verify_fail('Could not create disposable test employee.');
    exit(1);
}

$employeeId = (int)$actor['id'];
$username = (string)($actor['username'] ?? 'qrsharetest');
itm_script_test_employee_register_teardown($conn, $employeeId, []);

$vaultKeyPlain = 'QrShareTestVault' . bin2hex(random_bytes(4));
$_SESSION['vault_key'] = hash('sha256', $vaultKeyPlain);

// Passwords
$account = 'QR Share Pwd ' . bin2hex(random_bytes(2));
$loginName = 'qr-user';
$plainPassword = 'Secret-' . bin2hex(random_bytes(2));
$encryptedPassword = itm_encrypt($plainPassword, $_SESSION['vault_key']);
$pwdIns = $conn->prepare(
    'INSERT INTO password_entries (company_id, employee_id, account, login_name, password, website, comments, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$website = 'https://example.test/login';
$comments = 'share probe';
$pwdIns->bind_param('iisssssi', $companyId, $employeeId, $account, $loginName, $encryptedPassword, $website, $comments, $employeeId);
if (!$pwdIns->execute()) {
    qr_share_verify_fail('Could not insert test password entry.');
    itm_script_test_employee_delete($conn, $employeeId);
    exit(1);
}
$passwordEntryId = (int)$pwdIns->insert_id;
$pwdIns->close();

$pwdCreated = passwords_share_create_session($conn, $passwordEntryId, $companyId, $employeeId, $username, true);
if (!$pwdCreated['ok'] || empty($pwdCreated['session'])) {
    qr_share_verify_fail('passwords_share_create_session failed: ' . ($pwdCreated['error'] ?? 'unknown'));
} else {
    qr_share_verify_pass('Password share session created.');
    $pwdSession = $pwdCreated['session'];
    $pwdPayload = itm_qr_share_decode_payload($pwdSession['payload_json'] ?? '');
    if ($pwdPayload === null || ($pwdPayload['password'] ?? '') !== $plainPassword || ($pwdPayload['account'] ?? '') !== $account) {
        qr_share_verify_fail('Password share payload mismatch.');
    } else {
        qr_share_verify_pass('Password share payload contains decrypted fields.');
    }
    if (itm_qr_share_fetch_session_by_token($conn, passwords_share_table_name(), (string)$pwdSession['access_token']) === null) {
        qr_share_verify_fail('Password session token lookup failed.');
    } else {
        qr_share_verify_pass('Password session resolves by access_token.');
    }
}

// Bookmarks (shared row — no vault required)
$bookmarkTitle = 'QR Share Bookmark ' . bin2hex(random_bytes(2));
$bookmarkUrl = 'https://example.test/' . bin2hex(random_bytes(3));
$urlHash = hash('sha256', $bookmarkUrl);
$bkmIns = $conn->prepare(
    'INSERT INTO bookmarks (company_id, employee_id, title, url, url_hash, notes, shared, created_by) VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
);
$bookmarkNotes = 'share probe notes';
$bkmIns->bind_param('iissssi', $companyId, $employeeId, $bookmarkTitle, $bookmarkUrl, $urlHash, $bookmarkNotes, $employeeId);
if (!$bkmIns->execute()) {
    qr_share_verify_fail('Could not insert test bookmark.');
} else {
    $bookmarkId = (int)$bkmIns->insert_id;
    $bkmIns->close();
    $bkmCreated = bookmarks_share_create_session($conn, $bookmarkId, $companyId, $employeeId, $username, false, true);
    if (!$bkmCreated['ok'] || empty($bkmCreated['session'])) {
        qr_share_verify_fail('bookmarks_share_create_session failed: ' . ($bkmCreated['error'] ?? 'unknown'));
    } else {
        qr_share_verify_pass('Bookmark share session created.');
        $bkmPayload = itm_qr_share_decode_payload($bkmCreated['session']['payload_json'] ?? '');
        if ($bkmPayload === null || ($bkmPayload['url'] ?? '') !== $bookmarkUrl) {
            qr_share_verify_fail('Bookmark share payload mismatch.');
        } else {
            qr_share_verify_pass('Bookmark share payload contains URL.');
        }
    }
    $conn->query('DELETE FROM bookmark_share_sessions WHERE bookmark_id = ' . (int)$bookmarkId);
    $conn->query('DELETE FROM bookmarks WHERE id = ' . (int)$bookmarkId);
}

// Todo
$taskTitle = 'QR Share Task ' . bin2hex(random_bytes(2));
$taskDescription = 'Cross-device todo payload';
$todoIns = $conn->prepare(
    'INSERT INTO todo (company_id, title, description, created_by, importance, completed) VALUES (?, ?, ?, ?, 1, 0)'
);
$todoIns->bind_param('issi', $companyId, $taskTitle, $taskDescription, $employeeId);
if (!$todoIns->execute()) {
    qr_share_verify_fail('Could not insert test todo row.');
} else {
    $todoId = (int)$todoIns->insert_id;
    $todoIns->close();
    $todoCreated = todo_share_create_session($conn, $todoId, $companyId, $employeeId, $username, [], [], []);
    if (!$todoCreated['ok'] || empty($todoCreated['session'])) {
        qr_share_verify_fail('todo_share_create_session failed: ' . ($todoCreated['error'] ?? 'unknown'));
    } else {
        qr_share_verify_pass('Todo share session created.');
        $todoPayload = itm_qr_share_decode_payload($todoCreated['session']['payload_json'] ?? '');
        if ($todoPayload === null || ($todoPayload['title'] ?? '') !== $taskTitle) {
            qr_share_verify_fail('Todo share payload mismatch.');
        } else {
            qr_share_verify_pass('Todo share payload contains task title.');
        }
        $joinUrl = todo_share_build_join_url((string)$todoCreated['session']['access_token']);
        if ($joinUrl === '' || stripos($joinUrl, 'modules/todo/join.php?t=') === false) {
            qr_share_verify_fail('Todo join URL was not built.');
        } else {
            qr_share_verify_pass('Todo join URL built.');
        }
    }
    $conn->query('DELETE FROM todo_share_sessions WHERE todo_id = ' . (int)$todoId);
    $conn->query('DELETE FROM todo WHERE id = ' . (int)$todoId);
}

// Events
$eventTitle = 'QR Share Event ' . bin2hex(random_bytes(2));
$eventDescription = 'Cross-device event payload';
$startDatetime = date('Y-m-d H:i:s', strtotime('+1 day'));
$endDatetime = date('Y-m-d H:i:s', strtotime('+1 day +2 hours'));
$eventLocation = 'Conference Room A';
$eventIns = $conn->prepare(
    'INSERT INTO events (company_id, title, description, start_datetime, end_datetime, location, created_by, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
);
$eventIns->bind_param('isssssi', $companyId, $eventTitle, $eventDescription, $startDatetime, $endDatetime, $eventLocation, $employeeId);
if (!$eventIns->execute()) {
    qr_share_verify_fail('Could not insert test event row.');
} else {
    $eventId = (int)$eventIns->insert_id;
    $eventIns->close();
    $eventCreated = events_share_create_session($conn, $eventId, $companyId, $employeeId, $username);
    if (!$eventCreated['ok'] || empty($eventCreated['session'])) {
        qr_share_verify_fail('events_share_create_session failed: ' . ($eventCreated['error'] ?? 'unknown'));
    } else {
        qr_share_verify_pass('Event share session created.');
        $eventPayload = itm_qr_share_decode_payload($eventCreated['session']['payload_json'] ?? '');
        if ($eventPayload === null || ($eventPayload['title'] ?? '') !== $eventTitle) {
            qr_share_verify_fail('Event share payload mismatch.');
        } else {
            qr_share_verify_pass('Event share payload contains event title.');
        }
        $joinUrl = events_share_build_join_url((string)$eventCreated['session']['access_token']);
        if ($joinUrl === '' || stripos($joinUrl, 'modules/events/join.php?t=') === false) {
            qr_share_verify_fail('Event join URL was not built.');
        } else {
            qr_share_verify_pass('Event join URL built.');
        }
    }
    $conn->query('DELETE FROM event_share_sessions WHERE event_id = ' . (int)$eventId);
    $conn->query('DELETE FROM events WHERE id = ' . (int)$eventId);
}

$conn->query('DELETE FROM password_share_sessions WHERE password_entry_id = ' . (int)$passwordEntryId);
$conn->query('DELETE FROM password_entries WHERE id = ' . (int)$passwordEntryId);
unset($_SESSION['vault_key']);
itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failure(s).\n");
    exit(1);
}

fwrite(STDOUT, "\nAll QR share module checks passed.\n");
exit(0);
