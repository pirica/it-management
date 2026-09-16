<?php
/**
 * Vault encryption helpers for private_contacts (always employee-private; no in-app share).
 */

function pc_vault_encrypted_field_names()
{
    return [
        'name_prefix', 'first_name', 'middle_name', 'last_name', 'name_suffix',
        'phonetic_first_name', 'phonetic_middle_name', 'phonetic_last_name',
        'nickname', 'file_as',
        'email1_label', 'email1_value', 'phone1_label', 'phone1_value',
        'address1_label', 'address1_country', 'address1_street', 'address1_extended',
        'address1_city', 'address1_region', 'address1_postcode', 'address1_po_box',
        'organization_name', 'organization_title', 'organization_department',
        'event1_label', 'relation1_label', 'relation1_value',
        'website1_label', 'website1_value',
        'custom_field1_label', 'custom_field1_value',
        'notes', 'labels',
    ];
}

function pc_vault_session_key()
{
    return isset($_SESSION['vault_key']) ? (string)$_SESSION['vault_key'] : '';
}

function pc_private_text_legacy_plaintext_check($stored)
{
    $stored = (string)$stored;
    if ($stored === '') {
        return true;
    }

    $decoded = base64_decode($stored, true);
    if ($decoded === false) {
        return true;
    }

    $ivLen = openssl_cipher_iv_length('aes-256-cbc');

    return strlen($decoded) <= $ivLen;
}

/**
 * @return array{text:string,locked:bool,label:string}
 */
function pc_resolve_private_field($stored, array $options = [])
{
    $stored = (string)$stored;
    $vaultLabel = (string)($options['vault_label'] ?? '🔒 Unlock vault to view');
    $decryptFailLabel = (string)($options['decrypt_fail_label'] ?? '🔒 Unable to decrypt');
    $legacyPlaintextCheck = $options['legacy_plaintext_check'] ?? 'pc_private_text_legacy_plaintext_check';

    $vaultKey = pc_vault_session_key();
    if ($vaultKey === '') {
        return ['text' => '', 'locked' => true, 'label' => $vaultLabel];
    }

    $plain = itm_decrypt($stored, $vaultKey);
    if ($plain !== false) {
        return ['text' => $plain, 'locked' => false, 'label' => ''];
    }

    if (is_callable($legacyPlaintextCheck) && $legacyPlaintextCheck($stored)) {
        return ['text' => $stored, 'locked' => false, 'label' => ''];
    }

    return ['text' => '', 'locked' => true, 'label' => $decryptFailLabel];
}

function pc_encrypt_plain_field($plainText, $vaultKey = null)
{
    $plainText = (string)$plainText;
    $vaultKey = $vaultKey !== null ? (string)$vaultKey : pc_vault_session_key();
    if ($vaultKey === '') {
        return null;
    }
    if ($plainText === '') {
        return '';
    }

    return itm_encrypt($plainText, $vaultKey);
}

function pc_hydrate_contact_row(array &$row)
{
    foreach (pc_vault_encrypted_field_names() as $field) {
        if (!array_key_exists($field, $row)) {
            continue;
        }
        $resolved = pc_resolve_private_field((string)($row[$field] ?? ''));
        $row[$field] = $resolved['text'];
        $row[$field . '_locked'] = $resolved['locked'];
        $row[$field . '_locked_label'] = $resolved['label'];
    }
}

/**
 * @return array<string,string>|null null when vault is locked
 */
function pc_prepare_contact_fields_from_plain(array $plainRow, $vaultKey = null)
{
    $vaultKey = $vaultKey !== null ? (string)$vaultKey : pc_vault_session_key();
    if ($vaultKey === '') {
        return null;
    }

    $stored = [];
    foreach (pc_vault_encrypted_field_names() as $field) {
        $plain = (string)($plainRow[$field] ?? '');
        if ($plain === '') {
            $stored[$field] = '';
            continue;
        }
        $encrypted = itm_encrypt($plain, $vaultKey);
        if ($encrypted === false) {
            return null;
        }
        $stored[$field] = $encrypted;
    }

    return $stored;
}

/**
 * Encrypt SQL row value map entries during JSON import (values are quoted SQL literals).
 */
function pc_encrypt_contact_import_row_values(array &$rowValues, $vaultKey, $conn)
{
    $vaultKey = (string)$vaultKey;
    if ($vaultKey === '' || !($conn instanceof mysqli)) {
        return false;
    }

    foreach (pc_vault_encrypted_field_names() as $field) {
        if (!isset($rowValues[$field]) || $rowValues[$field] === 'NULL') {
            continue;
        }
        $raw = (string)$rowValues[$field];
        if ($raw === "''" || $raw === '') {
            continue;
        }
        if ($raw[0] === "'" && substr($raw, -1) === "'") {
            $plain = str_replace("''", "'", substr($raw, 1, -1));
        } else {
            $plain = $raw;
        }
        if ($plain === '') {
            continue;
        }
        $encrypted = itm_encrypt($plain, $vaultKey);
        if ($encrypted === false) {
            return false;
        }
        $rowValues[$field] = "'" . mysqli_real_escape_string($conn, $encrypted) . "'";
    }

    return true;
}

function pc_contact_display_name(array $contact)
{
    $first = trim((string)($contact['first_name'] ?? ''));
    $last = trim((string)($contact['last_name'] ?? ''));
    $name = trim($first . ' ' . $last);

    return $name !== '' ? $name : 'Contact';
}

function pc_row_matches_search(array $contact, $searchRaw)
{
    $searchRaw = mb_strtolower(trim((string)$searchRaw));
    if ($searchRaw === '') {
        return true;
    }

    $haystacks = [
        pc_contact_display_name($contact),
        (string)($contact['email1_value'] ?? ''),
        (string)($contact['phone1_value'] ?? ''),
        (string)($contact['organization_name'] ?? ''),
        (string)($contact['labels'] ?? ''),
        (string)($contact['first_name'] ?? ''),
        (string)($contact['last_name'] ?? ''),
        (string)($contact['middle_name'] ?? ''),
        (string)($contact['nickname'] ?? ''),
        (string)($contact['notes'] ?? ''),
    ];

    foreach ($haystacks as $haystack) {
        if ($haystack !== '' && mb_strpos(mb_strtolower($haystack), $searchRaw) !== false) {
            return true;
        }
    }

    return false;
}

function pc_compare_contact_rows(array $a, array $b, $sort, $dir)
{
    $sort = (string)$sort;
    $dir = strtoupper((string)$dir) === 'DESC' ? 'DESC' : 'ASC';
    $favA = (int)($a['is_favorite'] ?? 0);
    $favB = (int)($b['is_favorite'] ?? 0);
    if ($favA !== $favB) {
        return $favB <=> $favA;
    }

    if ($sort === 'first_name') {
        $left = mb_strtolower(pc_contact_display_name($a));
        $right = mb_strtolower(pc_contact_display_name($b));
    } else {
        $left = mb_strtolower((string)($a[$sort] ?? ''));
        $right = mb_strtolower((string)($b[$sort] ?? ''));
    }

    $cmp = $left <=> $right;

    return $dir === 'DESC' ? -$cmp : $cmp;
}
