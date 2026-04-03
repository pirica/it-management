<?php
require '../../config/config.php';

function ticket_parse_photo_filenames($rawValue): array
{
    if (!is_string($rawValue) || trim($rawValue) === '') {
        return [];
    }

    $decoded = json_decode($rawValue, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter(array_map(static function ($value) {
        return basename((string)$value);
    }, $decoded), static function ($value) {
        return $value !== '';
    }));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

itm_require_post_csrf();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $usageError = '';
    if (!itm_can_delete_record($conn, 'tickets', 'id', $id, $company_id, $usageError)) {
        $_SESSION['crud_error'] = $usageError;
    } else {
        $ticketPhotos = [];
        $selectStmt = mysqli_prepare($conn, 'SELECT tickets_photos FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
        if ($selectStmt) {
            mysqli_stmt_bind_param($selectStmt, 'ii', $id, $company_id);
            mysqli_stmt_execute($selectStmt);
            $selectResult = mysqli_stmt_get_result($selectStmt);
            if ($selectResult && mysqli_num_rows($selectResult) === 1) {
                $ticketRow = mysqli_fetch_assoc($selectResult);
                $ticketPhotos = ticket_parse_photo_filenames((string)($ticketRow['tickets_photos'] ?? ''));
            }
            mysqli_stmt_close($selectStmt);
        }

        $stmt = mysqli_prepare($conn, 'DELETE FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
            mysqli_stmt_execute($stmt);
            $affectedRows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affectedRows > 0 && !empty($ticketPhotos)) {
                foreach ($ticketPhotos as $ticketPhotoFilename) {
                    $ticketPhotoPath = TICKET_UPLOAD_PATH . $ticketPhotoFilename;
                    if (is_file($ticketPhotoPath)) {
                        @unlink($ticketPhotoPath);
                    }
                }
            }
        }
    }
}

header('Location: index.php');
exit;
