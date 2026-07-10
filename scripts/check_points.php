<?php
/**
 * Audits network points and connections.
 *
 * Why: Verification of floor designer points mapping to equipment ports.
 *
 * Browser: open scripts/check_points.php (login required).
 * CLI: php scripts/check_points.php
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    define('ITM_CLI_SCRIPT', true);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/lib/script_cli_output.php';

if (PHP_SAPI !== 'cli' && !itm_is_admin($conn, (int)($_SESSION['employee_id'] ?? 0))) {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

$nl = itm_script_output_nl();
itm_script_output_begin('Check Points');

$id = 5; // Default floor designer ID to check
echo "Auditing Floor Designer ID: $id" . $nl;

$res = mysqli_query($conn, "SELECT p.id, p.company_id, p.point_type_id, st.type as type_name, st.company_id as type_company_id
                            FROM floor_designer_points p
                            LEFT JOIN switch_port_types st ON st.id = p.point_type_id
                            WHERE p.floor_designer_id = $id");

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        echo "Point ID: {$row['id']} | Type: {$row['type_name']} (ID: {$row['point_type_id']}) | Co: {$row['company_id']}" . $nl;
    }
} else {
    echo colorText("Error fetching points: " . mysqli_error($conn), 'fail') . $nl;
}

echo $nl . "### Available Switch Port Types:" . $nl;
$res = mysqli_query($conn, "SELECT id, company_id, type FROM switch_port_types");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        echo " - Type ID: {$row['id']} | Company: {$row['company_id']} | Name: {$row['type']}" . $nl;
    }
}

itm_script_output_end();
