<?php
require '../../config/config.php';

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    header('Location: index.php');
    exit();
}

$equipment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM equipment WHERE id = $id AND company_id = $company_id"));

if (!$equipment) {
    die("Equipment not found");
}

// Delete photo if exists
if ($equipment['photo_filename'] && file_exists('../../equipment/' . $equipment['photo_filename'])) {
    unlink('../../equipment/' . $equipment['photo_filename']);
}

// Delete from database
if (mysqli_query($conn, "DELETE FROM equipment WHERE id = $id")) {
    header('Location: index.php?deleted=1');
} else {
    die("Error deleting equipment: " . mysqli_error($conn));
}
exit();
?>