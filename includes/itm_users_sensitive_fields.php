<?php
/**
 * Shared list/view column policy for the Users module.
 */

function itm_users_sensitive_field_names()
{
    return [
        'password',
        'vault_key_hash',
        'reset_token',
        'reset_token_hash',
        'reset_token_expires_at',
    ];
}

function itm_users_is_sensitive_field($fieldName)
{
    return in_array((string)$fieldName, itm_users_sensitive_field_names(), true);
}

/**
 * Filters Users module list/view columns; credential and reset-token fields never render.
 */
function itm_users_filter_ui_columns(array $columns)
{
    return array_values(array_filter($columns, function ($col) {
        return !itm_users_is_sensitive_field($col['Field'] ?? '');
    }));
}
