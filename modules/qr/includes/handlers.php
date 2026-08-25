<?php
/**
 * QR Generator — POST handlers (save, delete, upload).
 */

if (!isset($conn) || !($conn instanceof mysqli)) {
    return;
}

require_once ROOT_PATH . 'includes/itm_qr_generator.php';

$qrEmployeeId = (int) ($_SESSION['employee_id'] ?? 0);
$qrCompanyId = (int) ($company_id ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_action']) && $_POST['qr_action'] === 'upload_asset') {
    header('Content-Type: application/json; charset=utf-8');
    itm_require_post_csrf();
    if (!itm_qr_generator_ensure_upload_dir($qrCompanyId, $qrEmployeeId)) {
        echo json_encode(['ok' => false, 'error' => 'Upload directory unavailable.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        echo json_encode(['ok' => false, 'error' => 'No file uploaded.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $name = (string) ($_FILES['file']['name'] ?? '');
    if ($name === '' || $name[0] === '.') {
        echo json_encode(['ok' => false, 'error' => 'Invalid file name.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    $relDir = (string) $qrCompanyId . '/' . itm_qr_generator_upload_relative_dir($qrCompanyId, $qrEmployeeId);
    $absDir = rtrim(itm_files_storage_root(), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
    $target = $absDir . DIRECTORY_SEPARATOR . $safe;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        echo json_encode(['ok' => false, 'error' => 'Upload failed.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $storedRel = $relDir . '/' . $safe;
    echo json_encode(['ok' => true, 'path' => $storedRel], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_action']) && $_POST['qr_action'] === 'list_design_templates') {
    header('Content-Type: application/json; charset=utf-8');
    itm_require_post_csrf();
    echo json_encode([
        'ok' => true,
        'templates' => itm_qr_generator_design_templates_for_api($conn, $qrCompanyId, $qrEmployeeId),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_action']) && $_POST['qr_action'] === 'save_design_template') {
    header('Content-Type: application/json; charset=utf-8');
    itm_require_post_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $designRaw = json_decode((string) ($_POST['design_json'] ?? ''), true);
    if (!is_array($designRaw)) {
        $designRaw = [];
    }
    $result = itm_qr_generator_save_design_template($conn, $qrCompanyId, $qrEmployeeId, $name, $designRaw, $qrEmployeeId);
    if (empty($result['ok'])) {
        echo json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'Save failed.')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'id' => (int) ($result['id'] ?? 0),
        'templates' => itm_qr_generator_design_templates_for_api($conn, $qrCompanyId, $qrEmployeeId),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_action']) && $_POST['qr_action'] === 'delete_design_template') {
    header('Content-Type: application/json; charset=utf-8');
    itm_require_post_csrf();
    $templateId = (int) ($_POST['template_id'] ?? 0);
    $result = itm_qr_generator_delete_design_template($conn, $qrCompanyId, $qrEmployeeId, $templateId, $qrEmployeeId);
    if (empty($result['ok'])) {
        echo json_encode(['ok' => false, 'error' => (string) ($result['error'] ?? 'Delete failed.')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'templates' => itm_qr_generator_design_templates_for_api($conn, $qrCompanyId, $qrEmployeeId),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_action']) && $_POST['qr_action'] === 'save') {
    itm_require_post_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $typeSlug = trim((string) ($_POST['type_slug'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $encodingMode = trim((string) ($_POST['encoding_mode'] ?? 'dynamic'));
    $payloadRaw = $_POST['payload'] ?? [];
    if (!is_array($payloadRaw)) {
        $payloadRaw = [];
    }
    if ($typeSlug === 'menu' && isset($payloadRaw['sections_json'])) {
        $decoded = json_decode((string) $payloadRaw['sections_json'], true);
        $payloadRaw['sections'] = is_array($decoded) ? $decoded : [];
        unset($payloadRaw['sections_json']);
    }
    $designRaw = $_POST['design'] ?? [];
    if (!is_array($designRaw)) {
        $designRaw = [];
    }

    $saveData = [
        'title' => $title,
        'type_slug' => $typeSlug,
        'encoding_mode' => $encodingMode,
        'payload' => $payloadRaw,
    ];
    $errors = itm_qr_generator_validate_save($saveData);
    if ($errors) {
        $_SESSION['qr_flash_error'] = implode(' ', $errors);
        $redirect = $id > 0 ? 'edit.php?id=' . $id : 'create.php';
        if ($typeSlug !== '') {
            $redirect .= (strpos($redirect, '?') !== false ? '&' : '?') . 'type=' . rawurlencode($typeSlug);
        }
        header('Location: ' . $redirect);
        exit;
    }

    $payload = itm_qr_generator_normalize_payload($typeSlug, $payloadRaw);
    if (itm_qr_generator_type_requires_dynamic($typeSlug)) {
        $encodingMode = 'dynamic';
    }
    $catalog = itm_qr_generator_type_catalog();
    if (!empty($catalog[$typeSlug]['static_only'])) {
        $encodingMode = 'static';
    }

    $shortUrlId = null;
    if ($typeSlug === 'website' && $encodingMode === 'dynamic' && !empty($payloadRaw['use_short_url'])) {
        require_once ROOT_PATH . 'includes/itm_short_url.php';
        $destForShort = trim((string) ($payload['url'] ?? ''));
        if ($destForShort !== '') {
            if ($id > 0) {
                $existingForShort = itm_qr_generator_fetch_by_id($conn, $qrCompanyId, $qrEmployeeId, $id);
                if ($existingForShort && !empty($existingForShort['short_url_id'])) {
                    $shortUrlId = (int) $existingForShort['short_url_id'];
                    $suExisting = itm_short_url_fetch_by_id($conn, $qrCompanyId, $qrEmployeeId, $shortUrlId);
                    if ($suExisting) {
                        $payload['url'] = itm_short_url_build_public_url((string) $suExisting['short_code'], $conn, $qrCompanyId);
                    }
                }
            }
            if ($shortUrlId === null) {
                $shortOpts = ['title' => ($title !== '' ? $title : 'QR link') . ' (short)'];
                $shortCreate = itm_short_url_create_from_destination($conn, $qrCompanyId, $qrEmployeeId, $destForShort, $shortOpts);
                if (!empty($shortCreate['ok'])) {
                    $shortUrlId = (int) $shortCreate['id'];
                    $payload['url'] = (string) $shortCreate['public_url'];
                }
            }
        }
    }

    $design = itm_qr_generator_normalize_design($designRaw);
    $encodedPayload = null;
    $accessToken = itm_qr_generator_generate_access_token();
    if ($encodingMode === 'static') {
        $encodedPayload = itm_qr_generator_build_static_payload($typeSlug, $payload);
    }
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $designJson = json_encode($design, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($id > 0) {
        $existing = itm_qr_generator_fetch_by_id($conn, $qrCompanyId, $qrEmployeeId, $id);
        if (!$existing) {
            $_SESSION['qr_flash_error'] = 'QR code not found.';
            header('Location: index.php');
            exit;
        }
        if ($shortUrlId === null && !empty($existing['short_url_id'])) {
            $shortUrlId = (int) $existing['short_url_id'];
        }
        if ($encodingMode === 'dynamic') {
            $accessToken = trim((string) ($existing['access_token'] ?? ''));
            if ($accessToken === '') {
                $accessToken = itm_qr_generator_generate_access_token();
            }
            $encodedPayload = null;
        } else {
            $accessToken = trim((string) ($existing['access_token'] ?? ''));
            if ($accessToken === '') {
                $accessToken = itm_qr_generator_generate_access_token();
            }
            $encodedPayload = itm_qr_generator_build_static_payload($typeSlug, $payload);
        }
        $sql = 'UPDATE qr_codes SET title = ?, type_slug = ?, encoding_mode = ?, payload_json = ?, encoded_payload = ?, access_token = ?, design_json = ?, short_url_id = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            'sssssssiiiii',
            $title,
            $typeSlug,
            $encodingMode,
            $payloadJson,
            $encodedPayload,
            $accessToken,
            $designJson,
            $shortUrlId,
            $qrEmployeeId,
            $id,
            $qrCompanyId,
            $qrEmployeeId
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['qr_flash_success'] = 'QR code updated.';
        header('Location: view.php?id=' . $id);
        exit;
    }

    $sql = 'INSERT INTO qr_codes (company_id, employee_id, title, type_slug, encoding_mode, payload_json, encoded_payload, access_token, design_json, short_url_id, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'iisssssssiii',
        $qrCompanyId,
        $qrEmployeeId,
        $title,
        $typeSlug,
        $encodingMode,
        $payloadJson,
        $encodedPayload,
        $accessToken,
        $designJson,
        $shortUrlId,
        $qrEmployeeId,
        $qrEmployeeId
    );
    mysqli_stmt_execute($stmt);
    $newId = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    $_SESSION['qr_flash_success'] = 'QR code created.';
    header('Location: view.php?id=' . $newId);
    exit;
}

if ($crud_action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();
    $id = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
    if ($id > 0) {
        if (function_exists('itm_crud_build_soft_delete_sql')) {
            $sql = itm_crud_build_soft_delete_sql('qr_codes', 'WHERE id = ? AND company_id = ? AND employee_id = ?', $qrEmployeeId);
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'iii', $id, $qrCompanyId, $qrEmployeeId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $sql = 'UPDATE qr_codes SET active = 0, deleted_by = ?, deleted_at = NOW() WHERE id = ? AND company_id = ? AND employee_id = ? AND deleted_at IS NULL';
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'iiii', $qrEmployeeId, $id, $qrCompanyId, $qrEmployeeId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $_SESSION['qr_flash_success'] = 'QR code deleted.';
    }
    header('Location: index.php');
    exit;
}
