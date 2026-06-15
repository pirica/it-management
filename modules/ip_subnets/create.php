<?php
/**
 * IP Subnets Module - Create
 * 
 * Wrapper for the master CRUD implementation.
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = 'ip_subnets';
$crud_title = 'IP Subnets';
$crud_action = 'create';
require 'index.php';
