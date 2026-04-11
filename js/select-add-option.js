(function () {
    if (window.ITM_SELECT_ADD_OPTION_INITIALIZED) {
        return;
    }
    window.ITM_SELECT_ADD_OPTION_INITIALIZED = true;

    const ADD_VALUE = '__add_new__';
    let modalStylesInjected = false;

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
        return base.replace(/\/?$/, '/') + 'modules/select_options_api.php';
    }

    function injectAddOption(selectEl) {
        const has = Array.from(selectEl.options).some((opt) => opt.value === ADD_VALUE);
        if (has) return;

        const option = document.createElement('option');
        option.value = ADD_VALUE;
        option.textContent = '➕ Add';
        selectEl.appendChild(option);
    }

    function parseExtraFieldConfig(selectEl) {
        const raw = selectEl.getAttribute('data-add-extra-fields');
        if (!raw) return [];
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function normalizeFieldConfig(field) {
        return {
            name: String(field.name || '').trim(),
            label: String(field.label || field.name || 'Field').trim(),
            type: String(field.type || 'text').trim().toLowerCase(),
            options: Array.isArray(field.options) ? field.options : [],
            required: field.required !== false,
            required_when: (field && typeof field.required_when === 'object' && field.required_when !== null) ? field.required_when : null,
        };
    }

    function isFieldRequired(field, payload) {
        if (!field.required_when) return field.required !== false;

        const refField = String(field.required_when.field || '').trim();
        if (!refField) return field.required !== false;

        const actual = String(payload[refField] || '').trim();
        if (Object.prototype.hasOwnProperty.call(field.required_when, 'equals')) {
            return actual === String(field.required_when.equals || '').trim();
        }

        if (Array.isArray(field.required_when.in)) {
            const validValues = field.required_when.in.map((value) => String(value).trim());
            return validValues.includes(actual);
        }

        return field.required !== false;
    }

    function buildSelectOptionsHtml(options) {
        const normalized = options.map((opt) => {
            if (typeof opt === 'string' || typeof opt === 'number') {
                return { value: String(opt), label: String(opt) };
            }
            return {
                value: String(opt.value || ''),
                label: String(opt.label || opt.value || ''),
            };
        });
        const optionHtml = normalized.map((opt) => (
            `<option value="${escapeHtml(opt.value)}">${escapeHtml(opt.label)}</option>`
        )).join('');
        return `<option value="">-- Select --</option>${optionHtml}`;
    }

    function ensureModalStyles() {
        if (modalStylesInjected) return;
        modalStylesInjected = true;

        const style = document.createElement('style');
        style.id = 'itm-select-add-option-modal-styles';
        style.textContent = `
            .itm-add-option-overlay{
                position:fixed;
                inset:0;
                display:flex;
                align-items:center;
                justify-content:center;
                z-index:9999;
                padding:16px;
                background:rgba(0,0,0,.45);
                backdrop-filter: blur(2px);
            }
            .itm-add-option-card{
                width:min(560px,100%);
                border:1px solid var(--border);
                border-radius:12px;
                background:var(--bg-primary);
                color:var(--text-primary);
                box-shadow:var(--shadow-lg);
                padding:18px;
            }
            .itm-add-option-heading{
                margin:0 0 12px 0;
                color:var(--text-primary);
            }
            .itm-add-option-group{
                margin-bottom:10px;
            }
            .itm-add-option-label{
                display:block;
                font-weight:600;
                margin-bottom:4px;
                color:var(--text-primary);
            }
            .itm-add-option-input{
                width:100%;
                padding:8px;
                border:1px solid var(--border);
                border-radius:6px;
                background-color:var(--bg-primary);
                color:var(--text-primary);
            }
            input[type="color"].itm-add-option-input{
                padding:4px;
                min-height:38px;
            }
            .itm-add-option-actions{
                display:flex;
                justify-content:flex-end;
                gap:8px;
                margin-top:8px;
            }
            .itm-add-option-actions button{
                border:1px solid var(--border);
                border-radius:6px;
                padding:8px 12px;
                font-size:14px;
                cursor:pointer;
                background:var(--bg-secondary);
                color:var(--text-primary);
            }
            .itm-add-option-actions button[type="submit"]{
                border-color:var(--accent);
                background:var(--accent);
                color:#fff;
            }
        `;
        document.head.appendChild(style);
    }

    function openAddModal(selectEl, fields) {
        return new Promise((resolve) => {
            ensureModalStyles();
            const overlay = document.createElement('div');
            overlay.className = 'itm-add-option-overlay';

            const card = document.createElement('div');
            card.className = 'itm-add-option-card';
            overlay.appendChild(card);

            const title = selectEl.getAttribute('data-add-friendly') || 'value';
            const heading = document.createElement('h3');
            heading.textContent = 'Add New ' + title.charAt(0).toUpperCase() + title.slice(1);
            heading.className = 'itm-add-option-heading';
            card.appendChild(heading);

            const form = document.createElement('form');
            card.appendChild(form);

            const allFields = [{ name: 'new_value', label: 'Name', type: 'text', required: true, required_when: null }]
                .concat(fields.map(normalizeFieldConfig).filter((f) => f.name));

            const renderedFields = [];
            allFields.forEach((field) => {
                const group = document.createElement('div');
                group.className = 'itm-add-option-group';
                const label = document.createElement('label');
                label.className = 'itm-add-option-label';
                group.appendChild(label);

                let input;
                if (field.type === 'select') {
                    input = document.createElement('select');
                    input.innerHTML = buildSelectOptionsHtml(field.options);
                } else {
                    input = document.createElement('input');
                    if (field.type === 'number') {
                        input.type = 'number';
                        input.placeholder = 'Enter ' + field.label.toLowerCase();
                    } else if (field.type === 'color') {
                        input.type = 'color';
                        input.value = '#000000';
                    } else {
                        input.type = 'text';
                        input.placeholder = 'Enter ' + field.label.toLowerCase();
                    }
                }

                input.required = field.required !== false && !field.required_when;
                input.name = field.name;
                input.className = 'itm-add-option-input';
                group.appendChild(input);
                form.appendChild(group);
                renderedFields.push({ field, group, label, input });
            });

            function refreshConditionalFields() {
                const payload = {};
                renderedFields.forEach(({ input }) => {
                    payload[input.name] = String(input.value || '').trim();
                });

                renderedFields.forEach(({ field, group, label, input }) => {
                    const requiredNow = isFieldRequired(field, payload);
                    const hasCondition = !!field.required_when;
                    const shouldShow = !hasCondition || requiredNow || String(input.value || '').trim() !== '';
                    group.style.display = shouldShow ? '' : 'none';
                    input.required = requiredNow;
                    label.textContent = field.label + (requiredNow ? ' *' : '');
                });
            }

            renderedFields.forEach(({ input }) => {
                input.addEventListener('change', refreshConditionalFields);
                input.addEventListener('input', refreshConditionalFields);
            });
            refreshConditionalFields();

            const actions = document.createElement('div');
            actions.className = 'itm-add-option-actions';
            actions.innerHTML = '<button type="button" data-cancel="1">Cancel</button><button type="submit">Save</button>';
            form.appendChild(actions);

            function close(result) {
                overlay.remove();
                resolve(result);
            }

            actions.querySelector('[data-cancel="1"]').addEventListener('click', () => close(null));
            overlay.addEventListener('click', (event) => {
                if (event.target === overlay) close(null);
            });
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const formData = new FormData(form);
                const payload = {};
                for (const [key, value] of formData.entries()) {
                    payload[key] = String(value || '').trim();
                }
                if (!payload.new_value) {
                    window.alert('Please type a value first.');
                    return;
                }
                for (const field of allFields) {
                    const requiredNow = isFieldRequired(field, payload);
                    if (requiredNow && !payload[field.name]) {
                        window.alert(field.label + ' is required.');
                        return;
                    }
                }
                close(payload);
            });

            document.body.appendChild(overlay);
            const firstInput = form.querySelector('input,select');
            if (firstInput) firstInput.focus();
        });
    }

    async function collectInput(selectEl) {
        const extraFields = parseExtraFieldConfig(selectEl);
        const result = await openAddModal(selectEl, extraFields);
        if (result === null) return null;
        const extraPayload = {};
        Object.keys(result).forEach((key) => {
            if (key !== 'new_value') extraPayload[key] = result[key];
        });
        return { new_value: result.new_value, extra_fields: extraPayload };
    }

    async function addValue(selectEl) {
        const userInput = await collectInput(selectEl);
        if (userInput === null) {
            selectEl.value = selectEl.dataset.previousValue || '';
            return;
        }
        if (userInput === false) {
            selectEl.value = selectEl.dataset.previousValue || '';
            return;
        }

        selectEl.disabled = true;

        const payload = new URLSearchParams({
            table: selectEl.getAttribute('data-add-table') || '',
            id_col: selectEl.getAttribute('data-add-id-col') || 'id',
            label_col: selectEl.getAttribute('data-add-label-col') || 'name',
            company_scoped: selectEl.getAttribute('data-add-company-scoped') || '0',
            new_value: userInput.new_value,
            extra_fields: JSON.stringify(userInput.extra_fields || {}),
        });

        try {
            const csrfToken = window.ITM_CSRF_TOKEN || '';
            if (!csrfToken) {
                throw new Error('Missing CSRF token.');
            }

            payload.append('csrf_token', csrfToken);

            const response = await fetch(endpointFor(selectEl), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-CSRF-Token': csrfToken,
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
        //    selectEl.innerHTML = `${blankHtml}${optionHtml}<option value="${ADD_VALUE}">➕</option>`;
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
