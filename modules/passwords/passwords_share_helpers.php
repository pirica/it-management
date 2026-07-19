<?php
/**
 * Temporary QR / code share sessions for Passwords vault entries.
 */

require_once ROOT_PATH . 'includes/itm_qr_share.php';

function passwords_share_table_name()
{
    return 'password_share_sessions';
}

function passwords_share_join_script_path()
{
    return 'modules/passwords/join.php';
}

/**
 * @return array<string,mixed>|null
 */
function passwords_share_build_payload_from_entry(array $entry, $vaultKey, $ownerUsername)
{
    $vaultKey = (string)$vaultKey;
    if ($vaultKey === '') {
        return null;
    }

    $account = (string)($entry['account'] ?? '');
    $passwordPlain = itm_decrypt((string)($entry['password'] ?? ''), $vaultKey);

    return [
        'type' => 'password',
        'heading' => $account !== '' ? $account : 'Password',
        'owner_username' => (string)$ownerUsername,
        'account' => $account,
        'login_name' => (string)($entry['login_name'] ?? ''),
        'password' => $passwordPlain,
        'website' => (string)($entry['website'] ?? ''),
        'comments' => (string)($entry['comments'] ?? ''),
    ];
}

function passwords_share_build_join_url($accessToken)
{
    return itm_qr_share_build_join_url(passwords_share_join_script_path(), $accessToken);
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function passwords_share_create_session($conn, $entryId, $companyId, $employeeId, $ownerUsername, $vaultUnlocked)
{
    $entryId = (int)$entryId;
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($entryId <= 0 || $companyId <= 0 || $employeeId <= 0 || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    if (!$vaultUnlocked || empty($_SESSION['vault_key'])) {
        return ['ok' => false, 'error' => 'Unlock your vault before sharing a password.'];
    }

    $stmt = $conn->prepare(
        'SELECT * FROM password_entries WHERE id = ? AND employee_id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not load password entry.'];
    }
    $stmt->bind_param('iii', $entryId, $employeeId, $companyId);
    $stmt->execute();
    $entry = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$entry) {
        return ['ok' => false, 'error' => 'Password entry not found.'];
    }

    $payload = passwords_share_build_payload_from_entry($entry, (string)$_SESSION['vault_key'], $ownerUsername);
    if ($payload === null) {
        return ['ok' => false, 'error' => 'Could not prepare password for sharing.'];
    }

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return ['ok' => false, 'error' => 'Could not encode share payload.'];
    }

    return itm_qr_share_create_session($conn, passwords_share_table_name(), [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'record_id' => $entryId,
        'payload_json' => $payloadJson,
    ]);
}
