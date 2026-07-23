<?php
/**
 * Vault encryption helpers for events private fields (mirrors notes private-field pattern).
 */

require_once ROOT_PATH . 'includes/events_visibility.php';

function events_text_hash($plainText)
{
    return hash('sha256', trim((string)$plainText));
}

function events_is_shared_with_others($sharedWithJson)
{
    if ($sharedWithJson === null || $sharedWithJson === '') {
        return false;
    }
    $decoded = json_decode((string)$sharedWithJson, true);

    return is_array($decoded) && count($decoded) > 0;
}

function events_private_text_legacy_plaintext_check($stored)
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

function events_vault_session_key()
{
    return isset($_SESSION['vault_key']) ? (string)$_SESSION['vault_key'] : '';
}

/**
 * @return array{text:string,text_hash:string}|null null when private and vault is locked
 */
function events_prepare_text_storage($plainText, $isShared)
{
    $plainText = (string)$plainText;
    $textHash = events_text_hash($plainText);
    $isShared = (int)$isShared;

    if ($isShared === 1) {
        return ['text' => $plainText, 'text_hash' => $textHash];
    }

    $vaultKey = events_vault_session_key();
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
function events_resolve_private_text($stored, $isShared, $ownerId, $viewerEmployeeId, array $options = [])
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

    $vaultKey = events_vault_session_key();
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

function events_hydrate_event_row(array &$row, $viewerEmployeeId)
{
    $isShared = events_is_shared_with_others($row['shared_with_json'] ?? null) ? 1 : 0;
    $ownerId = (int)($row['employee_id'] ?? 0);
    $legacyTitle = static function ($stored) {
        return events_private_text_legacy_plaintext_check($stored);
    };
    $legacyBody = static function ($stored) {
        return events_private_text_legacy_plaintext_check($stored);
    };

    $titleResolved = events_resolve_private_text(
        (string)($row['title'] ?? ''),
        $isShared,
        $ownerId,
        (int)$viewerEmployeeId,
        [
            'other_user_label' => '🔒 Private event',
            'vault_label' => '🔒 Unlock vault to view',
            'decrypt_fail_label' => '🔒 Unable to decrypt title',
            'legacy_plaintext_check' => $legacyTitle,
        ]
    );
    $row['title'] = $titleResolved['text'];
    $row['title_locked'] = $titleResolved['locked'];
    $row['title_locked_label'] = $titleResolved['label'];

    $descriptionResolved = events_resolve_private_text(
        (string)($row['description'] ?? ''),
        $isShared,
        $ownerId,
        (int)$viewerEmployeeId,
        [
            'other_user_label' => '🔒 Private event',
            'vault_label' => '🔒 Unlock vault to view',
            'decrypt_fail_label' => '🔒 Unable to decrypt description',
            'legacy_plaintext_check' => $legacyBody,
        ]
    );
    $row['description'] = $descriptionResolved['text'];
    $row['description_locked'] = $descriptionResolved['locked'];
    $row['description_locked_label'] = $descriptionResolved['label'];

    $locationResolved = events_resolve_private_text(
        (string)($row['location'] ?? ''),
        $isShared,
        $ownerId,
        (int)$viewerEmployeeId,
        [
            'other_user_label' => '🔒 Private event',
            'vault_label' => '🔒 Unlock vault to view',
            'decrypt_fail_label' => '🔒 Unable to decrypt location',
            'legacy_plaintext_check' => $legacyTitle,
        ]
    );
    $row['location'] = $locationResolved['text'];
    $row['location_locked'] = $locationResolved['locked'];
    $row['location_locked_label'] = $locationResolved['label'];
}

/**
 * @return array{title:string,title_hash:string,description:?string,location:?string}|null
 */
function events_prepare_event_fields_for_storage($title, $description, $location, $sharedWithJson)
{
    $isShared = events_is_shared_with_others($sharedWithJson) ? 1 : 0;
    $titlePrep = events_prepare_text_storage($title, $isShared);
    $descriptionPrep = events_prepare_text_storage((string)$description, $isShared);
    $locationPrep = events_prepare_text_storage((string)$location, $isShared);
    if ($titlePrep === null || $descriptionPrep === null || $locationPrep === null) {
        return null;
    }

    return [
        'title' => $titlePrep['text'],
        'title_hash' => $titlePrep['text_hash'],
        'description' => $descriptionPrep['text'] !== '' ? $descriptionPrep['text'] : null,
        'location' => $locationPrep['text'] !== '' ? $locationPrep['text'] : null,
    ];
}

function events_ui_requires_vault_lock_screen($crudAction, array $vaultState, $loggedUserId, $eventRow = null)
{
    if (!empty($vaultState['unlocked'])) {
        return false;
    }

    $crudAction = (string)$crudAction;
    if (in_array($crudAction, ['index', 'list_all', 'create'], true)) {
        return true;
    }

    if ($eventRow === null) {
        return in_array($crudAction, ['edit', 'view'], true);
    }

    $ownerId = (int)($eventRow['employee_id'] ?? 0);
    $loggedUserId = (int)$loggedUserId;
    $isShared = events_is_shared_with_others($eventRow['shared_with_json'] ?? null);

    if ($isShared) {
        return false;
    }

    if ($ownerId !== $loggedUserId) {
        return false;
    }

    return in_array($crudAction, ['edit', 'view'], true);
}

function events_row_matches_search(array $event, $search, array $users)
{
    $search = mb_strtolower(trim((string)$search));
    if ($search === '') {
        return true;
    }

    $haystacks = [
        mb_strtolower((string)($event['title'] ?? '')),
        mb_strtolower((string)($event['description'] ?? '')),
        mb_strtolower((string)($event['location'] ?? '')),
        mb_strtolower((string)($event['category_name'] ?? '')),
    ];
    $sharedIds = json_decode((string)($event['shared_with_json'] ?? '[]'), true);
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

function events_compare_event_rows(array $a, array $b, $sort, $dir)
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
 * Count live events visible to the signed-in employee (owner or shared recipient).
 */
function events_count_visible_live_events(mysqli $conn, int $companyId, int $employeeId): int
{
    if ($companyId <= 0 || $employeeId <= 0) {
        return 0;
    }

    $visSql = itm_events_visibility_sql('e');
    $sql = 'SELECT COUNT(*) AS c FROM events e WHERE e.company_id = ? AND e.deleted_at IS NULL AND (' . $visSql . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('iii', $companyId, $employeeId, $employeeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int)($row['c'] ?? 0);
}

/**
 * @return array{rows:list<array<string,mixed>>,totalRows:int,totalPages:int,page:int}
 */
function events_query_events_for_list($conn, array $options)
{
    $companyId = (int)($options['company_id'] ?? 0);
    $loggedUserId = (int)($options['employee_id'] ?? 0);
    $searchRaw = trim((string)($options['search'] ?? ''));
    $sort = (string)($options['sort'] ?? 'id');
    $dir = strtoupper((string)($options['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
    $page = max(1, (int)($options['page'] ?? 1));
    $perPage = max(1, (int)($options['per_page'] ?? 25));
    $users = (array)($options['users'] ?? []);

    $visSql = itm_events_visibility_sql('e');
    $sql = 'SELECT e.*, ec.name AS category_name, ec.color AS category_color, u.first_name, u.last_name, u.username
            FROM events e
            LEFT JOIN event_categories ec ON e.category_id = ec.id AND ec.company_id = e.company_id
            LEFT JOIN employees u ON e.assigned_to_employee_id = u.id
            WHERE e.company_id = ? AND e.deleted_at IS NULL AND (' . $visSql . ')';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['rows' => [], 'totalRows' => 0, 'totalPages' => 1, 'page' => 1];
    }
    $stmt->bind_param('iii', $companyId, $loggedUserId, $loggedUserId);
    $stmt->execute();
    $res = $stmt->get_result();
    $events = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($events as &$eventRow) {
        events_hydrate_event_row($eventRow, $loggedUserId);
    }
    unset($eventRow);

    if ($searchRaw !== '') {
        $events = array_values(array_filter($events, static function ($event) use ($searchRaw, $users) {
            return events_row_matches_search($event, $searchRaw, $users);
        }));
    }

    usort($events, static function (array $a, array $b) use ($sort, $dir) {
        return events_compare_event_rows($a, $b, $sort, $dir);
    });

    $totalRows = count($events);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;
    $events = array_slice($events, $offset, $perPage);

    return [
        'rows' => $events,
        'totalRows' => $totalRows,
        'totalPages' => $totalPages,
        'page' => $page,
    ];
}
