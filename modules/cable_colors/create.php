<?php
/**
 * Cable Colors Module - Create Entry Point
 *
 * Why: The cable_colors module centralizes CRUD handling in index.php and
 * switches behavior via $crud_action. Keeping create.php as a thin wrapper
 * avoids duplicate function declarations when the shared runtime is loaded.
 */

$crud_table = 'cable_colors';
$crud_title = 'Cable Colors';
$crud_action = 'create';

require __DIR__ . '/index.php';
