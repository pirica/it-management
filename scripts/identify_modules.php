<?php
define('ITM_CLI_SCRIPT', true);
require_once __DIR__ . '/../config/config.php';

$modulesDir = __DIR__ . '/../modules';
$modules = [];

foreach (scandir($modulesDir) as $module) {
    if ($module === '.' || $module === '..' || !is_dir($modulesDir . '/' . $module)) {
        continue;
    }

    $indexPath = $modulesDir . '/' . $module . '/index.php';
    if (!is_file($indexPath)) {
        continue;
    }

    $content = file_get_contents($indexPath);
    $crudTable = null;
    if (preg_match('/\$crud_table\s*=\s*(?:\$crud_table\s*\?\?\s*)?[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
        $crudTable = $matches[1];
    }

    $modules[$module] = [
        'path' => 'modules/' . $module,
        'crud_table' => $crudTable,
        'is_standard' => ($crudTable !== null),
    ];
}

echo json_encode($modules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
