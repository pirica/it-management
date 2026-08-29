<?php
/** Link modal + staging AJAX script (shared). */
?>
<div id="nd-link-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
    <div class="card" style="max-width:480px;width:90%;padding:20px;">
        <h3 style="margin-top:0;">Link to equipment</h3>
        <div class="form-group">
            <label for="nd-link-equipment">Equipment</label>
            <select id="nd-link-equipment" style="width:100%;">
                <option value="">— Select —</option>
                <?php foreach ($equipmentOptions as $eqOpt): ?>
                    <option value="<?php echo (int)$eqOpt['id']; ?>">
                        <?php echo sanitize(trim((string)$eqOpt['name'] . ' ' . (string)($eqOpt['hostname'] ?? '') . ' ' . (string)($eqOpt['ip_address'] ?? ''))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <label class="itm-checkbox-control">
            <input type="checkbox" id="nd-link-ipam" value="1" checked>
            <span>Create IPAM row when missing</span>
        </label>
        <div style="display:flex;gap:8px;margin-top:16px;">
            <button type="button" class="btn btn-primary" id="nd-link-confirm" title="Save">💾</button>
            <button type="button" class="btn" id="nd-link-cancel" title="Cancel">🔙</button>
        </div>
    </div>
</div>

<script>
(function () {
    var csrf = <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE); ?>;
    var apiBase = <?php echo json_encode($ndApiBase, JSON_UNESCAPED_UNICODE); ?>;
    var linkModal = document.getElementById('nd-link-modal');
    var linkStagingId = 0;

    function postAction(action, payload) {
        var body = new FormData();
        body.append('csrf_token', csrf);
        body.append('action', action);
        Object.keys(payload).forEach(function (key) {
            body.append(key, payload[key]);
        });
        return fetch(apiBase, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); });
    }

    document.querySelectorAll('.nd-promote-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-staging-id');
            postAction('promote', { staging_id: id, create_ipam: '1' }).then(function (data) {
                if (data && data.success) {
                    window.location.reload();
                } else {
                    alert((data && data.message) || 'Promote failed.');
                }
            });
        });
    });

    document.querySelectorAll('.nd-dismiss-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-staging-id');
            postAction('dismiss', { staging_id: id }).then(function (data) {
                if (data && data.success) {
                    window.location.reload();
                } else {
                    alert((data && data.message) || 'Dismiss failed.');
                }
            });
        });
    });

    document.querySelectorAll('.nd-link-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            linkStagingId = btn.getAttribute('data-staging-id');
            var matched = btn.getAttribute('data-matched-eq');
            var select = document.getElementById('nd-link-equipment');
            if (matched && select) {
                select.value = matched;
            }
            linkModal.style.display = 'flex';
        });
    });

    var cancelBtn = document.getElementById('nd-link-cancel');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            linkModal.style.display = 'none';
        });
    }

    var confirmBtn = document.getElementById('nd-link-confirm');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            var eqId = document.getElementById('nd-link-equipment').value;
            var createIpam = document.getElementById('nd-link-ipam').checked ? '1' : '0';
            if (!eqId) {
                alert('Select equipment.');
                return;
            }
            postAction('link', { staging_id: linkStagingId, equipment_id: eqId, create_ipam: createIpam }).then(function (data) {
                if (data && data.success) {
                    window.location.reload();
                } else {
                    alert((data && data.message) || 'Link failed.');
                }
            });
        });
    }
})();
</script>
