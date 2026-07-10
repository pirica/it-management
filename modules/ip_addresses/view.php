<?php
/**
 * IP Addresses Module - View
 * 

 * Configures the action to 'view' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'ip_addresses';
$crud_title = 'IP Addresses';
$crud_action = 'view';
require 'index.php';
