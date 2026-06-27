<?php
/**
 * Employee profile photo storage helpers.
 *
 * Photos live under files/{company_id}/Private/{username}_{employee_id}/profile/
 * as {username}_{employee_id}.png or {username}_{employee_id}.jpg only.
 */

if (!function_exists('emp_profile_photo_safe_username')) {
function emp_profile_photo_safe_username($username) {
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string)$username);
}
}

if (!function_exists('emp_profile_photo_folder_suffix')) {
function emp_profile_photo_folder_suffix(array $employee) {
    return (int)($employee['id'] ?? 0);
}
}

if (!function_exists('emp_profile_photo_can_store')) {
function emp_profile_photo_can_store(array $employee) {
    $username = trim((string)($employee['username'] ?? ''));
    return $username !== '' && emp_profile_photo_folder_suffix($employee) > 0;
}
}

if (!function_exists('emp_profile_photo_filename')) {
function emp_profile_photo_filename($username, $employee_id, $ext) {
    $safe = emp_profile_photo_safe_username($username);
    $normalizedExt = strtolower((string)$ext) === 'jpg' ? 'jpg' : 'png';
    return $safe . '_' . (int)$employee_id . '.' . $normalizedExt;
}
}

if (!function_exists('emp_profile_photo_relative_dir')) {
function emp_profile_photo_relative_dir($username, $employee_id) {
    $safe = emp_profile_photo_safe_username($username);
    return 'Private/' . $safe . '_' . (int)$employee_id . '/profile';
}
}

if (!function_exists('emp_profile_photo_legacy_relative_dir')) {
function emp_profile_photo_legacy_relative_dir($username, $user_id) {
    $safe = emp_profile_photo_safe_username($username);
    return 'Private/' . $safe . '_' . (int)$user_id . '/profile';
}
}

if (!function_exists('emp_profile_photo_serve_path')) {
function emp_profile_photo_serve_path(array $employee) {
    $filename = trim((string)($employee['photo'] ?? ''));
    if ($filename === '') {
        return '';
    }

    $basename = basename($filename);
    $employeeId = emp_profile_photo_folder_suffix($employee);
    $username = trim((string)($employee['username'] ?? ''));

    if ($username !== '' && $employeeId > 0) {
        return emp_profile_photo_relative_dir($username, $employeeId) . '/' . $basename;
    }

    // Why: Legacy rows may still point at Private/{username}_{linked_user_id}/profile/.
    $legacyUserId = (int)($employee['user_id'] ?? 0);
    if ($username !== '' && $legacyUserId > 0) {
        return emp_profile_photo_legacy_relative_dir($username, $legacyUserId) . '/' . $basename;
    }

    return '';
}
}

if (!function_exists('emp_profile_photo_url')) {
function emp_profile_photo_url(array $employee) {
    $relative = emp_profile_photo_serve_path($employee);
    if ($relative === '') {
        return '';
    }
    return itm_files_serve_url($relative);
}
}

if (!function_exists('emp_profile_photo_absolute_dir')) {
function emp_profile_photo_absolute_dir($company_id, $username, $employee_id) {
    return ROOT_PATH . 'files/' . (int)$company_id . '/' . emp_profile_photo_relative_dir($username, $employee_id);
}
}

if (!function_exists('emp_profile_photo_store_upload')) {
function emp_profile_photo_store_upload($company_id, array $employee, array $uploadFile) {
    $username = trim((string)($employee['username'] ?? ''));
    if ($username === '') {
        return ['ok' => false, 'error' => 'Set a username on the employee before uploading a profile photo.'];
    }
    if (emp_profile_photo_folder_suffix($employee) <= 0) {
        return ['ok' => false, 'error' => 'Employee id is required before uploading a profile photo.'];
    }
    if (!isset($uploadFile['error']) || (int)$uploadFile['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Photo upload failed.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($uploadFile['tmp_name']);
    $ext = '';
    if ($mime === 'image/png') {
        $ext = 'png';
    } elseif ($mime === 'image/jpeg') {
        $ext = 'jpg';
    } else {
        return ['ok' => false, 'error' => 'Only PNG and JPG profile photos are allowed.'];
    }

    $employeeId = emp_profile_photo_folder_suffix($employee);
    $filename = emp_profile_photo_filename($employee['username'], $employeeId, $ext);
    $dir = emp_profile_photo_absolute_dir($company_id, $employee['username'], $employeeId);
    if (!itm_ensure_files_storage_directory($dir)) {
        return ['ok' => false, 'error' => 'Could not prepare profile photo folder.'];
    }

    $target = $dir . '/' . $filename;
    foreach (['png', 'jpg'] as $oldExt) {
        $oldFile = $dir . '/' . emp_profile_photo_filename($employee['username'], $employeeId, $oldExt);
        if (is_file($oldFile) && $oldFile !== $target) {
            @unlink($oldFile);
        }
    }

    if (!move_uploaded_file($uploadFile['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'Could not save profile photo.'];
    }

    return ['ok' => true, 'filename' => $filename];
}
}

if (!function_exists('emp_format_birthday_day_only')) {
function emp_format_birthday_day_only($birthday) {
    if (!$birthday || $birthday === '0000-00-00') {
        return '—';
    }
    $ts = strtotime((string)$birthday);
    if ($ts === false) {
        return '—';
    }
    return date('j', $ts);
}
}

if (!function_exists('emp_format_birthday_display')) {
function emp_format_birthday_display($birthday, $hide_year) {
    if (!$birthday || $birthday === '0000-00-00') {
        return '—';
    }
    $ts = strtotime((string)$birthday);
    if ($ts === false) {
        return '—';
    }
    if ((int)$hide_year === 1) {
        return date('d/m', $ts);
    }
    return date('d/m/Y', $ts);
}
}
