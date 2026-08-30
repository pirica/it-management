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

    function showPasteHint(input) {
        var existing = document.getElementById('su-paste-hint');
        if (existing) {
            existing.remove();
        }
        var hint = document.createElement('small');
        hint.id = 'su-paste-hint';
        hint.className = 'text-muted';
        hint.style.display = 'block';
        hint.style.marginTop = '6px';
        hint.textContent = 'Clipboard access is unavailable here. Click the URL field and press Ctrl+V (Cmd+V on Mac).';
        if (input.parentNode) {
            input.parentNode.appendChild(hint);
        }
        window.setTimeout(function () {
            if (hint.parentNode) {
                hint.parentNode.removeChild(hint);
            }
        }, 5000);
    }

    function applyPastedText(input, text) {
        if (!text) return false;
        input.value = String(text).trim();
        if (typeof Event === 'function') {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        return true;
    }

    function tryLegacyPaste(input) {
        input.focus();
        try {
            if (document.queryCommandSupported && document.queryCommandSupported('paste')) {
                return document.execCommand('paste');
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    function pasteIntoInput(input) {
        input.focus();
        if (window.isSecureContext && navigator.clipboard && typeof navigator.clipboard.readText === 'function') {
            return navigator.clipboard.readText().then(function (text) {
                if (applyPastedText(input, text)) {
                    return true;
                }
                if (tryLegacyPaste(input) && input.value.trim() !== '') {
                    return true;
                }
                showPasteHint(input);
                return false;
            }).catch(function () {
                if (tryLegacyPaste(input) && input.value.trim() !== '') {
                    return true;
                }
                showPasteHint(input);
                return false;
            });
        }
        if (tryLegacyPaste(input) && input.value.trim() !== '') {
            return Promise.resolve(true);
        }
        showPasteHint(input);
        return Promise.resolve(false);
    }

    function initShortUrlUi() {
        var pasteBtn = document.getElementById('su-paste-btn');
        var destInput = document.getElementById('su-destination-url');
        if (pasteBtn && destInput) {
            pasteBtn.addEventListener('click', function (event) {
                event.preventDefault();
                pasteIntoInput(destInput);
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initShortUrlUi);
    } else {
        initShortUrlUi();
    }
})();
