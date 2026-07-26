<?php
/**
 * Webmail — session-scoped mailbox rules on shared `emails` table.
 */

if (!function_exists('webmail_session_email')) {
    function webmail_session_email(): string
    {
        return trim((string)($_SESSION['email'] ?? ''));
    }
}

if (!function_exists('webmail_normalize_email')) {
    function webmail_normalize_email(string $email): string
    {
        return strtolower(trim($email));
    }
}

if (!function_exists('webmail_cc_contains')) {
    function webmail_cc_contains(string $ccField, string $sessionEmail): bool
    {
        $needle = webmail_normalize_email($sessionEmail);
        if ($needle === '') {
            return false;
        }
        $ccField = trim($ccField);
        if ($ccField === '') {
            return false;
        }
        if (webmail_normalize_email($ccField) === $needle) {
            return true;
        }
        $parts = preg_split('/[,;]+/', $ccField);
        if (!is_array($parts)) {
            return false;
        }
        foreach ($parts as $part) {
            if (webmail_normalize_email((string)$part) === $needle) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('webmail_is_recipient')) {
    function webmail_is_recipient(array $row, string $sessionEmail): bool
    {
        $needle = webmail_normalize_email($sessionEmail);
        if ($needle === '') {
            return false;
        }
        $to = webmail_normalize_email((string)($row['to_email'] ?? ''));

        return $to === $needle || webmail_cc_contains((string)($row['cc_email'] ?? ''), $sessionEmail);
    }
}

if (!function_exists('webmail_is_sender')) {
    function webmail_is_sender(array $row, string $sessionEmail): bool
    {
        $needle = webmail_normalize_email($sessionEmail);
        if ($needle === '') {
            return false;
        }

        return webmail_normalize_email((string)($row['from_email'] ?? '')) === $needle;
    }
}

if (!function_exists('webmail_folders')) {
    function webmail_folders(): array
    {
        return ['inbox', 'starred', 'sent', 'archived', 'trash'];
    }
}

if (!function_exists('webmail_resolve_folder')) {
    function webmail_resolve_folder(string $raw): string
    {
        $folder = strtolower(trim($raw));
        if (!in_array($folder, webmail_folders(), true)) {
            return 'inbox';
        }

        return $folder;
    }
}

if (!function_exists('webmail_sql_recipient_match')) {
    /**
     * SQL fragment with six bound placeholders (same normalized email).
     *
     * @return array{sql: string, types: string, repeat: int}
     */
    function webmail_sql_recipient_match(): array
    {
        return [
            'sql' => '(LOWER(TRIM(to_email)) = ? OR LOWER(TRIM(cc_email)) = ? OR LOWER(cc_email) LIKE CONCAT(\'%,\', ?, \'%\') OR LOWER(cc_email) LIKE CONCAT(?, \',%\') OR LOWER(cc_email) LIKE CONCAT(\'%;\', ?, \'%\') OR LOWER(cc_email) LIKE CONCAT(?, \';\'))',
            'types' => 'ssssss',
            'repeat' => 6,
        ];
    }
}

if (!function_exists('webmail_bind_email_params')) {
    function webmail_bind_email_params(string $normalizedEmail, int $repeat): array
    {
        $params = [];
        for ($i = 0; $i < $repeat; $i++) {
            $params[] = $normalizedEmail;
        }

        return $params;
    }
}

if (!function_exists('webmail_row_visible_to_user')) {
    function webmail_row_visible_to_user(array $row, string $sessionEmail, int $employeeId, string $folder = ''): bool
    {
        $folder = $folder !== '' ? webmail_resolve_folder($folder) : '';
        $isDeleted = (int)($row['is_deleted'] ?? 0) === 1;
        $isArchived = (int)($row['is_archived'] ?? 0) === 1;
        $deletedBy = (int)($row['deleted_by'] ?? 0);

        if ($folder === 'trash') {
            return $isDeleted && $deletedBy === $employeeId;
        }

        if ($isDeleted) {
            return false;
        }

        $mailbox = webmail_is_recipient($row, $sessionEmail) || webmail_is_sender($row, $sessionEmail);
        if (!$mailbox) {
            return false;
        }

        if ($folder === 'archived') {
            return $isArchived;
        }

        if ($folder === 'starred') {
            return (int)($row['is_star'] ?? 0) === 1;
        }

        if ($folder === 'sent') {
            return webmail_is_sender($row, $sessionEmail) && !$isArchived;
        }

        if ($folder === 'inbox') {
            return webmail_is_recipient($row, $sessionEmail) && !$isArchived;
        }

        if ($isArchived) {
            return true;
        }

        return $mailbox;
    }
}

if (!function_exists('webmail_get_row_by_id')) {
    function webmail_get_row_by_id(mysqli $conn, int $id, int $companyId): ?array
    {
        if ($id <= 0 || $companyId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare($conn, 'SELECT * FROM emails WHERE id = ? AND company_id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = ($res && mysqli_num_rows($res) === 1) ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('webmail_build_list_query')) {
    /**
     * @return array{
     *   where_sql: string,
     *   types: string,
     *   params: array<int, mixed>,
     *   sort: string,
     *   dir: string
     * }
     */
    function webmail_build_list_query(
        string $folder,
        int $companyId,
        int $employeeId,
        string $sessionEmail,
        array $filters
    ): array {
        $folder = webmail_resolve_folder($folder);
        $normalized = webmail_normalize_email($sessionEmail);
        $recipient = webmail_sql_recipient_match();

        $whereParts = ['company_id = ?'];
        $types = 'i';
        $params = [$companyId];

        if ($folder === 'trash') {
            $whereParts[] = 'is_deleted = 1';
            $whereParts[] = 'deleted_by = ?';
            $types .= 'i';
            $params[] = $employeeId;
        } else {
            $whereParts[] = 'is_deleted = 0';
            if ($folder === 'archived') {
                $whereParts[] = 'is_archived = 1';
                $whereParts[] = '(' . $recipient['sql'] . ' OR LOWER(TRIM(from_email)) = ?)';
                $types .= $recipient['types'] . 's';
                $params = array_merge($params, webmail_bind_email_params($normalized, $recipient['repeat']), [$normalized]);
            } elseif ($folder === 'starred') {
                $whereParts[] = 'is_star = 1';
                $whereParts[] = '(' . $recipient['sql'] . ' OR LOWER(TRIM(from_email)) = ?)';
                $types .= $recipient['types'] . 's';
                $params = array_merge($params, webmail_bind_email_params($normalized, $recipient['repeat']), [$normalized]);
            } elseif ($folder === 'sent') {
                $whereParts[] = 'is_archived = 0';
                $whereParts[] = 'LOWER(TRIM(from_email)) = ?';
                $types .= 's';
                $params[] = $normalized;
            } else {
                $whereParts[] = 'is_archived = 0';
                $whereParts[] = $recipient['sql'];
                $types .= $recipient['types'];
                $params = array_merge($params, webmail_bind_email_params($normalized, $recipient['repeat']));
            }
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['sent', 'failed', 'received'], true)) {
            $whereParts[] = 'status = ?';
            $types .= 's';
            $params[] = $status;
        }

        $starFilter = (string)($filters['starred'] ?? '');
        if ($folder !== 'starred') {
            if ($starFilter === '1') {
                $whereParts[] = 'is_star = 1';
            } elseif ($starFilter === '0') {
                $whereParts[] = 'is_star = 0';
            }
        }

        $archivedFilter = (string)($filters['archived'] ?? '');
        if ($folder === 'inbox' && $archivedFilter === '1') {
            $whereParts = array_values(array_filter($whereParts, static function (string $part): bool {
                return $part !== 'is_archived = 0';
            }));
            $whereParts[] = 'is_archived = 1';
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $parsed = itm_parse_date_input($dateFrom);
            if ($parsed !== '') {
                $whereParts[] = 'sent_at >= ?';
                $types .= 's';
                $params[] = $parsed . ' 00:00:00';
            }
        }
        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $parsed = itm_parse_date_input($dateTo);
            if ($parsed !== '') {
                $whereParts[] = 'sent_at <= ?';
                $types .= 's';
                $params[] = $parsed . ' 23:59:59';
            }
        }

        $searchRaw = trim((string)($filters['search'] ?? ''));
        if ($searchRaw !== '') {
            $searchPattern = (strpos($searchRaw, '%') !== false || strpos($searchRaw, '_') !== false)
                ? $searchRaw
                : '%' . $searchRaw . '%';
            $whereParts[] = '(from_email LIKE ? OR to_email LIKE ? OR cc_email LIKE ? OR subject LIKE ? OR details LIKE ? OR CAST(sent_at AS CHAR) LIKE ?)';
            $types .= 'ssssss';
            for ($i = 0; $i < 6; $i++) {
                $params[] = $searchPattern;
            }
        }

        $sortable = ['from_email', 'to_email', 'cc_email', 'subject', 'status', 'is_star', 'sent_at', 'id'];
        $sort = trim((string)($filters['sort'] ?? 'sent_at'));
        if (!in_array($sort, $sortable, true)) {
            $sort = 'sent_at';
        }
        $dir = strtoupper((string)($filters['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        return [
            'where_sql' => implode(' AND ', $whereParts),
            'types' => $types,
            'params' => $params,
            'sort' => $sort,
            'dir' => $dir,
        ];
    }
}

if (!function_exists('webmail_fetch_list')) {
    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    function webmail_fetch_list(
        mysqli $conn,
        string $folder,
        int $companyId,
        int $employeeId,
        string $sessionEmail,
        array $filters,
        int $perPage,
        int $page
    ): array {
        $built = webmail_build_list_query($folder, $companyId, $employeeId, $sessionEmail, $filters);
        $total = 0;
        $countSql = 'SELECT COUNT(*) FROM emails WHERE ' . $built['where_sql'];
        $countStmt = mysqli_prepare($conn, $countSql);
        if ($countStmt) {
            mysqli_stmt_bind_param($countStmt, $built['types'], ...$built['params']);
            mysqli_stmt_execute($countStmt);
            mysqli_stmt_bind_result($countStmt, $total);
            mysqli_stmt_fetch($countStmt);
            mysqli_stmt_close($countStmt);
        }
        $total = (int)$total;
        $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $rows = [];
        $listSql = 'SELECT id, from_email, to_email, cc_email, subject, status, is_star, is_archived, is_deleted, sent_at
                    FROM emails WHERE ' . $built['where_sql'] . ' ORDER BY ' . $built['sort'] . ' ' . $built['dir'] . ', id DESC LIMIT ? OFFSET ?';
        $listTypes = $built['types'] . 'ii';
        $listParams = array_merge($built['params'], [$perPage, $offset]);
        $listStmt = mysqli_prepare($conn, $listSql);
        if ($listStmt) {
            mysqli_stmt_bind_param($listStmt, $listTypes, ...$listParams);
            mysqli_stmt_execute($listStmt);
            $res = mysqli_stmt_get_result($listStmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($listStmt);
        }

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'total_pages' => $totalPages];
    }
}

if (!function_exists('webmail_render_details_html')) {
    function webmail_render_details_html(?string $html): string
    {
        $html = (string)$html;
        if ($html === '') {
            return '';
        }
        $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><a><h1><h2><h3><blockquote><span><div>';
        $stripped = strip_tags($html, $allowed);

        return $stripped;
    }
}

if (!function_exists('webmail_soft_delete')) {
    function webmail_soft_delete(mysqli $conn, int $id, int $companyId, int $employeeId, string $sessionEmail): bool
    {
        $row = webmail_get_row_by_id($conn, $id, $companyId);
        if (!$row || (int)($row['is_deleted'] ?? 0) === 1) {
            return false;
        }
        if (!webmail_is_recipient($row, $sessionEmail) && !webmail_is_sender($row, $sessionEmail)) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE emails SET is_deleted = 1, deleted_by = ?, deleted_at = NOW(), active = 0, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ? AND is_deleted = 0'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $employeeId, $employeeId, $id, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('webmail_restore')) {
    function webmail_restore(mysqli $conn, int $id, int $companyId, int $employeeId): bool
    {
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE emails SET is_deleted = 0, deleted_by = NULL, deleted_at = NULL, active = 1, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ? AND is_deleted = 1 AND deleted_by = ?'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $employeeId, $id, $companyId, $employeeId);
        $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('webmail_hard_delete')) {
    function webmail_hard_delete(mysqli $conn, int $id, int $companyId, int $employeeId, string $sessionEmail): bool
    {
        $row = webmail_get_row_by_id($conn, $id, $companyId);
        if (!$row) {
            return false;
        }
        $inTrash = (int)($row['is_deleted'] ?? 0) === 1 && (int)($row['deleted_by'] ?? 0) === $employeeId;
        if (!$inTrash) {
            return false;
        }
        $stmt = mysqli_prepare($conn, 'DELETE FROM emails WHERE id = ? AND company_id = ?');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
        $ok = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('webmail_toggle_star')) {
    function webmail_toggle_star(mysqli $conn, int $id, int $companyId, int $employeeId, string $sessionEmail): bool
    {
        $row = webmail_get_row_by_id($conn, $id, $companyId);
        if (!$row || (int)($row['is_deleted'] ?? 0) === 1) {
            return false;
        }
        if (!webmail_is_recipient($row, $sessionEmail) && !webmail_is_sender($row, $sessionEmail)) {
            return false;
        }
        $newStar = (int)($row['is_star'] ?? 0) === 1 ? 0 : 1;
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE emails SET is_star = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND is_deleted = 0'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $newStar, $employeeId, $id, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('webmail_toggle_archive')) {
    function webmail_toggle_archive(mysqli $conn, int $id, int $companyId, int $employeeId, string $sessionEmail, ?int $forceValue = null): bool
    {
        $row = webmail_get_row_by_id($conn, $id, $companyId);
        if (!$row || (int)($row['is_deleted'] ?? 0) === 1) {
            return false;
        }
        if (!webmail_is_recipient($row, $sessionEmail) && !webmail_is_sender($row, $sessionEmail)) {
            return false;
        }
        $newArchived = $forceValue !== null ? (int)$forceValue : ((int)($row['is_archived'] ?? 0) === 1 ? 0 : 1);
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE emails SET is_archived = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ? AND is_deleted = 0'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $newArchived, $employeeId, $id, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('webmail_page_url')) {
    function webmail_page_url(array $base, array $extra = []): string
    {
        $query = array_merge($base, $extra);

        return 'index.php?' . http_build_query($query);
    }
}
