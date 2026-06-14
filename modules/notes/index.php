<?php
/**
 * Notes Module - Index - manages notes with a Google Keep style UI.
 */

require_once "../../config/config.php";
require_once ROOT_PATH . "includes/notes_visibility.php";

$crud_table = "notes";
$crud_title = "Notes";
$crud_action = $crud_action ?? "index";
$logged_user_id = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;
$company_id = isset($_SESSION["company_id"]) ? (int)$_SESSION["company_id"] : 0;

// Metadata
$user_tags = [];
$stmtUL = $conn->prepare("SELECT DISTINCT label FROM note_labels WHERE company_id = ? AND user_id = ? AND active = 1 ORDER BY label ASC");
$stmtUL->bind_param("ii", $company_id, $logged_user_id);
$stmtUL->execute();
$resUserLabels = $stmtUL->get_result();
if ($resUserLabels) { while ($row = mysqli_fetch_assoc($resUserLabels)) { $user_tags[] = $row["label"]; } }

$categories = [];
$stmtCat = $conn->prepare("SELECT id, label as name FROM note_labels WHERE (company_id = ? AND user_id = ?) OR (company_id IS NULL)");
$stmtCat->bind_param("ii", $company_id, $logged_user_id);
$stmtCat->execute();
$resCat = $stmtCat->get_result();
if ($resCat) { while ($row = mysqli_fetch_assoc($resCat)) { $categories[$row['id']] = $row; } }

$users = [];
$stmtUsers = $conn->prepare("SELECT id, username FROM users WHERE company_id = ? AND active = 1");
$stmtUsers->bind_param("i", $company_id);
$stmtUsers->execute();
$resUser = $stmtUsers->get_result();
if ($resUser) { while ($row = mysqli_fetch_assoc($resUser)) { $users[$row['id']] = $row; } }

// Check for existing data to show/hide sidebar items
$hasPinned = false;
$stmtP = $conn->prepare("SELECT 1 FROM notes WHERE company_id = ? AND user_id = ? AND is_pinned = 1 AND active = 1 LIMIT 1");
$stmtP->bind_param("ii", $company_id, $logged_user_id);
$stmtP->execute();
if ($stmtP->get_result()->fetch_assoc()) $hasPinned = true;

$hasImages = false;
$stmtI = $conn->prepare("SELECT 1 FROM notes WHERE company_id = ? AND user_id = ? AND images_json IS NOT NULL AND active = 1 LIMIT 1");
$stmtI->bind_param("ii", $company_id, $logged_user_id);
$stmtI->execute();
if ($stmtI->get_result()->fetch_assoc()) $hasImages = true;

$hasImportant = false;
$stmtImp = $conn->prepare("SELECT 1 FROM notes WHERE company_id = ? AND user_id = ? AND is_important = 1 AND active = 1 LIMIT 1");
$stmtImp->bind_param("ii", $company_id, $logged_user_id);
$stmtImp->execute();
if ($stmtImp->get_result()->fetch_assoc()) $hasImportant = true;

$departments = [];
$stmtDept = $conn->prepare("SELECT id, name, code FROM departments WHERE company_id = ? OR company_id IS NULL");
$stmtDept->bind_param("i", $company_id);
$stmtDept->execute();
$resDept = $stmtDept->get_result();
if ($resDept) { while ($row = mysqli_fetch_assoc($resDept)) { $departments[$row['id']] = $row; } }

// Standard CRUD processing
$editId = (int)($_GET["id"] ?? 0);
$csrfToken = itm_get_csrf_token();

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_GET["ajax_action"])) {
    if (!itm_validate_csrf_token($_POST["csrf_token"] ?? "")) {
        die("CSRF token mismatch");
    }

    $action = $_POST["bulk_action"] ?? "";
    if ($action === "delete" && !empty($_POST["ids"])) {
        $ids = array_map("intval", $_POST["ids"]);
        foreach ($ids as $id) {
            $stmt = $conn->prepare("UPDATE notes SET active = 0 WHERE id = ? AND company_id = ? AND user_id = ?");
            $stmt->bind_param("iii", $id, $company_id, $logged_user_id);
            $stmt->execute();
        }
        header("Location: index.php?msg=deleted");
        die();
    }

    if ($action === "single_delete" && $editId > 0) {
        $stmt = $conn->prepare("UPDATE notes SET active = 0 WHERE id = ? AND company_id = ? AND user_id = ?");
        $stmt->bind_param("iii", $editId, $company_id, $logged_user_id);
        $stmt->execute();
        header("Location: index.php?msg=deleted");
        die();
    }

    if (in_array($crud_action, ["create", "edit"], true)) {
        $title = $_POST["title"] ?? "";
        $content = $_POST["content"] ?? "";
        $is_checklist = isset($_POST["is_checklist"]) ? 1 : 0;
        $color = $_POST["color"] ?? null;
        $is_pinned = isset($_POST["is_pinned"]) ? 1 : 0;
        $is_important = isset($_POST["is_important"]) ? 1 : 0;
        $is_archived = isset($_POST["is_archived"]) ? 1 : 0;
        $reminder_at = !empty($_POST["reminder_at"]) ? $_POST["reminder_at"] : null;

        $image_files = $_POST['existing_images'] ?? [];
        if (!empty($_FILES['images'])) {
            $upload_dir = ROOT_PATH . "files/$company_id/Private/{$_SESSION['username']}_$logged_user_id/notes/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp_name) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $name = basename($_FILES['images']['name'][$i]);
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $safe_name = uniqid('note_') . '.' . $ext;
                    if (move_uploaded_file($tmp_name, $upload_dir . $safe_name)) $image_files[] = $safe_name;
                }
            }
        }
        $images_json = !empty($image_files) ? json_encode(array_values($image_files)) : null;

        $shared_users = $_POST["shared_with_json"] ?? [];
        $shared_with_json = !empty($shared_users) ? json_encode(array_map('intval', $shared_users)) : null;

        $checklist_data = [];
        if ($is_checklist && !empty($_POST['checklist_text'])) {
            foreach ($_POST['checklist_text'] as $i => $text) {
                if (trim($text) !== '') {
                    $checklist_data[] = ['text' => $text, 'completed' => isset($_POST['checklist_completed'][$i]) ? 1 : 0];
                }
            }
        }
        $checklist_json = !empty($checklist_data) ? json_encode($checklist_data) : null;

        if ($crud_action === "edit" && $editId > 0) {
            $stmt = $conn->prepare("UPDATE notes SET title=?, content=?, is_checklist=?, color=?, is_pinned=?, is_important=?, is_archived=?, reminder_at=?, checklist_json=?, images_json=?, shared_with_json=? WHERE id=? AND company_id=? AND user_id=?");
            $stmt->bind_param("ssisiiisssiiii", $title, $content, $is_checklist, $color, $is_pinned, $is_important, $is_archived, $reminder_at, $checklist_json, $images_json, $shared_with_json, $editId, $company_id, $logged_user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO notes (company_id, user_id, title, content, is_checklist, color, is_pinned, is_important, is_archived, reminder_at, checklist_json, images_json, shared_with_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissisiiissss", $company_id, $logged_user_id, $title, $content, $is_checklist, $color, $is_pinned, $is_important, $is_archived, $reminder_at, $checklist_json, $images_json, $shared_with_json);
        }

        if ($stmt->execute()) {
            if ($crud_action === "edit") { $stmtD = $conn->prepare("DELETE FROM note_labels WHERE note_id = ?"); $stmtD->bind_param("i", $editId); $stmtD->execute(); $noteId = $editId; }
            else $noteId = mysqli_insert_id($conn);

            $labels = $_POST["category_id"] ?? [];
            foreach ($labels as $label_id) {
                if (empty($label_id) || $label_id == '__add_new__') continue;
                $label_name = is_numeric($label_id) ? null : $label_id;
                if (is_numeric($label_id)) {
                    $stmtGL = $conn->prepare("SELECT label FROM note_labels WHERE id = ?"); $stmtGL->bind_param("i", $label_id); $stmtGL->execute(); $resL = $stmtGL->get_result();
                    if ($rowL = mysqli_fetch_assoc($resL)) $label_name = $rowL['label'];
                }
                if (!empty($label_name)) {
                    $stmtL = $conn->prepare("INSERT INTO note_labels (company_id, user_id, note_id, label) VALUES (?, ?, ?, ?)");
                    $stmtL->bind_param("iiis", $company_id, $logged_user_id, $noteId, $label_name);
                    $stmtL->execute();
                }
            }
            header("Location: index.php?msg=saved");
            die();
        }
    }
}

if (isset($_GET["download_zip"])) {
    $zipName = basename($_GET["download_zip"]);
    $zipPath = sys_get_temp_dir() . '/' . $zipName;
    if (file_exists($zipPath) && strpos($zipName, '_download.zip') !== false) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        unlink($zipPath);
        exit;
    }
}

if (isset($_GET["ajax_action"])) {
    if (!itm_validate_csrf_token($_POST["csrf_token"] ?? $_POST["CSRF_TOKEN"] ?? "")) {
        echo json_encode(["ok" => false, "error" => "CSRF token mismatch"]);
        die();
    }

    $action = $_GET["ajax_action"];
    if ($action === "quick_add") {
        $title = $_POST["title"] ?? "";
        $is_checklist = (int)($_POST["is_checklist"] ?? 0);
        $reminder_at = !empty($_POST["reminder_at"]) ? $_POST["reminder_at"] : null;
        $image_files = [];
        if (!empty($_FILES['images'])) {
            $upload_dir = ROOT_PATH . "files/$company_id/Private/{$_SESSION['username']}_$logged_user_id/notes/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp_name) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $safe_name = uniqid('note_') . '.' . pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                    if (move_uploaded_file($tmp_name, $upload_dir . $safe_name)) $image_files[] = $safe_name;
                }
            }
        }
        $images_json = !empty($image_files) ? json_encode($image_files) : null;
        $stmt = $conn->prepare("INSERT INTO notes (company_id, user_id, title, is_checklist, images_json, reminder_at) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iisiss", $company_id, $logged_user_id, $title, $is_checklist, $images_json, $reminder_at);
            if ($stmt->execute()) echo json_encode(["ok" => true]);
            else echo json_encode(["ok" => false, "error" => $stmt->error]);
        } else {
            echo json_encode(["ok" => false, "error" => $conn->error]);
        }
        die();
    }
    if ($action === "toggle_pinned") {
        $id = (int)($_POST["id"] ?? 0);
        $is_pinned = (int)($_POST["is_pinned"] ?? 0);
        $stmt = $conn->prepare("UPDATE notes SET is_pinned = ? WHERE id = ? AND company_id = ? AND user_id = ?");
        $stmt->bind_param("iiii", $is_pinned, $id, $company_id, $logged_user_id);
        if ($stmt->execute()) echo json_encode(["ok" => true]); else echo json_encode(["ok" => false]);
        die();
    }
    if ($action === "toggle_important") {
        $id = (int)($_POST["id"] ?? 0);
        $is_important = (int)($_POST["is_important"] ?? 0);
        $stmt = $conn->prepare("UPDATE notes SET is_important = ? WHERE id = ? AND company_id = ? AND user_id = ?");
        $stmt->bind_param("iiii", $is_important, $id, $company_id, $logged_user_id);
        if ($stmt->execute()) echo json_encode(["ok" => true]); else echo json_encode(["ok" => false]);
        die();
    }
    if ($action === "rename_tag") {
        $old = $_POST["old_name"] ?? "";
        $new = $_POST["new_name"] ?? "";
        if ($new === "") { echo json_encode(["ok" => false, "error" => "Tag name cannot be empty"]); die(); }
        $stmtC = $conn->prepare("SELECT 1 FROM note_labels WHERE user_id = ? AND label = ? AND company_id = ? LIMIT 1");
        $stmtC->bind_param("isi", $logged_user_id, $new, $company_id);
        $stmtC->execute();
        if ($stmtC->get_result()->fetch_assoc()) { echo json_encode(["ok" => false, "error" => "Tag already exists"]); die(); }
        $stmt = $conn->prepare("UPDATE note_labels SET label = ? WHERE label = ? AND user_id = ? AND company_id = ?");
        $stmt->bind_param("ssii", $new, $old, $logged_user_id, $company_id);
        if ($stmt->execute()) echo json_encode(["ok" => true]); else echo json_encode(["ok" => false]);
        die();
    }
    if ($action === "delete_tag") {
        $name = $_POST["name"] ?? "";
        $stmt = $conn->prepare("DELETE FROM note_labels WHERE label = ? AND user_id = ? AND company_id = ?");
        $stmt->bind_param("sii", $name, $logged_user_id, $company_id);
        if ($stmt->execute()) echo json_encode(["ok" => true]); else echo json_encode(["ok" => false]);
        die();
    }
    if ($action === "add_tag") {
        $name = trim($_POST["name"] ?? "");
        if ($name === "") { echo json_encode(["ok" => false, "error" => "Tag name cannot be empty"]); die(); }
        $stmtC = $conn->prepare("SELECT 1 FROM note_labels WHERE user_id = ? AND label = ? AND company_id = ? LIMIT 1");
        $stmtC->bind_param("isi", $logged_user_id, $name, $company_id);
        $stmtC->execute();
        if ($stmtC->get_result()->fetch_assoc()) { echo json_encode(["ok" => false, "error" => "Tag already exists"]); die(); }
        $stmt = $conn->prepare("INSERT INTO note_labels (company_id, user_id, label) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $company_id, $logged_user_id, $name);
        if ($stmt->execute()) echo json_encode(["ok" => true]); else echo json_encode(["ok" => false]);
        die();
    }
    if ($action === "download_all_images") {
        $id = (int)($_POST["id"] ?? 0);
        $stmt = $conn->prepare("SELECT title, images_json FROM notes WHERE id = ? AND company_id = ? AND user_id = ?");
        $stmt->bind_param("iii", $id, $company_id, $logged_user_id);
        $stmt->execute();
        $resD = $stmt->get_result()->fetch_assoc();
        $imgs = json_decode($resD['images_json'] ?? '[]', true);
        if (empty($imgs)) { echo json_encode(["ok" => false, "error" => "No images to download"]); die(); }

        $title = $resD['title'] ?: "note_{$id}";
        $safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title);
        $zip = new ZipArchive();
        $zipName = "{$safeTitle}_download.zip";
        $zipPath = sys_get_temp_dir() . '/' . $zipName;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            foreach ($imgs as $img) {
                $filePath = ROOT_PATH . "files/{$company_id}/Private/{$_SESSION['username']}_{$logged_user_id}/notes/{$img}";
                if (file_exists($filePath)) $zip->addFile($filePath, $img);
            }
            $zip->close();
            echo json_encode(["ok" => true, "zip_url" => "index.php?download_zip=" . urlencode($zipName)]);
        } else {
            echo json_encode(["ok" => false, "error" => "Could not create ZIP file"]);
        }
        die();
    }
}

// Data fetching
$filter = $_GET["filter"] ?? "all";
$search = $_GET["search"] ?? "";

if ($crud_action === "index") {
    if ($filter === "garbage") {
        $sql = "SELECT t.* FROM notes t WHERE t.company_id = ? AND t.active = 0";
    } else {
        $sql = "SELECT t.* FROM notes t WHERE t.company_id = ? AND t.active = 1";
    }
    $params = [$company_id];
    $types = "i";
    $visibilitySql = itm_notes_visibility_sql("t");
    $sql .= " AND ($visibilitySql)";
    $types .= "ii";
    $params[] = $logged_user_id;
    $params[] = $logged_user_id;

    if ($filter === "reminders") {
        $sql .= " AND t.reminder_at IS NOT NULL";
    } elseif ($filter === "tag") {
        $label_filter = $_GET["label"] ?? "";
        $sql .= " AND EXISTS (SELECT 1 FROM note_labels nl WHERE nl.note_id = t.id AND nl.label = ? AND nl.active = 1)";
        $types .= "s";
        $params[] = $label_filter;
    } elseif ($filter === "archive") {
        $sql .= " AND t.is_archived = 1";
    } elseif ($filter === "pinned") {
        $sql .= " AND t.is_pinned = 1 AND t.is_archived = 0";
    } elseif ($filter === "images") {
        $sql .= " AND t.images_json IS NOT NULL AND t.is_archived = 0";
    } elseif ($filter === "important") {
        $sql .= " AND t.is_important = 1 AND t.is_archived = 0";
    } elseif ($filter === "all") {
        $sql .= " AND t.is_archived = 0";
    } else {
        $sql .= " AND t.is_archived = 0";
    }

    if ($search !== "") {
        $sql .= " AND (t.title LIKE ? OR t.content LIKE ?)";
        $types .= "ss";
        $searchTerm = "%$search%";
        $params[] = $searchTerm; $params[] = $searchTerm;
    }

    $sql .= " ORDER BY t.is_pinned DESC, t.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $notes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
} elseif ($crud_action === "edit" || $crud_action === "view") {
    $stmt = $conn->prepare("SELECT * FROM notes WHERE id = ? AND company_id = ? AND active = 1");
    $stmt->bind_param("ii", $editId, $company_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    if (!$data) { header("Location: index.php"); die(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Management - Notes</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .notes-container { display: flex; height: calc(100vh - 120px); background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .notes-sidebar { width: 280px; background: var(--bg-secondary); border-right: 1px solid var(--border); padding: 20px 0; display: flex; flex-direction: column; }
        .notes-sidebar-item { padding: 10px 25px; display: flex; align-items: center; cursor: pointer; color: var(--text-primary); text-decoration: none; transition: background 0.2s; }
        .notes-sidebar-item:hover { background: var(--bg-tertiary); }
        .notes-sidebar-item.active { background: #e7f3ff; color: var(--accent); font-weight: 500; }
        .notes-content { flex: 1; padding: 30px 50px; overflow-y: auto; position: relative; }
        .note-item { border-radius: 8px; padding: 12px 15px; margin-bottom: 8px; display: flex; align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border); transition: transform 0.1s; }
        .note-item:hover { transform: translateY(-1px); box-shadow: var(--shadow-sm); }
        .note-star { cursor: pointer; font-size: 20px; color: var(--text-tertiary); margin-left: 10px; }
        .note-star.active { color: var(--accent); }
        .color-option input[type="radio"]:checked + div { border-color: var(--accent) !important; }
        .empty-state { text-align: center; padding: 100px 50px; color: var(--text-tertiary); }
        .quick-add { border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; background: var(--bg-secondary); margin-bottom: 24px; }
        .quick-add input { flex: 1; border: none; background: transparent; outline: none; font-size: 16px; color: var(--text-primary); }
        .quick-add-icon { cursor: pointer; color: var(--text-secondary); font-size: 20px; position: relative; }
        .quick-add-dropdown { position: absolute; top: 100%; right: 0; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 4px; box-shadow: var(--shadow-lg); z-index: 1000; min-width: 180px; display: none; margin-top: 5px; }
        .quick-add-dropdown.show { display: block; }
        .quick-add-dropdown-header { padding: 8px 15px; border-bottom: 1px solid var(--border); font-weight: 600; text-align: center; font-size: 12px; color: var(--text-secondary); }
        .quick-add-dropdown-item { padding: 10px 15px; display: flex; align-items: center; gap: 12px; cursor: pointer; color: var(--text-primary); transition: background 0.2s; font-size: 14px; text-align: left; }
        .quick-add-dropdown-item:hover { background: var(--bg-tertiary); }
        .quick-add-dropdown-item i { width: 16px; text-align: center; font-style: normal; }
        .quick-add-dropdown-item .item-label { flex: 1; }
        .quick-add-dropdown-item.danger { color: var(--danger); border-top: 1px solid var(--border); margin-top: 5px; }

        /* Modal Styles */
        .modal-backdrop { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: #000; opacity: 0.5; z-index: 1040; display: none; }
        .modal-backdrop.show { display: block; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; overflow-y: auto; z-index: 1050; }
        .modal.show { display: block; }
        .modal-dialog { position: relative; width: auto; margin: 1.75rem auto; max-width: 500px; pointer-events: none; }
        .modal-content { position: relative; display: flex; flex-direction: column; width: 100%; pointer-events: auto; background: var(--bg-primary); border: 1px solid var(--border); border-radius: 0.3rem; outline: 0; box-shadow: var(--shadow-lg); }
        .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--border); }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }
        .close { background: transparent; border: 0; font-size: 1.5rem; cursor: pointer; color: var(--text-primary); }
    </style>
</head>
<body>
<div class="container">
    <?php include ROOT_PATH . "includes/sidebar.php"; ?>
    <div class="main-content">
	    <?php include ROOT_PATH . "includes/header.php"; ?>
        <div class="content">
            <div class="notes-container">
                <div class="notes-sidebar">
                    <a href="index.php" class="notes-sidebar-item <?php echo ($filter === "all" || $filter === "") ? "active" : ""; ?>">💡 Notes</a>
                    <a href="?filter=reminders" class="notes-sidebar-item <?php echo $filter === "reminders" ? "active" : ""; ?>">🔔 Reminders</a>
                    <?php foreach ($user_tags as $ul): ?>
                        <a href="?filter=tag&label=<?php echo urlencode($ul); ?>" class="notes-sidebar-item <?php echo ($filter === "tag" && ($_GET["label"] ?? "") === $ul) ? "active" : ""; ?>">🏷️ <?php echo sanitize($ul); ?></a>
                    <?php endforeach; ?>
                    <a href="#" class="notes-sidebar-item" onclick="openEditTagsModal(); return false;">✏️ Edit tags</a>
                    <a href="?filter=archive" class="notes-sidebar-item <?php echo $filter === "archive" ? "active" : ""; ?>">📥 Archive</a>
                    <a href="?filter=garbage" class="notes-sidebar-item <?php echo $filter === "garbage" ? "active" : ""; ?>">🗑️ Garbage</a>
                    <?php if ($hasPinned): ?><a href="?filter=pinned" class="notes-sidebar-item <?php echo $filter === "pinned" ? "active" : ""; ?>">📌 Pinned</a><?php endif; ?>
                    <?php if ($hasImages): ?><a href="?filter=images" class="notes-sidebar-item <?php echo $filter === "images" ? "active" : ""; ?>">🖼️ Images</a><?php endif; ?>
                    <?php if ($hasImportant): ?><a href="?filter=important" class="notes-sidebar-item <?php echo $filter === "important" ? "active" : ""; ?>">★ Important</a><?php endif; ?>
                </div>
                <div class="notes-content">
                    <?php if ($crud_action === "index"): ?>
                        <div class="notes-header">
                            <h1>
                                <?php
                                    if ($filter === "reminders") echo "🔔 Reminders";
                                    elseif ($filter === "tag") echo "🏷️ " . sanitize($_GET["label"] ?? "");
                                    elseif ($filter === "archive") echo "📥 Archive";
                                    elseif ($filter === "garbage") echo "🗑️ Garbage";
                                    elseif ($filter === "pinned") echo "📌 Pinned";
                                    elseif ($filter === "images") echo "🖼️ Images";
                                    elseif ($filter === "important") echo "★ Important";
                                    else echo "💡 Notes";
                                ?>
                            </h1>
                            <div class="date-subtitle"><?php echo date("l, F j"); ?></div>
                        </div>

                        <div class="quick-add">
                            <div style="display: flex; align-items: center; width: 100%;">
                                <input type="text" id="quickAddInput" placeholder="Take a note..." onkeypress="if(event.key==='Enter') quickAdd()">
                                <div style="display: flex; gap: 15px; margin-left: 10px;">
                                    <div class="quick-add-icon" onclick="toggleChecklistMode()" title="New list">☑️</div>
                                    <div class="quick-add-icon" onclick="triggerQuickImageUpload()" title="New note with image">🖼️</div>
                                    <div class="quick-add-icon" id="quickReminderBtn" onclick="toggleQuickReminderDropdown(event)" title="New note with reminder">
                                        🔔
                                        <div class="quick-add-dropdown" id="quickReminderDropdown">
                                            <div class="quick-add-dropdown-header">Reminder</div>
                                            <div class="quick-add-dropdown-item" onclick="setQuickReminder('later', event)">🕒 <span class="item-label">Later today</span></div>
                                            <div class="quick-add-dropdown-item" onclick="setQuickReminder('tomorrow', event)">🕒 <span class="item-label">Tomorrow</span></div>
                                            <div class="quick-add-dropdown-item" onclick="setQuickReminder('next_week', event)">🕒 <span class="item-label">Next week</span></div>
                                            <div class="quick-add-dropdown-item" onclick="setQuickReminder('choose', event)">🕒 <span class="item-label">Pick a date</span></div>
                                            <div class="quick-add-dropdown-item danger" onclick="setQuickReminder('remove', event)">🗑️ <span class="item-label">Remove</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="file" id="quickAddImageInput" multiple accept="image/*" style="display: none;" onchange="handleQuickImageSelect(event)">
                            <div id="quickAddPreview" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;"></div>
                            <input type="hidden" id="quickAddIsChecklist" value="0">
                            <input type="hidden" id="quickAddReminderAt" value="">
                        </div>

                        <div class="notes-list">
                            <?php if (empty($notes)): ?>
                                <div class="empty-state"><i style="font-size: 48px; display: block; margin-bottom: 20px; opacity: 0.5;">📋</i><p>No notes found.</p></div>
                            <?php else: ?>
                                <?php foreach ($notes as $note): ?>
                                    <div class="note-item" style="background-color: <?php echo $note["color"] ?? "var(--bg-primary)"; ?>;">
                                        <div class="note-main" onclick="location.href='view.php?id=<?php echo $note["id"]; ?>'" style="flex: 1; cursor: pointer;">
                                            <div class="note-title" style="font-weight: 600;"><?php echo sanitize($note["title"] ?: '(Untitled)'); ?></div>
                                            <div class="note-meta" style="font-size: 12px; color: var(--text-secondary); display: flex; gap: 10px;">
                                                <span>Notes</span>
                                                <?php if ($note['is_pinned']): ?><span>• 📌</span><?php endif; ?>
                                                <?php if (!empty($note['images_json'])): ?><span>• 🖼️</span><?php endif; ?>
                                                <?php if ($note['is_checklist']): ?><span>• ☑️</span><?php endif; ?>
                                                <?php if (!empty($note['reminder_at'])): ?><span>• 🔔 <?php echo date("M j, H:i", strtotime($note['reminder_at'])); ?></span><?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="note-star <?php echo $note["is_pinned"] ? "active" : ""; ?>" onclick="togglePinned(<?php echo $note["id"]; ?>, this)" data-pinned="<?php echo $note['is_pinned']; ?>" title="Pin">
                                            <?php echo $note["is_pinned"] ? "📌" : "☆"; ?>
                                        </div>
                                        <div class="note-star <?php echo $note["is_important"] ? "active" : ""; ?>" onclick="toggleImportant(<?php echo $note["id"]; ?>, this)" data-important="<?php echo $note['is_important']; ?>" title="Important" style="margin-left: 10px;">
                                            <?php echo $note["is_important"] ? "★" : "☆"; ?>
                                        </div>
                                        <a href="edit.php?id=<?php echo $note["id"]; ?>" style="margin-left:15px; text-decoration:none;">✏️</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($crud_action === "edit" || $crud_action === "create"): ?>
                        <h1><?php echo $crud_action === "edit" ? "Edit Note" : "New Note"; ?></h1>
                        <form method="POST" class="form-grid" style="max-width: 800px;" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo sanitize($data["title"] ?? ""); ?>" autofocus></div>
                            <div class="form-group"><label>Content</label><textarea name="content" rows="5"><?php echo sanitize($data["content"] ?? ""); ?></textarea></div>
                            <div class="form-group"><label>Images</label>
                                <div id="image-drop-zone" style="border: 2px dashed var(--border); padding: 20px; text-align: center; border-radius: 8px; cursor: pointer;" ondragover="event.preventDefault();" ondrop="handleDrop(event)" onclick="document.getElementById('file-input').click()">
                                    <p>Drag and drop images here or click to upload</p>
                                    <input type="file" id="file-input" name="images[]" multiple accept="image/*" style="display: none;" onchange="handleFileSelect(event)">
                                </div>
                                <div id="image-preview-container" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px;">
                                    <?php $imgs = json_decode($data['images_json'] ?? '[]', true); if (is_array($imgs)): foreach ($imgs as $img): $imgPath = "../../files/{$company_id}/Private/{$_SESSION['username']}_{$logged_user_id}/notes/{$img}"; ?>
                                        <div class="image-item" style="position: relative;"><img src="<?php echo $imgPath; ?>" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px;"><input type="hidden" name="existing_images[]" value="<?php echo sanitize($img); ?>"><span onclick="this.parentElement.remove()" style="position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer;">&times;</span></div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                            <div class="form-group" id="reminder-section" style="<?php echo !empty($data["reminder_at"]) ? "" : "display: none;"; ?>"><label>Reminder Date & Time</label><input type="datetime-local" name="reminder_at" value="<?php echo isset($data["reminder_at"]) ? str_replace(" ", "T", substr($data["reminder_at"], 0, 16)) : ""; ?>"></div>
                            <div class="form-group" id="checklist-section" style="<?php echo !empty($data['is_checklist']) ? '' : 'display: none;'; ?>">
                                <label>Checklist</label>
                                <div id="checklist-container">
                                    <?php $checklist = json_decode($data['checklist_json'] ?? '[]', true); if (is_array($checklist)): foreach ($checklist as $item): ?>
                                        <div class="checklist-item" style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;"><input type="checkbox" name="checklist_completed[]" value="1" <?php echo !empty($item['completed']) ? 'checked' : ''; ?>><input type="text" name="checklist_text[]" value="<?php echo sanitize($item['text'] ?? ''); ?>" style="flex: 1;"><span onclick="this.parentElement.remove()" style="cursor: pointer; color: var(--danger);">&times;</span></div>
                                    <?php endforeach; endif; ?>
                                </div>
                                <button type="button" class="btn btn-sm" onclick="addChecklistItem()" style="margin-top: 10px;">➕ Add item</button>
                            </div>
                            <div class="form-group"><label>Tags</label>
                                <select name="category_id[]" multiple size="5" data-addable-select="1" data-add-table="note_labels" data-add-label-col="label" data-add-company-scoped="1">
                                    <option value="">-- None --</option><option value="__add_new__">➕</option>
                                    <option value="">-- None --</option><option value="__add_new__">➕</option>
                                    <?php $nId = $data['id'] ?? 0; $selL = []; if ($nId > 0) { $stmtL = $conn->prepare("SELECT label FROM note_labels WHERE note_id = ? AND active = 1"); $stmtL->bind_param("i", $nId); $stmtL->execute(); $rL = $stmtL->get_result(); while ($rowL = mysqli_fetch_assoc($rL)) $selL[] = $rowL['label']; }
                                    foreach ($user_tags as $ul): ?><option value="<?php echo sanitize($ul); ?>" <?php echo in_array($ul, $selL) ? "selected" : ""; ?>><?php echo sanitize($ul); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Note Color</label><div style="display: flex; gap: 10px; margin-top: 5px;">
                                <?php $colors = ['default' => 'var(--bg-secondary)', 'red' => '#f28b82', 'orange' => '#fbbc04', 'yellow' => '#fff475', 'green' => '#ccff90', 'teal' => '#a7ffeb', 'blue' => '#cbf0f8', 'darkblue' => '#aecbfa', 'purple' => '#d7aefb', 'pink' => '#fdcfe8', 'brown' => '#e6c9a8', 'gray' => '#e8eaed'];
                                foreach ($colors as $hex): ?>
                                    <label style="cursor: pointer;" class="color-option"><input type="radio" name="color" value="<?php echo $hex; ?>" <?php echo ($data['color'] ?? 'var(--bg-secondary)') === $hex ? 'checked' : ''; ?> style="display: none;" onchange="updateColorSelection(this)"><div style="width: 30px; height: 30px; border-radius: 50%; background: <?php echo $hex; ?>; border: 2px solid <?php echo ($data['color'] ?? 'var(--bg-secondary)') === $hex ? 'var(--accent)' : 'transparent'; ?>;"></div></label>
                                <?php endforeach; ?>
                            </div></div>
                            <div class="form-group"><label>Shared With</label>
                                <select name="shared_with_json[]" multiple size="5" data-addable-select="1" data-add-table="users" data-add-label-col="username" data-add-company-scoped="1">
                                    <option value="">-- None --</option><option value="__add_new__">➕</option>
                                <?php
                                // Ensure users are always loaded for this dropdown
                                if (empty($users)) {
                                    $stmtULoad = $conn->prepare("SELECT id, username FROM users WHERE active = 1 AND company_id = ?");
                                    $stmtULoad->bind_param("i", $company_id);
                                    $stmtULoad->execute();
                                    $resULoad = $stmtULoad->get_result();
                                    while ($rowU = mysqli_fetch_assoc($resULoad)) { $users[$rowU["id"]] = $rowU; }
                                }
                                $sharedUsers = json_decode($data['shared_with_json'] ?? '[]', true);
                                foreach ($users as $u): if ($u['id'] == $logged_user_id) continue; ?>
                                    <option value="<?php echo $u["id"]; ?>" <?php echo is_array($sharedUsers) && in_array($u["id"], $sharedUsers) ? "selected" : ""; ?>><?php echo sanitize($u["username"]); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                            <div style="display: flex; gap: 30px; margin-top: 10px;">
                                <label class="itm-checkbox-control"><input type="checkbox" name="is_checklist" value="1" <?php echo !empty($data["is_checklist"]) ? "checked" : ""; ?> onchange="toggleChecklistSection(this.checked)"><span>Checklist ☑️</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" name="is_pinned" value="1" <?php echo !empty($data["is_pinned"]) ? "checked" : ""; ?>><span>Pinned 📌</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" name="is_important" value="1" <?php echo !empty($data["is_important"]) ? "checked" : ""; ?>><span>Important ★</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" name="is_archived" value="1" <?php echo !empty($data["is_archived"]) ? "checked" : ""; ?>><span>Archived 📥</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" name="is_reminder" value="1" <?php echo !empty($data["reminder_at"]) ? "checked" : ""; ?> onchange="toggleReminderSection(this.checked)"><span>Reminder 🔔</span></label>
                            </div>
                            <div class="form-actions" style="margin-top: 30px;"><button class="btn btn-primary" type="submit">💾 Save</button><a href="index.php" class="btn">🔙 Cancel</a>
                                <?php if ($crud_action === "edit"): ?><button type="submit" name="bulk_action" value="single_delete" class="btn btn-danger" style="margin-left: auto;" onclick="return confirm('Delete this note?')">🗑️ Delete</button><?php endif; ?>
                            </div>
                        </form>

                    <?php elseif ($crud_action === "view"): ?>
                        <h1>Note Details</h1>
                        <div class="card" style="max-width: 800px; background-color: <?php echo $data["color"] ?? "var(--bg-primary)"; ?>; border: 1px solid var(--border); border-radius: 8px; padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <div>
                                    <h2 style="margin: 0;"><?php echo sanitize($data["title"] ?: '(Untitled)'); ?></h2>
                                    <div style="color: var(--text-secondary); margin-bottom: 20px;">Created on <?php echo date("M j, Y", strtotime($data["created_at"])); ?></div>
                                </div>
                                <?php if (!empty(json_decode($data['images_json'] ?? '[]', true))): ?>
                                    <button class="btn btn-sm" onclick="downloadAllImages(<?php echo $data['id']; ?>)">📥 Download All Images</button>
                                <?php endif; ?>
                            </div>
                            <?php $vimgs = json_decode($data['images_json'] ?? '[]', true); if (!empty($vimgs)): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;"><?php foreach ($vimgs as $img): $imgPath = "../../files/{$company_id}/Private/{$_SESSION['username']}_{$logged_user_id}/notes/{$img}"; ?>
                                    <div style="text-align: center;">
                                        <img src="<?php echo $imgPath; ?>" onclick="openImageModal('<?php echo $imgPath; ?>')" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); display: block; margin-bottom: 5px; cursor: pointer;">
                                        <div style="font-size: 12px; display: flex; justify-content: center; gap: 10px;"><a href="#" onclick="openImageModal('<?php echo $imgPath; ?>'); return false;">👁️ Preview</a><a href="<?php echo $imgPath; ?>" download>📥 Download</a></div>
                                    </div><?php endforeach; ?></div>
                            <?php endif; ?>
                            <?php $vcl = json_decode($data['checklist_json'] ?? '[]', true); if (!empty($vcl)): ?>
                                <div style="margin-bottom: 20px;"><?php foreach ($vcl as $item): ?><div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;"><input type="checkbox" disabled <?php echo !empty($item['completed']) ? 'checked' : ''; ?>><span style="<?php echo !empty($item['completed']) ? 'text-decoration: line-through; color: var(--text-tertiary);' : ''; ?>"><?php echo sanitize($item['text']); ?></span></div><?php endforeach; ?></div>
                            <?php endif; ?>
                            <?php if (!empty($data["content"])): ?><div style="margin-bottom: 20px; white-space: pre-wrap;"><?php echo sanitize($data["content"]); ?></div><?php endif; ?>
                            <table class="table" style="width: auto;">
                                <?php if (!empty($data['reminder_at'])): ?>
                                    <tr><th style="text-align: left; padding-right: 20px;">Reminder</th><td>🔔 <?php echo date("M j, Y H:i", strtotime($data['reminder_at'])); ?></td></tr>
                                <?php endif; ?>
                                <tr><th style="text-align: left; padding-right: 20px;">Tags</th><td><?php $lbls = []; $noteId = (int)$data['id']; $stmtVL = $conn->prepare("SELECT label FROM note_labels WHERE note_id = ? AND active = 1"); $stmtVL->bind_param("i", $noteId); $stmtVL->execute(); $resL = $stmtVL->get_result(); while ($rowL = mysqli_fetch_assoc($resL)) $lbls[] = $rowL['label']; echo empty($lbls) ? "None" : sanitize(implode(', ', $lbls)); ?></td></tr>
                                <tr><th style="text-align: left; padding-right: 20px;">Shared With</th><td><?php $uIds = json_decode($data['shared_with_json'] ?? '[]', true); if (empty($uIds)) echo "Private"; else { $names = []; foreach ($uIds as $uid) { if (isset($users[$uid])) $names[] = $users[$uid]['username']; } echo sanitize(implode(', ', $names)); } ?></td></tr>
                            </table>
                            <div class="form-actions" style="margin-top: 30px;"><a href="edit.php?id=<?php echo $data["id"]; ?>" class="btn btn-primary">✏️ Edit</a><a href="index.php" class="btn">🔙 Back</a></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
    let quickAddFiles = [];
    function toggleChecklistMode() { const input = document.getElementById('quickAddIsChecklist'); if (!input) return; const icon = document.querySelector('.quick-add-icon[onclick="toggleChecklistMode()"]'); if (input.value === '1') { input.value = '0'; icon.style.color = 'var(--text-secondary)'; } else { input.value = '1'; icon.style.color = 'var(--accent)'; } }
    function triggerQuickImageUpload() { const el = document.getElementById('quickAddImageInput'); if (el) el.click(); }
    function toggleQuickReminderDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('quickReminderDropdown');
        const isShown = dropdown.classList.contains('show');
        document.querySelectorAll('.quick-add-dropdown').forEach(d => d.classList.remove('show'));
        if (!isShown) dropdown.classList.add('show');
    }
    document.addEventListener('click', () => {
        document.querySelectorAll('.quick-add-dropdown').forEach(d => d.classList.remove('show'));
    });
    function setQuickReminder(type, event) {
        event.stopPropagation();
        const input = document.getElementById('quickAddReminderAt');
        const btn = document.getElementById('quickReminderBtn');
        const now = new Date();
        let dbValue = '';

        if (type === 'later') {
            const later = new Date(now.getTime() + 3 * 60 * 60 * 1000);
            dbValue = later.toISOString().slice(0, 19).replace('T', ' ');
        } else if (type === 'tomorrow') {
            const tomorrow = new Date(now);
            tomorrow.setDate(now.getDate() + 1);
            tomorrow.setHours(9, 0, 0, 0);
            dbValue = tomorrow.toISOString().slice(0, 19).replace('T', ' ');
        } else if (type === 'next_week') {
            const nextMon = new Date(now);
            nextMon.setDate(now.getDate() + ((1 + 7 - now.getDay()) % 7 || 7));
            nextMon.setHours(9, 0, 0, 0);
            dbValue = nextMon.toISOString().slice(0, 19).replace('T', ' ');
        } else if (type === 'choose') {
            const dt = prompt("Enter reminder date & time (YYYY-MM-DD HH:MM):", now.toISOString().slice(0,16).replace("T", " "));
            if (dt) dbValue = dt;
            else return;
        } else if (type === 'remove') {
            dbValue = '';
        }

        input.value = dbValue;
        btn.style.color = dbValue ? 'var(--accent)' : 'var(--text-secondary)';
        document.getElementById('quickReminderDropdown').classList.remove('show');
    }
    function handleQuickImageSelect(event) { quickAddFiles = quickAddFiles.concat(Array.from(event.target.files)); renderQuickPreview(); }
    function renderQuickPreview() { const preview = document.getElementById('quickAddPreview'); if (!preview) return; preview.innerHTML = ''; quickAddFiles.forEach((file, index) => { const div = document.createElement('div'); div.style.position = 'relative'; const img = document.createElement('img'); img.src = URL.createObjectURL(file); img.style.cssText = 'width: 60px; height: 60px; object-fit: cover; border-radius: 4px;'; const close = document.createElement('span'); close.innerHTML = '&times;'; close.style.cssText = 'position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; cursor: pointer;'; close.onclick = () => { quickAddFiles.splice(index, 1); renderQuickPreview(); }; div.appendChild(img); div.appendChild(close); preview.appendChild(div); }); }
    function quickAdd() {
        const input = document.getElementById("quickAddInput");
        if (!input) return;
        const title = input.value.trim();
        if (!title && quickAddFiles.length === 0) {
            alert("Please add a title or an image.");
            return;
        }
        const formData = new FormData();
        formData.append("csrf_token", CSRF_TOKEN);
        formData.append("title", title);
        const isCL = document.getElementById("quickAddIsChecklist");
        formData.append("is_checklist", isCL ? isCL.value : '0');
        const reminderInput = document.getElementById("quickAddReminderAt");
        if (reminderInput) formData.append("reminder_at", reminderInput.value);
        quickAddFiles.forEach((file) => formData.append("images[]", file));
        fetch("index.php?ajax_action=quick_add", { method: "POST", body: formData })
            .then(r => r.json())
            .then(data => { if (data.ok) location.reload(); else alert("Error adding note: " + (data.error || "Unknown error")); });
    }
    function togglePinned(id, el) { const newVal = el.dataset.pinned === '1' ? 0 : 1; const formData = new FormData(); formData.append("csrf_token", CSRF_TOKEN); formData.append("id", id); formData.append("is_pinned", newVal); fetch("index.php?ajax_action=toggle_pinned", { method: "POST", body: formData }).then(r => r.json()).then(data => { if (data.ok) location.reload(); }); }
    function toggleImportant(id, el) { const newVal = el.dataset.important === '1' ? 0 : 1; const formData = new FormData(); formData.append("csrf_token", CSRF_TOKEN); formData.append("id", id); formData.append("is_important", newVal); fetch("index.php?ajax_action=toggle_important", { method: "POST", body: formData }).then(r => r.json()).then(data => { if (data.ok) location.reload(); }); }
    function openImageModal(src) { document.getElementById('modalImage').src = src; document.getElementById('downloadModalImage').href = src; document.getElementById('imageModal').classList.add('show'); document.getElementById('modalBackdrop').classList.add('show'); }
    function closeImageModal() { document.getElementById('imageModal').classList.remove('show'); document.getElementById('modalBackdrop').classList.remove('show'); }
    function downloadAllImages(id) { const formData = new FormData(); formData.append("csrf_token", CSRF_TOKEN); formData.append("id", id); fetch("index.php?ajax_action=download_all_images", { method: "POST", body: formData }).then(r => r.json()).then(data => { if (data.ok) window.location.href = data.zip_url; else alert(data.error || "Error downloading images"); }); }
    function openEditTagsModal() { document.getElementById('editTagsModal').classList.add('show'); document.getElementById('modalBackdrop').classList.add('show'); }
    function closeEditTagsModal() { document.getElementById('editTagsModal').classList.remove('show'); document.getElementById('modalBackdrop').classList.remove('show'); location.reload(); }
    function renameTag(oldName, newName) { if (!newName || oldName === newName) return; const formData = new FormData(); formData.append("csrf_token", CSRF_TOKEN); formData.append("old_name", oldName); formData.append("new_name", newName); fetch("index.php?ajax_action=rename_tag", { method: "POST", body: formData }).then(r => r.json()).then(data => { if (!data.ok) alert(data.error || "Error renaming tag"); }); }
    function deleteTag(name) { if (!confirm('Are you sure?')) return; const formData = new FormData(); formData.append("csrf_token", CSRF_TOKEN); formData.append("name", name); fetch("index.php?ajax_action=delete_tag", { method: "POST", body: formData }).then(r => r.json()).then(data => { if (data.ok) renderTagsModal(); else alert(data.error || "Error deleting tag"); }); }
    function addTag() { const input = document.getElementById('newTagName'); const name = input.value.trim(); if (!name) return; const formData = new FormData(); formData.append("csrf_token", CSRF_TOKEN); formData.append("name", name); fetch("index.php?ajax_action=add_tag", { method: "POST", body: formData }).then(r => r.json()).then(data => { if (data.ok) { input.value = ''; renderTagsModal(); } else alert(data.error || "Error adding tag"); }); }
    function renderTagsModal() { fetch(location.href).then(r => r.text()).then(html => { const parser = new DOMParser(); const doc = parser.parseFromString(html, 'text/html'); document.getElementById('tags-list').innerHTML = doc.getElementById('tags-list').innerHTML; }); }
    function updateColorSelection(radio) { document.querySelectorAll('.color-option div').forEach(div => div.style.borderColor = 'transparent'); radio.nextElementSibling.style.borderColor = 'var(--accent)'; }
    function addChecklistItem() { const container = document.getElementById('checklist-container'); const div = document.createElement('div'); div.style.cssText = 'display: flex; align-items: center; gap: 10px; margin-bottom: 5px;'; div.innerHTML = '<input type="checkbox" name="checklist_completed[]" value="1"><input type="text" name="checklist_text[]" placeholder="List item" style="flex: 1;"><span onclick="this.parentElement.remove()" style="cursor: pointer; color: var(--danger);">&times;</span>'; container.appendChild(div); div.querySelector('input[type="text"]').focus(); }
    function toggleChecklistSection(checked) { document.getElementById('checklist-section').style.display = checked ? '' : 'none'; }
    function toggleReminderSection(checked) { document.getElementById("reminder-section").style.display = checked ? "" : "none"; }
    function handleDrop(e) { e.preventDefault(); handleFiles(e.dataTransfer.files); }
    function handleFileSelect(e) { handleFiles(e.target.files); }
    function handleFiles(files) { const container = document.getElementById('image-preview-container'); Array.from(files).forEach(file => { if (!file.type.startsWith('image/')) return; const reader = new FileReader(); reader.onload = (e) => { const div = document.createElement('div'); div.className = 'image-item'; div.style.position = 'relative'; const img = document.createElement('img'); img.src = e.target.result; img.style.cssText = 'width: 100px; height: 100px; object-fit: cover; border-radius: 4px;'; const span = document.createElement('span'); span.innerHTML = '&times;'; span.style.cssText = 'position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer;'; span.onclick = () => div.remove(); div.appendChild(img); div.appendChild(span); container.appendChild(div); }; reader.readAsDataURL(file); }); }
</script>

<div class="modal" id="imageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width: 90%;"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Image Preview</h5><button type="button" class="close" onclick="closeImageModal()">&times;</button></div>
    <div class="modal-body" style="text-align: center;"><img id="modalImage" src="" style="max-width: 100%; max-height: 80vh; border-radius: 4px;"></div>
    <div class="modal-footer"><a id="downloadModalImage" href="" download class="btn btn-primary">📥 Download</a><button type="button" class="btn" onclick="closeImageModal()">Close</button></div></div></div>
</div>

<div class="modal" id="editTagsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit tags</h5><button type="button" class="close" onclick="closeEditTagsModal()">&times;</button></div>
    <div class="modal-body">
        <div style="display: flex; gap: 10px; margin-bottom: 20px;">
            <input type="text" id="newTagName" placeholder="Create new tag" style="flex: 1;" onkeypress="if(event.key==='Enter') addTag()">
            <button type="button" class="btn btn-sm" onclick="addTag()">➕</button>
        </div>
        <div id="tags-list"><?php foreach ($user_tags as $ul): ?><div class="tag-edit-item" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;"><span onclick="deleteTag('<?php echo addslashes($ul); ?>')" style="cursor: pointer;">🗑️</span><input type="text" value="<?php echo sanitize($ul); ?>" onchange="renameTag('<?php echo addslashes($ul); ?>', this.value)" style="flex: 1; border: none; background: transparent; border-bottom: 1px solid transparent;" onfocus="this.style.borderBottom='1px solid var(--accent)'" onblur="this.style.borderBottom='1px solid transparent'"></div><?php endforeach; ?></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn" onclick="closeEditTagsModal()">Done</button></div></div></div>
</div>
<div class="modal-backdrop" id="modalBackdrop"></div>
</body>
</html>
