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

    function exportTableAsExcel(table) {
        const { filenameBase, actionsIndex } = tableMeta(table);
        const clone = table.cloneNode(true);

        if (actionsIndex >= 0) {
            clone.querySelectorAll('tr').forEach((row) => {
                if (row.children[actionsIndex]) {
                    row.removeChild(row.children[actionsIndex]);
                }
            });
        }

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
        const { filenameBase, actionsIndex } = tableMeta(table);
        const clone = table.cloneNode(true);

        if (actionsIndex >= 0) {
            clone.querySelectorAll('tr').forEach((row) => {
                if (row.children[actionsIndex]) {
                    row.removeChild(row.children[actionsIndex]);
                }
            });
        }

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

    function attachTools(table, index) {
        if (table.dataset.tableToolsAttached === '1') {
            return;
        }
        table.dataset.tableToolsAttached = '1';

        const toolbar = document.createElement('div');
        toolbar.className = 'table-tools';

        const excelBtn = document.createElement('button');
        excelBtn.type = 'button';
        excelBtn.className = 'btn btn-sm';
        excelBtn.textContent = '📗 Export Excel';
        excelBtn.addEventListener('click', () => exportTableAsExcel(table));

        const pdfBtn = document.createElement('button');
        pdfBtn.type = 'button';
        pdfBtn.className = 'btn btn-sm';
        pdfBtn.textContent = '📄 Export PDF';
        pdfBtn.addEventListener('click', () => exportTableAsPdf(table));

        const importBtn = document.createElement('button');
        importBtn.type = 'button';
        importBtn.className = 'btn btn-sm';
        importBtn.textContent = '📥 Import Excel';

        const importInput = document.createElement('input');
        importInput.type = 'file';
        importInput.accept = '.csv,.xlsx,.xls';
        importInput.className = 'table-tools-file';
        importInput.id = `tableToolsImport-${index}`;
        importInput.addEventListener('change', () => {
            if (importInput.files && importInput.files[0]) {
                importTableFromFile(table, importInput.files[0]);
                importInput.value = '';
            }
        });

        importBtn.addEventListener('click', () => importInput.click());

        toolbar.appendChild(excelBtn);
        toolbar.appendChild(pdfBtn);
        toolbar.appendChild(importBtn);
        toolbar.appendChild(importInput);

        table.parentNode.insertBefore(toolbar, table);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const tables = Array.from(document.querySelectorAll('.content .card table'));
        tables.forEach((table, index) => {
            if (!table.querySelector('thead th')) {
                return;
            }
            attachTools(table, index);
        });
    });
})();
