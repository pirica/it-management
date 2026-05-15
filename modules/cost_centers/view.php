<?php
/**
 * 🧾 Cost Centers Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'cost_centers';
$crud_title = '🧾 Cost Centers';
$crud_action = 'view';
require 'index.php';
