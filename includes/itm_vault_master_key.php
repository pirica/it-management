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

        $notes_sel = mysqli_prepare(
            $conn,
            'SELECT id, notes FROM bookmarks WHERE employee_id = ? AND shared = 0 AND active = 1'
        );
        if (!$notes_sel) {
            return ['ok' => false, 'message' => 'Failed to load bookmark notes.'];
        }
        mysqli_stmt_bind_param($notes_sel, 'i', $employeeId);
        if (!mysqli_stmt_execute($notes_sel)) {
            mysqli_stmt_close($notes_sel);
            return ['ok' => false, 'message' => 'Failed to load bookmark notes.'];
        }
        $notes_res = mysqli_stmt_get_result($notes_sel);
        $notes_upd = mysqli_prepare($conn, 'UPDATE bookmarks SET notes = ? WHERE id = ? AND employee_id = ?');
        if (!$notes_upd) {
            mysqli_stmt_close($notes_sel);
            return ['ok' => false, 'message' => 'Failed to prepare bookmark notes update.'];
        }
        while ($row = mysqli_fetch_assoc($notes_res)) {
            $bookmarkId = (int)($row['id'] ?? 0);
            $stored = (string)($row['notes'] ?? '');
            if ($stored === '') {
                continue;
            }
            $plain = itm_decrypt($stored, $oldKeySession);
            if ($plain === false || $plain === '') {
                if (function_exists('bkm_private_text_legacy_plaintext_check')
                    && bkm_private_text_legacy_plaintext_check($stored)) {
                    $plain = $stored;
                } else {
                    mysqli_stmt_close($notes_upd);
                    mysqli_stmt_close($notes_sel);
                    return ['ok' => false, 'message' => 'Failed to re-encrypt bookmark notes. Please try again.'];
                }
            }
            $re_encrypted = itm_encrypt($plain, $newKeySession);
            mysqli_stmt_bind_param($notes_upd, 'sii', $re_encrypted, $bookmarkId, $employeeId);
            if (!mysqli_stmt_execute($notes_upd)) {
                mysqli_stmt_close($notes_upd);
                mysqli_stmt_close($notes_sel);
                return ['ok' => false, 'message' => 'Failed to re-encrypt bookmark notes. Please try again.'];
            }
        }
        mysqli_stmt_close($notes_upd);
        mysqli_stmt_close($notes_sel);

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

if (!function_exists('itm_vault_reencrypt_notes')) {
    /**
     * Re-encrypt private notes fields for one employee when the vault session key changes.
     *
     * @return array{ok:bool, message:string}
     */
    function itm_vault_reencrypt_notes($conn, $employeeId, $oldKeySession, $newKeySession)
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
            "SELECT id, title, content, checklist_json FROM notes WHERE employee_id = ? AND active = 1 AND (shared_with_json IS NULL OR shared_with_json = '' OR shared_with_json = '[]')"
        );
        if (!$sel_stmt) {
            return ['ok' => false, 'message' => 'Failed to load notes.'];
        }
        mysqli_stmt_bind_param($sel_stmt, 'i', $employeeId);
        if (!mysqli_stmt_execute($sel_stmt)) {
            mysqli_stmt_close($sel_stmt);
            return ['ok' => false, 'message' => 'Failed to load notes.'];
        }

        $res = mysqli_stmt_get_result($sel_stmt);
        $upd_stmt = mysqli_prepare(
            $conn,
            'UPDATE notes SET title = ?, content = ?, checklist_json = ? WHERE id = ? AND employee_id = ?'
        );
        if (!$upd_stmt) {
            mysqli_stmt_close($sel_stmt);
            return ['ok' => false, 'message' => 'Failed to prepare note update.'];
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $noteId = (int)($row['id'] ?? 0);
            $fields = ['title' => (string)($row['title'] ?? ''), 'content' => (string)($row['content'] ?? ''), 'checklist_json' => (string)($row['checklist_json'] ?? '')];
            foreach ($fields as $field => $stored) {
                if ($stored === '') {
                    continue;
                }
                $plain = itm_decrypt($stored, $oldKeySession);
                if ($plain === false || $plain === '') {
                    if ($field === 'title' && strlen($stored) <= 255) {
                        $plain = $stored;
                    } elseif ($field === 'content' && strlen($stored) <= 64 && base64_decode($stored, true) === false) {
                        $plain = $stored;
                    } elseif ($field === 'checklist_json' && ($stored[0] === '[' || $stored[0] === '{')) {
                        $plain = $stored;
                    } else {
                        mysqli_stmt_close($upd_stmt);
                        mysqli_stmt_close($sel_stmt);
                        return ['ok' => false, 'message' => 'Failed to re-encrypt notes. Please try again.'];
                    }
                }
                $fields[$field] = itm_encrypt($plain, $newKeySession);
            }

            $title = $fields['title'];
            $content = $fields['content'];
            $checklist = $fields['checklist_json'] === '' ? null : $fields['checklist_json'];
            mysqli_stmt_bind_param($upd_stmt, 'sssii', $title, $content, $checklist, $noteId, $employeeId);
            if (!mysqli_stmt_execute($upd_stmt)) {
                mysqli_stmt_close($upd_stmt);
                mysqli_stmt_close($sel_stmt);
                return ['ok' => false, 'message' => 'Failed to re-encrypt notes. Please try again.'];
            }
        }
        mysqli_stmt_close($upd_stmt);
        mysqli_stmt_close($sel_stmt);

        $label_sel = mysqli_prepare($conn, 'SELECT id, label FROM note_labels WHERE employee_id = ? AND active = 1');
        if (!$label_sel) {
            return ['ok' => false, 'message' => 'Failed to load note labels.'];
        }
        mysqli_stmt_bind_param($label_sel, 'i', $employeeId);
        if (!mysqli_stmt_execute($label_sel)) {
            mysqli_stmt_close($label_sel);
            return ['ok' => false, 'message' => 'Failed to load note labels.'];
        }
        $label_res = mysqli_stmt_get_result($label_sel);
        $label_upd = mysqli_prepare($conn, 'UPDATE note_labels SET label = ? WHERE id = ? AND employee_id = ?');
        if (!$label_upd) {
            mysqli_stmt_close($label_sel);
            return ['ok' => false, 'message' => 'Failed to prepare note label update.'];
        }
        while ($row = mysqli_fetch_assoc($label_res)) {
            $labelId = (int)($row['id'] ?? 0);
            $stored = (string)($row['label'] ?? '');
            if ($stored === '') {
                continue;
            }
            $plain = itm_decrypt($stored, $oldKeySession);
            if ($plain === false || $plain === '') {
                if ($stored !== '' && strlen($stored) <= 100) {
                    $plain = $stored;
                } else {
                    mysqli_stmt_close($label_upd);
                    mysqli_stmt_close($label_sel);
                    return ['ok' => false, 'message' => 'Failed to re-encrypt note labels. Please try again.'];
                }
            }
            $re_encrypted = itm_encrypt($plain, $newKeySession);
            mysqli_stmt_bind_param($label_upd, 'sii', $re_encrypted, $labelId, $employeeId);
            if (!mysqli_stmt_execute($label_upd)) {
                mysqli_stmt_close($label_upd);
                mysqli_stmt_close($label_sel);
                return ['ok' => false, 'message' => 'Failed to re-encrypt note labels. Please try again.'];
            }
        }
        mysqli_stmt_close($label_upd);
        mysqli_stmt_close($label_sel);

        return ['ok' => true, 'message' => ''];
    }
}
