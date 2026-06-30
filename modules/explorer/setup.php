<?php
/**
 * Explorer Setup Utility (UK English)
 *
 * Initialises the storage structure for the Explorer module.
 * Storage is now centrally located in the root /files/ directory,
 * scoped by Company ID.
 */

define('ITM_CLI_SCRIPT', true);
require_once dirname(dirname(__DIR__)) . '/config/config.php';

// Why: Protection Zone - User needs to be logged in and have a company selected.
if (!isset($_SESSION['employee_id']) || !isset($_SESSION['company_id'])) {
    if (PHP_SAPI !== 'cli') {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

$company_id = (int)($_SESSION['company_id'] ?? 1);
$storage_root = ROOT_PATH . 'files/' . $company_id;
$trash_root = $storage_root . '/Trash';

/* Create directories with deny_http .htaccess on every path segment */
itm_ensure_files_storage_directory($storage_root);
itm_ensure_files_storage_directory($trash_root);
itm_ensure_files_storage_directory($storage_root . '/Common');
itm_ensure_files_storage_directory($storage_root . '/Departments');
itm_ensure_files_storage_directory($storage_root . '/Private');

/* Create test file */
file_put_contents($storage_root . '/Common/Welcome.txt', "Explorer successfully initialised.\n");

if (PHP_SAPI === 'cli') {
    echo "Setup completed for Company ID: $company_id\n";
} else {
    echo "<h2>Setup completed!</h2>";
    echo "<p>The storage directories for Company ID: <b>$company_id</b> have been created in <b>/files/</b>.</p>";
    echo "<p>You can now open the <a href='index.php'>Explorer</a>.</p>";
}
