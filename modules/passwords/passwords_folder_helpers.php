<?php
/**
 * Password folder tree helpers (move / merge) — mirrors bookmarks folder DnD behaviour.
 */

if (!function_exists('pwd_get_folder_row_by_id')) {
    function pwd_get_folder_row_by_id($conn, $folderId, $employeeId)
    {
        $folderId = (int)$folderId;
        $employeeId = (int)$employeeId;
        if ($folderId <= 0 || $employeeId <= 0) {
            return null;
        }

        $stmt = mysqli_prepare($conn, 'SELECT * FROM password_folders WHERE id = ? AND employee_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $folderId, $employeeId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('pwd_folder_is_descendant_of')) {
    function pwd_folder_is_descendant_of($conn, $folderId, $possibleAncestorId)
    {
        $folderId = (int)$folderId;
        $possibleAncestorId = (int)$possibleAncestorId;
        if ($folderId <= 0 || $possibleAncestorId <= 0) {
            return false;
        }
        if ($folderId === $possibleAncestorId) {
            return true;
        }

        $seen = [];
        $current = $folderId;
        while ($current > 0 && !isset($seen[$current])) {
            $seen[$current] = true;
            $stmt = mysqli_prepare($conn, 'SELECT parent_id FROM password_folders WHERE id = ? LIMIT 1');
            if (!$stmt) {
                break;
            }
            mysqli_stmt_bind_param($stmt, 'i', $current);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if (!is_array($row)) {
                break;
            }
            $parent = $row['parent_id'] !== null ? (int)$row['parent_id'] : 0;
            if ($parent === $possibleAncestorId) {
                return true;
            }
            $current = $parent;
        }

        return false;
    }
}

if (!function_exists('pwd_merge_folder_into')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function pwd_merge_folder_into($conn, $employeeId, $sourceFolderId, $targetFolderId)
    {
        $sourceFolderId = (int)$sourceFolderId;
        $targetFolderId = (int)$targetFolderId;
        $employeeId = (int)$employeeId;

        if ($sourceFolderId <= 0 || $targetFolderId <= 0 || $sourceFolderId === $targetFolderId) {
            return ['ok' => false, 'message' => 'Invalid folder merge.'];
        }

        mysqli_begin_transaction($conn);

        $moveEntriesStmt = mysqli_prepare(
            $conn,
            'UPDATE password_entries SET folder_id = ? WHERE folder_id = ? AND employee_id = ?'
        );
        if (!$moveEntriesStmt) {
            mysqli_rollback($conn);

            return ['ok' => false, 'message' => 'Could not merge folders.'];
        }
        mysqli_stmt_bind_param($moveEntriesStmt, 'iii', $targetFolderId, $sourceFolderId, $employeeId);
        if (!mysqli_stmt_execute($moveEntriesStmt)) {
            mysqli_stmt_close($moveEntriesStmt);
            mysqli_rollback($conn);

            return ['ok' => false, 'message' => 'Could not merge folders.'];
        }
        mysqli_stmt_close($moveEntriesStmt);

        $moveChildrenStmt = mysqli_prepare(
            $conn,
            'UPDATE password_folders SET parent_id = ? WHERE parent_id = ? AND employee_id = ?'
        );
        if (!$moveChildrenStmt) {
            mysqli_rollback($conn);

            return ['ok' => false, 'message' => 'Could not merge folders.'];
        }
        mysqli_stmt_bind_param($moveChildrenStmt, 'iii', $targetFolderId, $sourceFolderId, $employeeId);
        if (!mysqli_stmt_execute($moveChildrenStmt)) {
            mysqli_stmt_close($moveChildrenStmt);
            mysqli_rollback($conn);

            return ['ok' => false, 'message' => 'Could not merge folders.'];
        }
        mysqli_stmt_close($moveChildrenStmt);

        $deleteFolderStmt = mysqli_prepare($conn, 'DELETE FROM password_folders WHERE id = ? AND employee_id = ?');
        if (!$deleteFolderStmt) {
            mysqli_rollback($conn);

            return ['ok' => false, 'message' => 'Could not merge folders.'];
        }
        mysqli_stmt_bind_param($deleteFolderStmt, 'ii', $sourceFolderId, $employeeId);
        if (!mysqli_stmt_execute($deleteFolderStmt)) {
            mysqli_stmt_close($deleteFolderStmt);
            mysqli_rollback($conn);

            return ['ok' => false, 'message' => 'Could not merge folders.'];
        }
        mysqli_stmt_close($deleteFolderStmt);

        mysqli_commit($conn);

        return ['ok' => true, 'message' => ''];
    }
}

if (!function_exists('pwd_move_folder')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function pwd_move_folder($conn, $employeeId, $folderId, $newParentId, $mergeIntoFolderId)
    {
        $folderId = (int)$folderId;
        $employeeId = (int)$employeeId;
        $newParentId = $newParentId !== null && (int)$newParentId > 0 ? (int)$newParentId : null;
        $mergeIntoFolderId = (int)$mergeIntoFolderId;

        $folder = pwd_get_folder_row_by_id($conn, $folderId, $employeeId);
        if (!$folder) {
            return ['ok' => false, 'message' => 'Folder not found or access denied.'];
        }

        if ($newParentId !== null && pwd_folder_is_descendant_of($conn, $newParentId, $folderId)) {
            return ['ok' => false, 'message' => 'Cannot move a folder into itself or a subfolder.'];
        }

        if ($mergeIntoFolderId > 0) {
            if ($mergeIntoFolderId === $folderId) {
                return ['ok' => false, 'message' => 'Invalid merge target.'];
            }

            $target = pwd_get_folder_row_by_id($conn, $mergeIntoFolderId, $employeeId);
            if (!$target) {
                return ['ok' => false, 'message' => 'Merge target not found or access denied.'];
            }

            $targetParent = $target['parent_id'] !== null ? (int)$target['parent_id'] : null;
            if ($targetParent !== $newParentId) {
                return ['ok' => false, 'message' => 'Merge target is not in the destination folder.'];
            }

            if (strcasecmp(trim((string)$folder['name']), trim((string)$target['name'])) !== 0) {
                return ['ok' => false, 'message' => 'Folder names must match to merge.'];
            }

            return pwd_merge_folder_into($conn, $employeeId, $folderId, $mergeIntoFolderId);
        }

        if ($newParentId === null) {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE password_folders SET parent_id = NULL, updated_by = ? WHERE id = ? AND employee_id = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Could not move folder.'];
            }
            mysqli_stmt_bind_param($stmt, 'iii', $employeeId, $folderId, $employeeId);
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE password_folders SET parent_id = ?, updated_by = ? WHERE id = ? AND employee_id = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Could not move folder.'];
            }
            mysqli_stmt_bind_param($stmt, 'iiii', $newParentId, $employeeId, $folderId, $employeeId);
        }

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);

            return ['ok' => false, 'message' => 'Could not move folder.'];
        }
        mysqli_stmt_close($stmt);

        return ['ok' => true, 'message' => ''];
    }
}
