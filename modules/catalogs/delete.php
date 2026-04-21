<?php
$crud_table = $crud_table ?? 'catalogs';
$crud_title = $crud_title ?? 'Catalogs';
$crud_action = 'delete';

// Why: delete endpoint is POST-driven; avoid rendering an empty state on direct GET hits.
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Location: index.php');
    exit;
}

require __DIR__ . '/index.php';
