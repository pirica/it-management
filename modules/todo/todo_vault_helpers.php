<?php
/**
 * Vault encryption helpers for todo private fields (mirrors notes/events private-field pattern).
 */

function todo_text_hash($plainText)
{
    return hash('sha256', trim((string)$plainText));
}

/**
 * Company-global (assigned NULL) and tasks assigned to other employees stay plaintext.
 */
function todo_task_is_shared_with_others($assignedToCsv, $createdBy)
{
    if ($assignedToCsv === null) {
        return true;
    }

    $csv = trim((string)$assignedToCsv);
    if ($csv === '') {
        return false;
    }

    $createdBy = (int)$createdBy;
    foreach (array_filter(array_map('intval', explode(',', $csv))) as $assigneeId) {
        if ($assigneeId > 0 && $assigneeId !== $createdBy) {
            return true;
        }
    }

    return false;
}

function todo_private_text_legacy_plaintext_check($stored)
{
    $stored = (string)$stored;
    if ($stored === '') {
        return true;
    }

    $decoded = base64_decode($stored, true);
    if ($decoded === false) {
        return true;
    }

    $ivLen = openssl_cipher_iv_length('aes-256-cbc');

    return strlen($decoded) <= $ivLen;
}

function todo_vault_session_key()
{
    return isset($_SESSION['vault_key']) ? (string)$_SESSION['vault_key'] : '';
}

/**
 * @return array{text:string,text_hash:string}|null null when private and vault is locked
 */
function todo_prepare_text_storage($plainText, $isShared)
{
    $plainText = (string)$plainText;
    $textHash = todo_text_hash($plainText);
    $isShared = (int)$isShared;

    if ($isShared === 1) {
        return ['text' => $plainText, 'text_hash' => $textHash];
    }

    $vaultKey = todo_vault_session_key();
    if ($vaultKey === '') {
        return null;
    }

    return [
        'text' => itm_encrypt($plainText, $vaultKey),
        'text_hash' => $textHash,
    ];
}

/**
 * @return array{text:string,locked:bool,label:string}
 */
function todo_resolve_private_text($stored, $isShared, $ownerId, $viewerEmployeeId, array $options = [])
{
    $stored = (string)$stored;
    $isShared = (int)$isShared;
    $ownerId = (int)$ownerId;
    $viewerEmployeeId = (int)$viewerEmployeeId;
    $otherUserLabel = (string)($options['other_user_label'] ?? '🔒 Private');
    $vaultLabel = (string)($options['vault_label'] ?? '🔒 Unlock vault to view');
    $decryptFailLabel = (string)($options['decrypt_fail_label'] ?? '🔒 Unable to decrypt');
    $legacyPlaintextCheck = $options['legacy_plaintext_check'] ?? null;

    if ($isShared === 1) {
        return ['text' => $stored, 'locked' => false, 'label' => ''];
    }

    if ($ownerId !== $viewerEmployeeId) {
        return ['text' => '', 'locked' => true, 'label' => $otherUserLabel];
    }

    $vaultKey = todo_vault_session_key();
    if ($vaultKey === '') {
        return ['text' => '', 'locked' => true, 'label' => $vaultLabel];
    }

    if (is_callable($legacyPlaintextCheck) && $legacyPlaintextCheck($stored)) {
        return ['text' => $stored, 'locked' => false, 'label' => ''];
    }

    $plain = itm_decrypt($stored, $vaultKey);
    if ($plain !== false) {
        return ['text' => $plain, 'locked' => false, 'label' => ''];
    }

    return ['text' => '', 'locked' => true, 'label' => $decryptFailLabel];
}

function todo_hydrate_task_row(array &$row, $viewerEmployeeId)
{
    $ownerId = (int)($row['created_by'] ?? 0);
    $isShared = todo_task_is_shared_with_others($row['assigned_to_employee_id'] ?? null, $ownerId) ? 1 : 0;
    $legacyPlaintextCheck = static function ($stored) {
        return todo_private_text_legacy_plaintext_check($stored);
    };

    $titleResolved = todo_resolve_private_text(
        (string)($row['title'] ?? ''),
        $isShared,
        $ownerId,
        (int)$viewerEmployeeId,
        [
            'other_user_label' => '🔒 Private task',
            'vault_label' => '🔒 Unlock vault to view',
            'decrypt_fail_label' => '🔒 Unable to decrypt title',
            'legacy_plaintext_check' => $legacyPlaintextCheck,
        ]
    );
    $row['title'] = $titleResolved['text'];
    $row['title_locked'] = $titleResolved['locked'];
    $row['title_locked_label'] = $titleResolved['label'];

    $descriptionResolved = todo_resolve_private_text(
        (string)($row['description'] ?? ''),
        $isShared,
        $ownerId,
        (int)$viewerEmployeeId,
        [
            'other_user_label' => '🔒 Private task',
            'vault_label' => '🔒 Unlock vault to view',
            'decrypt_fail_label' => '🔒 Unable to decrypt description',
            'legacy_plaintext_check' => $legacyPlaintextCheck,
        ]
    );
    $row['description'] = $descriptionResolved['text'];
    $row['description_locked'] = $descriptionResolved['locked'];
    $row['description_locked_label'] = $descriptionResolved['label'];
}

/**
 * @return array{title:string,title_hash:string,description:?string}|null
 */
function todo_prepare_task_fields_for_storage($title, $description, $assignedToCsv, $createdBy)
{
    $isShared = todo_task_is_shared_with_others($assignedToCsv, $createdBy) ? 1 : 0;
    $titlePrep = todo_prepare_text_storage($title, $isShared);
    $descriptionPrep = todo_prepare_text_storage((string)$description, $isShared);
    if ($titlePrep === null || $descriptionPrep === null) {
        return null;
    }

    return [
        'title' => $titlePrep['text'],
        'title_hash' => $titlePrep['text_hash'],
        'description' => $descriptionPrep['text'] !== '' ? $descriptionPrep['text'] : null,
    ];
}

function todo_ui_requires_vault_lock_screen($crudAction, array $vaultState, $loggedUserId, $taskRow = null)
{
    if (!empty($vaultState['unlocked'])) {
        return false;
    }

    $crudAction = (string)$crudAction;
    if (in_array($crudAction, ['index', 'create'], true)) {
        return true;
    }

    if ($taskRow === null) {
        return in_array($crudAction, ['edit', 'view'], true);
    }

    $ownerId = (int)($taskRow['created_by'] ?? 0);
    $loggedUserId = (int)$loggedUserId;
    $isShared = todo_task_is_shared_with_others($taskRow['assigned_to_employee_id'] ?? null, $ownerId);

    if ($isShared) {
        return false;
    }

    if ($ownerId !== $loggedUserId) {
        return false;
    }

    return in_array($crudAction, ['edit', 'view'], true);
}

function todo_compare_task_rows(array $a, array $b, $sort, $dir)
{
    $sort = (string)$sort;
    $dir = strtoupper((string)$dir) === 'ASC' ? 'ASC' : 'DESC';
    $sortable = todo_list_sortable_columns();
    if (!in_array($sort, $sortable, true)) {
        $sort = 'created_at';
    }

    if ($sort === 'created_at' && $dir === 'DESC') {
        $completedCmp = ((int)($a['completed'] ?? 0)) <=> ((int)($b['completed'] ?? 0));
        if ($completedCmp !== 0) {
            return $completedCmp;
        }
        $importanceCmp = ((int)($b['importance'] ?? 0)) <=> ((int)($a['importance'] ?? 0));
        if ($importanceCmp !== 0) {
            return $importanceCmp;
        }
    }

    $valA = $a[$sort] ?? '';
    $valB = $b[$sort] ?? '';
    if ($sort === 'importance' || $sort === 'completed') {
        $cmp = ((int)$valA) <=> ((int)$valB);
    } else {
        $cmp = strcmp((string)$valA, (string)$valB);
    }

    if ($cmp === 0) {
        return ((int)($a['id'] ?? 0)) <=> ((int)($b['id'] ?? 0));
    }

    return $dir === 'ASC' ? $cmp : -$cmp;
}

function todo_row_matches_search(array $task, $search, array $categories, array $departments, array $users)
{
    $search = mb_strtolower(trim((string)$search));
    if ($search === '') {
        return true;
    }

    $haystacks = [
        mb_strtolower((string)($task['title'] ?? '')),
        mb_strtolower((string)($task['description'] ?? '')),
    ];

    foreach (array_filter(explode(',', (string)($task['category_id'] ?? ''))) as $catId) {
        $name = (string)($categories[(int)$catId]['name'] ?? '');
        if ($name !== '') {
            $haystacks[] = mb_strtolower($name);
        }
    }
    foreach (array_filter(explode(',', (string)($task['department_id'] ?? ''))) as $deptId) {
        $dept = $departments[(int)$deptId] ?? [];
        foreach (['name', 'code'] as $field) {
            $val = (string)($dept[$field] ?? '');
            if ($val !== '') {
                $haystacks[] = mb_strtolower($val);
            }
        }
    }
    foreach (array_filter(explode(',', (string)($task['assigned_to_employee_id'] ?? ''))) as $uid) {
        $user = $users[(int)$uid] ?? [];
        foreach (['username', 'first_name', 'last_name'] as $field) {
            $val = (string)($user[$field] ?? '');
            if ($val !== '') {
                $haystacks[] = mb_strtolower($val);
            }
        }
    }

    foreach ($haystacks as $haystack) {
        if ($haystack !== '' && mb_strpos($haystack, $search) !== false) {
            return true;
        }
    }

    return false;
}
