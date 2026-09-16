<?php
$crud_table = 'short_urls';
$crud_title = 'Short URLs';
$crud_action = $crud_action ?? 'index';
require '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete (do not duplicate per handler).
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);

require __DIR__ . '/includes/handlers.php';
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials/render.php';
