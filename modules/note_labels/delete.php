<?php
/**
 * Manufacturers Module - Delete
 * 
 * Wrapper for the master CRUD implementation.
 * Handles row deletion via POST in index.php.
 */

$crud_table = $crud_table ?? 'note_labels';
$crud_title = $crud_title ?? 'Note Labels';
$crud_action = 'delete';
require 'index.php';
