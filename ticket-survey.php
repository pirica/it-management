<?php
define('ITM_TICKET_SURVEY_PUBLIC', true);
require_once __DIR__ . '/config/config.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$payload = $token !== '' ? itm_ticket_survey_verify_token($conn, $token) : null;
$error = '';
$success = false;
$ticketTitle = '';
$questions = [];

if ($payload && $conn) {
    $companyId = (int)$payload['company_id'];
    $ticketId = (int)$payload['ticket_id'];
    $surveyId = (int)$payload['survey_id'];
    $questionnaireId = (int)$payload['questionnaire_id'];
    if (!empty($payload['completed_at'])) {
        $success = true;
    }
    $stmt = mysqli_prepare($conn, 'SELECT title FROM tickets WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (is_array($row)) {
            $ticketTitle = (string)($row['title'] ?? '');
        } else {
            $payload = null;
            $error = 'Ticket not found or no longer available.';
        }
    }
    if ($payload && !$success) {
        $questions = itm_ticket_survey_load_questions($conn, $questionnaireId, $companyId);
        if ($questions === []) {
            $payload = null;
            $error = 'Survey questions are not available.';
        }
    }
}

if ($payload && !$success && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey'])) {
    if (!itm_validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired form submission. Please refresh the page and try again.';
    } else {
        $answers = [];
        foreach ($questions as $q) {
            $qid = (int)$q['id'];
            $type = (string)($q['question_type'] ?? 'rating_1_5');
            if ($type === 'text') {
                $answers[$qid] = trim((string)($_POST['answer_text_' . $qid] ?? ''));
            } else {
                $answers[$qid] = (int)($_POST['answer_rating_' . $qid] ?? 0);
            }
        }
        $acceptFeedback = isset($_POST['accept_feedback']) ? (int)$_POST['accept_feedback'] : null;
        if (itm_ticket_survey_submit($conn, (int)$payload['survey_id'], $answers, $acceptFeedback)) {
            $success = true;
        } else {
            $error = 'Unable to save your feedback. Please complete all required questions.';
        }
    }
}

$appName = itm_ui_config_app_name($ui_config ?? []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket survey - <?php echo sanitize($appName); ?></title>
    <link rel="stylesheet" href="<?php echo sanitize(rtrim(BASE_URL, '/') . '/css/styles.css'); ?>">
    <style>.itm-survey-wrap { max-width: 560px; margin: 48px auto; padding: 0 16px; } .itm-survey-stars { display: flex; gap: 8px; flex-wrap: wrap; margin: 8px 0 16px; }</style>
</head>
<body>
<div class="itm-survey-wrap">
    <div class="card">
        <h1 title="Ticket survey">📋</h1>
        <?php if (!$payload): ?>
            <div class="alert alert-danger"><?php echo sanitize($error !== '' ? $error : 'Invalid or expired survey link.'); ?></div>
        <?php elseif ($success): ?>
            <div class="alert alert-success">Thank you — your feedback has been recorded.</div>
        <?php else: ?>
            <?php if ($ticketTitle !== ''): ?><p><strong>Ticket:</strong> <?php echo sanitize($ticketTitle); ?></p><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo sanitize($error); ?></div><?php endif; ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo sanitize($token); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize(itm_get_csrf_token()); ?>">
                <?php foreach ($questions as $q): ?>
                    <?php
                    $qid = (int)$q['id'];
                    $qText = (string)($q['question_text'] ?? '');
                    $qType = (string)($q['question_type'] ?? 'rating_1_5');
                    $required = (int)($q['is_required'] ?? 0) === 1;
                    ?>
                    <div class="form-group">
                        <label><?php echo sanitize($qText); ?><?php if ($required): ?> <span aria-hidden="true">*</span><?php endif; ?></label>
                        <?php if ($qType === 'text'): ?>
                            <textarea name="answer_text_<?php echo $qid; ?>" rows="3" maxlength="2000"<?php echo $required ? ' required' : ''; ?>></textarea>
                        <?php else: ?>
                            <div class="itm-survey-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label><input type="radio" name="answer_rating_<?php echo $qid; ?>" value="<?php echo $i; ?>"<?php echo $required ? ' required' : ''; ?>> <?php echo $i; ?></label>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <div class="form-group">
                    <label class="itm-checkbox-control">
                        <input type="checkbox" name="accept_feedback" value="1">
                        <span>I accept that my feedback may be used to improve our service</span>
                    </label>
                </div>
                <button type="submit" name="submit_survey" value="1" class="btn btn-primary" title="Submit survey">💾</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
