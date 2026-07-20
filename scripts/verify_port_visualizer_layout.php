<?php
declare(strict_types=1);

function sanitize($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

require __DIR__ . '/../modules/idfs/port_visualizer_helper.php';

$ports = [];
for ($i = 1; $i <= 48; $i++) {
    $ports[] = ['id' => 0, 'port_no' => $i, 'status_color' => '#007bff', 'status_label' => 'Up'];
}

function extract_port_grid(string $html, int $portNo): ?array
{
    $pattern = '/data-port-number="' . $portNo . '"[^>]*style="([^"]*)"/';
    if (!preg_match($pattern, $html, $match)) {
        return null;
    }
    $style = $match[1];
    if (!preg_match('/grid-row:\s*(\d+)/', $style, $rowMatch) || !preg_match('/grid-column:\s*(\d+)/', $style, $colMatch)) {
        return null;
    }

    return ['row' => (int)$rowMatch[1], 'col' => (int)$colMatch[1]];
}

function extract_meta(string $html): array
{
    preg_match('/data-layout="([^"]+)"/', $html, $layout);
    preg_match('/data-grid-cols="(\d+)"/', $html, $cols);
    preg_match('/data-grid-rows="(\d+)"/', $html, $rows);

    return [
        'layout' => $layout[1] ?? '',
        'cols' => (int)($cols[1] ?? 0),
        'rows' => (int)($rows[1] ?? 0),
    ];
}

$failures = 0;

$verticalHtml = itm_render_port_visualizer($ports, ['layout' => 'Vertical']);
$verticalMeta = extract_meta($verticalHtml);
if ($verticalMeta !== ['layout' => 'vertical', 'cols' => 24, 'rows' => 2]) {
    echo '[FAIL] vertical meta ' . json_encode($verticalMeta) . PHP_EOL;
    $failures++;
} else {
    echo '[PASS] vertical meta 24x2' . PHP_EOL;
}

$verticalPort2 = extract_port_grid($verticalHtml, 2);
if ($verticalPort2 !== ['row' => 2, 'col' => 1]) {
    echo '[FAIL] vertical port 2 grid ' . json_encode($verticalPort2) . PHP_EOL;
    $failures++;
} else {
    echo '[PASS] vertical port 2 at row 2 col 1' . PHP_EOL;
}

$horizontalHtml = itm_render_port_visualizer($ports, ['layout' => 'Horizontal']);
$horizontalMeta = extract_meta($horizontalHtml);
if ($horizontalMeta !== ['layout' => 'horizontal', 'cols' => 24, 'rows' => 2]) {
    echo '[FAIL] horizontal meta ' . json_encode($horizontalMeta) . PHP_EOL;
    $failures++;
} else {
    echo '[PASS] horizontal meta 24x2' . PHP_EOL;
}

$horizontalPort2 = extract_port_grid($horizontalHtml, 2);
if ($horizontalPort2 !== ['row' => 1, 'col' => 2]) {
    echo '[FAIL] horizontal port 2 grid ' . json_encode($horizontalPort2) . PHP_EOL;
    $failures++;
} else {
    echo '[PASS] horizontal port 2 at row 1 col 2' . PHP_EOL;
}

exit($failures > 0 ? 1 : 0);
