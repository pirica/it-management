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

if (!function_exists('webmail_reads_table_exists')) {
    function webmail_reads_table_exists(mysqli $conn): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $res = mysqli_query($conn, "SHOW TABLES LIKE 'webmail_email_reads'");
        $cache = $res instanceof mysqli_result && mysqli_num_rows($res) > 0;
        if ($res instanceof mysqli_result) {
            mysqli_free_result($res);
        }

        return $cache;
    }
}

if (!function_exists('webmail_user_may_touch_message')) {
    function webmail_user_may_touch_message(array $row, string $sessionEmail): bool
    {
        if ((int)($row['is_deleted'] ?? 0) === 1) {
            return false;
        }

        return webmail_is_recipient($row, $sessionEmail) || webmail_is_sender($row, $sessionEmail);
    }
}

if (!function_exists('webmail_is_email_read')) {
    function webmail_is_email_read(mysqli $conn, int $emailId, int $companyId, int $employeeId): bool
    {
        if ($emailId <= 0 || !webmail_reads_table_exists($conn)) {
            return true;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT 1 FROM webmail_email_reads
             WHERE company_id = ? AND employee_id = ? AND email_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return true;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $employeeId, $emailId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $found);
        $has = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$has;
    }
}

if (!function_exists('webmail_attach_read_flags_to_rows')) {
    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    function webmail_attach_read_flags_to_rows(mysqli $conn, array $rows, int $companyId, int $employeeId): array
    {
        if ($rows === []) {
            return $rows;
        }
        if (!webmail_reads_table_exists($conn)) {
            foreach ($rows as $idx => $row) {
                $rows[$idx]['is_read'] = 1;
            }

            return $rows;
        }
        $ids = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return $rows;
        }
        $readIds = [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT email_id FROM webmail_email_reads
                WHERE company_id = ? AND employee_id = ? AND deleted_at IS NULL AND email_id IN (' . $placeholders . ')';
        $types = 'ii' . str_repeat('i', count($ids));
        $params = array_merge([$companyId, $employeeId], array_values($ids));
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($readRow = mysqli_fetch_assoc($res))) {
                $readIds[(int)($readRow['email_id'] ?? 0)] = true;
            }
            mysqli_stmt_close($stmt);
        }
        foreach ($rows as $idx => $row) {
            $id = (int)($row['id'] ?? 0);
            $rows[$idx]['is_read'] = isset($readIds[$id]) ? 1 : 0;
        }

        return $rows;
    }
}

if (!function_exists('webmail_mark_read')) {
    function webmail_mark_read(mysqli $conn, int $emailId, int $companyId, int $employeeId, string $sessionEmail): bool
    {
        if ($emailId <= 0 || !webmail_reads_table_exists($conn)) {
            return false;
        }
        $row = webmail_get_row_by_id($conn, $emailId, $companyId);
        if (!$row || !webmail_user_may_touch_message($row, $sessionEmail)) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO webmail_email_reads (company_id, employee_id, email_id, read_at, active, created_by, updated_by)
             VALUES (?, ?, ?, NOW(), 1, ?, ?)
             ON DUPLICATE KEY UPDATE read_at = NOW(), deleted_at = NULL, deleted_by = NULL, active = 1, updated_by = ?, updated_at = NOW()'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiiiii', $companyId, $employeeId, $emailId, $employeeId, $employeeId, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('webmail_mark_unread')) {
    function webmail_mark_unread(mysqli $conn, int $emailId, int $companyId, int $employeeId, string $sessionEmail): bool
    {
        if ($emailId <= 0 || !webmail_reads_table_exists($conn)) {
            return false;
        }
        $row = webmail_get_row_by_id($conn, $emailId, $companyId);
        if (!$row || !webmail_user_may_touch_message($row, $sessionEmail)) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'DELETE FROM webmail_email_reads WHERE company_id = ? AND employee_id = ? AND email_id = ?'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $employeeId, $emailId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
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

        $sortable = ['from_email', 'to_email', 'cc_email', 'subject', 'status', 'is_star', 'is_archived', 'is_deleted', 'sent_at', 'id', 'is_read'];
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

if (!function_exists('webmail_resolve_list_order')) {
    /**
     * @return array{join_sql: string, join_types: string, join_params: array<int, int>, order_expr: string}
     */
    function webmail_resolve_list_order(mysqli $conn, string $sort, int $companyId, int $employeeId): array
    {
        $result = [
            'join_sql' => '',
            'join_types' => '',
            'join_params' => [],
            'order_expr' => 'emails.sent_at',
        ];
        if ($sort === 'is_read' && webmail_reads_table_exists($conn)) {
            $result['join_sql'] = ' LEFT JOIN webmail_email_reads AS wread ON wread.email_id = emails.id AND wread.company_id = ? AND wread.employee_id = ? AND wread.deleted_at IS NULL';
            $result['join_types'] = 'ii';
            $result['join_params'] = [$companyId, $employeeId];
            $result['order_expr'] = '(wread.id IS NOT NULL)';

            return $result;
        }
        $sortable = ['from_email', 'to_email', 'cc_email', 'subject', 'status', 'is_star', 'is_archived', 'is_deleted', 'sent_at', 'id'];
        if (in_array($sort, $sortable, true) && function_exists('itm_is_safe_identifier') && itm_is_safe_identifier($sort)) {
            $result['order_expr'] = 'emails.' . $sort;
        }

        return $result;
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

        $order = webmail_resolve_list_order($conn, $built['sort'], $companyId, $employeeId);

        $rows = [];
        $listSql = 'SELECT emails.id, emails.from_email, emails.to_email, emails.cc_email, emails.subject, emails.status, emails.is_star, emails.is_archived, emails.is_deleted, emails.sent_at
                    FROM emails' . $order['join_sql'] . ' WHERE ' . $built['where_sql'] . ' ORDER BY ' . $order['order_expr'] . ' ' . $built['dir'] . ', emails.id DESC LIMIT ? OFFSET ?';
        $listTypes = $built['types'] . $order['join_types'] . 'ii';
        $listParams = array_merge($built['params'], $order['join_params'], [$perPage, $offset]);
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

        $rows = webmail_attach_read_flags_to_rows($conn, $rows, $companyId, $employeeId);

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'total_pages' => $totalPages];
    }
}

if (!function_exists('webmail_fetch_all_list_ids')) {
    /**
     * All message ids in the current folder + filters (for Clear Table).
     *
     * @return list<int>
     */
    function webmail_fetch_all_list_ids(
        mysqli $conn,
        string $folder,
        int $companyId,
        int $employeeId,
        string $sessionEmail,
        array $filters
    ): array {
        $built = webmail_build_list_query($folder, $companyId, $employeeId, $sessionEmail, $filters);
        $sql = 'SELECT id FROM emails WHERE ' . $built['where_sql'] . ' ORDER BY id DESC';
        $ids = [];
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return $ids;
        }
        mysqli_stmt_bind_param($stmt, $built['types'], ...$built['params']);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $ids[] = (int)$row['id'];
        }
        mysqli_stmt_close($stmt);

        return $ids;
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

if (!function_exists('webmail_signatures_table_exists')) {
    function webmail_signatures_table_exists(mysqli $conn): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $res = mysqli_query($conn, "SHOW TABLES LIKE 'webmail_signatures'");
        $cache = $res instanceof mysqli_result && mysqli_num_rows($res) > 0;
        if ($res instanceof mysqli_result) {
            mysqli_free_result($res);
        }

        return $cache;
    }
}

if (!function_exists('webmail_signatures_list')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function webmail_signatures_list(mysqli $conn, int $companyId, int $employeeId): array
    {
        if (!webmail_signatures_table_exists($conn) || $companyId <= 0 || $employeeId <= 0) {
            return [];
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, signature FROM webmail_signatures
             WHERE company_id = ? AND employee_id = ?
             ORDER BY name ASC, id ASC'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $employeeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result instanceof mysqli_result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('webmail_signature_get')) {
    /**
     * @return array<string, mixed>|null
     */
    function webmail_signature_get(mysqli $conn, int $id, int $companyId, int $employeeId): ?array
    {
        if ($id <= 0 || !webmail_signatures_table_exists($conn)) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, signature FROM webmail_signatures
             WHERE id = ? AND company_id = ? AND employee_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $id, $companyId, $employeeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result instanceof mysqli_result ? mysqli_fetch_assoc($result) : null;
        if ($result instanceof mysqli_result) {
            mysqli_free_result($result);
        }
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('webmail_signature_create')) {
    function webmail_signature_create(
        mysqli $conn,
        int $companyId,
        int $employeeId,
        string $name,
        string $signatureHtml
    ): int {
        if (!webmail_signatures_table_exists($conn) || $companyId <= 0 || $employeeId <= 0) {
            return 0;
        }
        $name = trim($name);
        $signatureHtml = webmail_render_details_html($signatureHtml);
        if ($name === '' || $signatureHtml === '') {
            return 0;
        }
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO webmail_signatures (company_id, employee_id, name, signature, active, created_by, updated_by)
             VALUES (?, ?, ?, ?, 1, ?, ?)'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'iissii', $companyId, $employeeId, $name, $signatureHtml, $employeeId, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        $newId = $ok ? (int)mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($stmt);

        return $newId;
    }
}

if (!function_exists('webmail_signature_update')) {
    function webmail_signature_update(
        mysqli $conn,
        int $id,
        int $companyId,
        int $employeeId,
        string $name,
        string $signatureHtml
    ): bool {
        if ($id <= 0 || !webmail_signatures_table_exists($conn)) {
            return false;
        }
        $name = trim($name);
        $signatureHtml = webmail_render_details_html($signatureHtml);
        if ($name === '' || $signatureHtml === '') {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE webmail_signatures SET name = ?, signature = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ? AND employee_id = ?'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ssiiii', $name, $signatureHtml, $employeeId, $id, $companyId, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('webmail_signature_delete')) {
    function webmail_signature_delete(mysqli $conn, int $id, int $companyId, int $employeeId): bool
    {
        if ($id <= 0 || !webmail_signatures_table_exists($conn)) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'DELETE FROM webmail_signatures WHERE id = ? AND company_id = ? AND employee_id = ?'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $id, $companyId, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        return $ok && $affected > 0;
    }
}

if (!function_exists('webmail_compose_merge_body_and_signature')) {
    function webmail_compose_merge_body_and_signature(string $bodyHtml, string $signatureHtml): string
    {
        $body = webmail_render_details_html($bodyHtml);
        $sig = webmail_render_details_html($signatureHtml);
        if ($body === '' && $sig === '') {
            return '';
        }
        if ($body === '') {
            return $sig;
        }
        if ($sig === '') {
            return $body;
        }

        return $body . '<br><br>' . $sig;
    }
}

if (!function_exists('webmail_signature_redirect_after_mutation')) {
    function webmail_signature_redirect_after_mutation(string $returnTo): void
    {
        $returnTo = trim($returnTo);
        if ($returnTo === 'compose') {
            header('Location: compose.php');
            exit;
        }
        header('Location: signatures.php');
        exit;
    }
}

if (!function_exists('webmail_render_tabs')) {
    function webmail_render_tabs(string $activeTab): void
    {
        $folderLabels = [
            'inbox' => 'Inbox',
            'starred' => 'Starred',
            'sent' => 'Sent',
            'archived' => 'Archived',
            'trash' => 'Trash',
        ];
        foreach (webmail_folders() as $tabFolder) {
            $class = 'webmail-tab' . ($activeTab === $tabFolder ? ' active' : '');
            echo '<a href="index.php?folder=' . sanitize($tabFolder) . '" class="' . $class . '">';
            echo sanitize($folderLabels[$tabFolder] ?? $tabFolder);
            echo '</a>';
        }
        $sigClass = 'webmail-tab' . ($activeTab === 'signatures' ? ' active' : '');
        echo '<a href="signatures.php" class="' . $sigClass . '">Signatures</a>';
        $composeClass = 'webmail-tab' . ($activeTab === 'compose' ? ' active' : '');
        echo '<a href="compose.php" class="' . $composeClass . '">Compose</a>';
    }
}

if (!function_exists('webmail_handle_signature_post')) {
    /**
     * Shared POST handlers for signatures.php (and compose when posting to signatures endpoint).
     *
     * @return array{handled: bool, errors: array<int, string>, notices: array<int, string>}
     */
    function webmail_handle_signature_post(mysqli $conn, int $companyId, int $employeeId): array
    {
        $errors = [];
        $notices = [];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['handled' => false, 'errors' => $errors, 'notices' => $notices];
        }
        if (!webmail_signatures_table_exists($conn)) {
            $errors[] = 'Signatures are not available (database table missing).';

            return ['handled' => true, 'errors' => $errors, 'notices' => $notices];
        }

        $returnTo = trim((string)($_POST['return_to'] ?? ''));

        if (isset($_POST['save_signature'])) {
            itm_require_post_csrf();
            $name = trim((string)($_POST['name'] ?? ''));
            $signatureHtml = (string)($_POST['signature_html'] ?? '');
            if ($name === '') {
                $errors[] = 'Signature name is required.';
            } elseif (webmail_render_details_html($signatureHtml) === '' && trim(strip_tags($signatureHtml)) === '') {
                $errors[] = 'Signature body is required.';
            } else {
                $newId = webmail_signature_create($conn, $companyId, $employeeId, $name, $signatureHtml);
                if ($newId <= 0) {
                    $errors[] = 'Could not save signature.';
                } else {
                    $_SESSION['webmail_notice'] = 'Signature saved.';
                    if ($returnTo === 'compose') {
                        header('Location: compose.php?signature_id=' . $newId);
                        exit;
                    }
                    webmail_signature_redirect_after_mutation($returnTo);
                }
            }

            return ['handled' => true, 'errors' => $errors, 'notices' => $notices];
        }

        if (isset($_POST['update_signature'])) {
            itm_require_post_csrf();
            $signatureId = (int)($_POST['signature_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $signatureHtml = (string)($_POST['signature_html'] ?? '');
            if ($signatureId <= 0) {
                $errors[] = 'Invalid signature.';
            } elseif ($name === '') {
                $errors[] = 'Signature name is required.';
            } elseif (webmail_render_details_html($signatureHtml) === '' && trim(strip_tags($signatureHtml)) === '') {
                $errors[] = 'Signature body is required.';
            } elseif (!webmail_signature_update($conn, $signatureId, $companyId, $employeeId, $name, $signatureHtml)) {
                $errors[] = 'Could not update signature.';
            } else {
                $_SESSION['webmail_notice'] = 'Signature updated.';
                webmail_signature_redirect_after_mutation($returnTo);
            }

            return ['handled' => true, 'errors' => $errors, 'notices' => $notices];
        }

        if (isset($_POST['delete_signature'])) {
            itm_require_post_csrf();
            $signatureId = (int)($_POST['signature_id'] ?? 0);
            if ($signatureId <= 0) {
                $errors[] = 'Invalid signature.';
            } elseif (!webmail_signature_delete($conn, $signatureId, $companyId, $employeeId)) {
                $errors[] = 'Could not delete signature.';
            } else {
                $_SESSION['webmail_notice'] = 'Signature deleted.';
                webmail_signature_redirect_after_mutation($returnTo);
            }

            return ['handled' => true, 'errors' => $errors, 'notices' => $notices];
        }

        return ['handled' => false, 'errors' => $errors, 'notices' => $notices];
    }
}
