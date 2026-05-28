<?php
/**
 * Floor Plans Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'floor_plans';
$crud_title = 'Floor Plans';
$crud_action = 'create';
require 'index.php';
