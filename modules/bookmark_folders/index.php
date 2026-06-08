<?php
require_once '../../config/config.php';

$crud_table = $crud_table ?? 'bookmark_folders';
$crud_title = $crud_title ?? 'Bookmark Folders';
$crud_action = $crud_action ?? 'index';

// Fetch sample data if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sample_data'])) {
    if (itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $company_id = (int)($_SESSION['company_id'] ?? 0);
        if ($company_id > 0) {
            mysqli_query($conn, "INSERT INTO bookmark_folders (company_id, name) VALUES ($company_id, 'Work'), ($company_id, 'Personal'), ($company_id, 'News')");
        }
    }
}

require 'index.php';
