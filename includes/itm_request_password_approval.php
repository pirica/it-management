<?php
if (!function_exists('itm_request_password_approval_secret')) {
    /**
     * HMAC key for Request Password HR/HOD email approval links.
     *
     * Why: Secret must not live in source — set ITM_REQUEST_PASSWORD_APPROVAL_SECRET in project root .env.
     */
    function itm_request_password_approval_secret()
    {
        $env = getenv('ITM_REQUEST_PASSWORD_APPROVAL_SECRET');
        if ($env !== false && $env !== '') {
            return (string) $env;
        }

        return '';
    }
}

if (!function_exists('itm_request_password_approval_target_is_valid')) {
    /**
     * @param mixed $target
     */
    function itm_request_password_approval_target_is_valid($target)
    {
        return in_array((string) $target, ['hr', 'hod'], true);
    }
}

if (!function_exists('itm_request_password_approval_decision_is_valid')) {
    /**
     * @param mixed $decision
     */
    function itm_request_password_approval_decision_is_valid($decision)
    {
        return in_array((string) $decision, ['approve', 'decline'], true);
    }
}

if (!function_exists('itm_request_password_approver_type_description')) {
    /**
     * @param mixed $target
     */
    function itm_request_password_approver_type_description($target)
    {
        return ((string) $target === 'hr') ? 'HRD Approval' : 'HOD Approval';
    }
}

if (!function_exists('itm_request_password_approval_token_payload')) {
    /**
     * Canonical HMAC message for approval links (includes designated approver employee id).
     *
     * @param mixed $recordId
     * @param mixed $target
     * @param mixed $decision
     * @param mixed $approverEmployeeId
     */
    function itm_request_password_approval_token_payload($recordId, $target, $decision, $approverEmployeeId)
    {
        return (int) $recordId . '|' . (string) $target . '|' . (string) $decision . '|' . (int) $approverEmployeeId;
    }
}

if (!function_exists('itm_request_password_approval_sign_token')) {
    /**
     * @param mixed $recordId
     * @param mixed $target
     * @param mixed $decision
     * @param mixed $approverEmployeeId
     */
    function itm_request_password_approval_sign_token($recordId, $target, $decision, $approverEmployeeId)
    {
        $secret = itm_request_password_approval_secret();
        if ($secret === '') {
            return '';
        }

        return hash_hmac(
            'sha256',
            itm_request_password_approval_token_payload($recordId, $target, $decision, $approverEmployeeId),
            $secret
        );
    }
}

if (!function_exists('itm_request_password_approval_verify_token')) {
    /**
     * @param mixed $recordId
     * @param mixed $target
     * @param mixed $decision
     * @param mixed $approverEmployeeId
     * @param mixed $token
     */
    function itm_request_password_approval_verify_token($recordId, $target, $decision, $approverEmployeeId, $token)
    {
        $secret = itm_request_password_approval_secret();
        if ($secret === '' || (string) $token === '') {
            return false;
        }
        if (
            !itm_request_password_approval_target_is_valid($target)
            || !itm_request_password_approval_decision_is_valid($decision)
            || (int) $recordId <= 0
            || (int) $approverEmployeeId <= 0
        ) {
            return false;
        }

        $expected = itm_request_password_approval_sign_token($recordId, $target, $decision, $approverEmployeeId);

        return $expected !== '' && hash_equals($expected, (string) $token);
    }
}

if (!function_exists('itm_request_password_resolve_approver_employee_id')) {
    /**
     * Tenant HR or HOD approver employee id from approvers / approver_type.
     *
     * @param mysqli $conn
     * @param int $companyId
     * @param mixed $target hr|hod
     */
    function itm_request_password_resolve_approver_employee_id(mysqli $conn, $companyId, $target)
    {
        if (!itm_request_password_approval_target_is_valid($target) || (int) $companyId <= 0) {
            return 0;
        }

        $approverTypeDesc = itm_request_password_approver_type_description($target);
        $sql = 'SELECT a.employee_id FROM approvers a
                JOIN approver_type at ON a.approver_type_id = at.id
                WHERE a.company_id = ? AND at.approver_type_description = ?
                LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $approverTypeDesc);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return (int) ($row['employee_id'] ?? 0);
    }
}

if (!function_exists('itm_request_password_approval_employee_may_act')) {
    /**
     * Session employee must match the designated approver bound in the email token.
     *
     * @param mysqli $conn
     * @param int $companyId
     * @param int $sessionEmployeeId
     * @param mixed $target
     * @param int $approverEmployeeId
     */
    function itm_request_password_approval_employee_may_act(
        mysqli $conn,
        $companyId,
        $sessionEmployeeId,
        $target,
        $approverEmployeeId
    ) {
        $sessionEmployeeId = (int) $sessionEmployeeId;
        $approverEmployeeId = (int) $approverEmployeeId;
        if ($sessionEmployeeId <= 0 || $approverEmployeeId <= 0) {
            return false;
        }
        if ($sessionEmployeeId !== $approverEmployeeId) {
            return false;
        }

        $designatedId = itm_request_password_resolve_approver_employee_id($conn, (int) $companyId, $target);

        return $designatedId > 0 && $designatedId === $sessionEmployeeId;
    }
}

if (!function_exists('itm_request_password_approval_render_message_page')) {
    /**
     * Minimal HTML response for approval link errors and success.
     *
     * @param string $title
     * @param string $bodyHtml
     */
    function itm_request_password_approval_render_message_page($title, $bodyHtml)
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>'
            . htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8')
            . '</title></head><body>'
            . $bodyHtml
            . '</body></html>';
    }
}

if (!function_exists('itm_request_password_approval_render_confirm_form')) {
    /**
     * GET landing page: validate link, show POST confirmation with CSRF (no state change on GET).
     *
     * @param array<string,mixed> $params
     */
    function itm_request_password_approval_render_confirm_form(array $params)
    {
        $recordId = (int) ($params['record_id'] ?? 0);
        $target = (string) ($params['target'] ?? '');
        $decision = (string) ($params['decision'] ?? '');
        $approverEmployeeId = (int) ($params['approver_employee_id'] ?? 0);
        $token = (string) ($params['token'] ?? '');
        $applicantName = (string) ($params['applicant_name'] ?? '');
        $application = (string) ($params['application'] ?? '');
        $csrfToken = (string) ($params['csrf_token'] ?? '');
        $roleLabel = itm_request_password_approver_type_description($target);
        $decisionLabel = ($decision === 'approve') ? 'Authorize' : 'Decline';
        $statusPreview = ($decision === 'approve') ? 'Approved' : 'Declined';

        $body = '<h2>Password change request — ' . htmlspecialchars($decisionLabel, ENT_QUOTES, 'UTF-8') . '</h2>';
        if ($applicantName !== '') {
            $body .= '<p>Applicant: <strong>' . htmlspecialchars($applicantName, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        }
        if ($application !== '') {
            $body .= '<p>Application: <strong>' . htmlspecialchars($application, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        }
        $body .= '<p>Role: <strong>' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . '</strong></p>';
        $body .= '<p>Confirm to mark this request as <strong>' . htmlspecialchars($statusPreview, ENT_QUOTES, 'UTF-8') . '</strong>.</p>';
        $body .= '<form method="POST" action="index.php">';
        $body .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';
        $body .= '<input type="hidden" name="approval_submit" value="1">';
        $body .= '<input type="hidden" name="id" value="' . (int) $recordId . '">';
        $body .= '<input type="hidden" name="target" value="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '">';
        $body .= '<input type="hidden" name="decision" value="' . htmlspecialchars($decision, ENT_QUOTES, 'UTF-8') . '">';
        $body .= '<input type="hidden" name="approver" value="' . (int) $approverEmployeeId . '">';
        $body .= '<input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        $body .= '<button type="submit" class="btn btn-primary" title="Confirm">' . ($decision === 'approve' ? '✅' : '❌') . '</button>';
        $body .= '</form>';

        itm_request_password_approval_render_message_page('Confirm approval', $body);
    }
}
