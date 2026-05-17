/**
 * Floor Plans gallery: upload dropzone, preview modal, drag cards to folders.
 */
(function () {
    const dropzone = document.getElementById('floorPlanDropzone');
    const fileInput = document.getElementById('galleryFilesInput');
    const modal = document.getElementById('floorPlanPreviewModal');
    const modalTitle = document.getElementById('floorPlanPreviewTitle');
    const modalBody = document.getElementById('floorPlanPreviewBody');
    const moveForm = document.getElementById('floorPlanMoveForm');
    const movePlanInput = document.getElementById('floorPlanMovePlanId');
    const moveFolderInput = document.getElementById('floorPlanMoveFolderId');

    if (dropzone && fileInput) {
        dropzone.addEventListener('dragover', function (event) {
            if (event.dataTransfer && event.dataTransfer.types && event.dataTransfer.types.indexOf('Files') !== -1) {
                event.preventDefault();
                dropzone.classList.add('is-dragover');
            }
        });
        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('is-dragover');
        });
        dropzone.addEventListener('drop', function (event) {
            if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
                event.preventDefault();
                dropzone.classList.remove('is-dragover');
                fileInput.files = event.dataTransfer.files;
            }
        });
        dropzone.addEventListener('click', function (event) {
            if (event.target === fileInput || event.target.closest('button') || event.target.closest('select')) {
                return;
            }
            fileInput.click();
        });
    }

    function closePreview() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        if (modalBody) {
            modalBody.innerHTML = '';
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openPreview(url, type, name) {
        if (!modal || !modalBody || !modalTitle) {
            return;
        }
        modalTitle.textContent = name || 'Preview';
        if (type === 'pdf') {
            modalBody.innerHTML = '<iframe class="itm-floor-plan-pdf-frame" src="' + escapeHtml(url) + '#view=FitH" title="PDF preview"></iframe>';
        } else if (type === 'cad' || type === 'download') {
            modalBody.innerHTML = '<p>Preview is not available for this file type.</p>'
                + '<p><a class="btn btn-primary" href="' + escapeHtml(url) + '" download>Download file</a></p>';
        } else {
            modalBody.innerHTML = '<img src="' + escapeHtml(url) + '" alt="">';
        }
        modal.hidden = false;
    }

    function clearDropTargets() {
        document.querySelectorAll('.itm-folder-drop-target.is-drop-hover').forEach(function (el) {
            el.classList.remove('is-drop-hover');
        });
    }

    function submitMove(planId, folderId) {
        if (!moveForm || !movePlanInput || !moveFolderInput) {
            return;
        }
        movePlanInput.value = String(planId);
        moveFolderInput.value = String(folderId);
        moveForm.submit();
    }

    document.querySelectorAll('.itm-floor-plan-card[draggable="true"]').forEach(function (card) {
        card.addEventListener('dragstart', function (event) {
            const planId = card.getAttribute('data-plan-id') || '';
            if (!planId) {
                return;
            }
            event.dataTransfer.setData('text/plain', planId);
            event.dataTransfer.effectAllowed = 'move';
            card.classList.add('is-dragging');
        });
        card.addEventListener('dragend', function () {
            card.classList.remove('is-dragging');
            clearDropTargets();
        });
    });

    document.querySelectorAll('.itm-folder-drop-target').forEach(function (target) {
        target.addEventListener('dragover', function (event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            target.classList.add('is-drop-hover');
        });
        target.addEventListener('dragleave', function () {
            target.classList.remove('is-drop-hover');
        });
        target.addEventListener('drop', function (event) {
            event.preventDefault();
            clearDropTargets();
            const planId = parseInt(event.dataTransfer.getData('text/plain'), 10);
            if (!planId) {
                return;
            }
            const folderId = parseInt(target.getAttribute('data-folder-drop-id') || '0', 10);
            submitMove(planId, folderId);
        });
    });

    document.addEventListener('click', function (event) {
        const closeBtn = event.target.closest('[data-close-preview="1"]');
        if (closeBtn) {
            closePreview();
            return;
        }
        const thumb = event.target.closest('.itm-floor-plan-thumb-link[data-preview-url]');
        if (thumb && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            openPreview(
                thumb.getAttribute('data-preview-url') || '',
                thumb.getAttribute('data-preview-type') || 'image',
                thumb.getAttribute('data-preview-name') || ''
            );
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePreview();
        }
    });
})();
