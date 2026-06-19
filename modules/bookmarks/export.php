<?php
require '../../config/config.php';
require './helpers.php';

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id = (int)($_SESSION['employee_id'] ?? 0);

if ($company_id <= 0) {
    header('Location: ../../index.php');
    return;
}

$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;

$where = "company_id = $company_id AND (employee_id = $user_id OR shared = 1) AND active = 1";
if ($folder_id) {
    $where .= " AND folder_id = $folder_id";
}

$sql = "SELECT title, url, notes, shared FROM bookmarks WHERE $where ORDER BY position ASC, title ASC";
$res = mysqli_query($conn, $sql);
$data = [];
while ($res && ($row = mysqli_fetch_assoc($res))) {
    $data[] = $row;
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=bookmarks.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Title', 'URL', 'Notes', 'Shared']);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    return;
} elseif ($format === 'txt') {
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
} elseif ($format === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename=bookmarks.html');
    echo "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n";
    echo "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n";
    echo "<TITLE>Bookmarks</TITLE>\n";
    echo "<H1>Bookmarks</H1>\n";
    echo "<DL><p>\n";
    foreach ($data as $row) {
        echo "    <DT><A HREF=\"" . sanitize($row['url']) . "\">" . sanitize($row['title']) . "</A>\n";
        if ($row['notes']) {
            echo "    <DD>" . sanitize($row['notes']) . "\n";
        }
    }
    echo "</DL><p>\n";
    return;
}

// Fallback or print view for PDF
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Export Bookmarks</title>
    <style>
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body onload="window.print()">
    <h1>Bookmarks Export</h1>
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
