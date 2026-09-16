<?php
/**
 * Webmail signature create/edit and delete confirm modals.
 *
 * Expects: $csrfToken, $signatureFormAction (default signatures.php), $signatureReturnTo (optional: compose).
 */
$signatureFormAction = isset($signatureFormAction) ? (string)$signatureFormAction : 'signatures.php';
$signatureReturnTo = isset($signatureReturnTo) ? trim((string)$signatureReturnTo) : '';
?>
<div class="modal" id="webmail-signature-save-modal" tabindex="-1" role="dialog" aria-hidden="true" style="display:none;position:fixed;inset:0;z-index:1050;overflow:auto;">
    <div class="modal-dialog" role="document" style="position:relative;width:auto;margin:1.75rem auto;max-width:640px;pointer-events:none;">
        <div class="modal-content" style="pointer-events:auto;background:var(--bg-primary);border:1px solid var(--border);border-radius:8px;">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem;border-bottom:1px solid var(--border);">
                <h5 class="modal-title" id="webmail-signature-save-title" style="margin:0;" title="Create signature">➕</h5>
                <button type="button" class="close webmail-signature-modal-close" data-webmail-signature-modal="save" style="background:transparent;border:0;font-size:1.5rem;cursor:pointer;" title="Cancel">&times;</button>
            </div>
            <form id="webmail-signature-save-form" method="POST" action="<?php echo sanitize($signatureFormAction); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <input type="hidden" name="signature_id" id="webmail-signature-id" value="">
                <input type="hidden" name="signature_html" id="webmail-signature-html-hidden" value="">
                <?php if ($signatureReturnTo !== ''): ?>
                    <input type="hidden" name="return_to" value="<?php echo sanitize($signatureReturnTo); ?>">
                <?php endif; ?>
                <div class="modal-body" style="padding:1rem;">
                    <div class="form-group">
                        <label for="webmail-signature-name">Name</label>
                        <input type="text" name="name" id="webmail-signature-name" class="form-control" maxlength="255" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="webmail-signature-editor">Signature</label>
                        <div class="webmail-quill-wrap">
                            <div id="webmail-signature-editor"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding:1rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" class="btn webmail-signature-modal-close" data-webmail-signature-modal="save" title="Cancel">🔙</button>
                    <button type="submit" class="btn btn-primary" id="webmail-signature-save-submit" name="save_signature" value="1" title="Save">💾</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal" id="webmail-signature-delete-modal" tabindex="-1" role="dialog" aria-hidden="true" style="display:none;position:fixed;inset:0;z-index:1050;overflow:auto;">
    <div class="modal-dialog" role="document" style="position:relative;width:auto;margin:1.75rem auto;max-width:480px;pointer-events:none;">
        <div class="modal-content" style="pointer-events:auto;background:var(--bg-primary);border:1px solid var(--border);border-radius:8px;">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem;border-bottom:1px solid var(--border);">
                <h5 class="modal-title" style="margin:0;" title="Confirm delete">🗑️</h5>
                <button type="button" class="close webmail-signature-modal-close" data-webmail-signature-modal="delete" style="background:transparent;border:0;font-size:1.5rem;cursor:pointer;" title="Cancel">&times;</button>
            </div>
            <form id="webmail-signature-delete-form" method="POST" action="<?php echo sanitize($signatureFormAction); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
                <input type="hidden" name="signature_id" id="webmail-signature-delete-id" value="">
                <input type="hidden" name="delete_signature" value="1">
                <?php if ($signatureReturnTo !== ''): ?>
                    <input type="hidden" name="return_to" value="<?php echo sanitize($signatureReturnTo); ?>">
                <?php endif; ?>
                <div class="modal-body" style="padding:1rem;">
                    <p style="margin:0;">Delete signature <strong id="webmail-signature-delete-label"></strong>?</p>
                </div>
                <div class="modal-footer" style="padding:1rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" class="btn webmail-signature-modal-close" data-webmail-signature-modal="delete" title="Cancel">🔙</button>
                    <button type="submit" class="btn btn-danger" title="Delete">🗑️</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal-backdrop" id="webmail-signature-backdrop" style="display:none;position:fixed;inset:0;background:#000;opacity:.5;z-index:1040;"></div>
