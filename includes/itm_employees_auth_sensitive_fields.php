<?php
/**
 * Shared list/view column policy for the Employees module (auth columns on employees).
 */

function itm_employees_auth_sensitive_field_names()
{
    return [
        'password',
        'vault_key_hash',
        'totp_secret',
        'reset_token',
        'reset_token_hash',
        'reset_token_expires_at',
    ];
}

function itm_employees_auth_is_sensitive_field($fieldName)
{
    return in_array((string)$fieldName, itm_employees_auth_sensitive_field_names(), true);
}

/**
 * Filters Employees list/view columns; credential and reset-token fields never render.
 */
function itm_employees_auth_filter_ui_columns(array $columns)
{
    return array_values(array_filter($columns, function ($col) {
        return !itm_employees_auth_is_sensitive_field($col['Field'] ?? '');
    }));
}
