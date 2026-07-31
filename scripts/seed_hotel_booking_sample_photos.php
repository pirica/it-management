<?php
/**
 * Copy TechCorp Retreat sample hotel photos onto disk and upsert hotel_booking_hotel_photos rows.
 *
 * Usage: php scripts/seed_hotel_booking_sample_photos.php [--apply]
 */
define('ITM_CLI_SCRIPT', true);
require dirname(__DIR__) . '/config/config.php';
require ROOT_PATH . 'includes/itm_hotel_booking.php';

$apply = in_array('--apply', $argv ?? [], true);
$companyId = 1;
$hotelId = 1;

$samples = [
    ['source' => 'booking/images/image_2.jpg', 'portal' => 'booking/images/hotel-sample-exterior.jpg', 'stored' => 'hb_seed_01.jpg', 'original' => 'hotel-sample-exterior.jpg', 'sort_order' => 0, 'is_cover' => 1],
    ['source' => 'booking/images/services-2.jpg', 'portal' => 'booking/images/hotel-sample-lobby.jpg', 'stored' => 'hb_seed_02.jpg', 'original' => 'hotel-sample-lobby.jpg', 'sort_order' => 1, 'is_cover' => 0],
    ['source' => 'booking/images/room-5.jpg', 'portal' => 'booking/images/hotel-sample-room.jpg', 'stored' => 'hb_seed_03.jpg', 'original' => 'hotel-sample-room.jpg', 'sort_order' => 2, 'is_cover' => 0],
    ['source' => 'booking/images/image_3.jpg', 'portal' => 'booking/images/hotel-sample-pool.jpg', 'stored' => 'hb_seed_04.jpg', 'original' => 'hotel-sample-pool.jpg', 'sort_order' => 3, 'is_cover' => 0],
];

$relDir = itm_hotel_booking_photo_storage_dir($companyId, 'hotel', $hotelId);
$absDir = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
if (!function_exists('itm_ensure_upload_directory')) {
    require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
}

echo ($apply ? 'APPLY' : 'DRY-RUN') . " hotel booking sample photos (company {$companyId}, hotel {$hotelId})\n";

foreach ($samples as $sample) {
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

    echo "[OK] {$sample['stored']} <= {$sample['source']}\n";
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

        $stored = $sample['stored'];
        $original = $sample['original'];
        $sortOrder = (int) $sample['sort_order'];
        $isCover = (int) $sample['is_cover'];

        $find = mysqli_prepare(
            $conn,
            'SELECT id FROM hotel_booking_hotel_photos WHERE company_id = ? AND hotel_id = ? AND stored_filename = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($find, 'iis', $companyId, $hotelId, $stored);
        mysqli_stmt_execute($find);
        $res = mysqli_stmt_get_result($find);
        $existing = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($find);

        if ($existing) {
            $photoId = (int) $existing['id'];
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE hotel_booking_hotel_photos SET original_filename = ?, sort_order = ?, is_cover = ?, active = 1, deleted_at = NULL, deleted_by = NULL WHERE id = ? AND company_id = ?'
            );
            mysqli_stmt_bind_param($stmt, 'siiii', $original, $sortOrder, $isCover, $photoId, $companyId);
        } else {
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO hotel_booking_hotel_photos (company_id, hotel_id, stored_filename, original_filename, sort_order, is_cover, active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
            );
            mysqli_stmt_bind_param($stmt, 'iissii', $companyId, $hotelId, $stored, $original, $sortOrder, $isCover);
        }
        if (!$stmt) {
            echo "[FAIL] prepare upsert: " . mysqli_error($conn) . "\n";
            exit(1);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

if (!$apply) {
    echo "Re-run with --apply to write files and upsert DB rows.\n";
}

exit(0);
