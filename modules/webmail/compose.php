<?php
/**
 * Webmail — compose and send.
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once __DIR__ . '/includes/webmail_helpers.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$employee_id = (int)($_SESSION['employee_id'] ?? 0);
$sessionEmail = webmail_session_email();
$csrfToken = itm_get_csrf_token();
$uiConfig = itm_get_ui_configuration($conn, $company_id, $employee_id > 0 ? $employee_id : null);

$errors = [];
$notices = [];
if (!empty($_SESSION['webmail_error'])) {
    $errors[] = (string)$_SESSION['webmail_error'];
    unset($_SESSION['webmail_error']);
}
if (!empty($_SESSION['webmail_notice'])) {
    $notices[] = (string)$_SESSION['webmail_notice'];
    unset($_SESSION['webmail_notice']);
}

$data = [
    'to_email' => '',
    'cc_email' => '',
    'subject' => '',
    'body_html' => '',
];

if ($sessionEmail === '') {
    $errors[] = 'Your account has no email on file. Add a work or personal email in your profile before using Webmail.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_now'])) {
    itm_require_post_csrf();
    if ($sessionEmail === '') {
        $errors[] = 'Cannot send without a session email address.';
    } else {
        $data['to_email'] = trim((string)($_POST['to_email'] ?? ''));
        $data['cc_email'] = trim((string)($_POST['cc_email'] ?? ''));
        $data['subject'] = trim((string)($_POST['subject'] ?? ''));
        $data['body_html'] = (string)($_POST['body_html'] ?? '');

        if ($data['to_email'] === '' || !filter_var($data['to_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'To is required and must be a valid email address.';
        }
        if ($data['cc_email'] !== '') {
            $ccParts = preg_split('/[,;]+/', $data['cc_email']);
            if (is_array($ccParts)) {
                foreach ($ccParts as $ccPart) {
                    $ccPart = trim((string)$ccPart);
                    if ($ccPart !== '' && !filter_var($ccPart, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = 'CC contains an invalid email address.';
                        break;
                    }
                }
            }
        }
        if ($data['subject'] === '') {
            $errors[] = 'Subject is required.';
        }

        if ($errors === []) {
            $htmlBody = webmail_render_details_html($data['body_html']);
            if ($htmlBody === '' && trim(strip_tags($data['body_html'])) === '') {
                $htmlBody = '<p></p>';
            }
            $sendOk = itm_send_email(
                $data['to_email'],
                $data['subject'],
                $htmlBody,
                $company_id,
                [
                    'cc_email' => $data['cc_email'],
                    'email_template' => false,
                    'log_from_email' => $sessionEmail,
                    'log_details' => $htmlBody,
                    'log_created_by' => $employee_id,
                ]
            );
            if ($sendOk) {
                $_SESSION['webmail_notice'] = 'Message sent.';
                header('Location: index.php?folder=sent');
                exit;
            }
            $errors[] = 'Send failed. Check SMTP configuration under Email Management or try again later.';
        }
    }
}

$crud_title = 'Compose';
require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$crud_title = itm_crud_apply_module_icon_to_browser_title(
    $conn,
    $company_id,
    $employee_id,
    'webmail',
    (string)$crud_title
);
$currentUiConfig = $uiConfig ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($crud_title) ?> - <?php echo sanitize($app_name ?? itm_ui_config_app_name($currentUiConfig)); ?></title>
    <?php echo itm_render_head_favicon_link($favicon_url ?? null); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
    <style>
        .webmail-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px; flex-wrap: wrap; align-items: center; }
        .webmail-tab { padding: 8px 16px; text-decoration: none; color: var(--text-primary); border-radius: 6px; font-weight: 500; }
        .webmail-tab.active { background: var(--accent); color: #fff; font-weight: 600; }
        .webmail-quill-wrap .ql-toolbar.ql-snow,
        .webmail-quill-wrap .ql-container.ql-snow {
            border-color: var(--border);
            background: var(--bg-primary);
        }
        .webmail-quill-wrap .ql-editor {
            min-height: 220px;
            color: var(--text-primary);
        }
        .webmail-quill-wrap .ql-snow .ql-stroke {
            stroke: var(--text-primary);
        }
        .webmail-quill-wrap .ql-snow .ql-fill,
        .webmail-quill-wrap .ql-snow .ql-picker {
            color: var(--text-primary);
        }
        .webmail-quill-wrap .ql-snow .ql-picker-options {
            background: var(--bg-primary);
            border-color: var(--border);
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:12px;">
                <h1 style="margin:0;" title="Compose message">✉️</h1>
                <a href="index.php" class="btn" title="Back">🔙</a>
            </div>

            <?php echo itm_render_alert_errors($errors); ?>
            <?php foreach ($notices as $notice): ?>
                <div class="alert alert-success"><?php echo sanitize($notice); ?></div>
            <?php endforeach; ?>

            <div class="webmail-tabs">
                <a href="index.php?folder=inbox" class="webmail-tab">Inbox</a>
                <a href="index.php?folder=starred" class="webmail-tab">Starred</a>
                <a href="index.php?folder=sent" class="webmail-tab">Sent</a>
                <a href="index.php?folder=archived" class="webmail-tab">Archived</a>
                <a href="index.php?folder=trash" class="webmail-tab">Trash</a>
                <a href="compose.php" class="webmail-tab active">Compose</a>
            </div>

            <div class="card">
                <form id="webmail-compose-form" method="POST" action="compose.php">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <div class="form-group">
                        <label for="to_email">To</label>
                        <input type="email" name="to_email" id="to_email" class="form-control" required value="<?php echo sanitize($data['to_email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="from_email">From</label>
                        <input type="email" id="from_email" class="form-control" readonly value="<?php echo sanitize($sessionEmail); ?>">
                    </div>
                    <div class="form-group">
                        <label for="cc_email">CC</label>
                        <input type="text" name="cc_email" id="cc_email" class="form-control" placeholder="Optional, comma-separated" value="<?php echo sanitize($data['cc_email']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" name="subject" id="subject" class="form-control" required maxlength="500" value="<?php echo sanitize($data['subject']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="webmail-body-editor">Body</label>
                        <div class="webmail-quill-wrap">
                            <div id="webmail-body-editor"></div>
                        </div>
                        <script type="application/json" id="webmail-body-initial"><?php
                            echo json_encode(
                                webmail_render_details_html($data['body_html']),
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
                            );
                        ?></script>
                        <input type="hidden" name="body_html" id="webmail-body-html" value="">
                    </div>
                    <button type="submit" name="send_now" value="1" class="btn btn-primary" title="Send Now">📤</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script src="../../js/webmail-compose.js"></script>
</body>
</html>
