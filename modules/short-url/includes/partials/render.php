<?php
/**
 * Short URLs — HTML render.
 */
require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$pageTitle = (string) ($crud_title ?? 'Short URLs');
$pageTitle = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), 'short-url', $pageTitle);
$csrfToken = itm_get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - <?= sanitize($app_name ?? itm_ui_config_app_name($ui_config ?? [])) ?></title>
    <?php itm_render_head_favicon_link(); ?>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .su-feature-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; }
        .su-feature-card {
            display:flex; flex-direction:column; align-items:center; gap:8px; padding:16px 12px;
            border:1px solid var(--border); border-radius:10px; background:var(--bg-primary); cursor:pointer;
            text-align:center; color:var(--text-primary);
        }
        .su-feature-card:hover, .su-feature-card.active { border-color:var(--accent); background:var(--bg-secondary); }
        .su-feature-icon { font-size:22px; width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:10px; }
        .su-feature-label { font-size:13px; font-weight:500; }
        .su-tabs { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
        .su-tab { padding:8px 16px; border-radius:8px; text-decoration:none; color:var(--text-primary); border:1px solid var(--border); }
        .su-tab.active { background:var(--accent); color:#fff; border-color:var(--accent); }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if ($suFlashSuccess): ?><div class="alert alert-success"><?= sanitize($suFlashSuccess) ?></div><?php endif; ?>
            <?php if ($suFlashError): ?><div class="alert alert-danger"><?= sanitize($suFlashError) ?></div><?php endif; ?>

            <?php if ($crud_action === 'index' || $crud_action === 'list_all'): ?>
                <div class="su-tabs">
                    <a href="index.php?tab=links" class="itm-plain-link su-tab <?= $suActiveTab === 'links' ? 'active' : '' ?>">Links</a>
                    <a href="index.php?tab=configuration" class="itm-plain-link su-tab <?= $suActiveTab === 'configuration' ? 'active' : '' ?>">Configuration</a>
                </div>
                <?php if ($suActiveTab === 'configuration'): ?>
                    <?php include __DIR__ . '/tab_configuration.php'; ?>
                <?php else: ?>
                    <?php include __DIR__ . '/tab_links.php'; ?>
                <?php endif; ?>

            <?php elseif ($crud_action === 'view' && $suRow): ?>
                <?php
                $pub = itm_short_url_build_public_url((string) $suRow['short_code']);
                $exp = trim((string) ($suRow['expires_at'] ?? ''));
                $expDisplay = $exp !== '' && function_exists('itm_format_date_display') ? itm_format_date_display(substr($exp, 0, 10)) : ($exp !== '' ? substr($exp, 0, 10) : '—');
                ?>
                <div class="card" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
                    <h1 title="View short link">🔎</h1>
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
                <div class="card" style="margin-bottom:16px;">
                    <p><strong><?= sanitize((string) $suRow['title']) ?></strong></p>
                    <p>Short URL: <code id="su-view-public"><?= sanitize($pub) ?></code>
                        <button type="button" class="btn btn-sm su-copy-btn" data-copy="<?= sanitize($pub) ?>" title="Copy">📋</button></p>
                    <p>Destination: <a class="itm-plain-link" href="<?= sanitize((string) $suRow['destination_url']) ?>" target="_blank" rel="noopener noreferrer"><?= sanitize((string) $suRow['destination_url']) ?></a></p>
                    <p>Clicks: <?= (int) $suRow['click_count'] ?></p>
                    <p>Expires: <?= sanitize($expDisplay) ?></p>
                    <p>Password: <?= trim((string) ($suRow['password_hash'] ?? '')) !== '' ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></p>
                    <?php if (!empty($suRow['qr_code_id'])): ?>
                    <p>QR: <a class="itm-plain-link" href="<?= sanitize(BASE_URL . 'modules/qr/view.php?id=' . (int) $suRow['qr_code_id']) ?>">Open QR #<?= (int) $suRow['qr_code_id'] ?></a></p>
                    <?php endif; ?>
                    <a class="btn btn-sm" href="edit.php?id=<?= (int) $suRow['id'] ?>" title="Edit">✏️</a>
                </div>
                <?php if (!empty($suClicks)): ?>
                <div class="card">
                    <h2 title="Click analytics">📊</h2>
                    <table class="table">
                        <thead><tr><th>When</th><th>User agent</th><th>Referrer</th></tr></thead>
                        <tbody>
                        <?php foreach ($suClicks as $click): ?>
                            <tr>
                                <td><?= sanitize(function_exists('itm_format_audit_timestamp_display') ? itm_format_audit_timestamp_display((string) $click['clicked_at']) : (string) $click['clicked_at']) ?></td>
                                <td><?= sanitize((string) ($click['user_agent'] ?? '')) ?></td>
                                <td><?= sanitize((string) ($click['referrer'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            <?php elseif ($crud_action === 'edit' && $suRow): ?>
                <div class="card" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
                    <h1 title="Edit short link">✏️</h1>
                    <a href="view.php?id=<?= (int) $suRow['id'] ?>" class="btn" title="Back">🔙</a>
                </div>
                <div class="card">
                    <form method="post" action="edit.php?id=<?= (int) $suRow['id'] ?>">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                        <input type="hidden" name="short_url_action" value="save">
                        <input type="hidden" name="id" value="<?= (int) $suRow['id'] ?>">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" class="form-control" value="<?= sanitize((string) $suRow['title']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Destination URL</label>
                            <input type="url" name="destination_url" class="form-control" required value="<?= sanitize((string) $suRow['destination_url']) ?>">
                        </div>
                        <div class="form-group">
                            <label>Short code</label>
                            <input type="text" name="short_code" class="form-control" required value="<?= sanitize((string) $suRow['short_code']) ?>">
                        </div>
                        <?php if (!empty($suSettings['allow_password_protect'])): ?>
                        <div class="form-group">
                            <label>New password (leave blank to keep)</label>
                            <input type="password" name="link_password" class="form-control" autocomplete="new-password">
                            <?php if (trim((string) ($suRow['password_hash'] ?? '')) !== ''): ?>
                            <label class="itm-checkbox-control" style="margin-top:8px;">
                                <input type="checkbox" name="clear_password" value="1">
                                <span>Remove password</span>
                            </label>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label>Expires (dd/mm/yyyy)</label>
                            <?php
                            $expVal = '';
                            $expRaw = trim((string) ($suRow['expires_at'] ?? ''));
                            if ($expRaw !== '' && function_exists('itm_format_date_display')) {
                                $expVal = itm_format_date_display(substr($expRaw, 0, 10));
                            }
                            ?>
                            <input type="text" name="expires_at" class="form-control" value="<?= sanitize($expVal) ?>" placeholder="dd/mm/yyyy">
                        </div>
                        <?php if (empty($suRow['qr_code_id'])): ?>
                        <div class="form-group">
                            <label class="itm-checkbox-control">
                                <input type="checkbox" name="generate_qr" value="1">
                                <span>Generate linked QR code</span>
                            </label>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary" title="Save">💾</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/itm-short-url.js" defer></script>
</body>
</html>
