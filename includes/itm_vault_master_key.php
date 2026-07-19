<?php
/**
 * Vault master key change helpers — shared re-encryption for password_entries.
 */

if (!function_exists('itm_vault_reencrypt_password_entries')) {
    /**
     * Re-encrypt all password_entries for one employee when the vault session key changes.
     * Caller must BEGIN a transaction and COMMIT or ROLLBACK based on the return value.
     *
     * @return array{ok:bool, message:string}
     */
    function itm_vault_reencrypt_password_entries($conn, $employeeId, $oldKeySession, $newKeySession)
    {
        if (!($conn instanceof mysqli)) {
            return ['ok' => false, 'message' => 'Database connection unavailable.'];
        }

        $employeeId = (int)$employeeId;
        if ($employeeId <= 0) {
            return ['ok' => false, 'message' => 'Invalid employee.'];
        }

        $oldKeySession = (string)$oldKeySession;
        $newKeySession = (string)$newKeySession;
        if ($oldKeySession === '' || $newKeySession === '') {
            return ['ok' => false, 'message' => 'Invalid vault key material.'];
        }

        $sel_stmt = mysqli_prepare($conn, 'SELECT id, password FROM password_entries WHERE employee_id = ?');
        if (!$sel_stmt) {
            return ['ok' => false, 'message' => 'Failed to load vault entries.'];
        }

        mysqli_stmt_bind_param($sel_stmt, 'i', $employeeId);
        if (!mysqli_stmt_execute($sel_stmt)) {
            mysqli_stmt_close($sel_stmt);
            return ['ok' => false, 'message' => 'Failed to load vault entries.'];
        }

        $res = mysqli_stmt_get_result($sel_stmt);
        $upd_stmt = mysqli_prepare($conn, 'UPDATE password_entries SET password = ? WHERE id = ? AND employee_id = ?');
        if (!$upd_stmt) {
            mysqli_stmt_close($sel_stmt);
            return ['ok' => false, 'message' => 'Failed to prepare vault update.'];
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $entryId = (int)($row['id'] ?? 0);
            $decrypted = itm_decrypt((string)($row['password'] ?? ''), $oldKeySession);
            if ($decrypted === false) {
                mysqli_stmt_close($upd_stmt);
                mysqli_stmt_close($sel_stmt);
                return ['ok' => false, 'message' => 'Failed to re-encrypt vault entries. Please try again.'];
            }

            $re_encrypted = itm_encrypt($decrypted, $newKeySession);
            mysqli_stmt_bind_param($upd_stmt, 'sii', $re_encrypted, $entryId, $employeeId);
            if (!mysqli_stmt_execute($upd_stmt)) {
                mysqli_stmt_close($upd_stmt);
                mysqli_stmt_close($sel_stmt);
                return ['ok' => false, 'message' => 'Failed to re-encrypt vault entries. Please try again.'];
            }
        }

        mysqli_stmt_close($upd_stmt);
        mysqli_stmt_close($sel_stmt);

        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('itm_vault_reencrypt_bookmark_urls')) {
    /**
     * Re-encrypt private bookmark URLs for one employee when the vault session key changes.
     *
     * @return array{ok:bool, message:string}
     */
    function itm_vault_reencrypt_bookmark_urls($conn, $employeeId, $oldKeySession, $newKeySession)
    {
        if (!($conn instanceof mysqli)) {
            return ['ok' => false, 'message' => 'Database connection unavailable.'];
        }

        $employeeId = (int)$employeeId;
        if ($employeeId <= 0) {
            return ['ok' => false, 'message' => 'Invalid employee.'];
        }

        $oldKeySession = (string)$oldKeySession;
        $newKeySession = (string)$newKeySession;
        if ($oldKeySession === '' || $newKeySession === '') {
            return ['ok' => false, 'message' => 'Invalid vault key material.'];
        }

        $sel_stmt = mysqli_prepare(
            $conn,
            'SELECT id, url FROM bookmarks WHERE employee_id = ? AND shared = 0 AND active = 1'
        );
        if (!$sel_stmt) {
            return ['ok' => false, 'message' => 'Failed to load bookmark URLs.'];
        }

        mysqli_stmt_bind_param($sel_stmt, 'i', $employeeId);
        if (!mysqli_stmt_execute($sel_stmt)) {
            mysqli_stmt_close($sel_stmt);
            return ['ok' => false, 'message' => 'Failed to load bookmark URLs.'];
        }

        $res = mysqli_stmt_get_result($sel_stmt);
        $upd_stmt = mysqli_prepare($conn, 'UPDATE bookmarks SET url = ? WHERE id = ? AND employee_id = ?');
        if (!$upd_stmt) {
            mysqli_stmt_close($sel_stmt);
            return ['ok' => false, 'message' => 'Failed to prepare bookmark update.'];
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $bookmarkId = (int)($row['id'] ?? 0);
            $stored = (string)($row['url'] ?? '');
            $plain = itm_decrypt($stored, $oldKeySession);
            if ($plain === false || $plain === '') {
                if (preg_match('/^(https?|ftp):\/\//i', $stored)) {
                    $plain = $stored;
                } else {
                    mysqli_stmt_close($upd_stmt);
                    mysqli_stmt_close($sel_stmt);
                    return ['ok' => false, 'message' => 'Failed to re-encrypt bookmark URLs. Please try again.'];
                }
            }

            $re_encrypted = itm_encrypt($plain, $newKeySession);
            mysqli_stmt_bind_param($upd_stmt, 'sii', $re_encrypted, $bookmarkId, $employeeId);
            if (!mysqli_stmt_execute($upd_stmt)) {
                mysqli_stmt_close($upd_stmt);
                mysqli_stmt_close($sel_stmt);
                return ['ok' => false, 'message' => 'Failed to re-encrypt bookmark URLs. Please try again.'];
            }
        }

        mysqli_stmt_close($upd_stmt);
        mysqli_stmt_close($sel_stmt);

        $title_sel = mysqli_prepare(
            $conn,
            'SELECT id, title FROM bookmarks WHERE employee_id = ? AND shared = 0 AND active = 1'
        );
        if (!$title_sel) {
            return ['ok' => false, 'message' => 'Failed to load bookmark titles.'];
        }
        mysqli_stmt_bind_param($title_sel, 'i', $employeeId);
        if (!mysqli_stmt_execute($title_sel)) {
            mysqli_stmt_close($title_sel);
            return ['ok' => false, 'message' => 'Failed to load bookmark titles.'];
        }
        $title_res = mysqli_stmt_get_result($title_sel);
        $title_upd = mysqli_prepare($conn, 'UPDATE bookmarks SET title = ? WHERE id = ? AND employee_id = ?');
        if (!$title_upd) {
            mysqli_stmt_close($title_sel);
            return ['ok' => false, 'message' => 'Failed to prepare bookmark title update.'];
        }
        while ($row = mysqli_fetch_assoc($title_res)) {
            $bookmarkId = (int)($row['id'] ?? 0);
            $stored = (string)($row['title'] ?? '');
            $plain = itm_decrypt($stored, $oldKeySession);
            if ($plain === false || $plain === '') {
                if ($stored !== '' && strlen($stored) <= 255) {
                    $plain = $stored;
                } else {
                    mysqli_stmt_close($title_upd);
                    mysqli_stmt_close($title_sel);
                    return ['ok' => false, 'message' => 'Failed to re-encrypt bookmark titles. Please try again.'];
                }
            }
            $re_encrypted = itm_encrypt($plain, $newKeySession);
            mysqli_stmt_bind_param($title_upd, 'sii', $re_encrypted, $bookmarkId, $employeeId);
            if (!mysqli_stmt_execute($title_upd)) {
                mysqli_stmt_close($title_upd);
                mysqli_stmt_close($title_sel);
                return ['ok' => false, 'message' => 'Failed to re-encrypt bookmark titles. Please try again.'];
            }
        }
        mysqli_stmt_close($title_upd);
        mysqli_stmt_close($title_sel);

        $folder_sel = mysqli_prepare(
            $conn,
            'SELECT id, name FROM bookmark_folders WHERE employee_id = ? AND shared = 0 AND active = 1'
        );
        if (!$folder_sel) {
            return ['ok' => false, 'message' => 'Failed to load folder names.'];
        }
        mysqli_stmt_bind_param($folder_sel, 'i', $employeeId);
        if (!mysqli_stmt_execute($folder_sel)) {
            mysqli_stmt_close($folder_sel);
            return ['ok' => false, 'message' => 'Failed to load folder names.'];
        }
        $folder_res = mysqli_stmt_get_result($folder_sel);
        $folder_upd = mysqli_prepare($conn, 'UPDATE bookmark_folders SET name = ? WHERE id = ? AND employee_id = ?');
        if (!$folder_upd) {
            mysqli_stmt_close($folder_sel);
            return ['ok' => false, 'message' => 'Failed to prepare folder name update.'];
        }
        while ($row = mysqli_fetch_assoc($folder_res)) {
            $folderId = (int)($row['id'] ?? 0);
            $stored = (string)($row['name'] ?? '');
            $plain = itm_decrypt($stored, $oldKeySession);
            if ($plain === false || $plain === '') {
                if ($stored !== '' && strlen($stored) <= 255) {
                    $plain = $stored;
                } else {
                    mysqli_stmt_close($folder_upd);
                    mysqli_stmt_close($folder_sel);
                    return ['ok' => false, 'message' => 'Failed to re-encrypt folder names. Please try again.'];
                }
            }
            $re_encrypted = itm_encrypt($plain, $newKeySession);
            mysqli_stmt_bind_param($folder_upd, 'sii', $re_encrypted, $folderId, $employeeId);
            if (!mysqli_stmt_execute($folder_upd)) {
                mysqli_stmt_close($folder_upd);
                mysqli_stmt_close($folder_sel);
                return ['ok' => false, 'message' => 'Failed to re-encrypt folder names. Please try again.'];
            }
        }
        mysqli_stmt_close($folder_upd);
        mysqli_stmt_close($folder_sel);

        return ['ok' => true, 'message' => ''];
    }
}
