<?php
/**
 * IP Addresses Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'ip_addresses';
$crud_title = 'IP Addresses';
$crud_action = 'view';
require 'index.php';
