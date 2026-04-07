<?php
/**
 * Companies Module - Edit Wrapper
 * 
 * Reuses the creation logic in an update context.
 */

$_GET['id'] = (int)($_GET['id'] ?? 0);
require 'create.php';
