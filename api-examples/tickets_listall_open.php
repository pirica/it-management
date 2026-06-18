<?php
/**
 * IT Management System API Example: List all Open Tickets
 *
 * Demonstrates how to fetch the ticket list and filter for "Open" status
 * by using the search query parameter and parsing the HTML response.
 */

$baseUrl = "http://localhost/it-management";
$sessionCookie = "PHPSESSID=your_session_id_here";

// Use the search parameter to filter for "Open"
$url = "$baseUrl/modules/tickets/index.php?search=Open";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: $sessionCookie"]);
$html = curl_exec($ch);
curl_close($ch);

if (!$html) {
    die("Failed to fetch tickets list.\n");
}

// Simple HTML table parsing example
echo "List of Open Tickets:\n";
echo str_repeat("-", 80) . "\n";
echo sprintf("%-5s | %-20s | %-30s | %-10s\n", "ID", "Code", "Title", "Status");
echo str_repeat("-", 80) . "\n";

$dom = new DOMDocument();
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);

// Query all rows in the data table
$rows = $xpath->query("//table[@data-itm-db-import-endpoint='index.php']/tbody/tr");

foreach ($rows as $row) {
    $cols = $xpath->query("td", $row);
    if ($cols->length < 5) continue;

    $id = trim($cols->item(0)->textContent);
    $code = trim($cols->item(1)->textContent);
    $title = trim($cols->item(2)->textContent);
    $status = trim($cols->item(3)->textContent);

    // Double check status matches exactly (in case "Open" was matched in title)
    if (strcasecmp($status, "Open") === 0) {
        echo sprintf("%-5s | %-20s | %-30s | %-10s\n", $id, $code, $title, $status);
    }
}
