<?php
/**
 * Vault unlock/lock handling for Explorer private folder access.
 */

/**
 * @return array{configured:bool,unlocked:bool,error:string,totp_required:bool}
 */
function explorer_handle_vault_requests($conn, $user_id)
{
    return itm_vault_handle_unlock_requests($conn, $user_id, 'explorer_vault_redirect', 'index.php', 'index.php');
}

function explorer_render_vault_lock_screen($csrfToken, array $vaultState, $redirectTarget = 'index.php')
{
    itm_vault_render_lock_screen(
        $csrfToken,
        $vaultState,
        'Enter your master key to access your Private folder in Explorer.',
        'explorer_vault_redirect',
        $redirectTarget,
        [
            ['href' => '../../user-config.php#vault-security', 'title' => 'Change master key', 'label' => 'Change Master Key'],
            ['href' => '../../dashboard.php', 'title' => 'Back to dashboard', 'label' => 'Back to Dashboard'],
        ]
    );
}
