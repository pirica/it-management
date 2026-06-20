<?php
/**
 * Manufacturers Module - Create
 *
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'users';
$crud_title = $crud_title ?? 'Users';
$crud_action = 'create';
require 'index.php';
