<?php
/**
 * IP Addresses Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'ip_addresses';
$crud_title = 'IP Addresses';
$crud_action = 'list_all';
require 'index.php';
