<?php
/**
 * API Key Scopes Module - Edit
 *
 * Read/write edit view for a single department record.
 */

$crud_table = 'api_key_scopes';
$crud_title = 'API Key Scopes';
$crud_action = 'edit';
?>
<?php
require_once '../../config/config.php';

// Reuse shared CRUD logic from index.php while preserving the edit action.
require 'index.php';
