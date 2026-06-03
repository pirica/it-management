<?php
/**
 * Visitors Access Log Module
 *
 * Log and manage visitor access to premises.
 */

require '../../config/config.php';

$crud_table = 'visitors_access_log';
$crud_title = 'Visitors Access Log';
$crud_action = $crud_action ?? 'index';

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/handlers.php';
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials/render.php';
