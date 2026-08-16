<?php
/**
 * Inbound IMAP email → ticket creation and threaded replies.
 * Tenant routing: companies.email is the expected To address per company.
 */

if (!function_exists('itm_inbound_email_imap_available')) {
    function itm_inbound_email_imap_available()
    {
        return function_exists('imap_open');
    }
}

if (!function_exists('itm_inbound_email_normalize_message_id')) {
    function itm_inbound_email_normalize_message_id($messageId)
    {
        $messageId = trim((string)$messageId);
        $messageId = trim($messageId, '<>');
        return strtolower($messageId);
    }
}

if (!function_exists('itm_inbound_email_strip_body')) {
    function itm_inbound_email_strip_body($body)
    {
        $body = (string)$body;
        if ($body === '') {
            return '';
        }
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = strip_tags($body);
        $body = preg_replace('/\r\n|\r/', "\n", $body);
        $body = preg_replace('/[ \t]+/', ' ', $body);
        $body = preg_replace("/\n{3,}/", "\n\n", $body);

        return trim((string)$body);
    }
}

if (!function_exists('itm_inbound_email_extract_address')) {
    function itm_inbound_email_extract_address($raw)
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/<([^>]+)>/', $raw, $m)) {
            return strtolower(trim($m[1]));
        }
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return strtolower($raw);
        }

        return '';
    }
}

if (!function_exists('itm_inbound_email_build_mailbox_string')) {
    /**
     * @param array<string,mixed> $profile
     */
    function itm_inbound_email_build_mailbox_string(array $profile)
    {
        $host = trim((string)($profile['imap_host'] ?? ''));
        if ($host === '') {
            $host = trim((string)($profile['smtp_host'] ?? ''));
        }
        if ($host === '') {
            return '';
        }
        $port = (int)($profile['imap_port'] ?? 143);
        if ($port <= 0) {
            $port = 143;
        }
        $secure = (int)($profile['pop3_require_secure_connection'] ?? 0) === 1;
        $flags = '/imap';
        if ($port === 993 || $secure) {
            $flags .= '/ssl';
        } elseif ($port === 143) {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }

        return '{' . $host . ':' . $port . $flags . '}INBOX';
    }
}

if (!function_exists('itm_inbound_email_fetch_body_part')) {
    /**
     * @param object|null $structure
     */
    function itm_inbound_email_fetch_body_part($imap, $msgno, $structure, $partNo = '')
    {
        if (!$imap || !$structure) {
            return '';
        }
        $body = '';
        if (isset($structure->parts) && is_array($structure->parts) && count($structure->parts) > 0) {
            foreach ($structure->parts as $index => $sub) {
                $prefix = $partNo === '' ? (string)($index + 1) : $partNo . '.' . ($index + 1);
                $chunk = itm_inbound_email_fetch_body_part($imap, $msgno, $sub, $prefix);
                if ($chunk !== '') {
                    $body = $chunk;
                    break;
                }
            }
        } else {
            $type = (int)($structure->type ?? 0);
            $subtype = strtolower((string)($structure->subtype ?? ''));
            if ($type === 0 && $subtype === 'plain') {
                $section = $partNo === '' ? '1' : $partNo;
                $raw = imap_fetchbody($imap, (string)$msgno, $section);
                if ($raw === false) {
                    return '';
                }
                $encoding = (int)($structure->encoding ?? 0);
                if ($encoding === 3) {
                    $raw = base64_decode($raw, true);
                } elseif ($encoding === 4) {
                    $raw = quoted_printable_decode($raw);
                }
                $body = (string)$raw;
            }
        }

        return $body;
    }
}

if (!function_exists('itm_inbound_email_parse_ticket_ref')) {
    /**
     * @return array{ticket_id:int,ticket_external_code:string}
     */
    function itm_inbound_email_parse_ticket_ref($subject, $body = '')
    {
        $haystack = trim((string)$subject) . "\n" . trim((string)$body);
        $ticketId = 0;
        $externalCode = '';

        if (preg_match('/\b(TCK-\d+)\b/i', $haystack, $m)) {
            $externalCode = strtoupper($m[1]);
        }
        if ($externalCode === '' && preg_match('/\[#(\d+)\]/', $haystack, $m)) {
            $ticketId = (int)$m[1];
        }

        return [
            'ticket_id' => $ticketId,
            'ticket_external_code' => $externalCode,
        ];
    }
}

if (!function_exists('itm_inbound_email_resolve_ticket_id')) {
    function itm_inbound_email_resolve_ticket_id($conn, $companyId, array $ref)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return 0;
        }
        $ticketId = (int)($ref['ticket_id'] ?? 0);
        $externalCode = trim((string)($ref['ticket_external_code'] ?? ''));
        if ($ticketId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT id FROM tickets WHERE id = ? AND company_id = ? AND deleted_at IS NULL AND active = 1 LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if ($row) {
                    return (int)$row['id'];
                }
            }
        }
        if ($externalCode !== '') {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT id FROM tickets WHERE company_id = ? AND ticket_external_code = ? AND deleted_at IS NULL AND active = 1 LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $companyId, $externalCode);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if ($row) {
                    return (int)$row['id'];
                }
            }
        }

        return 0;
    }
}

if (!function_exists('itm_inbound_email_resolve_requester')) {
    /**
     * @return array{employee_id:int,is_fallback:bool,from_email:string}
     */
    function itm_inbound_email_resolve_requester($conn, $companyId, $fromEmail)
    {
        $companyId = (int)$companyId;
        $fromEmail = itm_inbound_email_extract_address($fromEmail);
        if ($companyId <= 0 || $fromEmail === '') {
            return ['employee_id' => 0, 'is_fallback' => true, 'from_email' => $fromEmail];
        }

        if (!function_exists('itm_employee_resolve_id_by_email')) {
            require_once ROOT_PATH . 'includes/itm_employee_notifications.php';
        }
        $employeeId = itm_employee_resolve_id_by_email($conn, $companyId, $fromEmail);
        if ($employeeId > 0) {
            return ['employee_id' => $employeeId, 'is_fallback' => false, 'from_email' => $fromEmail];
        }

        if (!function_exists('itm_seed_resolve_tenant_seed_admin_employee_id')) {
            require_once ROOT_PATH . 'includes/itm_sample_data_seed.php';
        }
        $fallbackId = itm_seed_resolve_tenant_seed_admin_employee_id($conn, $companyId);

        return [
            'employee_id' => $fallbackId,
            'is_fallback' => true,
            'from_email' => $fromEmail,
        ];
    }
}

if (!function_exists('itm_inbound_email_to_matches_company')) {
    function itm_inbound_email_to_matches_company($toAddresses, $ccAddresses, $companyEmail)
    {
        $companyEmail = strtolower(trim((string)$companyEmail));
        if ($companyEmail === '') {
            return true;
        }
        $all = array_merge(
            is_array($toAddresses) ? $toAddresses : [],
            is_array($ccAddresses) ? $ccAddresses : []
        );
        foreach ($all as $addr) {
            if (strtolower(trim((string)$addr)) === $companyEmail) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_inbound_email_is_processed')) {
    function itm_inbound_email_is_processed($conn, $companyId, $messageId)
    {
        $companyId = (int)$companyId;
        $messageId = itm_inbound_email_normalize_message_id($messageId);
        if ($companyId <= 0 || $messageId === '') {
            return true;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id FROM ticket_inbound_email_messages WHERE company_id = ? AND message_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return true;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $messageId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $exists = $res && mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        return (bool)$exists;
    }
}

if (!function_exists('itm_inbound_email_record_processed')) {
    function itm_inbound_email_record_processed($conn, $companyId, $messageId, $ticketId, $emailLogId, $fromEmail, $subject)
    {
        $companyId = (int)$companyId;
        $messageId = itm_inbound_email_normalize_message_id($messageId);
        $ticketIdVal = $ticketId > 0 ? (int)$ticketId : 0;
        $emailLogIdVal = $emailLogId > 0 ? (int)$emailLogId : 0;

        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO ticket_inbound_email_messages
             (company_id, message_id, ticket_id, email_log_id, from_email, subject, processed_at, active)
             VALUES (?, ?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, NOW(), 1)'
        );
        if (!$stmt) {
            return false;
        }
        $fromEmail = trim((string)$fromEmail);
        $subject = trim((string)$subject);
        if ($companyId <= 0 || $messageId === '') {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'isiiss', $companyId, $messageId, $ticketIdVal, $emailLogIdVal, $fromEmail, $subject);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }
}

if (!function_exists('itm_inbound_email_log_received')) {
    function itm_inbound_email_log_received($conn, $companyId, $smtpConfigId, $toEmail, $fromEmail, $ccEmail, $subject, $details)
    {
        if (!function_exists('itm_email_log_send')) {
            require_once ROOT_PATH . 'includes/itm_email.php';
        }
        $ok = itm_email_log_send(
            $conn,
            $companyId,
            $toEmail,
            $subject,
            'received',
            $details,
            $smtpConfigId,
            $fromEmail,
            $ccEmail,
            null
        );
        if (!$ok) {
            return 0;
        }

        return (int)mysqli_insert_id($conn);
    }
}

if (!function_exists('itm_inbound_email_assign_external_code')) {
    function itm_inbound_email_assign_external_code($conn, $companyId, $ticketId)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        if ($companyId <= 0 || $ticketId <= 0) {
            return '';
        }
        $code = 'TCK-' . str_pad((string)$ticketId, 4, '0', STR_PAD_LEFT);
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE tickets SET ticket_external_code = ?
             WHERE id = ? AND company_id = ? AND (ticket_external_code IS NULL OR ticket_external_code = \'\')'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sii', $code, $ticketId, $companyId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        return $code;
    }
}

if (!function_exists('itm_inbound_email_create_ticket')) {
    /**
     * @return array{ticket_id:int,ticket_external_code:string}|false
     */
    function itm_inbound_email_create_ticket($conn, $companyId, $requesterEmployeeId, $title, $description, $fromEmail, $sendAutoReply = true)
    {
        if (!function_exists('itm_live_chat_create_ticket')) {
            require_once ROOT_PATH . 'includes/itm_live_chat_ticket.php';
        }
        $title = trim((string)$title);
        if ($title === '') {
            $title = '(No subject)';
        }
        if (strlen($title) > 255) {
            $title = substr($title, 0, 255);
        }
        $description = itm_inbound_email_strip_body($description);
        if ($description === '' && $fromEmail !== '') {
            $description = 'Inbound email from ' . $fromEmail;
        }

        $ticketId = itm_live_chat_create_ticket($conn, $companyId, $requesterEmployeeId, $title, $description);
        if (!$ticketId) {
            return false;
        }
        $externalCode = itm_inbound_email_assign_external_code($conn, $companyId, $ticketId);

        if (function_exists('itm_search_index_after_module_save')) {
            require_once ROOT_PATH . 'includes/itm_search_index.php';
            itm_search_index_after_module_save($conn, 'tickets', (int)$companyId, (int)$ticketId);
        }

        if ($sendAutoReply && $fromEmail !== '' && filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            if (!function_exists('itm_send_email')) {
                require_once ROOT_PATH . 'includes/itm_email.php';
            }
            $replySubject = 'Ticket ' . $externalCode . ' received';
            $replyHtml = '<p>Your request has been logged as ticket <strong>' . htmlspecialchars($externalCode, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                . '<p>Reply to this thread with the ticket code in the subject to add updates.</p>';
            itm_send_email($fromEmail, $replySubject, $replyHtml, $companyId, [
                'email_template' => [
                    'subtitle' => 'Support request received',
                    'footer_text' => 'IT Management Helpdesk',
                ],
            ]);
        }

        return [
            'ticket_id' => (int)$ticketId,
            'ticket_external_code' => $externalCode,
        ];
    }
}

if (!function_exists('itm_inbound_email_append_comment')) {
    function itm_inbound_email_append_comment($conn, $companyId, $ticketId, $employeeId, $body, $fromEmail, $isFallbackRequester)
    {
        if (!function_exists('itm_ticket_comment_create')) {
            require_once ROOT_PATH . 'includes/itm_ticket_comments.php';
        }
        $body = itm_inbound_email_strip_body($body);
        if ($body === '') {
            $body = '(Empty email body)';
        }
        if ($isFallbackRequester && $fromEmail !== '') {
            $body = 'Email from ' . $fromEmail . ":\n\n" . $body;
        }

        return itm_ticket_comment_create($conn, $companyId, $ticketId, $employeeId, $body, 0);
    }
}

if (!function_exists('itm_inbound_email_fetch_unseen')) {
    /**
     * @param array<string,mixed> $profile
     * @return array{ok:bool,error:string,messages:array<int,array<string,mixed>>,imap:mixed}
     */
    function itm_inbound_email_fetch_unseen(array $profile, $password)
    {
        $result = ['ok' => false, 'error' => '', 'messages' => [], 'imap' => null];
        if (!itm_inbound_email_imap_available()) {
            $result['error'] = 'PHP imap extension is not loaded. Enable extension=imap in php.ini.';

            return $result;
        }
        $mailbox = itm_inbound_email_build_mailbox_string($profile);
        $username = trim((string)($profile['username'] ?? ''));
        if ($mailbox === '' || $username === '' || $password === '') {
            $result['error'] = 'IMAP host, username, and password are required.';

            return $result;
        }

        $imap = @imap_open($mailbox, $username, $password, 0, 1);
        if (!$imap) {
            $result['error'] = 'IMAP connect failed: ' . (string)imap_last_error();

            return $result;
        }

        $nums = imap_search($imap, 'UNSEEN');
        if ($nums === false) {
            $result['ok'] = true;
            $result['imap'] = $imap;

            return $result;
        }

        foreach ($nums as $msgno) {
            $header = imap_headerinfo($imap, (int)$msgno);
            if (!$header) {
                continue;
            }
            $messageId = isset($header->message_id) ? (string)$header->message_id : '';
            if ($messageId === '') {
                $messageId = 'local-' . (int)$msgno . '-' . md5((string)($header->subject ?? '') . (string)($header->date ?? ''));
            }
            $from = isset($header->from[0]) ? ($header->from[0]->mailbox . '@' . $header->from[0]->host) : '';
            $toList = [];
            if (!empty($header->to)) {
                foreach ($header->to as $addr) {
                    if (isset($addr->mailbox, $addr->host)) {
                        $toList[] = strtolower($addr->mailbox . '@' . $addr->host);
                    }
                }
            }
            $ccList = [];
            if (!empty($header->cc)) {
                foreach ($header->cc as $addr) {
                    if (isset($addr->mailbox, $addr->host)) {
                        $ccList[] = strtolower($addr->mailbox . '@' . $addr->host);
                    }
                }
            }
            $structure = imap_fetchstructure($imap, (int)$msgno);
            $body = itm_inbound_email_fetch_body_part($imap, (int)$msgno, $structure);
            if ($body === '') {
                $body = (string)imap_body($imap, (int)$msgno);
            }

            $result['messages'][] = [
                'msgno' => (int)$msgno,
                'message_id' => $messageId,
                'from' => $from,
                'to' => $toList,
                'cc' => $ccList,
                'subject' => isset($header->subject) ? (string)$header->subject : '',
                'body' => $body,
            ];
        }

        $result['ok'] = true;
        $result['imap'] = $imap;

        return $result;
    }
}

if (!function_exists('itm_inbound_email_list_enabled_profiles')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function itm_inbound_email_list_enabled_profiles($conn, $companyFilter = 0)
    {
        $companyFilter = (int)$companyFilter;
        $sql = 'SELECT esc.*, c.email AS company_email, c.company AS company_name
                FROM email_smtp_configurations esc
                INNER JOIN companies c ON c.id = esc.company_id
                WHERE esc.is_default = 1 AND esc.inbound_ticket_enabled = 1 AND esc.active = 1
                  AND c.deleted_at IS NULL AND c.active = 1';
        if ($companyFilter > 0) {
            $sql .= ' AND esc.company_id = ' . $companyFilter;
        }
        $sql .= ' ORDER BY esc.company_id ASC';

        $rows = [];
        $res = mysqli_query($conn, $sql);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
            mysqli_free_result($res);
        }

        return $rows;
    }
}

if (!function_exists('itm_inbound_email_process_company')) {
    /**
     * @param array<string,mixed> $profile
     * @param array{dry_run?:bool,verbose?:bool} $options
     * @return array{status:string,created:int,comments:int,skipped:int,warnings:array<int,string>,errors:array<int,string>}
     */
    function itm_inbound_email_process_company($conn, array $profile, array $options = [])
    {
        $dryRun = !empty($options['dry_run']);
        $verbose = !empty($options['verbose']);
        $summary = [
            'status' => 'ok',
            'created' => 0,
            'comments' => 0,
            'skipped' => 0,
            'warnings' => [],
            'errors' => [],
        ];

        $companyId = (int)($profile['company_id'] ?? 0);
        $companyEmail = strtolower(trim((string)($profile['company_email'] ?? '')));
        $smtpConfigId = (int)($profile['id'] ?? 0);

        if ($companyId <= 0) {
            $summary['status'] = 'fail';
            $summary['errors'][] = 'Invalid company_id on SMTP profile.';

            return $summary;
        }

        $password = '';
        if (!empty($profile['password_encrypted'])) {
            if (!function_exists('itm_email_decrypt_password')) {
                require_once ROOT_PATH . 'includes/itm_email.php';
            }
            $password = (string)itm_email_decrypt_password($profile['password_encrypted']);
        }

        $fetch = itm_inbound_email_fetch_unseen($profile, $password);
        if (!$fetch['ok']) {
            $summary['status'] = 'fail';
            $summary['errors'][] = (string)$fetch['error'];

            return $summary;
        }

        $imap = $fetch['imap'];
        foreach ($fetch['messages'] as $message) {
            $messageId = (string)($message['message_id'] ?? '');
            $normalizedId = itm_inbound_email_normalize_message_id($messageId);
            if ($normalizedId === '') {
                $summary['skipped']++;
                continue;
            }
            if (itm_inbound_email_is_processed($conn, $companyId, $messageId)) {
                $summary['skipped']++;
                if ($verbose) {
                    $summary['warnings'][] = 'Already processed Message-ID: ' . $messageId;
                }
                if (!$dryRun && $imap) {
                    @imap_setflag_full($imap, (string)$message['msgno'], '\\Seen');
                }
                continue;
            }

            $toList = is_array($message['to'] ?? null) ? $message['to'] : [];
            $ccList = is_array($message['cc'] ?? null) ? $message['cc'] : [];
            if ($companyEmail !== '' && !itm_inbound_email_to_matches_company($toList, $ccList, $companyEmail)) {
                $summary['warnings'][] = 'To/Cc does not include companies.email (' . $companyEmail . ') for Message-ID ' . $messageId;
            }

            $fromEmail = (string)($message['from'] ?? '');
            $subject = (string)($message['subject'] ?? '');
            $body = (string)($message['body'] ?? '');
            $requester = itm_inbound_email_resolve_requester($conn, $companyId, $fromEmail);
            if ($requester['employee_id'] <= 0) {
                $summary['errors'][] = 'No requester employee for Message-ID ' . $messageId;
                $summary['status'] = 'fail';
                continue;
            }

            $ref = itm_inbound_email_parse_ticket_ref($subject, $body);
            $existingTicketId = itm_inbound_email_resolve_ticket_id($conn, $companyId, $ref);
            $ticketId = 0;
            $externalCode = '';

            if ($dryRun) {
                if ($existingTicketId > 0) {
                    $summary['comments']++;
                    if ($verbose) {
                        $summary['warnings'][] = '[dry-run] Would append comment to ticket #' . $existingTicketId;
                    }
                } else {
                    $summary['created']++;
                    if ($verbose) {
                        $summary['warnings'][] = '[dry-run] Would create ticket: ' . $subject;
                    }
                }
                continue;
            }

            $toEmail = $companyEmail !== '' ? $companyEmail : implode(', ', $toList);
            $emailLogId = itm_inbound_email_log_received(
                $conn,
                $companyId,
                $smtpConfigId,
                $toEmail,
                $fromEmail,
                implode(', ', $ccList),
                $subject,
                itm_inbound_email_strip_body($body)
            );

            if ($existingTicketId > 0) {
                $commentId = itm_inbound_email_append_comment(
                    $conn,
                    $companyId,
                    $existingTicketId,
                    $requester['employee_id'],
                    $body,
                    $requester['from_email'],
                    $requester['is_fallback']
                );
                if (!$commentId) {
                    $summary['errors'][] = 'Failed to append comment for ticket #' . $existingTicketId;
                    $summary['status'] = 'fail';
                    continue;
                }
                $ticketId = $existingTicketId;
                $summary['comments']++;
            } else {
                $created = itm_inbound_email_create_ticket(
                    $conn,
                    $companyId,
                    $requester['employee_id'],
                    $subject,
                    $body,
                    $requester['from_email'],
                    true
                );
                if (!$created) {
                    $summary['errors'][] = 'Failed to create ticket for Message-ID ' . $messageId;
                    $summary['status'] = 'fail';
                    continue;
                }
                $ticketId = (int)$created['ticket_id'];
                $externalCode = (string)$created['ticket_external_code'];
                $summary['created']++;
            }

            itm_inbound_email_record_processed(
                $conn,
                $companyId,
                $messageId,
                $ticketId,
                $emailLogId,
                $requester['from_email'],
                $subject
            );

            if ($imap) {
                @imap_setflag_full($imap, (string)$message['msgno'], '\\Seen');
            }

            if ($verbose && $externalCode !== '') {
                $summary['warnings'][] = 'Created ' . $externalCode;
            }
        }

        if ($imap) {
            @imap_close($imap);
        }

        return $summary;
    }
}
