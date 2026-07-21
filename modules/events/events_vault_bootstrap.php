<?php
/**
 * Vault unlock/lock handling for events (private title/description/location encrypted at rest).
 */

/**
 * @return array{configured:bool,unlocked:bool,error:string,totp_required:bool}
 */
function events_handle_vault_requests($conn, $user_id)
{
    return itm_vault_handle_unlock_requests($conn, $user_id, 'events_vault_redirect', 'index.php', 'index.php');
}

function events_render_vault_lock_screen($csrfToken, array $vaultState, $redirectTarget = 'index.php')
{
    itm_vault_render_lock_screen(
        $csrfToken,
        $vaultState,
        'Enter your master key to access private event titles, descriptions, and locations.',
        'events_vault_redirect',
        $redirectTarget
    );
}
