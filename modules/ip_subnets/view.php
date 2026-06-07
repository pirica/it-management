<?php
/**
 * IP Subnets Module - View
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = 'ip_subnets';
$crud_title = 'IP Subnets';
$crud_action = 'view';
require 'index.php';
