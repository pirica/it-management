<?php
/**

 * 

 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'ops_report_hotel_figure';
$crud_title = $crud_title ?? 'Ops Report Hotel Figure';
$crud_action = 'create';
require 'index.php';
