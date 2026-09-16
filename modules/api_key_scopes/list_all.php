<?php
/**
 * API Key Scopes Module - List All
 * 
 * A simplified list view for departments, often used for quick reference.
 */

$crud_table = 'api_key_scopes';
$crud_title = 'API Key Scopes';
$crud_action = 'list_all';
?>
<?php
require_once '../../config/config.php';

// Reuse logic from index.php
require 'index.php';
