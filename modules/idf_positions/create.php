<?php
/**
 * Idf Positions Module - Create
 * 
 * Local wrapper for the isolated module implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'idf_positions';
$crud_title = $crud_title ?? 'Idf Positions';
$crud_action = 'create';
require 'index.php';
