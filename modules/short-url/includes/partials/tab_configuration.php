<?php
/**
 * Short URLs — Configuration tab.
 */
$csrfToken = itm_get_csrf_token();
$s = $suSettings;
$defaultPublicBase = itm_short_url_default_public_base_prefix();
$publicBaseValue = trim((string) ($s['public_base_url'] ?? ''));
?>
<div class="card">
    <h1 title="Short URL configuration">⚙️</h1>
    <p style="color:var(--text-secondary);">Company defaults for new short links. Administrators can edit; all users can view.</p>
    <?php if (!$suCanEditSettings): ?>
        <div class="alert alert-info">Settings are read-only. Contact an administrator to change defaults.</div>
    <?php endif; ?>
    <form method="post" action="index.php?tab=configuration">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
        <input type="hidden" name="save_short_url_settings" value="1">
        <div class="form-group">
            <label>Public short link base URL</label>
            <input type="url" name="public_base_url" class="form-control" maxlength="512" value="<?= sanitize($publicBaseValue) ?>" placeholder="<?= sanitize($defaultPublicBase) ?>" <?= $suCanEditSettings ? '' : 'readonly' ?>>
            <small class="text-muted">Prefix before the short code (include <code>?c=</code>). Leave blank to use the application base URL: <code><?= sanitize($defaultPublicBase) ?></code></small>
        </div>
        <div class="form-group">
            <label>Default expiry (days)</label>
            <input type="number" name="default_expiry_days" class="form-control" min="1" max="3650" value="<?= sanitize((string) ($s['default_expiry_days'] ?? '')) ?>" placeholder="Leave blank for no default expiry" <?= $suCanEditSettings ? '' : 'readonly' ?>>
        </div>
        <div class="form-group">
            <label>Minimum custom code length</label>
            <input type="number" name="custom_code_min_length" class="form-control" min="4" max="12" value="<?= (int) ($s['custom_code_min_length'] ?? 4) ?>" <?= $suCanEditSettings ? '' : 'readonly' ?>>
        </div>
        <div class="form-group">
            <label class="itm-checkbox-control">
                <input type="checkbox" name="require_https_destination" value="1" <?= !empty($s['require_https_destination']) ? 'checked' : '' ?> <?= $suCanEditSettings ? '' : 'disabled' ?>>
                <span>Require HTTPS destinations</span>
            </label>
        </div>
        <div class="form-group">
            <label class="itm-checkbox-control">
                <input type="checkbox" name="analytics_enabled" value="1" <?= !empty($s['analytics_enabled']) ? 'checked' : '' ?> <?= $suCanEditSettings ? '' : 'disabled' ?>>
                <span>Enable click analytics</span>
            </label>
        </div>
        <div class="form-group">
            <label class="itm-checkbox-control">
                <input type="checkbox" name="allow_password_protect" value="1" <?= !empty($s['allow_password_protect']) ? 'checked' : '' ?> <?= $suCanEditSettings ? '' : 'disabled' ?>>
                <span>Allow password-protected links</span>
            </label>
        </div>
        <?php if ($suCanEditSettings): ?>
        <button type="submit" class="btn btn-primary" title="Save">💾</button>
        <?php endif; ?>
    </form>
</div>
