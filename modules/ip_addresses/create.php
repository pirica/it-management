<?php
/**
 * IP Addresses Module - Create
 * 

 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'ip_addresses';
$crud_title = 'IP Addresses';
$crud_action = 'create';
require 'index.php';
