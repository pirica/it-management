<?php
/**
 * Private contact photo storage under files/{company_id}/Private/{username}_{user_id}/private_contacts/.
 */

if (!function_exists('pc_contact_photo_serve_url')) {
    function pc_contact_photo_serve_url(array $contact)
    {
        $filename = trim((string)($contact['photo'] ?? ''));
        $username = trim((string)($_SESSION['username'] ?? ''));
        $employeeId = (int)($_SESSION['employee_id'] ?? 0);
        if ($filename === '' || $username === '' || $employeeId <= 0) {
            return '';
        }

        return itm_files_serve_url('Private/' . $username . '_' . $employeeId . '/private_contacts/' . basename($filename));
    }
}

if (!function_exists('pc_contact_photo_resolve_png_extension')) {
    /**
     * @return string|null png or null when not a PNG profile photo
     */
    function pc_contact_photo_resolve_png_extension($tmpPath, $originalName, $clientMime = '')
    {
        if (is_string($tmpPath) && $tmpPath !== '' && is_file($tmpPath)) {
            $imageInfo = @getimagesize($tmpPath);
            if (is_array($imageInfo) && isset($imageInfo[2])) {
                return $imageInfo[2] === IMAGETYPE_PNG ? 'png' : null;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = strtolower((string)$finfo->file($tmpPath));
            if ($mime === 'image/png') {
                return 'png';
            }
        }

        if (strtolower((string)$clientMime) === 'image/png') {
            return 'png';
        }

        $lowerName = strtolower((string)$originalName);
        if (preg_match('/\.png$/', $lowerName)) {
            return 'png';
        }

        return null;
    }
}

if (!function_exists('pc_contact_photo_store_upload')) {
    /**
     * @return array{ok:bool,filename:string,error:string}
     */
    function pc_contact_photo_store_upload(array $file, $contactId, $companyId, $username, $employeeId, $existingFilename = '', $confirmReplace = false)
    {
        $existingFilename = (string)$existingFilename;
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'filename' => $existingFilename, 'error' => ''];
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'filename' => $existingFilename, 'error' => 'Photo upload failed.'];
        }

        $ext = pc_contact_photo_resolve_png_extension(
            (string)($file['tmp_name'] ?? ''),
            (string)($file['name'] ?? ''),
            (string)($file['type'] ?? '')
        );
        if ($ext === null) {
            return ['ok' => false, 'filename' => $existingFilename, 'error' => 'Only PNG profile photos are allowed.'];
        }

        $contactId = (int)$contactId;
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        $username = (string)$username;
        if ($contactId <= 0 || $companyId <= 0 || $employeeId <= 0 || $username === '') {
            return ['ok' => false, 'filename' => $existingFilename, 'error' => 'Could not prepare private contact photo folder.'];
        }

        $photoFilename = $contactId . '_photo.png';
        $dir = ROOT_PATH . 'files/' . $companyId . '/Private/' . $username . '_' . $employeeId . '/private_contacts';
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
