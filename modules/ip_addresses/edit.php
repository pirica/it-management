<?php
/**
 * IP Addresses Module - Edit
 * 

 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'ip_addresses';
$crud_title = 'IP Addresses';
$crud_action = 'edit';
require 'index.php';
