<?php
/**
 * Tenant ticket module settings (surveys, SLA) — one row per company.
 */

if (!function_exists('itm_ticket_settings_load')) {
    function itm_ticket_settings_load(mysqli $conn, int $companyId): ?array
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM ticket_settings WHERE company_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_ticket_settings_ensure_company')) {
    /**
     * Load tenant settings or insert defaults (auto-issue survey off, email on, SLA on).
     */
    function itm_ticket_settings_ensure_company(mysqli $conn, int $companyId, int $employeeId = 0): ?array
    {
        $row = itm_ticket_settings_load($conn, $companyId);
        if ($row) {
            return $row;
        }
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return null;
        }
        $employeeId = max(0, (int)$employeeId);
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO ticket_settings (company_id, auto_issue_survey_on_close, survey_send_email_on_issue, sla_enabled_on_create, active, created_by, updated_by)
             VALUES (?, 0, 1, 1, 1, NULLIF(?, 0), NULLIF(?, 0))'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $employeeId, $employeeId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return itm_ticket_settings_load($conn, $companyId);
    }
}

if (!function_exists('itm_ticket_settings_save')) {
    /**
     * @param array<string,mixed> $fields auto_issue_survey_on_close, survey_send_email_on_issue, sla_enabled_on_create (0|1)
     */
    function itm_ticket_settings_save(mysqli $conn, int $companyId, int $employeeId, array $fields): bool
    {
        $companyId = (int)$companyId;
        $employeeId = (int)$employeeId;
        if ($companyId <= 0 || $employeeId <= 0) {
            return false;
        }
        $settings = itm_ticket_settings_ensure_company($conn, $companyId, $employeeId);
        if (!$settings) {
            return false;
        }
        $autoIssue = !empty($fields['auto_issue_survey_on_close']) ? 1 : 0;
        $sendEmail = !empty($fields['survey_send_email_on_issue']) ? 1 : 0;
        $slaEnabled = !empty($fields['sla_enabled_on_create']) ? 1 : 0;
        $settingsId = (int)($settings['id'] ?? 0);
        if ($settingsId <= 0) {
            return false;
        }
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE ticket_settings
             SET auto_issue_survey_on_close = ?, survey_send_email_on_issue = ?, sla_enabled_on_create = ?, updated_by = ?
             WHERE id = ? AND company_id = ? AND deleted_at IS NULL'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiiiii', $autoIssue, $sendEmail, $slaEnabled, $employeeId, $settingsId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return (bool)$ok;
    }
}

if (!function_exists('itm_ticket_settings_auto_issue_survey_on_close')) {
    function itm_ticket_settings_auto_issue_survey_on_close(mysqli $conn, int $companyId): bool
    {
        $row = itm_ticket_settings_ensure_company($conn, $companyId);
        if (!$row) {
            return false;
        }

        return (int)($row['auto_issue_survey_on_close'] ?? 0) === 1;
    }
}

if (!function_exists('itm_ticket_settings_survey_send_email_on_issue')) {
    function itm_ticket_settings_survey_send_email_on_issue(mysqli $conn, int $companyId): bool
    {
        $row = itm_ticket_settings_ensure_company($conn, $companyId);
        if (!$row) {
            return true;
        }

        return (int)($row['survey_send_email_on_issue'] ?? 1) === 1;
    }
}

if (!function_exists('itm_ticket_settings_sla_enabled_on_create')) {
    function itm_ticket_settings_sla_enabled_on_create(mysqli $conn, int $companyId): bool
    {
        $row = itm_ticket_settings_ensure_company($conn, $companyId);
        if (!$row) {
            return true;
        }

        return (int)($row['sla_enabled_on_create'] ?? 1) === 1;
    }
}
