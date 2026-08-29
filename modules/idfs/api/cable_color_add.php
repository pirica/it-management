<?php
require_once __DIR__ . '/_bootstrap.php';

$data = idf_read_json();
idf_require_csrf($data);

function idf_is_hex_color(string $value): bool {
    return (bool)preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value);
}

function idf_normalize_hex_color(string $value): string {
    $trimmed = trim($value);
    if (!idf_is_hex_color($trimmed)) {
        return '';
    }
    if (strlen($trimmed) === 4) {
        return '#' . strtoupper($trimmed[1] . $trimmed[1] . $trimmed[2] . $trimmed[2] . $trimmed[3] . $trimmed[3]);
    }
    return '#' . strtoupper(substr($trimmed, 1, 6));
}

function idf_hex_to_color_name(string $hex): string {
    if (!preg_match('/^#([0-9A-F]{6})$/', $hex, $matches)) {
        return '';
    }

    $raw = $matches[1];
    $r = hexdec(substr($raw, 0, 2)) / 255;
    $g = hexdec(substr($raw, 2, 2)) / 255;
    $b = hexdec(substr($raw, 4, 2)) / 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $delta = $max - $min;
    $lightness = ($max + $min) / 2;
    $saturation = 0.0;
    if ($delta > 0.0) {
        $saturation = $delta / (1 - abs(2 * $lightness - 1));
    }

    if ($saturation < 0.12) {
        if ($lightness < 0.12) {
            return 'Black';
        }
        if ($lightness > 0.9) {
            return 'White';
        }
        return 'Gray';
    }

    $hue = 0.0;
    if ($delta > 0.0) {
        if ($max === $r) {
            $hue = fmod((($g - $b) / $delta), 6.0);
        } elseif ($max === $g) {
            $hue = (($b - $r) / $delta) + 2.0;
        } else {
            $hue = (($r - $g) / $delta) + 4.0;
        }
        $hue *= 60.0;
        if ($hue < 0) {
            $hue += 360.0;
        }
    }

    $baseColor = 'Color';
    if ($hue < 15 || $hue >= 345) {
        $baseColor = 'Red';
    } elseif ($hue < 45) {
        $baseColor = 'Orange';
    } elseif ($hue < 70) {
        $baseColor = 'Yellow';
    } elseif ($hue < 165) {
        $baseColor = 'Green';
    } elseif ($hue < 200) {
        $baseColor = 'Cyan';
    } elseif ($hue < 255) {
        $baseColor = 'Blue';
    } elseif ($hue < 290) {
        $baseColor = 'Purple';
    } else {
        $baseColor = 'Pink';
    }

    if ($lightness >= 0.72) {
        return 'Light ' . $baseColor;
    }
    if ($lightness <= 0.32) {
        return 'Dark ' . $baseColor;
    }
    return $baseColor;
}

$colorNameRaw = trim((string)($data['color_name'] ?? ''));
$hexColorRaw = trim((string)($data['hex_color'] ?? ''));
$hexColor = idf_normalize_hex_color($hexColorRaw);

if ($hexColorRaw !== '' && $hexColor === '') {
    idf_fail('Hex color must be in #RGB or #RRGGBB format');
}

$colorName = $colorNameRaw !== '' ? substr($colorNameRaw, 0, 100) : '';
if ($colorName === '' && $hexColor !== '') {
    $colorName = idf_hex_to_color_name($hexColor);
}
if ($colorName === '' && $hexColor === '') {
    idf_fail('Cable color is required');
}
$colorName = substr($colorName !== '' ? $colorName : $hexColor, 0, 100);

$stmtFind = mysqli_prepare(
    $conn,
    'SELECT color_name, hex_color
     FROM cable_colors
     WHERE company_id = ?
       AND (
            LOWER(color_name) = LOWER(?)
            OR (? <> "" AND UPPER(COALESCE(hex_color, "")) = UPPER(?))
       )
     LIMIT 1'
);
if (!$stmtFind) {
    idf_fail('Unable to prepare cable color lookup', 500);
}

mysqli_stmt_bind_param($stmtFind, 'isss', $company_id, $colorName, $hexColor, $hexColor);
mysqli_stmt_execute($stmtFind);
$resFind = mysqli_stmt_get_result($stmtFind);
$existing = $resFind ? mysqli_fetch_assoc($resFind) : null;
mysqli_stmt_close($stmtFind);

if ($existing && isset($existing['color_name'])) {
    idf_ok([
        'color_name' => (string)$existing['color_name'],
        'hex_color' => (string)($existing['hex_color'] ?? ''),
    ]);
}

$stmtInsert = mysqli_prepare(
    $conn,
    'INSERT INTO cable_colors (company_id, color_name, hex_color) VALUES (?, ?, ?)'
);
if (!$stmtInsert) {
    idf_fail('Unable to prepare cable color insert', 500);
}

if ($hexColor === '') {
    $hexColor = null;
}
mysqli_stmt_bind_param($stmtInsert, 'iss', $company_id, $colorName, $hexColor);
if (!mysqli_stmt_execute($stmtInsert)) {
    $insertError = mysqli_stmt_error($stmtInsert);
    mysqli_stmt_close($stmtInsert);
    idf_fail('Unable to save cable color: ' . $insertError, 500);
}
mysqli_stmt_close($stmtInsert);

idf_ok([
    'color_name' => $colorName,
    'hex_color' => (string)($hexColor ?? ''),
]);
