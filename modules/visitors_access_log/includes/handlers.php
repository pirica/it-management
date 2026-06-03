<?php
/**
 * Handlers for Visitors Access Log Module
 */

// AJAX Inline Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_inline_edit'])) {
    itm_require_post_csrf();
    header('Content-Type: application/json; charset=UTF-8');

    $id = (int)($_POST['id'] ?? 0);
    $field = trim((string)($_POST['field'] ?? ''));
    $value = trim((string)($_POST['value'] ?? ''));

    if ($id <= 0 || !itm_is_safe_identifier($field) || !isset($displayFieldColumns[$field])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    // Check if the record is from today (security check)
    $stmt = mysqli_prepare($conn, "SELECT date_time_in FROM visitors_access_log WHERE id = ? AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$row || !val_is_today($row['date_time_in'])) {
        echo json_encode(['success' => false, 'message' => 'Only today\'s records can be edited.']);
        exit;
    }

    $sql = "UPDATE visitors_access_log SET $field = ? WHERE id = ? AND company_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sii', $value, $id, $company_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'message' => $ok ? 'Updated.' : 'Update failed.']);
    exit;
}

// Handle Timestamp Buttons (In/Out)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_timestamp'])) {
    itm_require_post_csrf();
    header('Content-Type: application/json; charset=UTF-8');

    $id = (int)($_POST['id'] ?? 0);
    $type = trim((string)($_POST['type'] ?? '')); // 'in' or 'out'
    $now = date('Y-m-d H:i:s');

    if ($id <= 0 || !in_array($type, ['in', 'out'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }

    $field = ($type === 'in') ? 'date_time_in' : 'date_time_out';
    $sql = "UPDATE visitors_access_log SET $field = ? WHERE id = ? AND company_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sii', $now, $id, $company_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo json_encode(['success' => $ok, 'value' => $now, 'formatted' => val_format_datetime($now)]);
    exit;
}

// Handle Quick Add (First Row)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_quick_add'])) {
    itm_require_post_csrf();

    $visitor_name = trim((string)($_POST['visitor_name'] ?? ''));
    $company_department = trim((string)($_POST['company_department'] ?? ''));
    $reason_for_visit = trim((string)($_POST['reason_for_visit'] ?? ''));
    $pre_approved_by = trim((string)($_POST['pre_approved_by'] ?? ''));
    $room_opened_by = trim((string)($_POST['room_opened_by'] ?? ''));
    $date_time_in = !empty($_POST['date_time_in']) ? $_POST['date_time_in'] : date('Y-m-d H:i:s');

    if ($visitor_name === '') {
        $_SESSION['crud_error'] = 'Visitor name is required.';
        header('Location: index.php');
        exit;
    }

    $sql = "INSERT INTO visitors_access_log (company_id, visitor_name, company_department, reason_for_visit, pre_approved_by, room_opened_by, date_time_in) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'issssss', $company_id, $visitor_name, $company_department, $reason_for_visit, $pre_approved_by, $room_opened_by, $date_time_in);

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['crud_success'] = 'Visitor logged.';
    } else {
        $_SESSION['crud_error'] = 'Error logging visitor.';
    }
    mysqli_stmt_close($stmt);

    header('Location: index.php');
    exit;
}

// Handle Full Form Save (Create/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($crud_action, ['create', 'edit'], true)) {
    itm_require_post_csrf();

    $id = (int)($_POST['id'] ?? 0);
    $visitor_name = trim((string)($_POST['visitor_name'] ?? ''));
    $company_department = trim((string)($_POST['company_department'] ?? ''));
    $reason_for_visit = trim((string)($_POST['reason_for_visit'] ?? ''));
    $pre_approved_by = trim((string)($_POST['pre_approved_by'] ?? ''));
    $room_opened_by = trim((string)($_POST['room_opened_by'] ?? ''));
    $date_time_in = !empty($_POST['date_time_in']) ? str_replace('T', ' ', $_POST['date_time_in']) : null;
    $date_time_out = !empty($_POST['date_time_out']) ? str_replace('T', ' ', $_POST['date_time_out']) : null;

    if ($visitor_name === '') {
        $errors[] = 'Visitor name is required.';
    }

    // Security: Check if editing a past record
    if ($crud_action === 'edit' && $id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT date_time_in FROM visitors_access_log WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row && !val_is_today($row['date_time_in'])) {
            $errors[] = 'Only today\'s records can be edited.';
        }
    }

    if (empty($errors)) {
        if ($crud_action === 'create') {
            $sql = "INSERT INTO visitors_access_log (company_id, visitor_name, company_department, reason_for_visit, pre_approved_by, room_opened_by, date_time_in, date_time_out) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'isssssss', $company_id, $visitor_name, $company_department, $reason_for_visit, $pre_approved_by, $room_opened_by, $date_time_in, $date_time_out);
        } else {
            $sql = "UPDATE visitors_access_log SET visitor_name = ?, company_department = ?, reason_for_visit = ?, pre_approved_by = ?, room_opened_by = ?, date_time_in = ?, date_time_out = ? WHERE id = ? AND company_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssssssii', $visitor_name, $company_department, $reason_for_visit, $pre_approved_by, $room_opened_by, $date_time_in, $date_time_out, $id, $company_id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_success'] = 'Visitor log saved.';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Error saving visitor log: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

// Fetch data for Edit/View
$data = [];
$editId = (int)($_GET['id'] ?? 0);
if ($editId > 0 && in_array($crud_action, ['edit', 'view'], true)) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM visitors_access_log WHERE id = ? AND company_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $editId, $company_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$data) {
        $_SESSION['crud_error'] = 'Record not found.';
        header('Location: index.php');
        exit;
    }
}

// Standard CRUD Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $crud_action === 'delete') {
    itm_require_post_csrf();

    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'clear_table') {
        $stmt = mysqli_prepare($conn, "DELETE FROM visitors_access_log WHERE company_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['crud_success'] = 'Log cleared.';
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['bulk_action']) && $_POST['bulk_action'] === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM visitors_access_log WHERE id IN ($placeholders) AND company_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            $types = str_repeat('i', count($ids)) . 'i';
            $params = array_map('intval', $ids);
            $params[] = $company_id;
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['crud_success'] = 'Selected entries deleted.';
        }
        header('Location: index.php');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM visitors_access_log WHERE id = ? AND company_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['crud_success'] = 'Entry deleted.';
    }
    header('Location: index.php');
    exit;
}
