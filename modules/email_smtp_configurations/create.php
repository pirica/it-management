<?php
/**
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'email_smtp_configurations';
$crud_title = $crud_title ?? 'Email Smtp Configurations';
$crud_action = 'create';
require 'index.php';
