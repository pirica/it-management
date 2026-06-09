<?php
/**
 * Idf Positions Module - Delete
 * 
 * Local wrapper for the isolated module implementation.
 * Handles row deletion via POST in index.php.
 */

$crud_table = $crud_table ?? 'idf_positions';
$crud_title = $crud_title ?? 'Idf Positions';
$crud_action = 'delete';
require 'index.php';
