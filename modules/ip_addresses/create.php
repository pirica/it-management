<?php
/**
 * IP Addresses Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'ip_addresses';
$crud_title = 'IP Addresses';
$crud_action = 'create';
require 'index.php';
