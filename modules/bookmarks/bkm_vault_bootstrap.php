<?php
/**
 * Vault unlock/lock handling for bookmarks (private URLs encrypted at rest).
 */

/**
 * @return array{configured:bool,unlocked:bool,error:string,totp_required:bool}
 */
function bkm_handle_vault_requests($conn, $user_id)
{
    return itm_vault_handle_unlock_requests($conn, $user_id, 'bkm_vault_redirect', 'index.php', 'index.php');
}

function bkm_render_vault_lock_screen($csrfToken, array $vaultState, $redirectTarget = 'index.php')
{
    itm_vault_render_lock_screen(
        $csrfToken,
        $vaultState,
        'Enter your master key to access private bookmark URLs, titles, notes, and folder names.',
        'bkm_vault_redirect',
        $redirectTarget
    );
}
