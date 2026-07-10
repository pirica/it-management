<?php
/**
 * Budget Categories Module - Delete
 * 

 * Handles single or bulk deletion via POST in index.php.
 */

$crud_table = $crud_table ?? 'floor_plans';
$crud_title = 'Floor Plans';
$crud_action = 'delete';
$fp_delete_return = 'list_all';
require 'index.php';
