<?php
/**
 * Private contact photo storage under files/{company_id}/Private/{username}_{user_id}/private_contacts/.
 */

require_once ROOT_PATH . 'includes/itm_profile_photo_upload.php';

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
     * @return array{ok:bool,filename:string,error:string}
     */
    function pc_contact_photo_store_upload(array $file, $contactId, $companyId, $username, $userId, $existingFilename = '', $confirmReplace = false)
    {
        $existingFilename = (string)$existingFilename;
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'filename' => $existingFilename, 'error' => ''];
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'filename' => $existingFilename, 'error' => 'Photo upload failed.'];
        }

        $ext = itm_profile_photo_allowed_extension(
            (string)($file['tmp_name'] ?? ''),
            (string)($file['name'] ?? ''),
            (string)($file['type'] ?? '')
        );
        if ($ext === null) {
            return ['ok' => false, 'filename' => $existingFilename, 'error' => 'Only PNG and JPG profile photos are allowed.'];
        }

        $contactId = (int)$contactId;
        $companyId = (int)$companyId;
        $userId = (int)$userId;
        $username = (string)$username;
        if ($contactId <= 0 || $companyId <= 0 || $userId <= 0 || $username === '') {
            return ['ok' => false, 'filename' => $existingFilename, 'error' => 'Could not prepare private contact photo folder.'];
        }

        $photoFilename = $contactId . '_photo.' . $ext;
        $dir = ROOT_PATH . 'files/' . $companyId . '/Private/' . $username . '_' . $userId . '/private_contacts';
        $targetPath = $dir . '/' . $photoFilename;

        if ($existingFilename !== '' && is_file($targetPath) && !$confirmReplace) {
            return ['ok' => true, 'filename' => $existingFilename, 'error' => ''];
        }

        if (!itm_ensure_files_storage_directory($dir)) {
            return ['ok' => false, 'filename' => $existingFilename, 'error' => 'Could not prepare private contact photo folder.'];
        }

        foreach (['png', 'jpg'] as $oldExt) {
            $oldPath = $dir . '/' . $contactId . '_photo.' . $oldExt;
            if (is_file($oldPath) && $oldPath !== $targetPath) {
                @unlink($oldPath);
            }
        }

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['ok' => false, 'filename' => $existingFilename, 'error' => 'Could not save profile photo.'];
        }

        return ['ok' => true, 'filename' => $photoFilename, 'error' => ''];
    }
}
