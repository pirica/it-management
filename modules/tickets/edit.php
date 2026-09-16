<?php
/**
 * Tickets Module - Edit Wrapper
 * 
 * Sets the ID from the query string and delegates the rendering and 
 * logic to create.php, which handles both insertion and updates.
 */

$_GET['id'] = (int)($_GET['id'] ?? 0);
require 'create.php';
