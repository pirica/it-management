/**
 * Webmail compose — Quill WYSIWYG; sync HTML to hidden field on submit.
 */
(function () {
    function readInitialHtml() {
        var node = document.getElementById('webmail-body-initial');
        if (!node || !node.textContent) {
            return '';
        }
        try {
            return JSON.parse(node.textContent);
        } catch (e) {
            return '';
        }
    }

    function init() {
        if (typeof window.Quill !== 'function') {
            return;
        }
        var form = document.getElementById('webmail-compose-form');
        var mount = document.getElementById('webmail-body-editor');
        var hidden = document.getElementById('webmail-body-html');
        if (!form || !mount || !hidden) {
            return;
        }

        var quill = new window.Quill(mount, {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ header: [1, 2, 3, false] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean'],
                ],
            },
            placeholder: 'Write your message…',
        });

        var initial = readInitialHtml();
        if (initial) {
            quill.clipboard.dangerouslyPasteHTML(initial);
        }

        form.addEventListener('submit', function () {
            var html = quill.root.innerHTML;
            if (html === '<p><br></p>' || html === '<p></p>') {
                html = '';
            }
            hidden.value = html;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
