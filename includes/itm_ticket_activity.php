<?php
/**
 * Append-only ticket timeline events.
 */

if (!function_exists('itm_ticket_activity_log')) {
    function itm_ticket_activity_log($conn, $companyId, $ticketId, $actorEmployeeId, $eventType, $payload = [])
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        $actorEmployeeId = $actorEmployeeId !== null ? (int)$actorEmployeeId : null;
        $eventType = trim((string)$eventType);
        if ($companyId <= 0 || $ticketId <= 0 || $eventType === '') {
            return false;
        }

        $payloadJson = json_encode(is_array($payload) ? $payload : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sessionEmployee = (int)($_SESSION['employee_id'] ?? 0);

        $sql = 'INSERT INTO ticket_activity (company_id, ticket_id, actor_employee_id, event_type, payload_json, created_by)
                VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'iiissi', $companyId, $ticketId, $actorEmployeeId, $eventType, $payloadJson, $sessionEmployee);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}
