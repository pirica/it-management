/**
 * Floor Plans gallery: upload dropzone, preview modal, drag files/folders to folders.
 * Why: drag handles avoid native link-drag behavior on folder/file anchors.
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
    const moveFolderForm = document.getElementById('floorPlanMoveFolderForm');
    const moveFolderSourceInput = document.getElementById('floorPlanMoveFolderSourceId');
    const moveFolderParentInput = document.getElementById('floorPlanMoveFolderParentId');
    const folderDragMime = 'application/x-itm-floor-folder';
    const folderDragPlainPrefix = 'itm-folder:';
    let activeFolderDragId = 0;
    let activePlanDragId = 0;

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

    function submitFileMove(planId, folderId) {
        if (!moveForm || !movePlanInput || !moveFolderInput) {
            return;
        }
        movePlanInput.value = String(planId);
        moveFolderInput.value = String(folderId);
        moveForm.submit();
    }

    function submitFolderMove(folderId, parentId) {
        if (!moveFolderForm || !moveFolderSourceInput || !moveFolderParentInput) {
            return;
        }
        moveFolderSourceInput.value = String(folderId);
        moveFolderParentInput.value = parentId === null || parentId === undefined ? '' : String(parentId);
        moveFolderForm.submit();
    }

    function readFolderDragId(event) {
        if (activeFolderDragId > 0) {
            return activeFolderDragId;
        }
        if (!event.dataTransfer) {
            return 0;
        }
        let raw = event.dataTransfer.getData(folderDragMime) || '';
        if (!raw) {
            raw = event.dataTransfer.getData('text/plain') || '';
        }
        if (raw.indexOf(folderDragPlainPrefix) === 0) {
            const folderId = parseInt(raw.slice(folderDragPlainPrefix.length), 10);
            return folderId > 0 ? folderId : 0;
        }
        return 0;
    }

    function readFileDragId(event) {
        if (activePlanDragId > 0) {
            return activePlanDragId;
        }
        if (!event.dataTransfer) {
            return 0;
        }
        const raw = event.dataTransfer.getData('text/plain') || '';
        if (raw.indexOf(folderDragPlainPrefix) === 0) {
            return 0;
        }
        const planId = parseInt(raw, 10);
        return planId > 0 ? planId : 0;
    }

    function bindDragHandles() {
        document.querySelectorAll('.itm-plan-drag-handle[draggable="true"]').forEach(function (handle) {
            if (handle.getAttribute('data-itm-drag-bound') === '1') {
                return;
            }
            handle.setAttribute('data-itm-drag-bound', '1');
            handle.addEventListener('dragstart', function (event) {
                const planId = parseInt(handle.getAttribute('data-plan-id') || '0', 10);
                if (!planId || !event.dataTransfer) {
                    return;
                }
                activePlanDragId = planId;
                activeFolderDragId = 0;
                event.dataTransfer.setData('text/plain', String(planId));
                event.dataTransfer.effectAllowed = 'move';
                const card = handle.closest('.itm-floor-plan-card');
                if (card) {
                    card.classList.add('is-dragging');
                }
                event.stopPropagation();
            });
            handle.addEventListener('dragend', function () {
                activePlanDragId = 0;
                document.querySelectorAll('.itm-floor-plan-card.is-dragging').forEach(function (card) {
                    card.classList.remove('is-dragging');
                });
                clearDropTargets();
            });
        });

        document.querySelectorAll('.itm-folder-drag-handle[draggable="true"]').forEach(function (handle) {
            if (handle.getAttribute('data-itm-drag-bound') === '1') {
                return;
            }
            handle.setAttribute('data-itm-drag-bound', '1');
            handle.addEventListener('dragstart', function (event) {
                const folderId = parseInt(handle.getAttribute('data-folder-id') || '0', 10);
                if (!folderId || !event.dataTransfer) {
                    return;
                }
                activeFolderDragId = folderId;
                activePlanDragId = 0;
                const payload = folderDragPlainPrefix + String(folderId);
                event.dataTransfer.setData(folderDragMime, String(folderId));
                event.dataTransfer.setData('text/plain', payload);
                event.dataTransfer.effectAllowed = 'move';
                const row = handle.closest('.itm-folder-tree-folder');
                if (row) {
                    row.classList.add('is-dragging');
                }
                event.stopPropagation();
            });
            handle.addEventListener('dragend', function () {
                activeFolderDragId = 0;
                document.querySelectorAll('.itm-folder-tree-folder.is-dragging').forEach(function (row) {
                    row.classList.remove('is-dragging');
                });
                clearDropTargets();
            });
        });
    }

    function bindDropTargets() {
        document.querySelectorAll('.itm-folder-drop-target').forEach(function (target) {
            if (target.getAttribute('data-itm-drop-bound') === '1') {
                return;
            }
            target.setAttribute('data-itm-drop-bound', '1');
            target.addEventListener('dragover', function (event) {
                event.preventDefault();
                event.stopPropagation();
                if (event.dataTransfer) {
                    event.dataTransfer.dropEffect = 'move';
                }
                target.classList.add('is-drop-hover');
            });
            target.addEventListener('dragleave', function (event) {
                event.stopPropagation();
                target.classList.remove('is-drop-hover');
            });
            target.addEventListener('drop', function (event) {
                event.preventDefault();
                event.stopPropagation();
                clearDropTargets();

                const folderDragId = readFolderDragId(event);
                if (folderDragId) {
                    let parentId = null;
                    if (target.getAttribute('data-folder-reparent-root') === '1') {
                        parentId = null;
                    } else {
                        const targetFolderId = parseInt(target.getAttribute('data-folder-drop-id') || '0', 10);
                        if (targetFolderId <= 0) {
                            activeFolderDragId = 0;
                            return;
                        }
                        if (targetFolderId === folderDragId) {
                            activeFolderDragId = 0;
                            return;
                        }
                        parentId = targetFolderId;
                    }
                    activeFolderDragId = 0;
                    submitFolderMove(folderDragId, parentId);
                    return;
                }

                const planId = readFileDragId(event);
                if (!planId) {
                    return;
                }
                const unfiled = target.getAttribute('data-folder-drop-unfiled') === '1';
                const folderId = unfiled
                    ? 0
                    : parseInt(target.getAttribute('data-folder-drop-id') || '0', 10);
                activePlanDragId = 0;
                submitFileMove(planId, folderId);
            });
        });
    }

    bindDragHandles();
    bindDropTargets();

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
