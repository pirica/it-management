<?php
require_once '../../config/config.php';

// Security check
if (!isset($_SESSION['employee_id'])) {
    header('Location: ../../login.php');
    exit();
}

$employeeId = (int)$_SESSION['employee_id'];
$companyId = (int)$_SESSION['company_id'];

// Handle POST actions (form/AJAX only — JSON import_excel_rows is handled on index.php).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    itm_require_post_csrf();

    // Handle Favorite Toggle (AJAX)
    if ($_POST['action'] === 'toggle_favorite') {
        $id = (int)$_POST['id'];
        $isFavorite = (int)$_POST['is_favorite'];

        $stmt = $conn->prepare('UPDATE private_contacts SET is_favorite = ? WHERE id = ? AND employee_id = ?');
        $stmt->bind_param('iii', $isFavorite, $id, $employeeId);
        $stmt->execute();
        exit();
    }

    // Handle Delete
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare('DELETE FROM private_contacts WHERE id = ? AND employee_id = ?');
        $stmt->bind_param('ii', $id, $employeeId);
        $stmt->execute();
        header('Location: index.php?msg=deleted');
        exit();
    }
}
