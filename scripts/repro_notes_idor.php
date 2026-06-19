<?php
/**
 * Reproduction script for Cross-user IDOR in Notes Module.
 *
 * Why: Confirms that an authenticated user cannot view or soft-delete
 * private notes belonging to other users in the same company.
 *
 * Browser: open scripts/repro_notes_idor.php (login required).
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/notes_visibility.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/itm_script_test_employee.php';

itm_script_output_begin('Notes IDOR Verification');

$nl = itm_script_output_nl();
echo "Testing IDOR in Notes View/Edit..." . $nl;

// 1. Setup - two users in the same company
$company_id = 1;
$user1 = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'repro-notes-idor-owner']);
$user2 = itm_script_test_employee_create($conn, $company_id, ['script_slug' => 'repro-notes-idor-attacker']);
if (!is_array($user1) || !is_array($user2)) {
    echo colorText('[FAIL] Unable to create disposable test users.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}
$user1_id = (int)$user1['id'];
$user2_id = (int)$user2['id'];
itm_script_test_employee_register_teardown($conn, $user1_id);
itm_script_test_employee_register_teardown($conn, $user2_id);

echo "User 1 (Owner) ID: $user1_id" . $nl;
echo "User 2 (Attacker) ID: $user2_id" . $nl;

// 2. User 1 creates a private note
$secret_content = "SECRET_TOKEN_" . uniqid();
$stmtInsert = $conn->prepare("INSERT INTO notes (company_id, employee_id, title, content, active) VALUES (?, ?, 'Private Note', ?, 1)");
$stmtInsert->bind_param('iis', $company_id, $user1_id, $secret_content);
$stmtInsert->execute();
$note_id = (int)$stmtInsert->insert_id;
$stmtInsert->close();

echo "Created private note ID: $note_id for User 1." . $nl;

// 3. User 2 (Attacker) attempts to view it via visibility contract
$_SESSION['employee_id'] = $user2_id;
$_SESSION['company_id'] = $company_id;

$data = itm_notes_fetch_visible_by_id($conn, $note_id, $company_id, $user2_id, true);
$view_vulnerable = ($data && $data['content'] === $secret_content);

if ($view_vulnerable) {
    echo colorText("[FAIL] VULNERABLE: User 2 accessed User 1's private note via IDOR.", 'fail') . $nl;
} else {
    echo colorText("[PASS] SAFE: Access to private note was restricted.", 'pass') . $nl;
}

// 4. Owner can still load the note
$ownerData = itm_notes_fetch_visible_by_id($conn, $note_id, $company_id, $user1_id, true);
if (!$ownerData || $ownerData['content'] !== $secret_content) {
    echo colorText('[FAIL] Owner could not load their own note via visibility helper.', 'fail') . $nl;
} else {
    echo colorText('[PASS] Owner can load their own note.', 'pass') . $nl;
}

// 5. Test IDOR in soft-delete (production visibility-scoped UPDATE)
$visSql = itm_notes_visibility_sql();
$stmt_del = $conn->prepare("UPDATE notes SET active = 0 WHERE id = ? AND company_id = ? AND ($visSql)");
$stmt_del->bind_param('iiii', $note_id, $company_id, $user2_id, $user2_id);
$stmt_del->execute();
$delete_vulnerable = ($stmt_del->affected_rows > 0);
$stmt_del->close();

if ($delete_vulnerable) {
    echo colorText("[FAIL] VULNERABLE: User 2 soft-deleted User 1's note via IDOR.", 'fail') . $nl;
} else {
    echo colorText("[PASS] SAFE: Soft-delete unauthorized via IDOR.", 'pass') . $nl;
}

// Cleanup
$stmtCleanup = $conn->prepare('DELETE FROM notes WHERE id = ?');
$stmtCleanup->bind_param('i', $note_id);
$stmtCleanup->execute();
$stmtCleanup->close();

$failed = $view_vulnerable || $delete_vulnerable || !$ownerData;
itm_script_output_end();
exit($failed ? 1 : 0);
