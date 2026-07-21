<?php
/**
 * Temporary QR / code share sessions for Events.
 */

require_once ROOT_PATH . 'includes/events_visibility.php';
require_once ROOT_PATH . 'includes/itm_qr_share.php';
require_once __DIR__ . '/events_vault_helpers.php';

function events_share_module_slug()
{
    return 'events';
}

/** @deprecated Use events_share_module_slug() */
function events_share_table_name()
{
    return events_share_module_slug();
}

function events_share_join_script_path()
{
    return 'modules/events/join.php';
}

function events_share_format_datetime($value)
{
    $value = (string)$value;
    if ($value === '') {
        return '';
    }
    if (function_exists('itm_format_cell_scalar_display')) {
        return (string)itm_format_cell_scalar_display('start_datetime', $value);
    }

    return $value;
}

/**
 * @return array<string,mixed>
 */
function events_share_build_payload_from_event(array $event, $ownerUsername, $viewerEmployeeId)
{
    $viewerEmployeeId = (int)$viewerEmployeeId;
    $eventRow = $event;
    events_hydrate_event_row($eventRow, $viewerEmployeeId);

    $title = (string)($eventRow['title'] ?? '');
    $firstName = trim((string)($event['first_name'] ?? ''));
    $lastName = trim((string)($event['last_name'] ?? ''));
    $username = trim((string)($event['username'] ?? ''));
    $fullName = trim($firstName . ' ' . $lastName);
    $assigneeName = $fullName !== '' ? $fullName : $username;

    return [
        'type' => 'event',
        'heading' => $title !== '' ? $title : 'Event',
        'owner_username' => (string)$ownerUsername,
        'title' => $title,
        'description' => (string)($eventRow['description'] ?? ''),
        'start_datetime' => events_share_format_datetime($eventRow['start_datetime'] ?? ''),
        'end_datetime' => events_share_format_datetime($eventRow['end_datetime'] ?? ''),
        'location' => (string)($eventRow['location'] ?? ''),
        'category_name' => (string)($event['category_name'] ?? ''),
        'assignee_name' => $assigneeName,
    ];
}

function events_share_build_join_url($accessToken)
{
    return itm_qr_share_build_join_url(events_share_join_script_path(), $accessToken);
}

/**
 * @return array{ok:bool,error?:string,session?:array<string,mixed>}
 */
function events_share_create_session($conn, $eventId, $companyId, $employeeId, $ownerUsername, $vaultUnlocked)
{
    $eventId = (int)$eventId;
    $companyId = (int)$companyId;
    $employeeId = (int)$employeeId;
    if ($eventId <= 0 || $companyId <= 0 || $employeeId <= 0 || !($conn instanceof mysqli)) {
        return ['ok' => false, 'error' => 'Invalid request.'];
    }

    $stmt = $conn->prepare(
        'SELECT e.*, ec.name AS category_name, ec.color AS category_color, u.first_name, u.last_name, u.username
         FROM events e
         LEFT JOIN event_categories ec ON e.category_id = ec.id AND ec.company_id = e.company_id
         LEFT JOIN employees u ON e.assigned_to_employee_id = u.id
         WHERE e.id = ? AND e.company_id = ? AND e.employee_id = ? AND e.deleted_at IS NULL
         LIMIT 1'
    );
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Could not load event.'];
    }
    $stmt->bind_param('iii', $eventId, $companyId, $employeeId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$event) {
        return ['ok' => false, 'error' => 'Event not found or you are not the owner.'];
    }

    if (!events_is_shared_with_others($event['shared_with_json'] ?? null) && !$vaultUnlocked) {
        return ['ok' => false, 'error' => 'Unlock your vault before sharing a private event.'];
    }

    $payload = events_share_build_payload_from_event($event, $ownerUsername, $employeeId);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return ['ok' => false, 'error' => 'Could not encode share payload.'];
    }

    return itm_qr_share_create_session($conn, events_share_module_slug(), [
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'record_id' => $eventId,
        'payload_json' => $payloadJson,
    ]);
}
