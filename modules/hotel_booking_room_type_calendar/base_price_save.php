<?php
/**
 * Save Base Prices per Room Type for the selected Hotel.
 */
require '../../config/config.php';

$company_id = (int) ($_SESSION['company_id'] ?? 0);
$employee_id = (int) ($_SESSION['employee_id'] ?? 0);
if ($company_id < 1) {
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    itm_require_post_csrf();

    $hotelId = (int) ($_POST['hotel_id'] ?? 0);
    if ($hotelId < 1) {
        header('Location: index.php');
        exit;
    }

    $basePrices = $_POST['base_prices'] ?? [];
    if (is_array($basePrices)) {
        foreach ($basePrices as $roomTypeId => $priceRaw) {
            $roomTypeId = (int) $roomTypeId;
            if ($roomTypeId < 1) {
                continue;
            }

            // Normalize price input
            $priceStr = str_replace(',', '.', trim((string) $priceRaw));
            $price = (float) $priceStr;
            if ($price < 0) {
                $price = 0.0;
            }

            // Check if record exists
            $checkStmt = mysqli_prepare($conn, 'SELECT id FROM hotel_booking_room_type_base_prices WHERE company_id = ? AND hotel_id = ? AND room_type_id = ? AND deleted_at IS NULL LIMIT 1');
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, 'iii', $company_id, $hotelId, $roomTypeId);
                mysqli_stmt_execute($checkStmt);
                $res = mysqli_stmt_get_result($checkStmt);
                $existing = $res ? mysqli_fetch_assoc($res) : null;
                mysqli_stmt_close($checkStmt);

                if ($existing) {
                    $existingId = (int) $existing['id'];
                    $upd = mysqli_prepare($conn, 'UPDATE hotel_booking_room_type_base_prices SET price_per_night = ?, updated_by = ?, updated_at = NOW() WHERE id = ? AND company_id = ?');
                    if ($upd) {
                        mysqli_stmt_bind_param($upd, 'diii', $price, $employee_id, $existingId, $company_id);
                        mysqli_stmt_execute($upd);
                        mysqli_stmt_close($upd);
                    }
                } else {
                    $ins = mysqli_prepare($conn, 'INSERT INTO hotel_booking_room_type_base_prices (company_id, hotel_id, room_type_id, price_per_night, active, created_by, created_at) VALUES (?, ?, ?, ?, 1, ?, NOW())');
                    if ($ins) {
                        mysqli_stmt_bind_param($ins, 'iiidi', $company_id, $hotelId, $roomTypeId, $price, $employee_id);
                        mysqli_stmt_execute($ins);
                        mysqli_stmt_close($ins);
                    }
                }
            }
        }
    }

    header('Location: index.php?hotel_id=' . $hotelId);
    exit;
}

header('Location: index.php');
exit;
