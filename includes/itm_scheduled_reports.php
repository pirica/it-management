<?php
/**
 * Scheduled executive reports — cron matcher, dataset builders, email delivery.
 */

if (!function_exists('itm_scheduled_reports_catalog')) {
    function itm_scheduled_reports_catalog()
    {
        return [
            'equipment_summary' => 'Equipment by type',
            'ticket_summary' => 'Tickets by status',
            'hr_summary' => 'Employees by department',
            'budget_summary' => 'Budget vs actual',
            'asset_value' => 'Asset financial value',
        ];
    }
}

if (!function_exists('itm_scheduled_reports_catalog_with_saved_views')) {
    function itm_scheduled_reports_catalog_with_saved_views($conn, $companyId, $employeeId)
    {
        $catalog = itm_scheduled_reports_catalog();
        if (!function_exists('itm_saved_reports_list_visible')) {
            require_once ROOT_PATH . 'includes/itm_saved_reports.php';
        }
        foreach (itm_saved_reports_list_visible($conn, (int) $companyId, (int) $employeeId) as $view) {
            $slug = itm_saved_reports_scheduled_slug((int) $view['id']);
            $moduleLabel = itm_saved_reports_module_config((string) $view['module_slug'])['label'] ?? $view['module_slug'];
            $catalog[$slug] = 'Saved: ' . (string) $view['name'] . ' (' . $moduleLabel . ')';
        }
        return $catalog;
    }
}

if (!function_exists('itm_scheduled_reports_cron_field_matches')) {
    function itm_scheduled_reports_cron_field_matches($fieldValue, $cronPart)
    {
        $fieldValue = (int) $fieldValue;
        $cronPart = trim((string) $cronPart);
        if ($cronPart === '*') {
            return true;
        }
        if (preg_match('/^\*\/(\d+)$/', $cronPart, $m)) {
            $step = max(1, (int) $m[1]);
            return $fieldValue % $step === 0;
        }
        foreach (explode(',', $cronPart) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            if (strpos($segment, '-') !== false) {
                [$start, $end] = array_map('intval', explode('-', $segment, 2));
                if ($fieldValue >= $start && $fieldValue <= $end) {
                    return true;
                }
                continue;
            }
            if ((int) $segment === $fieldValue) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('itm_scheduled_reports_cron_is_due')) {
    /**
     * Five-field cron: minute hour day-of-month month day-of-week (0-6, Sunday=0).
     */
    function itm_scheduled_reports_cron_is_due($cronExpression, $at = null)
    {
        $cronExpression = trim((string) $cronExpression);
        $parts = preg_split('/\s+/', $cronExpression);
        if (count($parts) !== 5) {
            return false;
        }
        $ts = $at instanceof DateTimeInterface ? $at->getTimestamp() : time();
        $minute = (int) date('i', $ts);
        $hour = (int) date('G', $ts);
        $dom = (int) date('j', $ts);
        $month = (int) date('n', $ts);
        $dow = (int) date('w', $ts);

        return itm_scheduled_reports_cron_field_matches($minute, $parts[0])
            && itm_scheduled_reports_cron_field_matches($hour, $parts[1])
            && itm_scheduled_reports_cron_field_matches($dom, $parts[2])
            && itm_scheduled_reports_cron_field_matches($month, $parts[3])
            && itm_scheduled_reports_cron_field_matches($dow, $parts[4]);
    }
}

if (!function_exists('itm_scheduled_reports_load_dataset')) {
    function itm_scheduled_reports_load_dataset($conn, $companyId, $reportSlug)
    {
        $companyId = (int) $companyId;
        $reportSlug = (string) $reportSlug;

        if (!function_exists('itm_saved_reports_parse_scheduled_slug')) {
            require_once ROOT_PATH . 'includes/itm_saved_reports.php';
        }
        $savedViewId = itm_saved_reports_parse_scheduled_slug($reportSlug);
        if ($savedViewId > 0) {
            if (!function_exists('itm_saved_reports_fetch_by_id')) {
                require_once ROOT_PATH . 'includes/itm_saved_reports.php';
            }
            $viewRow = itm_saved_reports_fetch_by_id($conn, $savedViewId, $companyId);
            if ($viewRow) {
                $query = itm_saved_reports_run_query($conn, $companyId, $viewRow, ['limit' => 500, 'offset' => 0]);
                $emailData = itm_saved_reports_render_email_dataset($query, (string) ($viewRow['name'] ?? 'Saved view'));
                $emailData['saved_view_id'] = $savedViewId;
                return $emailData;
            }
            return ['title' => 'Saved view not found', 'labels' => [], 'data' => []];
        }

        $catalog = itm_scheduled_reports_catalog();
        if (!isset($catalog[$reportSlug])) {
            return ['title' => 'Unknown report', 'labels' => [], 'data' => []];
        }

        $prevCompany = $GLOBALS['company_id'] ?? null;
        $GLOBALS['company_id'] = $companyId;
        if (!function_exists('get_equipment_statistics')) {
            require_once ROOT_PATH . 'modules/reports/api/helpers.php';
        }

        $dataset = ['title' => $catalog[$reportSlug], 'labels' => [], 'data' => []];
        switch ($reportSlug) {
            case 'equipment_summary':
                $raw = get_equipment_statistics();
                break;
            case 'ticket_summary':
                $raw = get_ticket_statistics();
                break;
            case 'hr_summary':
                $raw = get_hr_statistics();
                break;
            case 'budget_summary':
                $raw = get_budget_vs_actual_trend();
                $dataset['labels'] = $raw['labels'] ?? [];
                $dataset['data'] = $raw['actual'] ?? [];
                $dataset['extra'] = ['budget' => $raw['budget'] ?? []];
                $GLOBALS['company_id'] = $prevCompany;
                return $dataset;
            case 'asset_value':
                $raw = get_asset_financial_value();
                $dataset['labels'] = $raw['labels'] ?? [];
                $dataset['data'] = $raw['data'] ?? [];
                $GLOBALS['company_id'] = $prevCompany;
                return $dataset;
            default:
                $raw = ['labels' => [], 'data' => []];
        }
        $GLOBALS['company_id'] = $prevCompany;
        $dataset['labels'] = $raw['labels'] ?? [];
        $dataset['data'] = $raw['data'] ?? [];
        return $dataset;
    }
}

if (!function_exists('itm_scheduled_reports_render_html')) {
    function itm_scheduled_reports_render_html(array $dataset, $companyName = '')
    {
        $title = htmlspecialchars((string) ($dataset['title'] ?? 'Report'), ENT_QUOTES, 'UTF-8');
        $companyName = htmlspecialchars((string) $companyName, ENT_QUOTES, 'UTF-8');
        $rows = '';
        $labels = $dataset['labels'] ?? [];
        $values = $dataset['data'] ?? [];
        if (!empty($dataset['html_table'])) {
            $rows = (string) $dataset['html_table'];
            if (!empty($dataset['total'])) {
                $rows = '<p><strong>Total rows:</strong> ' . (int) $dataset['total'] . ' (showing up to 500 in email)</p>' . $rows;
            }
            return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $title . '</title></head><body>'
                . '<h1>' . $title . '</h1>'
                . ($companyName !== '' ? '<p><strong>Company:</strong> ' . $companyName . '</p>' : '')
                . '<p>Generated: ' . htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') . '</p>'
                . $rows . '</body></html>';
        }
        foreach ($labels as $idx => $label) {
            $val = $values[$idx] ?? 0;
            $rows .= '<tr><td>' . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="2">No data for this period.</td></tr>';
        }
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $title . '</title></head><body>'
            . '<h1>' . $title . '</h1>'
            . ($companyName !== '' ? '<p><strong>Company:</strong> ' . $companyName . '</p>' : '')
            . '<p>Generated: ' . htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') . '</p>'
            . '<table border="1" cellpadding="6" cellspacing="0"><thead><tr><th>Label</th><th>Value</th></tr></thead><tbody>'
            . $rows . '</tbody></table></body></html>';
    }
}

if (!function_exists('itm_scheduled_reports_build_csv')) {
    function itm_scheduled_reports_build_csv(array $dataset)
    {
        $lines = ['Label,Value'];
        $labels = $dataset['labels'] ?? [];
        $values = $dataset['data'] ?? [];
        foreach ($labels as $idx => $label) {
            $val = $values[$idx] ?? 0;
            $lines[] = '"' . str_replace('"', '""', (string) $label) . '","' . str_replace('"', '""', (string) $val) . '"';
        }
        return implode("\r\n", $lines) . "\r\n";
    }
}

if (!function_exists('itm_scheduled_reports_send_row')) {
    function itm_scheduled_reports_send_row($conn, array $row, $force = false)
    {
        $id = (int) ($row['id'] ?? 0);
        $companyId = (int) ($row['company_id'] ?? 0);
        $slug = (string) ($row['report_slug'] ?? '');
        $format = strtolower((string) ($row['format'] ?? 'pdf'));
        $cron = (string) ($row['schedule_cron'] ?? '');
        $recipients = json_decode((string) ($row['recipients_json'] ?? '[]'), true);
        if (!is_array($recipients)) {
            $recipients = [];
        }
        $recipients = array_values(array_filter(array_map('trim', $recipients)));
        if ($id <= 0 || $companyId <= 0 || $slug === '' || $recipients === []) {
            return ['ok' => false, 'error' => 'Invalid scheduled report row.'];
        }
        if (!$force && !itm_scheduled_reports_cron_is_due($cron)) {
            return ['ok' => false, 'error' => 'Not due.'];
        }

        $dataset = itm_scheduled_reports_load_dataset($conn, $companyId, $slug);
        $companyName = '';
        $cStmt = mysqli_prepare($conn, 'SELECT company FROM companies WHERE id = ? LIMIT 1');
        if ($cStmt) {
            mysqli_stmt_bind_param($cStmt, 'i', $companyId);
            mysqli_stmt_execute($cStmt);
            $cRes = mysqli_stmt_get_result($cStmt);
            if ($cRes && ($cRow = mysqli_fetch_assoc($cRes))) {
                $companyName = (string) ($cRow['company'] ?? '');
            }
            mysqli_stmt_close($cStmt);
        }

        $subject = 'Executive report: ' . ($dataset['title'] ?? $slug);
        $htmlBody = itm_scheduled_reports_render_html($dataset, $companyName);
        $options = ['log_created_by' => (int) ($row['created_by'] ?? 0)];
        if ($format === 'xlsx') {
            if (!empty($dataset['tabular_csv'])) {
                $csv = (string) $dataset['tabular_csv'];
            } elseif (!empty($dataset['html_table'])) {
                $csv = itm_scheduled_reports_build_csv($dataset);
            } else {
                $csv = itm_scheduled_reports_build_csv($dataset);
            }
            $options['attachments'] = [[
                'filename' => $slug . '-report.xlsx',
                'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'body' => $csv,
            ]];
            $htmlBody .= '<p>Spreadsheet export attached.</p>';
        } else {
            $options['attachments'] = [[
                'filename' => $slug . '-report.html',
                'content_type' => 'text/html',
                'body' => $htmlBody,
            ]];
        }

        $to = implode(',', $recipients);
        $sent = itm_send_email($to, $subject, $htmlBody, $companyId, $options);
        if (!$sent) {
            return ['ok' => false, 'error' => 'Email send failed.'];
        }

        $uStmt = mysqli_prepare($conn, 'UPDATE scheduled_reports SET last_sent_at = NOW() WHERE id = ? AND company_id = ?');
        if ($uStmt) {
            mysqli_stmt_bind_param($uStmt, 'ii', $id, $companyId);
            mysqli_stmt_execute($uStmt);
            mysqli_stmt_close($uStmt);
        }
        return ['ok' => true, 'error' => ''];
    }
}

if (!function_exists('itm_scheduled_reports_fetch_due_rows')) {
    function itm_scheduled_reports_fetch_due_rows($conn, $companyId = null)
    {
        $sql = "SELECT * FROM scheduled_reports WHERE deleted_at IS NULL AND enabled = 1 AND active = 1";
        $types = '';
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND company_id = ?';
            $types .= 'i';
            $params[] = (int) $companyId;
        }
        $sql .= ' ORDER BY company_id, id';
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            if (itm_scheduled_reports_cron_is_due((string) ($row['schedule_cron'] ?? ''))) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_scheduled_reports_process_due')) {
    function itm_scheduled_reports_process_due($conn, $companyId = null)
    {
        $rows = itm_scheduled_reports_fetch_due_rows($conn, $companyId);
        $summary = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'errors' => []];
        foreach ($rows as $row) {
            $summary['processed']++;
            $result = itm_scheduled_reports_send_row($conn, $row, true);
            if (!empty($result['ok'])) {
                $summary['sent']++;
            } else {
                $summary['failed']++;
                $summary['errors'][] = 'Report #' . (int) $row['id'] . ': ' . (string) ($result['error'] ?? 'unknown');
            }
        }
        return $summary;
    }
}
