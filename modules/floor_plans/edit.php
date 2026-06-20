<?php
/**
 * Budget Categories Module - Edit
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = 'floor_plans';
$crud_title = 'Floor Plans';
$crud_action = 'edit';
require 'index.php';
