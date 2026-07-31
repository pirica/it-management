<?php
/**
 * Copy TechCorp Retreat sample hotel and room-type photos onto disk and upsert photo rows.
 *
 * Usage: php scripts/seed_hotel_booking_sample_photos.php [--apply]
 */
define('ITM_CLI_SCRIPT', true);
require dirname(__DIR__) . '/config/config.php';
require ROOT_PATH . 'includes/itm_hotel_booking.php';

$apply = in_array('--apply', $argv ?? [], true);
$companyId = 1;
$hotelId = 1;

$hotelSamples = [
    ['source' => 'booking/images/image_2.jpg', 'portal' => 'booking/images/hotel-sample-exterior.jpg', 'stored' => 'hb_seed_01.jpg', 'original' => 'hotel-sample-exterior.jpg', 'sort_order' => 0, 'is_cover' => 1],
    ['source' => 'booking/images/services-2.jpg', 'portal' => 'booking/images/hotel-sample-lobby.jpg', 'stored' => 'hb_seed_02.jpg', 'original' => 'hotel-sample-lobby.jpg', 'sort_order' => 1, 'is_cover' => 0],
    ['source' => 'booking/images/room-5.jpg', 'portal' => 'booking/images/hotel-sample-room.jpg', 'stored' => 'hb_seed_03.jpg', 'original' => 'hotel-sample-room.jpg', 'sort_order' => 2, 'is_cover' => 0],
    ['source' => 'booking/images/image_3.jpg', 'portal' => 'booking/images/hotel-sample-pool.jpg', 'stored' => 'hb_seed_04.jpg', 'original' => 'hotel-sample-pool.jpg', 'sort_order' => 3, 'is_cover' => 0],
];

$roomTypeSamples = [
    ['type_code' => 'DLX', 'source' => 'booking/images/room-5.jpg', 'stored' => 'hb_rt_dlx_01.jpg', 'original' => 'deluxe-room-1.jpg', 'sort_order' => 0, 'is_cover' => 1],
    ['type_code' => 'DLX', 'source' => 'booking/images/image_3.jpg', 'stored' => 'hb_rt_dlx_02.jpg', 'original' => 'deluxe-room-2.jpg', 'sort_order' => 1, 'is_cover' => 0],
    ['type_code' => 'SUP', 'source' => 'booking/images/services-2.jpg', 'stored' => 'hb_rt_sup_01.jpg', 'original' => 'superior-room-1.jpg', 'sort_order' => 0, 'is_cover' => 1],
    ['type_code' => 'STD', 'source' => 'booking/images/room-5.jpg', 'stored' => 'hb_rt_std_01.jpg', 'original' => 'standard-room-1.jpg', 'sort_order' => 0, 'is_cover' => 1],
    ['type_code' => 'STD', 'source' => 'booking/images/image_2.jpg', 'stored' => 'hb_rt_std_02.jpg', 'original' => 'standard-room-2.jpg', 'sort_order' => 1, 'is_cover' => 0],
];

if (!function_exists('itm_ensure_upload_directory')) {
    require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
}

/**
 * @param array<int, string> $errors
 */
function itm_seed_hotel_booking_upsert_parent_photo($conn, $companyId, $photoTable, $parentColumn, $parentId, array $sample, array &$errors) {
    $stored = (string) $sample['stored'];
    $original = (string) $sample['original'];
    $sortOrder = (int) $sample['sort_order'];
    $isCover = (int) $sample['is_cover'];
    $companyId = (int) $companyId;
    $parentId = (int) $parentId;

    $findSql = 'SELECT id FROM `' . str_replace('`', '``', $photoTable) . '` WHERE company_id = ? AND `' . str_replace('`', '``', $parentColumn) . '` = ? AND stored_filename = ? LIMIT 1';
    $find = mysqli_prepare($conn, $findSql);
    if (!$find) {
        $errors[] = 'prepare find: ' . mysqli_error($conn);
        return;
    }
    mysqli_stmt_bind_param($find, 'iis', $companyId, $parentId, $stored);
    mysqli_stmt_execute($find);
    $res = mysqli_stmt_get_result($find);
    $existing = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($find);

    if ($existing) {
        $photoId = (int) $existing['id'];
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE `' . str_replace('`', '``', $photoTable) . '` SET original_filename = ?, sort_order = ?, is_cover = ?, active = 1, deleted_at = NULL, deleted_by = NULL WHERE id = ? AND company_id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'siiii', $original, $sortOrder, $isCover, $photoId, $companyId);
    } else {
        $insertSql = 'INSERT INTO `' . str_replace('`', '``', $photoTable) . '` (company_id, `' . str_replace('`', '``', $parentColumn) . '`, stored_filename, original_filename, sort_order, is_cover, active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())';
        $stmt = mysqli_prepare($conn, $insertSql);
        mysqli_stmt_bind_param($stmt, 'iissii', $companyId, $parentId, $stored, $original, $sortOrder, $isCover);
    }
    if (!$stmt) {
        $errors[] = 'prepare upsert: ' . mysqli_error($conn);
        return;
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function itm_seed_hotel_booking_room_type_id_by_code($conn, $companyId, $typeCode) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id FROM booking_rooms_types WHERE company_id = ? AND code = ? AND deleted_at IS NULL LIMIT 1'
    );
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'is', $companyId, $typeCode);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int) ($row['id'] ?? 0);
}

echo ($apply ? 'APPLY' : 'DRY-RUN') . " hotel booking sample photos (company {$companyId}, hotel {$hotelId})\n";

$relDir = itm_hotel_booking_photo_storage_dir($hotelId, 'hotel_photos');
$absDir = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relDir);

foreach ($hotelSamples as $sample) {
    $sourceAbs = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $sample['source']);
    $portalAbs = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $sample['portal']);
    $targetAbs = $absDir . DIRECTORY_SEPARATOR . $sample['stored'];

    if (!is_file($sourceAbs)) {
        echo "[FAIL] missing source {$sample['source']}\n";
        continue;
    }
    if (@getimagesize($sourceAbs) === false) {
        echo "[FAIL] invalid image {$sample['source']}\n";
        continue;
    }

    echo "[OK] hotel {$sample['stored']} <= {$sample['source']}\n";
    if ($apply) {
        if (!is_dir(dirname($portalAbs))) {
            mkdir(dirname($portalAbs), 0775, true);
        }
        copy($sourceAbs, $portalAbs);
        if (!itm_ensure_upload_directory($absDir, 'upload')) {
            echo "[FAIL] could not ensure storage dir {$relDir}\n";
            exit(1);
        }
        copy($sourceAbs, $targetAbs);
        $upsertErrors = [];
        itm_seed_hotel_booking_upsert_parent_photo($conn, $companyId, 'hotel_booking_hotel_photos', 'hotel_id', $hotelId, $sample, $upsertErrors);
        if (!empty($upsertErrors)) {
            echo '[FAIL] ' . implode(' ', $upsertErrors) . "\n";
            exit(1);
        }
    }
}

echo ($apply ? 'APPLY' : 'DRY-RUN') . " room type sample photos (company {$companyId})\n";

foreach ($roomTypeSamples as $sample) {
    $typeCode = (string) $sample['type_code'];
    $typeId = itm_seed_hotel_booking_room_type_id_by_code($conn, $companyId, $typeCode);
    if ($typeId <= 0) {
        echo "[SKIP] room type {$typeCode} not found for company {$companyId}\n";
        continue;
    }

    $hotelIds = itm_hotel_booking_company_hotel_ids($conn, $companyId);
    if (empty($hotelIds)) {
        $hotelIds = [$hotelId];
    }

    $sourceAbs = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $sample['source']);
    if (!is_file($sourceAbs)) {
        echo "[FAIL] missing source {$sample['source']}\n";
        continue;
    }
    if (@getimagesize($sourceAbs) === false) {
        echo "[FAIL] invalid image {$sample['source']}\n";
        continue;
    }

    echo "[OK] room_type {$typeCode} ({$typeId}) {$sample['stored']} <= {$sample['source']}\n";
    if ($apply) {
        foreach ($hotelIds as $seedHotelId) {
            $relTypeDir = itm_hotel_booking_photo_storage_dir((int) $seedHotelId, 'room_types_photos');
            $absTypeDir = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relTypeDir);
            if (!itm_ensure_upload_directory($absTypeDir, 'upload')) {
                echo "[FAIL] could not ensure storage dir {$relTypeDir}\n";
                exit(1);
            }
            copy($sourceAbs, $absTypeDir . DIRECTORY_SEPARATOR . $sample['stored']);
        }
        $upsertErrors = [];
        itm_seed_hotel_booking_upsert_parent_photo($conn, $companyId, 'booking_rooms_type_photos', 'room_type_id', $typeId, $sample, $upsertErrors);
        if (!empty($upsertErrors)) {
            echo '[FAIL] ' . implode(' ', $upsertErrors) . "\n";
            exit(1);
        }
    }
}

if (!$apply) {
    echo "Re-run with --apply to write files and upsert DB rows.\n";
}

exit(0);
