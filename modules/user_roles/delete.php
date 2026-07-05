<?php
/**

 *
 * Handles row deletion via POST in index.php.
 */

$crud_table = $crud_table ?? 'user_roles';
$crud_title = $crud_title ?? 'User Roles';
$crud_action = 'delete';
require 'index.php';
