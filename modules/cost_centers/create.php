<?php
/**
 * 🧾 Cost Centers Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'cost_centers';
$crud_title = '🧾 Cost Centers';
$crud_action = 'create';
require 'index.php';
