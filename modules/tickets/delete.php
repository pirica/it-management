<?php require '../../config/config.php'; $id=(int)($_GET['id']??0); if($id>0) mysqli_query($conn,"DELETE FROM tickets WHERE id=$id AND company_id=$company_id"); header('Location: index.php'); exit;
