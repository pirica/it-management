<?php
/**
 * Budget Categories Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'floor_plans';
$crud_title = 'Floor Plans';
$crud_action = 'list_all';
require 'index.php';
