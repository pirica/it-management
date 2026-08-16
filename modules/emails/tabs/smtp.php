<div class="card" style="margin-bottom:16px;">
    <h2>SMTP Configurations</h2>
    <?php if (!empty($smtpConfigs)): ?>
        <div class="smtp-list">
            <?php foreach ($smtpConfigs as $cfg): ?>
                <div class="smtp-item<?php echo ((int)($cfg['is_default'] ?? 0) === 1) ? ' smtp-item-default' : ''; ?>">
                    <div>
                        <strong><?php echo sanitize((string)$cfg['config_name']); ?></strong>
                        <?php if ((int)($cfg['is_default'] ?? 0) === 1): ?>
                            <span class="badge badge-success">Default</span>
                        <?php endif; ?>
                        <div><?php echo sanitize((string)$cfg['smtp_host']); ?>:<?php echo sanitize((string)$cfg['smtp_port']); ?></div>
                        <div>IMAP <?php echo sanitize((string)($cfg['imap_host'] ?? '')); ?><?php if (!empty($cfg['imap_host'])): ?>:<?php echo sanitize((string)($cfg['imap_port'] ?? '143')); ?><?php else: ?> <?php echo sanitize((string)($cfg['imap_port'] ?? '143')); ?><?php endif; ?> · POP3 <?php echo sanitize((string)($cfg['pop3_port'] ?? '110')); ?><?php if (!empty($cfg['pop3_tls_mode'])): ?> (<?php echo sanitize((string)$cfg['pop3_tls_mode']); ?>)<?php endif; ?></div>
                        <?php if ((int)($cfg['inbound_ticket_enabled'] ?? 0) === 1): ?>
                            <div><span class="badge badge-success">Inbound tickets</span></div>
                        <?php endif; ?>
                        <div><?php echo sanitize((string)$cfg['from_email']); ?><?php if (!empty($cfg['from_name'])): ?> (<?php echo sanitize((string)$cfg['from_name']); ?>)<?php endif; ?></div>
                    </div>
                    <div class="itm-actions-wrap">
                        <a class="btn btn-sm" href="?tab=smtp&amp;smtp_id=<?php echo (int)$cfg['id']; ?>" title="Edit">✏️</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this SMTP configuration?');">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                            <input type="hidden" name="smtp_config_id" value="<?php echo (int)$cfg['id']; ?>">
                            <button type="submit" name="delete_smtp_config" value="1" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>No SMTP configurations yet. Add one below.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?php echo $editSmtp ? 'Edit SMTP Configuration' : 'Add SMTP Configuration'; ?></h2>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
        <input type="hidden" name="smtp_config_id" value="<?php echo $editSmtp ? (int)$editSmtp['id'] : 0; ?>">
        <div class="email-form-grid">
            <div class="form-group">
                <label for="config_name">Config Name *</label>
                <input type="text" id="config_name" name="config_name" required value="<?php echo sanitize((string)($editSmtp['config_name'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="smtp_host">SMTP Host *</label>
                <input type="text" id="smtp_host" name="smtp_host" required value="<?php echo sanitize((string)($editSmtp['smtp_host'] ?? 'smtp.office365.com')); ?>">
            </div>
            <div class="form-group">
                <label for="smtp_port">Port</label>
                <input type="number" id="smtp_port" name="smtp_port" min="1" max="65535" value="<?php echo sanitize((string)($editSmtp['smtp_port'] ?? '587')); ?>">
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo sanitize((string)($editSmtp['username'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="smtp-password">Password</label>
                <div class="password-reveal-wrap">
                    <input type="password" id="smtp-password" name="password" autocomplete="new-password" placeholder="<?php echo $editSmtp ? 'Leave blank to keep current password' : ''; ?>">
                    <button type="button" class="btn btn-sm" data-reveal="0" onclick="togglePasswordField(this)" title="Show or hide password">👁️</button>
                </div>
            </div>
            <div class="form-group">
                <label for="from_email">From Email</label>
                <input type="email" id="from_email" name="from_email" value="<?php echo sanitize((string)($editSmtp['from_email'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label for="from_name">From Name</label>
                <input type="text" id="from_name" name="from_name" value="<?php echo sanitize((string)($editSmtp['from_name'] ?? 'Mail Manager')); ?>">
            </div>
        </div>

        <h3 style="margin-top:20px;">IMAP</h3>
        <?php if (!empty($companyInboundEmail)): ?>
            <p style="margin:0 0 12px;color:var(--text-secondary);">Inbound tickets: mail sent to <strong><?php echo sanitize($companyInboundEmail); ?></strong> (<code>companies.email</code>) should arrive in this mailbox when polling is enabled.</p>
        <?php else: ?>
            <p style="margin:0 0 12px;color:var(--text-secondary);">Set <code>companies.email</code> on the company profile so senders know which address creates tickets for this tenant.</p>
        <?php endif; ?>
        <div class="email-form-grid">
            <div class="form-group">
                <label for="imap_host">IMAP Host</label>
                <input type="text" id="imap_host" name="imap_host" value="<?php echo sanitize((string)($editSmtp['imap_host'] ?? '')); ?>" placeholder="mail.example.com">
            </div>
            <div class="form-group">
                <label for="imap_port">Port</label>
                <input type="number" id="imap_port" name="imap_port" min="1" max="65535" value="<?php echo sanitize((string)($editSmtp['imap_port'] ?? '143')); ?>">
            </div>
        </div>
        <div class="form-group" style="margin-top:12px;">
            <label class="itm-toggle">
                <input type="checkbox" name="inbound_ticket_enabled" value="1" <?php echo ((int)($editSmtp['inbound_ticket_enabled'] ?? 0) === 1) ? 'checked' : ''; ?>>
                <span class="itm-toggle-track" aria-hidden="true"></span>
                <span>Create tickets from inbound mail</span>
            </label>
        </div>

        <h3 style="margin-top:20px;">POP3</h3>
        <div class="email-form-grid">
            <div class="form-group">
                <label for="pop3_port">Port</label>
                <input type="number" id="pop3_port" name="pop3_port" min="1" max="65535" value="<?php echo sanitize((string)($editSmtp['pop3_port'] ?? '110')); ?>">
            </div>
            <div class="form-group">
                <label for="pop3_tls_mode">TLS mode</label>
                <input type="text" id="pop3_tls_mode" name="pop3_tls_mode" value="<?php echo sanitize((string)($editSmtp['pop3_tls_mode'] ?? 'None')); ?>">
            </div>
        </div>
        <div class="form-group" style="margin-top:12px;">
            <label class="itm-toggle">
                <input type="checkbox" name="pop3_require_secure_connection" value="1" <?php echo ((int)($editSmtp['pop3_require_secure_connection'] ?? 0) === 1) ? 'checked' : ''; ?>>
                <span class="itm-toggle-track" aria-hidden="true"></span>
                <span>Require secure connection</span>
            </label>
        </div>

        <div class="form-group" style="margin-top:12px;">
            <label class="itm-toggle">
                <input type="checkbox" name="is_default" value="1" <?php echo ((int)($editSmtp['is_default'] ?? 0) === 1 || !$editSmtp) ? 'checked' : ''; ?>>
                <span class="itm-toggle-track" aria-hidden="true"></span>
                <span>Set as default SMTP</span>
            </label>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;">
            <button type="submit" name="save_smtp_config" value="1" class="btn btn-primary" title="Save">💾</button>
            <?php if ($editSmtp): ?>
                <a href="?tab=smtp" class="btn" title="Back">🔙</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($editSmtp): ?>
        <hr style="margin:24px 0;">
        <h3>Send test email</h3>
        <form method="POST" class="email-form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
            <input type="hidden" name="smtp_config_id" value="<?php echo (int)$editSmtp['id']; ?>">
            <div class="form-group">
                <label for="test_to_email">Test recipient</label>
                <input type="email" id="test_to_email" name="test_to_email" placeholder="you@example.com" required>
            </div>
            <div class="form-group" style="align-self:end;">
                <button type="submit" name="send_test_email" value="1" class="btn btn-sm btn-success">Send test</button>
            </div>
        </form>
    <?php endif; ?>
</div>
