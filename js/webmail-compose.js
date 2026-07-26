/**
 * Webmail compose — sync contenteditable body to hidden field before submit.
 */
(function () {
    function init() {
        var form = document.getElementById('webmail-compose-form');
        var editor = document.getElementById('webmail-body-editor');
        var hidden = document.getElementById('webmail-body-html');
        if (!form || !editor || !hidden) {
            return;
        }
        form.addEventListener('submit', function () {
            hidden.value = editor.innerHTML;
        });
        document.querySelectorAll('[data-webmail-cmd]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var cmd = btn.getAttribute('data-webmail-cmd');
                if (!cmd) {
                    return;
                }
                editor.focus();
                if (cmd === 'createLink') {
                    var url = window.prompt('URL');
                    if (url) {
                        document.execCommand(cmd, false, url);
                    }
                } else {
                    document.execCommand(cmd, false, null);
                }
            });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
