<?php
/**
 * CLI: php scripts/verify_notes_share.php
 * Verifies temporary QR/code share sessions for Notes.
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/notes_visibility.php';
require_once ROOT_PATH . 'modules/notes/notes_share_helpers.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Notes Share Session Verification');

$failures = 0;

function notes_share_verify_fail($message)
{
    global $failures;
    $failures++;
    fwrite(STDERR, "[FAIL] {$message}\n");
}

function notes_share_verify_pass($message)
{
    fwrite(STDOUT, "[PASS] {$message}\n");
}

if (!($conn instanceof mysqli)) {
    notes_share_verify_fail('Database connection unavailable.');
    exit(1);
}

$tableRes = $conn->query("SHOW TABLES LIKE 'note_share_sessions'");
if (!$tableRes || $tableRes->num_rows === 0) {
    notes_share_verify_fail('note_share_sessions table missing — re-import database.sql.');
    exit(1);
}

$companyId = 1;
$actor = itm_script_test_employee_create($conn, $companyId, ['script_slug' => 'verify-notes-share']);
if (!is_array($actor) || empty($actor['id'])) {
    notes_share_verify_fail('Could not create disposable test employee.');
    exit(1);
}

$employeeId = (int)$actor['id'];
$username = (string)($actor['username'] ?? 'sharetest');
itm_script_test_employee_register_teardown($conn, $employeeId, []);

$title = 'Share test ' . bin2hex(random_bytes(3));
$content = 'Cross-device payload';
$sharedJson = json_encode([$employeeId + 99999]);
$ins = $conn->prepare('INSERT INTO notes (company_id, employee_id, title, title_hash, content, shared_with_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
$titleHash = hash('sha256', $title);
$ins->bind_param('iissssi', $companyId, $employeeId, $title, $titleHash, $content, $sharedJson, $employeeId);
if (!$ins->execute()) {
    notes_share_verify_fail('Could not insert test note.');
    itm_script_test_employee_delete($conn, $employeeId);
    exit(1);
}
$noteId = (int)$ins->insert_id;
$ins->close();

$created = notes_share_create_session($conn, $noteId, $companyId, $employeeId, $username, true);
if (!$created['ok'] || empty($created['session'])) {
    notes_share_verify_fail('notes_share_create_session failed: ' . ($created['error'] ?? 'unknown'));
    $conn->query('DELETE FROM notes WHERE id = ' . (int)$noteId);
    itm_script_test_employee_delete($conn, $employeeId);
    exit(1);
}
notes_share_verify_pass('Share session created for owned note.');

$session = $created['session'];
$byToken = notes_share_fetch_session_by_token($conn, (string)$session['access_token']);
$byCode = notes_share_fetch_session_by_code($conn, (string)$session['share_code']);
if (!$byToken || !$byCode) {
    notes_share_verify_fail('Session lookup by token or code failed.');
} else {
    notes_share_verify_pass('Session resolves by access_token and share_code.');
}

$payload = notes_share_decode_payload($session['payload_json'] ?? '');
if ($payload === null || $payload['title'] !== $title || $payload['content'] !== $content) {
    notes_share_verify_fail('Share payload does not match note content.');
} else {
    notes_share_verify_pass('Share payload contains hydrated note text.');
}

$joinUrl = notes_share_build_join_url((string)$session['access_token']);
if ($joinUrl === '' || stripos($joinUrl, 'join.php?t=') === false) {
    notes_share_verify_fail('Join URL was not built.');
} else {
    notes_share_verify_pass('Join URL built.');
}

$hasImagesFlag = is_array($payload) && !empty($payload['images']);
if ($hasImagesFlag) {
    notes_share_verify_fail('has_images should be false for note without attachments.');
} else {
    notes_share_verify_pass('has_images contract false when note has no images.');
}

$imagesJson = json_encode(['share-test.png']);
$upd = $conn->prepare('UPDATE notes SET images_json = ? WHERE id = ?');
$upd->bind_param('si', $imagesJson, $noteId);
if (!$upd->execute()) {
    notes_share_verify_fail('Could not set images_json on test note.');
} else {
    $upd->close();
    $conn->query('DELETE FROM note_share_sessions WHERE note_id = ' . (int)$noteId);
    $withImages = notes_share_create_session($conn, $noteId, $companyId, $employeeId, $username, true);
    if (!$withImages['ok'] || empty($withImages['session'])) {
        notes_share_verify_fail('Share session with images failed.');
    } else {
        $payloadImages = notes_share_decode_payload($withImages['session']['payload_json'] ?? '');
        $hasImagesFlag = is_array($payloadImages) && !empty($payloadImages['images']);
        if (!$hasImagesFlag) {
            notes_share_verify_fail('Share payload must include images when note has attachments.');
        } else {
            notes_share_verify_pass('Share payload and has_images contract true when note has attachments.');
        }
    }
}

$dummySession = null;
$assetCheck = notes_share_validate_asset_request($conn, (string)$session['access_token'], '../evil.png', $dummySession);
if ($assetCheck['ok']) {
    notes_share_verify_fail('Asset validation must reject traversal filenames.');
} else {
    notes_share_verify_pass('Asset validation rejects invalid filenames.');
}

$conn->query('DELETE FROM note_share_sessions WHERE note_id = ' . (int)$noteId);
$conn->query('DELETE FROM notes WHERE id = ' . (int)$noteId);
itm_script_test_employee_delete($conn, $employeeId);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} failure(s).\n");
    exit(1);
}

fwrite(STDOUT, "\nAll notes share checks passed.\n");
exit(0);
