<?php
/**
 * Vault unlock/lock handling for notes (private title/content/labels encrypted at rest).
 */

/**
 * @return array{configured:bool,unlocked:bool,error:string,totp_required:bool}
 */
function notes_handle_vault_requests($conn, $user_id)
{
    return itm_vault_handle_unlock_requests($conn, $user_id, 'notes_vault_redirect', 'index.php', 'index.php');
}

function notes_render_vault_lock_screen($csrfToken, array $vaultState, $redirectTarget = 'index.php')
{
    itm_vault_render_lock_screen(
        $csrfToken,
        $vaultState,
        'Enter your master key to access private note titles, content, checklists, and labels.',
        'notes_vault_redirect',
        $redirectTarget
    );
}
