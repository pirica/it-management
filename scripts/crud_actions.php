<?php
/**
 * CRUD Action Mapper
 *
 * Why: audits module-to-action mapping by reading each module entry file
 * (index.php and CRUD wrappers) for $crud_action assignments with sidebar links.
 *
 * Usage:
 *   php scripts/crud_actions.php > crud_actions.html
 */

declare(strict_types=1);

$rootPath = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$modulesPath = $rootPath . 'modules';

if (!is_dir($modulesPath)) {
    $message = "Modules directory not found: {$modulesPath}\n";
    if (PHP_SAPI === 'cli' && defined('STDERR')) {
        fwrite(STDERR, $message);
    }
    exit(1);
}

$moduleDirs = array_values(array_filter(scandir($modulesPath), static function ($entry) use ($modulesPath) {
    return $entry !== '.' && $entry !== '..' && is_dir($modulesPath . DIRECTORY_SEPARATOR . $entry);
}));

sort($moduleDirs, SORT_NATURAL | SORT_FLAG_CASE);

if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';
require_once __DIR__ . '/lib/script_browser_nav.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

/**
 * @return array{line:int,text:string,literal:?string,is_coalesce:bool}|null
 */
function itm_crud_actions_parse_assignment(string $filePath): ?array
{
    $lines = @file($filePath);
    if (!is_array($lines)) {
        return null;
    }

    foreach ($lines as $lineNumber => $lineText) {
        if (!preg_match('/\$crud_action\s*=\s*(.+);/', $lineText, $matches)) {
            continue;
        }

        $rhs = trim((string)$matches[1]);
        $literal = null;
        if (preg_match("/^['\"]([^'\"]+)['\"]$/", $rhs, $literalMatch)) {
            $literal = (string)$literalMatch[1];
        }

        return [
            'line' => $lineNumber + 1,
            'text' => trim($lineText),
            'literal' => $literal,
            'is_coalesce' => strpos($rhs, '??') !== false,
        ];
    }

    return null;
}

itm_script_output_begin('CRUD Action Mapper');

$entryFiles = ['index.php', 'create.php', 'edit.php', 'view.php', 'delete.php', 'list_all.php'];
$rows = [];

foreach ($moduleDirs as $moduleName) {
    $modulePath = $modulesPath . DIRECTORY_SEPARATOR . $moduleName;
    $moduleRows = [];

    foreach ($entryFiles as $entryFile) {
        $filePath = $modulePath . DIRECTORY_SEPARATOR . $entryFile;
        if (!is_file($filePath)) {
            continue;
        }

        $assignment = itm_crud_actions_parse_assignment($filePath);
        if ($assignment === null) {
            continue;
        }

        if ($assignment['literal'] !== null) {
            $statusClass = 'ok';
            $statusLabel = 'Found';
        } elseif ($assignment['is_coalesce'] && $entryFile === 'index.php') {
            $statusClass = 'default';
            $statusLabel = 'Default';
        } else {
            $statusClass = 'default';
            $statusLabel = 'Default';
        }

        $moduleRows[] = [
            'module' => $moduleName,
            'entry_file' => $entryFile,
            'crud_line_number' => $assignment['line'],
            'crud_line_text' => $assignment['text'],
            'status_class' => $statusClass,
            'status_label' => $statusLabel,
            'sidebar_link' => 'modules/' . $moduleName . '/',
        ];
    }

    if ($moduleRows === []) {
        $moduleRows[] = [
            'module' => $moduleName,
            'entry_file' => '(none)',
            'crud_line_number' => null,
            'crud_line_text' => null,
            'status_class' => 'na',
            'status_label' => 'N/A',
            'sidebar_link' => 'modules/' . $moduleName . '/',
        ];
    }

    foreach ($moduleRows as $moduleRow) {
        $rows[] = $moduleRow;
    }
}

itm_script_output_close_pre();

echo '<!doctype html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="utf-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>CRUD Action Mapper</title>';
echo '<style>';
echo 'body{font-family:Arial,sans-serif;background:#f6f8fa;color:#24292f;padding:20px;}';
echo '.wrap{max-width:1200px;margin:0 auto;overflow-x:auto;}';
echo 'h1{margin:0 0 8px 0;}';
echo 'p{margin:0 0 16px 0;color:#57606a;}';
echo 'table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d0d7de;}';
echo 'thead th{background:#f6f8fa;text-align:left;padding:10px;border-bottom:1px solid #d0d7de;white-space:nowrap;}';
echo 'tbody td{padding:10px;border-top:1px solid #d8dee4;vertical-align:top;white-space:nowrap;}';
echo 'tbody tr:nth-child(even){background:#f8fafc;}';
echo '.ok{color:#116329;font-weight:700;}';
echo '.default{color:#0969da;font-weight:700;}';
echo '.na{color:#57606a;font-weight:700;}';
echo '.missing{color:#cf222e;font-weight:700;display:inline-block;}';
echo 'code{background:#f6f8fa;padding:2px 6px;border-radius:4px;}';
echo 'a{color:#0969da;text-decoration:none;}';
echo 'a:hover{text-decoration:underline;}';
echo '</style>';
echo '</head>';
echo '<body>';
echo '<div class="wrap">';
echo '<h1>CRUD Action Mapper</h1>';
echo '<p>Modules scanned: <strong>' . count($moduleDirs) . '</strong> | Rows: <strong>' . count($rows) . '</strong></p>';
echo '<table>';
echo '<thead><tr><th>#</th><th>Module</th><th>Entry file</th><th>CRUD Mapping</th><th>Status</th><th>Sidebar Link</th></tr></thead>';
echo '<tbody>';

foreach ($rows as $index => $row) {
    $mappingText = '(not set)';
    if ($row['crud_line_number'] !== null && $row['crud_line_text'] !== null) {
        $mappingText = 'line ' . (int)$row['crud_line_number'] . ': ' . htmlspecialchars($row['crud_line_text'], ENT_QUOTES, 'UTF-8');
    }

    $moduleEscaped = htmlspecialchars($row['module'], ENT_QUOTES, 'UTF-8');
    $entryEscaped = htmlspecialchars($row['entry_file'], ENT_QUOTES, 'UTF-8');
    $sidebarEscaped = htmlspecialchars($row['sidebar_link'], ENT_QUOTES, 'UTF-8');
    $statusClass = htmlspecialchars($row['status_class'], ENT_QUOTES, 'UTF-8');
    $statusLabel = htmlspecialchars($row['status_label'], ENT_QUOTES, 'UTF-8');

    echo '<tr>';
    echo '<td>' . ($index + 1) . '</td>';
    echo '<td><strong>' . $moduleEscaped . '</strong></td>';
    echo '<td><code>' . $entryEscaped . '</code></td>';
    echo '<td><code>' . $mappingText . '</code></td>';
    echo '<td><span class="' . $statusClass . '">' . $statusLabel . '</span></td>';
    $moduleHref = '../' . $sidebarEscaped;
    echo '<td>' . itm_script_external_link_html($moduleHref, $sidebarEscaped) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';
itm_script_output_end();
