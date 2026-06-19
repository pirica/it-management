<?php
/**
 * ITM email delivery — tenant SMTP configurations, send logging, and alert dispatch helpers.
 */

if (!function_exists('itm_smtp_encryption_key')) {
    /**
     * Why: SMTP passwords must be recoverable server-side without a user vault session.
     */
    function itm_smtp_encryption_key()
    {
        return hash('sha256', (defined('DB_PASS') ? DB_PASS : 'itmanagement') . 'itm_smtp_v1', true);
    }
}

if (!function_exists('itm_email_encrypt_password')) {
    function itm_email_encrypt_password($plainPassword)
    {
        if (!function_exists('itm_encrypt')) {
            return null;
        }
        $plain = (string)$plainPassword;
        if ($plain === '') {
            return '';
        }
        return itm_encrypt($plain, itm_smtp_encryption_key());
    }
}

if (!function_exists('itm_email_decrypt_password')) {
    function itm_email_decrypt_password($encryptedPassword)
    {
        if (!function_exists('itm_decrypt')) {
            return '';
        }
        $encrypted = (string)$encryptedPassword;
        if ($encrypted === '') {
            return '';
        }
        $decrypted = itm_decrypt($encrypted, itm_smtp_encryption_key());
        return is_string($decrypted) ? $decrypted : '';
    }
}

if (!function_exists('itm_email_resolve_company_id')) {
    function itm_email_resolve_company_id(mysqli $conn, $companyId = null)
    {
        $resolved = (int)$companyId;
        if ($resolved > 0) {
            return $resolved;
        }
        if (isset($_SESSION['company_id']) && (int)$_SESSION['company_id'] > 0) {
            return (int)$_SESSION['company_id'];
        }
        return 0;
    }
}

if (!function_exists('itm_email_get_default_smtp_config')) {
    function itm_email_get_default_smtp_config(mysqli $conn, $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return null;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, company_id, config_name, smtp_host, smtp_port, username, password_encrypted,
                    from_email, from_name, imap_port, pop3_port, pop3_tls_mode, pop3_require_secure_connection,
                    is_default, active
             FROM email_smtp_configurations
             WHERE company_id = ? AND active = 1 AND is_default = 1
             ORDER BY id ASC
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (is_array($row)) {
            return $row;
        }

        $fallbackStmt = mysqli_prepare(
            $conn,
            'SELECT id, company_id, config_name, smtp_host, smtp_port, username, password_encrypted,
                    from_email, from_name, imap_port, pop3_port, pop3_tls_mode, pop3_require_secure_connection,
                    is_default, active
             FROM email_smtp_configurations
             WHERE company_id = ? AND active = 1
             ORDER BY id ASC
             LIMIT 1'
        );
        if (!$fallbackStmt) {
            return null;
        }
        mysqli_stmt_bind_param($fallbackStmt, 'i', $companyId);
        mysqli_stmt_execute($fallbackStmt);
        $fallbackResult = mysqli_stmt_get_result($fallbackStmt);
        $fallbackRow = $fallbackResult ? mysqli_fetch_assoc($fallbackResult) : null;
        mysqli_stmt_close($fallbackStmt);

        return is_array($fallbackRow) ? $fallbackRow : null;
    }
}

if (!function_exists('itm_email_get_smtp_config_by_id')) {
    function itm_email_get_smtp_config_by_id(mysqli $conn, $configId, $companyId)
    {
        $configId = (int)$configId;
        $companyId = (int)$companyId;
        if ($configId <= 0 || $companyId <= 0) {
            return null;
        }

        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, company_id, config_name, smtp_host, smtp_port, username, password_encrypted,
                    from_email, from_name, imap_port, pop3_port, pop3_tls_mode, pop3_require_secure_connection,
                    is_default, active
             FROM email_smtp_configurations
             WHERE id = ? AND company_id = ? AND active = 1
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $configId, $companyId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_email_log_send')) {
    function itm_email_log_send(mysqli $conn, $companyId, $toEmail, $subject, $status, $details = null, $smtpConfigId = null)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return false;
        }

        $status = strtolower((string)$status) === 'failed' ? 'failed' : 'sent';
        $smtpConfigId = $smtpConfigId !== null ? (int)$smtpConfigId : null;

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO emails (company_id, smtp_config_id, to_email, subject, status, details, sent_at, active)
             VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, NOW(), 1)'
        );
        if (!$stmt) {
            return false;
        }
        $smtpParam = $smtpConfigId !== null ? (int)$smtpConfigId : 0;
        mysqli_stmt_bind_param(
            $stmt,
            'iissss',
            $companyId,
            $smtpParam,
            $toEmail,
            $subject,
            $status,
            $details
        );
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_email_smtp_read_response')) {
    function itm_email_smtp_read_response($socket)
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }
}

if (!function_exists('itm_email_smtp_expect')) {
    function itm_email_smtp_expect($socket, array $allowedCodes)
    {
        $response = itm_email_smtp_read_response($socket);
        $code = (int)substr(trim($response), 0, 3);
        return in_array($code, $allowedCodes, true) ? $response : false;
    }
}

if (!function_exists('itm_email_smtp_command')) {
    function itm_email_smtp_command($socket, $command, array $allowedCodes)
    {
        fwrite($socket, $command . "\r\n");
        return itm_email_smtp_expect($socket, $allowedCodes);
    }
}

if (!function_exists('itm_email_send_via_smtp')) {
    /**
     * @return array{ok:bool,error:string}
     */
    function itm_email_send_via_smtp(array $config, $toEmail, $subject, $htmlBody)
    {
        $host = trim((string)($config['smtp_host'] ?? ''));
        $port = (int)($config['smtp_port'] ?? 587);
        $username = trim((string)($config['username'] ?? ''));
        $password = (string)($config['password_plain'] ?? '');
        if ($password === '' && !empty($config['password_encrypted'])) {
            $password = itm_email_decrypt_password($config['password_encrypted']);
        }
        $fromEmail = trim((string)($config['from_email'] ?? ''));
        $fromName = trim((string)($config['from_name'] ?? ''));

        if ($host === '' || $fromEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Invalid SMTP configuration or recipient.'];
        }

        $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            return ['ok' => false, 'error' => 'SMTP connection failed: ' . $errstr];
        }
        stream_set_timeout($socket, 20);

        if (itm_email_smtp_expect($socket, [220]) === false) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP greeting failed.'];
        }

        $ehloHost = 'localhost';
        if (itm_email_smtp_command($socket, 'EHLO ' . $ehloHost, [250]) === false) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP EHLO failed.'];
        }

        if ($port !== 465) {
            if (itm_email_smtp_command($socket, 'STARTTLS', [220]) !== false) {
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($socket);
                    return ['ok' => false, 'error' => 'SMTP STARTTLS negotiation failed.'];
                }
                if (itm_email_smtp_command($socket, 'EHLO ' . $ehloHost, [250]) === false) {
                    fclose($socket);
                    return ['ok' => false, 'error' => 'SMTP EHLO after STARTTLS failed.'];
                }
            }
        }

        if ($username !== '') {
            if (itm_email_smtp_command($socket, 'AUTH LOGIN', [334]) === false) {
                fclose($socket);
                return ['ok' => false, 'error' => 'SMTP AUTH LOGIN rejected.'];
            }
            if (itm_email_smtp_command($socket, base64_encode($username), [334]) === false) {
                fclose($socket);
                return ['ok' => false, 'error' => 'SMTP username rejected.'];
            }
            if (itm_email_smtp_command($socket, base64_encode($password), [235]) === false) {
                fclose($socket);
                return ['ok' => false, 'error' => 'SMTP password rejected.'];
            }
        }

        if (itm_email_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]) === false) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP MAIL FROM rejected.'];
        }
        if (itm_email_smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]) === false) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP RCPT TO rejected.'];
        }
        if (itm_email_smtp_command($socket, 'DATA', [354]) === false) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP DATA rejected.'];
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode((string)$subject) . '?=';
        $fromHeader = $fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>' : $fromEmail;
        $headers = [
            'From: ' . $fromHeader,
            'To: <' . $toEmail . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'Date: ' . date('r'),
        ];
        $body = implode("\r\n", $headers) . "\r\n\r\n" . (string)$htmlBody;
        $body = str_replace(["\r\n.\r\n", "\n.\n", "\r.\r"], ["\r\n..\r\n", "\n..\n", "\r..\r"], $body);
        fwrite($socket, $body . "\r\n.\r\n");

        if (itm_email_smtp_expect($socket, [250]) === false) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP message body rejected.'];
        }

        itm_email_smtp_command($socket, 'QUIT', [221]);
        fclose($socket);

        return ['ok' => true, 'error' => ''];
    }
}

if (!function_exists('itm_email_send_via_resend')) {
    function itm_email_send_via_resend($toEmail, $subject, $htmlBody, $fromEmail = null)
    {
        $apiKey = getenv('RESEND_API_KEY');
        if (!$apiKey || trim($apiKey) === '') {
            return ['ok' => false, 'error' => 'RESEND_API_KEY is missing.'];
        }

        $from = $fromEmail && trim((string)$fromEmail) !== '' ? trim((string)$fromEmail) : 'onboarding@resend.dev';
        $url = 'https://api.resend.com/emails';
        $data = [
            'from' => $from,
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $htmlBody,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            return ['ok' => true, 'error' => ''];
        }
        return ['ok' => false, 'error' => 'Resend API returned HTTP ' . $httpCode];
    }
}

if (!function_exists('itm_email_is_wrapped_html')) {
    function itm_email_is_wrapped_html($htmlBody)
    {
        $trimmed = ltrim((string)$htmlBody);
        if ($trimmed === '') {
            return false;
        }

        return stripos($trimmed, '<!DOCTYPE') === 0 || stripos($trimmed, '<html') === 0;
    }
}

if (!function_exists('itm_email_build_transactional_html')) {
    /**
     * Why: Transactional mail should match public auth pages (login/forgot/register) for a consistent brand.
     */
    function itm_email_build_transactional_html($bodyHtml, array $options = [])
    {
        $appName = trim((string)($options['app_name'] ?? ''));
        if ($appName === '' && function_exists('itm_ui_config_app_name')) {
            $appName = (string)itm_ui_config_app_name();
        }
        if ($appName === '') {
            $appName = 'IT Management';
        }

        $subtitle = trim((string)($options['subtitle'] ?? ''));
        $buttonText = trim((string)($options['button_text'] ?? ''));
        $buttonUrl = trim((string)($options['button_url'] ?? ''));
        $footerText = trim((string)($options['footer_text'] ?? ''));
        $loginUrl = defined('BASE_URL') ? (string)BASE_URL . 'login.php' : '';

        $safeAppName = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');
        $safeSubtitle = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');
        $safeButtonText = htmlspecialchars($buttonText, ENT_QUOTES, 'UTF-8');
        $safeButtonUrl = htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8');
        $safeFooterText = htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8');
        $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

        $subtitleBlock = $subtitle !== ''
            ? '<p style="margin:0 0 24px;font-size:14px;line-height:1.5;color:#666666;text-align:center;">' . $safeSubtitle . '</p>'
            : '';

        $buttonBlock = '';
        if ($buttonText !== '' && $buttonUrl !== '') {
            $buttonBlock = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:24px 0 8px;">'
                . '<tr><td align="center">'
                . '<a href="' . $safeButtonUrl . '" style="display:inline-block;padding:12px 24px;background:#667eea;color:#ffffff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:600;">'
                . $safeButtonText
                . '</a></td></tr></table>';
        }

        $footerBlock = '';
        if ($footerText !== '') {
            $footerBlock = '<p style="margin:24px 0 0;font-size:12px;line-height:1.5;color:#666666;text-align:center;">' . $safeFooterText . '</p>';
        } elseif ($loginUrl !== '') {
            $footerBlock = '<p style="margin:24px 0 0;font-size:12px;line-height:1.5;color:#666666;text-align:center;">'
                . '<a href="' . $safeLoginUrl . '" style="color:#0969da;text-decoration:none;">Sign in to ' . $safeAppName . '</a></p>';
        }

        return '<!DOCTYPE html>'
            . '<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $safeAppName . '</title></head>'
            . '<body style="margin:0;padding:0;background:#667eea;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#667eea;min-height:100%;">'
            . '<tr><td align="center" style="padding:40px 20px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:500px;background:#ffffff;border-radius:12px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">'
            . '<tr><td style="padding:40px;">'
            . '<div style="text-align:center;margin-bottom:24px;">'
            . '<h1 style="margin:0 0 8px;font-size:28px;line-height:1.2;color:#0969da;">&#9881;&#65039; ' . $safeAppName . '</h1>'
            . $subtitleBlock
            . '</div>'
            . '<div style="font-size:14px;line-height:1.6;color:#24292f;">' . (string)$bodyHtml . '</div>'
            . $buttonBlock
            . $footerBlock
            . '</td></tr></table>'
            . '</td></tr></table>'
            . '</body></html>';
    }
}

if (!function_exists('itm_email_apply_transactional_template')) {
    function itm_email_apply_transactional_template($htmlBody, $subject, array $options = [])
    {
        if (array_key_exists('email_template', $options) && $options['email_template'] === false) {
            return (string)$htmlBody;
        }
        if (itm_email_is_wrapped_html($htmlBody)) {
            return (string)$htmlBody;
        }

        $templateOptions = [];
        if (isset($options['email_template']) && is_array($options['email_template'])) {
            $templateOptions = $options['email_template'];
        }
        if (!isset($templateOptions['subtitle']) && trim((string)$subject) !== '') {
            $templateOptions['subtitle'] = (string)$subject;
        }

        return itm_email_build_transactional_html((string)$htmlBody, $templateOptions);
    }
}

if (!function_exists('itm_send_email')) {
    /**
     * Sends a transactional email using the tenant default SMTP configuration.
     *
     * @param string $to Recipient email
     * @param string $subject Subject line
     * @param string $htmlBody HTML body
     * @param int|null $companyId Tenant scope (falls back to session company_id)
     * @param array $options Optional overrides: smtp_config_id, log (bool, default true), email_template (array|false)
     * @return bool
     */
    function itm_send_email($to, $subject, $htmlBody, $companyId = null, array $options = [])
    {
        global $conn;
        if (!$conn instanceof mysqli) {
            error_log('itm_send_email: database connection unavailable.');
            return false;
        }

        $htmlBody = itm_email_apply_transactional_template($htmlBody, $subject, $options);

        $resolvedCompanyId = itm_email_resolve_company_id($conn, $companyId);
        $shouldLog = !array_key_exists('log', $options) || (bool)$options['log'];
        $smtpConfig = null;
        $smtpConfigId = isset($options['smtp_config_id']) ? (int)$options['smtp_config_id'] : 0;

        if ($smtpConfigId > 0 && $resolvedCompanyId > 0) {
            $smtpConfig = itm_email_get_smtp_config_by_id($conn, $smtpConfigId, $resolvedCompanyId);
        } elseif ($resolvedCompanyId > 0) {
            $smtpConfig = itm_email_get_default_smtp_config($conn, $resolvedCompanyId);
        }

        $sendResult = ['ok' => false, 'error' => 'No mail transport configured.'];
        $usedConfigId = null;

        if (is_array($smtpConfig)) {
            $sendResult = itm_email_send_via_smtp($smtpConfig, $to, $subject, $htmlBody);
            $usedConfigId = (int)$smtpConfig['id'];
        } else {
            $sendResult = itm_email_send_via_resend($to, $subject, $htmlBody);
        }

        if ($shouldLog && $resolvedCompanyId > 0) {
            itm_email_log_send(
                $conn,
                $resolvedCompanyId,
                $to,
                $subject,
                $sendResult['ok'] ? 'sent' : 'failed',
                $sendResult['ok'] ? null : (string)$sendResult['error'],
                $usedConfigId
            );
        }

        if (!$sendResult['ok']) {
            error_log('itm_send_email failed: ' . $sendResult['error']);
        }

        return (bool)$sendResult['ok'];
    }
}

if (!function_exists('itm_email_alert_rule_catalog')) {
    function itm_email_alert_rule_catalog()
    {
        return [
            'warranty_expiry' => [
                'label' => 'Warranty Expiry Alerts',
                'description' => 'Equipment warranty expiry reminders.',
                'supports_days_before' => true,
            ],
            'license_expiry' => [
                'label' => 'License Expiry Alerts',
                'description' => 'Software license expiry reminders.',
                'supports_days_before' => true,
            ],
            'certificate_expiry' => [
                'label' => 'Certificate Expiry Alerts',
                'description' => 'Equipment certificate expiry reminders.',
                'supports_days_before' => true,
            ],
            'alerts_expiry' => [
                'label' => 'Alerts Expiry',
                'description' => 'System alert end-datetime reminders.',
                'supports_days_before' => true,
            ],
            'notes_reminder' => [
                'label' => 'Notes Reminders',
                'description' => 'Note reminder_at notifications.',
                'supports_days_before' => false,
            ],
            'todo_deadline' => [
                'label' => 'To-Do Deadline Reminders',
                'description' => 'To-do due_date and reminder_at notifications.',
                'supports_days_before' => false,
            ],
            'events_datetime' => [
                'label' => 'Events Start/End',
                'description' => 'Event start and end datetime reminders.',
                'supports_days_before' => false,
            ],
        ];
    }
}

if (!function_exists('itm_email_parse_notify_list')) {
    function itm_email_parse_notify_list($raw)
    {
        $parts = preg_split('/[\s,;]+/', trim((string)$raw));
        $emails = [];
        if (!is_array($parts)) {
            return $emails;
        }
        foreach ($parts as $part) {
            $candidate = trim((string)$part);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($candidate);
            }
        }
        return array_values(array_unique($emails));
    }
}

if (!function_exists('itm_email_ensure_alert_rules')) {
    function itm_email_ensure_alert_rules(mysqli $conn, $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return;
        }

        foreach (array_keys(itm_email_alert_rule_catalog()) as $slug) {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT IGNORE INTO email_alert_rules (company_id, rule_slug, enabled, days_before, notify_emails, active)
                 VALUES (?, ?, 0, 30, NULL, 1)'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $companyId, $slug);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }
}

if (!function_exists('itm_email_get_alert_rules')) {
    function itm_email_get_alert_rules(mysqli $conn, $companyId)
    {
        $companyId = (int)$companyId;
        itm_email_ensure_alert_rules($conn, $companyId);

        $rules = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, rule_slug, enabled, days_before, notify_emails, active
             FROM email_alert_rules
             WHERE company_id = ? AND active = 1
             ORDER BY id ASC'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rules[$row['rule_slug']] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        return $rules;
    }
}

if (!function_exists('itm_email_dispatch_to_rule')) {
    function itm_email_dispatch_to_rule(mysqli $conn, $companyId, $ruleSlug, $subject, $htmlBody)
    {
        $rules = itm_email_get_alert_rules($conn, $companyId);
        $rule = $rules[$ruleSlug] ?? null;
        if (!$rule || (int)($rule['enabled'] ?? 0) !== 1) {
            return 0;
        }

        $recipients = itm_email_parse_notify_list($rule['notify_emails'] ?? '');
        $sent = 0;
        foreach ($recipients as $recipient) {
            if (itm_send_email($recipient, $subject, $htmlBody, $companyId)) {
                $sent++;
            }
        }
        return $sent;
    }
}
