<?php
/**
 * Generate includes/itm_news_feed_ms_support_products.php from feed picker HTML.
 */
$outFile = dirname(__DIR__) . '/includes/itm_news_feed_ms_support_products.php';
$h = @file_get_contents('https://support.microsoft.com/en-us/rss-feed-picker');
if ($h === false) {
    fwrite(STDERR, "fetch failed\n");
    exit(1);
}

if (!preg_match_all('/<option\s+value="([a-f0-9-]{36})"[^>]*>([^<]+)<\/option>/i', $h, $m, PREG_SET_ORDER)) {
    fwrite(STDERR, "no options found\n");
    exit(1);
}

$idOverrides = [
    '6ae59d69-36fc-8e4d-23dd-631d98bf74a9' => 'ms_win10_updates',
    '4ec863cc-2ecd-e187-6cb3-b50c6545db92' => 'ms_win11_updates',
];

$labelOverrides = [
    '6ae59d69-36fc-8e4d-23dd-631d98bf74a9' => 'Windows 10 Updates (KB)',
    '4ec863cc-2ecd-e187-6cb3-b50c6545db92' => 'Windows 11 Updates (KB)',
];

$products = [];
$seenGuids = [];
$usedIds = [];

foreach ($m as $row) {
    $guid = strtolower($row[1]);
    $label = trim(html_entity_decode($row[2], ENT_QUOTES, 'UTF-8'));
    if ($label === '' || isset($seenGuids[$guid])) {
        continue;
    }
    $seenGuids[$guid] = true;

    if (isset($labelOverrides[$guid])) {
        $label = $labelOverrides[$guid];
    }

    if (isset($idOverrides[$guid])) {
        $id = $idOverrides[$guid];
    } else {
        $slug = strtolower($label);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        if (strlen($slug) > 48) {
            $slug = substr($slug, 0, 48);
            $slug = rtrim($slug, '_');
        }
        $id = 'ms_support_' . ($slug !== '' ? $slug : substr(sha1($guid), 0, 8));
        $base = $id;
        $n = 2;
        while (isset($usedIds[$id])) {
            $id = $base . '_' . $n;
            $n++;
        }
    }

    $usedIds[$id] = true;
    $products[] = [
        'id' => $id,
        'guid' => $guid,
        'label' => $label,
    ];
}

usort($products, static function ($a, $b) {
    return strcasecmp($a['label'], $b['label']);
});

$php = "<?php\n";
$php .= "/**\n";
$php .= " * Microsoft Support RSS feed picker products (Atom feeds).\n";
$php .= " * Source: https://support.microsoft.com/en-us/rss-feed-picker\n";
$php .= " * Regenerate: php scripts/generate_ms_support_feed_products.php\n";
$php .= " */\n\n";
$php .= "if (!function_exists('news_microsoft_support_feed_products')) {\n";
$php .= "    function news_microsoft_support_feed_products()\n";
$php .= "    {\n";
$php .= "        return [\n";

foreach ($products as $product) {
    $php .= "            [\n";
    $php .= "                'id' => " . var_export($product['id'], true) . ",\n";
    $php .= "                'guid' => " . var_export($product['guid'], true) . ",\n";
    $php .= "                'label' => " . var_export($product['label'], true) . ",\n";
    $php .= "            ],\n";
}

$php .= "        ];\n";
$php .= "    }\n";
$php .= "}\n";

file_put_contents($outFile, $php);
echo 'wrote ' . count($products) . ' products to ' . $outFile . "\n";
