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
            const headers = Array.from(table.querySelectorAll('thead th'));
            const actionsIndex = headers.findIndex((th) => (th.textContent || '').trim().toLowerCase() === 'actions');
            if (actionsIndex < 0) return;

            table.querySelectorAll('tr').forEach((row) => {
                const cell = row.children[actionsIndex];
                if (!cell) return;
                cell.classList.remove('itm-actions-left', 'itm-actions-right', 'itm-actions-left-right');
                cell.classList.add('itm-actions-cell');
                if (mode === 'left') {
                    cell.classList.add('itm-actions-left');
                } else if (mode === 'right') {
                    cell.classList.add('itm-actions-right');
                } else {
                    cell.classList.add('itm-actions-left-right');
                }
            });
        });
    }

    function moveNewButtons() {
        const mode = configValue('new_button_position', 'left_right');
        const bars = document.querySelectorAll('.content > div[style*="justify-content:space-between"]');

        bars.forEach((bar, index) => {
            const heading = bar.querySelector('h1');
            const primaryLink = bar.querySelector('a.btn.btn-primary');
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
            const hasBack = !!el.querySelector('a.btn[href*="index.php"], a.btn[href$="index.php"]');
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
