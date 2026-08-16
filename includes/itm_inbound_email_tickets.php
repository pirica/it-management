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

if (!function_exists('itm_inbound_email_is_mailpit_profile')) {
    /**
     * Local Mailpit uses HTTP API (no IMAP). Set imap_host to mailpit or http://localhost/mailpit.
     *
     * @param array<string,mixed> $profile
     */
    function itm_inbound_email_is_mailpit_profile(array $profile)
    {
        $host = strtolower(trim((string)($profile['imap_host'] ?? '')));
        if ($host === 'mailpit' || $host === 'localhost/mailpit' || $host === '127.0.0.1/mailpit') {
            return true;
        }
        if ($host !== '' && stripos($host, 'mailpit') !== false) {
            return strpos($host, 'http://') === 0 || strpos($host, 'https://') === 0;
        }

        return false;
    }
}

if (!function_exists('itm_inbound_email_mailpit_api_base')) {
    /**
     * @param array<string,mixed> $profile
     */
    function itm_inbound_email_mailpit_api_base(array $profile)
    {
        $envBase = trim((string)(getenv('ITM_MAILPIT_API_URL') ?: ''));
        if ($envBase !== '') {
            $base = rtrim($envBase, '/');
        } else {
            $host = trim((string)($profile['imap_host'] ?? ''));
            if ($host === 'mailpit' || $host === 'localhost/mailpit' || $host === '127.0.0.1/mailpit') {
                $base = 'http://localhost/mailpit/api/v1';
            } elseif (stripos($host, 'http://') === 0 || stripos($host, 'https://') === 0) {
                $base = rtrim($host, '/');
                if (substr($base, -7) !== '/api/v1') {
                    $base .= '/api/v1';
                }
            } else {
                return '';
            }
        }

        return rtrim($base, '/');
    }
}

if (!function_exists('itm_inbound_email_mailpit_http')) {
    /**
     * @return array{ok:bool,status:int,body:string,error:string}
     */
    function itm_inbound_email_mailpit_http($method, $url, $jsonBody = null)
    {
        $result = ['ok' => false, 'status' => 0, 'body' => '', 'error' => ''];
        $method = strtoupper(trim((string)$method));
        if ($method === '') {
            $result['error'] = 'HTTP method required.';

            return $result;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init((string)$url);
            if ($ch === false) {
                $result['error'] = 'curl_init failed.';

                return $result;
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $headers = ['Accept: application/json'];
            if ($jsonBody !== null) {
                $payload = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $headers[] = 'Content-Type: application/json';
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($body === false) {
                $result['error'] = (string)curl_error($ch);
                curl_close($ch);

                return $result;
            }
            curl_close($ch);
            $result['ok'] = $status >= 200 && $status < 300;
            $result['status'] = $status;
            $result['body'] = (string)$body;

            return $result;
        }

        $contextOptions = [
            'http' => [
                'method' => $method,
                'timeout' => 30,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ];
        if ($jsonBody !== null) {
            $payload = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $contextOptions['http']['header'] .= "Content-Type: application/json\r\n";
            $contextOptions['http']['content'] = $payload;
        }
        $ctx = stream_context_create($contextOptions);
        $body = @file_get_contents((string)$url, false, $ctx);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
        if ($body === false) {
            $result['error'] = 'HTTP request failed.';

            return $result;
        }
        $result['ok'] = $status >= 200 && $status < 300;
        $result['status'] = $status;
        $result['body'] = (string)$body;

        return $result;
    }
}

if (!function_exists('itm_inbound_email_mailpit_reachable')) {
    /**
     * @param array<string,mixed> $profile
     */
    function itm_inbound_email_mailpit_reachable(array $profile)
    {
        $base = itm_inbound_email_mailpit_api_base($profile);
        if ($base === '') {
            return false;
        }
        $response = itm_inbound_email_mailpit_http('GET', $base . '/messages?limit=1');

        return $response['ok'];
    }
}

if (!function_exists('itm_inbound_email_mailpit_parse_address_list')) {
    /**
     * @param mixed $list
     * @return array<int,string>
     */
    function itm_inbound_email_mailpit_parse_address_list($list)
    {
        $out = [];
        if (!is_array($list)) {
            return $out;
        }
        foreach ($list as $item) {
            if (is_array($item) && !empty($item['Address'])) {
                $out[] = strtolower(trim((string)$item['Address']));
            } elseif (is_string($item)) {
                $addr = itm_inbound_email_extract_address($item);
                if ($addr !== '') {
                    $out[] = $addr;
                }
            }
        }

        return $out;
    }
}

if (!function_exists('itm_inbound_email_mailpit_mark_read')) {
    /**
     * @param array<int,string> $ids
     */
    function itm_inbound_email_mailpit_mark_read($apiBase, array $ids, $read = true)
    {
        $apiBase = rtrim((string)$apiBase, '/');
        if ($apiBase === '' || $ids === []) {
            return false;
        }
        $response = itm_inbound_email_mailpit_http('PUT', $apiBase . '/messages', [
            'IDs' => array_values($ids),
            'Read' => (bool)$read,
        ]);

        return $response['ok'];
    }
}

if (!function_exists('itm_inbound_email_mailpit_inject_message')) {
    /**
     * Deliver a test message to local Mailpit SMTP (for verify scripts).
     *
     * @param array<string,string> $extraHeaders Optional RFC headers (e.g. In-Reply-To).
     * @return array{ok:bool,error:string,message_id:string}
     */
    function itm_inbound_email_mailpit_inject_message($toEmail, $fromEmail, $subject, $body, $messageId = '', array $extraHeaders = [])
    {
        $result = ['ok' => false, 'error' => '', 'message_id' => ''];
        $host = trim((string)(getenv('ITM_MAILPIT_SMTP_HOST') ?: '127.0.0.1'));
        $port = (int)(getenv('ITM_MAILPIT_SMTP_PORT') ?: 1025);
        if ($port <= 0) {
            $port = 1025;
        }
        $toEmail = trim((string)$toEmail);
        $fromEmail = trim((string)$fromEmail);
        if ($toEmail === '' || $fromEmail === '') {
            $result['error'] = 'To and From email are required.';

            return $result;
        }
        if ($messageId === '') {
            $messageId = 'mailpit-inject-' . bin2hex(random_bytes(8)) . '@itm.local';
        }
        $messageId = trim($messageId, '<>');
        $result['message_id'] = $messageId;

        $fp = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$fp) {
            $result['error'] = 'SMTP connect failed: ' . $errstr;

            return $result;
        }
        $readLine = static function () use ($fp) {
            $line = fgets($fp);
            return is_string($line) ? $line : '';
        };
        $write = static function ($cmd) use ($fp) {
            fwrite($fp, $cmd . "\r\n");
        };

        $readLine();
        $write('EHLO localhost');
        while ($line = $readLine()) {
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $write('MAIL FROM:<' . $fromEmail . '>');
        $readLine();
        $write('RCPT TO:<' . $toEmail . '>');
        $readLine();
        $write('DATA');
        $readLine();
        $write('Subject: ' . str_replace(["\r", "\n"], ' ', (string)$subject));
        $write('Message-ID: <' . $messageId . '>');
        if (is_array($extraHeaders)) {
            foreach ($extraHeaders as $headerName => $headerValue) {
                $headerName = trim((string)$headerName);
                $headerValue = trim((string)$headerValue);
                if ($headerName === '' || $headerValue === '') {
                    continue;
                }
                $write($headerName . ': ' . str_replace(["\r", "\n"], ' ', $headerValue));
            }
        }
        $write('From: ' . $fromEmail);
        $write('To: ' . $toEmail);
        $write('');
        $bodyLines = preg_split('/\r\n|\r|\n/', (string)$body);
        if (!is_array($bodyLines)) {
            $bodyLines = [(string)$body];
        }
        foreach ($bodyLines as $line) {
            if ($line === '.') {
                $write('..');
            } else {
                $write((string)$line);
            }
        }
        $write('.');
        $readLine();
        $write('QUIT');
        fclose($fp);
        $result['ok'] = true;

        return $result;
    }
}

if (!function_exists('itm_inbound_email_fetch_unseen_mailpit')) {
    /**
     * @param array<string,mixed> $profile
     * @param array{fetch_body?:bool} $options
     * @return array{ok:bool,error:string,messages:array<int,array<string,mixed>>,imap:mixed,transport:string,mailpit_base:string}
     */
    function itm_inbound_email_fetch_unseen_mailpit(array $profile, array $options = [])
    {
        $fetchBody = !array_key_exists('fetch_body', $options) || (bool)$options['fetch_body'];
        $result = [
            'ok' => false,
            'error' => '',
            'messages' => [],
            'imap' => null,
            'transport' => 'mailpit',
            'mailpit_base' => '',
        ];
        $base = itm_inbound_email_mailpit_api_base($profile);
        if ($base === '') {
            $result['error'] = 'Mailpit API base URL is not configured (set imap_host to mailpit or http://localhost/mailpit).';

            return $result;
        }
        $result['mailpit_base'] = $base;

        $listResponse = itm_inbound_email_mailpit_http('GET', $base . '/messages?limit=100');
        if (!$listResponse['ok']) {
            $result['error'] = 'Mailpit API list failed (HTTP ' . $listResponse['status'] . ').';

            return $result;
        }
        $decoded = json_decode($listResponse['body'], true);
        if (!is_array($decoded) || !isset($decoded['messages']) || !is_array($decoded['messages'])) {
            $result['error'] = 'Mailpit API returned invalid JSON.';

            return $result;
        }

        foreach ($decoded['messages'] as $msg) {
            // Why: Mailpit marks messages read when opened in the web UI; dedupe is ticket_inbound_email_messages, not Read.
            if (!is_array($msg)) {
                continue;
            }
            $mailpitId = (string)($msg['ID'] ?? '');
            if ($mailpitId === '') {
                continue;
            }
            $messageId = (string)($msg['MessageID'] ?? '');
            if ($messageId === '') {
                $messageId = 'mailpit-' . $mailpitId . '@local';
            }
            $from = '';
            if (isset($msg['From']) && is_array($msg['From'])) {
                $from = strtolower(trim((string)($msg['From']['Address'] ?? '')));
            }
            $toList = itm_inbound_email_mailpit_parse_address_list($msg['To'] ?? []);
            $ccList = itm_inbound_email_mailpit_parse_address_list($msg['Cc'] ?? []);
            $subject = (string)($msg['Subject'] ?? '');
            $body = (string)($msg['Snippet'] ?? '');
            $alreadyRead = !empty($msg['Read']);
            $inReplyTo = '';
            $references = '';

            $detailResponse = itm_inbound_email_mailpit_http('GET', $base . '/message/' . rawurlencode($mailpitId));
            if ($detailResponse['ok']) {
                $detail = json_decode($detailResponse['body'], true);
                if (is_array($detail)) {
                    if ($fetchBody) {
                        if (!empty($detail['Text'])) {
                            $body = (string)$detail['Text'];
                        } elseif (!empty($detail['HTML'])) {
                            $body = (string)$detail['HTML'];
                        } elseif (!empty($detail['Snippet'])) {
                            $body = (string)$detail['Snippet'];
                        }
                    }
                    $headers = $detail['Headers'] ?? [];
                    $inReplyTo = itm_inbound_email_header_value($headers, 'In-Reply-To');
                    $references = itm_inbound_email_header_value($headers, 'References');
                }
            }

            $result['messages'][] = [
                'msgno' => $mailpitId,
                'message_id' => $messageId,
                'from' => $from,
                'to' => $toList,
                'cc' => $ccList,
                'subject' => $subject,
                'body' => $body,
                'in_reply_to' => $inReplyTo,
                'references' => $references,
                'transport' => 'mailpit',
            ];
        }

        $result['ok'] = true;

        return $result;
    }
}

if (!function_exists('itm_inbound_email_polling_available')) {
    /**
     * @param array<int,array<string,mixed>> $profiles
     */
    function itm_inbound_email_polling_available(array $profiles)
    {
        if ($profiles === []) {
            return true;
        }
        foreach ($profiles as $profile) {
            if (itm_inbound_email_is_mailpit_profile($profile)) {
                if (itm_inbound_email_mailpit_reachable($profile)) {
                    return true;
                }
                continue;
            }
            if (itm_inbound_email_imap_available()) {
                return true;
            }
        }

        return itm_inbound_email_imap_available();
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

if (!function_exists('itm_inbound_email_is_reply_subject')) {
    function itm_inbound_email_is_reply_subject($subject)
    {
        return (bool)preg_match('/^\s*(re|fw|fwd)\s*:/i', trim((string)$subject));
    }
}

if (!function_exists('itm_inbound_email_normalize_subject_thread')) {
    function itm_inbound_email_normalize_subject_thread($subject)
    {
        $subject = trim((string)$subject);
        while ($subject !== '' && preg_match('/^\s*(re|fw|fwd)\s*:\s*/i', $subject)) {
            $subject = preg_replace('/^\s*(re|fw|fwd)\s*:\s*/i', '', $subject);
            $subject = trim($subject);
        }

        return $subject;
    }
}

if (!function_exists('itm_inbound_email_header_value')) {
    /**
     * @param mixed $headers Mailpit Headers map or flat header lines.
     */
    function itm_inbound_email_header_value($headers, $name)
    {
        $name = strtolower(trim((string)$name));
        if ($name === '' || $headers === null) {
            return '';
        }
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strtolower((string)$key) !== $name) {
                    continue;
                }
                if (is_array($value)) {
                    return trim((string)($value[0] ?? ''));
                }

                return trim((string)$value);
            }

            return '';
        }

        return '';
    }
}

if (!function_exists('itm_inbound_email_parse_references_header')) {
    /**
     * @return array<int,string>
     */
    function itm_inbound_email_parse_references_header($references)
    {
        $references = trim((string)$references);
        if ($references === '') {
            return [];
        }
        $ids = [];
        if (preg_match_all('/<([^>]+)>/', $references, $matches)) {
            foreach ($matches[1] as $id) {
                $norm = itm_inbound_email_normalize_message_id($id);
                if ($norm !== '') {
                    $ids[] = $norm;
                }
            }
        }
        $bare = itm_inbound_email_normalize_message_id($references);
        if ($bare !== '' && !in_array($bare, $ids, true)) {
            $ids[] = $bare;
        }

        return $ids;
    }
}

if (!function_exists('itm_inbound_email_message_raw_payload')) {
    /**
     * @param array<string,mixed> $message
     */
    function itm_inbound_email_message_raw_payload(array $message)
    {
        $payload = [
            'message_id' => (string)($message['message_id'] ?? ''),
            'from' => (string)($message['from'] ?? ''),
            'to' => $message['to'] ?? [],
            'cc' => $message['cc'] ?? [],
            'subject' => (string)($message['subject'] ?? ''),
            'body' => (string)($message['body'] ?? ''),
            'in_reply_to' => (string)($message['in_reply_to'] ?? ''),
            'references' => (string)($message['references'] ?? ''),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return '';
        }
        if (strlen($json) > 60000) {
            $json = substr($json, 0, 60000) . '…[truncated]';
        }

        return $json;
    }
}

if (!function_exists('itm_inbound_email_build_event_details')) {
    /**
     * @param array<string,mixed> $meta
     */
    function itm_inbound_email_build_event_details($eventType, array $meta, $rawPayload = '')
    {
        $event = [
            'inbound_event' => trim((string)$eventType),
            'meta' => $meta,
        ];
        if ($rawPayload !== '') {
            $event['raw_payload'] = $rawPayload;
        }
        $json = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '';
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

if (!function_exists('itm_inbound_email_resolve_ticket_by_message_ids')) {
    /**
     * @param array<int,string> $messageIds
     */
    function itm_inbound_email_resolve_ticket_by_message_ids($conn, $companyId, array $messageIds)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0 || $messageIds === []) {
            return 0;
        }
        foreach ($messageIds as $messageId) {
            $norm = itm_inbound_email_normalize_message_id($messageId);
            if ($norm === '') {
                continue;
            }
            $stmt = mysqli_prepare(
                $conn,
                'SELECT ticket_id FROM ticket_inbound_email_messages
                 WHERE company_id = ? AND message_id = ? AND ticket_id IS NOT NULL AND ticket_id > 0
                 LIMIT 1'
            );
            if (!$stmt) {
                continue;
            }
            mysqli_stmt_bind_param($stmt, 'is', $companyId, $norm);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            if ($row && (int)$row['ticket_id'] > 0) {
                return (int)$row['ticket_id'];
            }
        }

        return 0;
    }
}

if (!function_exists('itm_inbound_email_resolve_ticket_by_subject_thread')) {
    function itm_inbound_email_resolve_ticket_by_subject_thread($conn, $companyId, $subject)
    {
        if (!itm_inbound_email_is_reply_subject($subject)) {
            return 0;
        }
        $companyId = (int)$companyId;
        $normalized = itm_inbound_email_normalize_subject_thread($subject);
        if ($companyId <= 0 || $normalized === '') {
            return 0;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT t.id FROM tickets t
             INNER JOIN ticket_statuses ts ON ts.id = t.status_id AND ts.company_id = t.company_id
             WHERE t.company_id = ? AND t.deleted_at IS NULL AND t.active = 1 AND ts.is_closed = 0
               AND LOWER(TRIM(t.title)) = LOWER(?)
             ORDER BY t.id DESC
             LIMIT 1'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'is', $companyId, $normalized);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);

        return $row ? (int)$row['id'] : 0;
    }
}

if (!function_exists('itm_inbound_email_resolve_thread_ticket')) {
    /**
     * @param array<string,mixed> $message
     */
    function itm_inbound_email_resolve_thread_ticket($conn, $companyId, array $message)
    {
        $subject = (string)($message['subject'] ?? '');
        $body = (string)($message['body'] ?? '');
        $ref = itm_inbound_email_parse_ticket_ref($subject, $body);
        $ticketId = itm_inbound_email_resolve_ticket_id($conn, $companyId, $ref);
        if ($ticketId > 0) {
            return $ticketId;
        }

        $headerIds = [];
        $inReplyTo = trim((string)($message['in_reply_to'] ?? ''));
        if ($inReplyTo !== '') {
            $headerIds[] = $inReplyTo;
        }
        $headerIds = array_merge($headerIds, itm_inbound_email_parse_references_header((string)($message['references'] ?? '')));
        $ticketId = itm_inbound_email_resolve_ticket_by_message_ids($conn, $companyId, $headerIds);
        if ($ticketId > 0) {
            return $ticketId;
        }

        if (itm_inbound_email_is_reply_subject($subject)) {
            return itm_inbound_email_resolve_ticket_by_subject_thread($conn, $companyId, $subject);
        }

        return 0;
    }
}

if (!function_exists('itm_inbound_email_list_routing_rules')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function itm_inbound_email_list_routing_rules($conn, $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return [];
        }
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, keyword, assigned_to_employee_id, category_id, priority_id, sort_order
             FROM ticket_inbound_email_routing_rules
             WHERE company_id = ? AND active = 1 AND deleted_at IS NULL
             ORDER BY sort_order ASC, id ASC'
        );
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
            mysqli_free_result($res);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('itm_inbound_email_apply_routing_rules')) {
    /**
     * @return array{priority_id:int,category_id:int,assigned_to_employee_id:int,matched_keywords:array<int,string>}
     */
    function itm_inbound_email_apply_routing_rules($conn, $companyId, $subject, $body = '')
    {
        $result = [
            'priority_id' => 0,
            'category_id' => 0,
            'assigned_to_employee_id' => 0,
            'matched_keywords' => [],
        ];
        $haystack = strtolower(trim((string)$subject) . ' ' . trim((string)$body));
        if ($haystack === '') {
            return $result;
        }
        foreach (itm_inbound_email_list_routing_rules($conn, $companyId) as $rule) {
            $keyword = strtolower(trim((string)($rule['keyword'] ?? '')));
            if ($keyword === '') {
                continue;
            }
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
            if (!preg_match($pattern, $haystack)) {
                continue;
            }
            $result['matched_keywords'][] = $keyword;
            $priorityId = (int)($rule['priority_id'] ?? 0);
            $categoryId = (int)($rule['category_id'] ?? 0);
            $assigneeId = (int)($rule['assigned_to_employee_id'] ?? 0);
            if ($priorityId > 0 && $result['priority_id'] <= 0) {
                $result['priority_id'] = $priorityId;
            }
            if ($categoryId > 0 && $result['category_id'] <= 0) {
                $result['category_id'] = $categoryId;
            }
            if ($assigneeId > 0 && $result['assigned_to_employee_id'] <= 0) {
                $result['assigned_to_employee_id'] = $assigneeId;
            }
        }

        return $result;
    }
}

if (!function_exists('itm_inbound_email_update_ticket_routing')) {
    /**
     * @param array{priority_id?:int,category_id?:int,assigned_to_employee_id?:int} $routing
     */
    function itm_inbound_email_update_ticket_routing($conn, $companyId, $ticketId, array $routing)
    {
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        if ($companyId <= 0 || $ticketId <= 0) {
            return false;
        }
        $priorityId = (int)($routing['priority_id'] ?? 0);
        $categoryId = (int)($routing['category_id'] ?? 0);
        $assigneeId = (int)($routing['assigned_to_employee_id'] ?? 0);
        if ($priorityId <= 0 && $categoryId <= 0 && $assigneeId <= 0) {
            return true;
        }

        $sets = [];
        $types = '';
        $params = [];
        if ($priorityId > 0) {
            $sets[] = 'priority_id = ?';
            $types .= 'i';
            $params[] = $priorityId;
        }
        if ($categoryId > 0) {
            $sets[] = 'category_id = ?';
            $types .= 'i';
            $params[] = $categoryId;
        }
        if ($assigneeId > 0) {
            $sets[] = 'assigned_to_employee_id = ?';
            $types .= 'i';
            $params[] = $assigneeId;
        }
        if ($sets === []) {
            return true;
        }
        $sql = 'UPDATE tickets SET ' . implode(', ', $sets) . ' WHERE id = ? AND company_id = ? AND deleted_at IS NULL';
        $types .= 'ii';
        $params[] = $ticketId;
        $params[] = $companyId;
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if ($ok && $priorityId > 0) {
            if (!function_exists('itm_ticket_sla_apply_on_create')) {
                require_once ROOT_PATH . 'includes/itm_ticket_sla.php';
            }
            itm_ticket_sla_apply_on_create($conn, $ticketId, $companyId, $priorityId);
        }

        return $ok;
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
    function itm_inbound_email_log_received($conn, $companyId, $smtpConfigId, $toEmail, $fromEmail, $ccEmail, $subject, $details, $status = 'received')
    {
        if (!function_exists('itm_email_log_send')) {
            require_once ROOT_PATH . 'includes/itm_email.php';
        }
        $ok = itm_email_log_send(
            $conn,
            $companyId,
            $toEmail,
            $subject,
            $status,
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

if (!function_exists('itm_inbound_email_log_event')) {
    /**
     * @param array<string,mixed> $meta
     */
    function itm_inbound_email_log_event(
        $conn,
        $companyId,
        $smtpConfigId,
        $toEmail,
        $fromEmail,
        $ccEmail,
        $subject,
        $eventType,
        $rawPayload,
        array $meta = [],
        $status = 'received'
    ) {
        $details = itm_inbound_email_build_event_details($eventType, $meta, $rawPayload);
        $logStatus = strtolower((string)$status);
        if (!in_array($logStatus, ['sent', 'failed', 'received'], true)) {
            $logStatus = $eventType === 'parse_error' || $eventType === 'requester_missing' ? 'failed' : 'received';
        }

        return itm_inbound_email_log_received(
            $conn,
            $companyId,
            $smtpConfigId,
            $toEmail,
            $fromEmail,
            $ccEmail,
            $subject,
            $details,
            $logStatus
        );
    }
}

if (!function_exists('itm_inbound_email_mark_message_handled')) {
    /**
     * @param array<string,mixed> $message
     */
    function itm_inbound_email_mark_message_handled($imap, $transport, $mailpitBase, array $message)
    {
        if ($imap) {
            @imap_setflag_full($imap, (string)$message['msgno'], '\\Seen');

            return;
        }
        if ($transport === 'mailpit' && $mailpitBase !== '') {
            itm_inbound_email_mailpit_mark_read($mailpitBase, [(string)$message['msgno']], true);
        }
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
     * @param array{priority_id?:int,category_id?:int,assigned_to_employee_id?:int,matched_keywords?:array<int,string>} $routing
     * @return array{ticket_id:int,ticket_external_code:string}|false
     */
    function itm_inbound_email_create_ticket(
        $conn,
        $companyId,
        $requesterEmployeeId,
        $title,
        $description,
        $fromEmail,
        $sendAutoReply = true,
        array $routing = []
    ) {
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

        $priorityId = (int)($routing['priority_id'] ?? 0);
        $ticketId = itm_live_chat_create_ticket(
            $conn,
            $companyId,
            $requesterEmployeeId,
            $title,
            $description,
            $priorityId > 0 ? $priorityId : null
        );
        if (!$ticketId) {
            return false;
        }
        itm_inbound_email_update_ticket_routing($conn, $companyId, (int)$ticketId, $routing);
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
    function itm_inbound_email_fetch_unseen(array $profile, $password, array $options = [])
    {
        if (itm_inbound_email_is_mailpit_profile($profile)) {
            $fetchBody = !array_key_exists('fetch_body', $options) ? true : (bool)$options['fetch_body'];

            return itm_inbound_email_fetch_unseen_mailpit($profile, ['fetch_body' => $fetchBody]);
        }

        $result = ['ok' => false, 'error' => '', 'messages' => [], 'imap' => null, 'transport' => 'imap', 'mailpit_base' => ''];
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
            $inReplyTo = isset($header->in_reply_to) ? (string)$header->in_reply_to : '';
            $references = isset($header->references) ? (string)$header->references : '';

            $result['messages'][] = [
                'msgno' => (int)$msgno,
                'message_id' => $messageId,
                'from' => $from,
                'to' => $toList,
                'cc' => $ccList,
                'subject' => isset($header->subject) ? (string)$header->subject : '',
                'body' => $body,
                'in_reply_to' => $inReplyTo,
                'references' => $references,
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
            'passes' => [],
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

        $fetch = itm_inbound_email_fetch_unseen($profile, $password, ['fetch_body' => !$dryRun]);
        if (!$fetch['ok']) {
            $summary['status'] = 'fail';
            $summary['errors'][] = (string)$fetch['error'];

            return $summary;
        }

        $imap = $fetch['imap'];
        $transport = (string)($fetch['transport'] ?? 'imap');
        $mailpitBase = (string)($fetch['mailpit_base'] ?? '');
        foreach ($fetch['messages'] as $message) {
            $messageId = (string)($message['message_id'] ?? '');
            $normalizedId = itm_inbound_email_normalize_message_id($messageId);
            $rawPayload = itm_inbound_email_message_raw_payload($message);
            $fromEmail = (string)($message['from'] ?? '');
            $subject = (string)($message['subject'] ?? '');
            $body = (string)($message['body'] ?? '');
            $toList = is_array($message['to'] ?? null) ? $message['to'] : [];
            $ccList = is_array($message['cc'] ?? null) ? $message['cc'] : [];
            $toEmail = $companyEmail !== '' ? $companyEmail : implode(', ', $toList);

            if ($normalizedId === '') {
                $summary['skipped']++;
                if (!$dryRun) {
                    itm_inbound_email_log_event(
                        $conn,
                        $companyId,
                        $smtpConfigId,
                        $toEmail,
                        $fromEmail,
                        implode(', ', $ccList),
                        $subject !== '' ? $subject : '(No subject)',
                        'parse_error',
                        $rawPayload,
                        ['reason' => 'missing_message_id'],
                        'failed'
                    );
                }
                continue;
            }
            if (itm_inbound_email_is_processed($conn, $companyId, $messageId)) {
                $summary['skipped']++;
                if ($verbose) {
                    $summary['passes'][] = 'Already processed Message-ID: ' . $messageId;
                }
                if (!$dryRun) {
                    itm_inbound_email_log_event(
                        $conn,
                        $companyId,
                        $smtpConfigId,
                        $toEmail,
                        $fromEmail,
                        implode(', ', $ccList),
                        $subject,
                        'duplicate_skip',
                        '',
                        ['message_id' => $normalizedId],
                        'received'
                    );
                    itm_inbound_email_mark_message_handled($imap, $transport, $mailpitBase, $message);
                }
                continue;
            }

            if ($companyEmail !== '' && !itm_inbound_email_to_matches_company($toList, $ccList, $companyEmail)) {
                if ($transport === 'mailpit') {
                    $summary['skipped']++;
                    if ($verbose) {
                        $summary['passes'][] = 'Skipped (To/Cc not ' . $companyEmail . '): Message-ID ' . $messageId;
                    }
                    if (!$dryRun) {
                        itm_inbound_email_log_event(
                            $conn,
                            $companyId,
                            $smtpConfigId,
                            $toEmail,
                            $fromEmail,
                            implode(', ', $ccList),
                            $subject,
                            'wrong_recipient_skip',
                            $rawPayload,
                            ['company_email' => $companyEmail],
                            'received'
                        );
                        itm_inbound_email_record_processed(
                            $conn,
                            $companyId,
                            $messageId,
                            0,
                            0,
                            $fromEmail,
                            $subject
                        );
                        itm_inbound_email_mark_message_handled($imap, $transport, $mailpitBase, $message);
                    }
                    continue;
                }
                $summary['warnings'][] = 'To/Cc does not include companies.email (' . $companyEmail . ') for Message-ID ' . $messageId;
            }

            $parseFailed = ($body === '' && $subject === '' && $fromEmail === '');
            if ($parseFailed) {
                $summary['skipped']++;
                if (!$dryRun) {
                    itm_inbound_email_log_event(
                        $conn,
                        $companyId,
                        $smtpConfigId,
                        $toEmail,
                        $fromEmail,
                        implode(', ', $ccList),
                        '(Unparseable email)',
                        'parse_error',
                        $rawPayload,
                        ['reason' => 'empty_message'],
                        'failed'
                    );
                    itm_inbound_email_record_processed(
                        $conn,
                        $companyId,
                        $messageId,
                        0,
                        0,
                        $fromEmail,
                        $subject
                    );
                    itm_inbound_email_mark_message_handled($imap, $transport, $mailpitBase, $message);
                }
                continue;
            }

            $requester = itm_inbound_email_resolve_requester($conn, $companyId, $fromEmail);
            if ($requester['employee_id'] <= 0) {
                $summary['errors'][] = 'No requester employee for Message-ID ' . $messageId;
                $summary['status'] = 'fail';
                if (!$dryRun) {
                    itm_inbound_email_log_event(
                        $conn,
                        $companyId,
                        $smtpConfigId,
                        $toEmail,
                        $fromEmail,
                        implode(', ', $ccList),
                        $subject,
                        'requester_missing',
                        $rawPayload,
                        ['from_email' => $fromEmail],
                        'failed'
                    );
                }
                continue;
            }

            $existingTicketId = itm_inbound_email_resolve_thread_ticket($conn, $companyId, $message);
            $routing = itm_inbound_email_apply_routing_rules($conn, $companyId, $subject, $body);
            $ticketId = 0;
            $externalCode = '';

            if ($dryRun) {
                if ($existingTicketId > 0) {
                    $summary['comments']++;
                    if ($verbose) {
                        $summary['passes'][] = '[dry-run] Would append comment to ticket #' . $existingTicketId;
                    }
                } else {
                    $summary['created']++;
                    if ($verbose) {
                        $summary['passes'][] = '[dry-run] Would create ticket: ' . $subject;
                    }
                }
                continue;
            }

            if ($existingTicketId > 0) {
                $emailLogId = itm_inbound_email_log_event(
                    $conn,
                    $companyId,
                    $smtpConfigId,
                    $toEmail,
                    $fromEmail,
                    implode(', ', $ccList),
                    $subject,
                    'comment_appended',
                    $rawPayload,
                    ['ticket_id' => $existingTicketId],
                    'received'
                );
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
                    itm_inbound_email_log_event(
                        $conn,
                        $companyId,
                        $smtpConfigId,
                        $toEmail,
                        $fromEmail,
                        implode(', ', $ccList),
                        $subject,
                        'comment_failed',
                        $rawPayload,
                        ['ticket_id' => $existingTicketId],
                        'failed'
                    );
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
                    true,
                    $routing
                );
                if (!$created) {
                    $summary['errors'][] = 'Failed to create ticket for Message-ID ' . $messageId;
                    $summary['status'] = 'fail';
                    itm_inbound_email_log_event(
                        $conn,
                        $companyId,
                        $smtpConfigId,
                        $toEmail,
                        $fromEmail,
                        implode(', ', $ccList),
                        $subject,
                        'ticket_create_failed',
                        $rawPayload,
                        ['matched_keywords' => $routing['matched_keywords'] ?? []],
                        'failed'
                    );
                    continue;
                }
                $ticketId = (int)$created['ticket_id'];
                $externalCode = (string)$created['ticket_external_code'];
                $summary['created']++;
                $emailLogId = itm_inbound_email_log_event(
                    $conn,
                    $companyId,
                    $smtpConfigId,
                    $toEmail,
                    $fromEmail,
                    implode(', ', $ccList),
                    $subject,
                    'ticket_created',
                    $rawPayload,
                    [
                        'ticket_id' => $ticketId,
                        'ticket_external_code' => $externalCode,
                        'matched_keywords' => $routing['matched_keywords'] ?? [],
                        'priority_id' => (int)($routing['priority_id'] ?? 0),
                        'category_id' => (int)($routing['category_id'] ?? 0),
                        'assigned_to_employee_id' => (int)($routing['assigned_to_employee_id'] ?? 0),
                    ],
                    'received'
                );
            }

            itm_inbound_email_record_processed(
                $conn,
                $companyId,
                $messageId,
                $ticketId,
                $emailLogId ?? 0,
                $requester['from_email'],
                $subject
            );

            itm_inbound_email_mark_message_handled($imap, $transport, $mailpitBase, $message);

            if ($verbose && $externalCode !== '') {
                $summary['passes'][] = 'Created ' . $externalCode;
            }
        }

        if ($imap) {
            @imap_close($imap);
        }

        return $summary;
    }
}

if (!function_exists('itm_inbound_email_echo_summary_verbose')) {
    /**
     * @param array<string,mixed> $summary
     */
    function itm_inbound_email_echo_summary_verbose(array $summary, $linePrefix = '')
    {
        $nl = function_exists('itm_script_output_nl') ? itm_script_output_nl() : PHP_EOL;
        $passes = is_array($summary['passes'] ?? null) ? $summary['passes'] : [];
        $warnings = is_array($summary['warnings'] ?? null) ? $summary['warnings'] : [];
        foreach ($passes as $line) {
            echo colorText($linePrefix . '[PASS] ' . (string)$line, 'pass') . $nl;
        }
        foreach ($warnings as $line) {
            echo colorText($linePrefix . '[WARN] ' . (string)$line, 'warn') . $nl;
        }
    }
}
