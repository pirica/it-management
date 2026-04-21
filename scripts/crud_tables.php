<?php
/**
 * CRUD Table Mapper
 *
 * Why: quickly audits module-to-table mapping by reading each module's index.php
 * and printing the first $crud_table assignment (if present) with a sidebar link.
 *
 * Usage:
 *   php scripts/crud_tables.php
 */

$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$modulesPath = $rootPath . 'modules';

if (!is_dir($modulesPath)) {
    fwrite(STDERR, "Modules directory not found: {$modulesPath}\n");
    exit(1);
}

$moduleDirs = array_values(array_filter(scandir($modulesPath), static function ($entry) use ($modulesPath) {
    return $entry !== '.' && $entry !== '..' && is_dir($modulesPath . DIRECTORY_SEPARATOR . $entry);
}));

sort($moduleDirs, SORT_NATURAL | SORT_FLAG_CASE);

foreach ($moduleDirs as $moduleName) {
    $indexPath = $modulesPath . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'index.php';
    $crudLineNumber = null;
    $crudLineText = null;

    if (is_file($indexPath)) {
        $lines = @file($indexPath);
        if (is_array($lines)) {
            foreach ($lines as $lineNumber => $lineText) {
                if (preg_match('/\$crud_table\s*=/', $lineText)) {
                    $crudLineNumber = $lineNumber + 1;
                    $crudLineText = trim($lineText);
                    break;
                }
            }
        }
    }

    echo $moduleName . PHP_EOL;
    if ($crudLineNumber !== null && $crudLineText !== null) {
        echo '<br>';
        echo '/index.php line ' . $crudLineNumber . ': ' . $crudLineText . PHP_EOL;
        echo '<br>';
    } else {
        echo '<br>';
        echo '/index.php: (not set in index.php)' . PHP_EOL;
        echo '<br>';
    }
    echo '<br>';
    echo 'sidebar link: modules/' . $moduleName . '/' . PHP_EOL . PHP_EOL;
    echo '<br>';
}
