<?php
require_once '../../config/config.php';

// Security check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$companyId = $_SESSION['company_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    // Handle Favorite Toggle (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_favorite') {
        $id = (int)$_POST['id'];
        $isFavorite = (int)$_POST['is_favorite'];

        $stmt = $conn->prepare("UPDATE private_contacts SET is_favorite = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $isFavorite, $id, $userId);
        $stmt->execute();
        exit();
    }

    // Handle Delete
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM private_contacts WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();
        header("Location: index.php?msg=deleted");
        exit();
    }
}

// Fetch Contacts
$search = $_GET['search'] ?? '';
$where = "user_id = ?";
$params = [$userId];
$types = "i";

if ($search) {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR email1_value LIKE ? OR organization_name LIKE ?)";
    $searchParam = "%" . $search . "%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
    $types .= "ssss";
}

$sql = "SELECT * FROM private_contacts WHERE $where ORDER BY is_favorite DESC, first_name ASC, last_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$contacts = $result->fetch_all(MYSQLI_ASSOC);
