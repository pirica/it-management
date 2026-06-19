<?php
/**
 * Notes Module - Index - manages notes with a Google Keep style UI.
 */

require_once "../../config/config.php";
require_once ROOT_PATH . "includes/notes_visibility.php";
require_once ROOT_PATH . 'includes/itm_employee_employment_status.php';
$crud_title = "Notes";
$crud_action = $crud_action ?? "index";
$logged_user_id = isset($_SESSION["employee_id"]) ? (int)$_SESSION["employee_id"] : 0;
$company_id = isset($_SESSION["company_id"]) ? (int)$_SESSION["company_id"] : 0;
$data = [];

// Metadata
$user_tags = [];
$stmtUL = $conn->prepare("SELECT DISTINCT label FROM note_labels WHERE company_id = ? AND employee_id = ? AND active = 1 ORDER BY label ASC");
$stmtUL->bind_param("ii", $company_id, $logged_user_id);
$stmtUL->execute();
$resUserLabels = $stmtUL->get_result();
if ($resUserLabels) { while ($row = mysqli_fetch_assoc($resUserLabels)) { $user_tags[] = $row["label"]; } }

$crud_table = "notes";
$join = itm_employee_active_employment_status_join_sql('e', 'es');
$predicate = itm_employee_active_employment_status_predicate_sql('es');
$stmtUsers = $conn->prepare('SELECT e.id, e.username FROM employees e' . $join . ' WHERE e.company_id = ? AND ' . $predicate);
$stmtUsers->bind_param("i", $company_id);
$stmtUsers->execute();
$resUser = $stmtUsers->get_result();
if ($resUser) { while ($row = mysqli_fetch_assoc($resUser)) { $users[$row['id']] = $row; } }

// Check for existing data to show/hide sidebar items
$hasPinned = false;
$stmtP = $conn->prepare("SELECT 1 FROM notes WHERE company_id = ? AND employee_id = ? AND is_pinned = 1 AND active = 1 LIMIT 1");
$stmtP->bind_param("ii", $company_id, $logged_user_id);
$stmtP->execute();
if ($stmtP->get_result()->fetch_assoc()) $hasPinned = true;

$hasImages = false;
$stmtI = $conn->prepare("SELECT 1 FROM notes WHERE company_id = ? AND employee_id = ? AND images_json IS NOT NULL AND active = 1 LIMIT 1");
$stmtI->bind_param("ii", $company_id, $logged_user_id);
$stmtI->execute();
if ($stmtI->get_result()->fetch_assoc()) $hasImages = true;

$hasImportant = false;
$stmtImp = $conn->prepare("SELECT 1 FROM notes WHERE company_id = ? AND employee_id = ? AND is_important = 1 AND active = 1 LIMIT 1");
$stmtImp->bind_param("ii", $company_id, $logged_user_id);
$stmtImp->execute();
if ($stmtImp->get_result()->fetch_assoc()) $hasImportant = true;

$hasShared = false;
$stmtS = $conn->prepare("SELECT 1 FROM notes WHERE company_id = ? AND (shared_with_json IS NOT NULL AND shared_with_json != '[]') AND active = 1 AND (" . itm_notes_visibility_sql() . ") LIMIT 1");
$stmtS->bind_param("iii", $company_id, $logged_user_id, $logged_user_id);
$stmtS->execute();
if ($stmtS->get_result()->fetch_assoc()) $hasShared = true;

// Standard CRUD processing
$editId = (int)($_GET["id"] ?? 0);
$csrfToken = itm_get_csrf_token();

// JSON API for Import Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['index', 'list_all'], true) && strpos((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode((string)$rawBody, true);
    if (is_array($jsonBody) && isset($jsonBody['import_excel_rows'])) {
        header('Content-Type: application/json');
        if (!itm_validate_csrf_token($jsonBody['csrf_token'] ?? '')) {
            echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token.']); exit;
        }

        // Map Tag names to labels and Usernames to IDs for this company
        $tagMap = [];
        $stmtT = $conn->prepare("SELECT DISTINCT label FROM note_labels WHERE company_id = ? AND employee_id = ?");
        $stmtT->bind_param("ii", $company_id, $logged_user_id);
        $stmtT->execute();
        $resTags = $stmtT->get_result();
        while ($row = mysqli_fetch_assoc($resTags)) { $tagMap[strtolower($row['label'])] = $row['label']; }

        $userMap = [];
        $stmtU = $conn->prepare("SELECT id, username FROM employees WHERE company_id = ?");
        $stmtU->bind_param("i", $company_id);
        $stmtU->execute();
        $resU = $stmtU->get_result();
        while ($row = mysqli_fetch_assoc($resU)) { $userMap[strtolower($row['username'])] = $row['id']; }

        $importRows = $jsonBody['import_excel_rows'];
        $headerRow = array_map('trim', array_map('strval', (array)($importRows[0] ?? [])));
        $inserted = 0; $updated = 0;
        for ($i = 1; $i < count($importRows); $i++) {
            $row = $importRows[$i];
            $idIdx = array_search('ID', $headerRow);
            $titleIdx = array_search('Title', $headerRow);
            $contentIdx = array_search('Content', $headerRow);
            $remIdx = array_search('Reminder', $headerRow);
            $tagIdx = array_search('Tags', $headerRow);
            $sharedIdx = array_search('Shared With', $headerRow);
            $pinIdx = array_search('Pinned', $headerRow);
            $impIdx = array_search('Important', $headerRow);
            $arcIdx = array_search('Archived', $headerRow);

            $id = ($idIdx !== false) ? (int)($row[$idIdx] ?? 0) : 0;
            $title = ($titleIdx !== false) ? ($row[$titleIdx] ?? '') : '';
            $content = ($contentIdx !== false) ? ($row[$contentIdx] ?? '') : '';
            $remAt = ($remIdx !== false && !empty($row[$remIdx])) ? $row[$remIdx] : null;
            $isPin = ($pinIdx !== false && in_array(strtolower($row[$pinIdx] ?? ''), ['yes', '1', 'true', '✅'])) ? 1 : 0;
            $isImp = ($impIdx !== false && in_array(strtolower($row[$impIdx] ?? ''), ['yes', '1', 'true', '✅'])) ? 1 : 0;
            $isArc = ($arcIdx !== false && in_array(strtolower($row[$arcIdx] ?? ''), ['yes', '1', 'true', '✅'])) ? 1 : 0;

            if ($title === '' && $content === '') continue;

            $sharedIds = [];
            if ($sharedIdx !== false && !empty($row[$sharedIdx])) {
                foreach (explode(',', $row[$sharedIdx]) as $uName) {
                    $uName = strtolower(trim($uName));
                    if (isset($userMap[$uName])) $sharedIds[] = $userMap[$uName];
                }
            }
            $sharedJson = !empty($sharedIds) ? json_encode(array_values($sharedIds)) : null;

            $existingId = 0;
            if ($id > 0) {
                $stmtCheckNote = $conn->prepare("SELECT id FROM notes WHERE id = ? AND company_id = ? AND employee_id = ?");
                $stmtCheckNote->bind_param("iii", $id, $company_id, $logged_user_id);
                $stmtCheckNote->execute();
                if ($stmtCheckNote->get_result()->fetch_assoc()) $existingId = $id;
            }

            if ($existingId > 0) {
                $stmt = $conn->prepare("UPDATE notes SET title=?, content=?, reminder_at=?, is_pinned=?, is_important=?, is_archived=?, shared_with_json=? WHERE id=?");
                $stmt->bind_param("sssiiisi", $title, $content, $remAt, $isPin, $isImp, $isArc, $sharedJson, $existingId);
                if ($stmt->execute()) { $updated++; $noteId = $existingId; }
            } else {
                $stmt = $conn->prepare("INSERT INTO notes (company_id, employee_id, title, content, reminder_at, is_pinned, is_important, is_archived, shared_with_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisssiiis", $company_id, $logged_user_id, $title, $content, $remAt, $isPin, $isImp, $isArc, $sharedJson);
                if ($stmt->execute()) { $inserted++; $noteId = mysqli_insert_id($conn); }
            }

            if (isset($noteId) && $tagIdx !== false && !empty($row[$tagIdx])) {
                $stmtDelL = $conn->prepare("DELETE FROM note_labels WHERE note_id = ?");
                $stmtDelL->bind_param("i", $noteId);
                $stmtDelL->execute();

                foreach (explode(',', $row[$tagIdx]) as $tagName) {
                    $tagName = trim($tagName);
                    if ($tagName === '') continue;
                    $stmtL = $conn->prepare("INSERT INTO note_labels (company_id, employee_id, note_id, label) VALUES (?, ?, ?, ?)");
                    $stmtL->bind_param("iiis", $company_id, $logged_user_id, $noteId, $tagName);
                    $stmtL->execute();
                }
            }
        }
        echo json_encode(['ok' => true, 'inserted' => $inserted, 'updated' => $updated]);
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_GET["ajax_action"])) {
    if (!itm_validate_csrf_token($_POST["csrf_token"] ?? "")) {
        die("CSRF token mismatch");
    }

    $action = $_POST["bulk_action"] ?? "";
    if ($action === "delete" && !empty($_POST["ids"])) {
        $ids = array_map("intval", $_POST["ids"]);
        foreach ($ids as $id) {
            $stmt = $conn->prepare("UPDATE notes SET active = 0 WHERE id = ? AND company_id = ? AND employee_id = ?");
            $stmt->bind_param("iii", $id, $company_id, $logged_user_id);
            $stmt->execute();
        }
        header("Location: index.php?msg=deleted");
        die();
    }

    if ($action === "single_delete" && $editId > 0) {
        $visSql = itm_notes_visibility_sql();
        $stmtCheck = $conn->prepare("SELECT active FROM notes WHERE id = ? AND company_id = ? AND ($visSql)");
        $stmtCheck->bind_param("iiii", $editId, $company_id, $logged_user_id, $logged_user_id);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result()->fetch_assoc();
        if ($resCheck && (int)$resCheck['active'] === 0) {
            $stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND company_id = ? AND ($visSql)");
        } else {
            $stmt = $conn->prepare("UPDATE notes SET active = 0 WHERE id = ? AND company_id = ? AND ($visSql)");
        }
        $stmt->bind_param("iiii", $editId, $company_id, $logged_user_id, $logged_user_id);
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
            itm_ensure_files_storage_directory(rtrim($upload_dir, '/'));
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

        $shared_users = array_filter(array_map('intval', (array)($_POST["shared_with_json"] ?? [])));
        $shared_with_json = !empty($shared_users) ? json_encode(array_values($shared_users)) : null;

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
            $stmt->bind_param("ssisiiissssiii", $title, $content, $is_checklist, $color, $is_pinned, $is_important, $is_archived, $reminder_at, $checklist_json, $images_json, $shared_with_json, $editId, $company_id, $logged_user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO notes (company_id, employee_id, title, content, is_checklist, color, is_pinned, is_important, is_archived, reminder_at, checklist_json, images_json, shared_with_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
                    $stmtL = $conn->prepare("INSERT INTO note_labels (company_id, employee_id, note_id, label) VALUES (?, ?, ?, ?)");
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
        http_response_code(403);
        echo json_encode(["ok" => false, "error" => "CSRF token mismatch"]);
        die();
    }

    $action = $_GET["ajax_action"];
    if ($action === "quick_add") {
        $title = $_POST["title"] ?? "";
        $content = $_POST["content"] ?? "";
        $is_checklist = (int)($_POST["is_checklist"] ?? 0);
        $reminder_at = !empty($_POST["reminder_at"]) ? $_POST["reminder_at"] : null;
        $image_files = [];
        if (!empty($_FILES['images'])) {
            $upload_dir = ROOT_PATH . "files/$company_id/Private/{$_SESSION['username']}_$logged_user_id/notes/";
            itm_ensure_files_storage_directory(rtrim($upload_dir, '/'));
            foreach ($_FILES['images']['tmp_name'] as $i => $tmp_name) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $safe_name = uniqid('note_') . '.' . pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                    if (move_uploaded_file($tmp_name, $upload_dir . $safe_name)) $image_files[] = $safe_name;
                }
            }
        }
        $images_json = !empty($image_files) ? json_encode($image_files) : null;
        $stmt = $conn->prepare("INSERT INTO notes (company_id, employee_id, title, content, is_checklist, images_json, reminder_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iississ", $company_id, $logged_user_id, $title, $content, $is_checklist, $images_json, $reminder_at);
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
        $stmt = $conn->prepare("UPDATE notes SET is_pinned = ? WHERE id = ? AND company_id = ? AND employee_id = ?");
        $stmt->bind_param("iiii", $is_pinned, $id, $company_id, $logged_user_id);
        itm_notes_json_mutation_response($stmt);
    }
    if ($action === "toggle_archived") {
        $id = (int)($_POST["id"] ?? 0);
        $is_archived = (int)($_POST["is_archived"] ?? 0);
        $stmt = $conn->prepare("UPDATE notes SET is_archived = ? WHERE id = ? AND company_id = ? AND employee_id = ?");
        $stmt->bind_param("iiii", $is_archived, $id, $company_id, $logged_user_id);
        itm_notes_json_mutation_response($stmt);
    }
    if ($action === "restore") {
        $id = (int)($_POST["id"] ?? 0);
        $visSql = itm_notes_visibility_sql();
        $stmt = $conn->prepare("UPDATE notes SET active = 1 WHERE id = ? AND company_id = ? AND ($visSql)");
        $stmt->bind_param("iiii", $id, $company_id, $logged_user_id, $logged_user_id);
        itm_notes_json_mutation_response($stmt);
    }
    if ($action === "single_delete") {
        $id = (int)($_POST["id"] ?? 0);
        $visSql = itm_notes_visibility_sql();
        $stmtCheck = $conn->prepare("SELECT active FROM notes WHERE id = ? AND company_id = ? AND ($visSql)");
        $stmtCheck->bind_param("iiii", $id, $company_id, $logged_user_id, $logged_user_id);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result()->fetch_assoc();
        if ($resCheck && (int)$resCheck['active'] === 0) {
            $stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND company_id = ? AND ($visSql)");
        } else {
            $stmt = $conn->prepare("UPDATE notes SET active = 0 WHERE id = ? AND company_id = ? AND ($visSql)");
        }
        $stmt->bind_param("iiii", $id, $company_id, $logged_user_id, $logged_user_id);
        itm_notes_json_mutation_response($stmt);
    }
    if ($action === "toggle_important") {
        $id = (int)($_POST["id"] ?? 0);
        $is_important = (int)($_POST["is_important"] ?? 0);
        $stmt = $conn->prepare("UPDATE notes SET is_important = ? WHERE id = ? AND company_id = ? AND employee_id = ?");
        $stmt->bind_param("iiii", $is_important, $id, $company_id, $logged_user_id);
        itm_notes_json_mutation_response($stmt);
    }
    if ($action === "rename_tag") {
        $old = $_POST["old_name"] ?? "";
        $new = $_POST["new_name"] ?? "";
        if ($new === "") {
            http_response_code(400);
            echo json_encode(["ok" => false, "error" => "Tag name cannot be empty"]);
            die();
        }
        $stmtC = $conn->prepare("SELECT 1 FROM note_labels WHERE employee_id = ? AND label = ? AND company_id = ? LIMIT 1");
        $stmtC->bind_param("isi", $logged_user_id, $new, $company_id);
        $stmtC->execute();
        if ($stmtC->get_result()->fetch_assoc()) {
            http_response_code(409);
            echo json_encode(["ok" => false, "error" => "Tag already exists"]);
            die();
        }
        $stmt = $conn->prepare("UPDATE note_labels SET label = ? WHERE label = ? AND employee_id = ? AND company_id = ?");
        $stmt->bind_param("ssii", $new, $old, $logged_user_id, $company_id);
        itm_notes_json_mutation_response($stmt);
    }
    if ($action === "delete_tag") {
        $name = $_POST["name"] ?? "";
        $stmt = $conn->prepare("DELETE FROM note_labels WHERE label = ? AND employee_id = ? AND company_id = ?");
        $stmt->bind_param("sii", $name, $logged_user_id, $company_id);
        itm_notes_json_mutation_response($stmt);
    }
    if ($action === "add_tag") {
        $name = trim($_POST["name"] ?? "");
        if ($name === "") {
            http_response_code(400);
            echo json_encode(["ok" => false, "error" => "Tag name cannot be empty"]);
            die();
        }
        $stmtC = $conn->prepare("SELECT 1 FROM note_labels WHERE employee_id = ? AND label = ? AND company_id = ? LIMIT 1");
        $stmtC->bind_param("isi", $logged_user_id, $name, $company_id);
        $stmtC->execute();
        if ($stmtC->get_result()->fetch_assoc()) {
            http_response_code(409);
            echo json_encode(["ok" => false, "error" => "Tag already exists"]);
            die();
        }
        $stmt = $conn->prepare("INSERT INTO note_labels (company_id, employee_id, label) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $company_id, $logged_user_id, $name);
        itm_notes_json_mutation_response($stmt);
    }
    if ($action === "download_all_images") {
        $id = (int)($_POST["id"] ?? 0);
        $stmt = $conn->prepare("SELECT title, images_json FROM notes WHERE id = ? AND company_id = ? AND employee_id = ?");
        $stmt->bind_param("iii", $id, $company_id, $logged_user_id);
        $stmt->execute();
        $resD = $stmt->get_result()->fetch_assoc();
        if (!$resD) {
            http_response_code(404);
            echo json_encode(["ok" => false, "error" => "Record not found or not permitted"]);
            die();
        }
        $imgs = json_decode($resD['images_json'] ?? '[]', true);
        if (empty($imgs)) {
            http_response_code(400);
            echo json_encode(["ok" => false, "error" => "No images to download"]);
            die();
        }

        $title = $resD['title'] ?: "note_{$id}";
        $safeTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title);
        $zip = new ZipArchive();
        $zipName = "{$safeTitle}_download.zip";
        $zipPath = sys_get_temp_dir() . '/' . $zipName;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $addedFiles = 0;
            foreach ($imgs as $img) {
                $filePath = itm_notes_resolve_image_path($company_id, $_SESSION['username'] ?? '', $logged_user_id, $img);
                if ($filePath === null) {
                    continue;
                }
                $zip->addFile($filePath, basename($filePath));
                $addedFiles++;
            }
            $zip->close();
            if ($addedFiles === 0) {
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                }
                http_response_code(400);
                echo json_encode(["ok" => false, "error" => "No images to download"]);
                die();
            }
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

// Pagination for Table View
$perPage = itm_resolve_records_per_page($ui_config ?? null);
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

if ($crud_action === "index" || $crud_action === "list_all") {
    if ($filter === "garbage") {
        $baseSql = "FROM notes t WHERE t.company_id = ? AND t.active = 0";
    } else {
        $baseSql = "FROM notes t WHERE t.company_id = ? AND t.active = 1";
    }
    $params = [$company_id];
    $types = "i";
    $visibilitySql = itm_notes_visibility_sql("t");
    $baseSql .= " AND ($visibilitySql)";
    $types .= "ii";
    $params[] = $logged_user_id;
    $params[] = $logged_user_id;

    if ($filter === "reminders") {
        $baseSql .= " AND t.reminder_at IS NOT NULL";
    } elseif ($filter === "tag") {
        $label_filter = $_GET["label"] ?? "";
        $baseSql .= " AND EXISTS (SELECT 1 FROM note_labels nl WHERE nl.note_id = t.id AND nl.label = ? AND nl.active = 1)";
        $types .= "s";
        $params[] = $label_filter;
    } elseif ($filter === "archive") {
        $baseSql .= " AND t.is_archived = 1";
    } elseif ($filter === "checklist") {
        $baseSql .= " AND t.is_checklist = 1 AND t.is_archived = 0";
    } elseif ($filter === "pinned") {
        $baseSql .= " AND t.is_pinned = 1 AND t.is_archived = 0";
    } elseif ($filter === "images") {
        $baseSql .= " AND t.images_json IS NOT NULL AND t.is_archived = 0";
    } elseif ($filter === "important") {
        $baseSql .= " AND t.is_important = 1 AND t.is_archived = 0";
    } elseif ($filter === "shared_with") {
       // $baseSql .= " AND t.shared_with_json IS NOT NULL AND t.shared_with_json != '[]' AND t.is_archived = 0";
		$baseSql .= "AND t.shared_with_json IS NOT NULL AND t.shared_with_json != '[]' AND JSON_CONTAINS(t.shared_with_json, CAST($logged_user_id AS JSON), '$') AND t.is_archived = 0";
    } elseif ($filter === "garbage") {
        // No additional filter for archived state needed in garbage
    } elseif ($filter === "all") {
        $baseSql .= " AND t.is_archived = 0";
    } else {
        $baseSql .= " AND t.is_archived = 0";
    }

    if ($search !== "") {
        $baseSql .= " AND (t.title LIKE ? OR t.content LIKE ? OR EXISTS (SELECT 1 FROM note_labels nl WHERE nl.note_id = t.id AND nl.label LIKE ?))";
        $types .= "sss";
        $searchTerm = "%$search%";
        $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm;
    }

    // Count total rows
    $countSql = "SELECT COUNT(*) as total $baseSql";
    $stmtCount = $conn->prepare($countSql);
    $stmtCount->bind_param($types, ...$params);
    $stmtCount->execute();
    $totalRows = $stmtCount->get_result()->fetch_assoc()['total'];
    $totalPages = max(1, ceil($totalRows / $perPage));

    $sql = "SELECT t.* $baseSql";
    $sql .= " ORDER BY t.is_pinned DESC, t.created_at DESC";

    if ($crud_action === "list_all") {
        $sql .= " LIMIT ?, ?";
        $types .= "ii";
        $params[] = $offset;
        $params[] = $perPage;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $notes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    // Pre-fetch tags for all notes to avoid N+1 query problem
    $note_tags_map = [];
    if (!empty($notes)) {
        $note_ids = array_column($notes, 'id');
        $placeholders = implode(',', array_fill(0, count($note_ids), '?'));
        $stmtTags = $conn->prepare("SELECT note_id, label FROM note_labels WHERE note_id IN ($placeholders) AND active = 1");
        $stmtTags->bind_param(str_repeat('i', count($note_ids)), ...$note_ids);
        $stmtTags->execute();
        $resTags = $stmtTags->get_result();
        while ($rowTags = $resTags->fetch_assoc()) {
            $note_tags_map[$rowTags['note_id']][] = $rowTags['label'];
        }
    }
} elseif ($crud_action === "edit" || $crud_action === "view") {
    if ($editId <= 0) {
        header("Location: index.php");
        die();
    }
    $data = itm_notes_fetch_visible_by_id($conn, $editId, $company_id, $logged_user_id, true);
    if (!$data) {
        header("Location: index.php");
        die();
    }
}

$uiColumns = [['Field'=>'id'],['Field'=>'title'],['Field'=>'content'],['Field'=>'reminder_at'],['Field'=>'tags'],['Field'=>'shared_with'],['Field'=>'is_pinned'],['Field'=>'is_important'],['Field'=>'is_archived']];
$displayFieldColumns = $uiColumns;
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
        .quick-add { border: 1px solid var(--border); border-radius: 8px; padding: 12px; background: var(--bg-secondary); margin-bottom: 24px; }
        .quick-add input { flex: 1; border: 1px solid var(--border); border-radius: 4px; background: var(--bg-primary); padding: 8px 12px; outline: none; font-size: 16px; color: var(--text-primary); transition: border-color 0.2s; }
        .quick-add input:focus { border-color: var(--border) !important; box-shadow: none !important; }
        .quick-add textarea { border: 1px solid var(--border); border-radius: 4px; background: var(--bg-primary); padding: 8px 12px; outline: none; font-size: 14px; color: var(--text-primary); transition: border-color 0.2s; resize: vertical; }
        .quick-add textarea:focus { border-color: var(--border) !important; box-shadow: none !important; }
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

        /* Table styles override for dark mode */
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid var(--border); padding: 12px; text-align: left; }
        th { background: var(--bg-secondary); }

        @media (max-width: 768px) {
            .notes-container { flex-direction: column; height: auto; min-height: calc(100vh - 120px); }
            .notes-sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--border); }
            .notes-content { padding: 16px; }
            .empty-state { padding: 40px 16px; }
            .modal-dialog { margin: 0.5rem; max-width: none; }
        }
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
                    <?php if ($hasImportant): ?><a href="?filter=important" class="notes-sidebar-item <?php echo $filter === "important" ? "active" : ""; ?>">★ Important</a><?php endif; ?>
                    <?php if ($hasShared): ?><a href="?filter=shared_with" class="notes-sidebar-item <?php echo $filter === "shared_with" ? "active" : ""; ?>">👤 Shared With</a><?php endif; ?>

                    <?php foreach ($user_tags as $ul): ?>
                        <a href="?filter=tag&label=<?php echo urlencode($ul); ?>" class="notes-sidebar-item <?php echo ($filter === "tag" && ($_GET["label"] ?? "") === $ul) ? "active" : ""; ?>">🏷️ <?php echo sanitize($ul); ?></a>
                    <?php endforeach; ?>

                    <a href="?filter=archive" class="notes-sidebar-item <?php echo $filter === "archive" ? "active" : ""; ?>">📥 Archive</a>
                    <a href="?filter=garbage" class="notes-sidebar-item <?php echo $filter === "garbage" ? "active" : ""; ?>">🗑️ Garbage</a>
                    <a href="?filter=checklist" class="notes-sidebar-item <?php echo $filter === "checklist" ? "active" : ""; ?>">☑️ Checklist</a>
                    <?php if ($hasPinned): ?><a href="?filter=pinned" class="notes-sidebar-item <?php echo $filter === "pinned" ? "active" : ""; ?>">📌 Pinned</a><?php endif; ?>
                    <?php if ($hasImages): ?><a href="?filter=images" class="notes-sidebar-item <?php echo $filter === "images" ? "active" : ""; ?>">🖼️ Images</a><?php endif; ?>
					<hr style="width: 80%; border-top: 1px solid var(--border); opacity: 0.5;">
                    <a href="#" class="notes-sidebar-item" onclick="openEditTagsModal(); return false;">✏️ Edit tags</a>
                    <a href="list_all.php" class="notes-sidebar-item <?php echo $crud_action === 'list_all' ? 'active' : ''; ?>">📊 Table View</a>
                    <hr style="width: 80%; border-top: 1px solid var(--border); opacity: 0.5;">
                </div>
                <div class="notes-content">
                    <?php if ($crud_action === "index" || $crud_action === "list_all"): ?>
                        <div class="notes-header">
                            <a href="create.php?filter=<?php echo urlencode($filter); ?>" class="btn btn-sm" style="margin-bottom: 20px;">➕</a>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h1>
                                    <?php
                                        if ($filter === "reminders") echo "🔔 Reminders";
                                        elseif ($filter === "tag") echo "🏷️ " . sanitize($_GET["label"] ?? "");
                                        elseif ($filter === "archive") echo "📥 Archive";
                                        elseif ($filter === "garbage") echo "🗑️ Garbage";
                                        elseif ($filter === "checklist") echo "☑️ Checklist";
                                        elseif ($filter === "pinned") echo "📌 Pinned";
                                        elseif ($filter === "images") echo "🖼️ Images";
                                        elseif ($filter === "important") echo "★ Important";
                                        elseif ($filter === "shared_with") echo "👤 Shared With";
                                        else echo "💡 Notes";
                                    ?>
                                </h1>
                                <div style="display: flex; gap: 10px;">
                                    <?php if ($crud_action === 'index'): ?>
                                        <a href="list_all.php" class="btn btn-sm">📊 Table View</a>
                                    <?php else: ?>
                                        <a href="index.php" class="btn btn-sm">💡 Keep View</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="date-subtitle"><?php echo date("l, F j"); ?></div>
                        </div>

                        <div class="card" style="margin-bottom:16px; margin-top: 20px;">
                            <form method="GET" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                                <input type="hidden" name="filter" value="<?php echo sanitize($filter); ?>">
                                <?php if ($filter === 'tag' && isset($_GET['label'])): ?>
                                    <input type="hidden" name="label" value="<?php echo sanitize($_GET['label']); ?>">
                                <?php endif; ?>
                                <div class="form-group" style="margin:0;min-width:260px;flex:1;">
                                    <label for="moduleSearch">Search (all fields)</label>
                                    <input type="text" id="moduleSearch" name="search" value="<?php echo sanitize($search); ?>" placeholder="Type to search records...">
                                </div>
                                <div class="form-actions" style="margin:0;display:flex;gap:8px;">
                                    <button type="submit" class="btn btn-primary">Search</button>
                                    <a href="<?php echo $crud_action === 'list_all' ? 'list_all.php' : 'index.php'; ?>" class="btn">🔙</a>
                                </div>
                            </form>
                        </div>

                        <!-- Toolbar for Tools (Excel/PDF) -->
                        <div class="card" style="margin-bottom: 20px;">
                            <table data-itm-db-import-endpoint="index.php" style="display:none;">
                                <thead><tr><th>ID</th><th>Title</th><th>Content</th><th>Reminder</th><th>Tags</th><th>Shared With</th><th>Pinned</th><th>Important</th><th>Archived</th></tr></thead>
                                <tbody><?php foreach ($notes as $note): ?><tr><td><?=$note['id']?></td><td><?=sanitize($note['title'])?></td><td><?=sanitize($note['content'])?></td><td><?=$note['reminder_at']?></td><td><?php $lbls = $note_tags_map[$note['id']] ?? []; echo sanitize(implode(", ",$lbls)); ?></td><td><?php $uIds=json_decode($note['shared_with_json']??'[]',true); $names=[]; foreach($uIds as $uid) if(isset($users[$uid]))$names[]=$users[$uid]['username']; echo sanitize(implode(", ",$names)); ?></td><td><?=$note['is_pinned']?'Yes':'No'?></td><td><?=$note['is_important']?'Yes':'No'?></td><td><?=$note['is_archived']?'Yes':'No'?></td></tr><?php endforeach; ?></tbody>
                            </table>
                        </div>

                        <?php if ($crud_action === "index"): ?>

                            <div class="quick-add">
                                <div style="display: flex; align-items: center; width: 100%;">
                                    <input type="text" id="quickAddInput" placeholder="Take a note..." onkeypress="if(event.key==='Enter') quickAdd()">
                                    <div style="display: flex; gap: 15px; margin-left: 10px;">
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
                                <textarea id="quickAddContent" rows="6" cols="80" placeholder="Content..." style="margin-top: 10px; width: 100%; display: block;" onkeypress="if(event.key==='Enter' && !event.shiftKey) { event.preventDefault(); quickAdd(); }"></textarea>
                                <input type="file" id="quickAddImageInput" multiple accept="image/*" style="display: none;" onchange="handleQuickImageSelect(event)">
                                <div id="quickAddPreview" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;"></div>
                                <div id="quickAddReminderPreview" style="display: none; align-items: center; gap: 8px; margin-top: 10px; font-size: 14px; color: var(--accent); font-weight: 600;">
                                    <span>🔔</span> <span id="quickAddReminderText"></span>
                                    <span onclick="setQuickReminder('remove', event)" style="cursor: pointer; color: var(--danger); font-size: 18px; margin-left: 5px;">&times;</span>
                                </div>
                                <input type="hidden" id="quickAddIsChecklist" value="0">
                                <input type="hidden" id="quickAddReminderAt" value="">
                            </div>

                            <div class="notes-list">
                                <?php if (empty($notes)): ?>
                                <!--    <div class="empty-state"><i style="font-size: 48px; display: block; margin-bottom: 20px; opacity: 0.5;">📋</i><p>No notes found.</p></div> -->
								<div class="empty-state">  <span style="font-size:48px; display:block; margin-bottom:20px; opacity:0.5;">📋</span><p>No notes found.</p></div>
                                <?php else: ?>
                                    <?php foreach ($notes as $note): ?>
                                        <div class="note-item" style="background-color: <?php echo $note["color"] ?? "var(--bg-primary)"; ?>;">
                                            <div class="note-main" onclick="location.href='view.php?id=<?php echo $note["id"]; ?>'" style="flex: 1; cursor: pointer;">
                                                <div class="note-title" style="font-weight: 600;"><?php echo sanitize($note["title"] ?: '(Untitled)'); ?></div>
                                                <div class="note-meta" style="font-size: 12px; color: var(--text-secondary); display: flex; gap: 10px; flex-wrap: wrap;">
                                                    <?php if ($note['is_pinned']): ?><span>• 📌</span><?php endif; ?>
                                                    <?php if ($note['is_checklist']): ?><span>• ☑️</span><?php endif; ?>
                                                    <?php
                                                    $note_tags = $note_tags_map[$note['id']] ?? [];
                                                    if (!empty($note_tags)) {
                                                        $formatted_tags = array_map(function($tag) { return '🏷️ ' . sanitize($tag); }, $note_tags);
                                                        echo '<span>• ' . implode(' - ', $formatted_tags) . '</span>';
                                                    }
                                                    ?>
                                                    <?php if (!empty($note['images_json'])): ?><span>• 🖼️</span><?php endif; ?>
                                                    <?php
                                                    $shared_ids = json_decode($note['shared_with_json'] ?? '[]', true);
                                                    if (!empty($shared_ids)) {
                                                        $shared_names = [];
                                                        foreach ($shared_ids as $sid) {
                                                            if (isset($users[$sid])) $shared_names[] = $users[$sid]['username'];
                                                        }
                                                        if (!empty($shared_names)) {
                                                            echo '<span>• 👥 ' . sanitize(implode(' - ', $shared_names)) . '</span>';
                                                        }
                                                    }
                                                    ?>
                                                    <?php if (!empty($note['reminder_at'])): ?>
                                                        <?php
                                                        $isToday = date("Y-m-d", strtotime($note["reminder_at"])) === date("Y-m-d");
                                                        $remLabel = $isToday ? "TODAY, " . date("H:i", strtotime($note["reminder_at"])) : date("M j, H:i", strtotime($note["reminder_at"]));
                                                        $remStyle = $isToday ? 'style="color: var(--danger); font-weight: 600;"' : '';
                                                        ?>
                                                        <span <?php echo $remStyle; ?>>• 🔔 <?php echo $remLabel; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="note-star <?php echo $note["is_pinned"] ? "active" : ""; ?>" onclick="togglePinned(<?php echo $note["id"]; ?>, this)" data-pinned="<?php echo $note['is_pinned']; ?>" title="Pin">
                                                <?php echo $note["is_pinned"] ? "📌" : "📌"; ?>
                                            </div>
                                            <div class="note-star <?php echo $note["is_important"] ? "active" : ""; ?>" onclick="toggleImportant(<?php echo $note["id"]; ?>, this)" data-important="<?php echo $note['is_important']; ?>" title="Important" style="margin-left: 10px;">
                                                <?php echo $note["is_important"] ? "★" : "☆"; ?>
                                            </div>
                                            <div class="note-star <?php echo $note["is_archived"] ? "active" : ""; ?>" onclick="toggleArchived(<?php echo $note["id"]; ?>, this)" data-archived="<?php echo $note['is_archived']; ?>" title="Archive" style="margin-left: 10px;">
                                                📥
                                            </div>
                                            <a href="edit.php?id=<?php echo $note["id"]; ?>" style="margin-left:15px; text-decoration:none;" title="Edit">✏️</a>
                                            <?php if ($filter === "garbage"): ?>
                                                <div class="note-star" onclick="restoreNote(<?php echo $note["id"]; ?>)" title="Restore" style="margin-left: 10px; color: var(--success);">
                                                    🔄
                                                </div>
                                            <?php endif; ?>
                                            <div class="note-star" onclick="deleteNote(<?php echo $note["id"]; ?>)" title="Delete" style="margin-left: 10px; color: var(--danger);">
                                                🗑️
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- TABLE VIEW -->
                            <div class="card" style="overflow:auto;">
                                <table data-itm-db-import-endpoint="index.php">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Reminder</th>
                                            <th>Tags</th>
                                            <th>Shared With</th>
                                            <th>Pinned</th>
                                            <th>Important</th>
                                            <th>Archived</th>
                                            <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($notes)): ?>
                                            <tr><td colspan="8" style="text-align:center;">No records found.</td></tr>
                                        <?php else: foreach ($notes as $note): ?>
                                            <tr style="background-color: <?php echo $note['color']; ?>22;">
                                                <td><?php echo sanitize($note['title'] ?: '(Untitled)'); ?></td>
                                                <td><?php echo $note['reminder_at'] ? date("M j, H:i", strtotime($note['reminder_at'])) : '—'; ?></td>
                                                <td><?php $lbls = $note_tags_map[$note['id']] ?? []; echo sanitize(implode(", ",$lbls)); ?></td>
                                                <td><?php $uIds=json_decode($note['shared_with_json']??'[]',true); $names=[]; foreach($uIds as $uid) if(isset($users[$uid]))$names[]=$users[$uid]['username']; echo sanitize(implode(", ",$names)); ?></td>
                                                <td><?php echo $note['is_pinned'] ? '✅' : '❌'; ?></td>
                                                <td><?php echo $note['is_important'] ? '✅' : '❌'; ?></td>
                                                <td><?php echo $note['is_archived'] ? '✅' : '❌'; ?></td>
                                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                                    <div class="itm-actions-wrap">
                                                        <a class="btn btn-sm" href="view.php?id=<?php echo $note['id']; ?>">🔎</a>
                                                        <a class="btn btn-sm" href="edit.php?id=<?php echo $note['id']; ?>">✏️</a>
                                                        <?php if ($filter === "garbage"): ?>
                                                            <button class="btn btn-sm btn-success" onclick="restoreNote(<?php echo $note['id']; ?>)">🔄</button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteNote(<?php echo $note['id']; ?>)">🗑️</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($totalPages > 1): ?>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                                    <div>Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
                                    <div style="display:flex;gap:6px;">
                                        <?php if ($page > 1): ?>
                                            <a class="btn btn-sm" href="?filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" title="◀️ Previous">Previous</a>
                                        <?php endif; ?>
                                        <span class="btn btn-sm" style="pointer-events:none;"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
                                        <?php if ($page < $totalPages): ?>
                                            <a class="btn btn-sm" href="?filter=<?php echo urlencode($filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" title="▶️ Next">Next</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                    <?php elseif ($crud_action === "edit" || $crud_action === "create"): ?>
                        <h1><?php echo $crud_action === "edit" ? "Edit Note" : "New Note"; ?></h1>
                        <form method="POST" class="form-grid" style="max-width: 800px;" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="form-group"><label>Title</label><input type="text" name="title" value="<?php echo sanitize($data["title"] ?? ""); ?>" autofocus></div>
                            <div class="form-group" id="content-section" style="<?php echo !empty($data['is_checklist']) ? 'display: none;' : ''; ?>">
                                <label>Content</label>
                                <textarea name="content" rows="5"><?php echo sanitize($data["content"] ?? ""); ?></textarea>
                            </div>
                            <div class="form-group"><label>Images</label>
                                <div id="image-drop-zone" style="border: 2px dashed var(--border); padding: 20px; text-align: center; border-radius: 8px; cursor: pointer;" ondragover="event.preventDefault();" ondrop="handleDrop(event)" onclick="document.getElementById('file-input').click()">
                                    <p>Drag and drop images here or click to upload</p>
                                    <input type="file" id="file-input" name="images[]" multiple accept="image/*" style="display: none;" onchange="handleFileSelect(event)">
                                </div>
                                <div id="image-preview-container" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px;">
                                    <?php $imgs = json_decode($data['images_json'] ?? '[]', true); if (is_array($imgs)): foreach ($imgs as $img): $imgPath = itm_files_serve_url('Private/' . $_SESSION['username'] . '_' . $logged_user_id . '/notes/' . $img); ?>
                                        <div class="image-item" style="position: relative;"><img src="<?php echo $imgPath; ?>" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px;"><input type="hidden" name="existing_images[]" value="<?php echo sanitize($img); ?>"><span onclick="this.parentElement.remove()" style="position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer;">&times;</span></div>
                                    <?php endforeach; endif; ?>
                                </div>
                            </div>
                            <div class="form-group" id="reminder-section">
                                <label>Reminder 🔔</label>
                                <input type="datetime-local" name="reminder_at" id="reminder_at_input" value="<?php echo isset($data["reminder_at"]) ? str_replace(" ", "T", substr($data["reminder_at"], 0, 16)) : ""; ?>" onchange="updateReminderCheckbox()">
                            </div>
                            <div class="form-group" id="checklist-section">
                                <label>Checklist</label>
                                <div id="checklist-container">
                                    <?php $checklist = json_decode($data['checklist_json'] ?? '[]', true); if (is_array($checklist)): foreach ($checklist as $item): ?>
                                        <div class="checklist-item" style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                                            <input type="checkbox" name="checklist_completed[]" value="1" <?php echo !empty($item['completed']) ? 'checked' : ''; ?> style="width: 18px; height: 18px;">
                                            <input type="text" name="checklist_text[]" value="<?php echo sanitize($item['text'] ?? ''); ?>" style="flex: 1; border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px;">
                                            <span onclick="this.parentElement.remove()" style="cursor: pointer; color: var(--danger); font-size: 18px;">&times;</span>
                                        </div>
                                    <?php endforeach; endif; ?>
                                </div>
                                <button type="button" class="btn btn-sm" onclick="addChecklistItem()" style="margin-top: 10px; display: flex; align-items: center; gap: 5px; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px; padding: 8px 15px;">
                                    <span style="color: var(--accent); font-weight: bold; font-size: 18px;">+</span> Add item
                                </button>
                            </div>
                            <div class="form-group"><label>Tags</label>
                                <select name="category_id[]" multiple size="5" data-addable-select="1" data-add-table="note_labels" data-add-label-col="label" data-add-company-scoped="1">
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
                                <select name="shared_with_json[]" multiple size="5" data-addable-select="1" data-add-table="employees" data-add-label-col="username" data-add-company-scoped="1">
                                    <option value="">-- None --</option><option value="__add_new__">➕</option>
                                <?php
                                // Ensure users are always loaded for this dropdown
                                if (empty($users)) {
                                    $join = itm_employee_active_employment_status_join_sql('e', 'es');
                                    $predicate = itm_employee_active_employment_status_predicate_sql('es');
                                    $stmtULoad = $conn->prepare('SELECT e.id, e.username FROM employees e' . $join . ' WHERE e.company_id = ? AND ' . $predicate);
                                    $stmtULoad->bind_param("i", $company_id);
                                    $stmtULoad->execute();
                                    $resULoad = $stmtULoad->get_result();
                                    while ($rowU = mysqli_fetch_assoc($resULoad)) { $users[$rowU["id"]] = $rowU; }
                                }
                                $sharedUsers = json_decode($data['shared_with_json'] ?? '[]', true);
                                foreach ($users as $u): ?>
                                    <option value="<?php echo $u["id"]; ?>" <?php echo is_array($sharedUsers) && in_array($u["id"], $sharedUsers) ? "selected" : ""; ?>><?php echo sanitize($u["username"]); ?></option>
                                <?php endforeach; ?>
                            </select></div>
                            <div style="display: flex; gap: 30px; margin-top: 10px;">
                                <label class="itm-checkbox-control"><input type="checkbox" name="is_checklist" value="1" <?php echo (!empty($data["is_checklist"]) || !empty($data['checklist_json'])) ? "checked" : ""; ?> onchange="toggleChecklistSection(this.checked)"><span>Checklist ☑️</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" name="is_pinned" value="1" <?php echo !empty($data["is_pinned"]) ? "checked" : ""; ?>><span>Pinned 📌</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" name="is_important" value="1" <?php echo !empty($data["is_important"]) ? "checked" : ""; ?>><span>Important ★</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" name="is_archived" value="1" <?php echo !empty($data["is_archived"]) ? "checked" : ""; ?>><span>Archived 📥</span></label>
                                <label class="itm-checkbox-control"><input type="checkbox" id="reminder_checkbox" onchange="toggleReminderSection(this.checked)" <?php echo !empty($data['reminder_at']) ? 'checked' : ''; ?>><span>Reminder 🔔</span></label>
                            </div>
                            <div class="form-actions" style="margin-top: 30px;"><button class="btn btn-primary" type="submit">💾 Save</button><a href="index.php" class="btn">🔙 Cancel</a>
                                <?php if ($crud_action === "edit"): ?><button type="submit" name="bulk_action" value="single_delete" class="btn btn-danger" style="margin-left: auto;" onclick="return confirm('Delete this note?')">🗑️ Delete</button><?php endif; ?>
                            </div>
                        </form>

                    <?php elseif ($crud_action === "view"): ?>
                        <h1>Note Details</h1>
                        <div class="card" data-itm-pdf-preview="1" style="max-width: 800px; background-color: <?php echo $data["color"] ?? "var(--bg-primary)"; ?>; border: 1px solid var(--border); border-radius: 8px; padding: 20px;">
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
                                <div class="itm-floor-plan-view-preview" style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;"><?php foreach ($vimgs as $img): $imgPath = itm_files_serve_url('Private/' . $_SESSION['username'] . '_' . $logged_user_id . '/notes/' . $img); ?>
                                    <div style="text-align: center;">
                                        <img src="<?php echo $imgPath; ?>" class="itm-floor-plan-view-image" onclick="openImageModal('<?php echo $imgPath; ?>')" style="width: 150px; height: 150px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); display: block; margin-bottom: 5px; cursor: pointer;">
                                        <div style="font-size: 12px; display: flex; justify-content: center; gap: 10px;"><a href="#" onclick="openImageModal('<?php echo $imgPath; ?>'); return false;" style="text-decoration: none;">👁️ Preview</a><a href="<?php echo $imgPath; ?>" download style="text-decoration: none;">📥 Download</a></div>
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
                            <div class="form-actions" style="margin-top: 30px;"><a href="edit.php?id=<?php echo $data["id"]; ?>" class="btn btn-primary">✏️</a><a href="index.php" class="btn">🔙</a></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../js/theme.js"></script>
<script>window.ITM_CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;</script>
<script src="../../js/xlsx.full.min.js"></script>
<script src="../../js/table-tools.js"></script>

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
    function openDatePickerModal() {
        document.querySelectorAll('.quick-add-dropdown').forEach(d => d.classList.remove('show'));
        const modal = document.getElementById('datePickerModal');
        const backdrop = document.getElementById('modalBackdrop');
        const input = document.getElementById('modalDatePickerInput');

        const currentVal = document.getElementById('quickAddReminderAt').value;
        if (currentVal) {
            input.value = currentVal.replace(' ', 'T').substring(0, 16);
        } else {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            input.value = now.toISOString().substring(0, 16);
        }

        modal.classList.add('show');
        backdrop.classList.add('show');
    }

    function closeDatePickerModal() {
        document.getElementById('datePickerModal').classList.remove('show');
        document.getElementById('modalBackdrop').classList.remove('show');
    }

    function saveQuickDate() {
        const input = document.getElementById('modalDatePickerInput');
        const val = input.value;
        if (!val) {
            alert("Please select a date");
            return;
        }
        const dbValue = val.replace('T', ' ') + ':00';
        document.getElementById('quickAddReminderAt').value = dbValue;
        document.getElementById('quickReminderBtn').style.color = 'var(--accent)';

        const preview = document.getElementById('quickAddReminderPreview');
        const text = document.getElementById('quickAddReminderText');
        if (preview && text) {
            preview.style.display = 'flex';
            text.innerText = dbValue;
        }

        closeDatePickerModal();
    }

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
            openDatePickerModal();
            return;
        } else if (type === 'remove') {
            dbValue = '';
        }

        input.value = dbValue;
        btn.style.color = dbValue ? 'var(--accent)' : 'var(--text-secondary)';

        const preview = document.getElementById('quickAddReminderPreview');
        const text = document.getElementById('quickAddReminderText');
        if (dbValue) {
            preview.style.display = 'flex';
            text.innerText = dbValue;
        } else {
            preview.style.display = 'none';
        }

        document.getElementById('quickReminderDropdown').classList.remove('show');
    }
    function handleQuickImageSelect(event) { quickAddFiles = quickAddFiles.concat(Array.from(event.target.files)); renderQuickPreview(); }
    function renderQuickPreview() { const preview = document.getElementById('quickAddPreview'); if (!preview) return; preview.innerHTML = ''; quickAddFiles.forEach((file, index) => { const div = document.createElement('div'); div.style.position = 'relative'; const img = document.createElement('img'); img.src = URL.createObjectURL(file); img.style.cssText = 'width: 60px; height: 60px; object-fit: cover; border-radius: 4px;'; const close = document.createElement('span'); close.innerHTML = '&times;'; close.style.cssText = 'position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; cursor: pointer;'; close.onclick = () => { quickAddFiles.splice(index, 1); renderQuickPreview(); }; div.appendChild(img); div.appendChild(close); preview.appendChild(div); }); }
    function quickAdd() {
        const input = document.getElementById("quickAddInput");
        const contentInput = document.getElementById("quickAddContent");
        if (!input) return;
        const title = input.value.trim();
        const content = contentInput ? contentInput.value.trim() : "";
        if (!title && !content && quickAddFiles.length === 0) {
            alert("Please add a title, content or an image.");
            return;
        }
        const formData = new FormData();
        formData.append("csrf_token", CSRF_TOKEN);
        formData.append("title", title);
        formData.append("content", content);
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
    function toggleArchived(id, el) { const newVal = el.dataset.archived === '1' ? 0 : 1; const formData = new FormData(); formData.append("csrf_token", CSRF_TOKEN); formData.append("id", id); formData.append("is_archived", newVal); fetch("index.php?ajax_action=toggle_archived", { method: "POST", body: formData }).then(r => r.json()).then(data => { if (data.ok) location.reload(); }); }
    function restoreNote(id) {
        const formData = new FormData();
        formData.append("csrf_token", CSRF_TOKEN);
        formData.append("id", id);
        fetch("index.php?ajax_action=restore", { method: "POST", body: formData })
            .then(r => r.json())
            .then(data => { if (data.ok) location.reload(); else alert("Error restoring note."); });
    }
    function deleteNote(id) { 
        const isGarbage = new URLSearchParams(window.location.search).get('filter') === 'garbage';
        if (!confirm(isGarbage ? 'Delete this note forever?' : 'Move this note to garbage?')) return;
        const formData = new FormData(); formData.append("csrf_token", CSRF_TOKEN); formData.append("id", id); fetch("index.php?ajax_action=single_delete", { method: "POST", body: formData }).then(r => r.json()).then(data => { if (data.ok) location.reload(); }); 
    }
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
    function addChecklistItem() { 
        const container = document.getElementById('checklist-container'); 
        const div = document.createElement('div'); 
        div.className = 'checklist-item';
        div.style.cssText = 'display: flex; align-items: center; gap: 15px; margin-bottom: 10px;'; 
        div.innerHTML = '<input type="checkbox" name="checklist_completed[]" value="1" style="width: 18px; height: 18px;"><input type="text" name="checklist_text[]" placeholder="List item" style="flex: 1; border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px;"><span onclick="this.parentElement.remove()" style="cursor: pointer; color: var(--danger); font-size: 18px;">&times;</span>'; 
        container.appendChild(div); 
        div.querySelector('input[type="text"]').focus(); 
    }
    function toggleChecklistSection(checked) { 
        document.getElementById('content-section').style.display = checked ? 'none' : '';
    }
    function toggleReminderSection(checked) { 
        if (!checked) document.getElementById("reminder_at_input").value = "";
    }
    function updateReminderCheckbox() {
        const val = document.getElementById("reminder_at_input").value;
        const cb = document.getElementById("reminder_checkbox");
        if (cb) cb.checked = (val !== "");
    }
    function handleDrop(e) { e.preventDefault(); handleFiles(e.dataTransfer.files); }
    function handleFileSelect(e) { handleFiles(e.target.files); }
    function handleFiles(files) { const container = document.getElementById('image-preview-container'); Array.from(files).forEach(file => { if (!file.type.startsWith('image/')) return; const reader = new FileReader(); reader.onload = (e) => { const div = document.createElement('div'); div.className = 'image-item'; div.style.position = 'relative'; const img = document.createElement('img'); img.src = e.target.result; img.style.cssText = 'width: 100px; height: 100px; object-fit: cover; border-radius: 4px;'; const span = document.createElement('span'); span.innerHTML = '&times;'; span.style.cssText = 'position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer;'; span.onclick = () => div.remove(); div.appendChild(img); div.appendChild(span); container.appendChild(div); }; reader.readAsDataURL(file); }); }
</script>

<div class="modal" id="imageModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document" style="max-width: 90%;"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Image Preview</h5><button type="button" class="close" onclick="closeImageModal()">&times;</button></div>
    <div class="modal-body" style="text-align: center;"><img id="modalImage" src="" style="max-width: 100%; max-height: 80vh; border-radius: 4px;"></div>
    <div class="modal-footer"><a id="downloadModalImage" href="" download class="btn btn-primary">📥 Download</a><button type="button" class="btn" onclick="closeImageModal()">Close</button></div></div></div>
</div>

<div class="modal" id="datePickerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Choose Date</h5>
                <button type="button" class="close" onclick="closeDatePickerModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Select Date and Time</label>
                    <input type="datetime-local" id="modalDatePickerInput">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeDatePickerModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveQuickDate()">Set Date</button>
            </div>
        </div>
    </div>
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
