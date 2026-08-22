<?php
/**
 * QR Generator — HTML render.
 */
require_once ROOT_PATH . 'includes/itm_crud_browser_title.php';
$pageTitle = (string) ($crud_title ?? 'QR Generator');
$pageTitle = itm_crud_apply_module_icon_to_browser_title($conn, (int)($company_id ?? 0), (int)($_SESSION['employee_id'] ?? 0), 'qr', $pageTitle);
$csrfToken = itm_get_csrf_token();
$newButtonPosition = function_exists('itm_resolve_new_button_position') ? itm_resolve_new_button_position($ui_config ?? null) : 'right';
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
        .qr-type-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(132px,1fr)); gap:12px; margin:16px 0; }
        .qr-type-card {
            display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px;
            min-height:108px; padding:16px 12px; border:1px solid var(--border); border-radius:10px;
            text-align:center; text-decoration:none; color:var(--text-primary); background:var(--bg-primary);
            cursor:pointer; transition:border-color 0.2s, background-color 0.2s, box-shadow 0.2s, transform 0.15s;
        }
        .qr-type-card:hover, .qr-type-card:focus-visible {
            border-color:var(--accent); background:var(--bg-secondary);
            box-shadow:0 2px 8px rgba(0,0,0,0.06); transform:translateY(-1px);
        }
        .qr-type-card:focus-visible { outline:2px solid var(--accent); outline-offset:2px; }
        .qr-type-card:visited { color:var(--text-primary); }
        .qr-type-card.selected { border-color:var(--accent); background:var(--bg-secondary); }
        .qr-type-emoji { font-size:28px; line-height:1; }
        .qr-type-label { font-size:13px; font-weight:500; line-height:1.25; color:var(--text-primary); }
        .qr-type-picker-intro { color:var(--text-secondary); margin:0 0 4px; font-size:14px; }
        .qr-section-heading { margin:0 0 8px; font-size:20px; }
        .qr-preview-panel { border:1px solid var(--border); border-radius:8px; padding:16px; text-align:center; min-height:280px; }
        .qr-wizard-steps { display:flex; gap:8px; margin-bottom:16px; }
        .qr-wizard-steps span { padding:6px 12px; border-radius:6px; background:var(--bg-secondary); }
        .qr-wizard-steps span.active { background:var(--accent); color:#fff; }
    </style>
</head>
<body>
<div class="container">
    <?php include '../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../../includes/header.php'; ?>
        <div class="content">
            <?php if ($qrFlashSuccess): ?><div class="alert alert-success"><?= sanitize($qrFlashSuccess) ?></div><?php endif; ?>
            <?php if ($qrFlashError): ?><div class="alert alert-danger"><?= sanitize($qrFlashError) ?></div><?php endif; ?>

            <?php if ($crud_action === 'index' || $crud_action === 'list_all'): ?>
                <div data-itm-new-button-managed="server" style="position:relative;display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;min-height:40px;">
                    <?php if (in_array($newButtonPosition, ['left', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary" title="Create">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    <h1 style="position:absolute;left:50%;transform:translateX(-50%);margin:0;text-align:center;" title="QR library">📱</h1>
                    <?php if (in_array($newButtonPosition, ['right', 'left_right'], true)): ?>
                        <a href="create.php" class="btn btn-primary" title="Create">➕</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                </div>
                <div class="card" style="margin-bottom:16px;">
                    <form method="get" style="display:flex;gap:8px;">
                        <input type="text" name="search" class="form-control" value="<?= sanitize($qrSearch) ?>" placeholder="Search title or type">
                        <button type="submit" class="btn">Search</button>
                        <?php if ($qrSearch !== ''): ?><a href="index.php" class="btn" title="Clear">🔙</a><?php endif; ?>
                    </form>
                </div>
                <div class="card">
                    <table class="table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
                        <thead><tr><th>Title</th><th>Type</th><th>Mode</th><th>Scans</th><th>Created</th><th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th></tr></thead>
                        <tbody>
                        <?php if (empty($qrListRows)): ?>
                            <tr><td colspan="6">No QR codes yet. Create one to get started.</td></tr>
                        <?php else: foreach ($qrListRows as $lr): ?>
                            <tr>
                                <td><?= sanitize((string)$lr['title']) ?></td>
                                <td><?= sanitize(itm_qr_generator_type_label((string)$lr['type_slug'])) ?></td>
                                <td><span class="badge badge-<?= ($lr['encoding_mode'] === 'dynamic') ? 'success' : 'secondary' ?>"><?= sanitize((string)$lr['encoding_mode']) ?></span></td>
                                <td><?= (int)$lr['scan_count'] ?></td>
                                <td><?= sanitize(function_exists('itm_format_date_display') ? itm_format_date_display((string)$lr['created_at']) : (string)$lr['created_at']) ?></td>
                                <td class="itm-actions-cell" data-itm-actions-origin="1">
                                    <div class="itm-actions-wrap">
                                        <a class="btn btn-sm" href="view.php?id=<?= (int)$lr['id'] ?>" title="View">🔎</a>
                                        <a class="btn btn-sm" href="edit.php?id=<?= (int)$lr['id'] ?>" title="Edit">✏️</a>
                                        <form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Remove this QR code?');">
                                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$lr['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    <?php if ($qrTotalPages > 1): ?>
                    <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
                        <?php if ($qrPage > 1): ?>
                        <a class="btn btn-sm" href="?search=<?= rawurlencode($qrSearch) ?>&page=1" title="First page">⏮️</a>
                        <a class="btn btn-sm" href="?search=<?= rawurlencode($qrSearch) ?>&page=<?= $qrPage - 1 ?>" title="Previous page">◀️</a>
                        <?php endif; ?>
                        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?= $qrPage ?> of <?= $qrTotalPages ?></span>
                        <?php if ($qrPage < $qrTotalPages): ?>
                        <a class="btn btn-sm" href="?search=<?= rawurlencode($qrSearch) ?>&page=<?= $qrPage + 1 ?>" title="Next page">▶️</a>
                        <a class="btn btn-sm" href="?search=<?= rawurlencode($qrSearch) ?>&page=<?= $qrTotalPages ?>" title="Last page">⏭️</a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($crud_action === 'create' || $crud_action === 'edit'): ?>
                <div class="card" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
                    <h1 title="<?= $crud_action === 'edit' ? 'Edit QR code' : 'New QR code' ?>"><?= $crud_action === 'edit' ? '✏️' : '➕' ?></h1>
                    <a href="index.php" class="btn" title="Back">🔙</a>
                </div>
                <?php if ($qrStep === 1 && $crud_action === 'create'): ?>
                <div class="card">
                    <h2 class="qr-section-heading" title="Select QR type">Select type</h2>
                    <p class="qr-type-picker-intro">Choose a type — each tile opens the content and design wizard.</p>
                    <div class="qr-type-grid">
                        <?php foreach ($qrCatalog as $slug => $meta): ?>
                        <a class="itm-plain-link qr-type-card" href="create.php?type=<?= rawurlencode($slug) ?>&step=2" title="<?= sanitize((string)$meta['label']) ?>">
                            <span class="qr-type-emoji" aria-hidden="true"><?= sanitize((string)$meta['emoji']) ?></span>
                            <span class="qr-type-label"><?= sanitize((string)$meta['label']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php elseif ($qrSelectedType && isset($qrCatalog[$qrSelectedType])): ?>
                <form method="post" action="<?= $crud_action === 'edit' ? 'edit.php?id=' . (int)$qrId : 'create.php' ?>" id="qr-wizard-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                    <input type="hidden" name="qr_action" value="save">
                    <input type="hidden" name="type_slug" value="<?= sanitize($qrSelectedType) ?>">
                    <?php if ($crud_action === 'edit'): ?><input type="hidden" name="id" value="<?= (int)$qrId ?>"><?php endif; ?>
                    <div class="card" style="display:grid;grid-template-columns:1fr 320px;gap:24px;">
                        <div>
                            <div class="qr-wizard-steps">
                                <span class="active" title="Content">1</span>
                                <span title="Design">2</span>
                            </div>
                            <div class="form-group"><label>Title</label><input type="text" name="title" class="form-control" required value="<?= sanitize((string)($qrRow['title'] ?? '')) ?>"></div>
                            <?php
                            $catMeta = $qrCatalog[$qrSelectedType];
                            $canPickMode = empty($catMeta['dynamic_only']) && empty($catMeta['static_only']);
                            $currentMode = (string)($qrRow['encoding_mode'] ?? ($catMeta['dynamic_default'] ? 'dynamic' : 'static'));
                            if (!empty($catMeta['dynamic_only'])) { $currentMode = 'dynamic'; }
                            if (!empty($catMeta['static_only'])) { $currentMode = 'static'; }
                            ?>
                            <?php if ($canPickMode): ?>
                            <div class="form-group"><label>Encoding</label>
                                <select name="encoding_mode" class="form-control" id="qr-encoding-mode">
                                    <option value="dynamic" <?= $currentMode === 'dynamic' ? 'selected' : '' ?>>Dynamic (editable URL, scan analytics)</option>
                                    <option value="static" <?= $currentMode === 'static' ? 'selected' : '' ?>>Static (smaller QR; re-download if content changes)</option>
                                </select>
                            </div>
                            <?php else: ?>
                            <input type="hidden" name="encoding_mode" value="<?= sanitize($currentMode) ?>">
                            <p class="join-expiry"><?= $currentMode === 'dynamic' ? 'Dynamic QR — same printed code can be updated.' : 'Static QR — content is embedded directly.' ?></p>
                            <?php endif; ?>
                            <?php include __DIR__ . '/form_fields.php'; ?>
                            <hr>
                            <h3 title="Design">Design</h3>
                            <div class="form-group"><label>Size</label><input type="number" name="design[size]" min="128" max="1024" class="form-control itm-qr-design" value="<?= (int)$qrDesign['size'] ?>"></div>
                            <div class="form-group"><label>Dark color</label><input type="color" name="design[colorDark]" class="itm-qr-design" value="<?= sanitize($qrDesign['colorDark']) ?>"></div>
                            <div class="form-group"><label>Light color</label><input type="color" name="design[colorLight]" class="itm-qr-design" value="<?= sanitize($qrDesign['colorLight']) ?>"></div>
                            <div class="form-group"><label>Error correction</label>
                                <select name="design[correctLevel]" class="form-control itm-qr-design">
                                    <?php foreach (['L','M','Q','H'] as $lv): ?><option value="<?= $lv ?>" <?= $qrDesign['correctLevel'] === $lv ? 'selected' : '' ?>><?= $lv ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Logo overlay (optional)</label><input type="file" id="qr-logo-upload" accept="image/*"><input type="hidden" name="design[logo_path]" id="qr-logo-path" value="<?= sanitize((string)$qrDesign['logo_path']) ?>"></div>
                            <button type="submit" class="btn btn-primary" title="Save">💾</button>
                        </div>
                        <div class="qr-preview-panel">
                            <div id="qr-preview-mount"></div>
                            <p id="qr-preview-hint" style="font-size:12px;color:var(--text-secondary);margin-top:8px;">Preview updates as you type (static types).</p>
                            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
                                <button type="button" class="btn btn-sm" id="qr-download-png" title="Download PNG">PNG</button>
                                <button type="button" class="btn btn-sm" id="qr-download-jpg" title="Download JPG">JPG</button>
                                <button type="button" class="btn btn-sm" id="qr-download-svg" title="Download SVG">SVG</button>
                            </div>
                        </div>
                    </div>
                </form>
                <?php else: ?>
                <div class="card"><p>Select a QR type to continue.</p><a href="create.php" class="btn" title="Back">🔙</a></div>
                <?php endif; ?>

            <?php elseif ($crud_action === 'view' && $qrRow): ?>
                <div class="card" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
                    <h1 title="View QR code">🔎</h1>
                    <div style="display:flex;gap:8px;">
                        <a href="edit.php?id=<?= (int)$qrId ?>" class="btn btn-sm" title="Edit">✏️</a>
                        <a href="index.php" class="btn" title="Back">🔙</a>
                    </div>
                </div>
                <div class="card" style="display:grid;grid-template-columns:1fr 320px;gap:24px;">
                    <div>
                        <p><strong><?= sanitize((string)$qrRow['title']) ?></strong></p>
                        <p>Type: <?= sanitize(itm_qr_generator_type_label((string)$qrRow['type_slug'])) ?></p>
                        <p>Mode: <?= sanitize((string)$qrRow['encoding_mode']) ?></p>
                        <p>Scans: <?= (int)$qrRow['scan_count'] ?></p>
                        <?php if ((string)$qrRow['encoding_mode'] === 'dynamic'): ?>
                        <p>Public URL: <code><?= sanitize(itm_qr_generator_build_public_url((string)$qrRow['access_token'])) ?></code></p>
                        <?php else: ?>
                        <p>Encoded: <code style="word-break:break-all;"><?= sanitize((string)$qrRow['encoded_payload']) ?></code></p>
                        <?php endif; ?>
                        <?php if (!empty($qrScans)): ?>
                        <h3 title="Recent scans">Scans</h3>
                        <table class="table"><thead><tr><th>Time</th><th>User agent</th></tr></thead><tbody>
                        <?php foreach ($qrScans as $scan): ?>
                        <tr><td><?= sanitize(function_exists('itm_format_audit_timestamp_display') ? itm_format_audit_timestamp_display((string)$scan['scanned_at']) : (string)$scan['scanned_at']) ?></td><td><?= sanitize(substr((string)($scan['user_agent'] ?? ''), 0, 80)) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <?php endif; ?>
                    </div>
                    <div class="qr-preview-panel">
                        <div id="qr-preview-mount" data-qr-text="<?= sanitize(itm_qr_generator_resolve_qr_text($qrRow)) ?>"
                             data-size="<?= (int)$qrDesign['size'] ?>"
                             data-dark="<?= sanitize($qrDesign['colorDark']) ?>"
                             data-light="<?= sanitize($qrDesign['colorLight']) ?>"
                             data-level="<?= sanitize($qrDesign['correctLevel']) ?>"
                             data-logo="<?= sanitize((string)$qrDesign['logo_path']) ?>"></div>
                        <div style="margin-top:12px;display:flex;gap:8px;">
                            <button type="button" class="btn btn-sm" id="qr-download-png" title="Download PNG">PNG</button>
                            <button type="button" class="btn btn-sm" id="qr-download-jpg" title="Download JPG">JPG</button>
                            <button type="button" class="btn btn-sm" id="qr-download-svg" title="Download SVG">SVG</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="../../js/qrcode.min.js"></script>
<script src="../../js/itm-qr-generator.js"></script>
</body>
</html>
