<?php
/**
 * Detach inbound references before admin employee delete.
 *
 * Why: itm_can_delete_record() blocks on any inbound FK row even when MySQL would
 * SET NULL or CASCADE; admins must be able to delete employees after cleaning
 * login, audit, access, and other safe child links.
 */

if (!function_exists('itm_employees_delete_run_prepared')) {
    /**
     * @param array<int, mixed> $params
     */
    function itm_employees_delete_run_prepared(mysqli $conn, string $sql, string $types, array $params): ?string
    {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 'Delete failed: ' . mysqli_error($conn);
        }

        if ($types !== '' && $params !== []) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($conn);
            mysqli_stmt_close($stmt);
            return 'Delete failed: ' . $error;
        }

        mysqli_stmt_close($stmt);
        return null;
    }
}

if (!function_exists('itm_employees_resolve_ticket_creator_fallback_id')) {
    /**
     * Another employee in the same company for tickets.created_by_employee_id (NOT NULL).
     */
    function itm_employees_resolve_ticket_creator_fallback_id(mysqli $conn, int $companyId, int $excludeEmployeeId): int
    {
        if ($companyId <= 0 || $excludeEmployeeId <= 0) {
            return 0;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM employees WHERE company_id = ? AND id <> ? ORDER BY is_hidden DESC, id ASC LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $excludeEmployeeId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return (int)($row['id'] ?? 0);
    }
}

if (!function_exists('itm_employees_detach_delete_dependencies')) {
    /**
     * Remove or null inbound links that block admin employee delete.
     *
     * @return string|null Error message, or null when dependencies were detached.
     */
    function itm_employees_detach_delete_dependencies(mysqli $conn, int $employeeId, int $companyId): ?string
    {
        if ($employeeId <= 0) {
            return 'Invalid employee ID.';
        }

        $detachSteps = [
            ['DELETE FROM password_entries WHERE employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM password_folders WHERE employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM bookmarks WHERE employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM bookmark_folders WHERE employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM private_contacts WHERE employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM todo_categories WHERE cat_from_employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM employee_system_access WHERE employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM employee_companies WHERE employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM attempts WHERE employee_id = ?', 'i', [$employeeId]],
            ['UPDATE audit_logs SET employee_id = NULL WHERE employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM employee_sidebar_preferences WHERE employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM ui_configuration WHERE employee_id = ?', 'i', [$employeeId]],
            ['UPDATE employees SET reports_to = NULL WHERE reports_to = ?', 'i', [$employeeId]],
            ['DELETE FROM approvers WHERE employee_id = ?', 'i', [$employeeId]],
            ['DELETE FROM employee_assignment_history WHERE employee_id = ?', 'i', [$employeeId]],
            ['UPDATE employee_companies SET granted_by_employee_id = NULL WHERE granted_by_employee_id = ?', 'i', [$employeeId]],
            ['UPDATE employee_assignment_history SET assigned_by_employee_id = NULL WHERE assigned_by_employee_id = ?', 'i', [$employeeId]],
            ['UPDATE employee_assignment_history SET received_by_employee_id = NULL WHERE received_by_employee_id = ?', 'i', [$employeeId]],
        ];

        if ($companyId > 0) {
            $detachSteps = array_merge($detachSteps, [
                ['UPDATE floor_plans SET created_by_employee_id = NULL WHERE created_by_employee_id = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
                ['UPDATE equipment SET assigned_to_employee_id = NULL WHERE assigned_to_employee_id = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
                ['UPDATE inventory_items SET last_employee_id = NULL WHERE last_employee_id = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
                ['UPDATE patches_updates SET created_by = NULL WHERE created_by = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
                ['UPDATE alerts SET assigned_to_employee_id = NULL WHERE assigned_to_employee_id = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
                ['UPDATE alerts SET created_by_employee_id = NULL WHERE created_by_employee_id = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
                ['UPDATE events SET assigned_to_employee_id = NULL WHERE assigned_to_employee_id = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
                ['UPDATE explorer SET employee_id = NULL WHERE employee_id = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
                ['UPDATE employee_onboarding_requests SET employee_id = NULL WHERE employee_id = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
                ['UPDATE registration_invitations SET invited_by_employee_id = NULL WHERE invited_by_employee_id = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
                ['UPDATE tickets SET assigned_to_employee_id = NULL WHERE assigned_to_employee_id = ? AND company_id = ?', 'ii', [$employeeId, $companyId]],
            ]);
        }

        foreach ($detachSteps as $step) {
            $error = itm_employees_delete_run_prepared($conn, $step[0], $step[1], $step[2]);
            if ($error !== null) {
                return $error;
            }
        }

        if ($companyId > 0) {
            $fallbackCreatorId = itm_employees_resolve_ticket_creator_fallback_id($conn, $companyId, $employeeId);
            if ($fallbackCreatorId > 0) {
                $error = itm_employees_delete_run_prepared(
                    $conn,
                    'UPDATE tickets SET created_by_employee_id = ? WHERE created_by_employee_id = ? AND company_id = ?',
                    'iii',
                    [$fallbackCreatorId, $employeeId, $companyId]
                );
                if ($error !== null) {
                    return $error;
                }
            } else {
                $error = itm_employees_delete_run_prepared(
                    $conn,
                    'DELETE FROM tickets WHERE created_by_employee_id = ? AND company_id = ?',
                    'ii',
                    [$employeeId, $companyId]
                );
                if ($error !== null) {
                    return $error;
                }
            }
        }

        return null;
    }
}
