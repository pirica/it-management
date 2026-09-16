<?php
/**
 * Registration invitation email helpers for admin create/edit saves.
 */

if (!function_exists('itm_registration_invitation_build_register_url')) {
    function itm_registration_invitation_build_register_url(string $email, string $invitationCode): string
    {
        $base = defined('BASE_URL') ? (string)BASE_URL : '/';
        $query = http_build_query(
            [
                'email' => $email,
                'invite' => $invitationCode,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return rtrim($base, '/') . '/register.php?' . $query;
    }
}

if (!function_exists('itm_registration_invitation_should_send_email')) {
    function itm_registration_invitation_should_send_email(array $invitationRow): bool
    {
        if ((int)($invitationRow['active'] ?? 0) !== 1) {
            return false;
        }

        $email = trim((string)($invitationRow['email'] ?? ''));
        $invitationCode = trim((string)($invitationRow['invitation_code'] ?? ''));
        if ($email === '' || $invitationCode === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $acceptedAt = trim((string)($invitationRow['accepted_at'] ?? ''));
        if ($acceptedAt !== '' && strtoupper($acceptedAt) !== 'NULL') {
            return false;
        }

        return true;
    }
}

if (!function_exists('itm_registration_invitation_send_email')) {
    /**
     * Sends the invitation code and registration link to the invitee.
     */
    function itm_registration_invitation_send_email(
        mysqli $conn,
        int $companyId,
        string $recipientEmail,
        string $invitationCode,
        ?string $appName = null
    ): bool {
        if (!function_exists('itm_send_email')) {
            return false;
        }

        $recipientEmail = trim($recipientEmail);
        $invitationCode = trim($invitationCode);
        if ($recipientEmail === '' || $invitationCode === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $resolvedCompanyId = (int)$companyId;
        if ($resolvedCompanyId <= 0 && function_exists('itm_email_resolve_company_id')) {
            $resolvedCompanyId = itm_email_resolve_company_id($conn, null);
        }

        $appLabel = trim((string)$appName);
        if ($appLabel === '') {
            $appLabel = function_exists('itm_ui_config_app_name') ? itm_ui_config_app_name() : 'IT Management';
        }

        $registerUrl = itm_registration_invitation_build_register_url($recipientEmail, $invitationCode);
        $safeApp = htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($invitationCode, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8');

        $htmlBody = '<p>You have been invited to register for <strong>' . $safeApp . '</strong>.</p>'
            . '<p>Your invitation code is:</p>'
            . '<p style="font-size:18px;font-weight:700;letter-spacing:0.5px;">' . $safeCode . '</p>'
            . '<p>Use the button below to open the registration page with your email and invitation code pre-filled.</p>'
            . '<p style="word-break:break-all;color:#57606a;font-size:13px;">Or copy and paste this link:<br>'
            . '<a href="' . $safeUrl . '">' . $safeUrl . '</a></p>';

        return itm_send_email(
            $recipientEmail,
            'Registration invitation — ' . $appLabel,
            $htmlBody,
            $resolvedCompanyId > 0 ? $resolvedCompanyId : null,
            [
                'email_template' => [
                    'subtitle' => 'Your registration invitation',
                    'button_text' => 'Register now',
                    'button_url' => $registerUrl,
                ],
            ]
        );
    }
}

if (!function_exists('itm_registration_invitation_notify_after_save')) {
    /**
     * @return string|null Success or warning message for session flash, or null when no email attempted.
     */
    function itm_registration_invitation_notify_after_save(
        mysqli $conn,
        int $companyId,
        array $invitationRow,
        ?string $appName = null
    ): ?string {
        if (!itm_registration_invitation_should_send_email($invitationRow)) {
            return null;
        }

        $sent = itm_registration_invitation_send_email(
            $conn,
            $companyId,
            (string)($invitationRow['email'] ?? ''),
            (string)($invitationRow['invitation_code'] ?? ''),
            $appName
        );

        if ($sent) {
            return 'Invitation saved and registration email sent.';
        }

        return 'Invitation saved, but the registration email could not be sent. Check Email Management SMTP settings.';
    }
}
