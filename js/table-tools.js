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

    function importTableFromFile(table, file) {
        const extension = (file.name.split('.').pop() || '').toLowerCase();
        const reader = new FileReader();

        if (extension === 'csv') {
            reader.onload = () => {
                const text = typeof reader.result === 'string' ? reader.result : '';
                importRowsIntoTable(table, readCsv(text));
            };
            reader.readAsText(file);
            return;
        }

        if (window.XLSX && (extension === 'xlsx' || extension === 'xls')) {
            reader.onload = () => {
                const data = new Uint8Array(reader.result);
                const workbook = window.XLSX.read(data, { type: 'array' });
                const firstSheet = workbook.SheetNames[0];
                const rows = window.XLSX.utils.sheet_to_json(workbook.Sheets[firstSheet], { header: 1, defval: '' });
                importRowsIntoTable(table, rows);
            };
            reader.readAsArrayBuffer(file);
            return;
        }

        window.alert('Unsupported file type. Please import CSV, XLS, or XLSX files.');
    }


    function uiConfig(key, fallback) {
        const config = window.ITM_UI_CONFIG || {};
        return typeof config[key] === 'string' && config[key] ? config[key] : fallback;
    }

    function toolbarAlignClass(mode) {
        if (mode === 'left') return 'itm-tools-left';
        if (mode === 'right' || mode === 'top_right' || mode === 'bottom_right' || mode === 'top_bottom_right') return 'itm-tools-right';
        if (mode === 'top_left' || mode === 'bottom_left' || mode === 'top_bottom_left') return 'itm-tools-left';
        return 'itm-tools-left-right';
    }

    function placeToolbar(table, toolbar) {
        const mode = uiConfig('export_buttons_position', 'left_right');
        const topModes = new Set(['left_right', 'left', 'right', 'top_right', 'top_left', 'top_bottom_right', 'top_bottom_left']);
        const bottomModes = new Set(['bottom_right', 'bottom_left', 'top_bottom_right', 'top_bottom_left']);

        toolbar.classList.add(toolbarAlignClass(mode));

        if (topModes.has(mode)) {
            table.parentNode.insertBefore(toolbar, table);
        }

        if (bottomModes.has(mode)) {
            const bottomToolbar = toolbar.cloneNode(true);
            bottomToolbar.classList.add('table-tools-bottom');
            bottomToolbar.classList.remove('itm-tools-left', 'itm-tools-right', 'itm-tools-left-right');
            bottomToolbar.classList.add(toolbarAlignClass(mode));
            bindToolbarEvents(bottomToolbar, table);
            if (topModes.has(mode)) {
                table.parentNode.insertBefore(bottomToolbar, table.nextSibling);
            } else {
                table.parentNode.insertBefore(bottomToolbar, table.nextSibling);
                toolbar.remove();
            }
        }
    }

    function bindToolbarEvents(toolbar, table) {
        toolbar.querySelectorAll('button[data-action]').forEach((btn) => {
            const action = btn.dataset.action;
            if (action === 'excel') {
                btn.addEventListener('click', () => exportTableAsExcel(table));
            } else if (action === 'pdf') {
                btn.addEventListener('click', () => exportTableAsPdf(table));
            } else if (action === 'import') {
                const inputId = btn.dataset.inputId;
                const importInput = toolbar.querySelector('#' + inputId);
                if (importInput) {
                    btn.addEventListener('click', () => importInput.click());
                    importInput.addEventListener('change', () => {
                        if (importInput.files && importInput.files[0]) {
                            importTableFromFile(table, importInput.files[0]);
                            importInput.value = '';
                        }
                    });
                }
            }
        });
    }

    function makeButton(label, onClick, action) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm';
        btn.textContent = label;
        if (action) btn.dataset.action = action;
        if (onClick) btn.addEventListener('click', onClick);
        return btn;
    }

    function toolsForPage(table) {
        const pathname = (window.location.pathname || '').toLowerCase();
        const isViewPage = pathname.endsWith('/view.php');
        const isListAllPage = pathname.endsWith('/list_all.php');
        const hasHeader = table.querySelectorAll('thead th').length > 0;

        if (isViewPage) {
            return { excel: false, pdf: true, importExcel: false };
        }

        if (isListAllPage) {
            return { excel: true, pdf: true, importExcel: true };
        }

        if (hasHeader) {
            return { excel: true, pdf: true, importExcel: true };
        }

        return { excel: false, pdf: false, importExcel: false };
    }

    function attachTools(table, index) {
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
    }

    document.addEventListener('DOMContentLoaded', () => {
        const tables = Array.from(document.querySelectorAll('.content .card table'));
        tables.forEach((table, index) => {
            attachTools(table, index);
        });
    });
})();
