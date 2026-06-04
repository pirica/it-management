<?php
/**
 * IP Subnets Module - List All
 * 
 * Wrapper for the master CRUD implementation.
 * Delegates to index.php.
 */

$crud_table = 'ip_subnets';
$crud_title = 'IP Subnets';
$crud_action = 'list_all';
require 'index.php';
