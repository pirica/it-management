<?php
/**
 * Tickets Module - Archive/Un-archive Handler
 *
 * Handles the logic for archiving and un-archiving support tickets.
 * - Archiving is only permitted for "Closed" tickets.
 * - CSRF protection is enforced.
 * - Access is restricted to the user's current company scope.
 */

require '../../config/config.php';

// Ensure the request method is POST for security
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php');
    exit;
}

// Validate CSRF token
itm_require_post_csrf();

$ticketId = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['archive_action'] ?? '');
$redirectArchived = (int)($_POST['redirect_archived'] ?? 0) === 1;

if ($ticketId <= 0 || !in_array($action, ['archive', 'unarchive'], true)) {
    $_SESSION['crud_error'] = 'Invalid request parameters.';
    header('Location: index.php');
    exit;
}

// Fetch ticket to verify ownership and status
$stmt = mysqli_prepare($conn, "
    SELECT t.id, ts.name AS status_name, ts.is_closed
    FROM tickets t
    LEFT JOIN ticket_statuses ts ON ts.id = t.status_id
    WHERE t.id = ? AND t.company_id = ?
    LIMIT 1
");

if (!$stmt) {
    $_SESSION['crud_error'] = 'Database preparation error.';
    header('Location: index.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $company_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$ticket = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$ticket) {
    $_SESSION['crud_error'] = 'Ticket not found or access denied.';
    header('Location: index.php');
    exit;
}

if ($action === 'archive') {
    // Confirm the ticket is closed before archiving
    $isClosed = (int)($ticket['is_closed'] ?? 0) === 1 || strcasecmp((string)($ticket['status_name'] ?? ''), 'Closed') === 0;
    if (!$isClosed) {
        $_SESSION['crud_error'] = 'Only closed tickets can be archived.';
        header('Location: index.php');
        exit;
    }

    $updateStmt = mysqli_prepare($conn, "UPDATE tickets SET is_archived = 1 WHERE id = ? AND company_id = ?");
    $successMsg = 'Ticket archived successfully.';
} else {
    // Un-archiving has no status restriction
    $updateStmt = mysqli_prepare($conn, "UPDATE tickets SET is_archived = 0 WHERE id = ? AND company_id = ?");
    $successMsg = 'Ticket restored to active list.';
}

if ($updateStmt) {
    mysqli_stmt_bind_param($updateStmt, 'ii', $ticketId, $company_id);
    if (mysqli_stmt_execute($updateStmt)) {
        $_SESSION['crud_success'] = $successMsg;
    } else {
        $_SESSION['crud_error'] = 'Failed to update ticket status.';
    }
    mysqli_stmt_close($updateStmt);
} else {
    $_SESSION['crud_error'] = 'Database preparation error on update.';
}

$redirectUrl = $redirectArchived ? 'index.php?show_archived=1' : 'index.php';
header('Location: ' . $redirectUrl);
exit;
