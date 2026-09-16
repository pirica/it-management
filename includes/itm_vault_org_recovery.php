<?php
/**
 * Tenant-controlled vault org recovery — company policy, employee consent, escrow, audit requests.
 */

if (!function_exists('itm_vault_org_recovery_storage_key')) {
    function itm_vault_org_recovery_storage_key($companyId)
    {
        $companyId = (int)$companyId;
        $secret = defined('DB_PASS') ? (string)DB_PASS : 'itmanagement';

        return hash('sha256', $secret . 'itm_vault_org_recovery_v1_' . $companyId, true);
    }
}

if (!function_exists('itm_vault_org_recovery_company_row')) {
    /**
     * @return array<string, mixed>|null
     */
    function itm_vault_org_recovery_company_row(mysqli $conn, $companyId)
    {
        $companyId = (int)$companyId;
        if ($companyId <= 0) {
            return null;
        }
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, company, vault_org_recovery_enabled, vault_org_recovery_passphrase_hash, vault_org_recovery_escrow_key_encrypted
             FROM companies WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'i', $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_vault_org_recovery_company_enabled')) {
    function itm_vault_org_recovery_company_enabled(array $companyRow)
    {
        return (int)($companyRow['vault_org_recovery_enabled'] ?? 0) === 1;
    }
}

if (!function_exists('itm_vault_org_recovery_employee_has_consent')) {
    function itm_vault_org_recovery_employee_has_consent(array $employeeRow)
    {
        return !empty($employeeRow['vault_org_recovery_consent_at']);
    }
}

if (!function_exists('itm_vault_org_recovery_employee_has_escrow')) {
    function itm_vault_org_recovery_employee_has_escrow(array $employeeRow)
    {
        return trim((string)($employeeRow['vault_key_escrow_encrypted'] ?? '')) !== '';
    }
}

if (!function_exists('itm_vault_org_recovery_generate_escrow_key')) {
    function itm_vault_org_recovery_generate_escrow_key()
    {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('itm_vault_org_recovery_encrypt_escrow_key')) {
    function itm_vault_org_recovery_encrypt_escrow_key($rawEscrowKey, $companyId)
    {
        $rawEscrowKey = trim((string)$rawEscrowKey);
        if ($rawEscrowKey === '') {
            return null;
        }

        return itm_encrypt($rawEscrowKey, itm_vault_org_recovery_storage_key($companyId));
    }
}

if (!function_exists('itm_vault_org_recovery_decrypt_escrow_key')) {
    function itm_vault_org_recovery_decrypt_escrow_key($encrypted, $companyId)
    {
        $encrypted = trim((string)$encrypted);
        if ($encrypted === '') {
            return null;
        }
        $plain = itm_decrypt($encrypted, itm_vault_org_recovery_storage_key($companyId));

        return is_string($plain) && $plain !== '' ? $plain : null;
    }
}

if (!function_exists('itm_vault_org_recovery_company_escrow_key')) {
    function itm_vault_org_recovery_company_escrow_key(array $companyRow)
    {
        $encrypted = trim((string)($companyRow['vault_org_recovery_escrow_key_encrypted'] ?? ''));
        if ($encrypted === '') {
            return null;
        }

        return itm_vault_org_recovery_decrypt_escrow_key($encrypted, (int)($companyRow['id'] ?? 0));
    }
}

if (!function_exists('itm_vault_org_recovery_hash_admin_passphrase')) {
    function itm_vault_org_recovery_hash_admin_passphrase($passphrase)
    {
        $passphrase = (string)$passphrase;
        if ($passphrase === '') {
            return null;
        }

        return password_hash($passphrase, PASSWORD_DEFAULT);
    }
}

if (!function_exists('itm_vault_org_recovery_verify_admin_passphrase')) {
    function itm_vault_org_recovery_verify_admin_passphrase($passphrase, $hash)
    {
        $hash = (string)$hash;
        if ($hash === '') {
            return false;
        }

        return password_verify((string)$passphrase, $hash);
    }
}

if (!function_exists('itm_vault_org_recovery_build_employee_escrow')) {
    function itm_vault_org_recovery_build_employee_escrow($plaintextMasterKey, array $companyRow)
    {
        $plaintextMasterKey = (string)$plaintextMasterKey;
        if ($plaintextMasterKey === '') {
            return null;
        }
        $companyEscrowKey = itm_vault_org_recovery_company_escrow_key($companyRow);
        if ($companyEscrowKey === null) {
            return null;
        }

        return itm_encrypt($plaintextMasterKey, hash('sha256', $companyEscrowKey, true));
    }
}

if (!function_exists('itm_vault_org_recovery_decrypt_employee_escrow')) {
    function itm_vault_org_recovery_decrypt_employee_escrow($encrypted, array $companyRow)
    {
        $encrypted = trim((string)$encrypted);
        if ($encrypted === '') {
            return null;
        }
        $companyEscrowKey = itm_vault_org_recovery_company_escrow_key($companyRow);
        if ($companyEscrowKey === null) {
            return null;
        }
        $plain = itm_decrypt($encrypted, hash('sha256', $companyEscrowKey, true));

        return is_string($plain) && $plain !== '' ? $plain : null;
    }
}

if (!function_exists('itm_vault_org_recovery_ensure_company_escrow_key')) {
    /**
     * @return array{ok:bool,message:string,escrow_key_encrypted?:string}
     */
    function itm_vault_org_recovery_ensure_company_escrow_key(mysqli $conn, $companyId, array $companyRow = null)
    {
        $companyId = (int)$companyId;
        if ($companyRow === null) {
            $companyRow = itm_vault_org_recovery_company_row($conn, $companyId);
        }
        if (!is_array($companyRow)) {
            return ['ok' => false, 'message' => 'Company not found.'];
        }
        $existing = trim((string)($companyRow['vault_org_recovery_escrow_key_encrypted'] ?? ''));
        if ($existing !== '') {
            return ['ok' => true, 'message' => 'Escrow key already present.', 'escrow_key_encrypted' => $existing];
        }
        $rawKey = itm_vault_org_recovery_generate_escrow_key();
        $encrypted = itm_vault_org_recovery_encrypt_escrow_key($rawKey, $companyId);
        if ($encrypted === null) {
            return ['ok' => false, 'message' => 'Failed to encrypt company escrow key.'];
        }

        return ['ok' => true, 'message' => 'Escrow key generated.', 'escrow_key_encrypted' => $encrypted];
    }
}

if (!function_exists('itm_vault_org_recovery_sync_employee_escrow')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_vault_org_recovery_sync_employee_escrow(mysqli $conn, $employeeId, $companyId, $plaintextMasterKey, array $employeeRow = null, array $companyRow = null)
    {
        $employeeId = (int)$employeeId;
        $companyId = (int)$companyId;
        if ($employeeRow === null) {
            $stmt = mysqli_prepare($conn, 'SELECT * FROM employees WHERE id = ? AND company_id = ? LIMIT 1');
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Failed to load employee.'];
            }
            mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $companyId);
            mysqli_stmt_execute($stmt);
            $employeeRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
        }
        if (!is_array($employeeRow)) {
            return ['ok' => false, 'message' => 'Employee not found.'];
        }
        if ($companyRow === null) {
            $companyRow = itm_vault_org_recovery_company_row($conn, $companyId);
        }
        if (!is_array($companyRow) || !itm_vault_org_recovery_company_enabled($companyRow)) {
            return ['ok' => true, 'message' => 'Org recovery disabled for company.'];
        }
        if (!itm_vault_org_recovery_employee_has_consent($employeeRow)) {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE employees SET vault_key_escrow_encrypted = NULL, updated_by = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ?'
            );
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Failed to clear escrow.'];
            }
            $sessionEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
            mysqli_stmt_bind_param($stmt, 'iii', $sessionEmployeeId, $employeeId, $companyId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return ['ok' => true, 'message' => 'Consent absent — escrow cleared.'];
        }
        $escrowEncrypted = itm_vault_org_recovery_build_employee_escrow($plaintextMasterKey, $companyRow);
        if ($escrowEncrypted === null) {
            return ['ok' => false, 'message' => 'Failed to build employee escrow.'];
        }
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE employees SET vault_key_escrow_encrypted = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ?'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Failed to save employee escrow.'];
        }
        $sessionEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
        mysqli_stmt_bind_param($stmt, 'siii', $escrowEncrypted, $sessionEmployeeId, $employeeId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok
            ? ['ok' => true, 'message' => 'Employee escrow updated.']
            : ['ok' => false, 'message' => 'Failed to persist employee escrow.'];
    }
}

if (!function_exists('itm_vault_org_recovery_grant_consent')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_vault_org_recovery_grant_consent(mysqli $conn, $employeeId, $companyId, $consentReference = '')
    {
        $employeeId = (int)$employeeId;
        $companyId = (int)$companyId;
        $companyRow = itm_vault_org_recovery_company_row($conn, $companyId);
        if (!is_array($companyRow) || !itm_vault_org_recovery_company_enabled($companyRow)) {
            return ['ok' => false, 'message' => 'Org recovery is not enabled for your company.'];
        }
        $consentReference = trim((string)$consentReference);
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE employees SET vault_org_recovery_consent_at = NOW(), vault_org_recovery_consent_reference = ?, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ?'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Failed to save consent.'];
        }
        $sessionEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
        mysqli_stmt_bind_param($stmt, 'siii', $consentReference, $sessionEmployeeId, $employeeId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok
            ? ['ok' => true, 'message' => 'Consent recorded. Re-save your vault key to refresh escrow.']
            : ['ok' => false, 'message' => 'Failed to record consent.'];
    }
}

if (!function_exists('itm_vault_org_recovery_revoke_consent')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_vault_org_recovery_revoke_consent(mysqli $conn, $employeeId, $companyId)
    {
        $employeeId = (int)$employeeId;
        $companyId = (int)$companyId;
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE employees SET vault_org_recovery_consent_at = NULL, vault_org_recovery_consent_reference = NULL,
             vault_key_escrow_encrypted = NULL, updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ?'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Failed to revoke consent.'];
        }
        $sessionEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
        mysqli_stmt_bind_param($stmt, 'iii', $sessionEmployeeId, $employeeId, $companyId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok
            ? ['ok' => true, 'message' => 'Consent revoked and escrow cleared.']
            : ['ok' => false, 'message' => 'Failed to revoke consent.'];
    }
}

if (!function_exists('itm_vault_org_recovery_create_request')) {
    /**
     * @return array{ok:bool,message:string,request_id?:int}
     */
    function itm_vault_org_recovery_create_request(mysqli $conn, $companyId, $requesterEmployeeId, $targetEmployeeId, $legalReference, $requestNotes = '')
    {
        $companyId = (int)$companyId;
        $requesterEmployeeId = (int)$requesterEmployeeId;
        $targetEmployeeId = (int)$targetEmployeeId;
        $legalReference = trim((string)$legalReference);
        $requestNotes = trim((string)$requestNotes);

        if (!itm_is_admin($conn, $requesterEmployeeId)) {
            return ['ok' => false, 'message' => 'Administrator access required.'];
        }
        if ($legalReference === '') {
            return ['ok' => false, 'message' => 'Legal / HR reference is required.'];
        }
        $companyRow = itm_vault_org_recovery_company_row($conn, $companyId);
        if (!is_array($companyRow) || !itm_vault_org_recovery_company_enabled($companyRow)) {
            return ['ok' => false, 'message' => 'Org recovery is not enabled for this company.'];
        }
        $stmt = mysqli_prepare($conn, 'SELECT * FROM employees WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Failed to load target employee.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $targetEmployeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $employeeRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!is_array($employeeRow)) {
            return ['ok' => false, 'message' => 'Target employee not found.'];
        }
        if (!itm_vault_org_recovery_employee_has_consent($employeeRow)) {
            return ['ok' => false, 'message' => 'Employee has not consented to org recovery.'];
        }
        if (!itm_vault_org_recovery_employee_has_escrow($employeeRow)) {
            return ['ok' => false, 'message' => 'No escrow snapshot exists — employee must save vault key after consent.'];
        }

        $consentReference = trim((string)($employeeRow['vault_org_recovery_consent_reference'] ?? ''));
        $consentVerifiedAt = (string)($employeeRow['vault_org_recovery_consent_at'] ?? '');
        $sql = 'INSERT INTO vault_org_recovery_requests
            (company_id, employee_id, status, legal_reference, consent_reference, consent_verified_at, request_notes, requester_employee_id, created_by, active)
            VALUES (?, ?, \'pending\', ?, ?, ?, ?, ?, ?, 1)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Failed to create recovery request.'];
        }
        mysqli_stmt_bind_param(
            $stmt,
            'iissssii',
            $companyId,
            $targetEmployeeId,
            $legalReference,
            $consentReference,
            $consentVerifiedAt,
            $requestNotes,
            $requesterEmployeeId,
            $requesterEmployeeId
        );
        $ok = mysqli_stmt_execute($stmt);
        $requestId = $ok ? (int)mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($stmt);

        return $ok
            ? ['ok' => true, 'message' => 'Recovery request created.', 'request_id' => $requestId]
            : ['ok' => false, 'message' => 'Failed to create recovery request.'];
    }
}

if (!function_exists('itm_vault_org_recovery_fetch_request')) {
    /**
     * @return array<string, mixed>|null
     */
    function itm_vault_org_recovery_fetch_request(mysqli $conn, $companyId, $requestId)
    {
        $companyId = (int)$companyId;
        $requestId = (int)$requestId;
        $stmt = mysqli_prepare(
            $conn,
            'SELECT r.*, e.first_name, e.last_name, e.username
             FROM vault_org_recovery_requests r
             INNER JOIN employees e ON e.id = r.employee_id AND e.company_id = r.company_id
             WHERE r.id = ? AND r.company_id = ? AND r.deleted_at IS NULL
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $requestId, $companyId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_vault_org_recovery_complete_request')) {
    /**
     * @return array{ok:bool,message:string,master_key?:string}
     */
    function itm_vault_org_recovery_complete_request(mysqli $conn, $companyId, $adminEmployeeId, $requestId, $adminPassphrase, $completionNotes = '')
    {
        $companyId = (int)$companyId;
        $adminEmployeeId = (int)$adminEmployeeId;
        $requestId = (int)$requestId;
        $adminPassphrase = (string)$adminPassphrase;
        $completionNotes = trim((string)$completionNotes);

        if (!itm_is_admin($conn, $adminEmployeeId)) {
            return ['ok' => false, 'message' => 'Administrator access required.'];
        }
        $request = itm_vault_org_recovery_fetch_request($conn, $companyId, $requestId);
        if (!is_array($request)) {
            return ['ok' => false, 'message' => 'Recovery request not found.'];
        }
        if ((string)($request['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'message' => 'Only pending requests can be completed.'];
        }
        $companyRow = itm_vault_org_recovery_company_row($conn, $companyId);
        if (!is_array($companyRow) || !itm_vault_org_recovery_company_enabled($companyRow)) {
            return ['ok' => false, 'message' => 'Org recovery is not enabled for this company.'];
        }
        if (!itm_vault_org_recovery_verify_admin_passphrase($adminPassphrase, (string)($companyRow['vault_org_recovery_passphrase_hash'] ?? ''))) {
            return ['ok' => false, 'message' => 'Recovery authorization passphrase is incorrect.'];
        }

        $targetEmployeeId = (int)($request['employee_id'] ?? 0);
        $stmt = mysqli_prepare($conn, 'SELECT * FROM employees WHERE id = ? AND company_id = ? LIMIT 1');
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Failed to load employee escrow.'];
        }
        mysqli_stmt_bind_param($stmt, 'ii', $targetEmployeeId, $companyId);
        mysqli_stmt_execute($stmt);
        $employeeRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!is_array($employeeRow)) {
            return ['ok' => false, 'message' => 'Employee not found.'];
        }
        $masterKey = itm_vault_org_recovery_decrypt_employee_escrow((string)($employeeRow['vault_key_escrow_encrypted'] ?? ''), $companyRow);
        if ($masterKey === null) {
            return ['ok' => false, 'message' => 'Failed to decrypt escrow snapshot.'];
        }

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE vault_org_recovery_requests
                 SET status = \'completed\', completion_notes = ?, completed_by_employee_id = ?, completed_at = NOW(), updated_by = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ? AND status = \'pending\''
            );
            if (!$stmt) {
                throw new RuntimeException('Failed to update recovery request.');
            }
            mysqli_stmt_bind_param($stmt, 'siiii', $completionNotes, $adminEmployeeId, $adminEmployeeId, $requestId, $companyId);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_affected_rows($stmt) < 1) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Recovery request is no longer pending.');
            }
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare(
                $conn,
                'UPDATE employees SET vault_key_escrow_encrypted = NULL, updated_by = ?, updated_at = NOW()
                 WHERE id = ? AND company_id = ?'
            );
            if (!$stmt) {
                throw new RuntimeException('Failed to clear employee escrow.');
            }
            mysqli_stmt_bind_param($stmt, 'iii', $adminEmployeeId, $targetEmployeeId, $companyId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        itm_log_audit($conn, 'vault_org_recovery_requests', $requestId, 'UPDATE', ['status' => 'pending'], [
            'status' => 'completed',
            'employee_id' => $targetEmployeeId,
            'legal_reference' => $request['legal_reference'] ?? '',
            'completed_by_employee_id' => $adminEmployeeId,
        ]);

        return ['ok' => true, 'message' => 'Recovery completed.', 'master_key' => $masterKey];
    }
}

if (!function_exists('itm_vault_org_recovery_reject_request')) {
    /**
     * @return array{ok:bool,message:string}
     */
    function itm_vault_org_recovery_reject_request(mysqli $conn, $companyId, $adminEmployeeId, $requestId, $completionNotes = '')
    {
        $companyId = (int)$companyId;
        $adminEmployeeId = (int)$adminEmployeeId;
        $requestId = (int)$requestId;
        $completionNotes = trim((string)$completionNotes);

        if (!itm_is_admin($conn, $adminEmployeeId)) {
            return ['ok' => false, 'message' => 'Administrator access required.'];
        }
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE vault_org_recovery_requests
             SET status = \'rejected\', completion_notes = ?, completed_by_employee_id = ?, completed_at = NOW(), updated_by = ?, updated_at = NOW()
             WHERE id = ? AND company_id = ? AND status = \'pending\''
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Failed to reject request.'];
        }
        mysqli_stmt_bind_param($stmt, 'siiii', $completionNotes, $adminEmployeeId, $adminEmployeeId, $requestId, $companyId);
        mysqli_stmt_execute($stmt);
        $ok = mysqli_stmt_affected_rows($stmt) > 0;
        mysqli_stmt_close($stmt);

        return $ok
            ? ['ok' => true, 'message' => 'Recovery request rejected.']
            : ['ok' => false, 'message' => 'Request not found or not pending.'];
    }
}

if (!function_exists('itm_vault_org_recovery_list_requests')) {
    /**
     * @param array<string, mixed> $options
     * @return array<int, array<string, mixed>>
     */
    function itm_vault_org_recovery_list_requests(mysqli $conn, $companyId, array $options = [])
    {
        $companyId = (int)$companyId;
        $status = trim((string)($options['status'] ?? ''));
        $search = trim((string)($options['search'] ?? ''));
        $limit = max(1, (int)($options['limit'] ?? 50));
        $offset = max(0, (int)($options['offset'] ?? 0));

        $sql = 'SELECT r.*, e.first_name, e.last_name, e.username
                FROM vault_org_recovery_requests r
                INNER JOIN employees e ON e.id = r.employee_id AND e.company_id = r.company_id
                WHERE r.company_id = ? AND r.deleted_at IS NULL';
        $types = 'i';
        $params = [$companyId];

        if ($status !== '' && in_array($status, ['pending', 'completed', 'rejected', 'cancelled'], true)) {
            $sql .= ' AND r.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if ($search !== '') {
            $sql .= ' AND (r.legal_reference LIKE ? OR r.consent_reference LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.username LIKE ?)';
            $types .= 'sssss';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY r.created_at DESC LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}

if (!function_exists('itm_vault_org_recovery_count_requests')) {
    function itm_vault_org_recovery_count_requests(mysqli $conn, $companyId, array $options = [])
    {
        $companyId = (int)$companyId;
        $status = trim((string)($options['status'] ?? ''));
        $search = trim((string)($options['search'] ?? ''));

        $sql = 'SELECT COUNT(*) AS c
                FROM vault_org_recovery_requests r
                INNER JOIN employees e ON e.id = r.employee_id AND e.company_id = r.company_id
                WHERE r.company_id = ? AND r.deleted_at IS NULL';
        $types = 'i';
        $params = [$companyId];

        if ($status !== '' && in_array($status, ['pending', 'completed', 'rejected', 'cancelled'], true)) {
            $sql .= ' AND r.status = ?';
            $types .= 's';
            $params[] = $status;
        }
        if ($search !== '') {
            $sql .= ' AND (r.legal_reference LIKE ? OR r.consent_reference LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR e.username LIKE ?)';
            $types .= 'sssss';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }
}

if (!function_exists('itm_vault_org_recovery_employee_label')) {
    function itm_vault_org_recovery_employee_label(array $row)
    {
        $first = trim((string)($row['first_name'] ?? ''));
        $last = trim((string)($row['last_name'] ?? ''));
        $full = trim($first . ' ' . $last);
        if ($full !== '') {
            return $full;
        }

        return trim((string)($row['username'] ?? '')) !== '' ? (string)$row['username'] : ('Employee #' . (int)($row['employee_id'] ?? $row['id'] ?? 0));
    }
}
