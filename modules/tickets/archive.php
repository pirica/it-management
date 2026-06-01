<?php
/**
 * Tickets Module - Archive/Un-archive
 *
 * Toggles the is_archived status of a ticket.
 */

require '../../config/config.php';

// Only allow via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// CSRF Protection
itm_require_post_csrf();

$id = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['archive_action'] ?? 'archive');

if ($id > 0 && $company_id > 0) {
    $isArchivedValue = ($action === 'archive') ? 1 : 0;

    // If archiving, verify status is "Closed" as per requirement
    if ($isArchivedValue === 1) {
        $statusStmt = mysqli_prepare($conn, "
            SELECT ts.name
            FROM tickets t
            JOIN ticket_statuses ts ON ts.id = t.status_id
            WHERE t.id = ? AND t.company_id = ?
        ");
        if ($statusStmt) {
            mysqli_stmt_bind_param($statusStmt, 'ii', $id, $company_id);
            mysqli_stmt_execute($statusStmt);
            $res = mysqli_stmt_get_result($statusStmt);
            $row = mysqli_fetch_assoc($res);
            mysqli_stmt_close($statusStmt);

            if (!$row || strcasecmp($row['name'], 'Closed') !== 0) {
                $_SESSION['crud_error'] = 'Only Closed tickets can be archived.';
                header('Location: index.php');
                exit;
            }
        }
    }

    $stmt = mysqli_prepare($conn, "UPDATE tickets SET is_archived = ? WHERE id = ? AND company_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iii', $isArchivedValue, $id, $company_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['crud_success'] = ($isArchivedValue === 1) ? 'Ticket archived.' : 'Ticket un-archived.';
        } else {
            $_SESSION['crud_error'] = 'Failed to update ticket.';
        }
        mysqli_stmt_close($stmt);
    }
}

// Redirect back - if we were in archived view, we might want to stay there or go back to main
// For simplicity, redirect to index.php. If they were in "show archived" mode,
// they might need to click the button again, unless we pass a param.
$redirectUrl = 'index.php';
if (isset($_POST['redirect_archived']) && $_POST['redirect_archived'] == '1') {
    $redirectUrl .= '?show_archived=1';
}

header('Location: ' . $redirectUrl);
exit;
