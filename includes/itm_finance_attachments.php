<?php

/**
 * Finance module file attachments (multi-upload, tenant-scoped storage under finance/).
 */

if (!function_exists('itm_finance_attachment_supported_parent_tables')) {
    function itm_finance_attachment_supported_parent_tables(): array
    {
        return ['invoices', 'bills', 'expenses', 'customers', 'bank_accounts'];
    }
}

if (!function_exists('itm_finance_attachments_enabled_for_table')) {
    function itm_finance_attachments_enabled_for_table(string $parentTable): bool
    {
        return in_array($parentTable, itm_finance_attachment_supported_parent_tables(), true);
    }
}

if (!function_exists('itm_finance_attachment_max_bytes')) {
    function itm_finance_attachment_max_bytes(): int
    {
        return 5 * 1024 * 1024;
    }
}

if (!function_exists('itm_finance_attachment_allowed_extensions')) {
    function itm_finance_attachment_allowed_extensions(): array
    {
        return ['pdf', 'zip', 'jpg', 'jpeg', 'png', 'bmp', 'xlsx', 'docx', 'xls', 'doc', 'txt'];
    }
}

if (!function_exists('itm_finance_attachment_accept_attribute')) {
    function itm_finance_attachment_accept_attribute(): string
    {
        $parts = [];
        foreach (itm_finance_attachment_allowed_extensions() as $ext) {
            $parts[] = '.' . $ext;
        }

        return implode(',', array_unique($parts));
    }
}

if (!function_exists('itm_finance_attachment_storage_root')) {
    function itm_finance_attachment_storage_root(): string
    {
        if (defined('FINANCE_UPLOAD_PATH')) {
            return FINANCE_UPLOAD_PATH;
        }

        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ROOT_PATH . 'finance'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('itm_finance_attachment_sanitize_folder_segment')) {
    function itm_finance_attachment_sanitize_folder_segment(string $segment): string
    {
        $segment = trim($segment);
        if ($segment === '') {
            return 'unnamed';
        }
        $segment = preg_replace('/[\\\\\/:*?"<>|]+/', '_', $segment);
        $segment = preg_replace('/\s+/', '_', $segment);

        return substr($segment, 0, 120);
    }
}

if (!function_exists('itm_finance_attachment_folder_key_for_row')) {
    function itm_finance_attachment_folder_key_for_row(string $parentTable, array $row): string
    {
        $id = (int) ($row['id'] ?? 0);
        switch ($parentTable) {
            case 'invoices':
            case 'bills':
                $key = trim((string) ($row['document_number'] ?? ''));
                break;
            case 'expenses':
                $key = trim((string) ($row['invoice_number'] ?? ''));
                if ($key === '') {
                    $key = 'expense-' . $id;
                }
                break;
            case 'customers':
                $key = trim((string) ($row['customer_code'] ?? ''));
                if ($key === '') {
                    $key = 'customer-' . $id;
                }
                break;
            case 'bank_accounts':
                $key = trim((string) ($row['account_number'] ?? ''));
                if ($key === '') {
                    $key = 'bank-' . $id;
                }
                break;
            default:
                $key = 'record-' . $id;
        }
        if ($key === '' && $id > 0) {
            $key = 'record-' . $id;
        }

        return itm_finance_attachment_sanitize_folder_segment($key);
    }
}

if (!function_exists('itm_finance_attachment_directory_for_key')) {
    function itm_finance_attachment_directory_for_key(int $companyId, string $parentTable, string $folderKey): string
    {
        $root = itm_finance_attachment_storage_root();
        $parts = [
            $root,
            (string) (int) $companyId,
            $parentTable,
            itm_finance_attachment_sanitize_folder_segment($folderKey),
        ];
        $path = '';
        foreach ($parts as $part) {
            $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim((string) $part, DIRECTORY_SEPARATOR);
        }

        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('itm_finance_attachment_ensure_directory')) {
    function itm_finance_attachment_ensure_directory(int $companyId, string $parentTable, string $folderKey): ?string
    {
        $dir = itm_finance_attachment_directory_for_key($companyId, $parentTable, $folderKey);
        if (!itm_ensure_upload_directory_chain($dir, 'deny_all', itm_finance_attachment_storage_root())) {
            return null;
        }

        return $dir;
    }
}

if (!function_exists('itm_finance_attachment_detect_upload_mime')) {
    function itm_finance_attachment_detect_upload_mime(array $file): string
    {
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return '';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }
        $mime = (string) finfo_file($finfo, $tmp);
        finfo_close($finfo);

        return $mime;
    }
}

if (!function_exists('itm_finance_attachment_validate_file')) {
    function itm_finance_attachment_validate_file(array $file, ?string &$error): bool
    {
        $error = '';
        $fileError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($fileError === UPLOAD_ERR_NO_FILE) {
            return false;
        }
        if ($fileError !== UPLOAD_ERR_OK) {
            $error = 'One of the attachment uploads failed.';

            return false;
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > itm_finance_attachment_max_bytes()) {
            $error = 'Each attachment must be 5 MB or smaller.';

            return false;
        }
        $original = (string) ($file['name'] ?? '');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $allowed = itm_finance_attachment_allowed_extensions();
        $checkExt = ($ext === 'jpeg') ? 'jpg' : $ext;
        if (!in_array($checkExt, $allowed, true)) {
            $error = 'Unsupported attachment type. Allowed: pdf, zip, jpg, png, bmp, xlsx, docx, xls, doc, txt.';

            return false;
        }
        $mime = itm_finance_attachment_detect_upload_mime($file);
        if ($mime !== '' && stripos($mime, 'php') !== false) {
            $error = 'Unsupported attachment type.';

            return false;
        }

        return true;
    }
}

if (!function_exists('itm_finance_attachment_build_stored_filename')) {
    function itm_finance_attachment_build_stored_filename(string $originalFilename): string
    {
        $base = basename($originalFilename);
        $base = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $base);
        if ($base === '' || $base === '.' || $base === '..') {
            $base = 'file';
        }

        return uniqid('fa_', true) . '_' . substr($base, 0, 180);
    }
}

if (!function_exists('itm_finance_attachment_load_for_parent')) {
    function itm_finance_attachment_load_for_parent(mysqli $conn, int $companyId, string $parentTable, int $parentId): array
    {
        if (!itm_finance_attachments_enabled_for_table($parentTable) || $parentId <= 0) {
            return [];
        }
        $sql = 'SELECT * FROM `finance_attachments` WHERE `company_id` = ? AND `parent_table` = ? AND `parent_id` = ? AND `deleted_at` IS NULL ORDER BY `id` ASC';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'isi', $companyId, $parentTable, $parentId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('itm_finance_attachment_soft_delete_ids_from_post')) {
    function itm_finance_attachment_soft_delete_ids_from_post(mysqli $conn, int $companyId, string $parentTable, int $parentId, int $employeeId): array
    {
        if (!itm_finance_attachments_enabled_for_table($parentTable) || $parentId <= 0) {
            return ['ok' => true, 'error' => ''];
        }
        $deleteIds = $_POST['finance_attachment_delete'] ?? [];
        if (!is_array($deleteIds) || empty($deleteIds)) {
            return ['ok' => true, 'error' => ''];
        }
        foreach ($deleteIds as $rawId) {
            $attachmentId = (int) $rawId;
            if ($attachmentId <= 0) {
                continue;
            }
            $selectSql = 'SELECT * FROM `finance_attachments` WHERE `id` = ? AND `company_id` = ? AND `parent_table` = ? AND `parent_id` = ? AND `deleted_at` IS NULL LIMIT 1';
            $stmt = mysqli_prepare($conn, $selectSql);
            if (!$stmt) {
                return ['ok' => false, 'error' => 'Could not remove attachment.'];
            }
            mysqli_stmt_bind_param($stmt, 'iisi', $attachmentId, $companyId, $parentTable, $parentId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if (!$row) {
                continue;
            }
            $updateSql = 'UPDATE `finance_attachments` SET `active` = 0, `deleted_by` = ?, `deleted_at` = NOW() WHERE `id` = ? AND `company_id` = ? LIMIT 1';
            $ustmt = mysqli_prepare($conn, $updateSql);
            if (!$ustmt) {
                return ['ok' => false, 'error' => 'Could not remove attachment.'];
            }
            mysqli_stmt_bind_param($ustmt, 'iii', $employeeId, $attachmentId, $companyId);
            if (!mysqli_stmt_execute($ustmt)) {
                mysqli_stmt_close($ustmt);

                return ['ok' => false, 'error' => 'Could not remove attachment.'];
            }
            mysqli_stmt_close($ustmt);
            $dir = itm_finance_attachment_directory_for_key($companyId, $parentTable, (string) ($row['storage_key'] ?? ''));
            $path = $dir . (string) ($row['stored_filename'] ?? '');
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return ['ok' => true, 'error' => ''];
    }
}

if (!function_exists('itm_finance_attachment_relocate_storage_if_key_changed')) {
    function itm_finance_attachment_relocate_storage_if_key_changed(mysqli $conn, int $companyId, string $parentTable, int $parentId, string $newFolderKey): void
    {
        $attachments = itm_finance_attachment_load_for_parent($conn, $companyId, $parentTable, $parentId);
        if (empty($attachments)) {
            return;
        }
        $newKey = itm_finance_attachment_sanitize_folder_segment($newFolderKey);
        $oldKey = (string) ($attachments[0]['storage_key'] ?? '');
        if ($oldKey === '' || $oldKey === $newKey) {
            return;
        }
        $oldDir = itm_finance_attachment_directory_for_key($companyId, $parentTable, $oldKey);
        $newDir = itm_finance_attachment_ensure_directory($companyId, $parentTable, $newKey);
        if ($newDir === null) {
            return;
        }
        foreach ($attachments as $att) {
            $stored = (string) ($att['stored_filename'] ?? '');
            if ($stored === '') {
                continue;
            }
            $from = $oldDir . $stored;
            $to = $newDir . $stored;
            if (is_file($from) && !is_file($to)) {
                @rename($from, $to);
            }
        }
        $updateSql = 'UPDATE `finance_attachments` SET `storage_key` = ? WHERE `company_id` = ? AND `parent_table` = ? AND `parent_id` = ? AND `deleted_at` IS NULL';
        $stmt = mysqli_prepare($conn, $updateSql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sisi', $newKey, $companyId, $parentTable, $parentId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

if (!function_exists('itm_finance_attachment_process_uploads_from_post')) {
    function itm_finance_attachment_process_uploads_from_post(
        mysqli $conn,
        int $companyId,
        string $parentTable,
        int $parentId,
        array $parentRow,
        int $employeeId
    ): array {
        if (!itm_finance_attachments_enabled_for_table($parentTable) || $parentId <= 0) {
            return ['ok' => true, 'error' => ''];
        }

        $folderKey = itm_finance_attachment_folder_key_for_row($parentTable, $parentRow);
        itm_finance_attachment_relocate_storage_if_key_changed($conn, $companyId, $parentTable, $parentId, $folderKey);

        $deleteResult = itm_finance_attachment_soft_delete_ids_from_post($conn, $companyId, $parentTable, $parentId, $employeeId);
        if (!$deleteResult['ok']) {
            return $deleteResult;
        }

        if (!isset($_FILES['finance_attachments']) || !is_array($_FILES['finance_attachments']['name'] ?? null)) {
            return ['ok' => true, 'error' => ''];
        }

        $names = $_FILES['finance_attachments']['name'];
        if (!is_array($names)) {
            $names = [$names];
        }
        $count = count($names);
        if ($count === 0) {
            return ['ok' => true, 'error' => ''];
        }

        $dir = itm_finance_attachment_ensure_directory($companyId, $parentTable, $folderKey);
        if ($dir === null) {
            return ['ok' => false, 'error' => 'Could not prepare attachment storage.'];
        }

        $uploadedPaths = [];
        for ($i = 0; $i < $count; $i++) {
            $file = [
                'name' => $_FILES['finance_attachments']['name'][$i] ?? '',
                'type' => $_FILES['finance_attachments']['type'][$i] ?? '',
                'tmp_name' => $_FILES['finance_attachments']['tmp_name'][$i] ?? '',
                'error' => $_FILES['finance_attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $_FILES['finance_attachments']['size'][$i] ?? 0,
            ];
            $fileError = '';
            if (!itm_finance_attachment_validate_file($file, $fileError)) {
                if ($fileError === '' || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                foreach ($uploadedPaths as $uploadedPath) {
                    if (is_file($uploadedPath)) {
                        @unlink($uploadedPath);
                    }
                }

                return ['ok' => false, 'error' => $fileError];
            }

            $storedFilename = itm_finance_attachment_build_stored_filename((string) $file['name']);
            $dest = $dir . $storedFilename;
            if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
                foreach ($uploadedPaths as $uploadedPath) {
                    if (is_file($uploadedPath)) {
                        @unlink($uploadedPath);
                    }
                }

                return ['ok' => false, 'error' => 'Unable to save one of the attachments.'];
            }
            $uploadedPaths[] = $dest;

            $mime = itm_finance_attachment_detect_upload_mime($file);
            $insertSql = 'INSERT INTO `finance_attachments` (`company_id`, `parent_table`, `parent_id`, `storage_key`, `stored_filename`, `original_filename`, `file_size`, `mime_type`, `active`, `created_by`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)';
            $stmt = mysqli_prepare($conn, $insertSql);
            if (!$stmt) {
                @unlink($dest);

                return ['ok' => false, 'error' => 'Could not save attachment metadata.'];
            }
            $original = (string) $file['name'];
            $size = (int) $file['size'];
            mysqli_stmt_bind_param(
                $stmt,
                'isisssisi',
                $companyId,
                $parentTable,
                $parentId,
                $folderKey,
                $storedFilename,
                $original,
                $size,
                $mime,
                $employeeId
            );
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                @unlink($dest);
                foreach ($uploadedPaths as $uploadedPath) {
                    if ($uploadedPath !== $dest && is_file($uploadedPath)) {
                        @unlink($uploadedPath);
                    }
                }

                return ['ok' => false, 'error' => 'Could not save attachment metadata.'];
            }
            mysqli_stmt_close($stmt);
        }

        return ['ok' => true, 'error' => ''];
    }
}

if (!function_exists('itm_finance_attachment_after_parent_save')) {
    function itm_finance_attachment_after_parent_save(
        mysqli $conn,
        int $companyId,
        string $parentTable,
        int $parentId
    ): array {
        if (!itm_finance_attachments_enabled_for_table($parentTable) || $parentId <= 0) {
            return ['ok' => true, 'error' => ''];
        }
        $sql = 'SELECT * FROM ' . cr_escape_identifier($parentTable) . ' WHERE `id` = ? AND `company_id` = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'error' => 'Parent record not found for attachments.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $parentId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            return ['ok' => false, 'error' => 'Parent record not found for attachments.'];
        }

        return itm_finance_attachment_process_uploads_from_post(
            $conn,
            $companyId,
            $parentTable,
            $parentId,
            $row,
            (int) ($_SESSION['employee_id'] ?? 0)
        );
    }
}

if (!function_exists('itm_finance_attachment_download_entry')) {
    function itm_finance_attachment_download_entry(mysqli $conn, string $expectedParentTable): void
    {
        $attachmentId = (int) ($_GET['id'] ?? 0);
        if ($attachmentId <= 0) {
            http_response_code(404);
            exit;
        }
        $companyId = (int) ($_SESSION['company_id'] ?? 0);
        if ($companyId <= 0) {
            http_response_code(403);
            exit;
        }
        itm_require_crud_role_module_permission($conn, 'view', $expectedParentTable);

        $sql = 'SELECT * FROM `finance_attachments` WHERE `id` = ? AND `company_id` = ? AND `parent_table` = ? AND `deleted_at` IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            http_response_code(500);
            exit;
        }
        mysqli_stmt_bind_param($stmt, 'iis', $attachmentId, $companyId, $expectedParentTable);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            http_response_code(404);
            exit;
        }

        $dir = itm_finance_attachment_directory_for_key($companyId, $expectedParentTable, (string) ($row['storage_key'] ?? ''));
        $path = $dir . (string) ($row['stored_filename'] ?? '');
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        $original = (string) ($row['original_filename'] ?? 'attachment');
        $mime = (string) ($row['mime_type'] ?? '');
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $original) . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }
}

if (!function_exists('itm_finance_render_attachments_editor')) {
    function itm_finance_render_attachments_editor(array $attachments): void
    {
        $accept = itm_finance_attachment_accept_attribute();
        ?>
        <div class="form-group">
            <label>Attachments</label>
            <div id="financeAttachmentUploadTarget" class="itm-photo-upload-target" role="button" tabindex="0" aria-label="Upload finance attachments">
                <p class="itm-dropzone-hint">Drag and drop files here, or click to choose files. You can attach multiple files (pdf, zip, jpg, png, bmp, xlsx, docx, xls, doc, txt — max 5 MB each).</p>
                <input type="file" name="finance_attachments[]" id="financeAttachmentInput" accept="<?php echo sanitize($accept); ?>" multiple>
            </div>
            <?php if (!empty($attachments)): ?>
                <ul class="form-hint" style="margin-top:12px;list-style:none;padding:0;">
                    <?php foreach ($attachments as $att): ?>
                        <li style="margin-bottom:6px;">
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="finance_attachment_delete[]" value="<?php echo (int) ($att['id'] ?? 0); ?>">
                                <span><?php echo sanitize((string) ($att['original_filename'] ?? '')); ?> <span class="itm-check-indicator" aria-hidden="true">🗑️</span></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="form-hint">Check files above to remove them when you save.</div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (!function_exists('itm_finance_render_attachments_view')) {
    function itm_finance_render_attachments_view(array $attachments): void
    {
        if (empty($attachments)) {
            return;
        }
        ?>
        <h3 title="Attachments">📎</h3>
        <ul>
            <?php foreach ($attachments as $att): ?>
                <li>
                    <a href="attachment.php?id=<?php echo (int) ($att['id'] ?? 0); ?>" title="Download attachment"><?php echo sanitize((string) ($att['original_filename'] ?? 'file')); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
}

if (!function_exists('itm_finance_render_attachments_form_scripts')) {
    function itm_finance_render_attachments_form_scripts(): void
    {
        ?>
        <script src="../../js/itm-upload-helper.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof itmUploadHelper !== 'undefined') {
                itmUploadHelper.setupById('financeAttachmentUploadTarget', 'financeAttachmentInput');
            }
        });
        </script>
        <?php
    }
}
