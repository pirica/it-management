<?php
/**
 * Equipment Module - Edit Wrapper
 * 
 * Reuses the sophisticated logic from create.php in an update context.
 */

$_GET['id'] = isset($_GET['id']) ? (int)$_GET['id'] : 0;
require 'create.php';
