<?php
/**
 * VLANs Module - Delete
 *
 * Why: Route POST delete/bulk/clear actions through index.php so soft-delete stays in one place.
 */
$crud_table = 'vlans';
$crud_title = 'Vlans';
$crud_action = 'delete';
require __DIR__ . '/index.php';
