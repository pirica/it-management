<?php
/**
 * DB Design Diagram Generator
 *
 * Why: provide a drawdb-like visual schema map directly from database.sql without
 * requiring external tooling or framework dependencies.
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

$itm_root_path = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$itm_schema_path = $itm_root_path . 'database.sql';

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

if (!is_file($itm_schema_path)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'database.sql not found at: ' . $itm_schema_path;
    exit;
}

$itm_schema_sql = (string) file_get_contents($itm_schema_path);
$itm_schema = itm_dbdesign_parse_schema($itm_schema_sql);
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
        'source' => 'database.sql',
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
        mermaid.initialize({ startOnLoad: true, theme: 'default', securityLevel: 'loose' });
    </script>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Database SQL Diagram</h1>
        <p class="muted">Generated from <code>database.sql</code> (drawdb-style ER overview).</p>
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
            <span class="zoom-label" id="zoom-value">100%</span>
            <span class="zoom-label">Max 1000% (Ctrl + Wheel)</span>
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
        var diagramViewport = document.getElementById('diagram');

        if (!scaleWrap || !zoomValue || !zoomIn || !zoomOut || !zoomReset || !diagramViewport) {
            return;
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

        renderScale();
    })();
</script>
</body>
</html>
