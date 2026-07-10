<?php
/**
 * 🧾 Cost Centers Module - Edit
 * 

 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'cost_centers';
$crud_title = '🧾 Cost Centers';
$crud_action = 'edit';
require 'index.php';
