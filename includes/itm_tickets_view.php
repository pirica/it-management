<?php
/**
 * Ticket master rollup view — cross-company incident read (not session company scoped).
 */

if (!function_exists('itm_ticket_master_view_page_href')) {
    /**
     * Relative href for master ticket incident links (requires ticket company_id).
     */
    function itm_ticket_master_view_page_href($ticketId, $companyId, $masterTicketId = 0)
    {
        $ticketId = (int)$ticketId;
        $companyId = (int)$companyId;
        $masterTicketId = (int)$masterTicketId;
        if ($ticketId <= 0 || $companyId <= 0) {
            return 'master_view.php';
        }
        $qs = [
            'id' => $ticketId,
            'company_id' => $companyId,
        ];
        if ($masterTicketId > 0) {
            $qs['master_ticket_id'] = $masterTicketId;
        }

        return 'master_view.php?' . http_build_query($qs);
    }
}

if (!function_exists('itm_ticket_load_master_view_row')) {
    /**
     * Load ticket for master_view.php using explicit company_id (not session tenant).
     *
     * @return array{item: ?array, company_id: int, company_name: string, error: string}
     */
    function itm_ticket_load_master_view_row($conn, $ticketId, $companyId, $employeeId)
    {
        $result = ['item' => null, 'company_id' => 0, 'company_name' => '', 'error' => ''];
        if (!$conn instanceof mysqli) {
            $result['error'] = 'Database unavailable.';
            return $result;
        }
        $ticketId = (int)$ticketId;
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if ($ticketId <= 0 || $companyId <= 0) {
            $result['error'] = 'Ticket id and company id are required.';
            return $result;
        }
        if ($employeeId <= 0) {
            $result['error'] = 'Sign in required.';
            return $result;
        }
        if (!function_exists('itm_employee_has_company_access')) {
            require_once ROOT_PATH . 'includes/itm_company_session.php';
        }
        if (!itm_employee_has_company_access($conn, $employeeId, $companyId)) {
            $result['error'] = 'You do not have access to this company.';
            return $result;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT t.*, tc.name AS category_name, ts.name AS status_name, ts.color AS status_color,
                tp.name AS priority_name, tp.color AS priority_color,
                assigned_user.username AS assigned_to_username, created_user.username AS created_by_username,
                e.name AS equipment_name, c.company AS company_name
            FROM tickets t
            INNER JOIN companies c ON c.id = t.company_id
            LEFT JOIN ticket_categories tc ON tc.id = t.category_id
            LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
            LEFT JOIN ticket_priorities tp ON tp.id = t.priority_id
            LEFT JOIN employees assigned_user ON assigned_user.id = t.assigned_to_employee_id
            LEFT JOIN employees created_user ON created_user.id = t.created_by_employee_id
            LEFT JOIN equipment e ON e.id = t.equipment_id
            WHERE t.id = ? AND t.company_id = ? AND t.deleted_at IS NULL
            LIMIT 1'
        );
        if (!$stmt) {
            $result['error'] = 'Could not load ticket.';
            return $result;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $query = mysqli_stmt_get_result($stmt);
        $row = ($query && mysqli_num_rows($query) === 1) ? mysqli_fetch_assoc($query) : null;
        mysqli_stmt_close($stmt);
        if (!is_array($row)) {
            $result['error'] = 'Record not found.';
            return $result;
        }

        $result['item'] = $row;
        $result['company_id'] = $companyId;
        $result['company_name'] = (string)($row['company_name'] ?? '');

        return $result;
    }
}
