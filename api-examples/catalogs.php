<?php
/**
 * IT Management System API Example: Catalog Import
 *
 * Demonstrates importing products into the Catalogs module.
 */

$baseUrl = "http://localhost/it-management";
$endpoint = "$baseUrl/modules/catalogs/index.php";
$sessionCookie = "PHPSESSID=your_session_id_here";
$csrfToken = "your_csrf_token_here";

$payload = [
    "csrf_token" => $csrfToken,
    "import_excel_rows" => [
        ["Model", "Price", "Supplier", "Manufacturer", "Product Url"],
        ["Latitude 5440", "1200.00", "Dell Direct", "Dell", "https://www.dell.com/latitude-5440"],
        ["ThinkPad X1 Carbon", "1500.00", "Lenovo Store", "Lenovo", "https://www.lenovo.com/thinkpad-x1"]
    ]
];

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Cookie: $sessionCookie"]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
echo "Response: $response\n";
curl_close($ch);
