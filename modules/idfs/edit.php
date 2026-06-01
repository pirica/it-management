<?php
/**
 * QA / navigation entry — edit form lives on index.php?edit_idf=.
 */
$id = (int)($_GET['id'] ?? 0);
$target = 'index.php';
if ($id > 0) {
    $target .= '?edit_idf=' . $id;
}
header('Location: ' . $target);
exit;
