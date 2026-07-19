(function () {
    function configValue(key, fallback) {
        const cfg = window.ITM_UI_CONFIG || {};
        const value = typeof cfg[key] === 'string' ? cfg[key] : '';
        return value || fallback;
    }

    function isBulkCheckboxColumn(cell) {
        if (!cell) return false;
        return !!cell.querySelector('input[type="checkbox"]');
    }

    // Why: bulk-select checkbox columns stay leftmost; Actions follows checkbox in Settings "left" mode.
    function placeActionCellAfterBulkCheckbox(row, actionCell) {
        if (!actionCell || actionCell.parentNode !== row) return;
        const first = row.firstElementChild;
        const anchor = (first && isBulkCheckboxColumn(first)) ? first.nextElementSibling : row.firstElementChild;
        if (actionCell !== anchor) {
            row.insertBefore(actionCell, anchor);
        }
    }

    function placeActionCellAtRowEnd(row, actionCell) {
        if (!actionCell || actionCell.parentNode !== row) return;
        if (row.lastElementChild !== actionCell) {
            row.appendChild(actionCell);
        }
    }

    function placeActionCloneBeforeBulkCheckbox(row, clone) {
        const first = row.firstElementChild;
        if (first && isBulkCheckboxColumn(first)) {
            row.insertBefore(clone, first);
            return;
        }
        row.insertBefore(clone, row.firstElementChild);
    }

    function applyTableActionsPosition() {
        const mode = configValue('table_actions_position', 'left_right');
        const tables = document.querySelectorAll('.content .card table');

        tables.forEach((table) => {
            const rows = Array.from(table.querySelectorAll('tr'));
            if (!rows.length) return;

            rows.forEach((row) => {
                row.querySelectorAll('[data-itm-actions-clone="1"]').forEach((clone) => clone.remove());
            });

            // Why: some partial tables (e.g. IT Locations linked floor plans) marked only the Actions header.
            const headerActionCell = table.querySelector('thead [data-itm-actions-origin="1"]');
            if (headerActionCell) {
                table.querySelectorAll('tbody tr').forEach((row) => {
                    if (row.querySelector('[data-itm-actions-origin="1"]')) {
                        return;
                    }
                    const actionTd = row.querySelector('td.itm-actions-cell');
                    if (actionTd) {
                        actionTd.dataset.itmActionsOrigin = '1';
                    }
                });
            }

            let actionCells = rows.map((row) => row.querySelector('[data-itm-actions-origin="1"]'));
            const hasMappedActionCells = actionCells.some((cell) => !!cell);

            if (!hasMappedActionCells) {
                const headers = Array.from(table.querySelectorAll('thead th'));
                const actionsIndex = headers.findIndex((th) => {
                    const txt = (th.textContent || '').trim().toLowerCase();
                    return txt === 'actions' || txt === 'action' || txt === 'table actions' || txt === 'options';
                });
                if (actionsIndex < 0) return;

                rows.forEach((row) => {
                    const cell = row.children[actionsIndex];
                    if (!cell) return;
                    cell.dataset.itmActionsOrigin = '1';
                });
                actionCells = rows.map((row) => row.querySelector('[data-itm-actions-origin="1"]'));
            }

            actionCells.forEach((cell) => {
                if (!cell) return;
                cell.classList.remove('itm-actions-left', 'itm-actions-right', 'itm-actions-left-right');
                cell.classList.add('itm-actions-cell');

                if (cell.tagName === 'TD') {
                    let wrap = cell.querySelector(':scope > .itm-actions-wrap');
                    if (!wrap) {
                        wrap = document.createElement('div');
                        wrap.className = 'itm-actions-wrap';
                        while (cell.firstChild) {
                            wrap.appendChild(cell.firstChild);
                        }
                        cell.appendChild(wrap);
                    }
                }
            });

            rows.forEach((row) => {
                const actionCell = row.querySelector('[data-itm-actions-origin="1"]');
                if (!actionCell) return;

                if (mode === 'left') {
                    actionCell.classList.add('itm-actions-left');
                    placeActionCellAfterBulkCheckbox(row, actionCell);
                    return;
                }

                if (mode === 'right') {
                    actionCell.classList.add('itm-actions-right');
                    placeActionCellAtRowEnd(row, actionCell);
                    return;
                }

                actionCell.classList.add('itm-actions-right');
                placeActionCellAtRowEnd(row, actionCell);

                // Why: left_right duplicates body action buttons only — keep a single Actions header.
                if (actionCell.tagName !== 'TD') {
                    return;
                }

                const leftClone = actionCell.cloneNode(true);
                leftClone.dataset.itmActionsClone = '1';
                leftClone.removeAttribute('data-itm-actions-origin');
                leftClone.classList.remove('itm-actions-right', 'itm-actions-left-right');
                leftClone.classList.add('itm-actions-left', 'itm-actions-cell');
                placeActionCloneBeforeBulkCheckbox(row, leftClone);
            });
        });
    }

    function moveNewButtons() {
        const mode = configValue('new_button_position', 'left_right');
        const bars = Array.from(document.querySelectorAll('.content > div, .content .card > div')).filter((bar) => {
            if (!(bar instanceof HTMLElement)) return false;
            const heading = bar.querySelector(':scope > h1, :scope > h2, :scope > h3');
            if (!heading) return false;
            const primaryLink = Array.from(bar.querySelectorAll(':scope > a.btn.btn-primary, :scope .btn.btn-primary')).find((link) => {
                if (!(link instanceof HTMLAnchorElement)) return false;
                const href = (link.getAttribute('href') || '').toLowerCase();
                const label = (link.textContent || '').toLowerCase();
                const looksLikeExport = href.includes('export=') || href.includes('download=') || label.includes('export') || label.includes('download');
                if (looksLikeExport) return false;
                return href.includes('create') || href.includes('new') || label.includes('new') || label.includes('add');
            });
            return !!primaryLink;
        });

        bars.forEach((bar, index) => {
            if (bar.dataset.itmNewButtonManaged === 'server') {
                return;
            }
            const heading = bar.querySelector(':scope > h1, :scope > h2, :scope > h3');
            const primaryLink = Array.from(bar.querySelectorAll(':scope > a.btn.btn-primary, :scope .btn.btn-primary')).find((link) => {
                if (!(link instanceof HTMLAnchorElement)) return false;
                const href = (link.getAttribute('href') || '').toLowerCase();
                const label = (link.textContent || '').toLowerCase();
                const looksLikeExport = href.includes('export=') || href.includes('download=') || label.includes('export') || label.includes('download');
                if (looksLikeExport) return false;
                return href.includes('create') || href.includes('new') || label.includes('new') || label.includes('add');
            });
            if (!heading || !primaryLink) return;

            bar.classList.add('itm-new-button-bar');
            const cloneClass = 'itm-new-btn-clone';
            bar.querySelectorAll('.' + cloneClass).forEach((el) => el.remove());

            if (mode === 'left') {
                bar.insertBefore(primaryLink, heading);
            } else if (mode === 'right') {
                bar.appendChild(primaryLink);
            } else {
                bar.appendChild(primaryLink);
                const cloned = primaryLink.cloneNode(true);
                cloned.classList.add(cloneClass);
                cloned.setAttribute('aria-label', 'New (left)');
                bar.insertBefore(cloned, heading);
            }

            bar.dataset.newButtonConfigured = '1-' + index;
        });
    }

    function detectFormActionRow(form) {
        // Why: only match the Save/Back bar itself. Ancestor wrappers (e.g. .card) also
        // contain submit+back via descendants; treating them as the action row adds
        // .itm-form-actions { display:flex } and flattens the entire create form.
        const backSelector = [
            'a.btn[href*="index.php"]',
            'a.btn[href$="index.php"]',
            'a.btn[href*="list"]',
            'a.btn[href*="view"]',
            'a.btn[href*="javascript:history.back"]',
            'button[type="button"]'
        ].join(', ');
        const submitSelector = 'button[type="submit"], input[type="submit"]';

        const isActionBar = (el) => {
            const children = Array.from(el.children);
            if (children.length === 0) {
                return false;
            }
            const hasSubmit = children.some((child) => child.matches(submitSelector));
            const hasBack = children.some((child) => child.matches(backSelector));
            return hasSubmit && hasBack;
        };

        const explicit = Array.from(form.querySelectorAll('.form-actions')).find(isActionBar);
        if (explicit) {
            return explicit;
        }

        return Array.from(form.querySelectorAll('div, p')).find(isActionBar) || null;
    }

    function makeRowClone(row) {
        const clone = row.cloneNode(true);
        clone.classList.add('itm-form-actions-clone');
        return clone;
    }

    function alignActionRow(row, align) {
        row.classList.add('itm-form-actions');
        row.classList.remove('itm-align-left', 'itm-align-right', 'itm-align-between');
        row.classList.add(align);
    }

    function applyBackSaveLayout() {
        const mode = configValue('back_save_position', 'left_right');
        const forms = document.querySelectorAll('.content form');

        forms.forEach((form) => {
            const actionRow = detectFormActionRow(form);
            if (!actionRow) return;

            form.querySelectorAll('.itm-form-actions-clone').forEach((clone) => clone.remove());

            if (mode === 'left') {
                alignActionRow(actionRow, 'itm-align-left');
                if (actionRow.parentNode !== form) form.appendChild(actionRow);
                return;
            }
            if (mode === 'right' || mode === 'bottom_right') {
                alignActionRow(actionRow, 'itm-align-right');
                if (actionRow.parentNode !== form) form.appendChild(actionRow);
                return;
            }
            if (mode === 'bottom_left') {
                alignActionRow(actionRow, 'itm-align-left');
                if (actionRow.parentNode !== form) form.appendChild(actionRow);
                return;
            }
            if (mode === 'top_right') {
                alignActionRow(actionRow, 'itm-align-right');
                form.insertBefore(actionRow, form.firstChild);
                return;
            }
            if (mode === 'top_left') {
                alignActionRow(actionRow, 'itm-align-left');
                form.insertBefore(actionRow, form.firstChild);
                return;
            }
            if (mode === 'top_bottom_right') {
                alignActionRow(actionRow, 'itm-align-right');
                if (actionRow.parentNode !== form) form.appendChild(actionRow);
                const topClone = makeRowClone(actionRow);
                alignActionRow(topClone, 'itm-align-right');
                form.insertBefore(topClone, form.firstChild);
                return;
            }
            if (mode === 'top_bottom_left') {
                alignActionRow(actionRow, 'itm-align-left');
                if (actionRow.parentNode !== form) form.appendChild(actionRow);
                const topClone = makeRowClone(actionRow);
                alignActionRow(topClone, 'itm-align-left');
                form.insertBefore(topClone, form.firstChild);
                return;
            }

            alignActionRow(actionRow, 'itm-align-between');
            if (actionRow.parentNode !== form) form.appendChild(actionRow);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        applyTableActionsPosition();
        moveNewButtons();
        applyBackSaveLayout();
    });
})();
