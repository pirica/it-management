(function () {
    function sanitizeFilename(value) {
        return (value || 'table')
            .toString()
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '') || 'table';
    }

    function getCellText(cell) {
        return (cell && cell.textContent ? cell.textContent : '').replace(/\s+/g, ' ').trim();
    }

    function tableMeta(table) {
        const heading = table.closest('.content')?.querySelector('h1');
        const filenameBase = sanitizeFilename(heading ? heading.textContent : document.title);
        const headers = Array.from(table.querySelectorAll('thead th')).map(getCellText);
        const actionsIndex = headers.findIndex((h) => h.toLowerCase() === 'actions');
        return { filenameBase, headers, actionsIndex };
    }

    function cloneTableWithoutActions(table) {
        const { actionsIndex } = tableMeta(table);
        const clone = table.cloneNode(true);

        if (actionsIndex >= 0) {
            clone.querySelectorAll('tr').forEach((row) => {
                if (row.children[actionsIndex]) {
                    row.removeChild(row.children[actionsIndex]);
                }
            });
        }

        return clone;
    }

    function exportTableAsExcel(table) {
        const { filenameBase } = tableMeta(table);
        const clone = cloneTableWithoutActions(table);
        const html = `<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>${clone.outerHTML}</body></html>`;
        const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${filenameBase}.xls`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function exportTableAsPdf(table) {
        const { filenameBase } = tableMeta(table);
        const clone = cloneTableWithoutActions(table);

        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            window.alert('Please allow popups to export PDF.');
            return;
        }

        printWindow.document.write(`
            <html>
            <head>
                <title>${filenameBase}.pdf</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #d0d7de; padding: 8px; text-align: left; font-size: 12px; }
                    th { background: #f6f8fa; }
                </style>
            </head>
            <body>
                <h2>${filenameBase.replace(/-/g, ' ')}</h2>
                ${clone.outerHTML}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        setTimeout(() => printWindow.close(), 200);
    }

    function readCsv(text) {
        const rows = [];
        let row = [];
        let cell = '';
        let insideQuotes = false;

        for (let i = 0; i < text.length; i += 1) {
            const char = text[i];
            const next = text[i + 1];

            if (insideQuotes) {
                if (char === '"' && next === '"') {
                    cell += '"';
                    i += 1;
                } else if (char === '"') {
                    insideQuotes = false;
                } else {
                    cell += char;
                }
            } else if (char === '"') {
                insideQuotes = true;
            } else if (char === ',') {
                row.push(cell.trim());
                cell = '';
            } else if (char === '\n' || char === '\r') {
                if (char === '\r' && next === '\n') {
                    i += 1;
                }
                row.push(cell.trim());
                if (row.some((value) => value !== '')) {
                    rows.push(row);
                }
                row = [];
                cell = '';
            } else {
                cell += char;
            }
        }

        if (cell.length || row.length) {
            row.push(cell.trim());
            if (row.some((value) => value !== '')) {
                rows.push(row);
            }
        }

        return rows;
    }

    function importRowsIntoTable(table, rows) {
        if (!rows || rows.length < 2) {
            window.alert('The file is empty or has no data rows.');
            return;
        }

        const { headers, actionsIndex } = tableMeta(table);
        const dataRows = rows.slice(1);
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        dataRows.forEach((sourceRow) => {
            const tr = document.createElement('tr');
            headers.forEach((_, colIndex) => {
                const td = document.createElement('td');
                td.textContent = colIndex === actionsIndex ? '' : (sourceRow[colIndex] ?? '');
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
    }

    async function importRowsIntoDatabase(table, rows) {
        const endpoint = table.dataset.itmDbImportEndpoint || '';
        if (!endpoint) {
            return false;
        }

        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
        if (!csrfToken) {
            window.alert('Import failed: missing CSRF token.');
            return true;
        }

        const response = await window.fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                import_excel_rows: rows,
                csrf_token: csrfToken
            })
        });

        const responseBody = await response.text();
        let payload = null;
        try {
            payload = responseBody ? JSON.parse(responseBody) : null;
        } catch (error) {
            payload = null;
        }

        if (!response.ok || !payload || payload.ok !== true) {
            let message = (payload && payload.error) ? payload.error : '';
            if (!message && responseBody) {
                const textOnly = responseBody.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                if (textOnly) {
                    message = textOnly.slice(0, 220);
                }
            }
            if (!message) {
                message = 'Import failed while saving to database.';
            }
            window.alert(message);
            return true;
        }

        const inserted = Number(payload.inserted || 0);
        const failed = Number(payload.failed || 0);
        const warning = payload.warning ? `\nNote: ${payload.warning}` : '';
        const failedLabel = failed > 0 ? ` ${failed} row(s) failed.` : '';
        window.alert(`Import completed. ${inserted} row(s) saved.${failedLabel}${warning}`);
        window.location.reload();
        return true;
    }

    function importTableFromFile(table, file) {
        const extension = (file.name.split('.').pop() || '').toLowerCase();
        const reader = new FileReader();

        if (extension === 'csv') {
            reader.onload = async () => {
                const text = typeof reader.result === 'string' ? reader.result : '';
                const rows = readCsv(text);
                const handledByDbImport = await importRowsIntoDatabase(table, rows);
                if (!handledByDbImport) {
                    importRowsIntoTable(table, rows);
                }
            };
            reader.readAsText(file);
            return;
        }

        if (window.XLSX && (extension === 'xlsx' || extension === 'xls')) {
            reader.onload = async () => {
                const data = new Uint8Array(reader.result);
                const workbook = window.XLSX.read(data, { type: 'array' });
                const firstSheet = workbook.SheetNames[0];
                const rows = window.XLSX.utils.sheet_to_json(workbook.Sheets[firstSheet], { header: 1, defval: '' });
                const handledByDbImport = await importRowsIntoDatabase(table, rows);
                if (!handledByDbImport) {
                    importRowsIntoTable(table, rows);
                }
            };
            reader.readAsArrayBuffer(file);
            return;
        }

        window.alert('Unsupported file type. Please import CSV, XLS, or XLSX files.');
    }

    function toolsForPage() {
        return {
            excel: true,
            pdf: true,
            importExcel: true,
        };
    }

    function makeButton(label, clickHandler, toolType) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm';
        button.textContent = label;
        button.dataset.toolType = toolType || '';
        if (typeof clickHandler === 'function') {
            button.addEventListener('click', clickHandler);
        }
        return button;
    }

    function bindToolbarEvents(toolbar, table) {
        toolbar.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-tool-type]');
            if (!button) return;
            const type = button.dataset.toolType;
            if (type === 'excel') exportTableAsExcel(table);
            if (type === 'pdf') exportTableAsPdf(table);
            if (type === 'import') {
                const inputId = button.dataset.inputId;
                const input = inputId ? document.getElementById(inputId) : null;
                if (input) input.click();
            }
        });

        toolbar.querySelectorAll('input[type="file"]').forEach((input) => {
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file) return;
                importTableFromFile(table, file);
                input.value = '';
            });
        });
    }

    function addToolbarClass(toolbar, mode) {
        toolbar.classList.remove('itm-tools-left', 'itm-tools-right', 'itm-tools-left-right');
        if (mode === 'left' || mode === 'top_left' || mode === 'bottom_left' || mode === 'top_bottom_left') {
            toolbar.classList.add('itm-tools-left');
            return;
        }
        if (mode === 'right' || mode === 'top_right' || mode === 'bottom_right' || mode === 'top_bottom_right') {
            toolbar.classList.add('itm-tools-right');
            return;
        }
        toolbar.classList.add('itm-tools-left-right');
    }

    function placeToolbar(table, toolbar) {
        const cfg = window.ITM_UI_CONFIG || {};
        const mode = (cfg.export_buttons_position || 'left_right').toString();
        const card = table.closest('.card');
        if (!card || !table.parentNode) return;

        addToolbarClass(toolbar, mode);

        if (mode === 'bottom_left' || mode === 'bottom_right') {
            toolbar.classList.add('table-tools-bottom');
            table.parentNode.appendChild(toolbar);
            return;
        }

        if (mode === 'top_bottom_left' || mode === 'top_bottom_right') {
            const topToolbar = toolbar.cloneNode(true);
            addToolbarClass(topToolbar, mode);
            bindToolbarEvents(topToolbar, table);
            table.parentNode.insertBefore(topToolbar, table);

            toolbar.classList.add('table-tools-bottom');
            table.parentNode.appendChild(toolbar);
            return;
        }

        table.parentNode.insertBefore(toolbar, table);
    }

    function attachListTools(table, index) {
        if (table.dataset.tableToolsAttached === '1') {
            return;
        }

        const enabled = toolsForPage(table);
        if (!enabled.excel && !enabled.pdf && !enabled.importExcel) {
            return;
        }

        table.dataset.tableToolsAttached = '1';

        const toolbar = document.createElement('div');
        toolbar.className = 'table-tools';

        if (enabled.excel) {
            toolbar.appendChild(makeButton('📗 Export Excel', null, 'excel'));
        }

        if (enabled.pdf) {
            toolbar.appendChild(makeButton('📄 Export PDF', null, 'pdf'));
        }

        if (enabled.importExcel) {
            const importInputId = `tableToolsImport-${index}`;
            const importBtn = makeButton('📥 Import Excel', null, 'import');
            importBtn.dataset.inputId = importInputId;

            const importInput = document.createElement('input');
            importInput.type = 'file';
            importInput.accept = '.csv,.xlsx,.xls';
            importInput.className = 'table-tools-file';
            importInput.id = importInputId;

            toolbar.appendChild(importBtn);
            toolbar.appendChild(importInput);
        }

        bindToolbarEvents(toolbar, table);
        placeToolbar(table, toolbar);
        attachInlineSearch(table);
    }

    function rowSearchText(row) {
        return Array.from(row.querySelectorAll('td'))
            .map((cell) => getCellText(cell).toLowerCase())
            .join(' ');
    }

    function attachInlineSearch(table) {
        if (table.dataset.tableSearchAttached === '1') {
            return;
        }

        const content = table.closest('.content');
        if (content && content.querySelector('input[name="search"]')) {
            return;
        }

        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const rows = Array.from(tbody.querySelectorAll('tr'));
        if (!rows.length) return;

        table.dataset.tableSearchAttached = '1';

        const searchWrap = document.createElement('div');
        searchWrap.className = 'table-search-inline';
        searchWrap.style.cssText = 'display:flex;gap:8px;align-items:center;margin:8px 0 10px;flex-wrap:wrap;';

        const label = document.createElement('label');
        label.textContent = 'Search table:';
        label.style.fontWeight = '600';

        const input = document.createElement('input');
        input.type = 'search';
        input.placeholder = 'Type to filter visible rows...';
        input.style.cssText = 'min-width:240px;max-width:420px;width:100%;';

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-sm';
        clearBtn.textContent = 'Clear';

        const emptyState = document.createElement('div');
        emptyState.textContent = 'No matching records found.';
        emptyState.style.cssText = 'display:none;color:var(--text-secondary,#6b7280);font-size:13px;padding:6px 0;';

        function applyFilter() {
            const query = input.value.trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach((row) => {
                const show = query === '' || rowSearchText(row).includes(query);
                row.style.display = show ? '' : 'none';
                if (show) visibleCount += 1;
            });

            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        input.addEventListener('input', applyFilter);
        clearBtn.addEventListener('click', () => {
            input.value = '';
            applyFilter();
            input.focus();
        });

        searchWrap.appendChild(label);
        searchWrap.appendChild(input);
        searchWrap.appendChild(clearBtn);

        table.parentNode.insertBefore(searchWrap, table);
        table.parentNode.insertBefore(emptyState, table.nextSibling);
    }

    function exportViewAsPdf(table) {
        const heading = table.closest('.content')?.querySelector('h1');
        const filenameBase = sanitizeFilename(heading ? heading.textContent : document.title);
        const clone = table.cloneNode(true);
        const printWindow = window.open('', '_blank');

        if (!printWindow) {
            window.alert('Please allow popups to export PDF.');
            return;
        }

        printWindow.document.write(`
            <html>
            <head>
                <title>${filenameBase}.pdf</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #d0d7de; padding: 8px; text-align: left; font-size: 12px; vertical-align: top; }
                    th { background: #f6f8fa; width: 240px; }
                </style>
            </head>
            <body>
                <h2>${filenameBase.replace(/-/g, ' ')}</h2>
                ${clone.outerHTML}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        setTimeout(() => printWindow.close(), 200);
    }

    function attachViewTools(table) {
        if (table.dataset.tableToolsAttached === '1') {
            return;
        }
        table.dataset.tableToolsAttached = '1';

        const toolbar = document.createElement('div');
        toolbar.className = 'table-tools';

        const pdfBtn = document.createElement('button');
        pdfBtn.type = 'button';
        pdfBtn.className = 'btn btn-sm';
        pdfBtn.textContent = '📄 Export PDF';
        pdfBtn.addEventListener('click', () => exportViewAsPdf(table));

        toolbar.appendChild(pdfBtn);
        table.parentNode.insertBefore(toolbar, table);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const tables = Array.from(document.querySelectorAll('.content .card table'));
        tables.forEach((table, index) => {
            const hasListHeader = Boolean(table.querySelector('thead th'));
            const isViewTable = !hasListHeader && Boolean(table.querySelector('tbody th'));

            if (hasListHeader) {
                attachListTools(table, index);
                return;
            }

            if (isViewTable) {
                attachViewTools(table);
            }
        });
    });
})();
