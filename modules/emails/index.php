<?php
/**
 * Emails Module — send logs, SMTP configurations, and automated alert rules.
 */

$crud_table = 'emails';
$crud_title = 'Emails';
$crud_action = $crud_action ?? 'index';

require_once dirname(__DIR__, 2) . '/config/config.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$csrfToken = itm_get_csrf_token();
$uiConfig = itm_get_ui_configuration($conn, $company_id, $employee_id > 0 ? $employee_id : null);

if (($uiConfig['enable_all_error_reporting'] ?? 0) == 1) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
}

$active_tab = $_GET['tab'] ?? 'send_logs';
$allowed_tabs = ['send_logs', 'smtp', 'alert_rules'];
if (!in_array($active_tab, $allowed_tabs, true)) {
    $active_tab = 'send_logs';
}

$status_filter = $_GET['status'] ?? '';
if (!in_array($status_filter, ['', 'sent', 'failed'], true)) {
    $status_filter = '';
}

$searchRaw = trim((string)($_GET['search'] ?? ''));

$errors = [];
$notices = [];

itm_email_ensure_alert_rules($conn, $company_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    if (isset($_POST['save_smtp_config'])) {
        $configId = (int)($_POST['smtp_config_id'] ?? 0);
        $configName = trim((string)($_POST['config_name'] ?? ''));
        $smtpHost = trim((string)($_POST['smtp_host'] ?? ''));
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $fromEmail = trim((string)($_POST['from_email'] ?? ''));
        $fromName = trim((string)($_POST['from_name'] ?? ''));
        $imapPort = (int)($_POST['imap_port'] ?? 143);
        $pop3Port = (int)($_POST['pop3_port'] ?? 110);
        $pop3TlsMode = trim((string)($_POST['pop3_tls_mode'] ?? 'None'));
        $pop3RequireSecure = isset($_POST['pop3_require_secure_connection']) ? 1 : 0;
        $isDefault = isset($_POST['is_default']) ? 1 : 0;

        if ($configName === '' || $smtpHost === '' || $fromEmail === '') {
            $errors[] = 'Config Name, SMTP Host, and From Email are required.';
        } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'From Email must be a valid email address.';
        } elseif ($imapPort < 1 || $imapPort > 65535 || $pop3Port < 1 || $pop3Port > 65535) {
            $errors[] = 'IMAP and POP3 ports must be between 1 and 65535.';
        } else {
            if ($pop3TlsMode === '') {
                $pop3TlsMode = 'None';
            }
            if ($isDefault === 1) {
                $clearStmt = mysqli_prepare($conn, 'UPDATE email_smtp_configurations SET is_default = 0 WHERE company_id = ?');
                if ($clearStmt) {
                    mysqli_stmt_bind_param($clearStmt, 'i', $company_id);
                    mysqli_stmt_execute($clearStmt);
                    mysqli_stmt_close($clearStmt);
                }
            }

            if ($configId > 0) {
                if ($password !== '') {
                    $encrypted = itm_email_encrypt_password($password);
                    $stmt = mysqli_prepare(
                        $conn,
                        'UPDATE email_smtp_configurations
                         SET config_name = ?, smtp_host = ?, smtp_port = ?, username = ?, password_encrypted = ?,
                             from_email = ?, from_name = ?, imap_port = ?, pop3_port = ?, pop3_tls_mode = ?,
                             pop3_require_secure_connection = ?, is_default = ?
                         WHERE id = ? AND company_id = ?'
                    );
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 'ssissssiiisiii', $configName, $smtpHost, $smtpPort, $username, $encrypted, $fromEmail, $fromName, $imapPort, $pop3Port, $pop3TlsMode, $pop3RequireSecure, $isDefault, $configId, $company_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        'UPDATE email_smtp_configurations
                         SET config_name = ?, smtp_host = ?, smtp_port = ?, username = ?,
                             from_email = ?, from_name = ?, imap_port = ?, pop3_port = ?, pop3_tls_mode = ?,
                             pop3_require_secure_connection = ?, is_default = ?
                         WHERE id = ? AND company_id = ?'
                    );
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, 'ssisssiiisiii', $configName, $smtpHost, $smtpPort, $username, $fromEmail, $fromName, $imapPort, $pop3Port, $pop3TlsMode, $pop3RequireSecure, $isDefault, $configId, $company_id);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }
                $notices[] = 'SMTP configuration updated.';
            } else {
                $encrypted = $password !== '' ? itm_email_encrypt_password($password) : '';
                $stmt = mysqli_prepare(
                    $conn,
                    'INSERT INTO email_smtp_configurations
                     (company_id, config_name, smtp_host, smtp_port, username, password_encrypted, from_email, from_name,
                      imap_port, pop3_port, pop3_tls_mode, pop3_require_secure_connection, is_default, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ississsiiisiii', $company_id, $configName, $smtpHost, $smtpPort, $username, $encrypted, $fromEmail, $fromName, $imapPort, $pop3Port, $pop3TlsMode, $pop3RequireSecure, $isDefault);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                    $notices[] = 'SMTP configuration created.';
                }
            }
            $active_tab = 'smtp';
        }
    }

    if (isset($_POST['delete_smtp_config'])) {
        $deleteId = (int)($_POST['smtp_config_id'] ?? 0);
        if ($deleteId > 0) {
            $stmt = mysqli_prepare($conn, 'UPDATE email_smtp_configurations SET active = 0 WHERE id = ? AND company_id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ii', $deleteId, $company_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $notices[] = 'SMTP configuration removed.';
            }
        }
        $active_tab = 'smtp';
    }

    if (isset($_POST['send_test_email'])) {
        $testTo = trim((string)($_POST['test_to_email'] ?? ''));
        $testConfigId = (int)($_POST['smtp_config_id'] ?? 0);
        if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid test recipient email.';
        } else {
            $subject = 'Test Email from IT Manager Pro';
            $html = '<p>This is a test message from the Email Management module. If you received this, your SMTP profile is working.</p>';
            $ok = itm_send_email($testTo, $subject, $html, $company_id, [
                'smtp_config_id' => $testConfigId > 0 ? $testConfigId : null,
                'email_template' => [
                    'subtitle' => 'SMTP test message',
                    'footer_text' => 'Sent from Email Management → SMTP Configurations.',
                ],
            ]);
            if ($ok) {
                $notices[] = 'Test email sent to ' . $testTo . '.';
            } else {
                $errors[] = 'Test email failed — check SMTP settings and error log.';
            }
        }
        $active_tab = 'smtp';
    }

    if (isset($_POST['save_alert_rules'])) {
        $catalog = itm_email_alert_rule_catalog();
        foreach (array_keys($catalog) as $slug) {
            $enabled = isset($_POST['rule_enabled'][$slug]) ? 1 : 0;
            $daysBefore = (int)($_POST['rule_days_before'][$slug] ?? 30);
            if ($daysBefore < 0) {
                $daysBefore = 0;
            }
            $notifyRaw = trim((string)($_POST['rule_notify_emails'][$slug] ?? ''));
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE email_alert_rules
                 SET enabled = ?, days_before = ?, notify_emails = ?
                 WHERE company_id = ? AND rule_slug = ?'
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iisis', $enabled, $daysBefore, $notifyRaw, $company_id, $slug);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        $notices[] = 'Alert rules saved.';
        $active_tab = 'alert_rules';
    }
}

$totalEmails = 0;
$sentEmails = 0;
$failedEmails = 0;

$countStmt = mysqli_prepare(
    $conn,
    'SELECT
        COUNT(*) AS total_count,
        SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS sent_count,
        SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed_count
     FROM emails
     WHERE company_id = ? AND active = 1'
);
if ($countStmt) {
    mysqli_stmt_bind_param($countStmt, 'i', $company_id);
    mysqli_stmt_execute($countStmt);
    mysqli_stmt_bind_result($countStmt, $totalEmails, $sentEmails, $failedEmails);
    mysqli_stmt_fetch($countStmt);
    mysqli_stmt_close($countStmt);
}

$sendLogs = [];
$perPage = itm_resolve_records_per_page($uiConfig ?? null);
$page = max(1, (int)($_GET['page'] ?? 1));
$sendLogsWhereSql = 'company_id = ? AND active = 1';
$sendLogsTypes = 'i';
$sendLogsParams = [$company_id];
if ($status_filter !== '') {
    $sendLogsWhereSql .= ' AND status = ?';
    $sendLogsTypes .= 's';
    $sendLogsParams[] = $status_filter;
}
if ($searchRaw !== '') {
    $searchPattern = (strpos($searchRaw, '%') !== false || strpos($searchRaw, '_') !== false) ? $searchRaw : '%' . $searchRaw . '%';
    $sendLogsWhereSql .= ' AND (
        to_email LIKE ?
        OR subject LIKE ?
        OR status LIKE ?
        OR details LIKE ?
        OR CAST(sent_at AS CHAR) LIKE ?
    )';
    $sendLogsTypes .= 'sssss';
    $sendLogsParams[] = $searchPattern;
    $sendLogsParams[] = $searchPattern;
    $sendLogsParams[] = $searchPattern;
    $sendLogsParams[] = $searchPattern;
    $sendLogsParams[] = $searchPattern;
}

$sendLogsTotalRows = 0;
$sendLogsCountSql = 'SELECT COUNT(*) FROM emails WHERE ' . $sendLogsWhereSql;
$sendLogsCountStmt = mysqli_prepare($conn, $sendLogsCountSql);
if ($sendLogsCountStmt) {
    mysqli_stmt_bind_param($sendLogsCountStmt, $sendLogsTypes, ...$sendLogsParams);
    mysqli_stmt_execute($sendLogsCountStmt);
    mysqli_stmt_bind_result($sendLogsCountStmt, $sendLogsTotalRows);
    mysqli_stmt_fetch($sendLogsCountStmt);
    mysqli_stmt_close($sendLogsCountStmt);
}
$sendLogsTotalRows = (int)$sendLogsTotalRows;

$sendLogsTotalPages = max(1, (int)ceil($sendLogsTotalRows / $perPage));
if ($page > $sendLogsTotalPages) {
    $page = $sendLogsTotalPages;
}
$sendLogsOffset = ($page - 1) * $perPage;

$logSql = 'SELECT id, to_email, subject, status, details, sent_at
           FROM emails
           WHERE ' . $sendLogsWhereSql . ' ORDER BY sent_at DESC, id DESC LIMIT ? OFFSET ?';
$logTypes = $sendLogsTypes . 'ii';
$logParams = array_merge($sendLogsParams, [$perPage, $sendLogsOffset]);
$logStmt = mysqli_prepare($conn, $logSql);
if ($logStmt) {
    mysqli_stmt_bind_param($logStmt, $logTypes, ...$logParams);
    mysqli_stmt_execute($logStmt);
    $logResult = mysqli_stmt_get_result($logStmt);
    while ($logResult && ($row = mysqli_fetch_assoc($logResult))) {
        $sendLogs[] = $row;
    }
    mysqli_stmt_close($logStmt);
}

$emailsSendLogsPageUrl = static function (array $extra = []) use ($status_filter, $searchRaw, $page): string {
    $query = ['tab' => 'send_logs'];
    if ($status_filter !== '') {
        $query['status'] = $status_filter;
    }
    if ($searchRaw !== '') {
        $query['search'] = $searchRaw;
    }
    if (!array_key_exists('page', $extra)) {
        $query['page'] = $page;
    }
    $query = array_merge($query, $extra);

    return 'index.php?' . http_build_query($query);
};

$smtpConfigs = [];
$smtpStmt = mysqli_prepare(
    $conn,
    'SELECT id, config_name, smtp_host, smtp_port, username, password_encrypted, from_email, from_name,
            imap_port, pop3_port, pop3_tls_mode, pop3_require_secure_connection, is_default
     FROM email_smtp_configurations
     WHERE company_id = ? AND active = 1
     ORDER BY is_default DESC, config_name ASC'
);
if ($smtpStmt) {
    mysqli_stmt_bind_param($smtpStmt, 'i', $company_id);
    mysqli_stmt_execute($smtpStmt);
    $smtpResult = mysqli_stmt_get_result($smtpStmt);
    while ($smtpResult && ($row = mysqli_fetch_assoc($smtpResult))) {
        $smtpConfigs[] = $row;
    }
    mysqli_stmt_close($smtpStmt);
}

$editSmtpId = (int)($_GET['smtp_id'] ?? 0);
$editSmtp = null;
if ($editSmtpId > 0) {
    foreach ($smtpConfigs as $cfg) {
        if ((int)$cfg['id'] === $editSmtpId) {
            $editSmtp = $cfg;
            break;
        }
    }
}

$alertRules = itm_email_get_alert_rules($conn, $company_id);
$alertCatalog = itm_email_alert_rule_catalog();

$sendLogsBaseQuery = ['tab' => 'send_logs'];
if ($searchRaw !== '') {
    $sendLogsBaseQuery['search'] = $searchRaw;
}
$sendLogsClearUrl = 'index.php?tab=send_logs' . ($status_filter !== '' ? '&status=' . rawurlencode($status_filter) : '');
$sendLogsStatHrefAll = htmlspecialchars('index.php?' . http_build_query($sendLogsBaseQuery), ENT_QUOTES, 'UTF-8');
$sendLogsStatHrefSent = htmlspecialchars('index.php?' . http_build_query(array_merge($sendLogsBaseQuery, ['status' => 'sent'])), ENT_QUOTES, 'UTF-8');
$sendLogsStatHrefFailed = htmlspecialchars('index.php?' . http_build_query(array_merge($sendLogsBaseQuery, ['status' => 'failed'])), ENT_QUOTES, 'UTF-8');

$page_title = 'Email Management';
$modulePath = dirname($_SERVER['PHP_SELF']);
// Why: List h1 must use Settings sidebar label so per-user emoji overrides apply in the tab shell header.
$moduleListHeading = itm_sidebar_label_for_module(basename(dirname($_SERVER['PHP_SELF']))) ?: $crud_title;
$newButtonPosition = (string)($uiConfig['new_button_position'] ?? 'left_right');
if (!in_array($newButtonPosition, ['left', 'right', 'left_right'], true)) {
    $newButtonPosition = 'left_right';
}
$showSmtpCreateButton = ($active_tab === 'smtp');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
if (!isset($currentUiConfig)) {
    $currentUiConfig = $ui_config ?? [];
}
if (!isset($crud_title)) {
    $crud_title = 'Emails';
}
?>
<title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .email-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .email-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; flex-wrap: wrap; }
        .email-tab { padding: 8px 16px; text-decoration: none; color: var(--text-primary); border-radius: 6px; font-weight: 500; }
        .email-tab.active { background: var(--accent); color: #fff; font-weight: 600; }
        .email-tab:hover:not(.active) { background: var(--bg-secondary); }
        .email-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .email-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        .email-rule-card { border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 16px; background: var(--bg-primary); }
        .email-rule-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .itm-toggle { position: relative; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
        .itm-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
        .itm-toggle-track { width: 44px; height: 24px; background: #c9d1d9; border-radius: 999px; position: relative; transition: background 0.2s; }
        .itm-toggle-track::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; background: #fff; border-radius: 50%; transition: transform 0.2s; }
        .itm-toggle input:checked + .itm-toggle-track { background: var(--accent); }
        .itm-toggle input:checked + .itm-toggle-track::after { transform: translateX(20px); }
        .smtp-list { display: grid; gap: 12px; margin-bottom: 20px; }
        .smtp-item { border: 1px solid var(--border); border-radius: 8px; padding: 12px 16px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center; }
        .smtp-item-default { border-color: var(--accent); }
        .password-reveal-wrap { display: flex; gap: 8px; align-items: center; }
        .password-reveal-wrap input { flex: 1; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap;min-height:40px;">
                <?php if ($showSmtpCreateButton && in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;"><?php echo sanitize($moduleListHeading); ?></h1>
                <?php if ($showSmtpCreateButton && in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                    <a href="create.php" class="btn btn-primary itm-list-new-button" title="Create">➕</a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
            </div>

            <?php echo itm_render_alert_errors($errors); ?>
            <?php foreach ($notices as $notice): ?>
                <div class="alert alert-success"><?php echo sanitize($notice); ?></div>
            <?php endforeach; ?>

            <div class="email-stats">
                <a class="stat-card stat-card-link" href="<?php echo $sendLogsStatHrefAll; ?>">
                    <div class="stat-label">Total Emails Logged</div>
                    <div class="stat-number"><?php echo (int)$totalEmails; ?></div>
                </a>
                <a class="stat-card stat-card-link" href="<?php echo $sendLogsStatHrefSent; ?>">
                    <div class="stat-label">Successfully Sent</div>
                    <div class="stat-number"><?php echo (int)$sentEmails; ?></div>
                </a>
                <a class="stat-card stat-card-link" href="<?php echo $sendLogsStatHrefFailed; ?>">
                    <div class="stat-label">Failed</div>
                    <div class="stat-number"><?php echo (int)$failedEmails; ?></div>
                </a>
            </div>

            <div class="email-tabs">
                <a href="?tab=send_logs" class="email-tab <?php echo $active_tab === 'send_logs' ? 'active' : ''; ?>">Send Logs</a>
                <a href="?tab=smtp" class="email-tab <?php echo $active_tab === 'smtp' ? 'active' : ''; ?>">SMTP Configurations</a>
                <a href="?tab=alert_rules" class="email-tab <?php echo $active_tab === 'alert_rules' ? 'active' : ''; ?>">Alert Rules</a>
            </div>

            <?php
            $tab_file = __DIR__ . '/tabs/' . $active_tab . '.php';
            if (file_exists($tab_file)) {
                include $tab_file;
            } else {
                echo '<div class="alert alert-danger">Tab content not found.</div>';
            }
            ?>
        </div>
    </div>
</div>
<script>
function togglePasswordField(btn) {
    const input = document.getElementById('smtp-password');
    if (!input) return;
    const reveal = btn.getAttribute('data-reveal') === '1';
    input.type = reveal ? 'text' : 'password';
    btn.setAttribute('data-reveal', reveal ? '0' : '1');
    btn.textContent = reveal ? '👁️' : '🙈';
}
function exportEmailLogsXlsx() {
    const rows = <?php echo json_encode(array_map(static function ($row) {
        $sentAt = $row['sent_at'] ?? '';
        if ($sentAt !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $sentAt);
            if ($dt instanceof DateTimeImmutable) {
                $sentAt = $dt->format('d M Y H:i');
            }
        }
        return [
            'to' => $row['to_email'] ?? '',
            'subject' => $row['subject'] ?? '',
            'status' => ucfirst((string)($row['status'] ?? '')),
            'date' => $sentAt,
            'details' => $row['details'] ?? '',
        ];
    }, $sendLogs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    function writeWorkbook() {
        const header = ['To', 'Subject', 'Status', 'Date', 'Details'];
        const data = [header].concat(rows.map(function (row) {
            return [row.to, row.subject, row.status, row.date, row.details];
        }));
        const ws = XLSX.utils.aoa_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Send Log');
        XLSX.writeFile(wb, 'email_send_log.xlsx');
    }
    if (typeof XLSX !== 'undefined') {
        writeWorkbook();
        return;
    }
    const script = document.createElement('script');
    script.src = '../../js/vendor/xlsx.full.min.js';
    script.onload = writeWorkbook;
    document.head.appendChild(script);
}
</script>
</body>
</html>
