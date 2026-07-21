<?php
/**
 * Vault unlock/lock handling for todo (private task title/description encrypted at rest).
 */

/**
 * @return array{configured:bool,unlocked:bool,error:string,totp_required:bool}
 */
function todo_handle_vault_requests($conn, $user_id)
{
    return itm_vault_handle_unlock_requests($conn, $user_id, 'todo_vault_redirect', 'index.php', 'index.php');
}

function todo_render_vault_lock_screen($csrfToken, array $vaultState, $redirectTarget = 'index.php')
{
    itm_vault_render_lock_screen(
        $csrfToken,
        $vaultState,
        'Enter your master key to access private task titles and descriptions.',
        'todo_vault_redirect',
        $redirectTarget
    );
}
