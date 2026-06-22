<?php
/**

 * 

 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'ops_report_fb_outlet';
$crud_title = $crud_title ?? 'Ops Report Fb Outlet';
$crud_action = 'create';
require 'index.php';
