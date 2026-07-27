<?php
/**
 * Compose message preview modal (read-pane layout).
 */
?>
<div class="modal" id="webmail-compose-preview-modal" tabindex="-1" role="dialog" aria-hidden="true" style="display:none;position:fixed;inset:0;z-index:1050;overflow:auto;">
    <div class="modal-dialog" role="document" style="position:relative;width:auto;margin:1.75rem auto;max-width:720px;pointer-events:none;">
        <div class="modal-content" style="pointer-events:auto;background:var(--bg-primary);border:1px solid var(--border);border-radius:8px;overflow:hidden;">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem;border-bottom:1px solid var(--border);">
                <h5 class="modal-title" style="margin:0;" title="Preview message">🔎</h5>
                <button type="button" class="close webmail-compose-preview-close" style="background:transparent;border:0;font-size:1.5rem;cursor:pointer;" title="Cancel">&times;</button>
            </div>
            <div class="modal-body" style="padding:0;">
                <header class="webmail-read-header" style="padding:20px 20px 16px;border-bottom:1px solid var(--border);">
                    <h2 class="webmail-read-subject" id="webmail-compose-preview-subject"></h2>
                    <div class="webmail-read-meta">
                        <div class="webmail-read-addresses">
                            <div class="webmail-read-line">
                                <span class="webmail-read-label">From</span>
                                <span class="webmail-read-value" id="webmail-compose-preview-from"></span>
                            </div>
                            <div class="webmail-read-line">
                                <span class="webmail-read-label">To</span>
                                <span class="webmail-read-value" id="webmail-compose-preview-to"></span>
                            </div>
                            <div class="webmail-read-line" id="webmail-compose-preview-cc-row" style="display:none;">
                                <span class="webmail-read-label">CC</span>
                                <span class="webmail-read-value" id="webmail-compose-preview-cc"></span>
                            </div>
                        </div>
                    </div>
                </header>
                <div class="webmail-read-body" id="webmail-compose-preview-body-wrap">
                    <div class="webmail-body-view" id="webmail-compose-preview-body"></div>
                </div>
            </div>
            <div class="modal-footer" style="padding:1rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;">
                <button type="button" class="btn webmail-compose-preview-close" title="Cancel">🔙</button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop" id="webmail-compose-preview-backdrop" style="display:none;position:fixed;inset:0;background:#000;opacity:.5;z-index:1040;"></div>
