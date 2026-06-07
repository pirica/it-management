/**
 * Bookmarks Export JS
 */
function exportBookmarks(format, folderId) {
    // Collect bookmarks from the current DOM view
    const cards = document.querySelectorAll('.bookmark-card');
    if (cards.length === 0 && format !== 'html' && format !== 'csv' && format !== 'txt') {
        alert('No bookmarks to export in this view.');
        return;
    }

    const table = document.createElement('table');
    let html = '<thead><tr><th>Title</th><th>URL</th><th>Notes</th><th>Shared</th></tr></thead><tbody>';

    cards.forEach(card => {
        const title = card.querySelector('strong').textContent.trim();
        const url = card.querySelector('a').textContent.trim();
        const notes = card.querySelector('p') ? card.querySelector('p').textContent.trim() : '';
        const shared = card.querySelector('.shared-badge') ? 'Yes' : 'No';
        html += `<tr><td>${title}</td><td>${url}</td><td>${notes}</td><td>${shared}</td></tr>`;
    });
    html += '</tbody>';
    table.innerHTML = html;

    if (format === 'xlsx') {
        const script = document.createElement('script');
        script.src = '../../js/vendor/xlsx.full.min.js';
        script.onload = () => {
            const wb = XLSX.utils.table_to_book(table);
            XLSX.writeFile(wb, 'bookmarks.xlsx');
        };
        document.head.appendChild(script);
    } else if (format === 'pdf') {
        window.print();
    } else {
        // Fallback to PHP-side export for CSV, TXT, HTML
        window.location.href = `export.php?format=${format}${folderId ? '&folder_name=' + folderId : ''}`;
    }
}
