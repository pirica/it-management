(function () {
    function configValue(key, fallback) {
        const cfg = window.ITM_UI_CONFIG || {};
        const value = typeof cfg[key] === 'string' ? cfg[key] : '';
        return value || fallback;
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
                    row.insertBefore(actionCell, row.firstElementChild);
                    return;
                }

                if (mode === 'right') {
                    actionCell.classList.add('itm-actions-right');
                    row.appendChild(actionCell);
                    return;
                }

                actionCell.classList.add('itm-actions-right');
                row.appendChild(actionCell);

                const leftClone = actionCell.cloneNode(true);
                leftClone.dataset.itmActionsClone = '1';
                leftClone.removeAttribute('data-itm-actions-origin');
                leftClone.classList.remove('itm-actions-right', 'itm-actions-left-right');
                leftClone.classList.add('itm-actions-left');
                row.insertBefore(leftClone, row.firstElementChild);
            });
        });
    }

    function moveNewButtons() {
        const mode = configValue('new_button_position', 'left_right');
        const bars = Array.from(document.querySelectorAll('.content > div, .content .card > div')).filter((bar) => {
            if (!(bar instanceof HTMLElement)) return false;
            const heading = bar.querySelector(':scope > h1, :scope > h2, :scope > h3');
            if (!heading) return false;
            const primaryLink = bar.querySelector(':scope > a.btn.btn-primary, :scope .btn.btn-primary[href*=\"create\"], :scope .btn.btn-primary[href*=\"new\"]');
            return !!primaryLink;
        });

        bars.forEach((bar, index) => {
            if (bar.dataset.itmNewButtonManaged === 'server') {
                return;
            }
            const heading = bar.querySelector(':scope > h1, :scope > h2, :scope > h3');
            const primaryLink = bar.querySelector(':scope > a.btn.btn-primary, :scope .btn.btn-primary[href*=\"create\"], :scope .btn.btn-primary[href*=\"new\"]');
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
        return Array.from(form.querySelectorAll('div, p')).find((el) => {
            const hasSubmit = !!el.querySelector('button[type="submit"], input[type="submit"]');
            const hasBack = !!el.querySelector('a.btn[href*="index.php"], a.btn[href$="index.php"], a.btn[href*="list"], a.btn[href*="view"], a.btn[href*="javascript:history.back"], button[type="button"]');
            return hasSubmit && hasBack;
        }) || null;
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
