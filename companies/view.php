<?php
$id = (int)($_GET['id'] ?? 0);
$query = $id > 0 ? ('?id=' . $id) : '';
header('Location: ../modules/companies/view.php' . $query);
exit;
