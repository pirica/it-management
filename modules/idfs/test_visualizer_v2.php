<?php
// Standalone mock to avoid DB connection issues in verification environment
function sanitize($s) { return htmlspecialchars((string)$s); }

require_once __DIR__ . '/port_visualizer_helper.php';

$ports = [];
for ($i=1; $i<=48; $i++) {
    // New data structure expects hex strings in these fields
    $status_color = ($i % 3 === 0) ? '#28a745' : (($i % 3 === 1) ? '#007bff' : '#adb5bd');
    $ports[] = [
        'id' => $i,
        'port_no' => $i,
        'status_color' => $status_color,
        'status_label' => ($i % 3 === 0) ? 'Free' : (($i % 3 === 1) ? 'Up' : 'Unknown'),
        'cable_hex_color' => ($i % 10 === 0) ? '#ffff00' : (($i % 10 === 5) ? '#ff0000' : null)
    ];
}

echo '<!DOCTYPE html><html><head><style>
:root {
  --bg-primary: #0d1117;
  --bg-secondary: #161b22;
  --text-primary: #c9d1d9;
  --border: #30363d;
  --btn-primary: #238636;
}
body { background: var(--bg-primary); color: var(--text-primary); font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif; padding: 20px; }
.card { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 6px; padding: 16px; margin-bottom: 20px; }
</style>';
echo '</head><body>';
echo '<h1>IDF Port Visualizer V2 (Relation-based)</h1>';
echo '<div id="captureArea" class="card">';
echo '<h2>Vertical Layout (48 Ports)</h2>';
echo itm_render_port_visualizer($ports, ['rows' => 2, 'layout' => 'Vertical', 'base_url' => '../../']);
echo '<br><br><h2>Horizontal Layout (48 Ports)</h2>';
echo itm_render_port_visualizer($ports, ['rows' => 2, 'layout' => 'Horizontal', 'base_url' => '../../']);
echo '</div>';
echo '</body></html>';
