<?php
/**
 * CRUD Table Mapper
 *
 * Why: quickly audits module-to-table mapping by reading each module's index.php
 * and printing the first $crud_table assignment (if present) with a sidebar link.
 * Bespoke modules (docs/list_bespoke_UI.txt + scripts/data/crud_tables_skip_modules.txt)
 * without $crud_table are reported as Skip — not Missing. No database table checks.
 *
 * Usage:
 *   php scripts/crud_tables.php > crud_tables.html
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
require_once __DIR__ . '/lib/itm_crud_tables_audit.php';

itm_script_require_admin_script_or_exit($conn, 'Access denied. Administrator privileges required.');

$skipModules = array_fill_keys(itm_crud_tables_load_skip_module_slugs($rootPath), true);

itm_script_output_begin('CRUD Table Mapper');

$rows = [];
$counts = ['found' => 0, 'skip' => 0, 'missing' => 0];

foreach ($moduleDirs as $moduleName) {
    $indexPath = $modulesPath . DIRECTORY_SEPARATOR . $moduleName . DIRECTORY_SEPARATOR . 'index.php';
    $assignment = is_file($indexPath) ? itm_crud_tables_detect_assignment($indexPath) : null;

    $statusClass = 'missing';
    $statusLabel = 'Missing';
    $mappingText = '(not set in index.php)';

    if ($assignment !== null) {
        $mappingText = 'line ' . (int)$assignment['line'] . ': ' . $assignment['text'];
        $statusClass = 'ok';
        $statusLabel = 'Found';
        $counts['found']++;
    } elseif (isset($skipModules[$moduleName])) {
        $statusClass = 'skip';
        $statusLabel = 'Skip';
        $mappingText = '(bespoke / exception — no $crud_table expected)';
        $counts['skip']++;
    } else {
        $counts['missing']++;
    }

    $rows[] = [
        'module' => $moduleName,
        'mapping_text' => $mappingText,
        'status_class' => $statusClass,
        'status_label' => $statusLabel,
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
echo '.wrap{max-width:1200px;margin:0 auto;overflow-x:auto;}';
echo 'h1{margin:0 0 8px 0;}';
echo 'p{margin:0 0 16px 0;color:#57606a;}';
echo 'table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d0d7de;}';
echo 'thead th{background:#f6f8fa;text-align:left;padding:10px;border-bottom:1px solid #d0d7de;white-space:nowrap;}';
echo 'tbody td{padding:10px;border-top:1px solid #d8dee4;vertical-align:top;white-space:nowrap;}';
echo 'tbody tr:nth-child(even){background:#f8fafc;}';
echo '.ok{color:#116329;font-weight:700;}';
echo '.skip{color:#57606a;font-weight:700;}';
echo '.missing{color:#cf222e;font-weight:700;display:inline-block;}';
echo 'code{background:#f6f8fa;padding:2px 6px;border-radius:4px;}';
echo 'a{color:#0969da;text-decoration:none;}';
echo 'a:hover{text-decoration:underline;}';
echo '</style>';
echo '</head>';
echo '<body>';
echo '<div class="wrap">';
echo '<h1>CRUD Table Mapper</h1>';
echo '<p>Modules scanned: <strong>' . count($rows) . '</strong>'
    . ' · Found: <strong>' . (int)$counts['found'] . '</strong>'
    . ' · Skip: <strong>' . (int)$counts['skip'] . '</strong>'
    . ' · Missing: <strong>' . (int)$counts['missing'] . '</strong></p>';
echo '<p>Skip inventory: <code>docs/list_bespoke_UI.txt</code> + <code>scripts/data/crud_tables_skip_modules.txt</code> (index.php <code>$crud_table</code> only — no database checks).</p>';
echo '<table>';
echo '<thead><tr><th>#</th><th>Module</th><th>CRUD Mapping</th><th>Status</th><th>Sidebar Link</th></tr></thead>';
echo '<tbody>';

foreach ($rows as $index => $row) {
    $moduleEscaped = htmlspecialchars($row['module'], ENT_QUOTES, 'UTF-8');
    $sidebarEscaped = htmlspecialchars($row['sidebar_link'], ENT_QUOTES, 'UTF-8');
    $mappingEscaped = htmlspecialchars($row['mapping_text'], ENT_QUOTES, 'UTF-8');
    $statusClass = htmlspecialchars($row['status_class'], ENT_QUOTES, 'UTF-8');
    $statusLabel = htmlspecialchars($row['status_label'], ENT_QUOTES, 'UTF-8');

    echo '<tr>';
    echo '<td>' . ($index + 1) . '</td>';
    echo '<td><strong>' . $moduleEscaped . '</strong></td>';
    echo '<td><code>' . $mappingEscaped . '</code></td>';
    echo '<td><span class="' . $statusClass . '">' . $statusLabel . '</span></td>';
    $moduleHref = '../' . $sidebarEscaped;
    echo '<td>' . itm_script_external_link_html($moduleHref, $sidebarEscaped) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</div>';
itm_script_output_end();
