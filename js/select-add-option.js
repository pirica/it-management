(function () {
    const ADD_VALUE = '__add_new__';

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function endpointFor(selectEl) {
        const custom = selectEl.getAttribute('data-add-endpoint');
        if (custom) return custom;

        const base = window.ITM_BASE_URL || '/';
        return base.replace(/\/?$/, '/') + 'modules/_shared/select_options_api.php';
    }

    function injectAddOption(selectEl) {
        const has = Array.from(selectEl.options).some((opt) => opt.value === ADD_VALUE);
        if (has) return;

        const option = document.createElement('option');
        option.value = ADD_VALUE;
        option.textContent = '+ Add';
        selectEl.appendChild(option);
    }

    async function addValue(selectEl) {
        const label = selectEl.getAttribute('data-add-friendly') || 'value';
        const typed = window.prompt('Add new ' + label + ':');

        if (typed === null) {
            selectEl.value = selectEl.dataset.previousValue || '';
            return;
        }

        const newValue = typed.trim();
        if (!newValue) {
            window.alert('Please type a value first.');
            selectEl.value = selectEl.dataset.previousValue || '';
            return;
        }

        selectEl.disabled = true;

        const payload = new URLSearchParams({
            table: selectEl.getAttribute('data-add-table') || '',
            id_col: selectEl.getAttribute('data-add-id-col') || 'id',
            label_col: selectEl.getAttribute('data-add-label-col') || 'name',
            company_scoped: selectEl.getAttribute('data-add-company-scoped') || '0',
            new_value: newValue,
        });

        try {
            const response = await fetch(endpointFor(selectEl), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: payload.toString(),
            });

            const result = await response.json();
            if (!response.ok || !result.ok) {
                throw new Error(result.error || 'Unable to add new option.');
            }

            const blankOption = Array.from(selectEl.options).find((opt) => opt.value === '');
            const blankHtml = blankOption ? `<option value="">${escapeHtml(blankOption.textContent)}</option>` : '';
            const optionHtml = (result.options || []).map((opt) => {
                return `<option value="${escapeHtml(opt.id)}">${escapeHtml(opt.label)}</option>`;
            }).join('');

            selectEl.innerHTML = `${blankHtml}${optionHtml}<option value="${ADD_VALUE}">+ Add</option>`;
            selectEl.value = String(result.selected_id);
            selectEl.dataset.previousValue = String(result.selected_id);
        } catch (error) {
            window.alert(error.message || 'Could not add the value right now.');
            selectEl.value = selectEl.dataset.previousValue || '';
        } finally {
            selectEl.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const selects = document.querySelectorAll('select[data-addable-select="1"]');
        selects.forEach((selectEl) => {
            injectAddOption(selectEl);
            selectEl.dataset.previousValue = selectEl.value || '';

            selectEl.addEventListener('focus', function () {
                if (selectEl.value !== ADD_VALUE) {
                    selectEl.dataset.previousValue = selectEl.value || '';
                }
            });

            selectEl.addEventListener('change', function () {
                if (selectEl.value === ADD_VALUE) {
                    addValue(selectEl);
                    return;
                }
                selectEl.dataset.previousValue = selectEl.value || '';
            });
        });
    });
})();
