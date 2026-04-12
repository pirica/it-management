<?php
/**
 * Catalogs Module - Index
 *
 * Product catalog seeded from multiple suppliers.
 */
$crud_table = 'catalogs';
$crud_title = 'Catalogs';
$crud_action = $crud_action ?? 'index';
require __DIR__ . '/../manufacturers/index.php';
