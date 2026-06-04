<?php
/**
 * Audit Logging Functions
 * 
 * Provides a centralized mechanism for logging database changes (INSERT, UPDATE, DELETE).
 * Captures old and new values in JSON format, along with user context, IP, and user agent.
 */

/**
 * Encodes an array or object into a JSON string for audit storage
 * 
 * @param mixed $values The data to encode
 * @return string|null The JSON string or null on failure
 */
function itm_audit_encode_values($values) {
    if ($values === null) {
        return null;
    }

    if (!is_array($values) && !is_object($values)) {
        $values = ['value' => $values];
    }

    $json = json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json === false ? null : $json;
}

/**
 * Resolves the most reliable client IP address from proxy-aware headers.
 *
 * Why: Deployments behind load balancers/reverse proxies often expose the proxy IP
 * in REMOTE_ADDR. We prefer forwarded public IPv4, then forwarded public IPv6,
 * then safely fall back while still prioritizing IPv4 when both are available.
 *
 * @return string Sanitized IP address (IPv4/IPv6) or an empty string when unavailable
 */
function itm_get_client_ip_address() {
    $server = $_SERVER ?? [];
    $forwardingHeaders = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
    ];
    $firstForwardedIpV4 = '';
    $firstForwardedIpV6 = '';
    $firstForwardedPublicIpV4 = '';
    $firstForwardedPublicIpV6 = '';

    // Why: Keep the earliest valid forwarding value so we can still identify
    // the real client when trusted proxies run on loopback/private addresses.
    foreach ($forwardingHeaders as $headerName) {
        if (!isset($server[$headerName])) {
            continue;
        }

        $headerValue = trim((string)$server[$headerName]);
        if ($headerValue === '') {
            continue;
        }

        $parts = array_map('trim', explode(',', $headerValue));
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (strpos($part, ':') !== false && substr_count($part, ':') === 1 && strpos($part, '.') !== false) {
                $ipv4PortCandidate = explode(':', $part);
                $part = trim((string)($ipv4PortCandidate[0] ?? $part));
            }

            if (filter_var($part, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            if (strpos($part, '.') !== false) {
                if ($firstForwardedIpV4 === '') {
                    $firstForwardedIpV4 = $part;
                }
            } elseif ($firstForwardedIpV6 === '') {
                $firstForwardedIpV6 = $part;
            }

            $isPublic = filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
            if ($isPublic) {
                if (strpos($part, '.') !== false) {
                    $firstForwardedPublicIpV4 = $part;
                    break 2;
                }
                if ($firstForwardedPublicIpV6 === '') {
                    $firstForwardedPublicIpV6 = $part;
                }
            }
        }
    }

    if ($firstForwardedPublicIpV4 !== '') {
        return $firstForwardedPublicIpV4;
    }
    if ($firstForwardedPublicIpV6 !== '') {
        return $firstForwardedPublicIpV6;
    }

    $remoteAddr = trim((string)($server['REMOTE_ADDR'] ?? ''));
    if ($remoteAddr !== '' && filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false) {
        if (str_starts_with(strtolower($remoteAddr), '::ffff:')) {
            $mappedIpv4 = substr($remoteAddr, 7);
            if ($mappedIpv4 !== '' && filter_var($mappedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $remoteAddr = $mappedIpv4;
            }
        }

        $remoteIsPublic = filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        if ($remoteIsPublic && strpos($remoteAddr, '.') !== false) {
            return $remoteAddr;
        }
        if (!$remoteIsPublic && $firstForwardedIpV4 !== '') {
            return $firstForwardedIpV4;
        }
        if (!$remoteIsPublic && $firstForwardedIpV6 !== '') {
            return $firstForwardedIpV6;
        }
        if ($remoteIsPublic) {
            return $remoteAddr;
        }

        if ($firstForwardedIpV4 !== '') {
            return $firstForwardedIpV4;
        }
        if ($firstForwardedIpV6 !== '') {
            return $firstForwardedIpV6;
        }

        // Why: Keep direct socket source as final fallback even if it is private,
        // because local/dev deployments may not pass forwarding headers at all.
        if ($remoteAddr !== '') {
            return $remoteAddr;
        }
    }

    return $firstForwardedIpV4 !== '' ? $firstForwardedIpV4 : $firstForwardedIpV6;
}

/**
 * Resolves a deterministic request IP for auth attempt storage.
 *
 * Why: Login/reset rate limiting should consistently store IPv4 when available
 * to keep analytics and operator triage predictable; otherwise store IPv6.
 *
 * @return string Valid IPv4/IPv6 address, or 0.0.0.0 when none is available
 */
function itm_get_login_request_ip(): string
{
    $server = $_SERVER ?? [];
    $candidates = [];
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];

    // Why: Auth throttling should prefer IPv4 whenever any trusted/request header
    // provides one, even if another resolver path selected an IPv6 address first.
    foreach ($headers as $header) {
        $headerValue = trim((string)($server[$header] ?? ''));
        if ($headerValue !== '') {
            $candidates[] = $headerValue;
        }
    }

    $resolved = trim((string)(function_exists('itm_get_client_ip_address') ? itm_get_client_ip_address() : ''));
    if ($resolved !== '') {
        $candidates[] = $resolved;
    }

    $remoteAddr = trim((string)($server['REMOTE_ADDR'] ?? ''));
    if ($remoteAddr !== '') {
        $candidates[] = $remoteAddr;
    }

    $preferred = itm_pick_preferred_ip_for_display(implode(',', $candidates));
    if ($preferred !== '' && filter_var($preferred, FILTER_VALIDATE_IP) !== false) {
        return $preferred;
    }

    return '0.0.0.0';
}

/**
 * Chooses a single human-friendly IP for UI display from a potentially noisy input.
 *
 * Why: Security tables can include proxy chains (`ip1, ip2`) or values with ports.
 * Operators asked to always see IPv4 first for faster triage, then IPv6 as fallback.
 *
 * @param mixed $value Raw IP value captured by the app
 * @return string Preferred IPv4, or IPv6 fallback, or raw trimmed value when unparsable
 */
function itm_pick_preferred_ip_for_display($value) {
    $raw = trim((string)($value ?? ''));
    if ($raw === '') {
        return '';
    }

    $parts = array_map('trim', explode(',', $raw));
    $firstIpv6 = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if (strtolower($part) === '::1') {
            return '127.0.0.1';
        }

        if (str_starts_with($part, '[') && str_contains($part, ']')) {
            $closingBracketPos = strpos($part, ']');
            if ($closingBracketPos !== false) {
                $part = substr($part, 1, $closingBracketPos - 1);
            }
        }

        if (strpos($part, ':') !== false && substr_count($part, ':') === 1 && strpos($part, '.') !== false) {
            $ipv4PortCandidate = explode(':', $part);
            $part = trim((string)($ipv4PortCandidate[0] ?? $part));
        }

        if (str_starts_with(strtolower($part), '::ffff:')) {
            $mappedIpv4 = substr($part, 7);
            if ($mappedIpv4 !== '' && filter_var($mappedIpv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $mappedIpv4;
            }
        }

        if (filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $part;
        }

        if ($firstIpv6 === '' && filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $firstIpv6 = $part;
        }
    }

    if ($firstIpv6 !== '') {
        return $firstIpv6;
    }

    return $raw;
}

/**
 * Logs a single audit event to the audit_logs table
 * 
 * @param mysqli $conn Database connection
 * @param string $table The table being modified
 * @param int $record_id The ID of the record being modified
 * @param string $action The action performed (INSERT, UPDATE, or DELETE)
 * @param mixed $old_values The record state before the change
 * @param mixed $new_values The record state after the change
 * @return bool True on success, false on failure
 */
function itm_log_audit($conn, $table, $record_id, $action, $old_values = null, $new_values = null) {
    // Only log if a company context exists in the session
    if (!isset($_SESSION['company_id'])) {
        return false;
    }

    $allowedActions = ['INSERT', 'UPDATE', 'DELETE'];
    $action = strtoupper(trim((string)$action));
    if (!in_array($action, $allowedActions, true)) {
        return false;
    }

    $table = trim((string)$table);
    if ($table === '') {
        return false;
    }

    $company_id = (int)$_SESSION['company_id'];
    // Check if audit logging is enabled in the UI configuration
    $uiConfig = itm_get_ui_configuration($conn, $company_id);
    if ((int)($uiConfig['enable_audit_logs'] ?? 1) !== 1) {
        return false;
    }

    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $old_json = itm_audit_encode_values($old_values);
    $new_json = itm_audit_encode_values($new_values);
    $ipAddress = itm_get_client_ip_address();
    $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $sql = 'INSERT INTO audit_logs (company_id, user_id, table_name, record_id, action, old_values, new_values, ip_address, user_agent) '
         . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';

    try {
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'iisisssss',
            $company_id,
            $user_id,
            $table,
            $record_id,
            $action,
            $old_json,
            $new_json,
            $ipAddress,
            $userAgent
        );

        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    } catch (Throwable $t) {
        // Why: audit writes must never break the originating CRUD action; callers
        // rely on best-effort logging and should not surface unrelated FK failures.
        return false;
    }
}

/**
 * Fetches a record for auditing purposes, scoped to a company if necessary
 */
function itm_fetch_audit_record($conn, $table, $record_id, $company_id = null) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$table)) {
        return null;
    }

    $company_id = $company_id === null ? (int)($_SESSION['company_id'] ?? 0) : (int)$company_id;
    $hasCompanyColumn = itm_audit_table_has_column($conn, $table, 'company_id');

    $sql = 'SELECT * FROM `' . $table . '` WHERE id = ?';
    if ($hasCompanyColumn && $company_id > 0) {
        $sql .= ' AND company_id = ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    $record_id = (int)$record_id;
    if ($hasCompanyColumn && $company_id > 0) {
        mysqli_stmt_bind_param($stmt, 'ii', $record_id, $company_id);
    } else {
        mysqli_stmt_bind_param($stmt, 'i', $record_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Checks if a specific table has a given column (used for dynamic audit logic)
 */
function itm_audit_table_has_column($conn, $table, $column) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$table)) {
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$column)) {
        return false;
    }

    // INFORMATION_SCHEMA is used for compatibility across MySQL/MariaDB versions
    $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $exists;
}

/**
 * Fetches a record by its primary key ID from any table
 */
function itm_fetch_audit_record_by_id($conn, $table, $record_id) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$table)) {
        return null;
    }

    $sql = 'SELECT * FROM `' . $table . '` WHERE id = ? LIMIT 1';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    $record_id = (int)$record_id;
    mysqli_stmt_bind_param($stmt, 'i', $record_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

/**
 * Parses an SQL query to extract the action, table, and record ID for auditing
 * 
 * Used for "intercepted" SQL queries that aren't using the standard CRUD helpers.
 */
function itm_parse_audit_sql($sql) {
    $sql = trim((string)$sql);
    if ($sql === '') {
        return null;
    }

    $meta = null;
    if (preg_match('/^INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
        $meta = ['action' => 'INSERT', 'table' => $m[1], 'record_id' => 0];
    } elseif (preg_match('/^UPDATE\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
        $meta = ['action' => 'UPDATE', 'table' => $m[1], 'record_id' => 0];
    } elseif (preg_match('/^DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
        $meta = ['action' => 'DELETE', 'table' => $m[1], 'record_id' => 0];
    } else {
        return null;
    }

    // Avoid recursive auditing
    if ($meta['table'] === 'audit_logs') {
        return null;
    }

    // Try to extract the ID from a simple WHERE clause
    if (preg_match('/\bWHERE\b[\s\S]*?\bid\s*=\s*(\d+)/i', $sql, $idMatch)) {
        $meta['record_id'] = (int)$idMatch[1];
    }

    return $meta;
}
