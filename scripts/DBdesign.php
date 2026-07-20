<?php
/**
 * DB Design Diagram Generator
 *
 * Why: provide a drawdb-like visual schema map from the live database when
 * available, with db/01_schema.sql as fallback.
 *
 * Usage (CLI):
 *   php scripts/DBdesign.php --mermaid
 *   php scripts/DBdesign.php --json
 *
 * Usage (Web):
 *   /scripts/DBdesign.php
 *   /scripts/DBdesign.php?format=mermaid
 *   /scripts/DBdesign.php?format=json
 */

declare(strict_types=1);

if (!defined('ITM_CLI_SCRIPT')) {
    define('ITM_CLI_SCRIPT', true);
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/itm_database_sql_source.php';

function itm_dbdesign_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function itm_dbdesign_normalize_column_list(string $raw): array
{
    $parts = preg_split('/\s*,\s*/', trim($raw));
    if (!is_array($parts)) {
        return [];
    }

    $columns = [];
    foreach ($parts as $part) {
        $clean = trim($part);
        $clean = trim($clean, "` \t\r\n");
        if ($clean !== '') {
            $columns[] = $clean;
        }
    }

    return $columns;
}

function itm_dbdesign_parse_schema(string $sql): array
{
    $tables = [];
    $relationships = [];

    if (!preg_match_all('/CREATE\s+TABLE\s+`([^`]+)`\s*\((.*?)\)\s*ENGINE\s*=\s*/si', $sql, $tableMatches, PREG_SET_ORDER)) {
        return ['tables' => $tables, 'relationships' => $relationships];
    }

    foreach ($tableMatches as $tableMatch) {
        $tableName = $tableMatch[1];
        $tableBody = $tableMatch[2];

        $table = [
            'name' => $tableName,
            'columns' => [],
            'primary_keys' => [],
        ];

        $lines = preg_split('/\r\n|\n|\r/', $tableBody);
        if (!is_array($lines)) {
            $lines = [];
        }

        foreach ($lines as $lineRaw) {
            $line = trim($lineRaw);
            if ($line === '') {
                continue;
            }
            $line = rtrim($line, ',');

            if (preg_match('/^`([^`]+)`\s+(.+)$/', $line, $columnMatch)) {
                $columnName = $columnMatch[1];
                $columnDefinition = $columnMatch[2];
                $columnType = strtolower((string) preg_replace('/\s+.*/', '', trim($columnDefinition)));
                $isNotNull = stripos($columnDefinition, 'NOT NULL') !== false;
                $isAutoIncrement = stripos($columnDefinition, 'AUTO_INCREMENT') !== false;

                $table['columns'][$columnName] = [
                    'name' => $columnName,
                    'type' => $columnType,
                    'not_null' => $isNotNull,
                    'auto_increment' => $isAutoIncrement,
                ];
                continue;
            }

            if (preg_match('/^PRIMARY\s+KEY\s*\(([^)]+)\)/i', $line, $pkMatch)) {
                $table['primary_keys'] = itm_dbdesign_normalize_column_list($pkMatch[1]);
                continue;
            }

            if (preg_match('/(?:CONSTRAINT\s+`[^`]+`\s+)?FOREIGN\s+KEY\s*\(([^)]+)\)\s+REFERENCES\s+`([^`]+)`\s*\(([^)]+)\)/i', $line, $fkMatch)) {
                $localColumns = itm_dbdesign_normalize_column_list($fkMatch[1]);
                $refTable = trim($fkMatch[2]);
                $refColumns = itm_dbdesign_normalize_column_list($fkMatch[3]);

                $relationships[] = [
                    'from_table' => $tableName,
                    'to_table' => $refTable,
                    'from_columns' => $localColumns,
                    'to_columns' => $refColumns,
                ];
            }
        }

        $tables[$tableName] = $table;
    }

    foreach ($tables as $tableName => &$tableData) {
        foreach ($tableData['primary_keys'] as $pkColumn) {
            if (isset($tableData['columns'][$pkColumn])) {
                $tableData['columns'][$pkColumn]['is_pk'] = true;
            }
        }
        foreach ($tableData['columns'] as &$columnData) {
            if (!isset($columnData['is_pk'])) {
                $columnData['is_pk'] = false;
            }
        }
        unset($columnData);
    }
    unset($tableData);

    ksort($tables, SORT_NATURAL | SORT_FLAG_CASE);

    return [
        'tables' => $tables,
        'relationships' => $relationships,
    ];
}

function itm_dbdesign_simplify_column_type(string $columnType): string
{
    $columnType = strtolower(trim($columnType));
    if ($columnType === '') {
        return 'text';
    }

    if (preg_match('/^([a-z]+)/', $columnType, $match) === 1) {
        return $match[1];
    }

    return 'text';
}

/**
 * @return array{tables:array<string,array<string,mixed>>,relationships:list<array<string,mixed>>}
 */
function itm_dbdesign_load_from_database(mysqli $conn, string $database): array
{
    $tables = [];
    $relationships = [];

    $columnStmt = $conn->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ?
         ORDER BY TABLE_NAME, ORDINAL_POSITION'
    );
    if (!$columnStmt) {
        return ['tables' => $tables, 'relationships' => $relationships];
    }

    $columnStmt->bind_param('s', $database);
    $columnStmt->execute();
    $columnResult = $columnStmt->get_result();

    while ($row = $columnResult->fetch_assoc()) {
        $tableName = (string) ($row['TABLE_NAME'] ?? '');
        $columnName = (string) ($row['COLUMN_NAME'] ?? '');
        if ($tableName === '' || $columnName === '') {
            continue;
        }

        if (!isset($tables[$tableName])) {
            $tables[$tableName] = [
                'name' => $tableName,
                'columns' => [],
                'primary_keys' => [],
            ];
        }

        $columnKey = (string) ($row['COLUMN_KEY'] ?? '');
        $extra = strtolower((string) ($row['EXTRA'] ?? ''));
        $isPk = $columnKey === 'PRI';

        $tables[$tableName]['columns'][$columnName] = [
            'name' => $columnName,
            'type' => itm_dbdesign_simplify_column_type((string) ($row['COLUMN_TYPE'] ?? '')),
            'not_null' => strtoupper((string) ($row['IS_NULLABLE'] ?? '')) === 'NO',
            'auto_increment' => strpos($extra, 'auto_increment') !== false,
            'is_pk' => $isPk,
        ];

        if ($isPk) {
            $tables[$tableName]['primary_keys'][] = $columnName;
        }
    }

    $columnStmt->close();

    $fkStmt = $conn->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME, CONSTRAINT_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION'
    );
    if ($fkStmt) {
        $fkStmt->bind_param('s', $database);
        $fkStmt->execute();
        $fkResult = $fkStmt->get_result();
        $fkGroups = [];

        while ($row = $fkResult->fetch_assoc()) {
            $constraint = (string) ($row['CONSTRAINT_NAME'] ?? '');
            $fromTable = (string) ($row['TABLE_NAME'] ?? '');
            $groupKey = $constraint . '|' . $fromTable;
            if (!isset($fkGroups[$groupKey])) {
                $fkGroups[$groupKey] = [
                    'from_table' => $fromTable,
                    'to_table' => (string) ($row['REFERENCED_TABLE_NAME'] ?? ''),
                    'from_columns' => [],
                    'to_columns' => [],
                ];
            }
            $fkGroups[$groupKey]['from_columns'][] = (string) ($row['COLUMN_NAME'] ?? '');
            $fkGroups[$groupKey]['to_columns'][] = (string) ($row['REFERENCED_COLUMN_NAME'] ?? '');
        }

        $relationships = array_values($fkGroups);
        $fkStmt->close();
    }

    ksort($tables, SORT_NATURAL | SORT_FLAG_CASE);

    return [
        'tables' => $tables,
        'relationships' => $relationships,
    ];
}

/**
 * @return array{schema:array{tables:array<string,array<string,mixed>>,relationships:list<array<string,mixed>>},source:string}
 */
function itm_dbdesign_resolve_schema(): array
{
    global $conn;

    if (isset($conn) && $conn instanceof mysqli) {
        $database = defined('DB_NAME') && DB_NAME !== '' ? (string) DB_NAME : 'itmanagement';
        $liveSchema = itm_dbdesign_load_from_database($conn, $database);
        if (!empty($liveSchema['tables'])) {
            return [
                'schema' => $liveSchema,
                'source' => 'live database (' . $database . ')',
            ];
        }
    }

    $schemaPath = itm_database_sql_schema_path();
    if (!is_file($schemaPath)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Schema unavailable: live database has no tables and db/01_schema.sql was not found at: ' . $schemaPath;
        exit;
    }

    $schemaSql = (string) file_get_contents($schemaPath);

    return [
        'schema' => itm_dbdesign_parse_schema($schemaSql),
        'source' => 'db/01_schema.sql',
    ];
}

function itm_dbdesign_to_mermaid(array $schema): string
{
    $lines = [];
    $lines[] = 'erDiagram';

    $tables = $schema['tables'] ?? [];
    foreach ($tables as $table) {
        $tableName = (string) ($table['name'] ?? '');
        if ($tableName === '') {
            continue;
        }

        $lines[] = '    ' . $tableName . ' {';

        foreach (($table['columns'] ?? []) as $column) {
            $type = strtoupper((string) ($column['type'] ?? 'TEXT'));
            $type = preg_replace('/[^A-Z0-9_]/', '_', $type);
            if ($type === null || $type === '') {
                $type = 'TEXT';
            }

            $name = (string) ($column['name'] ?? 'column');
            $flag = '';
            if (!empty($column['is_pk'])) {
                $flag .= ' PK';
            }
            $lines[] = '        ' . $type . ' ' . $name . $flag;
        }

        $lines[] = '    }';
    }

    foreach (($schema['relationships'] ?? []) as $relationship) {
        $fromTable = (string) ($relationship['from_table'] ?? '');
        $toTable = (string) ($relationship['to_table'] ?? '');
        if ($fromTable === '' || $toTable === '') {
            continue;
        }

        $fromColumns = (array) ($relationship['from_columns'] ?? []);
        $toColumns = (array) ($relationship['to_columns'] ?? []);
        $labelParts = $fromColumns;
        if (!empty($toColumns)) {
            $labelParts = array_merge($labelParts, $toColumns);
        }
        $label = implode('_', $labelParts);
        $label = preg_replace('/[^A-Za-z0-9_]/', '_', $label ?? '');
        if (!is_string($label) || $label === '') {
            $label = 'fk';
        }

        $lines[] = '    ' . $toTable . ' ||--o{ ' . $fromTable . ' : ' . $label;
    }

    return implode("\n", $lines);
}

if (PHP_SAPI !== 'cli' && isset($conn) && $conn instanceof mysqli) {
    require_once __DIR__ . '/lib/script_cli_output.php';
    itm_script_require_admin_script_or_exit($conn);
}

$itm_schema_bundle = itm_dbdesign_resolve_schema();
$itm_schema = $itm_schema_bundle['schema'];
$itm_schema_source = $itm_schema_bundle['source'];
$itm_mermaid = itm_dbdesign_to_mermaid($itm_schema);

$itm_cli = PHP_SAPI === 'cli';
$itm_format = 'html';

if ($itm_cli) {
    global $argv;
    $args = is_array($argv) ? $argv : [];
    if (in_array('--json', $args, true)) {
        $itm_format = 'json';
    } elseif (in_array('--mermaid', $args, true)) {
        $itm_format = 'mermaid';
    }
} else {
    if (isset($_GET['format'])) {
        $requestedFormat = strtolower((string) $_GET['format']);
        if (in_array($requestedFormat, ['html', 'json', 'mermaid'], true)) {
            $itm_format = $requestedFormat;
        }
    }
}

if ($itm_format === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'source' => $itm_schema_source,
        'table_count' => count($itm_schema['tables']),
        'relationship_count' => count($itm_schema['relationships']),
        'tables' => $itm_schema['tables'],
        'relationships' => $itm_schema['relationships'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($itm_format === 'mermaid') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo $itm_mermaid;
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
$itm_table_count = (int) count($itm_schema['tables']);
$itm_relationship_count = (int) count($itm_schema['relationships']);
$itm_generated_at = gmdate('Y-m-d H:i:s') . ' UTC';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DB Design Diagram</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f6f8fa; color: #1f2328; margin: 0; }
        .wrap { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .card { background: #ffffff; border: 1px solid #d0d7de; border-radius: 8px; margin-bottom: 16px; padding: 16px; }
        .muted { color: #57606a; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .toolbar a { color: #0969da; text-decoration: none; }
        .toolbar a:hover { text-decoration: underline; }
        .btn { border: 1px solid #d0d7de; border-radius: 6px; background: #f6f8fa; color: #1f2328; padding: 6px 10px; cursor: pointer; }
        .btn:hover { background: #eef2f7; }
        #diagram { overflow: auto; min-height: 400px; border: 1px solid #d0d7de; border-radius: 6px; padding: 10px; background: #fff; }
        #diagram-scale { transform-origin: top left; transition: transform 0.1s ease-in-out; }
        .zoom-label { font-size: 0.9rem; color: #57606a; min-width: 72px; }
        textarea { width: 100%; min-height: 220px; border: 1px solid #d0d7de; border-radius: 6px; padding: 10px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    </style>
    <script type="module">
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs';
        mermaid.initialize({ startOnLoad: true, theme: 'default', securityLevel: 'loose', maxTextSize: 1000000 });
    </script>
</head>
<body>
<?php
require_once __DIR__ . '/lib/script_browser_nav.php';
$itm_dbdesign_base = defined('BASE_URL') ? (string)BASE_URL : '../';
?>
<div class="wrap">
    <?php itm_script_browser_nav_echo($itm_dbdesign_base); ?>
    <div class="card">
        <h1>Database SQL Diagram</h1>
        <p class="muted">Generated from <code><?= itm_dbdesign_escape($itm_schema_source); ?></code> (drawdb-style ER overview).</p>
        <p class="muted">Tables: <strong><?= $itm_table_count; ?></strong> | Relationships: <strong><?= $itm_relationship_count; ?></strong> | Generated: <?= itm_dbdesign_escape($itm_generated_at); ?></p>
        <div class="toolbar">
            <a href="?format=mermaid" target="_blank">Open Mermaid Text</a>
            <a href="?format=json" target="_blank">Open JSON</a>
            <a href="?format=html">Refresh Diagram</a>
        </div>
    </div>

    <div class="card">
        <h2>Rendered Diagram</h2>
        <div class="toolbar" style="margin-bottom: 10px;">
            <button type="button" class="btn" id="zoom-out">− Zoom Out</button>
            <button type="button" class="btn" id="zoom-in">+ Zoom In</button>
            <button type="button" class="btn" id="zoom-reset">Reset</button>
            <button type="button" class="btn" id="export-svg">Export SVG</button>
            <button type="button" class="btn" id="export-png">Export PNG</button>
            <span class="zoom-label" id="zoom-value">100%</span>
            <span class="zoom-label">Max 1000% (Ctrl + Wheel, export uses current zoom)</span>
        </div>
        <div id="diagram">
            <div id="diagram-scale">
                <pre class="mermaid"><?= itm_dbdesign_escape($itm_mermaid); ?></pre>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Mermaid Source</h2>
        <textarea readonly><?= itm_dbdesign_escape($itm_mermaid); ?></textarea>
    </div>
</div>
<script>
    (function () {
        var scale = 1;
        var minScale = 0.4;
        var maxScale = 10;
        var step = 0.2;
        var scaleWrap = document.getElementById('diagram-scale');
        var zoomValue = document.getElementById('zoom-value');
        var zoomIn = document.getElementById('zoom-in');
        var zoomOut = document.getElementById('zoom-out');
        var zoomReset = document.getElementById('zoom-reset');
        var exportSvgButton = document.getElementById('export-svg');
        var exportPngButton = document.getElementById('export-png');
        var diagramViewport = document.getElementById('diagram');

        if (!scaleWrap || !zoomValue || !zoomIn || !zoomOut || !zoomReset || !exportSvgButton || !exportPngButton || !diagramViewport) {
            return;
        }

        function getMermaidSvgElement() {
            return diagramViewport.querySelector('svg');
        }

        // Why: browsers reject canvas.toBlob() when width*height exceeds safe limits (large ER + zoom).
        var maxExportSidePx = 8192;
        var maxExportPixelArea = 16777216;

        function readSvgViewBoxSize(svgElement) {
            var width = 0;
            var height = 0;

            if (svgElement.viewBox && svgElement.viewBox.baseVal) {
                width = svgElement.viewBox.baseVal.width;
                height = svgElement.viewBox.baseVal.height;
            }

            if (!(width > 0) || !(height > 0)) {
                var viewBox = svgElement.getAttribute('viewBox');
                if (viewBox) {
                    var vb = viewBox.trim().split(/\s+/);
                    if (vb.length === 4) {
                        width = parseFloat(vb[2]);
                        height = parseFloat(vb[3]);
                    }
                }
            }

            if (!(width > 0) || !(height > 0)) {
                width = parseFloat(svgElement.getAttribute('width')) || 1200;
                height = parseFloat(svgElement.getAttribute('height')) || 800;
            }

            return {
                width: Math.max(1, width),
                height: Math.max(1, height)
            };
        }

        function resolveExportSize(svgElement, exportScale) {
            var scaleForExport = Number(exportScale || 1);
            if (!isFinite(scaleForExport) || scaleForExport <= 0) {
                scaleForExport = 1;
            }

            var base = readSvgViewBoxSize(svgElement);
            var width = Math.ceil(base.width * scaleForExport);
            var height = Math.ceil(base.height * scaleForExport);
            var capped = false;

            if (width > maxExportSidePx) {
                height = Math.ceil(height * (maxExportSidePx / width));
                width = maxExportSidePx;
                capped = true;
            }
            if (height > maxExportSidePx) {
                width = Math.ceil(width * (maxExportSidePx / height));
                height = maxExportSidePx;
                capped = true;
            }
            while (width * height > maxExportPixelArea) {
                width = Math.max(1, Math.floor(width * 0.85));
                height = Math.max(1, Math.floor(height * 0.85));
                capped = true;
            }

            return {
                width: Math.max(1, width),
                height: Math.max(1, height),
                capped: capped
            };
        }

        function getSerializedSvg(svgElement, exportScale, exportSize) {
            var serializer = new XMLSerializer();
            var cloned = svgElement.cloneNode(true);
            if (!cloned.getAttribute('xmlns')) {
                cloned.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
            }
            if (!cloned.getAttribute('xmlns:xlink')) {
                cloned.setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');
            }

            if (exportSize && exportSize.width > 0 && exportSize.height > 0) {
                cloned.setAttribute('width', String(Math.ceil(exportSize.width)));
                cloned.setAttribute('height', String(Math.ceil(exportSize.height)));
            } else {
                var scaleForExport = Number(exportScale || 1);
                if (!isFinite(scaleForExport) || scaleForExport <= 0) {
                    scaleForExport = 1;
                }

                var viewBox = cloned.getAttribute('viewBox');
                if (viewBox) {
                    var vb = viewBox.trim().split(/\s+/);
                    if (vb.length === 4) {
                        var vbWidth = parseFloat(vb[2]);
                        var vbHeight = parseFloat(vb[3]);
                        if (isFinite(vbWidth) && vbWidth > 0 && isFinite(vbHeight) && vbHeight > 0) {
                            cloned.setAttribute('width', String(Math.ceil(vbWidth * scaleForExport)));
                            cloned.setAttribute('height', String(Math.ceil(vbHeight * scaleForExport)));
                        }
                    }
                }
            }

            return serializer.serializeToString(cloned);
        }

        function svgMarkupToDataUrl(svgText) {
            return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgText);
        }

        function downloadPngFromCanvas(canvas, wasCapped) {
            function finishWithBlob(blob) {
                if (blob) {
                    triggerDownload(blob, 'database-diagram.png');
                    if (wasCapped) {
                        alert('PNG export was resized to fit browser limits. Use Export SVG for the full-resolution diagram.');
                    }
                    return;
                }
                downloadPngFromDataUrl(canvas, wasCapped);
            }

            if (canvas.toBlob) {
                canvas.toBlob(function (pngBlob) {
                    finishWithBlob(pngBlob);
                }, 'image/png', 0.92);
                return;
            }

            downloadPngFromDataUrl(canvas, wasCapped);
        }

        function downloadPngFromDataUrl(canvas, wasCapped) {
            try {
                var dataUrl = canvas.toDataURL('image/png');
                if (!dataUrl || dataUrl === 'data:,') {
                    throw new Error('empty data url');
                }
                var parts = dataUrl.split(',');
                if (parts.length < 2) {
                    throw new Error('invalid data url');
                }
                var binary = atob(parts[1]);
                var bytes = new Uint8Array(binary.length);
                for (var i = 0; i < binary.length; i++) {
                    bytes[i] = binary.charCodeAt(i);
                }
                triggerDownload(new Blob([bytes], { type: 'image/png' }), 'database-diagram.png');
                if (wasCapped) {
                    alert('PNG export was resized to fit browser limits. Use Export SVG for the full-resolution diagram.');
                }
            } catch (exportError) {
                alert('Unable to create PNG from the diagram. It may be too large — zoom out, or use Export SVG instead.');
            }
        }

        function rasterizeSvgToPng(svgElement, exportScale) {
            var exportSize = resolveExportSize(svgElement, exportScale);
            var svgText = getSerializedSvg(svgElement, exportScale, exportSize);
            var image = new Image();
            var dataUrl = svgMarkupToDataUrl(svgText);

            function drawToCanvas() {
                var width = exportSize.width;
                var height = exportSize.height;
                var canvas = document.createElement('canvas');
                var context = canvas.getContext('2d');
                canvas.width = width;
                canvas.height = height;
                if (!context) {
                    alert('Canvas rendering is not available in this browser.');
                    return;
                }
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, width, height);
                context.drawImage(image, 0, 0, width, height);
                downloadPngFromCanvas(canvas, exportSize.capped);
            }

            image.onload = function () {
                if (image.decode) {
                    image.decode().then(drawToCanvas).catch(drawToCanvas);
                    return;
                }
                drawToCanvas();
            };

            image.onerror = function () {
                alert('Unable to render PNG. Try Export SVG instead.');
            };

            image.src = dataUrl;
        }

        function triggerDownload(blob, filename) {
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        function renderScale() {
            scaleWrap.style.transform = 'scale(' + scale.toFixed(2) + ')';
            zoomValue.textContent = Math.round(scale * 100) + '%';
        }

        zoomIn.addEventListener('click', function () {
            scale = Math.min(maxScale, scale + step);
            renderScale();
        });

        zoomOut.addEventListener('click', function () {
            scale = Math.max(minScale, scale - step);
            renderScale();
        });

        zoomReset.addEventListener('click', function () {
            scale = 1;
            renderScale();
        });

        diagramViewport.addEventListener('wheel', function (event) {
            if (!event.ctrlKey) {
                return;
            }
            event.preventDefault();
            if (event.deltaY < 0) {
                scale = Math.min(maxScale, scale + step);
            } else {
                scale = Math.max(minScale, scale - step);
            }
            renderScale();
        }, { passive: false });

        exportSvgButton.addEventListener('click', function () {
            var svgElement = getMermaidSvgElement();
            if (!svgElement) {
                alert('Diagram not ready yet. Please wait a moment and try again.');
                return;
            }

            var svgText = getSerializedSvg(svgElement, scale);
            triggerDownload(new Blob([svgText], { type: 'image/svg+xml;charset=utf-8' }), 'database-diagram.svg');
        });

        exportPngButton.addEventListener('click', function () {
            var svgElement = getMermaidSvgElement();
            if (!svgElement) {
                alert('Diagram not ready yet. Please wait a moment and try again.');
                return;
            }

            rasterizeSvgToPng(svgElement, scale);
        });

        renderScale();
    })();
</script>
</body>
</html>
