(function () {
    if (window.ITM_SELECT_ADD_OPTION_INITIALIZED) {
        return;
    }
    window.ITM_SELECT_ADD_OPTION_INITIALIZED = true;

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
        };
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

    function openAddModal(selectEl, fields) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:9999;padding:16px;';

            const card = document.createElement('div');
            card.style.cssText = 'width:min(560px,100%);background:#fff;border-radius:8px;box-shadow:0 12px 30px rgba(0,0,0,.25);padding:18px;';
            overlay.appendChild(card);

            const title = selectEl.getAttribute('data-add-friendly') || 'value';
            const heading = document.createElement('h3');
            heading.textContent = 'Add New ' + title.charAt(0).toUpperCase() + title.slice(1);
            heading.style.margin = '0 0 12px 0';
            card.appendChild(heading);

            const form = document.createElement('form');
            card.appendChild(form);

            const allFields = [{ name: 'new_value', label: 'Name', type: 'text' }]
                .concat(fields.map(normalizeFieldConfig).filter((f) => f.name));

            allFields.forEach((field) => {
                const group = document.createElement('div');
                group.style.marginBottom = '10px';
                const label = document.createElement('label');
                label.textContent = field.label + ' *';
                label.style.cssText = 'display:block;font-weight:600;margin-bottom:4px;';
                group.appendChild(label);

                let input;
                if (field.type === 'select') {
                    input = document.createElement('select');
                    input.innerHTML = buildSelectOptionsHtml(field.options);
                } else {
                    input = document.createElement('input');
                    input.type = field.type === 'number' ? 'number' : 'text';
                    input.placeholder = 'Enter ' + field.label.toLowerCase();
                }

                input.required = true;
                input.name = field.name;
                input.style.cssText = 'width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;';
                group.appendChild(input);
                form.appendChild(group);
            });

            const actions = document.createElement('div');
            actions.style.cssText = 'display:flex;justify-content:flex-end;gap:8px;margin-top:8px;';
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
                    if (!payload[field.name]) {
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
        if (extraFields.length === 0) {
            const label = selectEl.getAttribute('data-add-friendly') || 'value';
            const typed = window.prompt('Add new ' + label + ':');
            if (typed === null) return null;
            const newValue = typed.trim();
            if (!newValue) {
                window.alert('Please type a value first.');
                return false;
            }
            return { new_value: newValue, extra_fields: {} };
        }

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

            selectEl.innerHTML = `${blankHtml}${optionHtml}<option value="${ADD_VALUE}">➕ Add</option>`;
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
