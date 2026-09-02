<?php
/**
 * Short URLs — Links tab (hero + library).
 */
$csrfToken = itm_get_csrf_token();
$minCodeLen = (int) ($suSettings['custom_code_min_length'] ?? 4);
$allowPassword = !empty($suSettings['allow_password_protect']);
?>
<div class="card su-hero-card" style="margin-bottom:16px;padding:24px;">
    <h1 style="margin:0 0 8px;font-size:24px;" title="Short URLs">🔗</h1>
    <p style="margin:0 0 4px;font-size:18px;font-weight:600;">Turn your long links into powerful URLs</p>
    <p style="margin:0 0 20px;color:var(--text-secondary);">Shorten, personalize, and track your links with advanced analytics. The all-in-one solution for managing your digital campaigns.</p>
    <form method="post" action="index.php" id="su-shorten-form">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
        <input type="hidden" name="short_url_action" value="save">
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:stretch;margin-bottom:16px;">
            <div style="flex:1;min-width:220px;position:relative;">
                <input type="url" name="destination_url" id="su-destination-url" class="form-control" placeholder="Paste your URL here..." required style="padding-right:44px;">
                <button type="button" class="btn btn-sm" id="su-paste-btn" title="Paste from clipboard" style="position:absolute;right:6px;top:50%;transform:translateY(-50%);z-index:2;">📋</button>
            </div>
            <button type="submit" class="btn btn-primary" title="Shorten now">✨</button>
        </div>
        <div class="su-feature-grid">
            <button type="button" class="su-feature-card" data-su-panel="custom" title="Custom link">
                <span class="su-feature-icon" style="background:#e8f0fe;">🔗</span>
                <span class="su-feature-label">Custom Link</span>
            </button>
            <?php if ($allowPassword): ?>
            <button type="button" class="su-feature-card" data-su-panel="password" title="Password protect">
                <span class="su-feature-icon" style="background:#e6f4ea;">🛡️</span>
                <span class="su-feature-label">Password Protect</span>
            </button>
            <?php endif; ?>
            <button type="button" class="su-feature-card" data-su-panel="expiry" title="Set expiration">
                <span class="su-feature-icon" style="background:#f3e8fd;">📅</span>
                <span class="su-feature-label">Set Expiration</span>
            </button>
            <button type="button" class="su-feature-card" data-su-panel="qr" title="Generate QR code">
                <span class="su-feature-icon" style="background:#fef7e0;">📱</span>
                <span class="su-feature-label">Generate QR Code</span>
            </button>
        </div>
        <div id="su-panel-custom" class="su-option-panel" style="display:none;margin-top:16px;">
            <div class="form-group">
                <label>Custom short code (<?= (int) $minCodeLen ?>–64 chars)</label>
                <input type="text" name="short_code" id="su-short-code" class="form-control" pattern="[a-zA-Z0-9_-]+" minlength="<?= (int) $minCodeLen ?>" maxlength="64" placeholder="my-campaign">
                <small class="text-muted">Preview: <span id="su-code-preview"><?= sanitize($suPublicBase) ?></span><span id="su-code-preview-suffix"></span></small>
            </div>
        </div>
        <?php if ($allowPassword): ?>
        <div id="su-panel-password" class="su-option-panel" style="display:none;margin-top:16px;">
            <div class="form-group">
                <label>Link password (optional)</label>
                <input type="password" name="link_password" class="form-control" autocomplete="new-password">
            </div>
        </div>
        <?php endif; ?>
        <div id="su-panel-expiry" class="su-option-panel" style="display:none;margin-top:16px;">
            <div class="form-group">
                <label for="su-expires-at">Expires on (dd/mmm/yyyy)</label>
                <?php itm_render_uk_date_input('expires_at', 'su-expires-at', ''); ?>
            </div>
        </div>
        <div id="su-panel-qr" class="su-option-panel" style="display:none;margin-top:16px;">
            <label class="itm-checkbox-control">
                <input type="checkbox" name="generate_qr" value="1">
                <span>Create a linked QR code in QR Generator</span>
            </label>
        </div>
    </form>
</div>

<div class="card" style="margin-bottom:16px;">
    <form method="get" style="display:flex;gap:8px;">
        <input type="hidden" name="tab" value="links">
        <input type="text" name="search" class="form-control" value="<?= sanitize($suSearch) ?>" placeholder="Search title, URL, or code">
        <button type="submit" class="btn">Search</button>
        <?php if ($suSearch !== ''): ?><a href="index.php?tab=links" class="btn" title="Clear">🔙</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <table class="table" data-itm-no-export-excel="1" data-itm-no-export-pdf="1" data-itm-no-import-excel="1">
        <thead>
            <tr>
                <th>Short URL</th>
                <th>Destination</th>
                <th>Clicks</th>
                <th>Scans</th>
                <th>Expires</th>
                <th>QR</th>
                <th class="itm-actions-cell" data-itm-actions-origin="1">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($suListRows)): ?>
            <tr><td colspan="7">No short links yet. Paste a URL above to get started.</td></tr>
        <?php else: foreach ($suListRows as $lr):
            $pub = itm_short_url_build_public_url((string) $lr['short_code'], $conn, $suCompanyId);
            $dest = (string) $lr['destination_url'];
            $destShort = strlen($dest) > 48 ? substr($dest, 0, 45) . '…' : $dest;
            $exp = trim((string) ($lr['expires_at'] ?? ''));
            $expDisplay = $exp !== '' && function_exists('itm_format_date_display') ? itm_format_date_display(substr($exp, 0, 10)) : ($exp !== '' ? substr($exp, 0, 10) : '—');
        ?>
            <tr>
                <td>
                    <code class="su-copy-target"><?= sanitize($pub) ?></code>
                    <button type="button" class="btn btn-sm su-copy-btn" data-copy="<?= sanitize($pub) ?>" title="Copy">📋</button>
                </td>
                <td title="<?= sanitize($dest) ?>"><?= sanitize($destShort) ?></td>
                <td><?= (int) $lr['click_count'] ?></td>
                <td><?= (int) ($lr['linked_qr_id'] ?? 0) > 0 ? (int) ($lr['qr_scan_count'] ?? 0) : '—' ?></td>
                <td><?= sanitize($expDisplay) ?></td>
                <td><?= !empty($lr['qr_code_id']) ? '✅' : '—' ?></td>
                <td class="itm-actions-cell" data-itm-actions-origin="1">
                    <div class="itm-actions-wrap">
                        <a class="btn btn-sm" href="view.php?id=<?= (int) $lr['id'] ?>" title="View">🔎</a>
                        <a class="btn btn-sm" href="edit.php?id=<?= (int) $lr['id'] ?>" title="Edit">✏️</a>
                        <form method="post" action="delete.php" style="display:inline;" onsubmit="return confirm('Remove this short link?');">
                            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                            <input type="hidden" name="id" value="<?= (int) $lr['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <?php if ($suTotalPages > 1): ?>
    <div style="margin-top:12px;display:flex;gap:8px;align-items:center;">
        <?php if ($suPage > 1): ?>
        <a class="btn btn-sm" href="?tab=links&search=<?= rawurlencode($suSearch) ?>&page=1" title="First page">⏮️</a>
        <a class="btn btn-sm" href="?tab=links&search=<?= rawurlencode($suSearch) ?>&page=<?= $suPage - 1 ?>" title="Previous page">◀️</a>
        <?php endif; ?>
        <span class="btn btn-sm" style="pointer-events:none;opacity:.8;">Page <?= $suPage ?> of <?= $suTotalPages ?></span>
        <?php if ($suPage < $suTotalPages): ?>
        <a class="btn btn-sm" href="?tab=links&search=<?= rawurlencode($suSearch) ?>&page=<?= $suPage + 1 ?>" title="Next page">▶️</a>
        <a class="btn btn-sm" href="?tab=links&search=<?= rawurlencode($suSearch) ?>&page=<?= $suTotalPages ?>" title="Last page">⏭️</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
