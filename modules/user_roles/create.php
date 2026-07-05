<?php
/**

 *
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'user_roles';
$crud_title = $crud_title ?? 'User Roles';
$crud_action = 'create';
require 'index.php';
