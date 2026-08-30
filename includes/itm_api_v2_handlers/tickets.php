<?php
/**
 * API v2 ticket resource handlers.
 */

if (!function_exists('itm_api_v2_tickets_fk_label')) {
    function itm_api_v2_tickets_fk_label($conn, $companyId, $table, $id, $labelColumn = 'name')
    {
        if (!($conn instanceof mysqli) || !itm_is_safe_identifier($table) || !itm_is_safe_identifier($labelColumn)) {
            return null;
        }

        $id = (int)$id;
        $companyId = (int)$companyId;
        if ($id <= 0) {
            return null;
        }

        $sql = 'SELECT `' . $labelColumn . '` AS label FROM `' . $table . '` WHERE id = ? AND company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'ii', $id, $companyId);
        mysqli_stmt_execute($stmt);
        $row = function_exists('itm_mysqli_stmt_fetch_assoc') ? itm_mysqli_stmt_fetch_assoc($stmt) : null;
        if ($row === null && function_exists('mysqli_stmt_get_result')) {
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
        }
        mysqli_stmt_close($stmt);

        return is_array($row) ? (string)($row['label'] ?? '') : null;
    }
}

if (!function_exists('itm_api_v2_tickets_format_row')) {
    function itm_api_v2_tickets_format_row($conn, $companyId, array $row)
    {
        $statusId = (int)($row['status_id'] ?? 0);
        $priorityId = (int)($row['priority_id'] ?? 0);
        $categoryId = (int)($row['category_id'] ?? 0);
        $assigneeId = (int)($row['assigned_to_employee_id'] ?? 0);

        return [
            'id' => (int)($row['id'] ?? 0),
            'ticket_external_code' => (string)($row['ticket_external_code'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'category' => $categoryId > 0 ? itm_api_v2_tickets_fk_label($conn, $companyId, 'ticket_categories', $categoryId) : null,
            'status_id' => $statusId > 0 ? $statusId : null,
            'status' => $statusId > 0 ? itm_api_v2_tickets_fk_label($conn, $companyId, 'ticket_statuses', $statusId) : null,
            'priority_id' => $priorityId > 0 ? $priorityId : null,
            'priority' => $priorityId > 0 ? itm_api_v2_tickets_fk_label($conn, $companyId, 'ticket_priorities', $priorityId) : null,
            'assigned_to_employee_id' => $assigneeId > 0 ? $assigneeId : null,
            'due_date' => (string)($row['due_date'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('itm_api_v2_tickets_list')) {
    function itm_api_v2_tickets_list($conn, $companyId, array $query)
    {
        $companyId = (int)$companyId;
        $limit = isset($query['limit']) ? (int)$query['limit'] : 50;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 100) {
            $limit = 100;
        }

        $search = trim((string)($query['search'] ?? ''));
        $sql = 'SELECT id, ticket_external_code, title, description, category_id, status_id, priority_id,
                       assigned_to_employee_id, due_date, created_at, updated_at
                FROM tickets
                WHERE company_id = ? AND deleted_at IS NULL';
        $types = 'i';
        $params = [$companyId];

        if ($search !== '') {
            $sql .= ' AND (title LIKE ? OR description LIKE ? OR ticket_external_code LIKE ?)';
            $like = '%' . $search . '%';
            $types .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . (int)$limit;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            itm_api_v2_error(500, 'Unable to list tickets.');
        }

        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $rows = function_exists('itm_mysqli_stmt_fetch_all_assoc') ? itm_mysqli_stmt_fetch_all_assoc($stmt) : [];
        if ($rows === [] && function_exists('mysqli_stmt_get_result')) {
            $result = mysqli_stmt_get_result($stmt);
            if ($result instanceof mysqli_result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            }
        }
        mysqli_stmt_close($stmt);

        $items = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = itm_api_v2_tickets_format_row($conn, $companyId, $row);
            }
        }

        return ['items' => $items, 'count' => count($items)];
    }
}

if (!function_exists('itm_api_v2_tickets_get')) {
    function itm_api_v2_tickets_get($conn, $companyId, $ticketId)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $sql = 'SELECT id, ticket_external_code, title, description, category_id, status_id, priority_id,
                       assigned_to_employee_id, due_date, created_at, updated_at
                FROM tickets WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            itm_api_v2_error(500, 'Unable to load ticket.');
        }

        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $row = function_exists('itm_mysqli_stmt_fetch_assoc') ? itm_mysqli_stmt_fetch_assoc($stmt) : null;
        if ($row === null && function_exists('mysqli_stmt_get_result')) {
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
        }
        mysqli_stmt_close($stmt);

        if (!is_array($row)) {
            itm_api_v2_error(404, 'Ticket not found.');
        }

        return ['item' => itm_api_v2_tickets_format_row($conn, $companyId, $row)];
    }
}

if (!function_exists('itm_api_v2_tickets_create')) {
    function itm_api_v2_tickets_create($conn, $companyId, $employeeId, array $body)
    {
        $title = trim((string)($body['title'] ?? ''));
        if ($title === '') {
            itm_api_v2_error(422, 'title is required.');
        }

        $description = trim((string)($body['description'] ?? ''));
        $externalCode = trim((string)($body['ticket_external_code'] ?? ''));
        $categoryId = isset($body['category_id']) ? (int)$body['category_id'] : 0;
        $statusId = isset($body['status_id']) ? (int)$body['status_id'] : 0;
        $priorityId = isset($body['priority_id']) ? (int)$body['priority_id'] : 0;
        $assigneeId = isset($body['assigned_to_employee_id']) ? (int)$body['assigned_to_employee_id'] : 0;
        $dueDate = trim((string)($body['due_date'] ?? ''));

        $sql = 'INSERT INTO tickets
            (company_id, ticket_external_code, title, description, category_id, status_id, priority_id,
             created_by_employee_id, assigned_to_employee_id, due_date, active, created_by, updated_by)
            VALUES (?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?, NULLIF(?, 0), NULLIF(?, \'\'), 1, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            itm_api_v2_error(500, 'Unable to create ticket.');
        }

        mysqli_stmt_bind_param(
            $stmt,
            'isssiiiissii',
            $companyId,
            $externalCode,
            $title,
            $description,
            $categoryId,
            $statusId,
            $priorityId,
            $employeeId,
            $assigneeId,
            $dueDate,
            $employeeId,
            $employeeId
        );

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            itm_api_v2_error(500, 'Unable to create ticket.');
        }
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        if ($newId > 0 && function_exists('itm_ticket_sla_apply_on_create')) {
            require_once dirname(__DIR__) . '/itm_ticket_sla.php';
            itm_ticket_sla_apply_on_create($conn, $newId, $companyId, $priorityId);
        }

        return itm_api_v2_tickets_get($conn, $companyId, $newId)['item'];
    }
}

if (!function_exists('itm_api_v2_tickets_patch')) {
    function itm_api_v2_tickets_patch($conn, $companyId, $employeeId, $ticketId, array $body)
    {
        $existing = itm_api_v2_tickets_get($conn, $companyId, $ticketId);
        $item = is_array($existing['item'] ?? null) ? $existing['item'] : [];

        $title = array_key_exists('title', $body) ? trim((string)$body['title']) : (string)($item['title'] ?? '');
        if ($title === '') {
            itm_api_v2_error(422, 'title cannot be empty.');
        }

        $description = array_key_exists('description', $body) ? trim((string)$body['description']) : (string)($item['description'] ?? '');
        $externalCode = array_key_exists('ticket_external_code', $body) ? trim((string)$body['ticket_external_code']) : (string)($item['ticket_external_code'] ?? '');
        $categoryId = array_key_exists('category_id', $body) ? (int)$body['category_id'] : (int)($item['category_id'] ?? 0);
        $statusId = array_key_exists('status_id', $body) ? (int)$body['status_id'] : (int)($item['status_id'] ?? 0);
        $priorityId = array_key_exists('priority_id', $body) ? (int)$body['priority_id'] : (int)($item['priority_id'] ?? 0);
        $assigneeId = array_key_exists('assigned_to_employee_id', $body) ? (int)$body['assigned_to_employee_id'] : (int)($item['assigned_to_employee_id'] ?? 0);
        $dueDate = array_key_exists('due_date', $body) ? trim((string)$body['due_date']) : (string)($item['due_date'] ?? '');

        $sql = 'UPDATE tickets SET ticket_external_code = ?, title = ?, description = ?,
                category_id = NULLIF(?, 0), status_id = NULLIF(?, 0), priority_id = NULLIF(?, 0),
                assigned_to_employee_id = NULLIF(?, 0), due_date = NULLIF(?, \'\'), updated_by = ?
                WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            itm_api_v2_error(500, 'Unable to update ticket.');
        }

        mysqli_stmt_bind_param(
            $stmt,
            'sssiiiisiii',
            $externalCode,
            $title,
            $description,
            $categoryId,
            $statusId,
            $priorityId,
            $assigneeId,
            $dueDate,
            $employeeId,
            $ticketId,
            $companyId
        );

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            itm_api_v2_error(500, 'Unable to update ticket.');
        }
        mysqli_stmt_close($stmt);

        return itm_api_v2_tickets_get($conn, $companyId, (int)$ticketId)['item'];
    }
}
