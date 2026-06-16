<?php
require_once 'config/config.php';
$id = 5;
$res = mysqli_query($conn, "SELECT p.id, p.company_id, p.point_type_id, st.type as type_name, st.company_id as type_company_id
                            FROM floor_designer_points p
                            LEFT JOIN switch_port_types st ON st.id = p.point_type_id
                            WHERE p.floor_designer_id = $id");
while ($row = mysqli_fetch_assoc($res)) {
    print_r($row);
}
$res = mysqli_query($conn, "SELECT id, company_id, type FROM switch_port_types");
while ($row = mysqli_fetch_assoc($res)) {
    echo "Type ID: {$row['id']}, Company: {$row['company_id']}, Name: {$row['type']}\n";
}
