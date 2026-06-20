<?php
/**
 * Manufacturers Module - Create
 *
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'user_companies';
$crud_title = $crud_title ?? 'User Companies';
$crud_action = 'create';
require 'index.php';
