<?php
/**
 * Ticket questionnaires and surveys — issue, public submit, stats, CSAT sync.
 */

if (!function_exists('itm_ticket_survey_generate_token')) {
    function itm_ticket_survey_generate_token()
    {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('itm_ticket_survey_build_public_url')) {
    function itm_ticket_survey_build_public_url($token)
    {
        $token = trim((string)$token);
        if ($token === '') {
            return '';
        }
        return rtrim((string)(defined('BASE_URL') ? BASE_URL : '/'), '/') . '/ticket-survey.php?token=' . rawurlencode($token);
    }
}

if (!function_exists('itm_ticket_survey_verify_token')) {
    function itm_ticket_survey_verify_token($conn, $token)
    {
        if (!$conn instanceof mysqli) {
            return null;
        }
        $token = trim((string)$token);
        if ($token === '') {
            return null;
        }
        $stmt = mysqli_prepare($conn, 'SELECT id, company_id, ticket_id, questionnaire_id, completed_at FROM ticket_surveys WHERE token = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!is_array($row)) {
            return null;
        }
        return [
            'survey_id' => (int)$row['id'],
            'company_id' => (int)$row['company_id'],
            'ticket_id' => (int)$row['ticket_id'],
            'questionnaire_id' => (int)$row['questionnaire_id'],
            'completed_at' => $row['completed_at'] ?? null,
        ];
    }
}

if (!function_exists('itm_ticket_survey_resolve_questionnaire')) {
    function itm_ticket_survey_resolve_questionnaire($conn, $companyId, $ticketId)
    {
        if (!$conn instanceof mysqli) {
            return 0;
        }
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        if ($companyId <= 0 || $ticketId <= 0) {
            return 0;
        }
        $categoryId = 0;
        $stmt = mysqli_prepare($conn, 'SELECT category_id FROM tickets WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            $categoryId = (int)($row['category_id'] ?? 0);
        }
        if ($categoryId > 0) {
            $catStmt = mysqli_prepare($conn, 'SELECT id FROM ticket_questionnaires WHERE company_id = ? AND category_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY id ASC LIMIT 1');
            if ($catStmt) {
                mysqli_stmt_bind_param($catStmt, 'ii', $companyId, $categoryId);
                mysqli_stmt_execute($catStmt);
                $catRes = mysqli_stmt_get_result($catStmt);
                $catRow = $catRes ? mysqli_fetch_assoc($catRes) : null;
                mysqli_stmt_close($catStmt);
                if (is_array($catRow) && (int)$catRow['id'] > 0) {
                    return (int)$catRow['id'];
                }
            }
        }
        $defStmt = mysqli_prepare($conn, 'SELECT id FROM ticket_questionnaires WHERE company_id = ? AND is_default = 1 AND deleted_at IS NULL AND active = 1 ORDER BY id ASC LIMIT 1');
        if (!$defStmt) {
            return 0;
        }
        mysqli_stmt_bind_param($defStmt, 'i', $companyId);
        mysqli_stmt_execute($defStmt);
        $defRes = mysqli_stmt_get_result($defStmt);
        $defRow = $defRes ? mysqli_fetch_assoc($defRes) : null;
        mysqli_stmt_close($defStmt);
        return is_array($defRow) ? (int)($defRow['id'] ?? 0) : 0;
    }
}

if (!function_exists('itm_ticket_survey_ticket_requester_email')) {
    function itm_ticket_survey_ticket_requester_email($conn, $companyId, $ticketId)
    {
        if (!$conn instanceof mysqli) {
            return '';
        }
        $stmt = mysqli_prepare($conn, 'SELECT e.work_email, e.personal_email FROM tickets t INNER JOIN employees e ON e.id = t.created_by_employee_id WHERE t.id = ? AND t.company_id = ? LIMIT 1');
        if (!$stmt) {
            return '';
        }
        mysqli_stmt_bind_param($stmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!is_array($row)) {
            return '';
        }
        $work = trim((string)($row['work_email'] ?? ''));
        if ($work !== '') {
            return $work;
        }
        return trim((string)($row['personal_email'] ?? ''));
    }
}

if (!function_exists('itm_ticket_survey_get_latest_for_ticket')) {
    function itm_ticket_survey_get_latest_for_ticket($conn, $companyId, $ticketId)
    {
        if (!$conn instanceof mysqli) {
            return null;
        }
        $stmt = mysqli_prepare($conn, 'SELECT ts.*, tq.name AS questionnaire_name FROM ticket_surveys ts LEFT JOIN ticket_questionnaires tq ON tq.id = ts.questionnaire_id WHERE ts.company_id = ? AND ts.ticket_id = ? ORDER BY ts.id DESC LIMIT 1');
        if (!$stmt) {
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $ticketId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('itm_ticket_survey_cancel_pending_for_ticket')) {
    function itm_ticket_survey_cancel_pending_for_ticket($conn, $companyId, $ticketId)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $stmt = mysqli_prepare($conn, 'DELETE FROM ticket_surveys WHERE company_id = ? AND ticket_id = ? AND completed_at IS NULL');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $companyId, $ticketId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}

if (!function_exists('itm_ticket_survey_issue')) {
    function itm_ticket_survey_issue($conn, $companyId, $ticketId, $questionnaireId = 0, $respondentEmail = '', $sendEmail = true)
    {
        if (!$conn instanceof mysqli) {
            return 0;
        }
        $companyId = (int)$companyId;
        $ticketId = (int)$ticketId;
        if ($companyId <= 0 || $ticketId <= 0) {
            return 0;
        }
        $existing = itm_ticket_survey_get_latest_for_ticket($conn, $companyId, $ticketId);
        if (is_array($existing) && !empty($existing['completed_at'])) {
            return (int)$existing['id'];
        }
        if (is_array($existing) && empty($existing['completed_at'])) {
            return (int)$existing['id'];
        }
        if ($questionnaireId <= 0) {
            $questionnaireId = itm_ticket_survey_resolve_questionnaire($conn, $companyId, $ticketId);
        }
        if ($questionnaireId <= 0) {
            return 0;
        }
        $ticketStmt = mysqli_prepare($conn, 'SELECT title, ticket_external_code FROM tickets WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
        if (!$ticketStmt) {
            return 0;
        }
        mysqli_stmt_bind_param($ticketStmt, 'ii', $ticketId, $companyId);
        mysqli_stmt_execute($ticketStmt);
        $ticketRes = mysqli_stmt_get_result($ticketStmt);
        $ticketRow = $ticketRes ? mysqli_fetch_assoc($ticketRes) : null;
        mysqli_stmt_close($ticketStmt);
        if (!is_array($ticketRow)) {
            return 0;
        }
        $respondentEmail = trim((string)$respondentEmail);
        if ($respondentEmail === '') {
            $respondentEmail = itm_ticket_survey_ticket_requester_email($conn, $companyId, $ticketId);
        }
        $reference = trim((string)($ticketRow['ticket_external_code'] ?? ''));
        if ($reference !== '') {
            $reference .= ' — ';
        }
        $reference .= (string)($ticketRow['title'] ?? '');
        $token = itm_ticket_survey_generate_token();
        $issuedBy = (int)($_SESSION['employee_id'] ?? 0);
        if ($issuedBy > 0) {
            $ins = mysqli_prepare($conn, 'INSERT INTO ticket_surveys (company_id, ticket_id, questionnaire_id, token, respondent_email, reference, issued_by_employee_id, active, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)');
            if (!$ins) {
                return 0;
            }
            mysqli_stmt_bind_param($ins, 'iiisssii', $companyId, $ticketId, $questionnaireId, $token, $respondentEmail, $reference, $issuedBy, $issuedBy);
        } else {
            // Why: CLI/cron issue paths have no session actor — NULL avoids FK violation on employee id 0.
            $ins = mysqli_prepare($conn, 'INSERT INTO ticket_surveys (company_id, ticket_id, questionnaire_id, token, respondent_email, reference, active) VALUES (?, ?, ?, ?, ?, ?, 1)');
            if (!$ins) {
                return 0;
            }
            mysqli_stmt_bind_param($ins, 'iiisss', $companyId, $ticketId, $questionnaireId, $token, $respondentEmail, $reference);
        }
        $ok = mysqli_stmt_execute($ins);
        $surveyId = $ok ? (int)mysqli_insert_id($conn) : 0;
        mysqli_stmt_close($ins);
        if ($surveyId <= 0) {
            return 0;
        }
        if (function_exists('itm_ticket_activity_log')) {
            itm_ticket_activity_log($conn, $companyId, $ticketId, $issuedBy > 0 ? $issuedBy : null, 'survey_issued', [
                'survey_id' => $surveyId,
                'questionnaire_id' => $questionnaireId,
                'url' => itm_ticket_survey_build_public_url($token),
            ]);
        }
        if ($sendEmail) {
            itm_ticket_survey_send_request_email($conn, $surveyId);
        }
        return $surveyId;
    }
}

if (!function_exists('itm_ticket_survey_send_request_email')) {
    function itm_ticket_survey_send_request_email($conn, $surveyId)
    {
        if (!$conn instanceof mysqli || !function_exists('itm_send_email')) {
            return false;
        }
        $surveyId = (int)$surveyId;
        if ($surveyId <= 0) {
            return false;
        }
        $stmt = mysqli_prepare($conn, 'SELECT ts.company_id, ts.token, ts.respondent_email, ts.reference, t.ticket_external_code FROM ticket_surveys ts INNER JOIN tickets t ON t.id = ts.ticket_id WHERE ts.id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $surveyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!is_array($row)) {
            return false;
        }
        $email = trim((string)($row['respondent_email'] ?? ''));
        if ($email === '') {
            return false;
        }
        $url = itm_ticket_survey_build_public_url((string)($row['token'] ?? ''));
        $code = trim((string)($row['ticket_external_code'] ?? ''));
        $subject = 'Feedback request' . ($code !== '' ? ' — ' . $code : '');
        $body = '<p>Please share your feedback on ticket <strong>' . htmlspecialchars((string)($row['reference'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Open satisfaction survey</a></p>';
        return (bool)itm_send_email($email, $subject, $body, (int)$row['company_id']);
    }
}

if (!function_exists('itm_ticket_survey_load_questions')) {
    function itm_ticket_survey_load_questions($conn, $questionnaireId, $companyId)
    {
        $rows = [];
        if (!$conn instanceof mysqli) {
            return $rows;
        }
        $stmt = mysqli_prepare($conn, 'SELECT id, sort_order, question_text, question_type, is_required FROM ticket_questionnaire_questions WHERE questionnaire_id = ? AND company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY sort_order ASC');
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $questionnaireId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_ticket_survey_sync_csat_columns')) {
    function itm_ticket_survey_sync_csat_columns($conn, $surveyId)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $surveyId = (int)$surveyId;
        $stmt = mysqli_prepare($conn, 'SELECT ts.company_id, ts.ticket_id, ts.average_score, ts.questionnaire_id FROM ticket_surveys ts WHERE ts.id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $surveyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $survey = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!is_array($survey)) {
            return false;
        }
        $companyId = (int)$survey['company_id'];
        $ticketId = (int)$survey['ticket_id'];
        $avg = $survey['average_score'];
        $comment = '';
        $textStmt = mysqli_prepare($conn, 'SELECT answer_text FROM ticket_survey_answers WHERE survey_id = ? AND answer_text IS NOT NULL AND answer_text <> \'\' ORDER BY sort_order ASC LIMIT 1');
        if ($textStmt) {
            mysqli_stmt_bind_param($textStmt, 'i', $surveyId);
            mysqli_stmt_execute($textStmt);
            $textRes = mysqli_stmt_get_result($textStmt);
            $textRow = $textRes ? mysqli_fetch_assoc($textRes) : null;
            mysqli_stmt_close($textStmt);
            if (is_array($textRow)) {
                $comment = trim((string)($textRow['answer_text'] ?? ''));
            }
        }
        $overallScore = null;
        $overallStmt = mysqli_prepare($conn, 'SELECT tsa.answer_rating FROM ticket_survey_answers tsa INNER JOIN ticket_questionnaire_questions tqq ON tqq.id = tsa.question_id WHERE tsa.survey_id = ? AND tqq.question_text LIKE ? ORDER BY tsa.sort_order DESC LIMIT 1');
        if ($overallStmt) {
            $like = '%Overall satisfaction%';
            mysqli_stmt_bind_param($overallStmt, 'is', $surveyId, $like);
            mysqli_stmt_execute($overallStmt);
            $overallRes = mysqli_stmt_get_result($overallStmt);
            $overallRow = $overallRes ? mysqli_fetch_assoc($overallRes) : null;
            mysqli_stmt_close($overallStmt);
            if (is_array($overallRow) && $overallRow['answer_rating'] !== null) {
                $overallScore = (int)$overallRow['answer_rating'];
            }
        }
        if ($overallScore === null && $avg !== null) {
            $overallScore = (int)round((float)$avg);
        }
        if ($overallScore === null || $overallScore < 1 || $overallScore > 5) {
            return false;
        }
        if (strlen($comment) > 2000) {
            $comment = substr($comment, 0, 2000);
        }
        $upd = mysqli_prepare($conn, 'UPDATE tickets SET csat_score = ?, csat_comment = ?, csat_submitted_at = NOW() WHERE id = ? AND company_id = ? AND deleted_at IS NULL LIMIT 1');
        if (!$upd) {
            return false;
        }
        mysqli_stmt_bind_param($upd, 'isii', $overallScore, $comment, $ticketId, $companyId);
        $ok = mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        return $ok;
    }
}

if (!function_exists('itm_ticket_survey_submit')) {
    function itm_ticket_survey_submit($conn, $surveyId, array $answers, $acceptFeedback = null)
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        $surveyId = (int)$surveyId;
        if ($surveyId <= 0) {
            return false;
        }
        $stmt = mysqli_prepare($conn, 'SELECT id, company_id, ticket_id, questionnaire_id, completed_at FROM ticket_surveys WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $surveyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $survey = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!is_array($survey) || !empty($survey['completed_at'])) {
            return false;
        }
        $companyId = (int)$survey['company_id'];
        $ticketId = (int)$survey['ticket_id'];
        $questionnaireId = (int)$survey['questionnaire_id'];
        $questions = itm_ticket_survey_load_questions($conn, $questionnaireId, $companyId);
        if ($questions === []) {
            return false;
        }
        $ratingSum = 0;
        $ratingCount = 0;
        mysqli_begin_transaction($conn);
        try {
            foreach ($questions as $q) {
                $qid = (int)$q['id'];
                $payload = $answers[$qid] ?? $answers[(string)$qid] ?? null;
                $rating = null;
                $text = '';
                if (is_array($payload)) {
                    $rating = isset($payload['rating']) ? (int)$payload['rating'] : null;
                    $text = trim((string)($payload['text'] ?? ''));
                } elseif (is_numeric($payload)) {
                    $rating = (int)$payload;
                } else {
                    $text = trim((string)$payload);
                }
                if ((int)$q['is_required'] === 1) {
                    if (($q['question_type'] ?? '') === 'rating_1_5' && ($rating === null || $rating < 1 || $rating > 5)) {
                        throw new RuntimeException('required');
                    }
                    if (($q['question_type'] ?? '') === 'text' && $text === '') {
                        throw new RuntimeException('required');
                    }
                }
                if ($rating !== null && $rating >= 1 && $rating <= 5) {
                    $ratingSum += $rating;
                    $ratingCount++;
                }
                $ins = mysqli_prepare($conn, 'INSERT INTO ticket_survey_answers (survey_id, question_id, question_text_snapshot, sort_order, answer_rating, answer_text) VALUES (?, ?, ?, ?, ?, ?)');
                if (!$ins) {
                    throw new RuntimeException('insert');
                }
                $sortOrder = (int)$q['sort_order'];
                $qText = (string)$q['question_text'];
                $ratingVal = ($rating !== null && $rating >= 1 && $rating <= 5) ? $rating : 0;
                mysqli_stmt_bind_param($ins, 'iisiis', $surveyId, $qid, $qText, $sortOrder, $ratingVal, $text);
                if (!mysqli_stmt_execute($ins)) {
                    mysqli_stmt_close($ins);
                    throw new RuntimeException('insert');
                }
                mysqli_stmt_close($ins);
            }
            $average = $ratingCount > 0 ? round($ratingSum / $ratingCount, 1) : null;
            $acceptVal = $acceptFeedback === null ? 0 : ((int)(bool)$acceptFeedback);
            $upd = mysqli_prepare($conn, 'UPDATE ticket_surveys SET completed_at = NOW(), average_score = ?, accept_feedback = ? WHERE id = ? AND completed_at IS NULL LIMIT 1');
            if (!$upd) {
                throw new RuntimeException('update');
            }
            mysqli_stmt_bind_param($upd, 'dii', $average, $acceptVal, $surveyId);
            if (!mysqli_stmt_execute($upd) || mysqli_stmt_affected_rows($upd) < 1) {
                mysqli_stmt_close($upd);
                throw new RuntimeException('update');
            }
            mysqli_stmt_close($upd);
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            return false;
        }
        itm_ticket_survey_sync_csat_columns($conn, $surveyId);
        $logScore = $average !== null ? (int)round((float)$average) : 0;
        if (function_exists('itm_ticket_activity_log')) {
            itm_ticket_activity_log($conn, $companyId, $ticketId, null, 'survey_completed', [
                'survey_id' => $surveyId,
                'average_score' => $average,
            ]);
            if ($logScore >= 1 && $logScore <= 5) {
                itm_ticket_activity_log($conn, $companyId, $ticketId, null, 'csat_submitted', ['score' => $logScore, 'survey_id' => $surveyId]);
            }
        }
        $ticketRow = null;
        $tStmt = mysqli_prepare($conn, 'SELECT assigned_to_employee_id, ticket_external_code, title FROM tickets WHERE id = ? AND company_id = ? LIMIT 1');
        if ($tStmt) {
            mysqli_stmt_bind_param($tStmt, 'ii', $ticketId, $companyId);
            mysqli_stmt_execute($tStmt);
            $tRes = mysqli_stmt_get_result($tStmt);
            $ticketRow = $tRes ? mysqli_fetch_assoc($tRes) : null;
            mysqli_stmt_close($tStmt);
        }
        if (function_exists('itm_automation_rules_dispatch')) {
            $ctx = itm_automation_rules_build_ticket_context($conn, $companyId, $ticketId, ['automation_depth' => 0]);
            $ctx['survey_id'] = $surveyId;
            $ctx['average_score'] = $average;
            itm_automation_rules_dispatch($conn, $companyId, 'ticket.survey_completed', $ctx);
        }
        if (function_exists('itm_webhook_queue_enqueue')) {
            require_once ROOT_PATH . 'includes/itm_webhook_queue.php';
            itm_webhook_queue_enqueue($conn, $companyId, 'ticket.survey_completed', [
                'ticket_id' => $ticketId,
                'survey_id' => $surveyId,
                'average_score' => $average,
                'ticket_external_code' => (string)($ticketRow['ticket_external_code'] ?? ''),
                'title' => (string)($ticketRow['title'] ?? ''),
            ]);
        }
        if ($average !== null && (float)$average <= 2.0 && function_exists('itm_notify_employee') && is_array($ticketRow)) {
            $assigneeId = (int)($ticketRow['assigned_to_employee_id'] ?? 0);
            if ($assigneeId > 0) {
                itm_notify_employee($conn, $assigneeId, [
                    'company_id' => $companyId,
                    'module_slug' => 'tickets',
                    'record_id' => $ticketId,
                    'title' => 'Low survey score',
                    'body' => 'Ticket received survey average ' . $average,
                    'action_url' => 'modules/tickets/view.php?id=' . $ticketId,
                ]);
            }
        }
        return true;
    }
}

if (!function_exists('itm_ticket_survey_maybe_issue_on_close')) {
    function itm_ticket_survey_maybe_issue_on_close($conn, $companyId, $ticketId, $newStatusId)
    {
        if (!$conn instanceof mysqli || $newStatusId <= 0) {
            return 0;
        }
        $stmt = mysqli_prepare($conn, 'SELECT is_closed FROM ticket_statuses WHERE id = ? AND company_id = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param($stmt, 'ii', $newStatusId, $companyId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!is_array($row) || (int)($row['is_closed'] ?? 0) !== 1) {
            return 0;
        }
        if (
            function_exists('itm_ticket_settings_auto_issue_survey_on_close')
            && !itm_ticket_settings_auto_issue_survey_on_close($conn, (int)$companyId)
        ) {
            return 0;
        }
        $sendEmail = !function_exists('itm_ticket_settings_survey_send_email_on_issue')
            || itm_ticket_settings_survey_send_email_on_issue($conn, (int)$companyId);

        return itm_ticket_survey_issue($conn, (int)$companyId, (int)$ticketId, 0, '', $sendEmail);
    }
}

if (!function_exists('itm_ticket_survey_merge_canned_body')) {
    function itm_ticket_survey_merge_canned_body($conn, $body, $companyId, $ticketId)
    {
        $body = (string)$body;
        if (strpos($body, '{{survey_url}}') === false) {
            return $body;
        }
        $survey = itm_ticket_survey_get_latest_for_ticket($conn, (int)$companyId, (int)$ticketId);
        $url = '';
        if (is_array($survey) && !empty($survey['token']) && empty($survey['completed_at'])) {
            $url = itm_ticket_survey_build_public_url((string)$survey['token']);
        }
        return str_replace('{{survey_url}}', $url, $body);
    }
}

if (!function_exists('itm_ticket_survey_stats_aggregate')) {
    /**
     * @return array{issued:int,completed:int,response_rate:float|null,avg_score_30d:float|null,nps_promoters:int,nps_passives:int,nps_detractors:int,nps_score:float|null,avg_sla_met:float|null,avg_sla_breached:float|null}
     */
    function itm_ticket_survey_stats_aggregate($conn, $companyId, $questionnaireId = 0, $dateFrom = null, $dateTo = null)
    {
        $companyId = (int)$companyId;
        $questionnaireId = (int)$questionnaireId;
        $empty = [
            'issued' => 0,
            'completed' => 0,
            'response_rate' => null,
            'avg_score_30d' => null,
            'nps_promoters' => 0,
            'nps_passives' => 0,
            'nps_detractors' => 0,
            'nps_score' => null,
            'avg_sla_met' => null,
            'avg_sla_breached' => null,
        ];
        if ($companyId <= 0 || !$conn instanceof mysqli) {
            return $empty;
        }

        $where = ' WHERE ts.company_id = ?';
        $types = 'i';
        $params = [$companyId];
        if ($questionnaireId > 0) {
            $where .= ' AND ts.questionnaire_id = ?';
            $types .= 'i';
            $params[] = $questionnaireId;
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $where .= ' AND ts.created_at >= ?';
            $types .= 's';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== null && $dateTo !== '') {
            $where .= ' AND ts.created_at <= ?';
            $types .= 's';
            $params[] = $dateTo . ' 23:59:59';
        }

        $issuedStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM ticket_surveys ts' . $where);
        if (!$issuedStmt) {
            return $empty;
        }
        mysqli_stmt_bind_param($issuedStmt, $types, ...$params);
        mysqli_stmt_execute($issuedStmt);
        $issuedRes = mysqli_stmt_get_result($issuedStmt);
        $issued = ($issuedRes && ($row = mysqli_fetch_assoc($issuedRes))) ? (int)($row['c'] ?? 0) : 0;
        mysqli_stmt_close($issuedStmt);

        $completedStmt = mysqli_prepare($conn, 'SELECT COUNT(*) AS c FROM ticket_surveys ts' . $where . ' AND ts.completed_at IS NOT NULL');
        if (!$completedStmt) {
            return $empty;
        }
        mysqli_stmt_bind_param($completedStmt, $types, ...$params);
        mysqli_stmt_execute($completedStmt);
        $completedRes = mysqli_stmt_get_result($completedStmt);
        $completed = ($completedRes && ($row = mysqli_fetch_assoc($completedRes))) ? (int)($row['c'] ?? 0) : 0;
        mysqli_stmt_close($completedStmt);

        $responseRate = $issued > 0 ? round(($completed / $issued) * 100, 1) : null;

        $avgScore30d = null;
        $avgSql = 'SELECT AVG(ts.average_score) AS avg_score FROM ticket_surveys ts WHERE ts.company_id = ? AND ts.completed_at IS NOT NULL AND ts.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        $avgTypes = 'i';
        $avgParams = [$companyId];
        if ($questionnaireId > 0) {
            $avgSql .= ' AND ts.questionnaire_id = ?';
            $avgTypes .= 'i';
            $avgParams[] = $questionnaireId;
        }
        $avgStmt = mysqli_prepare($conn, $avgSql);
        if ($avgStmt) {
            mysqli_stmt_bind_param($avgStmt, $avgTypes, ...$avgParams);
            mysqli_stmt_execute($avgStmt);
            $avgRes = mysqli_stmt_get_result($avgStmt);
            if ($avgRes && ($avgRow = mysqli_fetch_assoc($avgRes)) && $avgRow['avg_score'] !== null) {
                $avgScore30d = round((float)$avgRow['avg_score'], 1);
            }
            mysqli_stmt_close($avgStmt);
        }

        $npsPromoters = 0;
        $npsPassives = 0;
        $npsDetractors = 0;
        $npsSql = 'SELECT SUM(CASE WHEN tsa.answer_rating >= 4 THEN 1 ELSE 0 END) AS promoters,
                SUM(CASE WHEN tsa.answer_rating = 3 THEN 1 ELSE 0 END) AS passives,
                SUM(CASE WHEN tsa.answer_rating <= 2 AND tsa.answer_rating IS NOT NULL THEN 1 ELSE 0 END) AS detractors
            FROM ticket_survey_answers tsa
            INNER JOIN ticket_surveys ts ON ts.id = tsa.survey_id
            INNER JOIN ticket_questionnaire_questions tqq ON tqq.id = tsa.question_id AND tqq.question_type = \'rating_1_5\'
            WHERE ts.company_id = ? AND ts.completed_at IS NOT NULL';
        $npsTypes = 'i';
        $npsParams = [$companyId];
        if ($questionnaireId > 0) {
            $npsSql .= ' AND ts.questionnaire_id = ?';
            $npsTypes .= 'i';
            $npsParams[] = $questionnaireId;
        }
        $npsStmt = mysqli_prepare($conn, $npsSql);
        if ($npsStmt) {
            mysqli_stmt_bind_param($npsStmt, $npsTypes, ...$npsParams);
            mysqli_stmt_execute($npsStmt);
            $npsRes = mysqli_stmt_get_result($npsStmt);
            if ($npsRes && ($npsRow = mysqli_fetch_assoc($npsRes))) {
                $npsPromoters = (int)($npsRow['promoters'] ?? 0);
                $npsPassives = (int)($npsRow['passives'] ?? 0);
                $npsDetractors = (int)($npsRow['detractors'] ?? 0);
            }
            mysqli_stmt_close($npsStmt);
        }
        $npsTotal = $npsPromoters + $npsPassives + $npsDetractors;
        $npsScore = $npsTotal > 0 ? round((($npsPromoters - $npsDetractors) / $npsTotal) * 100, 1) : null;

        $avgSlaMet = null;
        $avgSlaBreached = null;
        $slaSql = 'SELECT AVG(ts.average_score) AS avg_score FROM ticket_surveys ts INNER JOIN tickets t ON t.id = ts.ticket_id WHERE ts.company_id = ? AND ts.completed_at IS NOT NULL AND t.sla_resolve_breached_at IS NULL';
        $slaStmt = mysqli_prepare($conn, $slaSql);
        if ($slaStmt) {
            mysqli_stmt_bind_param($slaStmt, 'i', $companyId);
            mysqli_stmt_execute($slaStmt);
            $slaRes = mysqli_stmt_get_result($slaStmt);
            if ($slaRes && ($slaRow = mysqli_fetch_assoc($slaRes)) && $slaRow['avg_score'] !== null) {
                $avgSlaMet = round((float)$slaRow['avg_score'], 1);
            }
            mysqli_stmt_close($slaStmt);
        }
        $slaBSql = 'SELECT AVG(ts.average_score) AS avg_score FROM ticket_surveys ts INNER JOIN tickets t ON t.id = ts.ticket_id WHERE ts.company_id = ? AND ts.completed_at IS NOT NULL AND t.sla_resolve_breached_at IS NOT NULL';
        $slaBStmt = mysqli_prepare($conn, $slaBSql);
        if ($slaBStmt) {
            mysqli_stmt_bind_param($slaBStmt, 'i', $companyId);
            mysqli_stmt_execute($slaBStmt);
            $slaBRes = mysqli_stmt_get_result($slaBStmt);
            if ($slaBRes && ($slaBRow = mysqli_fetch_assoc($slaBRes)) && $slaBRow['avg_score'] !== null) {
                $avgSlaBreached = round((float)$slaBRow['avg_score'], 1);
            }
            mysqli_stmt_close($slaBStmt);
        }

        return [
            'issued' => $issued,
            'completed' => $completed,
            'response_rate' => $responseRate,
            'avg_score_30d' => $avgScore30d,
            'nps_promoters' => $npsPromoters,
            'nps_passives' => $npsPassives,
            'nps_detractors' => $npsDetractors,
            'nps_score' => $npsScore,
            'avg_sla_met' => $avgSlaMet,
            'avg_sla_breached' => $avgSlaBreached,
        ];
    }
}
