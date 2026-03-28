<?php
require '../../config/config.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0) {
    $q = mysqli_query($conn, "SELECT photo_filename FROM equipment WHERE id=$id AND company_id=$company_id LIMIT 1");
    if ($q && mysqli_num_rows($q) === 1) {
        $row = mysqli_fetch_assoc($q);
        if (!empty($row['photo_filename'])) {
            $path = UPLOAD_PATH . $row['photo_filename'];
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
    mysqli_query($conn, "DELETE FROM equipment WHERE id=$id AND company_id=$company_id LIMIT 1");
}
header('Location: index.php');
exit;
