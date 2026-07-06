<?php
/**
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'email_alert_rules';
$crud_title = $crud_title ?? 'Email Alert Rules';
$crud_action = 'create';
require 'index.php';
