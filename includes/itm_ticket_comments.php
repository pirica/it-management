<?php
/**
 * Ticket threaded comments (separate from live chat bubbles).
 */

if (!function_exists('itm_ticket_comment_body_preview')) {
    function itm_ticket_comment_body_preview($body, $maxLen = 120)
    {
        $body = trim(preg_replace('/\s+/', ' ', (string)$body));
        if ($body === '') {
            return 'Photo attachment';
        }
        if (strlen($body) <= $maxLen) {
            return $body;
        }
        return substr($body, 0, $maxLen - 1) . '…';
    }
}

if (!function_exists('itm_ticket_comment_parse_photos_json')) {
    function itm_ticket_comment_parse_photos_json($rawValue): array
    {
        if (!is_string($rawValue) || trim($rawValue) === '') {
            return [];
        }
        $decoded = json_decode($rawValue, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $decoded), static function ($value) {
            return $value !== '';
        }));
    }
}

if (!function_exists('itm_ticket_comment_photo_public_url')) {
    function itm_ticket_comment_photo_public_url(string $filename): string
    {
        return TICKET_UPLOAD_URL . rawurlencode($filename);
    }
}

if (!function_exists('itm_ticket_comment_detect_upload_mime_type')) {
    function itm_ticket_comment_detect_upload_mime_type(string $tmpName): string
    {
        if ($tmpName === '' || !is_file($tmpName)) {
            return '';
        }
        if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $tmpName);
                @finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }
        $imageInfo = @getimagesize($tmpName);
        if (is_array($imageInfo) && isset($imageInfo['mime']) && $imageInfo['mime'] !== '') {
            return strtolower((string)$imageInfo['mime']);
        }
        return '';
    }
}

if (!function_exists('itm_ticket_comment_save_uploaded_photos')) {
    /**
     * @param array<string,mixed>|null $filesField $_FILES['comment_photo'] shape
     * @return array<int,string>
     */
    function itm_ticket_comment_save_uploaded_photos($ticketId, $commentId, $filesField): array
    {
        $ticketId = (int)$ticketId;
        $commentId = (int)$commentId;
        if ($ticketId <= 0 || $commentId <= 0 || !is_array($filesField)) {
            return [];
        }

        $names = $filesField['name'] ?? null;
        $tmpNames = $filesField['tmp_name'] ?? null;
        $errors = $filesField['error'] ?? null;
        if (!is_array($names) || !is_array($tmpNames) || !is_array($errors)) {
            return [];
        }

        $saved = [];
        $uploadPath = TICKET_UPLOAD_PATH;
        if (!is_dir($uploadPath)) {
            itm_ensure_upload_directory($uploadPath, 'upload');
        }

        foreach ($names as $index => $name) {
            $errorCode = (int)($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode !== UPLOAD_ERR_OK) {
                continue;
            }
            $tmpName = (string)($tmpNames[$index] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                continue;
            }
            if (!in_array(itm_ticket_comment_detect_upload_mime_type($tmpName), ALLOWED_TYPES, true)) {
                continue;
            }
            $ext = strtolower((string)pathinfo((string)$name, PATHINFO_EXTENSION)) ?: 'jpg';
            $filename = 'comment_' . $ticketId . '_' . $commentId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            if (@move_uploaded_file($tmpName, $uploadPath . $filename)) {
                $saved[] = $filename;
            }
        }

        return $saved;
    }
}

if (!function_exists('itm_ticket_comment_render_photos_html')) {
    function itm_ticket_comment_render_photos_html(array $commentRow): string
    {
        $photos = itm_ticket_comment_parse_photos_json((string)($commentRow['photos_json'] ?? ''));
        if ($photos === []) {
            return '';
        }
        $html = '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px;">';
        foreach ($photos as $photoFilename) {
            $url = itm_ticket_comment_photo_public_url((string)$photoFilename);
            $html .= '<a class="itm-plain-link" href="' . sanitize($url) . '" target="_blank">'
                . '<img src="' . sanitize($url) . '" style="width:96px;height:96px;object-fit:cover;border-radius:6px;" alt="">'
                . '</a>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('itm_ticket_comment_fetch_row')) {
    function itm_ticket_comment_fetch_row($conn, $companyId, $ticketId, $commentId, $viewerEmployeeId, $viewerIsSupportAgent)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $commentId = (int)$commentId;
        $sql = 'SELECT tc.*, e.first_name, e.last_name, e.username
                FROM ticket_comments tc
                LEFT JOIN employees e ON e.id = tc.employee_id
                WHERE tc.company_id = ? AND tc.ticket_id = ? AND tc.id = ? AND tc.deleted_at IS NULL AND tc.active = 1';
        if (!$viewerIsSupportAgent) {
            $sql .= ' AND tc.is_internal = 0';
        }
        $sql .= ' LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $ticketId, $commentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }
}

if (!function_exists('itm_ticket_comment_run_post_create_hooks')) {
    function itm_ticket_comment_run_post_create_hooks($conn, $companyId, $ticketId, $commentId, $employeeId, $body, $isInternal, $logActivity = true, $photoCount = 0)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $commentId = (int)$commentId;
        $employeeId = (int)$employeeId;
        $body = trim((string)$body);
        $isInternal = (int)((bool)$isInternal);
        $photoCount = (int)$photoCount;
        if ($companyId <= 0 || $ticketId <= 0 || $commentId <= 0) {
            return;
        }
        if ($body === '' && $photoCount <= 0) {
            return;
        }

        if ($logActivity && function_exists('itm_ticket_activity_log')) {
            itm_ticket_activity_log($conn, $companyId, $ticketId, $employeeId, 'comment_added', [
                'comment_id' => $commentId,
                'is_internal' => $isInternal,
                'body_preview' => itm_ticket_comment_body_preview($body),
                'photo_count' => $photoCount,
                'source' => 'ticket_comments',
            ]);
        }

        if ($body !== '' && function_exists('itm_notify_ticket_comment_mentions')) {
            itm_notify_ticket_comment_mentions($conn, $companyId, $ticketId, $commentId, $body, $employeeId);
        }

        if (function_exists('itm_webhook_queue_emit_ticket_comment_created')) {
            require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
            itm_webhook_queue_emit_ticket_comment_created($conn, $companyId, [
                'id' => $commentId,
                'ticket_id' => $ticketId,
                'employee_id' => $employeeId,
                'is_internal' => $isInternal,
                'body' => $body,
                'photo_count' => $photoCount,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (function_exists('itm_ticket_sla_stamp_first_response')) {
            itm_ticket_sla_stamp_first_response($conn, $ticketId, $companyId);
        }
    }
}

if (!function_exists('itm_ticket_comment_create')) {
    function itm_ticket_comment_create($conn, $companyId, $ticketId, $employeeId, $body, $isInternal = 0, $photosJson = null)
    {
        return itm_ticket_comment_create_with_photos($conn, $companyId, $ticketId, $employeeId, $body, $isInternal, null, $photosJson);
    }
}

if (!function_exists('itm_ticket_comment_create_with_photos')) {
    /**
     * @param array<string,mixed>|null $filesField
     */
    function itm_ticket_comment_create_with_photos($conn, $companyId, $ticketId, $employeeId, $body, $isInternal = 0, $filesField = null, $photosJson = null)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $employeeId = (int)$employeeId;
        $body = trim((string)$body);
        $isInternal = (int)((bool)$isInternal);
        if ($companyId <= 0 || $ticketId <= 0 || $employeeId <= 0) {
            return 0;
        }

        $hasPendingUploads = is_array($filesField) && !empty($filesField['name']);
        if ($body === '' && !$hasPendingUploads && $photosJson === null) {
            return 0;
        }

        $photosJsonValue = null;
        if (is_string($photosJson) && trim($photosJson) !== '') {
            $photosJsonValue = $photosJson;
        }

        $sql = 'INSERT INTO ticket_comments (company_id, ticket_id, employee_id, body, photos_json, is_internal, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        $createdBy = (int)($_SESSION['employee_id'] ?? $employeeId);
        mysqli_stmt_bind_param($stmt, 'iiissii', $companyId, $ticketId, $employeeId, $body, $photosJsonValue, $isInternal, $createdBy);
        $ok = mysqli_stmt_execute($stmt);
        $commentId = $ok ? (int)mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($stmt);
        if (!$ok || $commentId <= 0) {
            return 0;
        }

        $photoFilenames = itm_ticket_comment_parse_photos_json((string)$photosJsonValue);
        if ($hasPendingUploads) {
            $photoFilenames = array_merge($photoFilenames, itm_ticket_comment_save_uploaded_photos($ticketId, $commentId, $filesField));
        }
        if ($photoFilenames !== []) {
            $encoded = json_encode(array_values(array_unique($photoFilenames)), JSON_UNESCAPED_SLASHES);
            $updateStmt = mysqli_prepare($conn, 'UPDATE ticket_comments SET photos_json = ? WHERE id = ? AND company_id = ? LIMIT 1');
            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, 'sii', $encoded, $commentId, $companyId);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }
        }

        itm_ticket_comment_run_post_create_hooks(
            $conn,
            $companyId,
            $ticketId,
            $commentId,
            $employeeId,
            $body,
            $isInternal,
            true,
            count($photoFilenames)
        );

        return $commentId;
    }
}

if (!function_exists('itm_ticket_comments_for_ticket')) {
    function itm_ticket_comments_for_ticket($conn, $companyId, $ticketId, $viewerEmployeeId, $viewerIsSupportAgent)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $rows = [];
        $sql = 'SELECT tc.*, e.first_name, e.last_name, e.username
                FROM ticket_comments tc
                LEFT JOIN employees e ON e.id = tc.employee_id
                WHERE tc.company_id = ? AND tc.ticket_id = ? AND tc.deleted_at IS NULL AND tc.active = 1';
        if (!$viewerIsSupportAgent) {
            $sql .= ' AND tc.is_internal = 0';
        }
        $sql .= ' ORDER BY tc.created_at ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $ticketId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}
