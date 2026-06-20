<?php
/**
 * Manufacturers Module - Create
 *
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'user_sidebar_preferences';
$crud_title = $crud_title ?? 'User Sidebar Preferences';
$crud_action = 'create';
require 'index.php';
