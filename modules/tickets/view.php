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

    return array_values(array_filter(array_map('strval', $decoded), static function ($value) {
        return $value !== '';
    }));
}

function ticket_photo_public_path(string $filename): string
{
    return TICKET_UPLOAD_URL . rawurlencode($filename);
}

$id = (int)($_GET['id'] ?? 0);
$item = null;
if ($id > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $id, $company_id);
        mysqli_stmt_execute($stmt);
        $query = mysqli_stmt_get_result($stmt);
        if ($query && mysqli_num_rows($query) === 1) {
            $item = mysqli_fetch_assoc($query);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Tickets</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <h1>🔎 View Tickets Record</h1>
            <div class="card">
                <?php if (!$item): ?>
                    <div class="alert alert-danger">Record not found.</div>
                <?php else: ?>
                    <table>
                        <tbody>
                        <?php foreach ($item as $field => $value): ?>
                            <?php if ($field === 'tickets_photos'): ?>
                                <?php $ticketPhotos = ticket_parse_photo_filenames((string)$value); ?>
                                <tr>
                                    <th style="width:220px;"><?php echo sanitize((string)$field); ?></th>
                                    <td>
                                        <?php if (empty($ticketPhotos)): ?>
                                            <span>—</span>
                                        <?php else: ?>
                                            <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                <?php foreach ($ticketPhotos as $ticketPhoto): ?>
                                                    <a href="<?php echo sanitize(ticket_photo_public_path($ticketPhoto)); ?>" target="_blank" rel="noopener noreferrer">
                                                        <img
                                                            src="<?php echo sanitize(ticket_photo_public_path($ticketPhoto)); ?>"
                                                            alt="Ticket photo"
                                                            style="width:96px;height:96px;object-fit:cover;border:1px solid #d0d7de;border-radius:6px;"
                                                        >
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php continue; ?>
                            <?php endif; ?>
                            <tr>
                                <th style="width:220px;"><?php echo sanitize((string)$field); ?></th>
                                <td><?php echo sanitize((string)($value ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="index.php" class="btn">Back</a>
                    <?php if ($item): ?>
                        <a href="edit.php?id=<?php echo (int)$item['id']; ?>" class="btn btn-primary">✏️</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
