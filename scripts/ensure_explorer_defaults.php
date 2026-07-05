<?php
/**
 * Maintenance Script: Ensure Explorer Default Folders
 *
 * This script iterates through all companies in the database and ensures
 * that the default Explorer folders (Common, Departments, Private, Trash) exist
 * in their storage directory.
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/lib/script_browser_nav.php';
require_once __DIR__ . '/lib/script_cli_output.php';

// Why: Destructive or maintenance tools are admin-only in browser.
if (PHP_SAPI !== 'cli' && !itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

$nl = itm_script_output_nl();
itm_script_output_begin();
if (PHP_SAPI !== 'cli') {
    itm_script_browser_nav_echo();
    echo "<h1>Ensure Explorer Default Folders</h1>";
}

echo colorText("Starting Explorer default folders backfill...", 'info') . $nl;

$sql = "SELECT id, company FROM companies";
$res = mysqli_query($conn, $sql);

if (!$res) {
    echo colorText("Error fetching companies: " . mysqli_error($conn), 'fail') . $nl;
    exit(1);
}

$count = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $company_id = (int)$row['id'];
    $company_name = $row['company'];
    $storage_root = ROOT_PATH . 'files/' . $company_id;

    echo "Checking Company ID: $company_id ($company_name)..." . $nl;

    $folders = [
        $storage_root,
        $storage_root . '/Common',
        $storage_root . '/Departments',
        $storage_root . '/Private',
        $storage_root . '/Trash'
    ];

    foreach ($folders as $folder) {
        if (!is_dir($folder)) {
            echo "  - Creating: " . basename($folder) . $nl;
        }
        itm_ensure_files_storage_directory($folder);
    }
    $count++;
}

echo $nl . colorText("Completed. Checked $count companies.", 'pass') . $nl;
itm_script_output_end();
