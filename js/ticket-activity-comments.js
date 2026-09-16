(function () {
    'use strict';

    function initTicketActivityComments() {
        var form = document.getElementById('ticket-activity-comment-form');
        var feed = document.getElementById('ticket-activity-feed');
        var emptyState = document.getElementById('ticket-activity-empty');
        var errorBox = document.getElementById('ticket-activity-comment-error');
        if (!form || !feed) {
            return;
        }

        var apiUrl = form.getAttribute('data-api-url') || 'api.php';
        var ticketId = form.getAttribute('data-ticket-id') || '';

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (errorBox) {
                errorBox.style.display = 'none';
                errorBox.textContent = '';
            }

            var bodyField = form.querySelector('[name="comment_body"]');
            var photoField = form.querySelector('[name="comment_photo[]"]');
            var bodyValue = bodyField ? String(bodyField.value || '').trim() : '';
            var hasPhotos = photoField && photoField.files && photoField.files.length > 0;
            if (bodyValue === '' && !hasPhotos) {
                if (errorBox) {
                    errorBox.textContent = 'Enter a comment or attach a photo.';
                    errorBox.style.display = 'block';
                }
                return;
            }

            var formData = new FormData(form);
            formData.set('action', 'add_comment');
            formData.set('ticket_id', ticketId);
            if (window.ITM_CSRF_TOKEN) {
                formData.set('csrf_token', window.ITM_CSRF_TOKEN);
            }

            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            fetch(apiUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': window.ITM_CSRF_TOKEN || ''
                }
            })
                .then(function (response) {
                    return response.json().then(function (payload) {
                        return { ok: response.ok, payload: payload };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.payload || !result.payload.success) {
                        var message = (result.payload && result.payload.message) ? result.payload.message : 'Could not add comment.';
                        throw new Error(message);
                    }
                    if (emptyState) {
                        emptyState.style.display = 'none';
                    }
                    if (result.payload.html) {
                        feed.insertAdjacentHTML('beforeend', result.payload.html);
                    }
                    form.reset();
                })
                .catch(function (err) {
                    if (errorBox) {
                        errorBox.textContent = err && err.message ? err.message : 'Could not add comment.';
                        errorBox.style.display = 'block';
                    }
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTicketActivityComments);
    } else {
        initTicketActivityComments();
    }
})();
