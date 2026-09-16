<?php
/**
 * IT Management System API Example: List all Active Catalog Items
 *
 * Demonstrates how to fetch the catalog list and extract items
 * that are marked as "Active".
 */

$baseUrl = "http://localhost/it-management";
$sessionCookie = "PHPSESSID=your_session_id_here";

// Fetch the list of catalogs
$url = "$baseUrl/modules/catalogs/index.php";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: $sessionCookie"]);
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    die("Failed to fetch catalogs list.\n");
}

echo "List of Active Catalog Items:\n";
echo str_repeat("-", 80) . "\n";
echo sprintf("%-5s | %-40s | %-15s | %-10s\n", "ID", "Model", "Price", "Status");
echo str_repeat("-", 80) . "\n";

$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// The catalogs table also uses data-itm-db-import-endpoint="index.php"
$rows = $xpath->query("//table[@data-itm-db-import-endpoint='index.php']/tbody/tr");

foreach ($rows as $row) {
    $cols = $xpath->query("td", $row);
    // Find the Active status column (usually the one with badge-success/badge-danger)
    $isActive = false;

    foreach ($cols as $cell) {
        if (strpos($dom->saveHTML($cell), 'badge-success') !== false && strpos($cell->textContent, 'Active') !== false) {
            $isActive = true;
            break;
        }
    }

    if ($isActive) {
        $id = trim($cols->item(0)->textContent);
        $model = trim($cols->item(1)->textContent);
        $price = trim($cols->item(3)->textContent);

        echo sprintf("%-5s | %-40s | %-15s | %-10s\n", $id, $model, $price, "Active");
    }
}
