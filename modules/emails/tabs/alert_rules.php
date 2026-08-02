<div class="card">
    <h2>Automated Alert Rules</h2>
    <p>Alerts are sent automatically when assets or licenses are within the configured threshold. Configure email first, then enable alerts below.</p>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">

        <?php foreach ($alertCatalog as $slug => $meta): ?>
            <?php $rule = $alertRules[$slug] ?? ['enabled' => 0, 'days_before' => 30, 'notify_emails' => '']; ?>
            <div class="email-rule-card">
                <div class="email-rule-head">
                    <div>
                        <strong><?php echo sanitize((string)$meta['label']); ?></strong>
                        <div style="color:var(--text-secondary);font-size:0.9rem;"><?php echo sanitize((string)$meta['description']); ?></div>
                    </div>
                    <label class="itm-toggle">
                        <input type="checkbox" name="rule_enabled[<?php echo sanitize($slug); ?>]" value="1" <?php echo ((int)($rule['enabled'] ?? 0) === 1) ? 'checked' : ''; ?>>
                        <span class="itm-toggle-track" aria-hidden="true"></span>
                    </label>
                </div>
                <?php if (!empty($meta['supports_days_before'])): ?>
                    <div class="form-group">
                        <label for="rule_days_<?php echo sanitize($slug); ?>">Alert X days before expiry</label>
                        <input type="number" id="rule_days_<?php echo sanitize($slug); ?>" name="rule_days_before[<?php echo sanitize($slug); ?>]" min="0" max="365" value="<?php echo sanitize((string)($rule['days_before'] ?? 30)); ?>">
                    </div>
                <?php else: ?>
                    <input type="hidden" name="rule_days_before[<?php echo sanitize($slug); ?>]" value="0">
                <?php endif; ?>
                <div class="form-group">
                    <label for="rule_notify_<?php echo sanitize($slug); ?>">Notify Email(s)</label>
                    <input type="text" id="rule_notify_<?php echo sanitize($slug); ?>" name="rule_notify_emails[<?php echo sanitize($slug); ?>]" placeholder="admin@company.com, it@company.com" value="<?php echo sanitize((string)($rule['notify_emails'] ?? '')); ?>">
                </div>
            </div>
        <?php endforeach; ?>

        <button type="submit" name="save_alert_rules" value="1" class="btn btn-primary" title="Save">💾</button>
    </form>
</div>
