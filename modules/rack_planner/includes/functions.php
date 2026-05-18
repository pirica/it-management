<?php
function rack_planner_component_catalog(): array
{
    return [
        'empty' => ['label' => ' - Empty - ', 'size' => 1],
        'pp24' => ['label' => '24-Port Patch Panel Cat.', 'size' => 1],
        'pp48' => ['label' => '48-Port Patch Panel Cat.', 'size' => 1],
        'ppfo24' => ['label' => '24-Port Patch Panel Fiber Optic', 'size' => 1],
        'ppfo48' => ['label' => '48-Port Patch Panel Fiber Optic', 'size' => 1],
        'sw24' => ['label' => '24-Port Switch', 'size' => 1],
        'sw48' => ['label' => '48-Port Switch', 'size' => 1],
        'bs' => ['label' => '1-RU Server', 'size' => 1],
        'bs_2' => ['label' => '2-RU Server', 'size' => 2],
        'ds' => ['label' => '1-RU Data Storage', 'size' => 1],
        'rt' => ['label' => '1-RU Router', 'size' => 1],
        'tr_2' => ['label' => '2-RU Rack Tray', 'size' => 2],
        'ph' => ['label' => '1-RU Placeholder', 'size' => 1],
        'ph_2' => ['label' => '2-RU Placeholder', 'size' => 2],
    ];
}

function rack_planner_component_groups(): array
{
    return [
        'Empty' => ['empty'],
        'Patchpanel' => ['pp24', 'pp48', 'ppfo24', 'ppfo48'],
        'Switch' => ['sw24', 'sw48'],
        'Server' => ['bs', 'bs_2'],
        'Other devices' => ['ds', 'rt', 'tr_2', 'ph', 'ph_2'],
    ];
}

function rack_planner_is_two_ru_name(string $name): bool
{
    return (bool)preg_match('/\b2\s*-\s*ru\b|\b2\s*ru\b/i', $name);
}

function rack_planner_extract_price_from_text(string $text)
{
    $input = trim($text);
    if ($input === '') {
        return null;
    }

    if (!preg_match('/(?:^|[\s:;,\-])([+-]?\d[\d.,]*)\s*(?:\x{20AC}|\$|usd|eur)?\s*$/iu', $input, $matches)) {
        return null;
    }

    $candidate = trim((string)($matches[1] ?? ''));
    if ($candidate === '') {
        return null;
    }

    $normalized = str_replace(' ', '', $candidate);
    $hasComma = strpos($normalized, ',') !== false;
    $hasDot = strpos($normalized, '.') !== false;

    if ($hasComma && $hasDot) {
        if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }
    } elseif ($hasComma) {
        $parts = explode(',', $normalized);
        if (count($parts) === 2 && strlen((string)$parts[1]) <= 2) {
            $normalized = str_replace('.', '', (string)$parts[0]) . '.' . (string)$parts[1];
        } else {
            $normalized = str_replace(',', '', $normalized);
        }
    } else {
        $normalized = str_replace(',', '', $normalized);
    }

    if (!is_numeric($normalized)) {
        return null;
    }

    return (float)$normalized;
}

function rack_planner_fetch_catalog_options(mysqli $conn, int $companyId): array
{
    $options = [];
    $seenModelKeys = [];
    if ($companyId <= 0) {
        return $options;
    }

    $sql = "SELECT c.id, c.model, c.price, et.name AS equipment_type_name
            FROM catalogs c
            LEFT JOIN equipment_types et ON et.id = c.equipment_type_id
            WHERE c.company_id = ? AND c.active = 1
            ORDER BY c.equipment_type_id ASC, c.model ASC, c.id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return $options;
    }

    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $model = trim((string)($row['model'] ?? ''));
        if ($model === '') {
            continue;
        }
        $modelKey = strtolower($model);
        if (isset($seenModelKeys[$modelKey])) {
            continue;
        }
        $seenModelKeys[$modelKey] = true;

        $equipmentType = trim((string)($row['equipment_type_name'] ?? ''));
        if ($equipmentType === '') {
            $equipmentType = 'Other';
        }

        $priceText = 'N/A';
        $priceValue = null;
        if (isset($row['price']) && $row['price'] !== null && $row['price'] !== '') {
            $priceValue = (float)$row['price'];
            $priceText = number_format($priceValue, 2, '.', ',');
        }

        $size = rack_planner_is_two_ru_name($model) ? 2 : 1;
        $code = 'catalog:' . (int)$row['id'];
        $selectText = $model . ' - ' . $equipmentType . ' - ' . $priceText;

        $options[] = [
            'code' => $code,
            'label' => $model,
            'select_text' => $selectText,
            'size' => $size,
            'model' => $model,
            'equipment_type' => $equipmentType,
            'price' => $priceText,
            'price_value' => $priceValue,
        ];
    }
    mysqli_stmt_close($stmt);

    return $options;
}

function rack_planner_catalog_code_meta_map(array $catalogOptions): array
{
    $map = [];
    foreach ($catalogOptions as $catalogOption) {
        if (!is_array($catalogOption)) {
            continue;
        }

        $code = trim((string)($catalogOption['code'] ?? ''));
        if ($code === '') {
            continue;
        }

        $size = ((int)($catalogOption['size'] ?? 1) === 2) ? 2 : 1;
        $label = trim((string)($catalogOption['label'] ?? ''));
        if ($label === '') {
            $label = $code;
        }

        $map[$code] = [
            'label' => trim((string)($catalogOption['select_text'] ?? $label)),
            'size' => $size,
            'price' => isset($catalogOption['price_value']) && is_numeric($catalogOption['price_value']) ? (float)$catalogOption['price_value'] : null,
        ];
    }

    return $map;
}

function rack_planner_normalize_layout_json(string $layoutJson, int $rackUnits, array $catalogCodeMeta = []): array
{
    $units = max(1, min(100, $rackUnits));
    $catalog = rack_planner_component_catalog();

    $decoded = json_decode($layoutJson, true);
    $rawDevices = [];
    if (is_array($decoded) && isset($decoded['devices']) && is_array($decoded['devices'])) {
        $rawDevices = $decoded['devices'];
    }

    $devices = [];
    $occupied = [];

    foreach ($rawDevices as $rawDevice) {
        if (!is_array($rawDevice)) {
            continue;
        }

        $code = trim((string)($rawDevice['code'] ?? ''));
        if ($code === '') {
            continue;
        }

        $meta = null;
        if (isset($catalog[$code])) {
            $meta = $catalog[$code];
        } elseif (isset($catalogCodeMeta[$code])) {
            $meta = $catalogCodeMeta[$code];
        } elseif (strpos($code, 'catalog:') === 0) {
            $meta = [
                'label' => trim((string)($rawDevice['label'] ?? $code)),
                'size' => (int)($rawDevice['size'] ?? 1),
            ];
        } else {
            continue;
        }

        $size = (int)($meta['size'] ?? 1);
        if ($size !== 2) {
            $size = 1;
        }

        $startU = (int)($rawDevice['start_u'] ?? ($rawDevice['unit'] ?? 0));
        if ($startU < 1 || ($startU + $size - 1) > $units) {
            continue;
        }

        $canPlace = true;
        for ($u = $startU; $u < $startU + $size; $u++) {
            if (isset($occupied[$u])) {
                $canPlace = false;
                break;
            }
        }
        if (!$canPlace) {
            continue;
        }

        for ($u = $startU; $u < $startU + $size; $u++) {
            $occupied[$u] = true;
        }

        $label = trim((string)($rawDevice['label'] ?? ''));
        if ($label === '') {
            $label = trim((string)($meta['label'] ?? ''));
        }
        if (strpos($code, 'catalog:') === 0 && trim((string)($meta['label'] ?? '')) !== '') {
            // Keep catalog labels standardized as "Model - Equipment Type - Price".
            $label = trim((string)$meta['label']);
        }
        if ($label === '') {
            $label = $code;
        }

        $price = null;
        if ($code !== 'empty' && strpos($code, 'catalog:') !== 0) {
            // Keep non-catalog component prices parsed from the trailing label amount.
            $parsedLabelPrice = rack_planner_extract_price_from_text($label);
            if ($parsedLabelPrice !== null && is_numeric($parsedLabelPrice)) {
                $price = (float)$parsedLabelPrice;
            }
        }
        if ($price === null) {
            if (isset($meta['price']) && is_numeric($meta['price'])) {
                $price = (float)$meta['price'];
            } elseif (isset($rawDevice['price']) && is_numeric($rawDevice['price'])) {
                $price = (float)$rawDevice['price'];
            }
        }

        $devices[] = [
            'code' => $code,
            'label' => $label,
            'start_u' => $startU,
            'size' => $size,
            'price' => $price,
        ];
    }

    usort($devices, static function (array $a, array $b): int {
        return $b['start_u'] <=> $a['start_u'];
    });

    return [
        'version' => 1,
        'units' => $units,
        'devices' => $devices,
    ];
}

function rack_planner_layout_total(array $layout): float
{
    $total = 0.0;
    if (!isset($layout['devices']) || !is_array($layout['devices'])) {
        return $total;
    }

    foreach ($layout['devices'] as $device) {
        if (!is_array($device)) {
            continue;
        }
        if (!isset($device['price']) || !is_numeric($device['price'])) {
            continue;
        }
        $total += (float)$device['price'];
    }

    return $total;
}

function rack_planner_encode_layout(array $layout): string
{
    $json = json_encode($layout, JSON_UNESCAPED_UNICODE);
    if (!is_string($json) || $json === '') {
        return '{"version":1,"units":42,"devices":[]}';
    }
    return $json;
}

function rack_planner_assignments_by_unit(array $layout): array
{
    $assignments = [];
    foreach ($layout['devices'] as $device) {
        $startU = (int)$device['start_u'];
        $size = (int)$device['size'];
        for ($u = $startU; $u < $startU + $size; $u++) {
            $assignments[$u] = [
                'code' => (string)$device['code'],
                'label' => (string)$device['label'],
                'start_u' => $startU,
                'size' => $size,
                'price' => (isset($device['price']) && is_numeric($device['price'])) ? (float)$device['price'] : null,
            ];
        }
    }
    return $assignments;
}

