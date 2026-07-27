<?php
/**
 * Webmail — per-employee signatures (modal create / edit / delete).
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

$postResult = webmail_handle_signature_post($conn, $company_id, $employee_id);
if ($postResult['handled'] && $postResult['errors'] !== []) {
    $errors = array_merge($errors, $postResult['errors']);
}

if ($sessionEmail === '') {
    $errors[] = 'Your account has no email on file. Add a work or personal email in your profile before using Webmail.';
}

$signatures = [];
if ($sessionEmail !== '' && webmail_signatures_table_exists($conn)) {
    $signatures = webmail_signatures_list($conn, $company_id, $employee_id);
} elseif ($sessionEmail !== '' && !webmail_signatures_table_exists($conn)) {
    $errors[] = 'Signatures table is missing. Import the latest database schema.';
}

$signatureHtmlMap = [];
foreach ($signatures as $sigRow) {
    $sigId = (int)($sigRow['id'] ?? 0);
    if ($sigId > 0) {
        $signatureHtmlMap[(string)$sigId] = webmail_render_details_html((string)($sigRow['signature'] ?? ''));
    }
}

$crud_title = 'Signatures';
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
$signatureReturnTo = '';
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
            min-height: 160px;
            color: var(--text-primary);
        }
        .webmail-quill-wrap .ql-snow .ql-stroke { stroke: var(--text-primary); }
        .webmail-quill-wrap .ql-snow .ql-fill,
        .webmail-quill-wrap .ql-snow .ql-picker { color: var(--text-primary); }
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
                <h1 style="margin:0;" title="Signatures">✍️</h1>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="button" class="btn btn-primary" data-webmail-signature-create="1" title="Create">➕</button>
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
            </div>

            <?php echo itm_render_alert_errors($errors); ?>
            <?php foreach ($notices as $notice): ?>
                <div class="alert alert-success"><?php echo sanitize($notice); ?></div>
            <?php endforeach; ?>

            <div class="webmail-tabs">
                <?php webmail_render_tabs('signatures'); ?>
            </div>

            <div class="card">
                <?php if ($signatures === []): ?>
                    <p style="margin:16px;color:var(--text-secondary);">No signatures yet. Use ➕ to create one.</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($signatures as $sigRow): ?>
                                <?php
                                $sigId = (int)($sigRow['id'] ?? 0);
                                $sigName = (string)($sigRow['name'] ?? '');
                                ?>
                                <tr>
                                    <td><?php echo sanitize($sigName); ?></td>
                                    <td class="itm-actions-cell" data-itm-actions-origin="1">
                                        <span class="itm-actions-wrap">
                                            <button type="button" class="btn btn-sm" title="Edit"
                                                data-webmail-signature-edit="<?php echo (int)$sigId; ?>"
                                                data-webmail-signature-name="<?php echo sanitize($sigName); ?>">✏️</button>
                                            <button type="button" class="btn btn-sm btn-danger" title="Delete"
                                                data-webmail-signature-delete="<?php echo (int)$sigId; ?>"
                                                data-webmail-signature-name="<?php echo sanitize($sigName); ?>">🗑️</button>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

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
<script src="../../js/theme.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script src="../../js/webmail-signatures.js"></script>
</body>
</html>
