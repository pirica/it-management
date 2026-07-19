<?php
/**
 * Temporary QR / code share sessions for Private Contacts.
 */

require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once __DIR__ . '/pc_vault_helpers.php';

function pc_share_table_name()
{
    return 'private_contact_share_sessions';
}

function pc_share_join_script_path()
{
    return 'modules/private_contacts/join.php';
}

/**
 * @return array<string,mixed>
 */
function pc_share_build_payload_from_contact(array $contact, $ownerUsername)
{
    pc_hydrate_contact_row($contact);

    $name = pc_contact_display_name($contact);
    $orgParts = array_filter([
        trim((string)($contact['organization_title'] ?? '')),
        trim((string)($contact['organization_name'] ?? '')),
    ]);
    $orgLine = implode(' at ', $orgParts);

    return [
        'type' => 'private_contact',
        'heading' => $name !== '' ? $name : 'Private Contact',
        'owner_username' => (string)$ownerUsername,
        'name' => $name,
        'email' => (string)($contact['email1_value'] ?? ''),
        'phone' => (string)($contact['phone1_value'] ?? ''),
        'organization' => $orgLine,
        'labels' => (string)($contact['labels'] ?? ''),
        'notes' => (string)($contact['notes'] ?? ''),
        'website' => (string)($contact['website1_value'] ?? ''),
        'address_street' => (string)($contact['address1_street'] ?? ''),
        'address_city' => (string)($contact['address1_city'] ?? ''),
        'address_region' => (string)($contact['address1_region'] ?? ''),
        'address_postcode' => (string)($contact['address1_postcode'] ?? ''),
        'address_country' => (string)($contact['address1_country'] ?? ''),
    ];
}

function pc_share_build_join_url($accessToken)
{
    return itm_qr_share_build_join_url(pc_share_join_script_path(), $accessToken);
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function pc_share_create_session($conn, $contactId, $companyId, $employeeId, $ownerUsername, $vaultUnlocked)
{
    $contactId = (int)$contactId;
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($contactId <= 0 || $companyId <= 0 || $employeeId <= 0 || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    if (!$vaultUnlocked || empty($_SESSION['vault_key'])) {
        return ['ok' => false, 'error' => 'Unlock your vault before sharing a contact.'];
    }

    $stmt = $conn->prepare(
        'SELECT * FROM private_contacts WHERE id = ? AND employee_id = ? AND company_id = ? AND active = 1 AND deleted_at IS NULL LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not load contact.'];
    }
    $stmt->bind_param('iii', $contactId, $employeeId, $companyId);
    $stmt->execute();
    $contact = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$contact) {
        return ['ok' => false, 'error' => 'Contact not found.'];
    }

    $payload = pc_share_build_payload_from_contact($contact, $ownerUsername);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return ['ok' => false, 'error' => 'Could not encode share payload.'];
    }

    return itm_qr_share_create_session($conn, pc_share_table_name(), [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'record_id' => $contactId,
        'payload_json' => $payloadJson,
    ]);
}
