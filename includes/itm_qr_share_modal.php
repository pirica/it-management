<?php
/**
 * QR share modal markup + JS (requires js/qrcode.min.js and window.ITM_CSRF_TOKEN).
 */
?>
<div class="modal" id="itmShareQrModal" tabindex="-1" role="dialog" style="display:none;position:fixed;inset:0;z-index:1050;overflow:auto;">
    <div class="modal-dialog" role="document" style="position:relative;width:auto;margin:1.75rem auto;max-width:520px;pointer-events:none;">
        <div class="modal-content" style="pointer-events:auto;background:var(--bg-primary);border:1px solid var(--border);border-radius:8px;">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem;border-bottom:1px solid var(--border);">
                <h5 class="modal-title" style="margin:0;">Share to device</h5>
                <button type="button" class="close" onclick="itmCloseQrShareModal()" style="background:transparent;border:0;font-size:1.5rem;cursor:pointer;">&times;</button>
            </div>
            <div class="modal-body" style="padding:1.5rem;text-align:center;">
                <p style="margin-top:0;">Scan on the other device or enter the code on the join page.</p>
                <div id="itmShareQrMount" style="display:inline-block;margin:12px auto;"></div>
                <div id="itmShareQrCodeText" style="font-size:28px;letter-spacing:0.35em;font-weight:700;margin:12px 0;"></div>
                <div id="itmShareQrJoinUrl" style="font-size:13px;color:var(--text-secondary);word-break:break-all;"></div>
                <p id="itmShareQrExpiry" style="margin-top:16px;color:var(--text-secondary);"></p>
            </div>
            <div class="modal-footer" style="padding:1rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;">
                <button type="button" class="btn" onclick="itmCloseQrShareModal()">Done</button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop" id="itmShareQrBackdrop" style="display:none;position:fixed;inset:0;background:#000;opacity:.5;z-index:1040;"></div>
<script src="../../js/qrcode.min.js"></script>
<script src="../../js/itm-qr-share.js"></script>
<script src="../../js/itm-share-no-attachments-prompt.js"></script>
<script src="../../js/itm-whatsapp-share.js"></script>
<script src="../../js/itm-outlook-share.js"></script>
