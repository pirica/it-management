<?php
/**
 * Reproduction script for Path Traversal in Notes ZIP Download.
 *
 * Why: Confirms that an attacker can include arbitrary server files in a ZIP
 * download by manipulating note image metadata.
 *
 * Browser: open scripts/repro_notes_traversal.php (login required).
 */

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . 'includes/notes_visibility.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('Notes Path Traversal Verification');

$nl = itm_script_output_nl();
echo "Testing Path Traversal in Notes ZIP Download..." . $nl;

// 1. Setup session context
$user_res = mysqli_query($conn, "SELECT id, username, company_id FROM users WHERE active = 1 LIMIT 1");
$user = mysqli_fetch_assoc($user_res);
if (!$user) {
    die("No active users found to test with." . $nl);
}

$user_id = (int)$user['id'];
$company_id = (int)$user['company_id'];
$username = $user['username'];

$_SESSION['user_id'] = $user_id;
$_SESSION['company_id'] = $company_id;
$_SESSION['username'] = $username;

// 2. Prepare malicious note
$malicious_path = "../../../../../config/config.php";
$images_json = json_encode([$malicious_path]);

$sql = "INSERT INTO notes (company_id, user_id, title, images_json, active) VALUES (?, ?, 'Traversal Test', ?, 1)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iis", $company_id, $user_id, $images_json);
if (!$stmt->execute()) {
    die("Insert failed: " . $stmt->error . $nl);
}
$note_id = $stmt->insert_id;
$stmt->close();

echo "Created malicious note ID: $note_id" . $nl;

// 3. Simulate ZIP creation logic from modules/notes/index.php
$stmt = $conn->prepare("SELECT title, images_json FROM notes WHERE id = ? AND company_id = ? AND user_id = ?");
$stmt->bind_param("iii", $note_id, $company_id, $user_id);
$stmt->execute();
$resD = $stmt->get_result()->fetch_assoc();
$imgs = json_decode($resD['images_json'] ?? '[]', true);
$stmt->close();

$title = $resD['title'] ?: "note_{$note_id}";
$safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title);
$zip = new ZipArchive();
$zipName = "{$safeTitle}_download.zip";
$zipPath = sys_get_temp_dir() . '/' . $zipName;

$vulnerable = false;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    foreach ($imgs as $img) {
        $filePath = itm_notes_resolve_image_path($company_id, $username, $user_id, $img);
        if ($filePath !== null) {
            $zip->addFile($filePath, basename($filePath));
        }
    }
    $zip->close();
}

// 4. Verify ZIP content
if (file_exists($zipPath)) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) === TRUE) {
        for($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat['size'] > 0 && strpos($stat['name'], 'config.php') !== false) {
                $vulnerable = true;
                break;
            }
        }
        $zip->close();
    }
    unlink($zipPath);
}

if ($vulnerable) {
    echo colorText("[FAIL] VULNERABLE: Sensitive file 'config.php' was successfully included in the ZIP archive via path traversal.", 'fail') . $nl;
} else {
    echo colorText("[PASS] SAFE: Sensitive files were not included in the ZIP archive.", 'pass') . $nl;
}

// Cleanup
mysqli_query($conn, "DELETE FROM notes WHERE id = $note_id");
itm_script_output_end();
