<?php
/**

 * 

 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'ops_report_courtesy_call';
$crud_title = $crud_title ?? 'Ops Report Courtesy Call';
$crud_action = 'create';
require 'index.php';
