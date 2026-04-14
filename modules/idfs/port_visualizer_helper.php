<?php
/**
 * Port Visualizer Component
 *
 * Renders a visual representation of equipment ports matching the user's reference image style.
 */

if (!function_exists('itm_render_port_visualizer')) {
    /**
     * Renders the HTML for a port grid.
     */
    function itm_render_port_visualizer($ports, $options = []) {
        if (empty($ports)) {
            return '';
        }

        // Sort ports by port_no
        usort($ports, function($a, $b) {
            return (int)($a['port_no'] ?? 0) <=> (int)($b['port_no'] ?? 0);
        });

        $totalPorts = count($ports);
        $rows = (int)($options['rows'] ?? 2);
        if ($rows < 1) $rows = 1;
        $cols = (int)($options['columns'] ?? ceil($totalPorts / $rows));

        $pixelImageB64 = 'iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAQAAAAnZu5uAAAADklEQVR42mNkgANGEpgAANwABiaeD7YAAAAASUVORK5CYII=';
        $pixelImageUrl = 'data:image/png;base64,' . $pixelImageB64;

        $html = '<div class="itm-port-visualizer-container">';

        // Left side: Port Grid
        $html .= '<div class="itm-port-grid" style="grid-template-columns: repeat(' . $cols . ', 14px); grid-template-rows: repeat(' . $rows . ', 14px);">';

        $layout = strtolower($options['layout'] ?? 'vertical');

        $grid = [];
        if ($layout === 'vertical' && $rows === 2) {
            // Vertical numbering: 1 above 2, 3 above 4, etc.
            foreach ($ports as $p) {
                $num = (int)$p['port_no'];
                $c = floor(($num - 1) / 2);
                $r = ($num % 2 === 0) ? 1 : 0;
                $grid[$r][$c] = $p;
            }
        } elseif ($layout === 'horizontal' && $rows === 2) {
            // Horizontal numbering: Top row 1, 2, 3... Bottom row n/2+1, n/2+2...
            $half = ceil($totalPorts / 2);
            foreach ($ports as $p) {
                $num = (int)$p['port_no'];
                if ($num <= $half) {
                    $r = 0;
                    $c = $num - 1;
                } else {
                    $r = 1;
                    $c = $num - $half - 1;
                }
                $grid[$r][$c] = $p;
            }
        } else {
            $curr = 0;
            for ($r = 0; $r < $rows; $r++) {
                for ($c = 0; $c < $cols; $c++) {
                    if ($curr < $totalPorts) {
                        $grid[$r][$c] = $ports[$curr++];
                    }
                }
            }
        }

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $p = $grid[$r][$c] ?? null;
                if (!$p) {
                    $html .= '<div class="itm-port-item-empty"></div>';
                    continue;
                }

                $statusColor = (string)($p['status_color'] ?? '#161b22');
                $isActive = false;
                if ($statusColor === '#007bff' || $statusColor === '#58a6ff' || strtolower($statusColor) === 'blue') {
                    $statusColor = '#58a6ff';
                    $isActive = true;
                } elseif ($statusColor === '#28a745' || $statusColor === '#3fb950' || strtolower($statusColor) === 'green') {
                    $statusColor = '#3fb950';
                    $isActive = true;
                }

                // Keep default gray statuses visible; only preserve explicit dark colors as dark.
                if (!$isActive && ($statusColor === '#6c757d' || $statusColor === '#161b22')) {
                    $statusColor = '#161b22';
                }

                $title = "Port " . (int)$p['port_no'];
                if (!empty($p['label'])) $title .= " - " . $p['label'];
                if (!empty($p['status_label'])) $title .= " (" . $p['status_label'] . ")";

                $clickable = !empty($options['clickable']);
                $onClick = $clickable ? 'onclick="if(typeof onPortClick === \'function\') onPortClick(' . (int)$p['id'] . ')"' : '';
                $cursor = $clickable ? 'pointer' : 'default';

                $glow = $isActive ? "box-shadow: 0 0 8px $statusColor;" : "";

                $html .= '<div class="itm-port-item" title="' . sanitize($title) . '" ' . $onClick . ' style="
                    width: 14px;
                    height: 14px;
                    background-color: ' . sanitize($statusColor) . ';
                    background-image: url(\'' . $pixelImageUrl . '\');
                    background-size: cover;
                    background-blend-mode: soft-light;
                    border-radius: 3px;
                    cursor: ' . $cursor . ';
                    ' . $glow . '
                    transition: all 0.2s;
                " onmouseover="this.style.transform=\'scale(1.4)\'; this.style.zIndex=\'10\'; this.style.boxShadow=\'0 0 12px \' + this.style.backgroundColor;" onmouseout="this.style.transform=\'scale(1)\'; this.style.zIndex=\'1\'; this.style.boxShadow=\'' . ($isActive ? "0 0 8px $statusColor" : "none") . '\';">';
                $html .= '</div>';
            }
        }
        $html .= '</div>'; // end grid

        // Right side: Icon (The 4-square icon from reference)
        $html .= '<div class="itm-device-icon">';
        for($i=0; $i<4; $i++) {
            $html .= '<div class="itm-device-icon-dot"></div>';
        }
        $html .= '</div>';

        $html .= '</div>'; // end container

        return $html;
    }
}
