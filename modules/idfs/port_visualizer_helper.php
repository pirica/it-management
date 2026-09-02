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

if (!function_exists('itm_build_capacity_placeholder_ports')) {
    /**
     * Why: Rack cards can declare RJ45/SFP capacity before idf_ports rows are materialized; synthesize dots from saved counts.
     */
    function itm_build_capacity_placeholder_ports(array $options): array
    {
        $ports = [];
        $positionId = isset($options['position_id']) ? (int)$options['position_id'] : 0;
        $append = static function (array &$target, array $numbers, string $typeLabel) use ($positionId): void {
            foreach ($numbers as $portNoRaw) {
                $portNo = (int)$portNoRaw;
                if ($portNo <= 0) {
                    continue;
                }
                $target[] = [
                    'id' => 0,
                    'port_no' => $portNo,
                    'port_type_label' => $typeLabel,
                    'status_label' => 'Unknown',
                    'status_color' => '#808080',
                    'cable_hex_color' => '#808080',
                    'position_id' => $positionId,
                ];
            }
        };

        $append($ports, (array)($options['rj45_ports'] ?? []), 'RJ45');
        $sfpSlots = array_merge(
            (array)($options['sfp_ports'] ?? []),
            (array)($options['sfp_plus_ports'] ?? [])
        );
        $append($ports, $sfpSlots, 'SFP');

        return $ports;
    }
}

if (!function_exists('itm_normalize_hex_color')) {
    function itm_normalize_hex_color($raw, string $fallback = '#808080'): string
    {
        $value = strtoupper(trim((string)$raw));
        if ($value === '') {
            return $fallback;
        }
        if (preg_match('/^#?([0-9A-F]{6})$/', $value, $matches)) {
            return '#' . $matches[1];
        }
        return $fallback;
    }
}

if (!function_exists('itm_resolve_port_display_color')) {
    /**
     * Resolve rack/device dot color from IDF port row metadata (DB cable/status hex fields).
     */
    function itm_resolve_port_display_color(array $portMeta, string $fallback = '#808080'): string
    {
        $candidates = [
            trim((string)($portMeta['cable_hex_color'] ?? '')),
            trim((string)($portMeta['status_color'] ?? '')),
            trim((string)($portMeta['hex_color'] ?? '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $normalized = itm_normalize_hex_color($candidate, '');
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return $fallback;
    }
}

if (!function_exists('itm_port_visualizer_type_key')) {
    function itm_port_visualizer_type_key(array $portMeta): string
    {
        $typeRaw = strtolower(trim((string)($portMeta['port_type_label'] ?? ($portMeta['port_type'] ?? 'rj45'))));
        if (strpos($typeRaw, 'sfp') !== false) {
            return 'sfp';
        }
        return 'rj45';
    }
}

if (!function_exists('itm_prune_hydrated_ports_to_sfp_capacity')) {
    /**
     * Why: Capacity merge fills missing placeholders only; surplus idf_ports fiber rows beyond the capacity list would still paint extra dots so rack badges disagree with the grid.
     */
    function itm_prune_hydrated_ports_to_sfp_capacity(array $ports, array $options): array
    {
        $allowedPortNos = [];
        foreach ((array)($options['sfp_ports'] ?? []) as $raw) {
            $n = (int)$raw;
            if ($n > 0) {
                $allowedPortNos[$n] = true;
            }
        }
        foreach ((array)($options['sfp_plus_ports'] ?? []) as $raw) {
            $n = (int)$raw;
            if ($n > 0) {
                $allowedPortNos[$n] = true;
            }
        }
        if (!$allowedPortNos) {
            return $ports;
        }
        $trimmed = [];
        foreach ($ports as $meta) {
            if (itm_port_visualizer_type_key($meta) !== 'sfp') {
                $trimmed[] = $meta;
                continue;
            }
            $pn = (int)($meta['port_no'] ?? 0);
            if ($pn > 0 && isset($allowedPortNos[$pn])) {
                $trimmed[] = $meta;
            }
        }

        return $trimmed;
    }
}

if (!function_exists('itm_merge_capacity_placeholder_ports')) {
    /**
     * Why: Positions can declare RJ45 and SFP capacity while idf_ports only has one family materialized.
     */
    function itm_merge_capacity_placeholder_ports(array $ports, array $options): array
    {
        $hasCapacity = !empty($options['rj45_ports'])
            || !empty($options['sfp_ports'])
            || !empty($options['sfp_plus_ports']); // Legacy callers may still pass capacity here; merged when building placeholders.
        if (!$hasCapacity) {
            return $ports;
        }

        $existing = [];
        foreach ($ports as $portMeta) {
            $portNo = (int)($portMeta['port_no'] ?? 0);
            if ($portNo <= 0) {
                continue;
            }
            $existing[itm_port_visualizer_type_key($portMeta) . ':' . $portNo] = true;
        }

        foreach (itm_build_capacity_placeholder_ports($options) as $placeholder) {
            $portNo = (int)($placeholder['port_no'] ?? 0);
            if ($portNo <= 0) {
                continue;
            }
            $key = itm_port_visualizer_type_key($placeholder) . ':' . $portNo;
            if (!isset($existing[$key])) {
                $ports[] = $placeholder;
                $existing[$key] = true;
            }
        }

        return $ports;
    }
}

if (!function_exists('itm_filter_ports_by_type_keys')) {
    function itm_filter_ports_by_type_keys(array $ports, array $allowedTypeKeys): array
    {
        $allowed = array_flip($allowedTypeKeys);
        $filtered = [];
        foreach ($ports as $portMeta) {
            $typeKey = itm_port_visualizer_type_key($portMeta);
            if (isset($allowed[$typeKey])) {
                $filtered[] = $portMeta;
            }
        }
        return $filtered;
    }
}

if (!function_exists('itm_port_visualizer_click_has_explicit_connection')) {
    /**
     * Why: Rack port clicks open Edit Port only when an idf_links row exists; hostname/Connected To alone must still offer Create Cable Link.
     */
    function itm_port_visualizer_click_has_explicit_connection(array $p): bool
    {
        return !empty($p['link_id']) && (int)$p['link_id'] > 0;
    }
}

if (!function_exists('itm_render_port_visualizer')) {
    /**
     * Renders the HTML for a port grid.
     */
    function itm_render_port_visualizer($ports, $options = []) {
        $hasRj45Capacity = !empty($options['rj45_ports']);
        $hasFiberCapacity = !empty($options['sfp_ports']) || !empty($options['sfp_plus_ports']);
        if ($hasFiberCapacity) {
            $ports = itm_prune_hydrated_ports_to_sfp_capacity($ports, $options);
        }
        if ($hasRj45Capacity || $hasFiberCapacity) {
            $ports = itm_merge_capacity_placeholder_ports($ports, $options);
        }
        if (empty($ports)) {
            $ports = itm_build_capacity_placeholder_ports($options);
        }
        if (empty($ports)) {
            return '';
        }

        $useSplitBlocks = $hasRj45Capacity && $hasFiberCapacity;
        $fragmentMode = (string)($options['fragment'] ?? '');
        if ($useSplitBlocks && $fragmentMode === '') {
            $layoutName = strtolower((string)($options['layout'] ?? 'vertical'));
            $rj45Count = count((array)($options['rj45_ports'] ?? []));
            $fiberCount = count((array)($options['sfp_ports'] ?? [])) + count((array)($options['sfp_plus_ports'] ?? []));

            $rj45Options = $options;
            $rj45Options['fragment'] = 'rj45';
            $rj45Options['grid_port_type'] = 'rj45';
            $rj45Options['sfp_ports'] = [];
            $rj45Options['sfp_plus_ports'] = [];
            $rj45Options['show_device_icon'] = false;

            $fiberOptions = $options;
            $fiberOptions['fragment'] = 'fiber';
            $fiberOptions['grid_port_type'] = 'fiber';
            $fiberOptions['rj45_ports'] = [];
            $fiberOptions['show_device_icon'] = false;

            return '<div class="itm-port-visualizer-container itm-port-visualizer-container--split"'
                . ' data-layout="' . sanitize($layoutName) . '"'
                . ' data-port-total="' . (int)($rj45Count + $fiberCount) . '">'
                . '<div class="itm-port-visualizer-split">'
                . itm_render_port_visualizer($ports, $rj45Options)
                . itm_render_port_visualizer($ports, $fiberOptions)
                . '</div></div>';
        }

        $allPortsForMeta = $ports;
        $renderPorts = $ports;
        // Why: Fiber (SFP family) ports must stay clickable in rack view for create/link/edit workflows;
        // filtering them out here prevented direct actions when RJ45 and fiber rows coexist.
        $gridPortType = strtolower(trim((string)($options['grid_port_type'] ?? 'all')));
        if ($gridPortType === 'rj45' && !$hasRj45Capacity && $hasFiberCapacity) {
            $gridPortType = 'all';
        }
        if ($gridPortType === 'rj45') {
            $renderPorts = itm_filter_ports_by_type_keys($ports, ['rj45']);
        } elseif ($gridPortType === 'fiber') {
            $renderPorts = itm_filter_ports_by_type_keys($ports, ['sfp']);
        }
        if (empty($renderPorts) && $hasFiberCapacity && $gridPortType !== 'rj45') {
            $renderPorts = itm_build_capacity_placeholder_ports($options);
        }
        if (empty($renderPorts)) {
            return '';
        }

        if ($fragmentMode !== '') {
            // Why: Split RJ45/fiber grids size rows/cols from COUNT and allocate cells from _visual coords (pairing/layout math).
            // RJ45 footprints end around N while fiber stays on physical numbering (baseline+ordinal), so copying port_no blindly
            // places fibers off-grid (high row/col). Use dense ordering inside each fragment instead.
            usort($renderPorts, static function (array $a, array $b): int {
                $noA = (int)($a['port_no'] ?? 0);
                $noB = (int)($b['port_no'] ?? 0);
                if ($noA === $noB) {
                    return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
                }
                return $noA <=> $noB;
            });
            $fragmentOrdinal = 0;
            foreach ($renderPorts as &$itmPortMeta) {
                $fragmentOrdinal++;
                $itmPortMeta['_visual_port_no'] = $fragmentOrdinal;
            }
            unset($itmPortMeta);
        } elseif ($gridPortType === 'rj45' || $gridPortType === 'fiber') {
            foreach ($renderPorts as &$itmPortMeta) {
                $itmPortMeta['_visual_port_no'] = (int)($itmPortMeta['port_no'] ?? 0);
            }
            unset($itmPortMeta);
        } else {
            // Why: IDF cards can store RJ45 and SFP rows with overlapping numeric port_no values (e.g., RJ45 1 and SFP 1).
            // Build type-local offsets from the rendered dataset itself so each physical port stays on a stable, non-overlapping dot.
            $maxByType = ['rj45' => 0, 'sfp' => 0];
            foreach ($renderPorts as $itmPortMetaScan) {
                $scanNo = (int)($itmPortMetaScan['port_no'] ?? 0);
                if ($scanNo <= 0) {
                    continue;
                }
                $scanTypeRaw = strtolower(trim((string)($itmPortMetaScan['port_type_label'] ?? ($itmPortMetaScan['port_type'] ?? 'rj45'))));
                $scanType = strpos($scanTypeRaw, 'sfp') !== false ? 'sfp' : 'rj45';
                if ($scanNo > $maxByType[$scanType]) {
                    $maxByType[$scanType] = $scanNo;
                }
            }
            $typeOffset = [
                'rj45' => 0,
                'sfp' => max(0, $maxByType['rj45']),
            ];

            foreach ($renderPorts as &$itmPortMeta) {
                $rawPortNo = (int)($itmPortMeta['port_no'] ?? 0);
                $normalizedType = strtolower(trim((string)($itmPortMeta['port_type_label'] ?? ($itmPortMeta['port_type'] ?? 'rj45'))));
                $visualPortNo = $rawPortNo > 0 ? $rawPortNo : 1;
                if (strpos($normalizedType, 'sfp') !== false) {
                    $visualPortNo += $typeOffset['sfp'];
                }
                $itmPortMeta['_visual_port_no'] = $visualPortNo;
            }
            unset($itmPortMeta);
        }

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

        $gridClass = 'itm-port-grid';
        if ($fragmentMode === 'rj45') {
            $gridClass .= ' itm-port-grid--rj45';
        } elseif ($fragmentMode === 'fiber') {
            $gridClass .= ' itm-port-grid--fiber';
        }

        if ($fragmentMode === '') {
            $html = '<div class="itm-port-visualizer-container" data-layout="' . sanitize($layout) . '" data-port-total="' . (int)$totalPorts . '" data-grid-cols="' . (int)$cols . '" data-grid-rows="' . (int)$rows . '">';
        } else {
            $html = '';
        }

        $html .= '<div class="' . $gridClass . '" style="grid-template-columns: repeat(' . $cols . ', 14px); grid-template-rows: repeat(' . $rows . ', 14px);">';

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $cols; $c++) {
                $p = $grid[$r][$c] ?? null;
                if (!$p) {
                    // Why: Rendering placeholders for missing grid cells creates fake ports at the tail.
                    continue;
                }

                // Why: dots must reflect cable_colors.hex_color from switch_ports / switch_status joins, not a hardcoded dark fill.
                $statusColor = itm_resolve_port_display_color($p, '#808080');
                $cableHexColor = itm_normalize_hex_color($p['cable_hex_color'] ?? '', '');
                $isActive = false;
                $statusLower = strtolower($statusColor);
                if (in_array($statusColor, ['#007bff', '#58a6ff'], true) || $statusLower === 'blue') {
                    $isActive = true;
                } elseif (in_array($statusColor, ['#28a745', '#3fb950'], true) || $statusLower === 'green') {
                    $isActive = true;
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
                // Why: SQL may yield '' when switch_status.status is blank; COALESCE does not skip empty strings, so normalize for tooltips and data-port-status-label.
                $statusLabel = trim((string)($p['status_label'] ?? ''));
                if ($statusLabel === '') {
                    $statusLabel = 'Unknown';
                }

                $portTypeLabel = trim((string)($p['port_type_label'] ?? ''));
                if ($portTypeLabel !== '') {
                    $titleParts[] = 'Type: ' . $portTypeLabel;
                }
                $portLabelDisplay = function_exists('idf_normalize_port_label_value')
                    ? idf_normalize_port_label_value($p['label'] ?? '')
                    : trim((string)($p['label'] ?? ''));
                if ($portLabelDisplay !== '') {
                    $titleParts[] = 'Label: ' . $portLabelDisplay;
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
                if ($cableHexColor !== '' && strcasecmp($cableHexColor, $cableName) !== 0) {
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
                } else {
                    $titleParts[] = 'VLAN: -';
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
                $portId = (int)($p['id'] ?? 0);
                $portNoAttr = (int)($p['port_no'] ?? 0);
                if ($clickable && $portId > 0) {
                    $onClick = 'onclick="if(typeof onPortClick === \'function\') onPortClick(' . $portId . ', this)"';
                } elseif ($clickable && $portNoAttr > 0) {
                    $onClick = 'onclick="if(typeof onPortDotClick === \'function\') onPortDotClick(this)"';
                } else {
                    $onClick = '';
                }
                $cursor = $clickable ? 'pointer' : 'default';
                $portNumberAttr = $portId <= 0 && $portNoAttr > 0
                    ? ' data-port-number="' . $portNoAttr . '"'
                    : '';

                $glow = $isActive ? "box-shadow: 0 0 8px $statusColor;" : "";
                $portStatusLabelAttr = sanitize($statusLabel);
                $portPositionIdAttr = (int)($p['position_id'] ?? 0);
                $portTypeKey = itm_port_visualizer_type_key($p);
                $portBorderRadius = $portTypeKey === 'sfp' ? '50%' : '3px';
                $portTypeClass = $portTypeKey === 'rj45' ? '' : ' itm-port-item--' . sanitize($portTypeKey);
                // Why: Vertical layout places odd/even pairs in the same column; without explicit grid lines auto-flow packs DOM order left-to-right like horizontal.
                $portGridRow = (int)$r + 1;
                $portGridCol = (int)$c + 1;
                // Why: Tooltip still uses merged `connected_to`; click routing uses idf_links only via itm_port_visualizer_click_has_explicit_connection().
                $portConnectedToAttr = sanitize(trim((string)($p['idf_port_connected_for_routing'] ?? '')));
                $portLinkIdAttr = isset($p['link_id']) ? (int)$p['link_id'] : 0;
                $portExplicitConnAttr = itm_port_visualizer_click_has_explicit_connection($p) ? '1' : '0';

                $html .= '<div class="itm-port-item' . $portTypeClass . '" title="' . sanitize($title) . '" data-port-id="' . $portId . '" data-port-status-label="' . $portStatusLabelAttr . '" data-position-id="' . $portPositionIdAttr . '" data-port-type="' . sanitize($portTypeKey) . '" data-port-connected-to="' . $portConnectedToAttr . '" data-port-link-id="' . $portLinkIdAttr . '" data-has-explicit-connection="' . $portExplicitConnAttr . '"' . $portNumberAttr . ' ' . $onClick . ' style="
                    grid-row: ' . $portGridRow . ';
                    grid-column: ' . $portGridCol . ';
                    width: 14px;
                    height: 14px;
                    background-color: ' . sanitize($statusColor) . ';
                    border-radius: ' . $portBorderRadius . ';
                    cursor: ' . $cursor . ';
                    ' . $glow . '
                    transition: all 0.2s;
                " onmouseover="this.style.transform=\'scale(1.4)\'; this.style.zIndex=\'10\'; this.style.boxShadow=\'0 0 12px \' + this.style.backgroundColor;" onmouseout="this.style.transform=\'scale(1)\'; this.style.zIndex=\'1\'; this.style.boxShadow=\'' . ($isActive ? "0 0 8px $statusColor" : "none") . '\';">';
                $html .= '</div>';
            }
        }
        $html .= '</div>'; // end grid

        if ($fragmentMode !== '') {
            return $html;
        }

        $showDeviceIcon = isset($options['show_device_icon']) ? !empty($options['show_device_icon']) : true;
        if ($showDeviceIcon) {
            $portMetaByTypeAndNo = [];
            $rj45Ports = [];
            $sfpPorts = [];

            foreach ($allPortsForMeta as $portMeta) {
                $portNo = (int)($portMeta['port_no'] ?? 0);
                if ($portNo <= 0) {
                    continue;
                }
                $typeKey = itm_port_visualizer_type_key($portMeta);
                if ($typeKey === 'sfp') {
                    $sfpPorts[] = $portNo;
                } else {
                    $rj45Ports[] = $portNo;
                }
                $portMetaByTypeAndNo[$typeKey . ':' . $portNo] = $portMeta;
            }


            // Why: Equipment master fields define physical port capacity; always prefer those configured ranges over sparse/legacy IDF port rows.
            // Passing [] must not discard fiber rows mined from hydrated ports — only non-empty overrides should replace slot lists.
            if (!empty($options['rj45_ports']) && is_array($options['rj45_ports'])) {
                $rj45Ports = [];
                foreach ($options['rj45_ports'] as $rj45PortNo) {
                    $rj45Ports[] = (int)$rj45PortNo;
                }
            }
            $sfpSlotsOverride = [];
            if (!empty($options['sfp_ports']) && is_array($options['sfp_ports'])) {
                foreach ($options['sfp_ports'] as $sfpPortNo) {
                    $sfpSlotsOverride[] = (int)$sfpPortNo;
                }
            }
            if (!empty($options['sfp_plus_ports']) && is_array($options['sfp_plus_ports'])) {
                foreach ($options['sfp_plus_ports'] as $legacyPlusNo) {
                    $sfpSlotsOverride[] = (int)$legacyPlusNo;
                }
            }
            if (!empty($sfpSlotsOverride)) {
                $sfpPorts = array_values(array_unique($sfpSlotsOverride));
            }

            sort($rj45Ports);
            sort($sfpPorts);

            $iconTitleParts = [];
            if (!empty($rj45Ports)) {
                $iconTitleParts[] = 'RJ45 Ports: ' . implode(', ', $rj45Ports);
            }
            if (!empty($sfpPorts)) {
                $iconTitleParts[] = 'Fiber (SFP) Ports: ' . implode(', ', $sfpPorts);
            }
            $iconTitle = empty($iconTitleParts) ? 'Ports not configured' : implode(' • ', $iconTitleParts);

            $iconDots = [];
            foreach ($sfpPorts as $portNo) {
                $iconDots[] = ['type' => 'sfp', 'no' => (int)$portNo];
            }

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

            if (!empty($iconDots)) {
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
                $dotNo = (int)($dotMeta['no'] ?? 0);
                $dotTypeRaw = (string)($dotMeta['type'] ?? '');
                $dotKey = $dotTypeRaw . ':' . $dotNo;
                $dotTitle = 'Port ' . $dotNo;
                // Why: Default empty so dots without port rows still expose a predictable data attribute for view.php routing.
                $dotConnectedTo = '';
                $dotLinkId = 0;
                // Why: Virtual SFP dots without persisted rows should not route users to edit flows.
                $dotIsClickable = !empty($options['clickable']) && $dotNo > 0 && $dotTypeRaw !== '';
                if (isset($portMetaByTypeAndNo[$dotKey])) {
                    $dotPort = $portMetaByTypeAndNo[$dotKey];
                    $dotType = $dotTypeRaw;
                    $dotColor = itm_resolve_port_display_color($dotPort, '#808080');
                    $dotStyleRules = [];
                    if ($dotColor !== '') {
                        $dotStyleRules[] = 'background:' . sanitize($dotColor);
                    }
                    if ($dotType === 'sfp') {
                        // Why: Keep fiber (SFP family) color previews fully solid so selected cable/status colors are not visually faded.
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
                    $dotStatusLabel = trim((string)($dotPort['status_label'] ?? ''));
                    if ($dotStatusLabel === '') {
                        $dotStatusLabel = 'Unknown';
                    }
                    $dotLabel = trim((string)($dotPort['label'] ?? ''));
                    $dotVlanLabel = trim((string)($dotPort['vlan_label'] ?? ''));
                    $dotVlan = (int)($dotPort['vlan_id'] ?? 0);
                    $dotConnectedTo = trim((string)($dotPort['connected_to'] ?? ''));
                    $dotCableName = trim((string)($dotPort['cable_color_name'] ?? ''));
                    $dotCableHex = trim((string)($dotPort['cable_hex_color'] ?? ''));
                    $dotCableLabel = trim((string)($dotPort['cable_label'] ?? ''));
                    $dotLinkNotes = trim((string)($dotPort['link_notes'] ?? ''));
                    $dotLinkId = isset($dotPort['link_id']) ? (int)($dotPort['link_id']) : 0;
                    $dotTitleParts = [];
                    $dotContextParts = [];
                    $dotContextCompany = trim((string)($options['company_name'] ?? ''));
                    if ($dotContextCompany !== '') { $dotContextParts[] = $dotContextCompany; }
                    $dotContextLocation = trim((string)($options['location_name'] ?? ''));
                    if ($dotContextLocation !== '') { $dotContextParts[] = 'Location: ' . $dotContextLocation; }
                    $dotContextIdfName = trim((string)($options['idf_name'] ?? ''));
                    if ($dotContextIdfName !== '') { $dotContextParts[] = 'Name: ' . $dotContextIdfName; }
                    $dotContextIdfCode = trim((string)($options['idf_code'] ?? ''));
                    if ($dotContextIdfCode !== '') { $dotContextParts[] = 'IDF Code: ' . $dotContextIdfCode; }
                    $dotContextRack = trim((string)($options['rack_name'] ?? ''));
                    if ($dotContextRack !== '') { $dotContextParts[] = 'Rack: ' . $dotContextRack; }
                    if (!empty($dotContextParts)) {
                        $dotTitleParts[] = implode(' • ', $dotContextParts);
                    }
                    $dotFromParts = ['From:', 'Port ' . (int)($dotMeta['no'] ?? 0) . ' (' . $dotStatusLabel . ')'];
                    if ($dotTypeLabel !== '') { $dotFromParts[] = $dotTypeLabel; }
                    $dotLocalType = trim((string)($dotPort['local_device_type_label'] ?? ''));
                    if ($dotLocalType !== '') { $dotFromParts[] = $dotLocalType; }
                    $dotLocalName = trim((string)($dotPort['local_device_name'] ?? ''));
                    if ($dotLocalName !== '') { $dotFromParts[] = $dotLocalName; }
                    $dotLocalAsset = itm_format_visualizer_equipment_code($dotPort['local_equipment_id'] ?? '');
                    if ($dotLocalAsset !== '') { $dotFromParts[] = 'Asset ' . $dotLocalAsset; }
                    if (!empty($dotPort['local_position_no'])) { $dotFromParts[] = 'Pos ' . (int)$dotPort['local_position_no']; }
                    $dotLocalIdf = trim((string)($dotPort['local_idf_name'] ?? ''));
                    if ($dotLocalIdf !== '') { $dotFromParts[] = 'IDF ' . $dotLocalIdf; }
                    $dotTitleParts[] = implode(' • ', $dotFromParts);
                    if ($dotLabel !== '' && $dotLabel !== '0') {
                        $dotTitleParts[] = 'Label: ' . $dotLabel;
                    }
                    if ($dotVlanLabel !== '') {
                        $dotTitleParts[] = 'VLAN: ' . $dotVlanLabel;
                    } elseif ($dotVlan > 0) {
                        $dotTitleParts[] = 'VLAN: ' . $dotVlan;
                    } else {
                        $dotTitleParts[] = 'VLAN: -';
                    }
                    $dotConnectedParts = ['Connected To:'];
                    if ($dotConnectedTo !== '') { $dotConnectedParts[] = $dotConnectedTo; }
                    $dotRemoteStatus = trim((string)($dotPort['remote_status_label'] ?? ''));
                    if ($dotRemoteStatus !== '') { $dotConnectedParts[] = 'Status (' . $dotRemoteStatus . ')'; }
                    $dotRemoteType = trim((string)($dotPort['remote_device_type_label'] ?? ''));
                    if ($dotRemoteType !== '') { $dotConnectedParts[] = $dotRemoteType; }
                    $dotRemoteName = trim((string)($dotPort['remote_device_name'] ?? ''));
                    if ($dotRemoteName !== '') { $dotConnectedParts[] = $dotRemoteName; }
                    $dotRemoteAsset = itm_format_visualizer_equipment_code($dotPort['remote_equipment_id'] ?? '');
                    if ($dotRemoteAsset !== '') { $dotConnectedParts[] = 'Asset ' . $dotRemoteAsset; }
                    if (!empty($dotPort['remote_position_no'])) { $dotConnectedParts[] = 'Pos ' . (int)$dotPort['remote_position_no']; }
                    if (!empty($dotPort['remote_port_no'])) { $dotConnectedParts[] = 'Port ' . (int)$dotPort['remote_port_no']; }
                    if ($dotCableName !== '') { $dotConnectedParts[] = 'Cable color: ' . $dotCableName; }
                    if ($dotCableHex !== '' && strcasecmp($dotCableHex, $dotCableName) !== 0) { $dotConnectedParts[] = $dotCableHex; }
                    if ($dotCableLabel !== '') { $dotConnectedParts[] = 'Cable label: ' . $dotCableLabel; }
                    if (count($dotConnectedParts) > 1) {
                        $dotTitleParts[] = implode(' • ', $dotConnectedParts);
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
                $dotStatusRaw = ($dotPort && array_key_exists('status_label', $dotPort)) ? trim((string)$dotPort['status_label']) : '';
                $dotStatusAttr = sanitize($dotStatusRaw !== '' ? $dotStatusRaw : 'Unknown');
                // Why: Fiber placeholder dots can be rendered before an IDF port row exists; keep routing by falling back to the card position id.
                $dotFallbackPositionId = isset($options['position_id']) ? (int)$options['position_id'] : 0;
                $dotPositionIdAttr = isset($dotPort['position_id']) ? (int)$dotPort['position_id'] : $dotFallbackPositionId;
                $dotRoutingConnectedTo = $dotPort ? trim((string)($dotPort['idf_port_connected_for_routing'] ?? '')) : '';
                // Why: Compact fiber dots use the same idf_links-only rack click rule as RJ45 cells.
                $dotExplicitConnAttr = ($dotPort && itm_port_visualizer_click_has_explicit_connection($dotPort)) ? '1' : '0';
                $dotDataAttrs = ' data-port-id="' . $dotPortId . '" data-port-status-label="' . $dotStatusAttr . '" data-position-id="' . $dotPositionIdAttr . '" data-port-number="' . (int)($dotMeta['no'] ?? 0) . '" data-port-type="' . sanitize((string)($dotMeta['type'] ?? '')) . '" data-port-connected-to="' . sanitize($dotRoutingConnectedTo) . '" data-port-link-id="' . (int)$dotLinkId . '" data-has-explicit-connection="' . $dotExplicitConnAttr . '"';
                $dotOnClick = '';
                if ($dotIsClickable) {
                    $dotOnClick = ' onclick="if(typeof onPortDotClick === \'function\') onPortDotClick(this)"';
                }
                $html .= '<div class="itm-device-icon-dot" title="' . sanitize($dotTitle) . '"' . $dotDataAttrs . $dotStyle . $dotOnClick . '></div>';
                }
                $html .= '</div>';
            }
        }


        $html .= '</div>'; // end container

        return $html;
    }
}


