/**
 * Bookmarks Export JS
 * Refactored to use document.createElement and textContent for security.
 */
function exportBookmarks(format, folderId) {
    const cards = document.querySelectorAll('.bookmark-card');
    if (cards.length === 0 && (format === 'xlsx' || format === 'pdf')) {
        alert('No bookmarks to export in this view.');
        return;
    }

    // Use fallback to PHP for non-JS formats
    if (format !== 'xlsx' && format !== 'pdf') {
        window.location.href = `export.php?format=${format}${folderId ? '&folder_id=' + folderId : ''}`;
        return;
    }

    const table = document.createElement('table');
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    ['Title', 'URL', 'Notes', 'Shared'].forEach(text => {
        const th = document.createElement('th');
        th.textContent = text;
        headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);
    table.appendChild(thead);

    const tbody = document.createElement('tbody');
    cards.forEach(card => {
        const tr = document.createElement('tr');

        const titleTd = document.createElement('td');
        titleTd.textContent = card.querySelector('strong').textContent.trim();
        tr.appendChild(titleTd);

        const urlTd = document.createElement('td');
        urlTd.textContent = card.querySelector('a').textContent.trim();
        tr.appendChild(urlTd);

        const notesTd = document.createElement('td');
        const notesP = card.querySelector('p');
        notesTd.textContent = notesP ? notesP.textContent.trim() : '';
        tr.appendChild(notesTd);

        const sharedTd = document.createElement('td');
        sharedTd.textContent = card.querySelector('.shared-badge') ? 'Yes' : 'No';
        tr.appendChild(sharedTd);

        tbody.appendChild(tr);
    });
    table.appendChild(tbody);

    if (format === 'xlsx') {
        if (typeof XLSX === 'undefined') {
            const script = document.createElement('script');
            script.src = '../../js/vendor/xlsx.full.min.js';
            script.onload = () => {
                const wb = XLSX.utils.table_to_book(table);
                XLSX.writeFile(wb, 'bookmarks.xlsx');
            };
            document.head.appendChild(script);
        } else {
            const wb = XLSX.utils.table_to_book(table);
            XLSX.writeFile(wb, 'bookmarks.xlsx');
        }
    } else if (format === 'pdf') {
        window.print();
    }
}
