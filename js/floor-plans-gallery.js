/**
 * Floor Plans gallery: upload dropzone, preview modal, drag files/folders to folders.
 * Why: custom MIME types and delegated drops avoid native image/link drag breaking file moves.
 */
(function () {
    const uploadForm = document.getElementById('floorPlanUploadForm');
    const uploadTarget = document.getElementById('floorPlanUploadTarget');
    const fileInput = document.getElementById('galleryFilesInput');
    const modal = document.getElementById('floorPlanPreviewModal');
    const modalTitle = document.getElementById('floorPlanPreviewTitle');
    const modalBody = document.getElementById('floorPlanPreviewBody');
    const modalActions = document.getElementById('floorPlanPreviewActions');
    const moveForm = document.getElementById('floorPlanMoveForm');
    const movePlanInput = document.getElementById('floorPlanMovePlanId');
    const moveFolderInput = document.getElementById('floorPlanMoveFolderId');
    const moveFolderForm = document.getElementById('floorPlanMoveFolderForm');
    const moveFolderSourceInput = document.getElementById('floorPlanMoveFolderSourceId');
    const moveFolderParentInput = document.getElementById('floorPlanMoveFolderParentId');
    const folderTree = document.querySelector('.itm-folder-tree');
    const planDragMime = 'application/x-itm-floor-plan';
    const folderDragMime = 'application/x-itm-floor-folder';
    const folderDragPlainPrefix = 'itm-folder:';
    let activeFolderDragId = 0;
    let activePlanDragId = 0;
    let dropHoverTarget = null;

    function isExternalFileDrag(event) {
        return !!(event.dataTransfer && event.dataTransfer.types && event.dataTransfer.types.indexOf('Files') !== -1);
    }

    if (uploadTarget && fileInput) {
        uploadTarget.addEventListener('dragover', function (event) {
            if (!isExternalFileDrag(event)) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            uploadTarget.classList.add('is-dragover');
        });
        uploadTarget.addEventListener("dragleave", function (event) {
            var related = event.relatedTarget;
            if (related && uploadTarget.contains(related)) {
                return;
            }
            uploadTarget.classList.remove("is-dragover");
        });
        uploadTarget.addEventListener("drop", function (event) {
            event.preventDefault();
            event.stopPropagation();
            uploadTarget.classList.remove("is-dragover");
            if (!isExternalFileDrag(event) || !event.dataTransfer.files || !event.dataTransfer.files.length) {
                return;
            }
            var transfer = new DataTransfer();
            if (fileInput.files) {
                Array.prototype.forEach.call(fileInput.files, function(file) {
                    transfer.items.add(file);
                });
            }
            Array.prototype.forEach.call(event.dataTransfer.files, function(file) {
                transfer.items.add(file);
            });
            fileInput.files = transfer.files;
            fileInput.dispatchEvent(new Event("change", { bubbles: true }));
        });
        uploadTarget.addEventListener('click', function (event) {
            if (event.target === fileInput) {
                return;
            }
            fileInput.click();
        });
        uploadTarget.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                fileInput.click();
            }
        });
    }

    if (uploadForm) {
        uploadForm.addEventListener('dragover', function (event) {
            if (isExternalFileDrag(event)) {
                event.preventDefault();
            }
        });
        uploadForm.addEventListener('drop', function (event) {
            if (!uploadTarget || uploadTarget.contains(event.target)) {
                return;
            }
            if (isExternalFileDrag(event)) {
                event.preventDefault();
                event.stopPropagation();
            }
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
        if (modalActions) {
            modalActions.innerHTML = '';
        }
    }



    function buildPreviewActions(url, downloadName) {
        const fragment = document.createDocumentFragment();
        const downloadBtn = document.createElement('a');
        downloadBtn.className = 'btn btn-sm btn-primary';
        downloadBtn.href = url;
        if (downloadName) {
            downloadBtn.setAttribute('download', downloadName);
        } else {
            downloadBtn.setAttribute('download', '');
        }
        downloadBtn.textContent = 'Download file';
        fragment.appendChild(downloadBtn);

        const openBtn = document.createElement('a');
        openBtn.className = 'btn btn-sm';
        openBtn.href = url;
        openBtn.target = '_blank';
        openBtn.rel = 'noopener';
        openBtn.textContent = 'Open in new tab';
        fragment.appendChild(openBtn);

        return fragment;
    }

    function openPreview(url, type, name, downloadName) {
        if (!modal || !modalBody || !modalTitle) {
            return;
        }
        modalTitle.textContent = name || 'Preview';

        if (modalActions) {
            modalActions.innerHTML = '';
            modalActions.appendChild(buildPreviewActions(url, downloadName));
        }

        modalBody.innerHTML = '';
        if (type === 'pdf') {
            const iframe = document.createElement('iframe');
            iframe.className = 'itm-floor-plan-pdf-frame';
            iframe.src = url + '#view=FitH';
            iframe.title = 'PDF preview';
            modalBody.appendChild(iframe);

            const hint = document.createElement('p');
            hint.className = 'itm-dropzone-hint';
            hint.style.marginTop = '8px';
            hint.textContent = 'Signed or protected PDFs may disable Save in the browser viewer; use ';
            const strong = document.createElement('strong');
            strong.textContent = 'Download file';
            hint.appendChild(strong);
            hint.appendChild(document.createTextNode(' above.'));
            modalBody.appendChild(hint);
        } else if (type === 'cad' || type === 'download') {
            const p = document.createElement('p');
            p.textContent = 'Preview is not available for this file type.';
            modalBody.appendChild(p);
        } else {
            const img = document.createElement('img');
            img.src = url;
            img.alt = name || 'Floor plan';
            modalBody.appendChild(img);
        }

        modal.hidden = false;
    }

    function clearDropTargets() {
        document.querySelectorAll('.itm-folder-drop-target.is-drop-hover').forEach(function (el) {
            el.classList.remove('is-drop-hover');
        });
        dropHoverTarget = null;
    }

    function setDropHover(target) {
        if (dropHoverTarget === target) {
            return;
        }
        clearDropTargets();
        dropHoverTarget = target;
        if (target) {
            target.classList.add('is-drop-hover');
        }
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

    function transferHasType(event, mime) {
        if (!event.dataTransfer || !event.dataTransfer.types) {
            return false;
        }
        const types = event.dataTransfer.types;
        for (let i = 0; i < types.length; i++) {
            if (types[i] === mime) {
                return true;
            }
        }
        return false;
    }

    function readFolderDragId(event) {
        if (activeFolderDragId > 0) {
            return activeFolderDragId;
        }
        if (!event.dataTransfer) {
            return 0;
        }
        if (transferHasType(event, planDragMime)) {
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
        if (transferHasType(event, folderDragMime)) {
            return 0;
        }
        let raw = event.dataTransfer.getData(planDragMime) || '';
        if (!raw) {
            raw = event.dataTransfer.getData('text/plain') || '';
        }
        if (raw.indexOf(folderDragPlainPrefix) === 0) {
            return 0;
        }
        const planId = parseInt(raw, 10);
        return planId > 0 ? planId : 0;
    }

    function beginPlanDrag(planId, event) {
        if (!planId || !event.dataTransfer) {
            return false;
        }
        activePlanDragId = planId;
        activeFolderDragId = 0;
        event.dataTransfer.setData(planDragMime, String(planId));
        event.dataTransfer.setData('text/plain', String(planId));
        event.dataTransfer.effectAllowed = 'move';
        const card = event.currentTarget && event.currentTarget.closest
            ? event.currentTarget.closest('.itm-floor-plan-card')
            : null;
        if (card) {
            card.classList.add('is-dragging');
        }
        return true;
    }

    function beginFolderDrag(folderId, event) {
        if (!folderId || !event.dataTransfer) {
            return false;
        }
        activeFolderDragId = folderId;
        activePlanDragId = 0;
        const payload = folderDragPlainPrefix + String(folderId);
        event.dataTransfer.setData(folderDragMime, String(folderId));
        event.dataTransfer.setData('text/plain', payload);
        event.dataTransfer.effectAllowed = 'move';
        const row = event.currentTarget && event.currentTarget.closest
            ? event.currentTarget.closest('.itm-folder-tree-folder')
            : null;
        if (row) {
            row.classList.add('is-dragging');
        }
        return true;
    }

    function resolveDropTarget(node) {
        if (!node || !node.closest) {
            return null;
        }
        return node.closest('.itm-folder-drop-target');
    }

    function handleFolderDrop(target, event) {
        const folderDragId = readFolderDragId(event);
        if (!folderDragId) {
            return false;
        }

        let parentId = null;
        if (target.getAttribute('data-folder-reparent-root') === '1') {
            parentId = null;
        } else {
            const targetFolderId = parseInt(target.getAttribute('data-folder-drop-id') || '0', 10);
            if (targetFolderId <= 0) {
                activeFolderDragId = 0;
                return true;
            }
            if (targetFolderId === folderDragId) {
                activeFolderDragId = 0;
                return true;
            }
            parentId = targetFolderId;
        }
        activeFolderDragId = 0;
        submitFolderMove(folderDragId, parentId);
        return true;
    }

    function handleFileDrop(target, event) {
        const planId = readFileDragId(event);
        if (!planId) {
            return false;
        }

        if (target.getAttribute('data-folder-reparent-root') === '1') {
            activePlanDragId = 0;
            return true;
        }

        const unfiled = target.getAttribute('data-folder-drop-unfiled') === '1';
        const folderId = unfiled
            ? 0
            : parseInt(target.getAttribute('data-folder-drop-id') || '0', 10);
        activePlanDragId = 0;
        submitFileMove(planId, folderId);
        return true;
    }

    function bindDragHandles() {
        document.querySelectorAll('.itm-floor-plan-card[data-plan-id]').forEach(function (card) {
            if (card.getAttribute('data-itm-card-drag-bound') === '1') {
                return;
            }
            card.setAttribute('data-itm-card-drag-bound', '1');
            card.setAttribute('draggable', 'true');

            card.addEventListener('dragstart', function (event) {
                if (event.target.closest('.itm-actions-wrap, form, button, input, select, textarea')) {
                    event.preventDefault();
                    return;
                }
                const planId = parseInt(card.getAttribute('data-plan-id') || '0', 10);
                if (!beginPlanDrag(planId, event)) {
                    event.preventDefault();
                    return;
                }
                event.stopPropagation();
            });
            card.addEventListener('dragend', function () {
                activePlanDragId = 0;
                card.classList.remove('is-dragging');
                clearDropTargets();
            });

            card.querySelectorAll('img').forEach(function (img) {
                img.setAttribute('draggable', 'false');
                img.addEventListener('dragstart', function (event) {
                    event.preventDefault();
                });
            });
        });

        document.querySelectorAll('.itm-plan-drag-handle').forEach(function (handle) {
            handle.setAttribute('draggable', 'false');
            handle.setAttribute('aria-grabbed', 'false');
        });

        document.querySelectorAll('.itm-folder-drag-handle[draggable="true"]').forEach(function (handle) {
            if (handle.getAttribute('data-itm-drag-bound') === '1') {
                return;
            }
            handle.setAttribute('data-itm-drag-bound', '1');
            handle.addEventListener('dragstart', function (event) {
                const folderId = parseInt(handle.getAttribute('data-folder-id') || '0', 10);
                if (!beginFolderDrag(folderId, event)) {
                    event.preventDefault();
                    return;
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
        if (!folderTree || folderTree.getAttribute('data-itm-drop-bound') === '1') {
            return;
        }
        folderTree.setAttribute('data-itm-drop-bound', '1');

        folderTree.addEventListener('dragover', function (event) {
            const target = resolveDropTarget(event.target);
            if (!target) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'move';
            }
            setDropHover(target);
        });

        folderTree.addEventListener('dragleave', function (event) {
            const target = resolveDropTarget(event.target);
            if (!target || !dropHoverTarget) {
                return;
            }
            const related = event.relatedTarget;
            if (related && dropHoverTarget.contains(related)) {
                return;
            }
            if (target === dropHoverTarget) {
                clearDropTargets();
            }
        });

        folderTree.addEventListener('drop', function (event) {
            const target = resolveDropTarget(event.target);
            if (!target) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            clearDropTargets();

            if (handleFolderDrop(target, event)) {
                return;
            }
            handleFileDrop(target, event);
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
                thumb.getAttribute('data-preview-name') || '',
                thumb.getAttribute('data-preview-download-name') || ''
            );
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePreview();
        }
    });
})();
