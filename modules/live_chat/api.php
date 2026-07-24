<?php
/**
 * Live Chat JSON API
 */

header('Content-Type: application/json; charset=utf-8');

require_once '../../config/config.php';

if (!isset($_SESSION['employee_id'], $_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$companyId = (int)$_SESSION['company_id'];
$employeeId = (int)$_SESSION['employee_id'];
$isSupportAgent = itm_live_chat_is_support_agent($conn, $employeeId);

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['action'])) {
        $action = trim((string)$_POST['action']);
    } else {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json) && isset($json['action'])) {
            $_POST = array_merge($_POST, $json);
            $action = trim((string)$_POST['action']);
        }
    }
    $csrf = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!itm_validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    $action = trim((string)($_GET['action'] ?? ''));
}

function lc_json($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lc_require_conv($conn, $companyId, $conversationId, $employeeId)
{
    if (!itm_live_chat_can_view_conversation($conn, $companyId, $conversationId, $employeeId)) {
        lc_json(['error' => 'Forbidden'], 403);
    }
    $conv = itm_live_chat_fetch_conversation_row($conn, $companyId, $conversationId);
    if (!$conv) {
        lc_json(['error' => 'Not found'], 404);
    }
    return $conv;
}

function lc_conversation_action_url($conversationId)
{
    return BASE_URL . 'modules/live_chat/?conversation_id=' . (int)$conversationId;
}

function lc_notify_participants_except($conn, $companyId, $conversationId, $exceptEmployeeId, $title, $body)
{
    $sql = 'SELECT employee_id FROM live_chat_participants WHERE conversation_id = ? AND company_id = ? AND deleted_at IS NULL';
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $conversationId, $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $pid = (int)$row['employee_id'];
        if ($pid !== (int)$exceptEmployeeId) {
            itm_employee_notification_create($conn, $companyId, $pid, 'live_chat', (int)$conversationId, $title, $body, lc_conversation_action_url($conversationId));
        }
    }
    mysqli_stmt_close($stmt);
}

function lc_format_messages($rows, $conv)
{
    $out = [];
    foreach ($rows as $row) {
        $attachments = [];
        if (!empty($row['attachments_json'])) {
            $decoded = json_decode((string)$row['attachments_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $att) {
                    if (is_array($att) && !empty($att['filename'])) {
                        $att['url'] = itm_live_chat_attachment_public_url($conv, $att['filename']);
                        $attachments[] = $att;
                    }
                }
            }
        }
        $out[] = [
            'id' => (int)$row['id'],
            'sender_employee_id' => (int)$row['sender_employee_id'],
            'sender_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: ($row['username'] ?? ''),
            'body' => $row['body'],
            'attachments' => $attachments,
            'created_at' => $row['created_at'],
            'is_mine' => ((int)$row['sender_employee_id'] === (int)($_SESSION['employee_id'] ?? 0)),
        ];
    }
    return $out;
}

switch ($action) {
    case 'launch_options_live_agent':
        lc_json(['options' => itm_live_chat_launch_options_live_agent($conn, $companyId)]);
        break;

    case 'launch_options_chat_with':
        lc_json(['options' => itm_live_chat_launch_options_chat_with($conn, $companyId)]);
        break;

    case 'list_conversations':
        $rows = itm_live_chat_list_conversations_sql($conn, $companyId, $employeeId, $isSupportAgent);
        $list = [];
        foreach ($rows as $row) {
            $labelEmployeeId = (int)($row['requester_employee_id'] ?: 0);
            if ((string)$row['conversation_type'] === 'chat_with') {
                $sqlP = 'SELECT employee_id FROM live_chat_participants WHERE conversation_id = ? AND company_id = ? AND employee_id <> ? AND deleted_at IS NULL LIMIT 1';
                $stmtP = mysqli_prepare($conn, $sqlP);
                if ($stmtP) {
                    mysqli_stmt_bind_param($stmtP, 'iii', $row['id'], $companyId, $employeeId);
                    mysqli_stmt_execute($stmtP);
                    $resP = mysqli_stmt_get_result($stmtP);
                    $pRow = $resP ? mysqli_fetch_assoc($resP) : null;
                    mysqli_stmt_close($stmtP);
                    if ($pRow) {
                        $labelEmployeeId = (int)$pRow['employee_id'];
                    }
                }
            }
            $list[] = [
                'id' => (int)$row['id'],
                'conversation_type' => $row['conversation_type'],
                'status' => $row['status'],
                'ticket_id' => $row['ticket_id'] !== null ? (int)$row['ticket_id'] : null,
                'rating' => $row['rating'] !== null ? (int)$row['rating'] : null,
                'peer_label' => itm_user_label_by_id_for_company($conn, $companyId, $labelEmployeeId),
                'updated_at' => $row['updated_at'] ?: $row['created_at'],
                'unread_count' => (int)($row['unread_count'] ?? 0),
            ];
        }
        lc_json(['conversations' => $list, 'notification_unread' => itm_employee_notification_unread_count($conn, $companyId, $employeeId)]);
        break;

    case 'get_conversation':
        $conversationId = (int)($_GET['conversation_id'] ?? $_POST['conversation_id'] ?? 0);
        $conv = lc_require_conv($conn, $companyId, $conversationId, $employeeId);
        itm_live_chat_mark_read($conn, $companyId, $conversationId, $employeeId);
        $detailEmployeeId = (int)($conv['requester_employee_id'] ?: 0);
        if ((string)$conv['conversation_type'] === 'chat_with') {
            $sqlP = 'SELECT employee_id FROM live_chat_participants WHERE conversation_id = ? AND company_id = ? AND employee_id <> ? AND deleted_at IS NULL LIMIT 1';
            $stmtP = mysqli_prepare($conn, $sqlP);
            if ($stmtP) {
                mysqli_stmt_bind_param($stmtP, 'iii', $conversationId, $companyId, $employeeId);
                mysqli_stmt_execute($stmtP);
                $resP = mysqli_stmt_get_result($stmtP);
                $pRow = $resP ? mysqli_fetch_assoc($resP) : null;
                mysqli_stmt_close($stmtP);
                if ($pRow) {
                    $detailEmployeeId = (int)$pRow['employee_id'];
                }
            }
        }
        $sqlE = 'SELECT e.id, e.first_name, e.last_name, e.work_email, e.personal_email, d.name AS department_name, il.name AS location_name
                 FROM employees e
                 LEFT JOIN departments d ON d.id = e.department_id AND d.company_id = e.company_id
                 LEFT JOIN it_locations il ON il.id = e.location_id AND il.company_id = e.company_id
                 WHERE e.id = ? AND e.company_id = ? LIMIT 1';
        $stmtE = mysqli_prepare($conn, $sqlE);
        $employeeDetail = null;
        if ($stmtE) {
            mysqli_stmt_bind_param($stmtE, 'ii', $detailEmployeeId, $companyId);
            mysqli_stmt_execute($stmtE);
            $resE = mysqli_stmt_get_result($stmtE);
            $employeeDetail = $resE ? mysqli_fetch_assoc($resE) : null;
            mysqli_stmt_close($stmtE);
        }
        if (!empty($conv['ticket_id'])) {
            itm_ticket_sla_check_breaches($conn, $companyId, (int)$conv['ticket_id'], $employeeId);
        }
        lc_json([
            'conversation' => [
                'id' => (int)$conv['id'],
                'conversation_type' => $conv['conversation_type'],
                'status' => $conv['status'],
                'ticket_id' => $conv['ticket_id'] !== null ? (int)$conv['ticket_id'] : null,
                'rating' => $conv['rating'] !== null ? (int)$conv['rating'] : null,
                'requester_employee_id' => $conv['requester_employee_id'] !== null ? (int)$conv['requester_employee_id'] : null,
                'assigned_to_employee_id' => $conv['assigned_to_employee_id'] !== null ? (int)$conv['assigned_to_employee_id'] : null,
                'assigned_label' => $conv['assigned_to_employee_id'] ? itm_user_label_by_id_for_company($conn, $companyId, (int)$conv['assigned_to_employee_id']) : '',
                'created_at' => $conv['created_at'],
                'can_claim' => $isSupportAgent && (string)$conv['conversation_type'] === 'live_agent' && (string)$conv['status'] === 'waiting',
            ],
            'employee' => $employeeDetail,
        ]);
        break;

    case 'get_messages':
        $conversationId = (int)($_GET['conversation_id'] ?? 0);
        $sinceId = (int)($_GET['since_id'] ?? 0);
        $conv = lc_require_conv($conn, $companyId, $conversationId, $employeeId);
        $sql = 'SELECT m.*, e.first_name, e.last_name, e.username FROM live_chat_messages m
                LEFT JOIN employees e ON e.id = m.sender_employee_id
                WHERE m.conversation_id = ? AND m.company_id = ? AND m.deleted_at IS NULL';
        if ($sinceId > 0) {
            $sql .= ' AND m.id > ' . $sinceId;
        }
        $sql .= ' ORDER BY m.id ASC LIMIT 200';
        $stmt = mysqli_prepare($conn, $sql);
        $rows = [];
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $conversationId, $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        lc_json(['messages' => lc_format_messages($rows, $conv)]);
        break;

    case 'send_message':
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $body = trim((string)($_POST['body'] ?? ''));
        $conv = lc_require_conv($conn, $companyId, $conversationId, $employeeId);
        if ((string)$conv['status'] === 'closed') {
            lc_json(['error' => 'Conversation is closed'], 400);
        }
        if ($body === '') {
            lc_json(['error' => 'Message body required'], 400);
        }
        $sql = 'INSERT INTO live_chat_messages (company_id, conversation_id, sender_employee_id, body, created_by)
                VALUES (?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            lc_json(['error' => 'Database error'], 500);
        }
        mysqli_stmt_bind_param($stmt, 'iiisi', $companyId, $conversationId, $employeeId, $body, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        $messageId = $ok ? (int)mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($stmt);
        if (!$ok) {
            lc_json(['error' => 'Failed to send'], 500);
        }
        mysqli_query($conn, 'UPDATE live_chat_conversations SET updated_at = NOW() WHERE id = ' . (int)$conversationId);
        if ($isSupportAgent && (string)$conv['conversation_type'] === 'live_agent' && !empty($conv['ticket_id'])) {
            itm_ticket_sla_stamp_first_response($conn, (int)$conv['ticket_id'], $companyId);
        }
        $conv = itm_live_chat_fetch_conversation_row($conn, $companyId, $conversationId);
        itm_live_chat_write_chat_json($conn, $conv);
        lc_notify_participants_except($conn, $companyId, $conversationId, $employeeId, 'New chat message', mb_substr($body, 0, 120));
        lc_json(['success' => true, 'message_id' => $messageId]);
        break;

    case 'set_typing':
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        lc_require_conv($conn, $companyId, $conversationId, $employeeId);
        $sql = 'INSERT INTO live_chat_typing (company_id, conversation_id, employee_id, expires_at)
                VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 8 SECOND))
                ON DUPLICATE KEY UPDATE expires_at = DATE_ADD(NOW(), INTERVAL 8 SECOND)';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iii', $companyId, $conversationId, $employeeId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        lc_json(['success' => true]);
        break;

    case 'poll':
        $conversationId = (int)($_GET['conversation_id'] ?? 0);
        $sinceId = (int)($_GET['since_id'] ?? 0);
        if ($conversationId > 0) {
            $conv = lc_require_conv($conn, $companyId, $conversationId, $employeeId);
            mysqli_query($conn, 'DELETE FROM live_chat_typing WHERE expires_at < NOW()');
            $sql = 'SELECT m.*, e.first_name, e.last_name, e.username FROM live_chat_messages m
                    LEFT JOIN employees e ON e.id = m.sender_employee_id
                    WHERE m.conversation_id = ? AND m.company_id = ? AND m.deleted_at IS NULL AND m.id > ?
                    ORDER BY m.id ASC LIMIT 100';
            $stmt = mysqli_prepare($conn, $sql);
            $rows = [];
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iii', $conversationId, $companyId, $sinceId);
                mysqli_stmt_execute($stmt);
                $res = mysqli_stmt_get_result($stmt);
                while ($res && ($row = mysqli_fetch_assoc($res))) {
                    $rows[] = $row;
                }
                mysqli_stmt_close($stmt);
            }
            $sqlT = 'SELECT employee_id FROM live_chat_typing WHERE conversation_id = ? AND employee_id <> ? AND expires_at > NOW()';
            $stmtT = mysqli_prepare($conn, $sqlT);
            $typing = [];
            if ($stmtT) {
                mysqli_stmt_bind_param($stmtT, 'ii', $conversationId, $employeeId);
                mysqli_stmt_execute($stmtT);
                $resT = mysqli_stmt_get_result($stmtT);
                while ($resT && ($tRow = mysqli_fetch_assoc($resT))) {
                    $typing[] = (int)$tRow['employee_id'];
                }
                mysqli_stmt_close($stmtT);
            }
            if (!empty($conv['ticket_id'])) {
                itm_ticket_sla_check_breaches($conn, $companyId, (int)$conv['ticket_id'], $employeeId);
            }
            lc_json([
                'messages' => lc_format_messages($rows, $conv),
                'typing_employee_ids' => $typing,
                'notification_unread' => itm_employee_notification_unread_count($conn, $companyId, $employeeId),
            ]);
        }
        lc_json([
            'conversations' => itm_live_chat_list_conversations_sql($conn, $companyId, $employeeId, $isSupportAgent),
            'notification_unread' => itm_employee_notification_unread_count($conn, $companyId, $employeeId),
        ]);
        break;

    case 'start_live_agent':
        $ticketMode = trim((string)($_POST['ticket_mode'] ?? 'existing'));
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        if ($ticketMode === 'new') {
            if ($title === '') {
                lc_json(['error' => 'Ticket title required'], 400);
            }
            $ticketId = (int)itm_live_chat_create_ticket($conn, $companyId, $employeeId, $title, $description);
            if ($ticketId <= 0) {
                lc_json(['error' => 'Failed to create ticket'], 500);
            }
            $existingTicket = false;
        } elseif ($ticketMode === 'reopen') {
            if ($ticketId <= 0) {
                lc_json(['error' => 'Ticket id required'], 400);
            }
            $reopened = itm_live_chat_reopen_ticket($conn, $companyId, $ticketId, $employeeId, $isSupportAgent);
            if ($reopened === false) {
                lc_json(['error' => 'Ticket is not closed or cannot be reopened'], 400);
            }
            $existingTicket = true;
        } else {
            if ($ticketId <= 0) {
                lc_json(['error' => 'Ticket id required'], 400);
            }
            if (!itm_live_chat_validate_ticket_for_requester($conn, $companyId, $ticketId, $employeeId, $isSupportAgent)) {
                lc_json(['error' => 'Invalid ticket'], 403);
            }
            $existingTicket = true;
        }
        $sql = 'INSERT INTO live_chat_conversations (company_id, conversation_type, ticket_id, requester_employee_id, status, created_by)
                VALUES (?, \'live_agent\', ?, ?, \'waiting\', ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            lc_json(['error' => 'Database error'], 500);
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $companyId, $ticketId, $employeeId, $employeeId);
        $ok = mysqli_stmt_execute($stmt);
        $conversationId = $ok ? (int)mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($stmt);
        if (!$ok) {
            lc_json(['error' => 'Failed to start chat'], 500);
        }
        $sqlP = 'INSERT INTO live_chat_participants (company_id, conversation_id, employee_id, role, created_by) VALUES (?, ?, ?, \'requester\', ?)';
        $stmtP = mysqli_prepare($conn, $sqlP);
        if ($stmtP) {
            mysqli_stmt_bind_param($stmtP, 'iiii', $companyId, $conversationId, $employeeId, $employeeId);
            mysqli_stmt_execute($stmtP);
            mysqli_stmt_close($stmtP);
        }
        $conv = itm_live_chat_fetch_conversation_row($conn, $companyId, $conversationId);
        itm_live_chat_ensure_storage($conn, $conv);
        itm_live_chat_write_chat_json($conn, $conv);
        itm_ticket_activity_log($conn, $companyId, $ticketId, $employeeId, 'live_chat_started', [
            'conversation_id' => $conversationId,
            'ticket_id' => $ticketId,
            'existing_ticket' => $existingTicket,
            'ticket_mode' => $ticketMode,
        ]);
        itm_live_chat_notify_support_agents_waiting($conn, $companyId, $conversationId, 'Live Agent waiting', 'A new live agent chat is waiting');
        lc_json(['success' => true, 'conversation_id' => $conversationId]);
        break;

    case 'start_chat_with':
        $peerId = (int)($_POST['peer_employee_id'] ?? 0);
        if ($peerId <= 0 || $peerId === $employeeId) {
            lc_json(['error' => 'Invalid peer'], 400);
        }
        if (!itm_live_chat_peer_eligible_for_company($conn, $peerId, $companyId)) {
            lc_json(['error' => 'Peer is not eligible for the active company'], 403);
        }
        $sqlFind = 'SELECT c.id FROM live_chat_conversations c
                    INNER JOIN live_chat_participants p1 ON p1.conversation_id = c.id AND p1.employee_id = ?
                    INNER JOIN live_chat_participants p2 ON p2.conversation_id = c.id AND p2.employee_id = ?
                    WHERE c.company_id = ? AND c.conversation_type = \'chat_with\' AND c.status <> \'closed\' AND c.deleted_at IS NULL
                    LIMIT 1';
        $stmtFind = mysqli_prepare($conn, $sqlFind);
        $conversationId = 0;
        if ($stmtFind) {
            mysqli_stmt_bind_param($stmtFind, 'iii', $employeeId, $peerId, $companyId);
            mysqli_stmt_execute($stmtFind);
            $resFind = mysqli_stmt_get_result($stmtFind);
            $found = $resFind ? mysqli_fetch_assoc($resFind) : null;
            mysqli_stmt_close($stmtFind);
            if ($found) {
                $conversationId = (int)$found['id'];
            }
        }
        if ($conversationId <= 0) {
            $sql = 'INSERT INTO live_chat_conversations (company_id, conversation_type, requester_employee_id, status, created_by)
                    VALUES (?, \'chat_with\', ?, \'active\', ?)';
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                lc_json(['error' => 'Database error'], 500);
            }
            mysqli_stmt_bind_param($stmt, 'iii', $companyId, $employeeId, $employeeId);
            mysqli_stmt_execute($stmt);
            $conversationId = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            foreach ([$employeeId, $peerId] as $pid) {
                $sqlP = 'INSERT INTO live_chat_participants (company_id, conversation_id, employee_id, role, created_by) VALUES (?, ?, ?, \'peer\', ?)';
                $stmtP = mysqli_prepare($conn, $sqlP);
                if ($stmtP) {
                    mysqli_stmt_bind_param($stmtP, 'iiii', $companyId, $conversationId, $pid, $employeeId);
                    mysqli_stmt_execute($stmtP);
                    mysqli_stmt_close($stmtP);
                }
            }
            $conv = itm_live_chat_fetch_conversation_row($conn, $companyId, $conversationId);
            itm_live_chat_ensure_storage($conn, $conv);
            itm_live_chat_write_chat_json($conn, $conv);
        }
        lc_json(['success' => true, 'conversation_id' => $conversationId]);
        break;

    case 'claim_conversation':
        if (!$isSupportAgent) {
            lc_json(['error' => 'Forbidden'], 403);
        }
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $conv = lc_require_conv($conn, $companyId, $conversationId, $employeeId);
        if ((string)$conv['conversation_type'] !== 'live_agent' || (string)$conv['status'] !== 'waiting') {
            lc_json(['error' => 'Cannot claim'], 400);
        }
        $sql = 'UPDATE live_chat_conversations SET assigned_to_employee_id = ?, status = \'active\', updated_by = ? WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            lc_json(['error' => 'Database error'], 500);
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $employeeId, $employeeId, $conversationId, $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $sqlP = 'INSERT INTO live_chat_participants (company_id, conversation_id, employee_id, role, created_by)
                 VALUES (?, ?, ?, \'agent\', ?) ON DUPLICATE KEY UPDATE role = \'agent\'';
        $stmtP = mysqli_prepare($conn, $sqlP);
        if ($stmtP) {
            mysqli_stmt_bind_param($stmtP, 'iiii', $companyId, $conversationId, $employeeId, $employeeId);
            mysqli_stmt_execute($stmtP);
            mysqli_stmt_close($stmtP);
        }
        if (!empty($conv['ticket_id'])) {
            $tid = (int)$conv['ticket_id'];
            $sqlT = 'UPDATE tickets SET assigned_to_employee_id = ? WHERE id = ? AND company_id = ?';
            $stmtT = mysqli_prepare($conn, $sqlT);
            if ($stmtT) {
                mysqli_stmt_bind_param($stmtT, 'iii', $employeeId, $tid, $companyId);
                mysqli_stmt_execute($stmtT);
                mysqli_stmt_close($stmtT);
            }
            itm_ticket_activity_log($conn, $companyId, $tid, $employeeId, 'live_chat_claimed', [
                'conversation_id' => $conversationId,
                'assigned_to_employee_id' => $employeeId,
            ]);
            itm_ticket_activity_log($conn, $companyId, $tid, $employeeId, 'assigned', [
                'to_employee_id' => $employeeId,
            ]);
        }
        $requesterId = (int)$conv['requester_employee_id'];
        if ($requesterId > 0) {
            itm_employee_notification_create($conn, $companyId, $requesterId, 'live_chat', $conversationId, 'Agent joined', 'A support agent joined your live chat', lc_conversation_action_url($conversationId));
        }
        lc_json(['success' => true]);
        break;

    case 'rate_conversation':
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            lc_json(['error' => 'Rating must be 1-5'], 400);
        }
        $conv = lc_require_conv($conn, $companyId, $conversationId, $employeeId);
        $sql = 'UPDATE live_chat_conversations SET rating = ?, updated_by = ? WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            lc_json(['error' => 'Database error'], 500);
        }
        mysqli_stmt_bind_param($stmt, 'iiii', $rating, $employeeId, $conversationId, $companyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $conv = itm_live_chat_fetch_conversation_row($conn, $companyId, $conversationId);
        itm_live_chat_write_chat_json($conn, $conv);
        if (!empty($conv['ticket_id'])) {
            itm_ticket_activity_log($conn, $companyId, (int)$conv['ticket_id'], $employeeId, 'live_chat_rated', [
                'conversation_id' => $conversationId,
                'rating' => $rating,
                'rated_by_employee_id' => $employeeId,
            ]);
        }
        lc_json(['success' => true, 'rating' => $rating]);
        break;

    case 'close_conversation':
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $conv = lc_require_conv($conn, $companyId, $conversationId, $employeeId);
        $sql = 'UPDATE live_chat_conversations SET status = \'closed\', updated_by = ? WHERE id = ? AND company_id = ?';
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iii', $employeeId, $conversationId, $companyId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $conv = itm_live_chat_fetch_conversation_row($conn, $companyId, $conversationId);
        itm_live_chat_write_chat_json($conn, $conv);
        if (!empty($conv['ticket_id'])) {
            $tid = (int)$conv['ticket_id'];
            itm_ticket_sla_stamp_resolved($conn, $tid, $companyId);
            itm_ticket_activity_log($conn, $companyId, $tid, $employeeId, 'live_chat_closed', [
                'conversation_id' => $conversationId,
                'closed_by_employee_id' => $employeeId,
            ]);
            $ratingText = $conv['rating'] !== null ? (int)$conv['rating'] . '/5' : 'n/a';
            itm_ticket_comment_create($conn, $companyId, $tid, $employeeId, 'Live chat session closed (rating: ' . $ratingText . ')', 0);
        }
        lc_notify_participants_except($conn, $companyId, $conversationId, $employeeId, 'Chat closed', 'A conversation was closed');
        lc_json(['success' => true]);
        break;

    case 'upload_attachment':
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $conv = lc_require_conv($conn, $companyId, $conversationId, $employeeId);
        if ((string)$conv['status'] === 'closed') {
            lc_json(['error' => 'Conversation is closed'], 400);
        }
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            lc_json(['error' => 'No file uploaded'], 400);
        }
        $origName = (string)($_FILES['file']['name'] ?? 'file');
        if (!itm_live_chat_allowed_upload_extension($origName)) {
            lc_json(['error' => 'File type not allowed'], 400);
        }
        $maxSize = defined('EXPLORER_MAX_FILE_SIZE') ? (int)EXPLORER_MAX_FILE_SIZE : (20 * 1024 * 1024);
        if ((int)($_FILES['file']['size'] ?? 0) > $maxSize) {
            lc_json(['error' => 'File too large'], 400);
        }
        $storageRel = itm_live_chat_ensure_storage($conn, $conv);
        $abs = itm_live_chat_storage_absolute_path($conv, $storageRel);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $storedName = 'lc_' . time() . '_' . bin2hex(random_bytes(4)) . ($ext !== '' ? '.' . $ext : '');
        $dest = $abs . DIRECTORY_SEPARATOR . $storedName;
        if (!@move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            lc_json(['error' => 'Upload failed'], 500);
        }
        $att = [
            'filename' => $storedName,
            'original_name' => $origName,
            'mime' => (string)($_FILES['file']['type'] ?? 'application/octet-stream'),
            'size' => (int)($_FILES['file']['size'] ?? 0),
            'uploaded_by' => $employeeId,
        ];
        $body = '[attachment] ' . $origName;
        $attJson = json_encode([$att], JSON_UNESCAPED_UNICODE);
        $sql = 'INSERT INTO live_chat_messages (company_id, conversation_id, sender_employee_id, body, attachments_json, created_by)
                VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            lc_json(['error' => 'Database error'], 500);
        }
        mysqli_stmt_bind_param($stmt, 'iiissi', $companyId, $conversationId, $employeeId, $body, $attJson, $employeeId);
        mysqli_stmt_execute($stmt);
        $messageId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        $conv = itm_live_chat_fetch_conversation_row($conn, $companyId, $conversationId);
        itm_live_chat_write_chat_json($conn, $conv);
        if ($isSupportAgent && (string)$conv['conversation_type'] === 'live_agent' && !empty($conv['ticket_id'])) {
            itm_ticket_sla_stamp_first_response($conn, (int)$conv['ticket_id'], $companyId);
        }
        lc_json(['success' => true, 'message_id' => $messageId, 'attachment' => array_merge($att, ['url' => itm_live_chat_attachment_public_url($conv, $storedName)])]);
        break;

    case 'delete_attachment':
        $conversationId = (int)($_POST['conversation_id'] ?? 0);
        $messageId = (int)($_POST['message_id'] ?? 0);
        $filename = basename((string)($_POST['filename'] ?? ''));
        $conv = lc_require_conv($conn, $companyId, $conversationId, $employeeId);
        $sql = 'SELECT attachments_json FROM live_chat_messages WHERE id = ? AND conversation_id = ? AND company_id = ? LIMIT 1';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            lc_json(['error' => 'Not found'], 404);
        }
        mysqli_stmt_bind_param($stmt, 'iii', $messageId, $conversationId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) {
            lc_json(['error' => 'Not found'], 404);
        }
        $attachments = json_decode((string)$row['attachments_json'], true);
        if (!is_array($attachments)) {
            lc_json(['error' => 'No attachments'], 400);
        }
        $kept = [];
        $deleted = false;
        foreach ($attachments as $att) {
            if (is_array($att) && ($att['filename'] ?? '') === $filename) {
                if ((int)($att['uploaded_by'] ?? 0) !== $employeeId) {
                    lc_json(['error' => 'Forbidden'], 403);
                }
                $path = itm_live_chat_resolve_attachment_path($conv, $conv['storage_rel_path'], $filename);
                if ($path && is_file($path)) {
                    @unlink($path);
                }
                $deleted = true;
                continue;
            }
            $kept[] = $att;
        }
        if (!$deleted) {
            lc_json(['error' => 'Attachment not found'], 404);
        }
        $newJson = $kept ? json_encode($kept, JSON_UNESCAPED_UNICODE) : null;
        $sqlU = 'UPDATE live_chat_messages SET attachments_json = ? WHERE id = ? AND conversation_id = ?';
        $stmtU = mysqli_prepare($conn, $sqlU);
        if ($stmtU) {
            mysqli_stmt_bind_param($stmtU, 'sii', $newJson, $messageId, $conversationId);
            mysqli_stmt_execute($stmtU);
            mysqli_stmt_close($stmtU);
        }
        $conv = itm_live_chat_fetch_conversation_row($conn, $companyId, $conversationId);
        itm_live_chat_write_chat_json($conn, $conv);
        lc_json(['success' => true]);
        break;

    case 'list_notifications':
        lc_json(['notifications' => itm_employee_notifications_list_recent($conn, $companyId, $employeeId, 20)]);
        break;

    case 'mark_notification_read':
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        itm_employee_notification_mark_read($conn, $companyId, $employeeId, $notificationId);
        lc_json(['success' => true]);
        break;

    case 'list_open_tickets':
        $sql = 'SELECT id, title FROM tickets WHERE company_id = ? AND deleted_at IS NULL AND active = 1';
        if (!$isSupportAgent) {
            $sql .= ' AND created_by_employee_id = ' . (int)$employeeId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 50';
        $stmt = mysqli_prepare($conn, $sql);
        $tickets = [];
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $tickets[] = ['id' => (int)$row['id'], 'title' => $row['title']];
            }
            mysqli_stmt_close($stmt);
        }
        lc_json(['tickets' => $tickets]);
        break;

    case 'list_closed_tickets':
        $sql = 'SELECT t.id, t.title
                FROM tickets t
                INNER JOIN ticket_statuses ts ON ts.id = t.status_id AND ts.company_id = t.company_id
                WHERE t.company_id = ? AND t.deleted_at IS NULL AND t.active = 1 AND ts.is_closed = 1';
        if (!$isSupportAgent) {
            $sql .= ' AND t.created_by_employee_id = ' . (int)$employeeId;
        }
        $sql .= ' ORDER BY t.id DESC LIMIT 50';
        $stmt = mysqli_prepare($conn, $sql);
        $tickets = [];
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($res && ($row = mysqli_fetch_assoc($res))) {
                $tickets[] = ['id' => (int)$row['id'], 'title' => $row['title']];
            }
            mysqli_stmt_close($stmt);
        }
        lc_json(['tickets' => $tickets]);
        break;

    case 'list_employees':
        $options = itm_live_chat_peer_options_for_company($conn, $companyId);
        $out = [];
        foreach ($options as $opt) {
            if ((int)$opt['id'] === $employeeId) {
                continue;
            }
            $out[] = $opt;
        }
        lc_json(['employees' => $out]);
        break;

    default:
        lc_json(['error' => 'Unknown action'], 400);
}
