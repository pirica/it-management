<?php
/**
 * API Key Scopes Module - View
 * 
 * Read-only detailed view of a single department record.
 */

$crud_table = 'api_key_scopes';
$crud_title = 'API Key Scopes';
$crud_action = 'view';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
