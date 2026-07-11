<?php
/**
 * Dispatches automated email alert rules per company.
 *
 * CLI: php scripts/run_email_alert_rules.php [--company=1] [--verbose]
 * Browser: scripts/run_email_alert_rules.php (admin login required)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/config/config.php';
    require_once __DIR__ . '/lib/script_browser_nav.php';
} else {
    define('ITM_CLI_SCRIPT', true);
    require_once dirname(__DIR__) . '/config/config.php';
}

require_once __DIR__ . '/lib/script_cli_output.php';
itm_script_output_begin();


if (PHP_SAPI !== 'cli') {
    if (!itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Run Email Alert Rules</title></head><body>';
    itm_script_browser_nav_echo();
}

$nl = itm_script_output_nl();

$companyFilter = 0;
$verbose = false;
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '--company=') === 0) {
            $companyFilter = (int)substr($arg, 10);
        } elseif ($arg === '--verbose' || $arg === '-v') {
            $verbose = true;
        }
    }
} else {
    $companyFilter = (int)($_REQUEST['company'] ?? 0);
    $verbose = !empty($_REQUEST['verbose']);
}

/**
 * @return array{sent:int,matches:int,note:string}
 */
function itm_email_alert_runner_dispatch_batch(mysqli $conn, int $companyId, string $ruleSlug, array $rule, array $rows, string $subjectPrefix, callable $subjectBuilder, callable $htmlBuilder): array
{
    $matches = count($rows);
    if ((int)($rule['enabled'] ?? 0) !== 1) {
        return ['sent' => 0, 'matches' => $matches, 'note' => 'disabled'];
    }

    $recipients = itm_email_parse_notify_list($rule['notify_emails'] ?? '');
    if ($matches === 0) {
        return ['sent' => 0, 'matches' => 0, 'note' => 'no matches'];
    }
    if ($recipients === []) {
        return ['sent' => 0, 'matches' => $matches, 'note' => 'no notify_emails'];
    }

    $sent = 0;
    foreach ($rows as $row) {
        $subject = $subjectBuilder($row, $subjectPrefix);
        $html = $htmlBuilder($row);
        $sent += itm_email_dispatch_to_rule($conn, $companyId, $ruleSlug, $subject, $html);
    }

    return ['sent' => $sent, 'matches' => $matches, 'note' => $sent > 0 ? 'sent' : 'send failed'];
}

$companies = [];
if ($companyFilter > 0) {
    $companies[] = $companyFilter;
} else {
    $res = mysqli_query($conn, 'SELECT id FROM companies WHERE active = 1 ORDER BY id ASC');
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $companies[] = (int)$row['id'];
    }
}

$totalSent = 0;
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');

foreach ($companies as $companyId) {
    itm_email_ensure_alert_rules($conn, $companyId);
    $rules = itm_email_get_alert_rules($conn, $companyId);
    echo 'Company ' . $companyId . $nl;

    $companyStats = [];

    if (!empty($rules['warranty_expiry'])) {
        $rule = $rules['warranty_expiry'];
        $days = max(0, (int)($rule['days_before'] ?? 30));
        $cutoff = date('Y-m-d', strtotime('+' . $days . ' days'));
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, hostname, warranty_expiry FROM equipment
             WHERE company_id = ? AND active = 1 AND warranty_expiry IS NOT NULL
               AND warranty_expiry >= ? AND warranty_expiry <= ?'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iss', $companyId, $today, $cutoff);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        $batch = itm_email_alert_runner_dispatch_batch(
            $conn,
            $companyId,
            'warranty_expiry',
            $rule,
            $rows,
            'Warranty expiry reminder: ',
            function (array $row, string $prefix) {
                $label = trim((string)($row['hostname'] ?? '')) ?: trim((string)($row['name'] ?? 'Equipment'));
                return $prefix . $label;
            },
            function (array $row) {
                $label = trim((string)($row['hostname'] ?? '')) ?: trim((string)($row['name'] ?? 'Equipment'));
                return '<p>Warranty for <strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong> expires on '
                    . htmlspecialchars((string)$row['warranty_expiry'], ENT_QUOTES, 'UTF-8') . '.</p>';
            }
        );
        $companyStats['warranty_expiry'] = $batch;
        $totalSent += $batch['sent'];
    }

    if (!empty($rules['license_expiry'])) {
        $rule = $rules['license_expiry'];
        $days = max(0, (int)($rule['days_before'] ?? 30));
        $cutoff = date('Y-m-d', strtotime('+' . $days . ' days'));
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, expiry_date FROM license_management
             WHERE company_id = ? AND active = 1 AND expiry_date IS NOT NULL
               AND expiry_date >= ? AND expiry_date <= ?'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iss', $companyId, $today, $cutoff);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        $batch = itm_email_alert_runner_dispatch_batch(
            $conn,
            $companyId,
            'license_expiry',
            $rule,
            $rows,
            'License expiry reminder: ',
            function (array $row, string $prefix) {
                return $prefix . (string)$row['name'];
            },
            function (array $row) {
                return '<p>License <strong>' . htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') . '</strong> expires on '
                    . htmlspecialchars((string)$row['expiry_date'], ENT_QUOTES, 'UTF-8') . '.</p>';
            }
        );
        $companyStats['license_expiry'] = $batch;
        $totalSent += $batch['sent'];
    }

    if (!empty($rules['certificate_expiry'])) {
        $rule = $rules['certificate_expiry'];
        $days = max(0, (int)($rule['days_before'] ?? 30));
        $cutoff = date('Y-m-d', strtotime('+' . $days . ' days'));
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, name, hostname, certificate_expiry FROM equipment
             WHERE company_id = ? AND active = 1 AND certificate_expiry IS NOT NULL
               AND certificate_expiry >= ? AND certificate_expiry <= ?'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iss', $companyId, $today, $cutoff);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        $batch = itm_email_alert_runner_dispatch_batch(
            $conn,
            $companyId,
            'certificate_expiry',
            $rule,
            $rows,
            'Certificate expiry reminder: ',
            function (array $row, string $prefix) {
                $label = trim((string)($row['hostname'] ?? '')) ?: trim((string)($row['name'] ?? 'Equipment'));
                return $prefix . $label;
            },
            function (array $row) {
                $label = trim((string)($row['hostname'] ?? '')) ?: trim((string)($row['name'] ?? 'Equipment'));
                return '<p>Certificate for <strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong> expires on '
                    . htmlspecialchars((string)$row['certificate_expiry'], ENT_QUOTES, 'UTF-8') . '.</p>';
            }
        );
        $companyStats['certificate_expiry'] = $batch;
        $totalSent += $batch['sent'];
    }

    if (!empty($rules['alerts_expiry'])) {
        $rule = $rules['alerts_expiry'];
        $days = max(0, (int)($rule['days_before'] ?? 30));
        $cutoff = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, title, end_datetime FROM alerts
             WHERE company_id = ? AND active = 1 AND end_datetime IS NOT NULL
               AND end_datetime >= ? AND end_datetime <= ?'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iss', $companyId, $now, $cutoff);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        $batch = itm_email_alert_runner_dispatch_batch(
            $conn,
            $companyId,
            'alerts_expiry',
            $rule,
            $rows,
            'Alert expiry reminder: ',
            function (array $row, string $prefix) {
                return $prefix . (string)$row['title'];
            },
            function (array $row) {
                return '<p>Alert <strong>' . htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') . '</strong> ends on '
                    . htmlspecialchars((string)$row['end_datetime'], ENT_QUOTES, 'UTF-8') . '.</p>';
            }
        );
        $companyStats['alerts_expiry'] = $batch;
        $totalSent += $batch['sent'];
    }

    if (!empty($rules['notes_reminder'])) {
        $rule = $rules['notes_reminder'];
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, title, reminder_at FROM notes
             WHERE company_id = ? AND active = 1 AND is_archived = 0 AND reminder_at IS NOT NULL
               AND reminder_at <= ? AND reminder_at >= DATE_SUB(?, INTERVAL 1 DAY)'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'iss', $companyId, $now, $now);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        $batch = itm_email_alert_runner_dispatch_batch(
            $conn,
            $companyId,
            'notes_reminder',
            $rule,
            $rows,
            'Note reminder: ',
            function (array $row, string $prefix) {
                $title = trim((string)($row['title'] ?? '')) ?: 'Note #' . (int)$row['id'];
                return $prefix . $title;
            },
            function (array $row) {
                $title = trim((string)($row['title'] ?? '')) ?: 'Note #' . (int)$row['id'];
                return '<p>Note <strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong> reminder at '
                    . htmlspecialchars((string)$row['reminder_at'], ENT_QUOTES, 'UTF-8') . '.</p>';
            }
        );
        $companyStats['notes_reminder'] = $batch;
        $totalSent += $batch['sent'];
    }

    if (!empty($rules['todo_deadline'])) {
        $rule = $rules['todo_deadline'];
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, title, due_date, reminder_at FROM todo
             WHERE company_id = ? AND active = 1 AND completed = 0
               AND (
                    (due_date IS NOT NULL AND due_date <= DATE_ADD(?, INTERVAL 1 DAY))
                    OR (reminder_at IS NOT NULL AND reminder_at <= ? AND reminder_at >= DATE_SUB(?, INTERVAL 1 DAY))
               )'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'isss', $companyId, $now, $now, $now);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        $batch = itm_email_alert_runner_dispatch_batch(
            $conn,
            $companyId,
            'todo_deadline',
            $rule,
            $rows,
            'To-do reminder: ',
            function (array $row, string $prefix) {
                return $prefix . (string)$row['title'];
            },
            function (array $row) {
                $html = '<p>To-do <strong>' . htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') . '</strong>';
                if (!empty($row['due_date'])) {
                    $html .= ' — due ' . htmlspecialchars((string)$row['due_date'], ENT_QUOTES, 'UTF-8');
                }
                return $html . '.</p>';
            }
        );
        $companyStats['todo_deadline'] = $batch;
        $totalSent += $batch['sent'];
    }

    if (!empty($rules['events_datetime'])) {
        $rule = $rules['events_datetime'];
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT id, title, start_datetime, end_datetime FROM events
             WHERE company_id = ? AND active = 1
               AND (
                    (start_datetime IS NOT NULL AND start_datetime <= DATE_ADD(?, INTERVAL 1 DAY) AND start_datetime >= DATE_SUB(?, INTERVAL 1 DAY))
                    OR (end_datetime IS NOT NULL AND end_datetime <= DATE_ADD(?, INTERVAL 1 DAY) AND end_datetime >= DATE_SUB(?, INTERVAL 1 DAY))
               )'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'issss', $companyId, $now, $now, $now, $now);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($result && ($row = mysqli_fetch_assoc($result))) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);
        }
        $batch = itm_email_alert_runner_dispatch_batch(
            $conn,
            $companyId,
            'events_datetime',
            $rule,
            $rows,
            'Event reminder: ',
            function (array $row, string $prefix) {
                return $prefix . (string)$row['title'];
            },
            function (array $row) {
                $html = '<p>Event <strong>' . htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') . '</strong>';
                if (!empty($row['start_datetime'])) {
                    $html .= ' starts ' . htmlspecialchars((string)$row['start_datetime'], ENT_QUOTES, 'UTF-8');
                }
                if (!empty($row['end_datetime'])) {
                    $html .= ' — ends ' . htmlspecialchars((string)$row['end_datetime'], ENT_QUOTES, 'UTF-8');
                }
                return $html . '.</p>';
            }
        );
        $companyStats['events_datetime'] = $batch;
        $totalSent += $batch['sent'];
    }

    if ($verbose) {
        foreach ($companyStats as $slug => $batch) {
            echo '  ' . $slug . ': ' . (int)$batch['matches'] . ' match(es), ' . (int)$batch['sent'] . ' sent (' . $batch['note'] . ')' . $nl;
        }
    }
}

echo 'Dispatched ' . $totalSent . ' alert email(s).' . $nl;
if ($totalSent === 0 && !$verbose) {
    echo 'Tip: re-run with --verbose to see per-rule match counts (disabled rules, missing notify_emails, or no qualifying rows).' . $nl;
}

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}

itm_script_output_end();
