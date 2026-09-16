<?php
/**
 * Vault unlock/lock handling for private contacts (PII encrypted at rest).
 */

/**
 * @return array{configured:bool,unlocked:bool,error:string,totp_required:bool}
 */
function pc_handle_vault_requests($conn, $user_id)
{
    return itm_vault_handle_unlock_requests($conn, $user_id, 'pc_vault_redirect', 'index.php', 'index.php');
}

function pc_render_vault_lock_screen($csrfToken, array $vaultState, $redirectTarget = 'index.php')
{
    itm_vault_render_lock_screen(
        $csrfToken,
        $vaultState,
        'Enter your master key to access your private contacts.',
        'pc_vault_redirect',
        $redirectTarget
    );
}

function pc_ui_requires_vault_lock_screen(array $vaultState)
{
    return empty($vaultState['unlocked']);
}
