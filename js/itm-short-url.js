/**
 * Short URL module — paste, feature panels, copy helpers.
 */
(function () {
    'use strict';

    function copyText(text) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).catch(function () {});
            return;
        }
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) { /* ignore */ }
        document.body.removeChild(ta);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var pasteBtn = document.getElementById('su-paste-btn');
        var destInput = document.getElementById('su-destination-url');
        if (pasteBtn && destInput && navigator.clipboard && navigator.clipboard.readText) {
            pasteBtn.addEventListener('click', function () {
                navigator.clipboard.readText().then(function (t) {
                    if (t) destInput.value = t.trim();
                }).catch(function () {});
            });
        }

        document.addEventListener('click', function (event) {
            var copyBtn = event.target && event.target.closest ? event.target.closest('.su-copy-btn') : null;
            if (copyBtn) {
                copyText(copyBtn.getAttribute('data-copy') || '');
                return;
            }
            var card = event.target && event.target.closest ? event.target.closest('.su-feature-card') : null;
            if (!card) return;
            var panelId = 'su-panel-' + (card.getAttribute('data-su-panel') || '');
            var panel = document.getElementById(panelId);
            if (!panel) return;
            var open = panel.style.display === 'none';
            document.querySelectorAll('.su-option-panel').forEach(function (p) {
                p.style.display = 'none';
            });
            document.querySelectorAll('.su-feature-card').forEach(function (c) {
                c.classList.remove('active');
            });
            if (open) {
                panel.style.display = 'block';
                card.classList.add('active');
            }
        });

        var codeInput = document.getElementById('su-short-code');
        var suffixEl = document.getElementById('su-code-preview-suffix');
        if (codeInput && suffixEl) {
            var updatePreview = function () {
                suffixEl.textContent = codeInput.value.trim();
            };
            codeInput.addEventListener('input', updatePreview);
            updatePreview();
        }
    });
})();
