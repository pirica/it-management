<?php
/**
 * IP Subnets Module - Edit
 * 

 * Configures the action to 'edit' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'ip_subnets';
$crud_title = 'IP Subnets';
$crud_action = 'edit';
require 'index.php';
