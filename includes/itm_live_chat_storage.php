<?php
/**
 * Live chat attachment paths, chat.json export, and safe file resolution.
 */

if (!function_exists('itm_live_chat_allowed_upload_extension')) {
    function itm_live_chat_allowed_upload_extension($filename)
    {
        if (function_exists('itm_explorer_is_allowed_extension')) {
            return itm_explorer_is_allowed_extension($filename);
        }
        $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'md', 'log', 'json', 'xml', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        return in_array($ext, $allowed, true);
    }
}

if (!function_exists('itm_live_chat_private_storage_dir')) {
    function itm_live_chat_private_storage_dir($conn, $companyId, $employeeId, $conversationId)
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $conversationId = (int)$conversationId;
        $sql = 'SELECT username FROM employees WHERE id = ? AND company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return null;
        }
        $safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$row['username']);
        $folderName = $conversationId . '_chat_' . date('Y-m-d_H-i-s');
        return 'Private/' . $safeUser . '_' . $employeeId . '/Live-Chat/' . $folderName;
    }
}

if (!function_exists('itm_live_chat_ticket_storage_dir')) {
    function itm_live_chat_ticket_storage_dir($ticketId)
    {
        return 'tickets_photos/' . (int)$ticketId;
    }
}

if (!function_exists('itm_live_chat_ensure_storage')) {
    function itm_live_chat_ensure_storage($conn, $conv)
    {
        if ((string)$conv['conversation_type'] === 'live_agent' && !empty($conv['ticket_id'])) {
            $abs = ROOT_PATH . itm_live_chat_ticket_storage_dir((int)$conv['ticket_id']);
            if (function_exists('itm_ensure_upload_directory')) {
                itm_ensure_upload_directory($abs, 'upload');
            } else {
                @mkdir($abs, 0755, true);
            }
            return itm_live_chat_ticket_storage_dir((int)$conv['ticket_id']);
        }
        if (!empty($conv['storage_rel_path'])) {
            $abs = ROOT_PATH . 'files/' . (int)$conv['company_id'] . '/' . ltrim((string)$conv['storage_rel_path'], '/');
            if (function_exists('itm_ensure_files_storage_directory')) {
                itm_ensure_files_storage_directory($abs);
            } else {
                @mkdir($abs, 0755, true);
            }
            return (string)$conv['storage_rel_path'];
        }
        $rel = itm_live_chat_private_storage_dir($conn, (int)$conv['company_id'], (int)$conv['requester_employee_id'], (int)$conv['id']);
        if ($rel === null) {
            return null;
        }
        $abs = ROOT_PATH . 'files/' . (int)$conv['company_id'] . '/' . $rel;
        if (function_exists('itm_ensure_files_storage_directory')) {
            itm_ensure_files_storage_directory($abs);
        } else {
            @mkdir($abs, 0755, true);
        }
        $sql = 'UPDATE live_chat_conversations SET storage_rel_path = ? WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sii', $rel, $conv['id'], $conv['company_id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        return $rel;
    }
}

if (!function_exists('itm_live_chat_storage_absolute_path')) {
    function itm_live_chat_storage_absolute_path($conv, $storageRel)
    {
        if ((string)$conv['conversation_type'] === 'live_agent' && !empty($conv['ticket_id'])) {
            return rtrim(ROOT_PATH . itm_live_chat_ticket_storage_dir((int)$conv['ticket_id']), '/\\');
        }
        return rtrim(ROOT_PATH . 'files/' . (int)$conv['company_id'] . '/' . ltrim((string)$storageRel, '/'), '/\\');
    }
}

if (!function_exists('itm_live_chat_attachment_public_url')) {
    function itm_live_chat_attachment_public_url($conv, $filename)
    {
        $filename = basename((string)$filename);
        if ((string)$conv['conversation_type'] === 'live_agent' && !empty($conv['ticket_id'])) {
            return BASE_URL . 'tickets_photos/' . (int)$conv['ticket_id'] . '/' . rawurlencode($filename);
        }
        $rel = ltrim((string)$conv['storage_rel_path'], '/');
        if (function_exists('itm_files_serve_url')) {
            return itm_files_serve_url($rel . '/' . $filename);
        }
        return BASE_URL . 'modules/explorer/file.php?path=' . rawurlencode($rel . '/' . $filename);
    }
}

if (!function_exists('itm_live_chat_resolve_attachment_path')) {
    function itm_live_chat_resolve_attachment_path($conv, $storageRel, $filename)
    {
        $filename = basename((string)$filename);
        if ($filename === '' || strpos($filename, '..') !== false) {
            return null;
        }
        $base = itm_live_chat_storage_absolute_path($conv, $storageRel);
        $full = $base . DIRECTORY_SEPARATOR . $filename;
        $realBase = realpath($base);
        $realFull = realpath($full);
        if ($realBase === false || $realFull === false || strpos($realFull, $realBase) !== 0) {
            return null;
        }
        return $realFull;
    }
}

if (!function_exists('itm_live_chat_write_chat_json')) {
    function itm_live_chat_write_chat_json($conn, $conv)
    {
        $conversationId = (int)$conv['id'];
        $companyId = (int)$conv['company_id'];
        $storageRel = itm_live_chat_ensure_storage($conn, $conv);
        if ($storageRel === null) {
            return false;
        }
        $sql = 'SELECT m.*, e.first_name, e.last_name, e.username
                FROM live_chat_messages m
                LEFT JOIN employees e ON e.id = m.sender_employee_id
                WHERE m.conversation_id = ? AND m.company_id = ? AND m.deleted_at IS NULL
                ORDER BY m.id ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $conversationId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $messages = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $attachments = [];
            if (!empty($row['attachments_json'])) {
                $decoded = json_decode((string)$row['attachments_json'], true);
                if (is_array($decoded)) {
                    $attachments = $decoded;
                }
            }
            $messages[] = [
                'id' => (int)$row['id'],
                'sender_employee_id' => (int)$row['sender_employee_id'],
                'sender_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: ($row['username'] ?? ''),
                'body' => $row['body'],
                'attachments' => $attachments,
                'created_at' => $row['created_at'],
            ];
        }
        mysqli_stmt_close($stmt);

        $payload = [
            'conversation_id' => $conversationId,
            'conversation_type' => $conv['conversation_type'],
            'ticket_id' => $conv['ticket_id'] !== null ? (int)$conv['ticket_id'] : null,
            'status' => $conv['status'],
            'rating' => $conv['rating'] !== null ? (int)$conv['rating'] : null,
            'updated_at' => date('c'),
            'messages' => $messages,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $base = itm_live_chat_storage_absolute_path($conv, $storageRel);
        $target = $base . DIRECTORY_SEPARATOR . 'chat.json';
        $tmp = $target . '.tmp';
        if (@file_put_contents($tmp, $json) === false) {
            return false;
        }
        return @rename($tmp, $target);
    }
}
