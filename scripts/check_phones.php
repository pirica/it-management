<?php
$content = file_get_contents('database.sql');
$lines = explode("\n", $content);
$currentTable = null;
$tables = [];
foreach ($lines as $line) {
    if (preg_match('/CREATE TABLE `([^`]+)`/', $line, $matches)) {
        $currentTable = $matches[1];
        $tables[$currentTable] = [];
    }
    if ($currentTable && preg_match('/^\s+`([^`]+)`/', $line, $matches)) {
        $tables[$currentTable][] = $matches[1];
    }
    if (strpos($line, ');') !== false && strpos($line, 'ENGINE=') !== false) {
        $currentTable = null;
    }
}

foreach ($tables as $table => $cols) {
    if (in_array('phone', $cols)) echo "Table $table has phone\n";
    if (in_array('personal_phone', $cols)) echo "Table $table has personal_phone\n";
}
