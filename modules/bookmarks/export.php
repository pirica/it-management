<?php
require '../../config/config.php';
require './helpers.php';
require './bkm_vault_bootstrap.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);
$bkmVaultState = bkm_handle_vault_requests($conn, $user_id);

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$format = isset($_GET['format']) ? strtolower((string)$_GET['format']) : 'csv';
$folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;

$where = "company_id = $company_id AND (employee_id = $user_id OR shared = 1) AND active = 1";
if ($folder_id) {
    $where .= " AND folder_id = $folder_id";
}

$sql = "SELECT title, url, notes, shared, employee_id FROM bookmarks WHERE $where ORDER BY position ASC, title ASC";
$res = mysqli_query($conn, $sql);
$data = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $data[] = bkm_export_row($row, $user_id);
}

// Why: Shared URLs are always plaintext; own private URLs decrypt when vault_key is in session.
if ($format === 'xlsx' || $format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $filename = $format === 'xlsx' ? 'bookmarks.xlsx' : 'bookmarks.csv';
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Title', 'URL', 'Notes', 'Shared']);
    foreach ($data as $row) {
        fputcsv($output, [$row['title'], $row['url'], $row['notes'], $row['shared'] ? 'Yes' : 'No']);
    }
    fclose($output);
    return;
}

if ($format === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename=bookmarks.txt');
    foreach ($data as $row) {
        echo "Title: " . $row['title'] . "\n";
        echo "URL: " . $row['url'] . "\n";
        echo "Notes: " . $row['notes'] . "\n";
        echo "Shared: " . ($row['shared'] ? 'Yes' : 'No') . "\n";
        echo "---------------------------\n";
    }
    return;
}

if ($format === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename=bookmarks.html');
    echo "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n";
    echo "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n";
    echo "<title>Bookmarks</title>\n";
    echo "<H1>Bookmarks</H1>\n";
    echo "<DL><p>\n";
    foreach ($data as $row) {
        if ($row['url'] === '') {
            continue;
        }
        echo "    <DT><A HREF=\"" . sanitize($row['url']) . "\">" . sanitize($row['title']) . "</A>\n";
        if ($row['notes'] !== '') {
            echo "    <DD>" . sanitize($row['notes']) . "\n";
        }
    }
    echo "</DL><p>\n";
    return;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bookmarks Export</title>
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body onload="window.print()">
    <h1>Bookmarks Export</h1>
    <?php if (empty($bkmVaultState['unlocked'])): ?>
        <p><em>Private bookmark titles, URLs, and notes are omitted until you unlock your vault. Shared bookmarks export with plaintext fields.</em></p>
    <?php endif; ?>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>URL</th>
                <th>Notes</th>
                <th>Shared</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $row): ?>
                <tr>
                    <td><?php echo sanitize($row['title']); ?></td>
                    <td><?php echo sanitize($row['url']); ?></td>
                    <td><?php echo sanitize($row['notes']); ?></td>
                    <td><?php echo $row['shared'] ? 'Yes' : 'No'; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
