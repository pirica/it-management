<?php
require '../../config/config.php';

$id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
} else {
    $id = (int)($_GET['id'] ?? 0);
}

if ($id > 0) {
    $sql = 'DELETE FROM employees WHERE id=' . $id . ' AND company_id=' . (int)$company_id . ' LIMIT 1';
    mysqli_query($conn, $sql);
}

header('Location: index.php');
exit;
