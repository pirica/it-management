/**
 * Bookmarks Export JS — all formats use export.php so vault decryption stays server-side.
 */
function exportBookmarks(format, folderId) {
    const params = new URLSearchParams();
    params.set('format', format || 'csv');
    if (folderId) {
        params.set('folder_id', folderId);
    }
    window.location.href = 'export.php?' + params.toString();
}
