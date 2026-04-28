<?php
/**
 * Port Visualizer Component
 *
 * Renders a visual representation of equipment ports matching the user's reference image style.
 */

if (!function_exists('itm_format_visualizer_equipment_code')) {
    function itm_format_visualizer_equipment_code($equipmentId) {
        $raw = trim((string)$equipmentId);
        if ($raw === '') {
            return '';
        }
        if (strpos($raw, '-') !== false) {
            return $raw;
        }
        if (ctype_digit($raw)) {
            $normalized = str_pad($raw, 8, '0', STR_PAD_LEFT);
            return substr($normalized, 0, 4) . '-' . substr($normalized, 4, 4);
        }
        return $raw;
    }
}

if (!function_exists('itm_render_port_visualizer')) {
    /**
     * Renders the HTML for a port grid.
     */
    function itm_render_port_visualizer($ports, $options = []) {
        if (empty($ports)) {
            return '';
        }

        // Why: IDF cards can store RJ45 and SFP rows with overlapping numeric port_no values (e.g., RJ45 1 and SFP 1).
        // Build type-local offsets from the rendered dataset itself so each physical port stays on a stable, non-overlapping dot.
        $maxByType = ['rj45' => 0, 'sfp' => 0, 'sfp_plus' => 0];
        foreach ($ports as $itmPortMetaScan) {
            $scanNo = (int)($itmPortMetaScan['port_no'] ?? 0);
            if ($scanNo <= 0) {
                continue;
            }
            $scanTypeRaw = strtolower(trim((string)($itmPortMetaScan['port_type_label'] ?? ($itmPortMetaScan['port_type'] ?? 'rj45'))));
            $scanType = 'rj45';
            if (strpos($scanTypeRaw, 'sfp+') !== false) {
                $scanType = 'sfp_plus';
            } elseif (strpos($scanTypeRaw, 'sfp') !== false) {
                $scanType = 'sfp';
            }
            if ($scanNo > $maxByType[$scanType]) {
                $maxByType[$scanType] = $scanNo;
            }
        }
        $typeOffset = [
            'rj45' => 0,
            'sfp' => max(0, $maxByType['rj45']),
            'sfp_plus' => max(0, $maxByType['rj45']) + max(0, $maxByType['sfp']),
        ];

        foreach ($ports as &$itmPortMeta) {
            $rawPortNo = (int)($itmPortMeta['port_no'] ?? 0);
            $normalizedType = strtolower(trim((string)($itmPortMeta['port_type_label'] ?? ($itmPortMeta['port_type'] ?? 'rj45'))));
            $visualPortNo = $rawPortNo > 0 ? $rawPortNo : 1;
            if (strpos($normalizedType, 'sfp+') !== false) {
                $visualPortNo += $typeOffset['sfp_plus'];
            } elseif (strpos($normalizedType, 'sfp') !== false) {
                $visualPortNo += $typeOffset['sfp'];
            }
            $itmPortMeta['_visual_port_no'] = $visualPortNo;
        }
        unset($itmPortMeta);

        // Sort ports by visual slot first, then by database id to keep ties deterministic.
        usort($ports, function($a, $b) {
            $slotA = (int)($a['_visual_port_no'] ?? ($a['port_no'] ?? 0));
            $slotB = (int)($b['_visual_port_no'] ?? ($b['port_no'] ?? 0));
            if ($slotA === $slotB) {
                return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
            }
            return $slotA <=> $slotB;
        });

        $totalPorts = count($ports);
        $rows = (int)($options['rows'] ?? 2);
        if ($rows < 1) $rows = 1;
        $cols = (int)($options['columns'] ?? ceil($totalPorts / $rows));

        $layout = strtolower($options['layout'] ?? 'vertical');

        $grid = [];
        $portsPerLine = 24;
        if ($layout === 'vertical') {
            // Why: Switches are physically read in odd/even pairs with a hard wrap after 24 paired positions.
            $pairCount = (int)ceil($totalPorts / 2);
            if (!isset($options['columns'])) {
                $cols = (int)max(1, min($portsPerLine, $pairCount));
            }
            if (!isset($options['rows'])) {
                $rows = (int)max(2, ceil($pairCount / max(1, $cols)) * 2);
            }

            foreach ($ports as $p) {
                $num = (int)($p['_visual_port_no'] ?? ($p['port_no'] ?? 0));
                if ($num <= 0) {
                    continue;
                }
                $pairIndex = (int)floor(($num - 1) / 2);
                $pairRowBlock = (int)floor($pairIndex / max(1, $cols));
                $c = (int)($pairIndex % max(1, $cols));
                $r = (int)($pairRowBlock * 2 + (($num % 2 === 0) ? 1 : 0));
                $grid[$r][$c] = $p;
            }
        } elseif ($layout === 'horizontal') {
            // Why: Patch panels are read left-to-right with a hard wrap every 24 ports.
            if (!isset($options['columns'])) {
                $cols = (int)max(1, min($portsPerLine, $totalPorts));
            }
            if (!isset($options['rows'])) {
                $rows = (int)max(1, ceil($totalPorts / max(1, $cols)));
            }

            foreach ($ports as $p) {
                $num = (int)($p['_visual_port_no'] ?? ($p['port_no'] ?? 0));
                if ($num <= 0) {
                    continue;
                }
                $index = $num - 1;
                $r = (int)floor($index / max(1, $cols));
                $c = (int)($index % max(1, $cols));
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

        $pixelImageB64 = 'iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAQAAAAnZu5uAAAADklEQVR42mNkgANGEpgAANwABiaeD7YAAAAASUVORK5CYII=';
        $pixelImageUrl = 'data:image/png;base64,' . $pixelImageB64;

        $html = '<div class="itm-port-visualizer-container">';

        // Left side: Port Grid
        $html .= '<div class="itm-port-grid" style="grid-template-columns: repeat(' . $cols . ', 14px); grid-template-rows: repeat(' . $rows . ', 14px);">';

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $p = $grid[$r][$c] ?? null;
                if (!$p) {
                    // Why: Rendering placeholders for missing grid cells creates fake ports at the tail.
                    continue;
                }

                $statusColor = trim((string)($p['status_color'] ?? ''));
                $cableHexColor = trim((string)($p['cable_hex_color'] ?? ''));
                if ($statusColor === '') {
                    $statusColor = $cableHexColor !== '' ? $cableHexColor : '#161b22';
                }
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

                $titleParts = [];
                $contextParts = [];
                $contextCompany = trim((string)($options['company_name'] ?? ''));
                if ($contextCompany !== '') {
                    $contextParts[] = $contextCompany;
                }
                $contextLocation = trim((string)($options['location_name'] ?? ''));
                if ($contextLocation !== '') {
                    $contextParts[] = 'Location: ' . $contextLocation;
                }
                $contextIdfName = trim((string)($options['idf_name'] ?? ''));
                if ($contextIdfName !== '') {
                    $contextParts[] = 'Name: ' . $contextIdfName;
                }
                $contextIdfCode = trim((string)($options['idf_code'] ?? ''));
                if ($contextIdfCode !== '') {
                    $contextParts[] = 'IDF Code: ' . $contextIdfCode;
                }
                $contextRackName = trim((string)($options['rack_name'] ?? ''));
                if ($contextRackName !== '') {
                    $contextParts[] = 'Rack: ' . $contextRackName;
                }
                if (!empty($contextParts)) {
                    $titleParts[] = implode(' • ', $contextParts);
                }
                $statusLabel = trim((string)($p['status_label'] ?? 'Unknown'));
                $statusSummary = 'Status (' . $statusLabel . ')';

                $portTypeLabel = trim((string)($p['port_type_label'] ?? ''));
                if ($portTypeLabel !== '') {
                    $titleParts[] = 'Type: ' . $portTypeLabel;
                }
                if (!empty($p['label'])) {
                    $titleParts[] = 'Label: ' . trim((string)$p['label']);
                }
                $cableName = trim((string)($p['cable_color_name'] ?? ''));
                if ($cableName === '' && $cableHexColor !== '') {
                    $cableName = $cableHexColor;
                }

                $fromParts = ['From:', 'Port ' . (int)$p['port_no'] . ' (' . $statusLabel . ')', $statusSummary];
                if ($portTypeLabel !== '') {
                    $fromParts[] = $portTypeLabel;
                }
                $localDeviceType = trim((string)($p['local_device_type_label'] ?? ''));
                if ($localDeviceType !== '') {
                    $fromParts[] = $localDeviceType;
                }
                $localDeviceName = trim((string)($p['local_device_name'] ?? ''));
                if ($localDeviceName !== '') {
                    $fromParts[] = $localDeviceName;
                }
                $localEquipmentCode = itm_format_visualizer_equipment_code($p['local_equipment_id'] ?? '');
                if ($localEquipmentCode !== '') {
                    $fromParts[] = 'Asset ' . $localEquipmentCode;
                }
                if (!empty($p['local_position_no'])) {
                    $fromParts[] = 'Pos ' . (int)$p['local_position_no'];
                }
                $localIdfName = trim((string)($p['local_idf_name'] ?? ''));
                if ($localIdfName !== '') {
                    $fromParts[] = 'IDF ' . $localIdfName;
                }
                $titleParts[] = implode(' • ', $fromParts);

                $connectedParts = ['Connected To:'];
                if (!empty($p['connected_to'])) {
                    $connectedParts[] = trim((string)$p['connected_to']);
                }
                if (empty($p['connected_to']) && !empty($p['remote_port_no'])) {
                    $connectedParts[] = 'Port ' . (int)$p['remote_port_no'];
                }
                $remoteStatusLabel = trim((string)($p['remote_status_label'] ?? ''));
                if ($remoteStatusLabel !== '') {
                    $remoteLabel = $remoteStatusLabel !== '' ? $remoteStatusLabel : 'Unknown';
                    $connectedParts[] = 'Status (' . $remoteLabel . ')';
                }
                $remoteDeviceType = trim((string)($p['remote_device_type_label'] ?? ''));
                if ($remoteDeviceType !== '') {
                    $connectedParts[] = $remoteDeviceType;
                }
                $remoteDeviceName = trim((string)($p['remote_device_name'] ?? ''));
                if ($remoteDeviceName !== '') {
                    $connectedParts[] = $remoteDeviceName;
                }
                $remoteEquipmentCode = itm_format_visualizer_equipment_code($p['remote_equipment_id'] ?? '');
                if ($remoteEquipmentCode !== '') {
                    $connectedParts[] = 'Asset ' . $remoteEquipmentCode;
                }
                if (!empty($p['remote_position_no'])) {
                    $connectedParts[] = 'Pos ' . (int)$p['remote_position_no'];
                }
                if ($cableName !== '') {
                    $connectedParts[] = 'Cable color ' . $cableName;
                }
                if ($cableHexColor !== '') {
                    $connectedParts[] = $cableHexColor;
                }
                if (count($connectedParts) > 1) {
                    $titleParts[] = implode(' • ', $connectedParts);
                }

                if (!empty($p['vlan_label'])) {
                    $titleParts[] = 'VLAN: ' . trim((string)$p['vlan_label']);
                }
                if (!empty($p['link_notes'])) {
                    $titleParts[] = 'Notes: ' . trim((string)$p['link_notes']);
                } elseif (!empty($p['notes'])) {
                    $titleParts[] = 'Notes: ' . trim((string)$p['notes']);
                }
                $title = implode(' • ', $titleParts);

                $clickable = !empty($options['clickable']);
                $onClick = $clickable ? 'onclick="if(typeof onPortClick === \'function\') onPortClick(' . (int)$p['id'] . ', this)"' : '';
                $cursor = $clickable ? 'pointer' : 'default';

                $glow = $isActive ? "box-shadow: 0 0 8px $statusColor;" : "";
                $portStatusLabelAttr = sanitize($statusLabel);
                $portPositionIdAttr = (int)($p['position_id'] ?? 0);

                $html .= '<div class="itm-port-item" title="' . sanitize($title) . '" data-port-id="' . (int)$p['id'] . '" data-port-status-label="' . $portStatusLabelAttr . '" data-position-id="' . $portPositionIdAttr . '" ' . $onClick . ' style="
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

        $showDeviceIcon = isset($options['show_device_icon']) ? !empty($options['show_device_icon']) : true;
        if ($showDeviceIcon) {
            $portMetaByTypeAndNo = [];
            $rj45Ports = [];
            $sfpPorts = [];
            $sfpPlusPorts = [];

            foreach ($ports as $portMeta) {
                $portNo = (int)($portMeta['port_no'] ?? 0);
                if ($portNo <= 0) {
                    continue;
                }
                $typeRaw = strtolower(trim((string)($portMeta['port_type_label'] ?? ($portMeta['port_type'] ?? ''))));
                $typeKey = 'rj45';
                if (strpos($typeRaw, 'sfp+') !== false) {
                    $typeKey = 'sfp_plus';
                    $sfpPlusPorts[] = $portNo;
                } elseif (strpos($typeRaw, 'sfp') !== false) {
                    $typeKey = 'sfp';
                    $sfpPorts[] = $portNo;
                } else {
                    $rj45Ports[] = $portNo;
                }
                $portMetaByTypeAndNo[$typeKey . ':' . $portNo] = $portMeta;
            }


            if (isset($options['rj45_ports']) && is_array($options['rj45_ports']) && !empty($options['rj45_ports'])) {
                $rj45Ports = [];
                foreach ($options['rj45_ports'] as $rj45PortNo) {
                    $rj45Ports[] = (int)$rj45PortNo;
                }
            }
            if (isset($options['sfp_ports']) && is_array($options['sfp_ports']) && !empty($options['sfp_ports'])) {
                $sfpPorts = [];
                foreach ($options['sfp_ports'] as $sfpPortNo) {
                    $sfpPorts[] = (int)$sfpPortNo;
                }
            }
            if (isset($options['sfp_plus_ports']) && is_array($options['sfp_plus_ports']) && !empty($options['sfp_plus_ports'])) {
                $sfpPlusPorts = [];
                foreach ($options['sfp_plus_ports'] as $sfpPlusPortNo) {
                    $sfpPlusPorts[] = (int)$sfpPlusPortNo;
                }
            }

            sort($rj45Ports);
            sort($sfpPorts);
            sort($sfpPlusPorts);

            $iconTitleParts = [];
            if (!empty($rj45Ports)) {
                $iconTitleParts[] = 'RJ45 Ports: ' . implode(', ', $rj45Ports);
            }
            if (!empty($sfpPorts)) {
                $iconTitleParts[] = 'SFP Ports: ' . implode(', ', $sfpPorts);
            }
            if (!empty($sfpPlusPorts)) {
                $iconTitleParts[] = 'SFP+ Ports: ' . implode(', ', $sfpPlusPorts);
            }
            $iconTitle = empty($iconTitleParts) ? 'Ports not configured' : implode(' • ', $iconTitleParts);

            $iconDots = [];
            foreach ($rj45Ports as $portNo) { $iconDots[] = ['type' => 'rj45', 'no' => (int)$portNo]; }
            foreach ($sfpPorts as $portNo) { $iconDots[] = ['type' => 'sfp', 'no' => (int)$portNo]; }
            foreach ($sfpPlusPorts as $portNo) { $iconDots[] = ['type' => 'sfp_plus', 'no' => (int)$portNo]; }

            if (empty($iconDots)) {
                for ($i = 0; $i < 4; $i++) { $iconDots[] = ['type' => '', 'no' => 0]; }
            }
            if (count($iconDots) > 20) {
                $iconDots = array_slice($iconDots, 0, 20);
            }

            $iconCols = max(2, min(10, count($iconDots)));
            $html .= '<div class="itm-device-icon" title="' . sanitize($iconTitle) . '" style="grid-template-columns: repeat(' . $iconCols . ', 10px);">';
            foreach ($iconDots as $dotMeta) {
                $dotStyle = '';
                $dotKey = (string)($dotMeta['type'] ?? '') . ':' . (int)($dotMeta['no'] ?? 0);
                if (isset($portMetaByTypeAndNo[$dotKey])) {
                    $dotPort = $portMetaByTypeAndNo[$dotKey];
                    $dotColor = trim((string)($dotPort['cable_hex_color'] ?? ''));
                    if ($dotColor === '') {
                        $dotColor = trim((string)($dotPort['status_color'] ?? ''));
                    }
                    if ($dotColor !== '') {
                        $dotStyle = ' style="background:' . sanitize($dotColor) . ';"';
                    }
                }
                $html .= '<div class="itm-device-icon-dot"' . $dotStyle . '></div>';
            }
            $html .= '</div>';
        }


        $html .= '</div>'; // end container

        return $html;
    }
}
