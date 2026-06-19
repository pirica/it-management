<?php
/**
 * Notes Module - Delete
 */

require_once '../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token mismatch");
    }

    $id = (int)($_POST['id'] ?? 0);
    $company_id = $_SESSION['company_id'] ?? 0;
    $user_id = $_SESSION['employee_id'] ?? 0;

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE notes SET active = 0 WHERE id = ? AND company_id = ? AND employee_id = ?");
        $stmt->bind_param("iii", $id, $company_id, $user_id);
        $stmt->execute();
    }
}

header("Location: index.php?msg=deleted");
