<?php
/**
 * Floor Plans — flat metadata list (export/import compliance).
 * Delegates to index.php with $crud_action = list_all.
 */

$crud_table = 'floor_plans';
$crud_title = 'Floor Plans';
$crud_action = 'list_all';
require 'index.php';
