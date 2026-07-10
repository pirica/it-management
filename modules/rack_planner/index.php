<?php
/**
 * Rack Planner Module
 *
 * Standard CRUD for Rack Planner with custom visualization.
 */

require '../../config/config.php';

$crud_table = $crud_table ?? 'rack_planner';
$crud_title = 'Rack Planner';
$crud_action = $crud_action ?? 'index';

require __DIR__ . '/includes/functions.php';
require __DIR__ . '/includes/handlers.php';
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials/render.php';
