<?php
/**
 * Reproduction script for Cross-user IDOR in Notes Module.
 *
 * Why: Confirms that an authenticated user can view or soft-delete
 * private notes belonging to other users in the same company.
 *
 * Browser: open scripts/repro_notes_idor.php (login required).
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Notes IDOR Verification');

$nl = itm_script_output_nl();
echo "Testing IDOR in Notes View/Edit..." . $nl;

// 1. Setup - two users in the same company
$company_id = 1;
$u1_name = "owner_" . uniqid();
mysqli_query($conn, "INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES ($company_id, '$u1_name', '$u1_name@example.com', 'pass', 2, 2, 1)");
$user1_id = mysqli_insert_id($conn);

$u2_name = "attacker_" . uniqid();
mysqli_query($conn, "INSERT INTO users (company_id, username, email, password, role_id, access_level_id, active) VALUES ($company_id, '$u2_name', '$u2_name@example.com', 'pass', 2, 2, 1)");
$user2_id = mysqli_insert_id($conn);

echo "User 1 (Owner) ID: $user1_id" . $nl;
echo "User 2 (Attacker) ID: $user2_id" . $nl;

// 2. User 1 creates a private note
$secret_content = "SECRET_TOKEN_" . uniqid();
mysqli_query($conn, "INSERT INTO notes (company_id, user_id, title, content, active) VALUES ($company_id, $user1_id, 'Private Note', '$secret_content', 1)");
$note_id = mysqli_insert_id($conn);

echo "Created private note ID: $note_id for User 1." . $nl;

// 3. User 2 (Attacker) attempts to view it
$_SESSION['user_id'] = $user2_id;
$_SESSION['company_id'] = $company_id;

$stmt = $conn->prepare("SELECT * FROM notes WHERE id = ? AND company_id = ? AND active = 1");
$stmt->bind_param("ii", $note_id, $company_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$view_vulnerable = ($data && $data['content'] === $secret_content);

if ($view_vulnerable) {
    echo colorText("[FAIL] VULNERABLE: User 2 accessed User 1's private note via IDOR.", 'fail') . $nl;
} else {
    echo colorText("[PASS] SAFE: Access to private note was restricted.", 'pass') . $nl;
}

// 4. Test IDOR in soft-delete
$stmt_del = $conn->prepare("UPDATE notes SET active = 0 WHERE id = ? AND company_id = ?");
$stmt_del->bind_param("ii", $note_id, $company_id);
$stmt_del->execute();
$delete_vulnerable = ($stmt_del->affected_rows > 0);
$stmt_del->close();

if ($delete_vulnerable) {
    echo colorText("[FAIL] VULNERABLE: User 2 soft-deleted User 1's note via IDOR.", 'fail') . $nl;
} else {
    echo colorText("[PASS] SAFE: Soft-delete unauthorized via IDOR.", 'pass') . $nl;
}

// Cleanup
mysqli_query($conn, "DELETE FROM notes WHERE id = $note_id");
mysqli_query($conn, "DELETE FROM users WHERE id IN ($user1_id, $user2_id)");

itm_script_output_end();
