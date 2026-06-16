<?php
/**
 * Employee profile photo storage helpers.
 *
 * Photos live under files/{company_id}/Private/{username}_{user_id}/profile/
 * as {username}_{user_id}.png or {username}_{user_id}.jpg only.
 */

if (!function_exists('emp_profile_photo_safe_username')) {
function emp_profile_photo_safe_username($username) {
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string)$username);
}
}

if (!function_exists('emp_profile_photo_can_store')) {
function emp_profile_photo_can_store(array $employee) {
    $username = trim((string)($employee['username'] ?? ''));
    $userId = (int)($employee['user_id'] ?? 0);
    return $username !== '' && $userId > 0;
}
}

if (!function_exists('emp_profile_photo_filename')) {
function emp_profile_photo_filename($username, $user_id, $ext) {
    $safe = emp_profile_photo_safe_username($username);
    $normalizedExt = strtolower((string)$ext) === 'jpg' ? 'jpg' : 'png';
    return $safe . '_' . (int)$user_id . '.' . $normalizedExt;
}
}

if (!function_exists('emp_profile_photo_relative_dir')) {
function emp_profile_photo_relative_dir($username, $user_id) {
    $safe = emp_profile_photo_safe_username($username);
    return 'Private/' . $safe . '_' . (int)$user_id . '/profile';
}
}

if (!function_exists('emp_profile_photo_serve_path')) {
function emp_profile_photo_serve_path(array $employee) {
    $filename = trim((string)($employee['photo'] ?? ''));
    if ($filename === '' || !emp_profile_photo_can_store($employee)) {
        return '';
    }
    return emp_profile_photo_relative_dir($employee['username'], $employee['user_id']) . '/' . basename($filename);
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
function emp_profile_photo_absolute_dir($company_id, $username, $user_id) {
    return ROOT_PATH . 'files/' . (int)$company_id . '/' . emp_profile_photo_relative_dir($username, $user_id);
}
}

if (!function_exists('emp_profile_photo_store_upload')) {
function emp_profile_photo_store_upload($company_id, array $employee, array $uploadFile) {
    if (!emp_profile_photo_can_store($employee)) {
        return ['ok' => false, 'error' => 'Link a username and user account before uploading a profile photo.'];
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

    $filename = emp_profile_photo_filename($employee['username'], $employee['user_id'], $ext);
    $dir = emp_profile_photo_absolute_dir($company_id, $employee['username'], $employee['user_id']);
    if (!itm_ensure_files_storage_directory($dir)) {
        return ['ok' => false, 'error' => 'Could not prepare profile photo folder.'];
    }

    $target = $dir . '/' . $filename;
    foreach (['png', 'jpg'] as $oldExt) {
        $oldFile = $dir . '/' . emp_profile_photo_filename($employee['username'], $employee['user_id'], $oldExt);
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
        return date('j M', $ts);
    }
    return date('j M Y', $ts);
}
}
