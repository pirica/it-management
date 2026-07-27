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
    'signature_id' => 0,
];

$signatures = [];
if ($sessionEmail !== '' && webmail_signatures_table_exists($conn)) {
    $signatures = webmail_signatures_list($conn, $company_id, $employee_id);
}

$signatureHtmlMap = [];
foreach ($signatures as $sigRow) {
    $sigId = (int)($sigRow['id'] ?? 0);
    if ($sigId > 0) {
        $signatureHtmlMap[(string)$sigId] = webmail_render_details_html((string)($sigRow['signature'] ?? ''));
    }
}

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
        $data['signature_id'] = (int)($_POST['signature_id'] ?? 0);

        $toError = webmail_validate_email_address_list($data['to_email'], true, 'To');
        if ($toError !== null) {
            $errors[] = $toError;
        } else {
            $data['to_email'] = webmail_normalize_email_list_field($data['to_email']);
        }
        $ccError = webmail_validate_email_address_list($data['cc_email'], false, 'CC');
        if ($ccError !== null) {
            $errors[] = $ccError;
        } elseif ($data['cc_email'] !== '') {
            $data['cc_email'] = webmail_normalize_email_list_field($data['cc_email']);
        }
        if ($data['subject'] === '') {
            $errors[] = 'Subject is required.';
        }

        if ($errors === []) {
            $bodyForSend = $data['body_html'];
            if ($data['signature_id'] > 0) {
                $sigRow = webmail_signature_get($conn, $data['signature_id'], $company_id, $employee_id);
                if ($sigRow !== null) {
                    $bodyForSend = webmail_compose_merge_body_and_signature(
                        $data['body_html'],
                        (string)($sigRow['signature'] ?? '')
                    );
                }
            }
            $htmlBody = webmail_render_details_html($bodyForSend);
            if ($htmlBody === '' && trim(strip_tags($bodyForSend)) === '') {
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
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data['signature_id'] = (int)($_GET['signature_id'] ?? 0);
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
$signatureFormAction = 'signatures.php';
$signatureReturnTo = 'compose';
$selectedSignatureId = (int)($data['signature_id'] ?? 0);
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
        .webmail-compose-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
        .webmail-compose-row > label { flex: 0 0 100px; margin: 0; font-weight: 500; }
        .webmail-compose-row .form-control { flex: 1 1 200px; min-width: 0; }
        .webmail-compose-row .webmail-compose-signature-wrap { flex: 1 1 200px; display: flex; gap: 8px; align-items: center; min-width: 0; }
        .webmail-compose-row .webmail-compose-signature-wrap select { flex: 1; min-width: 0; }
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
        .webmail-compose-body-label { margin-bottom: 8px; font-weight: 500; display: block; }
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
                <?php webmail_render_tabs('compose'); ?>
            </div>

            <div class="card" style="padding:16px;">
                <form id="webmail-compose-form" method="POST" action="compose.php">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                    <div class="webmail-compose-row">
                        <label for="to_email">To</label>
                        <input type="text" name="to_email" id="to_email" class="form-control" required placeholder="Comma-separated" value="<?php echo sanitize($data['to_email']); ?>">
                    </div>
                    <div class="webmail-compose-row">
                        <label for="from_email">From</label>
                        <input type="email" id="from_email" class="form-control" readonly value="<?php echo sanitize($sessionEmail); ?>">
                    </div>
                    <div class="webmail-compose-row">
                        <label for="cc_email">CC</label>
                        <input type="text" name="cc_email" id="cc_email" class="form-control" placeholder="Optional, comma-separated" value="<?php echo sanitize($data['cc_email']); ?>">
                    </div>
                    <div class="webmail-compose-row">
                        <label for="subject">Subject</label>
                        <input type="text" name="subject" id="subject" class="form-control" required maxlength="500" value="<?php echo sanitize($data['subject']); ?>">
                    </div>
                    <div class="webmail-compose-row">
                        <label for="webmail-compose-signature-id">Select Signature</label>
                        <div class="webmail-compose-signature-wrap">
                            <select name="signature_id" id="webmail-compose-signature-id" class="form-control">
                                <option value="">—</option>
                                <?php foreach ($signatures as $sigRow): ?>
                                    <?php $sigId = (int)($sigRow['id'] ?? 0); ?>
                                    <option value="<?php echo $sigId; ?>" <?php echo $selectedSignatureId === $sigId ? 'selected' : ''; ?>><?php echo sanitize((string)($sigRow['name'] ?? '')); ?></option>
                                <?php endforeach; ?>
                                <option value="__add_new__">➕</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-danger" id="webmail-compose-signature-delete" style="display:none;" title="Delete">🗑️</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <span class="webmail-compose-body-label">Body</span>
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

                <script type="application/json" id="webmail-signature-html-map"><?php
                    echo json_encode(
                        $signatureHtmlMap,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
                    );
                ?></script>

                <?php require __DIR__ . '/includes/webmail_signature_modal.php'; ?>
            </div>
        </div>
    </div>
</div>
<script src="../../js/theme.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script src="../../js/webmail-compose.js"></script>
<script src="../../js/webmail-signatures.js"></script>
</body>
</html>
