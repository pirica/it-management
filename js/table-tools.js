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

    function getExplicitExportValue(cell) {
        if (!cell) {
            return null;
        }

        if (cell.dataset && Object.prototype.hasOwnProperty.call(cell.dataset, 'itmExportValue')) {
            return cell.dataset.itmExportValue;
        }

        const exportNode = cell.querySelector('[data-itm-export-value]');
        if (exportNode) {
            return exportNode.getAttribute('data-itm-export-value') || '';
        }

        return null;
    }

    // Why: Excel export should keep intentional spacing; list/search UI still uses getCellText() normalization.
    function getExportCellText(cell) {
        const explicitValue = getExplicitExportValue(cell);
        if (explicitValue !== null) {
            return explicitValue.replace(/\r\n/g, '\n').replace(/\t/g, ' ').trim();
        }
        if (!cell || !cell.textContent) {
            return '';
        }
        return cell.textContent.replace(/\r\n/g, '\n').replace(/\t/g, ' ').trim();
    }

    function tableMeta(table) {
        const heading = table.closest('.content')?.querySelector('h1');
        const filenameBase = sanitizeFilename(heading ? heading.textContent : document.title);
        const headers = Array.from(table.querySelectorAll('thead th')).map(getCellText);
        const actionsIndex = headers.findIndex((h) => h.toLowerCase() === 'actions');
        return { filenameBase, headers, actionsIndex };
    }

    function isBulkSelectHeader(th) {
        return Boolean(th && th.querySelector('input[type="checkbox"]') && getCellText(th) === '');
    }

    function bulkSelectColumnIndexes(table) {
        const indexes = [];
        const headerRow = table.querySelector('thead tr');
        if (!headerRow) {
            return indexes;
        }
        Array.from(headerRow.children).forEach((cell, index) => {
            if (isBulkSelectHeader(cell)) {
                indexes.push(index);
            }
        });
        return indexes;
    }

    function removeColumnsFromTable(table, indexes) {
        if (!indexes.length) {
            return;
        }
        const sorted = indexes.slice().sort((a, b) => b - a);
        table.querySelectorAll('tr').forEach((row) => {
            sorted.forEach((index) => {
                if (row.children[index]) {
                    row.removeChild(row.children[index]);
                }
            });
        });
    }

    function cloneTableWithoutActions(table) {
        const { actionsIndex } = tableMeta(table);
        const clone = table.cloneNode(true);
        clone.style.display = '';
        if (clone.style.setProperty) clone.style.setProperty('display', 'table', 'important');

        clone.querySelectorAll('[data-itm-export-value]').forEach((node) => {
            node.textContent = node.getAttribute('data-itm-export-value') || '';
        });

        if (actionsIndex >= 0) {
            clone.querySelectorAll('tr').forEach((row) => {
                if (row.children[actionsIndex]) {
                    row.removeChild(row.children[actionsIndex]);
                }
            });
        }

        removeColumnsFromTable(clone, bulkSelectColumnIndexes(clone));

        return clone;
    }

    function exportRowsAsXlsxBlob(rows, filenameBase) {
        if (!window.XLSX || !window.XLSX.utils || typeof window.XLSX.write !== 'function') {
            return null;
        }

        const sheet = window.XLSX.utils.aoa_to_sheet(rows);
        const workbook = window.XLSX.utils.book_new();
        window.XLSX.utils.book_append_sheet(workbook, sheet, 'Sheet1');
        const bytes = window.XLSX.write(workbook, { bookType: 'xlsx', type: 'array' });
        const blob = new Blob([bytes], {
            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${filenameBase}.xlsx`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        return true;
    }

    function exportTableAsExcel(table) {
        const { filenameBase } = tableMeta(table);
        const clone = cloneTableWithoutActions(table);
        const headerRow = Array.from(clone.querySelectorAll('thead th')).map(getExportCellText);
        const rows = [headerRow];
        clone.querySelectorAll('tbody tr').forEach((row) => {
            rows.push(Array.from(row.querySelectorAll('td')).map(getExportCellText));
        });

        // Why: Only real OOXML .xlsx — never HTML disguised as .xls (Excel shows a format/extension warning).
        if (exportRowsAsXlsxBlob(rows, filenameBase)) {
            return;
        }

        window.alert('Export Excel is unavailable because the XLSX library did not load. Refresh the page and try again.');
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

    function dataColumnMeta(table) {
        const headerRow = table.querySelector('thead tr');
        const headers = [];
        const indexes = [];
        if (!headerRow) {
            return { headers, indexes };
        }

        Array.from(headerRow.children).forEach((th, index) => {
            const label = getCellText(th);
            if (isBulkSelectHeader(th)) {
                return;
            }
            if (label.toLowerCase() === 'actions') {
                return;
            }
            headers.push(label);
            indexes.push(index);
        });

        return { headers, indexes };
    }

    function importRowsIntoTable(table, rows) {
        if (!rows || rows.length < 2) {
            window.alert('The file is empty or has no data rows.');
            return;
        }

        const headerRow = table.querySelector('thead tr');
        const columnPlan = [];
        let dataColumnIndex = 0;
        if (headerRow) {
            Array.from(headerRow.children).forEach((th) => {
                const label = getCellText(th);
                if (isBulkSelectHeader(th)) {
                    columnPlan.push({ type: 'bulk' });
                    return;
                }
                if (label.toLowerCase() === 'actions') {
                    columnPlan.push({ type: 'actions' });
                    return;
                }
                columnPlan.push({ type: 'data', sourceIndex: dataColumnIndex });
                dataColumnIndex += 1;
            });
        }

        const dataRows = rows.slice(1);
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        dataRows.forEach((sourceRow) => {
            const tr = document.createElement('tr');
            columnPlan.forEach((plan) => {
                const td = document.createElement('td');
                if (plan.type === 'bulk') {
                    td.innerHTML = '<input type="checkbox" name="ids[]" value="">';
                } else if (plan.type === 'actions') {
                    td.textContent = '';
                } else {
                    td.textContent = sourceRow[plan.sourceIndex] ?? '';
                }
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
            if (window.itmNotifyError) {
                window.itmNotifyError(message);
            } else {
                window.alert(message);
            }
            return true;
        }

        const inserted = Number(payload.inserted || 0);
        const failed = Number(payload.failed || 0);
        const rowErrors = Array.isArray(payload.errors) ? payload.errors.filter(Boolean) : [];
        const detailLines = rowErrors.length > 0
            ? rowErrors.slice(0, 5).join('\n')
            : String(payload.error || payload.warning || '').trim();
        const notifyError = window.itmNotifyError || window.itmNotifyAjaxError;
        const notifySuccess = window.itmNotifySuccess;

        if (inserted === 0) {
            const message = detailLines
                || 'No rows could be saved. Review the file for duplicate values or missing required fields.';
            if (notifyError) {
                notifyError(message);
            } else {
                window.alert(message);
            }
            return true;
        }

        const successLead = `Import completed. ${inserted} row(s) saved.`;
        if (failed > 0) {
            const failureLead = `${failed} row(s) could not be saved.`;
            const failureDetail = detailLines || String(payload.warning || '').trim();
            if (notifySuccess) {
                notifySuccess(successLead);
            }
            if (notifyError) {
                notifyError(failureDetail ? `${failureLead}\n${failureDetail}` : failureLead);
            } else {
                window.alert(`${successLead}\n${failureLead}${failureDetail ? `\n${failureDetail}` : ''}`);
            }
        } else if (payload.warning) {
            const message = `${successLead}\nNote: ${payload.warning}`;
            if (notifySuccess) {
                notifySuccess(message);
            } else {
                window.alert(message);
            }
        } else if (notifySuccess) {
            notifySuccess(successLead);
        } else {
            window.alert(successLead);
        }

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

    function toolsForPage(table) {
        const card = table ? table.closest('.card') : null;
        const noImport = (table && table.dataset.itmNoImportExcel === '1')
            || (card && card.dataset.itmNoImportExcel === '1');
        const noExcel = (table && table.dataset.itmNoExportExcel === '1')
            || (card && card.dataset.itmNoExportExcel === '1');
        const noPdf = (table && table.dataset.itmNoExportPdf === '1')
            || (card && card.dataset.itmNoExportPdf === '1');

        return {
            excel: !noExcel,
            pdf: !noPdf,
            importExcel: !noImport,
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

        const card = table.closest('.card');
        const noSearch = (table && table.dataset.itmNoSearch === '1')
            || (card && card.dataset.itmNoSearch === '1');
        if (noSearch) {
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

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function buildFloorPlanFileLinksHtml(card) {
        const fileUrl = escapeHtml((card.getAttribute('data-itm-pdf-file-url') || '').trim());
        const fileName = escapeHtml(card.getAttribute('data-itm-pdf-file-name') || 'floor-plan');
        if (fileUrl === '') {
            return '';
        }

        return `<p style="margin:12px 0 0;">`
            + `<a href="${fileUrl}" download="${fileName}">Download file</a>`
            + ` · <a href="${fileUrl}" target="_blank" rel="noopener">Open in new tab</a></p>`;
    }

    function buildViewPdfPreviewHtml(table) {
        const card = table.closest('[data-itm-pdf-preview]');
        if (!card) {
            return '';
        }

        const preview = card.querySelector('.itm-floor-plan-view-preview');
        if (!preview) {
            return '';
        }

        const fileLinksHtml = buildFloorPlanFileLinksHtml(card);

        const images = Array.from(preview.querySelectorAll('img.itm-floor-plan-view-image'));
        if (images.length > 0) {
            let html = `<div class="itm-pdf-preview-wrap" style="margin:0 0 16px;text-align:center;display:flex;flex-wrap:wrap;justify-content:center;gap:15px;">`;
            images.forEach(image => {
                if (image.src) {
                    const alt = escapeHtml(image.getAttribute('alt') || 'Note image');
                    const style = images.length === 1 ? 'max-width:100%;height:auto;' : 'max-width:300px;height:auto;border:1px solid #ddd;border-radius:4px;';
                    html += `<img src="${escapeHtml(image.src)}" alt="${alt}" style="${style}display:block;margin:0 auto;">`;
                }
            });
            html += `</div>` + fileLinksHtml;
            return html;
        }

        const previewKind = (card.getAttribute('data-itm-pdf-preview-kind') || '').toLowerCase();
        const pdfFrame = preview.querySelector('iframe.itm-floor-plan-pdf-frame');
        if (previewKind === 'pdf' || (pdfFrame && pdfFrame.src)) {
            const fileUrl = escapeHtml(card.getAttribute('data-itm-pdf-file-url') || pdfFrame.src.split('#')[0]);
            const fileName = escapeHtml(card.getAttribute('data-itm-pdf-file-name') || 'floor-plan.pdf');
            return `<div class="itm-pdf-preview-wrap itm-pdf-preview-file" style="margin:0 0 16px;padding:12px;border:1px solid #d0d7de;border-radius:8px;">`
                + `<p><strong>Floor plan file (PDF)</strong></p>`
                + `<p>The PDF is not embedded in this printout. Browsers disable Save for many signed or protected PDFs when they are shown inside a viewer.</p>`
                + `<p><a href="${fileUrl}" download="${fileName}">Download file</a>`
                + ` · <a href="${fileUrl}" target="_blank" rel="noopener">Open in new tab</a></p>`
                + `<p style="font-size:12px;color:#57606a;">Use <strong>Download file</strong> for the original. Use the print dialog&rsquo;s <strong>Save as PDF</strong> / <strong>Microsoft Print to PDF</strong> for this summary page.</p>`
                + `</div>`;
        }

        const cadBlock = preview.querySelector('.itm-floor-plan-cad-view');
        if (cadBlock || previewKind === 'cad' || previewKind === 'download') {
            const cadNote = cadBlock ? cadBlock.querySelector('p') : null;
            const cadText = cadNote
                ? escapeHtml((cadNote.textContent || '').trim())
                : 'CAD file preview is not available in the browser.';
            const kindLabel = previewKind === 'cad' ? 'CAD' : 'file';
            return `<div class="itm-pdf-preview-wrap itm-pdf-preview-file" style="margin:0 0 16px;padding:12px;border:1px solid #d0d7de;border-radius:8px;">`
                + `<p><strong>Floor plan (${kindLabel})</strong></p>`
                + `<p>${cadText}</p>`
                + fileLinksHtml
                + `</div>`;
        }

        return '';
    }

    function triggerViewPdfPrint(printWindow) {
        const images = Array.from(printWindow.document.querySelectorAll('img'));
        const finish = () => {
            printWindow.focus();
            printWindow.print();
            setTimeout(() => printWindow.close(), 200);
        };

        if (images.length === 0) {
            finish();
            return;
        }

        let loadedCount = 0;
        const onImageLoad = () => {
            loadedCount++;
            if (loadedCount === images.length) {
                finish();
            }
        };

        images.forEach(img => {
            if (img.complete) {
                onImageLoad();
            } else {
                img.addEventListener('load', onImageLoad, { once: true });
                img.addEventListener('error', onImageLoad, { once: true });
            }
        });
    }

    function exportViewAsPdf(table) {
        const heading = table.closest('.content')?.querySelector('h1');
        const filenameBase = sanitizeFilename(heading ? heading.textContent : document.title);
        const clone = table.cloneNode(true);
        clone.style.display = '';
        if (clone.style.setProperty) clone.style.setProperty('display', 'table', 'important');
        const previewHtml = buildViewPdfPreviewHtml(table);
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
                    .itm-pdf-preview-wrap { page-break-inside: avoid; }
                </style>
            </head>
            <body>
                <h2>${filenameBase.replace(/-/g, ' ')}</h2>
                ${previewHtml}
                ${clone.outerHTML}
            </body>
            </html>
        `);
        printWindow.document.close();
        triggerViewPdfPrint(printWindow);
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
