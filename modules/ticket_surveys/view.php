<?php
/**
 * Ticket Surveys — read-only detail with answer rows.
 */

$crud_table = 'ticket_surveys';
$crud_title = 'Ticket Surveys';

require_once dirname(__DIR__, 2) . '/config/config.php';

itm_require_crud_role_module_permission($conn, 'view', $crud_table);

$surveyId = (int)($_GET['id'] ?? 0);
$errors = [];
$survey = null;
$answers = [];

if ($surveyId <= 0) {
    $errors[] = 'Survey not found.';
} else {
    $surveySql = 'SELECT ts.*, t.ticket_external_code, t.title AS ticket_title, tq.name AS questionnaire_name
        FROM ticket_surveys ts
        INNER JOIN tickets t ON t.id = ts.ticket_id AND t.company_id = ts.company_id
        INNER JOIN ticket_questionnaires tq ON tq.id = ts.questionnaire_id AND tq.company_id = ts.company_id
        WHERE ts.id = ? AND ts.company_id = ?
        LIMIT 1';
    $surveyStmt = mysqli_prepare($conn, $surveySql);
    if ($surveyStmt) {
        mysqli_stmt_bind_param($surveyStmt, 'ii', $surveyId, $company_id);
        mysqli_stmt_execute($surveyStmt);
        $surveyRes = mysqli_stmt_get_result($surveyStmt);
        $survey = ($surveyRes && mysqli_num_rows($surveyRes) === 1) ? mysqli_fetch_assoc($surveyRes) : null;
        mysqli_stmt_close($surveyStmt);
    }
    if (!$survey) {
        http_response_code(404);
        $errors[] = 'Survey not found.';
    } else {
        $ansSql = 'SELECT question_text_snapshot, sort_order, answer_rating, answer_text
            FROM ticket_survey_answers WHERE survey_id = ? ORDER BY sort_order ASC, id ASC';
        $ansStmt = mysqli_prepare($conn, $ansSql);
        if ($ansStmt) {
            mysqli_stmt_bind_param($ansStmt, 'i', $surveyId);
            mysqli_stmt_execute($ansStmt);
            $ansRes = mysqli_stmt_get_result($ansStmt);
            while ($ansRes && ($ansRow = mysqli_fetch_assoc($ansRes))) {
                $answers[] = $ansRow;
            }
            mysqli_stmt_close($ansStmt);
        }
    }
}

$moduleSlug = basename(dirname($_SERVER['PHP_SELF']));
$tsEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
$tsIcon = itm_resolve_module_sidebar_icon($conn, (int)$company_id, $tsEmployeeId, $moduleSlug);
$moduleListHeading = trim($tsIcon . ' ' . itm_module_access_strip_catalog_label_prefix('View Survey'));
$csrfToken = itm_get_csrf_token();
$ticketRef = trim((string)($survey['reference'] ?? ''));
if ($ticketRef === '' && $survey) {
    $ticketRef = trim((string)($survey['ticket_external_code'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    if (!isset($currentUiConfig)) {
        $currentUiConfig = $ui_config ?? [];
    }
    require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
    $crud_title = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), $tsEmployeeId, $moduleSlug, 'View Survey');
    ?>
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php echo itm_render_alert_errors($errors); ?>

            <?php if ($survey): ?>
                <h1 title="View survey">🔎</h1>
                <div class="card">
                    <table>
                        <tbody>
                        <tr><th style="width:240px;">Ticket</th><td><?php echo sanitize($ticketRef !== '' ? $ticketRef : '—'); ?></td></tr>
                        <tr><th>Questionnaire</th><td><?php echo sanitize($survey['questionnaire_name'] ?? '—'); ?></td></tr>
                        <tr><th>Respondent email</th><td><?php echo sanitize($survey['respondent_email'] ?? '—'); ?></td></tr>
                        <tr><th>Average score</th><td><?php echo $survey['average_score'] !== null ? sanitize((string)$survey['average_score']) : '—'; ?></td></tr>
                        <tr><th>Completed</th><td><?php echo !empty($survey['completed_at']) ? sanitize(itm_format_cell_scalar_display('completed_at', $survey['completed_at'])) : 'Pending'; ?></td></tr>
                        <tr><th>Issued</th><td><?php echo sanitize(itm_format_cell_scalar_display('created_at', $survey['created_at'] ?? '')); ?></td></tr>
                        </tbody>
                    </table>
                </div>

                <h2 style="margin-top:20px;" title="Answers">Answers</h2>
                <div class="card">
                    <table>
                        <thead>
                        <tr>
                            <th>Order</th>
                            <th>Question</th>
                            <th>Answer</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($answers)): ?>
                            <?php foreach ($answers as $ans): ?>
                                <tr>
                                    <td><?php echo (int)($ans['sort_order'] ?? 0); ?></td>
                                    <td><?php echo sanitize($ans['question_text_snapshot'] ?? ''); ?></td>
                                    <td>
                                        <?php
                                        if ($ans['answer_rating'] !== null && $ans['answer_rating'] !== '') {
                                            echo sanitize((string)$ans['answer_rating']);
                                        } elseif (trim((string)($ans['answer_text'] ?? '')) !== '') {
                                            echo nl2br(sanitize($ans['answer_text']));
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3">No answers recorded.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <p style="margin-top:16px;">
                    <a href="index.php" class="btn" title="Back">🔙</a>
                    <?php if (empty($survey['completed_at'])): ?>
                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this pending survey invite?');">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="id" value="<?php echo (int)$surveyId; ?>">
                            <button class="btn btn-sm btn-danger" type="submit" title="Delete">🗑️</button>
                        </form>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
</body>
</html>
