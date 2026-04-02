<?php
$id = (int)($_GET['id'] ?? 0);

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/companies/view.php'));
$currentDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$baseDir = preg_replace('#/companies$#', '', $currentDir);
$target = ($baseDir === '' ? '' : $baseDir) . '/modules/companies/view.php';

if ($id > 0) {
    $target .= '?id=' . $id;
}

header('Location: ' . $target, true, 302);
exit;
