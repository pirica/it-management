<?php
/**
 * Private contact photo storage under files/{company_id}/Private/{username}_{user_id}/private_contacts/.
 */

if (!function_exists('pc_contact_photo_serve_url')) {
    function pc_contact_photo_serve_url(array $contact)
    {
        $filename = trim((string)($contact['photo'] ?? ''));
        $username = trim((string)($_SESSION['username'] ?? ''));
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($filename === '' || $username === '' || $userId <= 0) {
            return '';
        }

        return itm_files_serve_url('Private/' . $username . '_' . $userId . '/private_contacts/' . basename($filename));
    }
}

if (!function_exists('pc_contact_photo_resolve_extension')) {
    /**
     * Resolve png/jpg from finfo MIME and original filename (Windows may report image/pjpeg).
     *
     * @return string|null png|jpg or null when not an allowed profile photo
     */
    function pc_contact_photo_resolve_extension($tmpPath, $originalName)
    {
        $mime = '';
        if (is_string($tmpPath) && $tmpPath !== '' && is_file($tmpPath)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = strtolower((string)$finfo->file($tmpPath));
        }

        if ($mime === 'image/png') {
            return 'png';
        }
        if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true)) {
            return 'jpg';
        }

        // Why: Some hosts return application/octet-stream for valid JPEG uploads.
        $lowerName = strtolower((string)$originalName);
        if (preg_match('/\.png$/', $lowerName)) {
            return 'png';
        }
        if (preg_match('/\.jpe?g$/', $lowerName)) {
            return 'jpg';
        }

        return null;
    }
}

if (!function_exists('pc_contact_photo_store_upload')) {
    /**
     * @return string Stored filename or previous filename when upload skipped/failed
     */
    function pc_contact_photo_store_upload(array $file, $contactId, $companyId, $username, $userId, $existingFilename = '', $confirmReplace = false)
    {
        if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            return (string)$existingFilename;
        }

        $ext = pc_contact_photo_resolve_extension(
            (string)($file['tmp_name'] ?? ''),
            (string)($file['name'] ?? '')
        );
        if ($ext === null) {
            return (string)$existingFilename;
        }

        $contactId = (int)$contactId;
        $companyId = (int)$companyId;
        $userId = (int)$userId;
        $username = (string)$username;
        if ($contactId <= 0 || $companyId <= 0 || $userId <= 0 || $username === '') {
            return (string)$existingFilename;
        }

        $photoFilename = $contactId . '_photo.' . $ext;
        $dir = ROOT_PATH . 'files/' . $companyId . '/Private/' . $username . '_' . $userId . '/private_contacts';
        $targetPath = $dir . '/' . $photoFilename;

        if ($existingFilename !== '' && is_file($targetPath) && !$confirmReplace) {
            return (string)$existingFilename;
        }

        if (!itm_ensure_files_storage_directory($dir)) {
            return (string)$existingFilename;
        }

        foreach (['png', 'jpg'] as $oldExt) {
            $oldPath = $dir . '/' . $contactId . '_photo.' . $oldExt;
            if (is_file($oldPath) && $oldPath !== $targetPath) {
                @unlink($oldPath);
            }
        }

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $photoFilename;
        }

        return (string)$existingFilename;
    }
}
