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
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    if (PHP_SAPI !== 'cli') {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

$company_id = (int)($_SESSION['company_id'] ?? 1);
$storage_root = ROOT_PATH . 'files/' . $company_id;
$trash_root = $storage_root . '/Trash';

/* Create directories if they don't exist */
if (!is_dir($storage_root)) mkdir($storage_root, 0777, true);
if (!is_dir($trash_root)) mkdir($trash_root, 0777, true);

/* Create initial subfolders */
@mkdir($storage_root . '/Common', 0777, true);
@mkdir($storage_root . '/Departments', 0777, true);
@mkdir($storage_root . '/Private', 0777, true);

/* Create test file */
file_put_contents($storage_root . '/Common/Welcome.txt', "Explorer successfully initialised.\n");

if (PHP_SAPI === 'cli') {
    echo "Setup completed for Company ID: $company_id\n";
} else {
    echo "<h2>Setup completed!</h2>";
    echo "<p>The storage directories for Company ID: <b>$company_id</b> have been created in <b>/files/</b>.</p>";
    echo "<p>You can now open the <a href='index.php'>Explorer</a>.</p>";   
}
