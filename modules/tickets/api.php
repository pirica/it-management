<?php
/**
 * Tickets module JSON API (Activity comment AJAX).
 */
require_once '../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

itm_api_enforce_rate_limit_or_exit($conn);
itm_release_session_lock();

$companyId = (int)($_SESSION['company_id'] ?? 0);
$employeeId = (int)($_SESSION['employee_id'] ?? 0);
if ($companyId <= 0 || $employeeId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

function tickets_api_json_error(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'add_comment' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    itm_require_post_csrf();
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    if ($ticketId <= 0) {
        tickets_api_json_error(400, 'Invalid ticket.');
    }

    $ticketStmt = mysqli_prepare(
        $conn,
        'SELECT id FROM tickets WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1'
    );
    if (!$ticketStmt) {
        tickets_api_json_error(500, 'Database error.');
    }
    mysqli_stmt_bind_param($ticketStmt, 'ii', $ticketId, $companyId);
    mysqli_stmt_execute($ticketStmt);
    $ticketRes = mysqli_stmt_get_result($ticketStmt);
    $ticketRow = $ticketRes ? mysqli_fetch_assoc($ticketRes) : null;
    mysqli_stmt_close($ticketStmt);
    if (!$ticketRow) {
        tickets_api_json_error(404, 'Ticket not found.');
    }

    $isSupportAgent = itm_live_chat_is_support_agent($conn, $employeeId);
    $commentBody = trim((string)($_POST['comment_body'] ?? ''));
    $isInternal = !empty($_POST['is_internal']) && $isSupportAgent ? 1 : 0;
    $photoFiles = isset($_FILES['comment_photo']) && is_array($_FILES['comment_photo']) ? $_FILES['comment_photo'] : null;

    $commentId = itm_ticket_comment_create_with_photos(
        $conn,
        $companyId,
        $ticketId,
        $employeeId,
        $commentBody,
        $isInternal,
        $photoFiles
    );
    if ($commentId <= 0) {
        tickets_api_json_error(400, 'Comment body or photo is required.');
    }

    $commentRow = itm_ticket_comment_fetch_row($conn, $companyId, $ticketId, $commentId, $employeeId, $isSupportAgent);
    if (!$commentRow) {
        tickets_api_json_error(500, 'Comment saved but could not be loaded.');
    }

    $feedItem = [
        'kind' => 'comment',
        'sort_at' => (string)($commentRow['created_at'] ?? ''),
        'comment' => $commentRow,
    ];

    echo json_encode([
        'success' => true,
        'comment_id' => $commentId,
        'html' => itm_ticket_activity_render_feed_item_html($feedItem),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

tickets_api_json_error(400, 'Unknown action.');
