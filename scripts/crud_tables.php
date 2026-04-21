<?php
/**
 * CRUD Table Mapper
 *
 * Why: quickly audits module-to-table mapping by reading each module's index.php
 * and printing the first $crud_table assignment (if present) with a sidebar link.
 *
 * Usage:
 *   php scripts/crud_tables.php > crud_tables.html
 */

$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$modulesPath = $rootPath . 'modules';

if (!is_dir($modulesPath)) {
    fwrite(STDERR, "Modules directory not found: {$modulesPath}\n");
    exit(1);
}

$moduleDirs = array_values(array_filter(scandir($modulesPath), static function ($entry) use ($modulesPath) {
    return $entry !== '.' && $entry !== '..' && is_dir($modulesPath . DIRECTORY_SEPARATOR . $entry);
}));

sort($moduleDirs, SORT_NATURAL | SORT_FLAG_CASE);

header('Content-Type: text/html; charset=UTF-8');

$rows = [];

foreach ($moduleDirs as $moduleName) {
    $indexPath = $modulesPath . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'index.php';
    $crudLineNumber = null;
    $crudLineText = null;

    if (is_file($indexPath)) {
        $lines = @file($indexPath);
        if (is_array($lines)) {
            foreach ($lines as $lineNumber => $lineText) {
                if (preg_match('/\$crud_table\s*=/', $lineText)) {
                    $crudLineNumber = $lineNumber + 1;
                    $crudLineText = trim($lineText);
                    break;
                }
            }
        }
    }

    $rows[] = [
        'module' => $moduleName,
        'crud_line_number' => $crudLineNumber,
        'crud_line_text' => $crudLineText,
        'sidebar_link' => 'modules/' . $moduleName . '/',
    ];
}

echo '<!doctype html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>CRUD Table Mapper</title>';
echo '<style>';
echo 'body{font-family:Arial,sans-serif;background:#f6f8fa;color:#24292f;padding:20px;}';
echo '.wrap{max-width:1200px;margin:0 auto;}';
echo 'h1{margin:0 0 8px 0;}';
echo 'p{margin:0 0 16px 0;color:#57606a;}';
echo 'table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d0d7de;}';
echo 'thead th{background:#f6f8fa;text-align:left;padding:10px;border-bottom:1px solid #d0d7de;}';
echo 'tbody td{padding:10px;border-top:1px solid #d8dee4;vertical-align:top;}';
echo 'tbody tr:nth-child(even){background:#f8fafc;}';
echo '.ok{color:#116329;font-weight:700;}';
echo '.missing{color:#cf222e;font-weight:700;}';
echo 'code{background:#f6f8fa;padding:2px 6px;border-radius:4px;}';
echo 'a{color:#0969da;text-decoration:none;}';
echo 'a:hover{text-decoration:underline;}';
echo '</style>';
echo '</head>';
echo '<body>';
echo '<div class="wrap">';
echo '<h1>CRUD Table Mapper</h1>';
echo '<p>Total modules scanned: <strong>' . count($rows) . '</strong></p>';
echo '<table>';
echo '<thead><tr><th>#</th><th>Module</th><th>CRUD Mapping</th><th>Status</th><th>Sidebar Link</th></tr></thead>';
echo '<tbody>';

foreach ($rows as $index => $row) {
    $mappingText = '(not set in index.php)';
    $statusClass = 'missing';
    $statusLabel = 'Missing';

    if ($row['crud_line_number'] !== null && $row['crud_line_text'] !== null) {
        $mappingText = 'line ' . (int) $row['crud_line_number'] . ': ' . htmlspecialchars($row['crud_line_text'], ENT_QUOTES, 'UTF-8');
        $statusClass = 'ok';
        $statusLabel = 'Found';
    }

    $moduleEscaped = htmlspecialchars($row['module'], ENT_QUOTES, 'UTF-8');
    $sidebarEscaped = htmlspecialchars($row['sidebar_link'], ENT_QUOTES, 'UTF-8');

    echo '<tr>';
    echo '<td>' . ($index + 1) . '</td>';
    echo '<td><strong>' . $moduleEscaped . '</strong></td>';
    echo '<td><code>' . $mappingText . '</code></td>';
    echo '<td><span class="' . $statusClass . '">' . $statusLabel . '</span></td>';
    echo '<td><a href="../' . $sidebarEscaped . '" target=blank>' . $sidebarEscaped . '</a></td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';
echo '</body>';
echo '</html>';
