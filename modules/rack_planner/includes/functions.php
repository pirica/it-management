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

function rack_planner_price_text($value): string
{
    if ($value === null || $value === '') {
        return 'N/A';
    }
    if (!is_numeric($value)) {
        return 'N/A';
    }
    return number_format((float)$value, 2, '.', ',');
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

function rack_planner_fetch_equipment_picker_options(mysqli $conn, int $companyId): array
{
    $options = [];
    if ($companyId <= 0) {
        return $options;
    }

    $equipmentSql = "SELECT e.id, e.name, e.purchase_cost, et.name AS equipment_type_name
                     FROM equipment e
                     LEFT JOIN equipment_types et ON et.id = e.equipment_type_id AND et.company_id = e.company_id
                     WHERE e.company_id = ? AND e.deleted_at IS NULL
                     ORDER BY e.name ASC, e.id ASC";
    $equipmentStmt = mysqli_prepare($conn, $equipmentSql);
    if ($equipmentStmt) {
        mysqli_stmt_bind_param($equipmentStmt, 'i', $companyId);
        mysqli_stmt_execute($equipmentStmt);
        $equipmentRes = mysqli_stmt_get_result($equipmentStmt);
        while ($equipmentRes && ($row = mysqli_fetch_assoc($equipmentRes))) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $equipmentType = trim((string)($row['equipment_type_name'] ?? ''));
            if ($equipmentType === '') {
                $equipmentType = 'Other';
            }

            $priceValue = null;
            if (isset($row['purchase_cost']) && $row['purchase_cost'] !== null && $row['purchase_cost'] !== '' && is_numeric($row['purchase_cost'])) {
                $priceValue = (float)$row['purchase_cost'];
            }

            $priceText = rack_planner_price_text($priceValue);
            $size = rack_planner_is_two_ru_name($name) ? 2 : 1;
            $code = 'equipment:' . (int)$row['id'];
            $selectText = $name . ' - ' . $equipmentType . ' - ' . $priceText;

            $options[] = [
                'code' => $code,
                'label' => $name,
                'select_text' => $selectText,
                'size' => $size,
                'equipment_type' => $equipmentType,
                'price' => $priceText,
                'price_value' => $priceValue,
            ];
        }
        mysqli_stmt_close($equipmentStmt);
    }

    $unlinkedSql = "SELECT p.equipment_id, p.device_name, p.price, dt.idfdevicetype_name
                    FROM idf_positions p
                    LEFT JOIN idf_device_type dt ON dt.id = p.device_type AND dt.company_id = p.company_id
                    WHERE p.company_id = ?
                      AND p.equipment_id IS NOT NULL
                      AND p.equipment_id REGEXP '^[0-9]{4}-[0-9]{4}$'
                    ORDER BY p.device_name ASC, p.equipment_id ASC";
    $unlinkedStmt = mysqli_prepare($conn, $unlinkedSql);
    if ($unlinkedStmt) {
        mysqli_stmt_bind_param($unlinkedStmt, 'i', $companyId);
        mysqli_stmt_execute($unlinkedStmt);
        $unlinkedRes = mysqli_stmt_get_result($unlinkedStmt);
        while ($unlinkedRes && ($row = mysqli_fetch_assoc($unlinkedRes))) {
            $token = trim((string)($row['equipment_id'] ?? ''));
            $deviceName = trim((string)($row['device_name'] ?? ''));
            if ($token === '' || $deviceName === '') {
                continue;
            }

            $equipmentType = trim((string)($row['idfdevicetype_name'] ?? ''));
            if ($equipmentType === '') {
                $equipmentType = 'Other';
            }

            $priceValue = null;
            if (isset($row['price']) && $row['price'] !== null && $row['price'] !== '' && is_numeric($row['price'])) {
                $priceValue = (float)$row['price'];
            }

            $priceText = rack_planner_price_text($priceValue);
            $size = rack_planner_is_two_ru_name($deviceName) ? 2 : 1;
            $code = 'idf_unlinked:' . $token;
            $selectText = $deviceName . ' - ' . $equipmentType . ' - ' . $priceText;

            $options[] = [
                'code' => $code,
                'label' => $deviceName,
                'select_text' => $selectText,
                'size' => $size,
                'equipment_type' => $equipmentType,
                'price' => $priceText,
                'price_value' => $priceValue,
            ];
        }
        mysqli_stmt_close($unlinkedStmt);
    }

    usort($options, static function (array $a, array $b): int {
        $labelCompare = strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        if ($labelCompare !== 0) {
            return $labelCompare;
        }
        return strcasecmp((string)($a['code'] ?? ''), (string)($b['code'] ?? ''));
    });

    return $options;
}

function rack_planner_combined_code_meta_map(array $catalogOptions, array $equipmentOptions): array
{
    $map = rack_planner_catalog_code_meta_map($catalogOptions);
    foreach ($equipmentOptions as $option) {
        if (!is_array($option)) {
            continue;
        }

        $code = trim((string)($option['code'] ?? ''));
        if ($code === '') {
            continue;
        }

        $size = ((int)($option['size'] ?? 1) === 2) ? 2 : 1;
        $label = trim((string)($option['label'] ?? ''));
        if ($label === '') {
            $label = $code;
        }

        $map[$code] = [
            'label' => trim((string)($option['select_text'] ?? $label)),
            'size' => $size,
            'price' => isset($option['price_value']) && is_numeric($option['price_value']) ? (float)$option['price_value'] : null,
        ];
    }

    return $map;
}

function rack_planner_sync_source_prices_from_layout(mysqli $conn, int $companyId, array $layout): bool
{
    if ($companyId <= 0 || !isset($layout['devices']) || !is_array($layout['devices'])) {
        return true;
    }

    $catalogPriceById = [];
    $equipmentPriceById = [];
    $unlinkedPriceByToken = [];

    foreach ($layout['devices'] as $device) {
        if (!is_array($device)) {
            continue;
        }

        $code = trim((string)($device['code'] ?? ''));
        if ($code === '' || !isset($device['price']) || !is_numeric($device['price'])) {
            continue;
        }

        $price = (float)$device['price'];
        if (preg_match('/^catalog:(\d+)$/', $code, $matches)) {
            $catalogId = (int)($matches[1] ?? 0);
            if ($catalogId > 0) {
                $catalogPriceById[$catalogId] = $price;
            }
            continue;
        }

        if (preg_match('/^equipment:(\d+)$/', $code, $matches)) {
            $equipmentId = (int)($matches[1] ?? 0);
            if ($equipmentId > 0) {
                $equipmentPriceById[$equipmentId] = $price;
            }
            continue;
        }

        if (preg_match('/^idf_unlinked:([0-9]{4}-[0-9]{4})$/', $code, $matches)) {
            $token = trim((string)($matches[1] ?? ''));
            if ($token !== '') {
                $unlinkedPriceByToken[$token] = $price;
            }
        }
    }

    $allOk = true;

    if (!empty($catalogPriceById)) {
        $stmt = mysqli_prepare($conn, "UPDATE catalogs SET price = ? WHERE company_id = ? AND id = ?");
        if (!$stmt) {
            $allOk = false;
        } else {
            foreach ($catalogPriceById as $catalogId => $price) {
                $catalogId = (int)$catalogId;
                $price = (float)$price;
                mysqli_stmt_bind_param($stmt, 'dii', $price, $companyId, $catalogId);
                if (!mysqli_stmt_execute($stmt)) {
                    $allOk = false;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (!empty($equipmentPriceById)) {
        $stmt = mysqli_prepare($conn, "UPDATE equipment SET purchase_cost = ? WHERE company_id = ? AND id = ?");
        if (!$stmt) {
            $allOk = false;
        } else {
            foreach ($equipmentPriceById as $equipmentId => $price) {
                $equipmentId = (int)$equipmentId;
                $price = (float)$price;
                mysqli_stmt_bind_param($stmt, 'dii', $price, $companyId, $equipmentId);
                if (!mysqli_stmt_execute($stmt)) {
                    $allOk = false;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    if (!empty($unlinkedPriceByToken)) {
        $stmt = mysqli_prepare($conn, "UPDATE idf_positions SET price = ? WHERE company_id = ? AND equipment_id = ? AND equipment_id REGEXP '^[0-9]{4}-[0-9]{4}$'");
        if (!$stmt) {
            $allOk = false;
        } else {
            foreach ($unlinkedPriceByToken as $token => $price) {
                $token = trim((string)$token);
                $price = (float)$price;
                mysqli_stmt_bind_param($stmt, 'dis', $price, $companyId, $token);
                if (!mysqli_stmt_execute($stmt)) {
                    $allOk = false;
                }
            }
            mysqli_stmt_close($stmt);
        }
    }

    return $allOk;
}

function rack_planner_normalize_layout_json(string $layoutJson, int $rackUnits, array $dynamicCodeMeta = []): array
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
        } elseif (isset($dynamicCodeMeta[$code])) {
            $meta = $dynamicCodeMeta[$code];
        } elseif (strpos($code, 'catalog:') === 0 || strpos($code, 'equipment:') === 0 || strpos($code, 'idf_unlinked:') === 0) {
            $fallbackLabel = trim((string)($rawDevice['label'] ?? $code));
            $meta = [
                'label' => $fallbackLabel,
                'size' => (int)($rawDevice['size'] ?? (rack_planner_is_two_ru_name($fallbackLabel) ? 2 : 1)),
                'price' => (isset($rawDevice['price']) && is_numeric($rawDevice['price'])) ? (float)$rawDevice['price'] : null,
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
        if ($code !== 'empty') {
            // Prefer trailing label amount when present so edited modal prices are preserved.
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
