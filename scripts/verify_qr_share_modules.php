<?php
/**
 * CLI: php scripts/verify_qr_share_modules.php
 * Verifies temporary QR/code share sessions for Passwords, Bookmarks, Todo, Events, Explorer, Floor Plans, and Rack Planner.
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once ROOT_PATH . 'modules/passwords/passwords_share_helpers.php';
require_once ROOT_PATH . 'modules/bookmarks/bookmarks_share_helpers.php';
require_once ROOT_PATH . 'modules/todo/todo_share_helpers.php';
require_once ROOT_PATH . 'modules/events/events_share_helpers.php';
require_once ROOT_PATH . 'modules/explorer/explorer_share_helpers.php';
require_once ROOT_PATH . 'modules/floor_plans/floor_plans_share_helpers.php';
require_once ROOT_PATH . 'modules/rack_planner/rack_planner_share_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('QR Share Modules Verification');
$nl = itm_script_output_nl();

$failures = 0;

function qr_share_verify_fail($message)
{
    global $failures, $nl;
    $failures++;
    echo colorText('[FAIL] ' . $message, 'fail') . $nl;
}

function qr_share_verify_pass($message)
{
    global $nl;
    echo colorText('[PASS] ' . $message, 'pass') . $nl;
}

if (!($conn instanceof mysqli)) {
    qr_share_verify_fail('Database connection unavailable.');
    exit(1);
}

$tableRes = $conn->query("SHOW TABLES LIKE 'share_sessions'");
if (!$tableRes || $tableRes->num_rows === 0) {
    foreach (['password_share_sessions', 'bookmark_share_sessions', 'todo_share_sessions', 'event_share_sessions', 'private_contact_share_sessions', 'explorer_share_sessions', 'floor_plan_share_sessions', 'rack_planner_share_sessions'] as $legacyTable) {
        $legacyRes = $conn->query("SHOW TABLES LIKE '{$legacyTable}'");
        if ($legacyRes && $legacyRes->num_rows > 0) {
            qr_share_verify_fail('Legacy share table still present — apply db/migrations/share_sessions_unified.sql or re-import db/ split bundle.');
            exit(1);
        }
    }
    qr_share_verify_fail('share_sessions table missing — re-import via bash scripts/import_database_split.sh.');
    exit(1);
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
    if (itm_qr_share_fetch_session_by_token($conn, passwords_share_module_slug(), (string)$pwdSession['access_token']) === null) {
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
    $conn->query("DELETE FROM share_sessions WHERE module_slug = 'bookmarks' AND record_id = " . (int)$bookmarkId);
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
    $conn->query("DELETE FROM share_sessions WHERE module_slug = 'todo' AND record_id = " . (int)$todoId);
    $conn->query('DELETE FROM todo WHERE id = ' . (int)$todoId);
}

// Events
$eventTitle = 'QR Share Event ' . bin2hex(random_bytes(2));
$eventDescription = 'Cross-device event payload';
$startDatetime = date('Y-m-d H:i:s', strtotime('+1 day'));
$endDatetime = date('Y-m-d H:i:s', strtotime('+1 day +2 hours'));
$eventLocation = 'Conference Room A';
$eventIns = $conn->prepare(
    'INSERT INTO events (company_id, employee_id, title, description, start_datetime, end_datetime, location, created_by, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
);
$eventIns->bind_param('iisssssi', $companyId, $employeeId, $eventTitle, $eventDescription, $startDatetime, $endDatetime, $eventLocation, $employeeId);
if (!$eventIns->execute()) {
    qr_share_verify_fail('Could not insert test event row.');
} else {
    $eventId = (int)$eventIns->insert_id;
    $eventIns->close();
    $eventCreated = events_share_create_session($conn, $eventId, $companyId, $employeeId, $username, true);
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
    $conn->query("DELETE FROM share_sessions WHERE module_slug = 'events' AND record_id = " . (int)$eventId);
    $conn->query('DELETE FROM events WHERE id = ' . (int)$eventId);
}

// Explorer (Common folder snapshot)
$scopeSuffix = bin2hex(random_bytes(2));
$scopePath = 'Common/QRShareTest' . $scopeSuffix;
$storageRoot = rtrim(str_replace('\\', '/', ROOT_PATH . 'files/' . $companyId), '/');
$probeRelative = $scopePath . '/probe.txt';
$probeAbsolute = $storageRoot . '/' . str_replace('/', DIRECTORY_SEPARATOR, $probeRelative);
$probeDir = dirname($probeAbsolute);
if (function_exists('itm_ensure_files_storage_directory')) {
    itm_ensure_files_storage_directory($probeDir);
} elseif (!is_dir($probeDir)) {
    mkdir($probeDir, 0755, true);
}
if (@file_put_contents($probeAbsolute, 'explorer share probe') === false) {
    qr_share_verify_fail('Could not create Explorer probe file.');
} else {
    $safeUsername = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
    $userPrivateDir = $safeUsername . '_' . $employeeId;
    $explorerCreated = explorer_share_create_session(
        $conn,
        $companyId,
        $employeeId,
        $username,
        $scopePath,
        '',
        $username,
        true,
        $storageRoot,
        $userPrivateDir
    );
    if (!$explorerCreated['ok'] || empty($explorerCreated['session'])) {
        qr_share_verify_fail('explorer_share_create_session failed: ' . ($explorerCreated['error'] ?? 'unknown'));
    } else {
        qr_share_verify_pass('Explorer share session created.');
        $explorerPayload = itm_qr_share_decode_payload($explorerCreated['session']['payload_json'] ?? '');
        if ($explorerPayload === null || ($explorerPayload['type'] ?? '') !== 'explorer' || (int)($explorerPayload['file_count'] ?? 0) < 1) {
            qr_share_verify_fail('Explorer share payload mismatch.');
        } else {
            qr_share_verify_pass('Explorer share payload lists scoped files.');
        }
        $explorerJoinUrl = explorer_share_build_join_url((string)$explorerCreated['session']['access_token']);
        if ($explorerJoinUrl === '' || stripos($explorerJoinUrl, 'modules/explorer/join.php?t=') === false) {
            qr_share_verify_fail('Explorer join URL was not built.');
        } else {
            qr_share_verify_pass('Explorer join URL built.');
        }
    }
    $conn->query("DELETE FROM share_sessions WHERE module_slug = 'explorer' AND employee_id = " . (int)$employeeId);
    @unlink($probeAbsolute);
    @rmdir($probeDir);
}

// Floor Plans
require_once ROOT_PATH . 'modules/floor_plans/gallery_helpers.php';
$storedFilename = 'qr-share-' . bin2hex(random_bytes(4)) . '.txt';
$fpUploadDir = fp_company_upload_dir($companyId);
if (!is_dir($fpUploadDir)) {
    @mkdir($fpUploadDir, 0755, true);
}
$fpAbsolute = fp_absolute_path($companyId, $storedFilename);
if (@file_put_contents($fpAbsolute, 'floor plan share probe') === false) {
    qr_share_verify_fail('Could not create floor plan probe file.');
} else {
    $fpDisplayName = 'QR Share Plan ' . bin2hex(random_bytes(2));
    $fpMime = 'text/plain';
    $fpExt = 'txt';
    $fpSize = (int)filesize($fpAbsolute);
    $fpIns = $conn->prepare(
        'INSERT INTO floor_plans (company_id, display_name, stored_filename, mime_type, file_ext, file_size, active, created_by) VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
    );
    if (!$fpIns) {
        qr_share_verify_fail('Could not prepare floor plan insert.');
    } else {
        $fpIns->bind_param('issssii', $companyId, $fpDisplayName, $storedFilename, $fpMime, $fpExt, $fpSize, $employeeId);
        if (!$fpIns->execute()) {
            qr_share_verify_fail('Could not insert test floor plan row.');
        } else {
            $floorPlanId = (int)$fpIns->insert_id;
            $fpIns->close();
            $fpCreated = floor_plans_share_create_session($conn, $floorPlanId, $companyId, $employeeId, $username);
            if (!$fpCreated['ok'] || empty($fpCreated['session'])) {
                qr_share_verify_fail('floor_plans_share_create_session failed: ' . ($fpCreated['error'] ?? 'unknown'));
            } else {
                qr_share_verify_pass('Floor plan share session created.');
                $fpPayload = itm_qr_share_decode_payload($fpCreated['session']['payload_json'] ?? '');
                if ($fpPayload === null || ($fpPayload['display_name'] ?? '') !== $fpDisplayName) {
                    qr_share_verify_fail('Floor plan share payload mismatch.');
                } else {
                    qr_share_verify_pass('Floor plan share payload contains display name.');
                }
                $fpJoinUrl = floor_plans_share_build_join_url((string)$fpCreated['session']['access_token']);
                if ($fpJoinUrl === '' || stripos($fpJoinUrl, 'modules/floor_plans/join.php?t=') === false) {
                    qr_share_verify_fail('Floor plan join URL was not built.');
                } else {
                    qr_share_verify_pass('Floor plan join URL built.');
                }
            }
            $conn->query("DELETE FROM share_sessions WHERE module_slug = 'floor_plans' AND record_id = " . (int)$floorPlanId);
            $conn->query('DELETE FROM floor_plans WHERE id = ' . (int)$floorPlanId);
        }
        @unlink($fpAbsolute);
    }
}

// Rack Planner
$rackStatusId = 0;
$rackStatusRes = $conn->query('SELECT id FROM rack_statuses WHERE company_id = ' . (int)$companyId . " AND name = 'Active' LIMIT 1");
if ($rackStatusRes && ($rackStatusRow = $rackStatusRes->fetch_assoc())) {
    $rackStatusId = (int)$rackStatusRow['id'];
}
if ($rackStatusId <= 0) {
    $rackStatusRes = $conn->query('SELECT id FROM rack_statuses WHERE company_id = ' . (int)$companyId . ' ORDER BY id ASC LIMIT 1');
    if ($rackStatusRes && ($rackStatusRow = $rackStatusRes->fetch_assoc())) {
        $rackStatusId = (int)$rackStatusRow['id'];
    }
}
if ($rackStatusId <= 0) {
    qr_share_verify_fail('Could not resolve rack_statuses row for Rack Planner share test.');
} else {
    $rackName = 'QR Share Rack ' . bin2hex(random_bytes(2));
    $rackUnits = 42;
    $rackLayout = '{"version":1,"units":42,"devices":[]}';
    $rackIns = $conn->prepare(
        'INSERT INTO rack_planner (company_id, employee_id, name, rack_units, layout_json, status_id, active, created_by) VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
    );
    if (!$rackIns) {
        qr_share_verify_fail('Could not prepare rack planner insert.');
    } else {
        $rackIns->bind_param('iisisii', $companyId, $employeeId, $rackName, $rackUnits, $rackLayout, $rackStatusId, $employeeId);
        if (!$rackIns->execute()) {
            qr_share_verify_fail('Could not insert test rack planner row.');
        } else {
            $rackPlanId = (int)$rackIns->insert_id;
            $rackIns->close();
            $rackCreated = rack_planner_share_create_session($conn, $rackPlanId, $companyId, $employeeId, $username);
            if (!$rackCreated['ok'] || empty($rackCreated['session'])) {
                qr_share_verify_fail('rack_planner_share_create_session failed: ' . ($rackCreated['error'] ?? 'unknown'));
            } else {
                qr_share_verify_pass('Rack planner share session created.');
                $rackPayload = itm_qr_share_decode_payload($rackCreated['session']['payload_json'] ?? '');
                if ($rackPayload === null || ($rackPayload['name'] ?? '') !== $rackName || ($rackPayload['type'] ?? '') !== 'rack_planner') {
                    qr_share_verify_fail('Rack planner share payload mismatch.');
                } else {
                    qr_share_verify_pass('Rack planner share payload contains plan name.');
                }
                $rackJoinUrl = rack_planner_share_build_join_url((string)$rackCreated['session']['access_token']);
                if ($rackJoinUrl === '' || stripos($rackJoinUrl, 'modules/rack_planner/join.php?t=') === false) {
                    qr_share_verify_fail('Rack planner join URL was not built.');
                } else {
                    qr_share_verify_pass('Rack planner join URL built.');
                }
            }
            $conn->query("DELETE FROM share_sessions WHERE module_slug = 'rack_planner' AND record_id = " . (int)$rackPlanId);
            $conn->query('DELETE FROM rack_planner WHERE id = ' . (int)$rackPlanId);
        }
    }
}

$conn->query("DELETE FROM share_sessions WHERE module_slug = 'passwords' AND record_id = " . (int)$passwordEntryId);
$conn->query('DELETE FROM password_entries WHERE id = ' . (int)$passwordEntryId);
unset($_SESSION['vault_key']);
itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    echo colorText($failures . ' failure(s).', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('All QR share module checks passed.', 'pass') . $nl;
itm_script_output_end();
exit(0);
