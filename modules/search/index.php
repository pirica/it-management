<?php
/**
 * Search module landing — command palette UI lives in the global header.
 */

require_once '../../config/config.php';
// Why: Single RBAC chokepoint for POST create/edit/delete (do not duplicate per handler).
itm_crud_mutation_guard_entry($conn, $crud_action, $crud_table);


header('Location: ' . BASE_URL . 'dashboard.php');
exit;
