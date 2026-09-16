<?php
/**
 * Vault gate helpers for Explorer Private/ paths (session vault_key; no DB ciphertext).
 */

function explorer_vault_is_unlocked()
{
    return !empty($_SESSION['vault_key']);
}

function explorer_normalize_path_slashes($path)
{
    $path = str_replace('\\', '/', (string)$path);
    $path = trim($path, '/');

    return $path;
}

/**
 * True when the relative path is under the signed-in employee's Private/{username}_{id}/ tree.
 */
function explorer_path_is_profile_storage($relativePath)
{
    $relativePath = explorer_normalize_path_slashes($relativePath);

    return (bool)preg_match('#^Private/[^/]+/profile(/|$)#', $relativePath);
}

function explorer_path_requires_vault_unlock($relativePath, $userPrivateDir)
{
    $relativePath = explorer_normalize_path_slashes($relativePath);
    if (explorer_path_is_profile_storage($relativePath)) {
        return false;
    }

    $userPrivateDir = trim((string)$userPrivateDir, '/');
    if ($userPrivateDir === '') {
        return false;
    }

    if ($relativePath === 'Private/' . $userPrivateDir || str_starts_with($relativePath, 'Private/' . $userPrivateDir . '/')) {
        return true;
    }

    return false;
}

/**
 * @return array{ok:bool,error:string}
 */
function explorer_enforce_vault_for_private_path($relativePath, $userPrivateDir)
{
    if (!explorer_path_requires_vault_unlock($relativePath, $userPrivateDir)) {
        return ['ok' => true, 'error' => ''];
    }

    if (explorer_vault_is_unlocked()) {
        return ['ok' => true, 'error' => ''];
    }

    return ['ok' => false, 'error' => 'Unlock the vault to access Private files.'];
}

function explorer_ui_requires_vault_lock_screen(array $vaultState, $relativePath, $userPrivateDir)
{
    if (!empty($vaultState['unlocked'])) {
        return false;
    }

    return explorer_path_requires_vault_unlock($relativePath, $userPrivateDir);
}
