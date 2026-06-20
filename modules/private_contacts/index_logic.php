<?php
require_once '../../config/config.php';

// Security check
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

$employeeId = $_SESSION['employee_id'];
$companyId = $_SESSION['company_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    // Handle Favorite Toggle (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_favorite') {
        $id = (int)$_POST['id'];
        $isFavorite = (int)$_POST['is_favorite'];

        $stmt = $conn->prepare("UPDATE private_contacts SET is_favorite = ? WHERE id = ? AND employee_id = ?");
        $stmt->bind_param("iii", $isFavorite, $id, $employeeId);
        $stmt->execute();
        exit();
    }

    // Handle Delete
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM private_contacts WHERE id = ? AND employee_id = ?");
        $stmt->bind_param("ii", $id, $employeeId);
        $stmt->execute();
        header("Location: index.php?msg=deleted");
        exit();
    }
}

// Fetch Contacts
$search = $_GET['search'] ?? '';
$where = "employee_id = ?";
$params = [$employeeId];
$types = "i";

if ($search) {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email1_value LIKE ? OR organization_name LIKE ? OR phone1_value LIKE ? OR labels LIKE ? OR CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?)";
    $searchParam = "%" . $search . "%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
    $types .= "sssssss";
}

$sql = "SELECT * FROM private_contacts WHERE $where ORDER BY is_favorite DESC, first_name ASC, last_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$contacts = $result->fetch_all(MYSQLI_ASSOC);
