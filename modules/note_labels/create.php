<?php
/**
 * Configures the action to 'create' and delegates to index.php.
 */

$crud_table = $crud_table ?? 'note_labels';
$crud_title = $crud_title ?? 'Note Labels';
$crud_action = 'create';
require 'index.php';
