<?php
/**
 * Build plaintext contact field map from create/edit POST for vault encryption.
 *
 * @return array<string,string>
 */
function pc_contact_plain_row_from_post(array $post)
{
    $fields = array_merge(pc_vault_encrypted_field_names(), ['birthday', 'event1_value']);
    $row = [];
    foreach ($fields as $field) {
        if ($field === 'birthday' || $field === 'event1_value') {
            $row[$field] = trim((string)($post[$field] ?? ''));
            continue;
        }
        $row[$field] = (string)($post[$field] ?? '');
    }

    return $row;
}
