<?php
/**
 * IDF port visualizer mock page (no database).
 *
 * Why: compare Vertical (switch odd/even pairs) vs Horizontal (patch panel L→R) using production layout rules.
 *
 * Usage: open scripts/test_visualizer_v2.php in the browser (Admin session) or
 *        php scripts/test_visualizer_v2.php > test_visualizer_v2.html
 */

declare(strict_types=1);

function sanitize($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/../modules/idfs/port_visualizer_helper.php';
require_once __DIR__ . '/lib/script_cli_output.php';

itm_script_output_begin('IDF Port Visualizer V2');
itm_script_output_close_pre();

$ports = [];
for ($i = 1; $i <= 48; $i++) {
    $status_color = ($i % 3 === 0) ? '#28a745' : (($i % 3 === 1) ? '#007bff' : '#adb5bd');
    $ports[] = [
        'id' => $i,
        'port_no' => $i,
        'status_color' => $status_color,
        'status_label' => ($i % 3 === 0) ? 'Free' : (($i % 3 === 1) ? 'Up' : 'Unknown'),
        'cable_hex_color' => ($i % 10 === 0) ? '#ffff00' : (($i % 10 === 5) ? '#ff0000' : null),
    ];
}

$closeDiv = '</' . 'div>';

echo '<link rel="stylesheet" href="../css/styles.css">';
echo '<style>
body { padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; }
.card { margin-bottom: 24px; }
.card h2 { margin-top: 0; }
.card p { color: var(--text-secondary, #57606a); margin: 0 0 12px 0; }
</style>';
echo '<h1>IDF Port Visualizer V2 (Relation-based)</h1>';
echo '<div id="captureArea">';

echo '<div class="card">';
echo '<h2>Vertical layout (48 ports)</h2>';
echo '<p>Switch numbering: odd/even pairs per column, wrap after 24 paired positions (expected grid: 24 cols x 2 rows for 48 ports).</p>';
echo itm_render_port_visualizer($ports, ['layout' => 'Vertical', 'base_url' => '../']);
echo $closeDiv;

echo '<div class="card">';
echo '<h2>Horizontal layout (48 ports)</h2>';
echo '<p>Patch panel numbering: left-to-right, wrap every 24 ports (expected grid: 24 cols x 2 rows).</p>';
echo itm_render_port_visualizer($ports, ['layout' => 'Horizontal', 'base_url' => '../']);
echo $closeDiv;

echo $closeDiv;
itm_script_output_end();
