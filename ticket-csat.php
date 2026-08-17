<?php
define('ITM_TICKET_CSAT_PUBLIC', true);
require_once __DIR__ . '/config/config.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$payload = $token !== '' ? itm_ticket_csat_verify_token($token) : null;
$error = '';
$success = false;
$ticketTitle = '';

if ($payload && $conn) {
    $companyId = (int)$payload['company_id'];
    $ticketId = (int)$payload['ticket_id'];
    $stmt = mysqli_prepare($conn, 'SELECT title, csat_submitted_at FROM tickets WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (is_array($row)) {
            $ticketTitle = (string)($row['title'] ?? '');
            if (!empty($row['csat_submitted_at'])) {
                $success = true;
            }
        } else {
            $payload = null;
            $error = 'Ticket not found or no longer available.';
        }
    }
}

if ($payload && !$success && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_csat'])) {
    $score = (int)($_POST['csat_score'] ?? 0);
    $comment = trim((string)($_POST['csat_comment'] ?? ''));
    if ($score < 1 || $score > 5) {
        $error = 'Please select a rating from 1 to 5.';
    } elseif (itm_ticket_csat_submit($conn, (int)$payload['company_id'], (int)$payload['ticket_id'], $score, $comment)) {
        $success = true;
    } else {
        $error = 'Unable to save your feedback. The survey may have already been submitted.';
    }
}

$appName = itm_ui_config_app_name($ui_config ?? []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket feedback - <?php echo sanitize($appName); ?></title>
    <link rel="stylesheet" href="<?php echo sanitize(rtrim(BASE_URL, '/') . '/css/styles.css'); ?>">
    <style>.itm-csat-wrap { max-width: 520px; margin: 48px auto; padding: 0 16px; } .itm-csat-stars { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0; }</style>
</head>
<body>
<div class="itm-csat-wrap">
    <div class="card">
        <h1 title="Ticket feedback">⭐</h1>
        <?php if (!$payload): ?>
            <div class="alert alert-danger"><?php echo sanitize($error !== '' ? $error : 'Invalid or expired survey link.'); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success">Thank you — your feedback has been recorded.</div>
        <?php else: ?>
            <?php if ($ticketTitle !== ''): ?><p><strong>Ticket:</strong> <?php echo sanitize($ticketTitle); ?></p><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo sanitize($token); ?>">
                <p>How satisfied were you with our support?</p>
                <div class="itm-csat-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <label><input type="radio" name="csat_score" value="<?php echo $i; ?>" required> <?php echo $i; ?></label>
                    <?php endfor; ?>
                </div>
                <div class="form-group">
                    <label for="csat_comment">Comments (optional)</label>
                    <textarea id="csat_comment" name="csat_comment" rows="4" maxlength="2000"></textarea>
                </div>
                <button type="submit" name="submit_csat" value="1" class="btn btn-primary" title="Submit feedback">💾</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
