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

if (!function_exists('pc_contact_photo_store_upload')) {
    /**
     * @return string Stored filename or previous filename when upload skipped/failed
     */
    function pc_contact_photo_store_upload(array $file, $contactId, $companyId, $username, $userId, $existingFilename = '', $confirmReplace = false)
    {
        if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            return (string)$existingFilename;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $ext = null;
        if ($mime === 'image/png') {
            $ext = 'png';
        } elseif ($mime === 'image/jpeg') {
            $ext = 'jpg';
        }
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

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $photoFilename;
        }

        return (string)$existingFilename;
    }
}
