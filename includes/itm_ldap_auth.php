<?php
/**
 * LDAP / SSO authentication helpers (v1 — LDAP bind + employee match only).
 */

if (!function_exists('itm_ldap_encryption_key')) {
    function itm_ldap_encryption_key()
    {
        return hash('sha256', (defined('DB_PASS') ? DB_PASS : 'itmanagement') . 'itm_company_sso_ldap_v1', true);
    }
}

if (!function_exists('itm_ldap_default_config')) {
    /**
     * @return array<string, mixed>
     */
    function itm_ldap_default_config()
    {
        return [
            'host' => '',
            'port' => 389,
            'bind_dn' => '',
            'bind_password' => '',
            'base_dn' => '',
            'user_filter' => '(&(objectClass=person)(|(sAMAccountName=%username%)(uid=%username%)(mail=%username%)))',
            'username_attr' => 'sAMAccountName',
            'email_attr' => 'mail',
        ];
    }
}

if (!function_exists('itm_ldap_normalize_config')) {
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    function itm_ldap_normalize_config(array $config)
    {
        $defaults = itm_ldap_default_config();
        $merged = array_merge($defaults, $config);
        $merged['host'] = trim((string)($merged['host'] ?? ''));
        $merged['port'] = (int)($merged['port'] ?? 389);
        if ($merged['port'] <= 0) {
            $merged['port'] = 389;
        }
        $merged['bind_dn'] = trim((string)($merged['bind_dn'] ?? ''));
        $merged['bind_password'] = (string)($merged['bind_password'] ?? '');
        $merged['base_dn'] = trim((string)($merged['base_dn'] ?? ''));
        $merged['user_filter'] = trim((string)($merged['user_filter'] ?? ''));
        if ($merged['user_filter'] === '') {
            $merged['user_filter'] = (string)$defaults['user_filter'];
        }
        $merged['username_attr'] = trim((string)($merged['username_attr'] ?? 'sAMAccountName'));
        if ($merged['username_attr'] === '') {
            $merged['username_attr'] = 'sAMAccountName';
        }
        $merged['email_attr'] = trim((string)($merged['email_attr'] ?? 'mail'));
        if ($merged['email_attr'] === '') {
            $merged['email_attr'] = 'mail';
        }

        return $merged;
    }
}

if (!function_exists('itm_ldap_encrypt_config')) {
    /**
     * @param array<string, mixed> $config
     */
    function itm_ldap_encrypt_config(array $config)
    {
        if (!function_exists('itm_encrypt')) {
            return null;
        }
        $normalized = itm_ldap_normalize_config($config);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return null;
        }

        return itm_encrypt($json, itm_ldap_encryption_key());
    }
}

if (!function_exists('itm_ldap_decrypt_config')) {
    /**
     * @return array<string, mixed>|null
     */
    function itm_ldap_decrypt_config($encrypted)
    {
        $encrypted = trim((string)$encrypted);
        if ($encrypted === '') {
            return itm_ldap_default_config();
        }
        if (!function_exists('itm_decrypt')) {
            return null;
        }
        $json = itm_decrypt($encrypted, itm_ldap_encryption_key());
        if ($json === false || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        return itm_ldap_normalize_config($decoded);
    }
}

if (!function_exists('itm_ldap_extension_available')) {
    function itm_ldap_extension_available()
    {
        return function_exists('ldap_connect');
    }
}

if (!function_exists('itm_sso_fetch_company_row')) {
    /**
     * @return array<string, mixed>|null
     */
    function itm_sso_fetch_company_row(mysqli $conn, int $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, company, incode, sso_enabled, sso_provider, sso_config_json_encrypted, active
             FROM companies
             WHERE id = ? AND active = 1 AND deleted_at IS NULL
             LIMIT 1'
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

if (!function_exists('itm_sso_resolve_company_for_login')) {
    /**
     * Resolve tenant for SSO login from company id, incode, or first SSO-enabled company.
     *
     * @return array<string, mixed>|null
     */
    function itm_sso_resolve_company_for_login(mysqli $conn, $companyHint)
    {
        $companyHint = trim((string)$companyHint);
        if ($companyHint !== '' && ctype_digit($companyHint) && (int)$companyHint > 0) {
            $row = itm_sso_fetch_company_row($conn, (int)$companyHint);
            if ($row && (int)($row['sso_enabled'] ?? 0) === 1) {
                return $row;
            }

            return null;
        }

        if ($companyHint !== '') {
            $incode = strtoupper(substr($companyHint, 0, 6));
            $stmt = mysqli_prepare(
                $conn,
                'SELECT id, company, incode, sso_enabled, sso_provider, sso_config_json_encrypted, active
                 FROM companies
                 WHERE UPPER(TRIM(COALESCE(incode, ""))) = ?
                   AND active = 1
                   AND deleted_at IS NULL
                   AND sso_enabled = 1
                 LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $incode);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if (is_array($row)) {
                    return $row;
                }
            }
        }

        $res = mysqli_query(
            $conn,
            'SELECT id, company, incode, sso_enabled, sso_provider, sso_config_json_encrypted, active
             FROM companies
             WHERE active = 1 AND deleted_at IS NULL AND sso_enabled = 1
             ORDER BY company ASC
             LIMIT 1'
        );
        if (!$res) {
            return null;
        }
        $row = mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_sso_any_company_enabled')) {
    function itm_sso_any_company_enabled(mysqli $conn)
    {
        $res = mysqli_query(
            $conn,
            'SELECT COUNT(*) AS c FROM companies WHERE active = 1 AND deleted_at IS NULL AND sso_enabled = 1'
        );
        if (!$res) {
            return false;
        }
        $row = mysqli_fetch_assoc($res);
        mysqli_free_result($res);

        return (int)($row['c'] ?? 0) > 0;
    }
}

if (!function_exists('itm_sso_list_enabled_companies')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function itm_sso_list_enabled_companies(mysqli $conn)
    {
        $rows = [];
        $res = mysqli_query(
            $conn,
            'SELECT id, company, incode
             FROM companies
             WHERE active = 1 AND deleted_at IS NULL AND sso_enabled = 1
             ORDER BY company ASC'
        );
        if (!$res) {
            return $rows;
        }
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        mysqli_free_result($res);

        return $rows;
    }
}

if (!function_exists('itm_ldap_apply_user_filter')) {
    function itm_ldap_apply_user_filter($filterTemplate, $username)
    {
        $username = trim((string)$username);
        $escaped = function_exists('ldap_escape')
            ? ldap_escape($username, '', LDAP_ESCAPE_FILTER)
            : preg_replace('/([\\\\*\\(\\)\\x00])/u', '\\\\$1', $username);

        return str_replace('%username%', $escaped, (string)$filterTemplate);
    }
}

if (!function_exists('itm_ldap_fetch_entry_attribute')) {
    function itm_ldap_fetch_entry_attribute($entry, $attributeName)
    {
        $attributeName = trim((string)$attributeName);
        if ($attributeName === '' || !is_array($entry)) {
            return '';
        }
        if (!isset($entry[$attributeName])) {
            return '';
        }
        $value = $entry[$attributeName];
        if (is_array($value)) {
            return trim((string)($value[0] ?? ''));
        }

        return trim((string)$value);
    }
}

if (!function_exists('itm_ldap_match_or_provision_employee')) {
    /**
     * Match an existing employee row for LDAP user data (no JIT provisioning in v1).
     *
     * @param array<string, mixed> $ldapUser
     * @return array<string, mixed>|null
     */
    function itm_ldap_match_or_provision_employee(mysqli $conn, int $companyId, array $ldapUser)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return null;
        }

        $ssoSubject = trim((string)($ldapUser['sso_subject'] ?? ''));
        $username = trim((string)($ldapUser['username'] ?? ''));
        $email = trim((string)($ldapUser['email'] ?? ''));

        $join = itm_employee_active_employment_status_join_sql('e', 'es');
        $activePredicate = itm_employee_active_employment_status_predicate_sql('es');

        if ($ssoSubject !== '') {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT e.*, er.name AS role_name
                 FROM employees e'
                . $join .
                ' LEFT JOIN employee_roles er ON e.role_id = er.id
                 WHERE e.company_id = ?
                   AND e.deleted_at IS NULL
                   AND ' . $activePredicate . '
                   AND e.sso_subject = ?
                 LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $companyId, $ssoSubject);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if (is_array($row)) {
                    return $row;
                }
            }
        }

        if ($email !== '') {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT e.*, er.name AS role_name
                 FROM employees e'
                . $join .
                ' LEFT JOIN employee_roles er ON e.role_id = er.id
                 WHERE e.company_id = ?
                   AND e.deleted_at IS NULL
                   AND ' . $activePredicate . '
                   AND LOWER(TRIM(COALESCE(e.work_email, ""))) = LOWER(?)
                 LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $companyId, $email);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if (is_array($row)) {
                    return $row;
                }
            }
        }

        if ($username !== '') {
            $stmt = mysqli_prepare(
                $conn,
                'SELECT e.*, er.name AS role_name
                 FROM employees e'
                . $join .
                ' LEFT JOIN employee_roles er ON e.role_id = er.id
                 WHERE e.company_id = ?
                   AND e.deleted_at IS NULL
                   AND ' . $activePredicate . '
                   AND LOWER(TRIM(COALESCE(e.username, ""))) = LOWER(?)
                 LIMIT 1'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $companyId, $username);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($stmt);
                if (is_array($row)) {
                    return $row;
                }
            }
        }

        return null;
    }
}

if (!function_exists('itm_ldap_auth_attempt')) {
    /**
     * @return array{ok:bool,employee?:array<string,mixed>,error?:string,ldap_user?:array<string,mixed>}
     */
    function itm_ldap_auth_attempt(mysqli $conn, int $companyId, $username, $password)
    {
        $companyId = (int)$companyId;
        $username = trim((string)$username);
        $password = (string)$password;

        if ($companyId <= 0) {
            return ['ok' => false, 'error' => 'Company is required for SSO login.'];
        }
        if ($username === '' || $password === '') {
            return ['ok' => false, 'error' => 'Username and password are required.'];
        }

        $company = itm_sso_fetch_company_row($conn, $companyId);
        if (!$company || (int)($company['sso_enabled'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'SSO is not enabled for this company.'];
        }
        if (strtolower(trim((string)($company['sso_provider'] ?? 'ldap'))) !== 'ldap') {
            return ['ok' => false, 'error' => 'Only LDAP SSO is supported in this release.'];
        }

        $config = itm_ldap_decrypt_config($company['sso_config_json_encrypted'] ?? '');
        if (!is_array($config) || trim((string)($config['host'] ?? '')) === '' || trim((string)($config['base_dn'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'LDAP is not configured for this company.'];
        }

        if (!itm_ldap_extension_available()) {
            return ['ok' => false, 'error' => 'LDAP extension is not loaded on this server.'];
        }

        $ldapUri = 'ldap://' . $config['host'] . ':' . (int)$config['port'];
        $ldapConn = @ldap_connect($ldapUri);
        if ($ldapConn === false) {
            return ['ok' => false, 'error' => 'Could not connect to the LDAP server.'];
        }

        ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);

        if (trim((string)($config['bind_dn'] ?? '')) !== '') {
            if (!@ldap_bind($ldapConn, (string)$config['bind_dn'], (string)$config['bind_password'])) {
                return ['ok' => false, 'error' => 'LDAP service bind failed.'];
            }
        }

        $filter = itm_ldap_apply_user_filter($config['user_filter'], $username);
        $search = @ldap_search(
            $ldapConn,
            (string)$config['base_dn'],
            $filter,
            [(string)$config['username_attr'], (string)$config['email_attr']]
        );
        if ($search === false) {
            return ['ok' => false, 'error' => 'LDAP user search failed.'];
        }

        $entries = ldap_get_entries($ldapConn, $search);
        if (!is_array($entries) || (int)($entries['count'] ?? 0) < 1) {
            return ['ok' => false, 'error' => 'Invalid credentials.'];
        }

        $entry = $entries[0];
        $userDn = trim((string)($entry['dn'] ?? ''));
        if ($userDn === '') {
            return ['ok' => false, 'error' => 'LDAP user record is missing a DN.'];
        }

        if (!@ldap_bind($ldapConn, $userDn, $password)) {
            return ['ok' => false, 'error' => 'Invalid credentials.'];
        }

        $ldapUsername = itm_ldap_fetch_entry_attribute($entry, (string)$config['username_attr']);
        if ($ldapUsername === '') {
            $ldapUsername = $username;
        }
        $ldapEmail = itm_ldap_fetch_entry_attribute($entry, (string)$config['email_attr']);

        $ldapUser = [
            'sso_subject' => $userDn,
            'username' => $ldapUsername,
            'email' => $ldapEmail,
        ];

        $employee = itm_ldap_match_or_provision_employee($conn, $companyId, $ldapUser);
        if (!is_array($employee)) {
            return [
                'ok' => false,
                'error' => 'No matching employee account was found for this LDAP user.',
                'ldap_user' => $ldapUser,
            ];
        }

        if (trim((string)($employee['sso_subject'] ?? '')) === '' && $userDn !== '') {
            $employeeId = (int)($employee['id'] ?? 0);
            if ($employeeId > 0) {
                $updateStmt = mysqli_prepare(
                    $conn,
                    'UPDATE employees SET sso_subject = ? WHERE id = ? AND company_id = ? LIMIT 1'
                );
                if ($updateStmt) {
                    mysqli_stmt_bind_param($updateStmt, 'sii', $userDn, $employeeId, $companyId);
                    mysqli_stmt_execute($updateStmt);
                    mysqli_stmt_close($updateStmt);
                    $employee['sso_subject'] = $userDn;
                }
            }
        }

        return ['ok' => true, 'employee' => $employee, 'ldap_user' => $ldapUser];
    }
}

if (!function_exists('itm_sso_finalize_employee_login_session')) {
    /**
     * Mirror login.php session stamping after successful SSO authentication.
     *
     * @param array<string, mixed> $employeeRow
     */
    function itm_sso_finalize_employee_login_session(mysqli $conn, array $employeeRow)
    {
        $employeeId = (int)($employeeRow['id'] ?? 0);
        if ($employeeId <= 0) {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $resolvedEmail = trim((string)($employeeRow['work_email'] ?? ''));
        if ($resolvedEmail === '') {
            $resolvedEmail = trim((string)($employeeRow['personal_email'] ?? ''));
        }

        $_SESSION['employee_id'] = $employeeId;
        $_SESSION['login_employee_id'] = $employeeId;
        $_SESSION['username'] = (string)($employeeRow['username'] ?? 'User');
        $_SESSION['email'] = $resolvedEmail;
        $_SESSION['role_name'] = (string)($employeeRow['role_name'] ?? '');
        $_SESSION['ui_theme'] = (strtolower(trim((string)($employeeRow['theme'] ?? 'light'))) === 'dark') ? 'dark' : 'light';
        unset($_SESSION['company_id'], $_SESSION['company_name']);

        $companyId = (int)($employeeRow['company_id'] ?? 0);
        $isAdmin = function_exists('itm_is_admin') && itm_is_admin($conn, $employeeId);
        $_SESSION['read_only_user_config'] = 0;

        if ($isAdmin) {
            $_SESSION['role_name'] = 'admin';
            if ($companyId > 0 && function_exists('itm_switch_active_company_session')) {
                itm_switch_active_company_session($conn, $employeeId, $companyId, true);
            } elseif ($companyId > 0) {
                $companyStmt = mysqli_prepare($conn, 'SELECT company FROM companies WHERE id = ? LIMIT 1');
                if ($companyStmt) {
                    mysqli_stmt_bind_param($companyStmt, 'i', $companyId);
                    mysqli_stmt_execute($companyStmt);
                    $companyRes = mysqli_stmt_get_result($companyStmt);
                    $company = $companyRes ? mysqli_fetch_assoc($companyRes) : null;
                    mysqli_stmt_close($companyStmt);
                    if ($company) {
                        $_SESSION['company_id'] = $companyId;
                        $_SESSION['company_name'] = (string)($company['company'] ?? '');
                    }
                }
            }

            return 'dashboard';
        }

        if (!itm_employee_has_active_employment_status($conn, $employeeId)) {
            $_SESSION['read_only_user_config'] = 1;

            return 'user-config';
        }

        if ($companyId > 0 && function_exists('itm_switch_active_company_session')) {
            itm_switch_active_company_session($conn, $employeeId, $companyId, false);
        }

        if (function_exists('itm_try_auto_select_single_company_session')
            && itm_try_auto_select_single_company_session($conn, $employeeId, false)) {
            return 'dashboard';
        }

        return 'index';
    }
}
