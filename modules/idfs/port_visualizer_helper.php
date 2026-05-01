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

        $allPortsForMeta = $ports;
        $renderPorts = $ports;
        // Why: SFP/SFP+ ports must stay clickable in rack view for create/link/edit workflows;
        // filtering them out here prevented direct actions when RJ45 and fiber rows coexist.
        $gridPortType = strtolower(trim((string)($options['grid_port_type'] ?? 'all')));
        if ($gridPortType === 'rj45') {
            $renderPorts = [];
            foreach ($ports as $itmRenderablePort) {
                $itmRenderableTypeRaw = strtolower(trim((string)($itmRenderablePort['port_type_label'] ?? ($itmRenderablePort['port_type'] ?? 'rj45'))));
                if (strpos($itmRenderableTypeRaw, 'sfp') !== false) {
                    continue;
                }
                $renderPorts[] = $itmRenderablePort;
            }
        }

        // Why: IDF cards can store RJ45 and SFP rows with overlapping numeric port_no values (e.g., RJ45 1 and SFP 1).
        // Build type-local offsets from the rendered dataset itself so each physical port stays on a stable, non-overlapping dot.
        $maxByType = ['rj45' => 0, 'sfp' => 0, 'sfp_plus' => 0];
        foreach ($renderPorts as $itmPortMetaScan) {
            $scanNo = (int)($itmPortMetaScan['port_no'] ?? 0);
            if ($scanNo <= 0) {
                continue;
            }
            $scanTypeRaw = strtolower(trim((string)($itmPortMetaScan['port_type_label'] ?? ($itmPortMetaScan['port_type'] ?? 'rj45'))));
            $scanType = 'rj45';
            if (strpos($scanTypeRaw, 'sfp+') !== false || strpos($scanTypeRaw, 'sfp plus') !== false) {
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

        foreach ($renderPorts as &$itmPortMeta) {
            $rawPortNo = (int)($itmPortMeta['port_no'] ?? 0);
            $normalizedType = strtolower(trim((string)($itmPortMeta['port_type_label'] ?? ($itmPortMeta['port_type'] ?? 'rj45'))));
            $visualPortNo = $rawPortNo > 0 ? $rawPortNo : 1;
            if (strpos($normalizedType, 'sfp+') !== false || strpos($normalizedType, 'sfp plus') !== false) {
                $visualPortNo += $typeOffset['sfp_plus'];
            } elseif (strpos($normalizedType, 'sfp') !== false) {
                $visualPortNo += $typeOffset['sfp'];
            }
            $itmPortMeta['_visual_port_no'] = $visualPortNo;
        }
        unset($itmPortMeta);

        // Sort ports by visual slot first, then by database id to keep ties deterministic.
        usort($renderPorts, function($a, $b) {
            $slotA = (int)($a['_visual_port_no'] ?? ($a['port_no'] ?? 0));
            $slotB = (int)($b['_visual_port_no'] ?? ($b['port_no'] ?? 0));
            if ($slotA === $slotB) {
                return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
            }
            return $slotA <=> $slotB;
        });

        $totalPorts = count($renderPorts);
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

            foreach ($renderPorts as $p) {
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

            foreach ($renderPorts as $p) {
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
                        $grid[$r][$c] = $renderPorts[$curr++];
                    }
                }
            }
        }

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
                // Why: rack dots should visually follow cable color when present; status color is fallback.
                $statusColor = $cableHexColor !== '' ? $cableHexColor : ($statusColor !== '' ? $statusColor : '#161b22');
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

                $fromParts = ['From:', 'Port ' . (int)$p['port_no'] . ' (' . $statusLabel . ')'];
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
                $cableLabel = trim((string)($p['cable_label'] ?? ''));
                if ($cableLabel !== '') {
                    $connectedParts[] = 'Cable label ' . $cableLabel;
                }
                if (count($connectedParts) > 1) {
                    $titleParts[] = implode(' • ', $connectedParts);
                }

                $vlanLabel = trim((string)($p['vlan_label'] ?? ''));
                $vlanId = isset($p['vlan_id']) ? (int)$p['vlan_id'] : 0;
                if ($vlanLabel !== '') {
                    $titleParts[] = 'VLAN: ' . $vlanLabel;
                } elseif ($vlanId > 0) {
                    $titleParts[] = 'VLAN: #' . $vlanId;
                }
                if (!empty($p['link_notes'])) {
                    $titleParts[] = 'Notes: ' . trim((string)$p['link_notes']);
                } elseif (!empty($p['notes'])) {
                    $titleParts[] = 'Notes: ' . trim((string)$p['notes']);
                }
                if (!empty($p['link_id'])) {
                    $titleParts[] = 'Link ID: ' . (int)$p['link_id'];
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

            foreach ($allPortsForMeta as $portMeta) {
                $portNo = (int)($portMeta['port_no'] ?? 0);
                if ($portNo <= 0) {
                    continue;
                }
                $typeRaw = strtolower(trim((string)($portMeta['port_type_label'] ?? ($portMeta['port_type'] ?? ''))));
                $typeKey = 'rj45';
                if (strpos($typeRaw, 'sfp+') !== false || strpos($typeRaw, 'sfp plus') !== false) {
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


            // Why: Equipment master fields define physical port capacity; always prefer those configured ranges over sparse/legacy IDF port rows.
            if (array_key_exists('rj45_ports', $options) && is_array($options['rj45_ports'])) {
                $rj45Ports = [];
                foreach ($options['rj45_ports'] as $rj45PortNo) {
                    $rj45Ports[] = (int)$rj45PortNo;
                }
            }
            if (array_key_exists('sfp_ports', $options) && is_array($options['sfp_ports'])) {
                $sfpPorts = [];
                foreach ($options['sfp_ports'] as $sfpPortNo) {
                    $sfpPorts[] = (int)$sfpPortNo;
                }
            }
            if (array_key_exists('sfp_plus_ports', $options) && is_array($options['sfp_plus_ports'])) {
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
            foreach ($sfpPorts as $portNo) { $iconDots[] = ['type' => 'sfp', 'no' => (int)$portNo]; }
            foreach ($sfpPlusPorts as $portNo) { $iconDots[] = ['type' => 'sfp_plus', 'no' => (int)$portNo]; }

            usort($iconDots, function ($a, $b) use ($layout) {
                $aNo = (int)($a['no'] ?? 0);
                $bNo = (int)($b['no'] ?? 0);
                if ($layout === 'vertical') {
                    $aIsEven = ($aNo % 2 === 0) ? 1 : 0;
                    $bIsEven = ($bNo % 2 === 0) ? 1 : 0;
                    if ($aIsEven === $bIsEven) {
                        return $aNo <=> $bNo;
                    }
                    return $aIsEven <=> $bIsEven;
                }
                if ($aNo === $bNo) {
                    return strcmp((string)($a['type'] ?? ''), (string)($b['type'] ?? ''));
                }
                return $aNo <=> $bNo;
            });

            if (empty($iconDots)) {
                for ($i = 0; $i < 4; $i++) { $iconDots[] = ['type' => '', 'no' => 0]; }
            }
            if (count($iconDots) > 20) {
                $iconDots = array_slice($iconDots, 0, 20);
            }

            $iconCols = $layout === 'vertical'
                ? max(1, min(10, (int)ceil(count($iconDots) / 2)))
                : max(2, min(10, count($iconDots)));
            $html .= '<div class="itm-device-icon" style="grid-template-columns: repeat(' . $iconCols . ', 10px); opacity:1;">';
            foreach ($iconDots as $dotMeta) {
                $dotPort = null;
                $dotStyle = '';
                $dotKey = (string)($dotMeta['type'] ?? '') . ':' . (int)($dotMeta['no'] ?? 0);
                $dotTitle = 'Port ' . (int)($dotMeta['no'] ?? 0);
                $dotIsClickable = !empty($options['clickable']);
                if (isset($portMetaByTypeAndNo[$dotKey])) {
                    $dotPort = $portMetaByTypeAndNo[$dotKey];
                    $dotType = (string)($dotMeta['type'] ?? '');
                    $dotCableHexColor = trim((string)($dotPort['cable_hex_color'] ?? ''));
                    $dotStatusColor = trim((string)($dotPort['status_color'] ?? ''));
                    $dotColor = $dotCableHexColor !== '' ? $dotCableHexColor : $dotStatusColor;
                    $dotStyleRules = [];
                    if ($dotColor !== '') {
                        $dotStyleRules[] = 'background:' . sanitize($dotColor);
                    }
                    if ($dotType === 'sfp' || $dotType === 'sfp_plus') {
                        // Why: Keep SFP/SFP+ color previews fully solid so selected cable/status colors are not visually faded.
                        $dotStyleRules[] = 'background-image:none';
                    }
                    if ($dotIsClickable) {
                        // Why: SFP compact icon dots were clickable but did not show the hand cursor, which made links feel inactive.
                        $dotStyleRules[] = 'cursor:pointer';
                    }
                    if (!empty($dotStyleRules)) {
                        $dotStyle = ' style="' . implode(';', $dotStyleRules) . ';"';
                    }
                    $dotTypeLabel = trim((string)($dotPort['port_type_label'] ?? strtoupper((string)($dotMeta['type'] ?? 'SFP'))));
                    $dotStatusLabel = trim((string)($dotPort['status_label'] ?? 'Unknown'));
                    $dotLabel = trim((string)($dotPort['label'] ?? ''));
                    $dotVlanLabel = trim((string)($dotPort['vlan_label'] ?? ''));
                    $dotVlan = (int)($dotPort['vlan_id'] ?? 0);
                    $dotConnectedTo = trim((string)($dotPort['connected_to'] ?? ''));
                    $dotCableName = trim((string)($dotPort['cable_color_name'] ?? ''));
                    $dotCableHex = trim((string)($dotPort['cable_hex_color'] ?? ''));
                    $dotCableLabel = trim((string)($dotPort['cable_label'] ?? ''));
                    $dotLinkNotes = trim((string)($dotPort['link_notes'] ?? ''));
                    $dotLinkId = isset($dotPort['link_id']) ? (int)($dotPort['link_id']) : 0;
                    $dotTitleParts = ['Port ' . (int)($dotMeta['no'] ?? 0), $dotTypeLabel, 'Status: ' . $dotStatusLabel];
                    if ($dotLabel !== '' && $dotLabel !== '0') {
                        $dotTitleParts[] = 'Label: ' . $dotLabel;
                    }
                    if ($dotVlanLabel !== '') {
                        $dotTitleParts[] = 'VLAN: ' . $dotVlanLabel;
                    } elseif ($dotVlan > 0) {
                        $dotTitleParts[] = 'VLAN: ' . $dotVlan;
                    }
                    if ($dotConnectedTo !== '') {
                        $dotTitleParts[] = 'Connected To: ' . $dotConnectedTo;
                    }
                    if ($dotCableName !== '') {
                        $dotTitleParts[] = 'Cable color: ' . $dotCableName;
                    }
                    if ($dotCableHex !== '') {
                        $dotTitleParts[] = $dotCableHex;
                    }
                    if ($dotCableLabel !== '') {
                        $dotTitleParts[] = 'Cable label: ' . $dotCableLabel;
                    }
                    if ($dotLinkNotes !== '') {
                        $dotTitleParts[] = 'Notes: ' . $dotLinkNotes;
                    }
                    $dotNotes = trim((string)($dotPort['notes'] ?? ''));
                    if ($dotNotes !== '' && strcasecmp($dotNotes, $dotLinkNotes) !== 0) {
                        $dotTitleParts[] = 'Notes: ' . $dotNotes;
                    }
                    if ($dotLinkId > 0) {
                        $dotTitleParts[] = 'Link ID: ' . $dotLinkId;
                    }
                    $dotTitle = implode(' • ', $dotTitleParts);
                }
                $dotPortId = isset($dotPort['id']) ? (int)$dotPort['id'] : 0;
                $dotStatusAttr = isset($dotPort['status_label']) ? sanitize((string)$dotPort['status_label']) : 'Unknown';
                $dotPositionIdAttr = isset($dotPort['position_id']) ? (int)$dotPort['position_id'] : 0;
                $dotDataAttrs = ' data-port-id="' . $dotPortId . '" data-port-status-label="' . $dotStatusAttr . '" data-position-id="' . $dotPositionIdAttr . '" data-port-number="' . (int)($dotMeta['no'] ?? 0) . '" data-port-type="' . sanitize((string)($dotMeta['type'] ?? '')) . '"';
                $dotOnClick = '';
                if ($dotIsClickable) {
                    $dotOnClick = ' onclick="if(typeof onPortDotClick === \'function\') onPortDotClick(this)"';
                }
                $html .= '<div class="itm-device-icon-dot" title="' . sanitize($dotTitle) . '"' . $dotDataAttrs . $dotStyle . $dotOnClick . '></div>';
            }
            $html .= '</div>';
        }


        $html .= '</div>'; // end container

        return $html;
    }
}


