<?php
require '../../config/config.php';
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    mysqli_query($conn, "DELETE FROM companies WHERE id = $id");
}
header('Location: index.php');
exit;
