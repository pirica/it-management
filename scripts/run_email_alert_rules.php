<?php
/**
 * Dispatches automated email alert rules per company.
 *
 * CLI: php scripts/run_email_alert_rules.php [--company=1]
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

if (PHP_SAPI !== 'cli') {
    if (!itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Run Email Alert Rules</title></head><body>';
    itm_script_browser_nav_echo();
}

$nl = function_exists('itm_script_output_nl') ? itm_script_output_nl() : (PHP_SAPI === 'cli' ? PHP_EOL : '<br>');

$companyFilter = 0;
if (PHP_SAPI === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '--company=') === 0) {
            $companyFilter = (int)substr($arg, 10);
        }
    }
} else {
    $companyFilter = (int)($_REQUEST['company'] ?? 0);
}

function itm_email_alert_runner_dispatch(mysqli $conn, int $companyId, string $subject, string $html, string $ruleSlug): int
{
    return itm_email_dispatch_to_rule($conn, $companyId, $ruleSlug, $subject, $html);
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

    if (!empty($rules['warranty_expiry']['enabled'])) {
        $days = max(0, (int)($rules['warranty_expiry']['days_before'] ?? 30));
        $cutoff = date('Y-m-d', strtotime('+' . $days . ' days'));
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
                $label = trim((string)($row['hostname'] ?? '')) ?: trim((string)($row['name'] ?? 'Equipment'));
                $subject = 'Warranty expiry reminder: ' . $label;
                $html = '<p>Warranty for <strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong> expires on '
                    . htmlspecialchars((string)$row['warranty_expiry'], ENT_QUOTES, 'UTF-8') . '.</p>';
                $totalSent += itm_email_alert_runner_dispatch($conn, $companyId, $subject, $html, 'warranty_expiry');
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (!empty($rules['license_expiry']['enabled'])) {
        $days = max(0, (int)($rules['license_expiry']['days_before'] ?? 30));
        $cutoff = date('Y-m-d', strtotime('+' . $days . ' days'));
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
                $subject = 'License expiry reminder: ' . (string)$row['name'];
                $html = '<p>License <strong>' . htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') . '</strong> expires on '
                    . htmlspecialchars((string)$row['expiry_date'], ENT_QUOTES, 'UTF-8') . '.</p>';
                $totalSent += itm_email_alert_runner_dispatch($conn, $companyId, $subject, $html, 'license_expiry');
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (!empty($rules['certificate_expiry']['enabled'])) {
        $days = max(0, (int)($rules['certificate_expiry']['days_before'] ?? 30));
        $cutoff = date('Y-m-d', strtotime('+' . $days . ' days'));
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
                $label = trim((string)($row['hostname'] ?? '')) ?: trim((string)($row['name'] ?? 'Equipment'));
                $subject = 'Certificate expiry reminder: ' . $label;
                $html = '<p>Certificate for <strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong> expires on '
                    . htmlspecialchars((string)$row['certificate_expiry'], ENT_QUOTES, 'UTF-8') . '.</p>';
                $totalSent += itm_email_alert_runner_dispatch($conn, $companyId, $subject, $html, 'certificate_expiry');
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (!empty($rules['alerts_expiry']['enabled'])) {
        $days = max(0, (int)($rules['alerts_expiry']['days_before'] ?? 30));
        $cutoff = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
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
                $subject = 'Alert expiry reminder: ' . (string)$row['title'];
                $html = '<p>Alert <strong>' . htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') . '</strong> ends on '
                    . htmlspecialchars((string)$row['end_datetime'], ENT_QUOTES, 'UTF-8') . '.</p>';
                $totalSent += itm_email_alert_runner_dispatch($conn, $companyId, $subject, $html, 'alerts_expiry');
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (!empty($rules['notes_reminder']['enabled'])) {
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
                $title = trim((string)($row['title'] ?? '')) ?: 'Note #' . (int)$row['id'];
                $subject = 'Note reminder: ' . $title;
                $html = '<p>Note <strong>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong> reminder at '
                    . htmlspecialchars((string)$row['reminder_at'], ENT_QUOTES, 'UTF-8') . '.</p>';
                $totalSent += itm_email_alert_runner_dispatch($conn, $companyId, $subject, $html, 'notes_reminder');
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (!empty($rules['todo_deadline']['enabled'])) {
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
                $subject = 'To-do reminder: ' . (string)$row['title'];
                $html = '<p>To-do <strong>' . htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') . '</strong>';
                if (!empty($row['due_date'])) {
                    $html .= ' — due ' . htmlspecialchars((string)$row['due_date'], ENT_QUOTES, 'UTF-8');
                }
                $html .= '.</p>';
                $totalSent += itm_email_alert_runner_dispatch($conn, $companyId, $subject, $html, 'todo_deadline');
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (!empty($rules['events_datetime']['enabled'])) {
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
                $subject = 'Event reminder: ' . (string)$row['title'];
                $html = '<p>Event <strong>' . htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') . '</strong>';
                if (!empty($row['start_datetime'])) {
                    $html .= ' starts ' . htmlspecialchars((string)$row['start_datetime'], ENT_QUOTES, 'UTF-8');
                }
                if (!empty($row['end_datetime'])) {
                    $html .= ' — ends ' . htmlspecialchars((string)$row['end_datetime'], ENT_QUOTES, 'UTF-8');
                }
                $html .= '.</p>';
                $totalSent += itm_email_alert_runner_dispatch($conn, $companyId, $subject, $html, 'events_datetime');
            }
            mysqli_stmt_close($stmt);
        }
    }
}

echo 'Dispatched ' . $totalSent . ' alert email(s).' . $nl;

if (PHP_SAPI !== 'cli') {
    echo '</body></html>';
}
