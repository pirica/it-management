<?php
/**
 * Vault encryption helpers for notes private fields (mirrors bookmarks private URL pattern).
 */

function notes_text_hash($plainText)
{
    return hash('sha256', trim((string)$plainText));
}

function notes_is_shared_with_others($sharedWithJson)
{
    if ($sharedWithJson === null || $sharedWithJson === '') {
        return false;
    }
    $decoded = json_decode((string)$sharedWithJson, true);

    return is_array($decoded) && count($decoded) > 0;
}

function notes_private_text_legacy_plaintext_check($stored)
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

function notes_vault_session_key()
{
    return isset($_SESSION['vault_key']) ? (string)$_SESSION['vault_key'] : '';
}

/**
 * @return array{text:string,text_hash:string}|null null when private and vault is locked
 */
function notes_prepare_text_storage($plainText, $isShared)
{
    $plainText = (string)$plainText;
    $textHash = notes_text_hash($plainText);
    $isShared = (int)$isShared;

    if ($isShared === 1) {
        return ['text' => $plainText, 'text_hash' => $textHash];
    }

    $vaultKey = notes_vault_session_key();
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
function notes_resolve_private_text($stored, $isShared, $ownerId, $viewerEmployeeId, array $options = [])
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

    $vaultKey = notes_vault_session_key();
    if ($vaultKey === '') {
        return ['text' => '', 'locked' => true, 'label' => $vaultLabel];
    }

    $plain = itm_decrypt($stored, $vaultKey);
    if ($plain !== false) {
        return ['text' => $plain, 'locked' => false, 'label' => ''];
    }

    if (is_callable($legacyPlaintextCheck) && $legacyPlaintextCheck($stored)) {
        return ['text' => $stored, 'locked' => false, 'label' => ''];
    }

    return ['text' => '', 'locked' => true, 'label' => $decryptFailLabel];
}

function notes_hydrate_note_row(array &$row, $viewerEmployeeId)
{
    $isShared = notes_is_shared_with_others($row['shared_with_json'] ?? null) ? 1 : 0;
    $ownerId = (int)($row['employee_id'] ?? 0);
    $legacyTitle = static function ($stored) {
        return $stored !== '' && strlen($stored) <= 255;
    };
    $legacyBody = static function ($stored) {
        return notes_private_text_legacy_plaintext_check($stored);
    };

    $titleResolved = notes_resolve_private_text(
        (string)($row['title'] ?? ''),
        $isShared,
        $ownerId,
        (int)$viewerEmployeeId,
        [
            'other_user_label' => '🔒 Private note',
            'vault_label' => '🔒 Unlock vault to view',
            'decrypt_fail_label' => '🔒 Unable to decrypt title',
            'legacy_plaintext_check' => $legacyTitle,
        ]
    );
    $row['title'] = $titleResolved['text'];
    $row['title_locked'] = $titleResolved['locked'];
    $row['title_locked_label'] = $titleResolved['label'];

    $contentResolved = notes_resolve_private_text(
        (string)($row['content'] ?? ''),
        $isShared,
        $ownerId,
        (int)$viewerEmployeeId,
        [
            'other_user_label' => '🔒 Private note',
            'vault_label' => '🔒 Unlock vault to view',
            'decrypt_fail_label' => '🔒 Unable to decrypt content',
            'legacy_plaintext_check' => $legacyBody,
        ]
    );
    $row['content'] = $contentResolved['text'];
    $row['content_locked'] = $contentResolved['locked'];
    $row['content_locked_label'] = $contentResolved['label'];

    $checklistRaw = (string)($row['checklist_json'] ?? '');
    if ($checklistRaw !== '') {
        $checklistResolved = notes_resolve_private_text(
            $checklistRaw,
            $isShared,
            $ownerId,
            (int)$viewerEmployeeId,
            [
                'other_user_label' => '🔒 Private checklist',
                'vault_label' => '🔒 Unlock vault to view checklist',
                'decrypt_fail_label' => '🔒 Unable to decrypt checklist',
                'legacy_plaintext_check' => static function ($stored) {
                    $stored = (string)$stored;
                    return $stored !== '' && ($stored[0] === '[' || $stored[0] === '{');
                },
            ]
        );
        $row['checklist_json'] = $checklistResolved['text'];
        $row['checklist_locked'] = $checklistResolved['locked'];
    }
}

function notes_hydrate_label_text($storedLabel, $ownerId, $viewerEmployeeId)
{
    $resolved = notes_resolve_private_text(
        (string)$storedLabel,
        0,
        (int)$ownerId,
        (int)$viewerEmployeeId,
        [
            'other_user_label' => '🔒 Private label',
            'vault_label' => '🔒 Unlock vault to view label',
            'decrypt_fail_label' => '🔒 Unable to decrypt label',
            'legacy_plaintext_check' => static function ($stored) {
                return $stored !== '' && strlen($stored) <= 100;
            },
        ]
    );

    return $resolved['text'];
}

/**
 * @return array{label:string,label_hash:string}|null
 */
function notes_prepare_label_storage($plainLabel)
{
    $plainLabel = trim((string)$plainLabel);
    if ($plainLabel === '') {
        return null;
    }

    $prepared = notes_prepare_text_storage($plainLabel, 0);
    if ($prepared === null) {
        return null;
    }

    return ['label' => $prepared['text'], 'label_hash' => $prepared['text_hash']];
}

/**
 * @return array{title:string,title_hash:string,content:string,checklist_json:?string}|null
 */
function notes_prepare_note_fields_for_storage($title, $content, $checklistJson, $sharedWithJson)
{
    $isShared = notes_is_shared_with_others($sharedWithJson) ? 1 : 0;
    $titlePrep = notes_prepare_text_storage($title, $isShared);
    $contentPrep = notes_prepare_text_storage($content, $isShared);
    if ($titlePrep === null || $contentPrep === null) {
        return null;
    }

    $checklistStored = $checklistJson;
    if ($checklistJson !== null && $checklistJson !== '') {
        if ($isShared === 1) {
            $checklistStored = (string)$checklistJson;
        } else {
            $clPrep = notes_prepare_text_storage((string)$checklistJson, 0);
            if ($clPrep === null) {
                return null;
            }
            $checklistStored = $clPrep['text'];
        }
    }

    return [
        'title' => $titlePrep['text'],
        'title_hash' => $titlePrep['text_hash'],
        'content' => $contentPrep['text'],
        'checklist_json' => $checklistStored,
    ];
}

function notes_ui_requires_vault_lock_screen($crudAction, array $vaultState, $loggedUserId, $noteRow = null)
{
    if (!empty($vaultState['unlocked'])) {
        return false;
    }

    $crudAction = (string)$crudAction;
    if (in_array($crudAction, ['index', 'list_all', 'create'], true)) {
        return true;
    }

    if ($noteRow === null) {
        return in_array($crudAction, ['edit', 'view'], true);
    }

    $ownerId = (int)($noteRow['employee_id'] ?? 0);
    $loggedUserId = (int)$loggedUserId;
    $isShared = notes_is_shared_with_others($noteRow['shared_with_json'] ?? null);

    if ($isShared) {
        return false;
    }

    if ($ownerId !== $loggedUserId) {
        return false;
    }

    return in_array($crudAction, ['edit', 'view'], true);
}

function notes_row_matches_search(array $note, $search, array $tagLabels, array $users)
{
    $search = mb_strtolower(trim((string)$search));
    if ($search === '') {
        return true;
    }

    $haystacks = [
        mb_strtolower((string)($note['title'] ?? '')),
        mb_strtolower((string)($note['content'] ?? '')),
    ];
    foreach ($tagLabels as $tag) {
        $haystacks[] = mb_strtolower((string)$tag);
    }
    $sharedIds = json_decode((string)($note['shared_with_json'] ?? '[]'), true);
    if (is_array($sharedIds)) {
        foreach ($sharedIds as $uid) {
            if (isset($users[$uid])) {
                $haystacks[] = mb_strtolower((string)($users[$uid]['username'] ?? ''));
            }
        }
    }

    foreach ($haystacks as $hay) {
        if ($hay !== '' && mb_strpos($hay, $search) !== false) {
            return true;
        }
    }

    return false;
}

function notes_load_distinct_user_labels($conn, $companyId, $employeeId, $viewerEmployeeId)
{
    $labels = [];
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    $viewerEmployeeId = (int)$viewerEmployeeId;
    if (!($conn instanceof mysqli) || $companyId <= 0 || $employeeId <= 0) {
        return $labels;
    }

    $stmt = $conn->prepare('SELECT DISTINCT label, label_hash FROM note_labels WHERE company_id = ? AND employee_id = ? AND active = 1 ORDER BY label_hash ASC');
    if (!$stmt) {
        return $labels;
    }
    $stmt->bind_param('ii', $companyId, $employeeId);
    $stmt->execute();
    $res = $stmt->get_result();
    $seen = [];
    while ($row = $res->fetch_assoc()) {
        $plain = notes_hydrate_label_text((string)($row['label'] ?? ''), $employeeId, $viewerEmployeeId);
        if ($plain === '' || isset($seen[$plain])) {
            continue;
        }
        $seen[$plain] = true;
        $labels[] = $plain;
    }
    $stmt->close();
    sort($labels, SORT_NATURAL | SORT_FLAG_CASE);

    return $labels;
}

function notes_fetch_labels_for_note($conn, $noteId, $ownerEmployeeId, $viewerEmployeeId)
{
    $labels = [];
    $noteId = (int)$noteId;
    $ownerEmployeeId = (int)$ownerEmployeeId;
    $viewerEmployeeId = (int)$viewerEmployeeId;
    if (!($conn instanceof mysqli) || $noteId <= 0) {
        return $labels;
    }

    $stmt = $conn->prepare('SELECT label FROM note_labels WHERE note_id = ? AND active = 1');
    if (!$stmt) {
        return $labels;
    }
    $stmt->bind_param('i', $noteId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $plain = notes_hydrate_label_text((string)($row['label'] ?? ''), $ownerEmployeeId, $viewerEmployeeId);
        if ($plain !== '') {
            $labels[] = $plain;
        }
    }
    $stmt->close();

    return $labels;
}

function notes_label_exists_for_employee($conn, $companyId, $employeeId, $plainLabel)
{
    $hash = notes_text_hash($plainLabel);
    $stmt = $conn->prepare('SELECT 1 FROM note_labels WHERE company_id = ? AND employee_id = ? AND label_hash = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iis', $companyId, $employeeId, $hash);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $exists;
}

function notes_insert_label_row($conn, $companyId, $employeeId, $noteId, $plainLabel)
{
    $prepared = notes_prepare_label_storage($plainLabel);
    if ($prepared === null) {
        return false;
    }

    $noteIdParam = $noteId !== null ? (int)$noteId : null;
    if ($noteIdParam !== null && $noteIdParam > 0) {
        $stmt = $conn->prepare('INSERT INTO note_labels (company_id, employee_id, note_id, label, label_hash) VALUES (?, ?, ?, ?, ?)');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iiiss', $companyId, $employeeId, $noteIdParam, $prepared['label'], $prepared['label_hash']);
    } else {
        $stmt = $conn->prepare('INSERT INTO note_labels (company_id, employee_id, label, label_hash) VALUES (?, ?, ?, ?)');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iiss', $companyId, $employeeId, $prepared['label'], $prepared['label_hash']);
    }

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * @return string[]
 */
function notes_list_sortable_columns()
{
    return ['id', 'title', 'reminder_at', 'is_pinned', 'is_important', 'is_archived', 'created_at'];
}

/**
 * @return array{base_sql:string,types:string,params:array<int,mixed>}
 */
function notes_build_list_base_sql($companyId, $loggedUserId, $filter, $labelFilter = '')
{
    $companyId = (int)$companyId;
    $loggedUserId = (int)$loggedUserId;
    $filter = (string)$filter;

    if ($filter === 'garbage') {
        $baseSql = 'FROM notes t WHERE t.company_id = ? AND t.active = 0';
    } else {
        $baseSql = 'FROM notes t WHERE t.company_id = ? AND t.active = 1';
    }
    $params = [$companyId];
    $types = 'i';
    $visibilitySql = itm_notes_visibility_sql('t');
    $baseSql .= ' AND (' . $visibilitySql . ')';
    $types .= 'ii';
    $params[] = $loggedUserId;
    $params[] = $loggedUserId;

    if ($filter === 'reminders') {
        $baseSql .= ' AND t.reminder_at IS NOT NULL';
    } elseif ($filter === 'tag') {
        $labelHash = notes_text_hash((string)$labelFilter);
        $baseSql .= ' AND EXISTS (SELECT 1 FROM note_labels nl WHERE nl.note_id = t.id AND nl.label_hash = ? AND nl.active = 1)';
        $types .= 's';
        $params[] = $labelHash;
    } elseif ($filter === 'archive') {
        $baseSql .= ' AND t.is_archived = 1';
    } elseif ($filter === 'checklist') {
        $baseSql .= ' AND t.is_checklist = 1 AND t.is_archived = 0';
    } elseif ($filter === 'pinned') {
        $baseSql .= ' AND t.is_pinned = 1 AND t.is_archived = 0';
    } elseif ($filter === 'images') {
        $baseSql .= ' AND t.images_json IS NOT NULL AND t.is_archived = 0';
    } elseif ($filter === 'important') {
        $baseSql .= ' AND t.is_important = 1 AND t.is_archived = 0';
    } elseif ($filter === 'shared_with') {
        $baseSql .= " AND t.shared_with_json IS NOT NULL AND t.shared_with_json != '[]' AND JSON_CONTAINS(t.shared_with_json, CAST($loggedUserId AS JSON), '$') AND t.is_archived = 0";
    } elseif ($filter !== 'garbage') {
        $baseSql .= ' AND t.is_archived = 0';
    }

    return ['base_sql' => $baseSql, 'types' => $types, 'params' => $params];
}

function notes_compare_note_rows(array $a, array $b, $sort, $dir)
{
    $sort = (string)$sort;
    $dirMult = strtoupper((string)$dir) === 'DESC' ? -1 : 1;

    $left = $a[$sort] ?? null;
    $right = $b[$sort] ?? null;
    if ($sort === 'title') {
        $left = mb_strtolower((string)($a['title'] ?? ''));
        $right = mb_strtolower((string)($b['title'] ?? ''));
    }

    if ($left == $right) {
        return ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0)) * $dirMult;
    }

    return ($left < $right ? -1 : 1) * $dirMult;
}

/**
 * Decrypt, search, sort, and paginate notes for list_all (and bespoke gates).
 *
 * @return array{rows:list<array<string,mixed>>,note_tags_map:array<int,list<string>>,totalRows:int,totalPages:int,page:int}
 */
function notes_query_notes_for_list($conn, array $options)
{
    $companyId = (int)($options['company_id'] ?? 0);
    $loggedUserId = (int)($options['employee_id'] ?? 0);
    $filter = (string)($options['filter'] ?? 'all');
    $labelFilter = (string)($options['label'] ?? '');
    $searchRaw = trim((string)($options['search'] ?? ''));
    $sort = (string)($options['sort'] ?? 'created_at');
    $dir = strtoupper((string)($options['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
    $page = max(1, (int)($options['page'] ?? 1));
    $perPage = max(1, (int)($options['per_page'] ?? 25));
    $users = (array)($options['users'] ?? []);
    $paginate = !empty($options['paginate']);

    $sortableColumns = notes_list_sortable_columns();
    if (!in_array($sort, $sortableColumns, true)) {
        $sort = 'created_at';
    }

    $built = notes_build_list_base_sql($companyId, $loggedUserId, $filter, $labelFilter);
    $sql = 'SELECT t.* ' . $built['base_sql'];
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['rows' => [], 'note_tags_map' => [], 'totalRows' => 0, 'totalPages' => 1, 'page' => 1];
    }
    $stmt->bind_param($built['types'], ...$built['params']);
    $stmt->execute();
    $res = $stmt->get_result();
    $notes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $note_tags_map = [];
    if (!empty($notes)) {
        $noteIds = array_column($notes, 'id');
        $placeholders = implode(',', array_fill(0, count($noteIds), '?'));
        $stmtTags = $conn->prepare("SELECT note_id, label, employee_id FROM note_labels WHERE note_id IN ($placeholders) AND active = 1");
        if ($stmtTags) {
            $stmtTags->bind_param(str_repeat('i', count($noteIds)), ...$noteIds);
            $stmtTags->execute();
            $resTags = $stmtTags->get_result();
            while ($rowTags = $resTags->fetch_assoc()) {
                $plainLabel = notes_hydrate_label_text((string)$rowTags['label'], (int)$rowTags['employee_id'], $loggedUserId);
                if ($plainLabel !== '') {
                    $note_tags_map[$rowTags['note_id']][] = $plainLabel;
                }
            }
            $stmtTags->close();
        }
    }

    foreach ($notes as &$noteRow) {
        notes_hydrate_note_row($noteRow, $loggedUserId);
    }
    unset($noteRow);

    if ($searchRaw !== '') {
        $notes = array_values(array_filter($notes, static function ($note) use ($searchRaw, $note_tags_map, $users) {
            return notes_row_matches_search($note, $searchRaw, $note_tags_map[$note['id']] ?? [], $users);
        }));
    }

    usort($notes, static function (array $a, array $b) use ($sort, $dir) {
        return notes_compare_note_rows($a, $b, $sort, $dir);
    });

    $totalRows = count($notes);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    if ($paginate) {
        $offset = ($page - 1) * $perPage;
        $notes = array_slice($notes, $offset, $perPage);
    }

    return [
        'rows' => $notes,
        'note_tags_map' => $note_tags_map,
        'totalRows' => $totalRows,
        'totalPages' => $totalPages,
        'page' => $page,
    ];
}
