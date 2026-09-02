<?php
/**
 * Ticket create/edit employee FK defaults — tenant context, not raw session employee_id.
 */

if (!function_exists('tickets_resolve_context_employee_id')) {
    function tickets_resolve_context_employee_id(mysqli $conn, int $companyId): int
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return 0;
        }

        if (function_exists('itm_company_session_login_employee_id')
            && function_exists('itm_resolve_company_context_employee_id')) {
            $loginEmployeeId = itm_company_session_login_employee_id();
            $contextEmployeeId = itm_resolve_company_context_employee_id($conn, $loginEmployeeId, $companyId);
            if ($contextEmployeeId > 0
                && function_exists('itm_user_label_by_id_for_company')
                && itm_user_label_by_id_for_company($conn, $companyId, $contextEmployeeId) !== '') {
                return $contextEmployeeId;
            }
        }

        if (function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
            return (int)itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);
        }

        return 0;
    }
}

if (!function_exists('tickets_default_created_by_employee_id')) {
    function tickets_default_created_by_employee_id(mysqli $conn, int $companyId): int
    {
        return tickets_resolve_context_employee_id($conn, $companyId);
    }
}

if (!function_exists('tickets_resolve_created_by_employee_id')) {
    function tickets_resolve_created_by_employee_id(mysqli $conn, int $companyId): int
    {
        $fromPost = (int)($_POST['created_by_employee_id'] ?? 0);
        if ($fromPost > 0) {
            return $fromPost;
        }

        return tickets_default_created_by_employee_id($conn, $companyId);
    }
}
