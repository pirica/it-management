<?php
/**
 * Copy sample hotel and room-type photos onto disk and upsert photo rows for seed companies 1–5.
 *
 * CLI: php scripts/seed_hotel_booking_sample_photos.php [--apply]
 * Browser: scripts/seed_hotel_booking_sample_photos.php?run=1 (dry-run) or ?run=1&apply=1 (Admin)
 */

declare(strict_types=1);

/**
 * Browser catalog: How to use (shown on landing before run=1).
 */
function itm_script_browser_how_to_use(): string
{
    return <<<'ITM_SCRIPT_BROWSER_HOW_TO_USE'
Copies demo hotel and room-type JPEGs into <code>booking/images/{hotel_id}/hotel_photos/</code> and <code>room_types_photos/</code>, upserts <code>hotel_booking_hotel_photos</code> / <code>booking_rooms_type_photos</code> rows for <strong>companies 1–5</strong> (each tenant’s primary hotel + room types DLX/SUP/STD). Shared portal assets under <code>booking/images/hotel-sample-*.jpg</code> are written once.
<br><br>
CLI dry-run: <code>php scripts/seed_hotel_booking_sample_photos.php</code><br>
CLI apply: <code>php scripts/seed_hotel_booking_sample_photos.php --apply</code><br>
Browser apply (Admin): <a href="seed_hotel_booking_sample_photos.php?run=1&amp;apply=1">seed_hotel_booking_sample_photos.php?run=1&amp;apply=1</a>
ITM_SCRIPT_BROWSER_HOW_TO_USE;
}

require_once __DIR__ . '/lib/itm_apply_script_bootstrap.php';

$boot = itm_apply_script_bootstrap('Seed hotel booking sample photos', ['skip_db_tests' => false]);
$apply = $boot['apply'];
$nl = $boot['nl'];
$conn = $boot['conn'];

if (!$conn instanceof mysqli) {
    echo colorText('[FAIL] No database connection.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

require_once dirname(__DIR__) . '/includes/itm_hotel_booking.php';
if (!function_exists('itm_ensure_upload_directory')) {
    require_once ROOT_PATH . 'includes/bootstrap_helpers.php';
}

$hotelSamples = [
    ['source' => 'booking/images/image_2.jpg', 'portal' => 'booking/images/hotel-sample-exterior.jpg', 'stored' => 'hb_seed_01.jpg', 'original' => 'hotel-sample-exterior.jpg', 'sort_order' => 0, 'is_cover' => 1],
    ['source' => 'booking/images/services-2.jpg', 'portal' => 'booking/images/hotel-sample-lobby.jpg', 'stored' => 'hb_seed_02.jpg', 'original' => 'hotel-sample-lobby.jpg', 'sort_order' => 1, 'is_cover' => 0],
    ['source' => 'booking/images/room-5.jpg', 'portal' => 'booking/images/hotel-sample-room.jpg', 'stored' => 'hb_seed_03.jpg', 'original' => 'hotel-sample-room.jpg', 'sort_order' => 2, 'is_cover' => 0],
    ['source' => 'booking/images/image_3.jpg', 'portal' => 'booking/images/hotel-sample-pool.jpg', 'stored' => 'hb_seed_04.jpg', 'original' => 'hotel-sample-pool.jpg', 'sort_order' => 3, 'is_cover' => 0],
];

$roomTypeSamples = [
    ['type_code' => 'DLX', 'source' => 'booking/images/sample-room.jpg', 'stored' => 'hb_rt_dlx_01.jpg', 'original' => 'deluxe-room-1.jpg', 'sort_order' => 0, 'is_cover' => 1],
    ['type_code' => 'DLX', 'source' => 'booking/images/sample-room.jpg', 'stored' => 'hb_rt_dlx_02.jpg', 'original' => 'deluxe-room-2.jpg', 'sort_order' => 1, 'is_cover' => 0],
    ['type_code' => 'SUP', 'source' => 'booking/images/sample-room.jpg', 'stored' => 'hb_rt_sup_01.jpg', 'original' => 'superior-room-1.jpg', 'sort_order' => 0, 'is_cover' => 1],
    ['type_code' => 'STD', 'source' => 'booking/images/sample-room.jpg', 'stored' => 'hb_rt_std_01.jpg', 'original' => 'standard-room-1.jpg', 'sort_order' => 0, 'is_cover' => 1],
    ['type_code' => 'STD', 'source' => 'booking/images/sample-room.jpg', 'stored' => 'hb_rt_std_02.jpg', 'original' => 'standard-room-2.jpg', 'sort_order' => 1, 'is_cover' => 0],
];

/**
 * Seed companies 1–5 (canonical multi-tenant hospitality demo tenants).
 *
 * @return array<int, int>
 */
function itm_seed_hotel_booking_target_company_ids($conn)
{
    $ids = [];
    $stmt = mysqli_prepare($conn, 'SELECT id FROM companies WHERE id BETWEEN 1 AND 5 ORDER BY id ASC');
    if (!$stmt) {
        return $ids;
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($res && ($row = mysqli_fetch_assoc($res))) {
        $ids[] = (int) ($row['id'] ?? 0);
    }
    mysqli_stmt_close($stmt);
    return array_values(array_filter($ids, static function ($id) {
        return $id > 0;
    }));
}

/**
 * Resolve a readable sample image on disk (legacy booking/assets first, then canonical sample-room.jpg).
 */
function itm_seed_hotel_booking_sample_source_abs(array $sample)
{
    $candidates = [];
    if (!empty($sample['source'])) {
        $candidates[] = (string) $sample['source'];
    }
    if (!empty($sample['portal'])) {
        $candidates[] = (string) $sample['portal'];
    }
    $candidates[] = 'booking/images/sample-room.jpg';
    foreach ($candidates as $rel) {
        $abs = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs) && @getimagesize($abs) !== false) {
            return $abs;
        }
    }
    return '';
}

/**
 * @param array<int, string> $errors
 */
function itm_seed_hotel_booking_upsert_parent_photo($conn, $companyId, $photoTable, $parentColumn, $parentId, array $sample, array &$errors)
{
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

function itm_seed_hotel_booking_room_type_id_by_code($conn, $companyId, $typeCode)
{
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

$companyIds = itm_seed_hotel_booking_target_company_ids($conn);
if ($companyIds === []) {
    echo colorText('[FAIL] No seed companies (ids 1–5) found.', 'fail') . $nl;
    itm_script_output_end();
    exit(1);
}

echo colorText('Target companies: ' . implode(', ', $companyIds), 'info') . $nl . $nl;

$failCount = 0;
$okCount = 0;
$portalAssetsCopied = [];

foreach ($companyIds as $companyId) {
    $hotelId = itm_hotel_booking_photo_default_hotel_id($conn, (int) $companyId);
    if ($hotelId < 1) {
        echo colorText("[SKIP] company {$companyId}: no hotel_booking_hotels row", 'warn') . $nl;
        continue;
    }

    echo colorText("Company {$companyId} (hotel {$hotelId})", 'info') . $nl;

    $relDir = itm_hotel_booking_photo_storage_dir($hotelId, 'hotel_photos');
    $absDir = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relDir);

    foreach ($hotelSamples as $sample) {
        $sourceAbs = itm_seed_hotel_booking_sample_source_abs($sample);
        $portalRel = (string) $sample['portal'];
        $portalAbs = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $portalRel);
        $targetAbs = $absDir . DIRECTORY_SEPARATOR . $sample['stored'];

        if ($sourceAbs === '') {
            echo colorText("[FAIL] company {$companyId} missing source for {$sample['stored']}", 'fail') . $nl;
            $failCount++;
            continue;
        }

        $relSource = str_replace('\\', '/', str_replace(ROOT_PATH, '', $sourceAbs));
        echo colorText("[OK] hotel {$sample['stored']} <= {$relSource}", 'pass') . $nl;
        $okCount++;

        if ($apply) {
            if (!isset($portalAssetsCopied[$portalRel])) {
                if (!is_dir(dirname($portalAbs))) {
                    mkdir(dirname($portalAbs), 0775, true);
                }
                copy($sourceAbs, $portalAbs);
                $portalAssetsCopied[$portalRel] = true;
            }
            if (!itm_ensure_upload_directory($absDir, 'upload')) {
                echo colorText("[FAIL] could not ensure storage dir {$relDir}", 'fail') . $nl;
                itm_script_output_end();
                exit(1);
            }
            copy($sourceAbs, $targetAbs);
            $upsertErrors = [];
            itm_seed_hotel_booking_upsert_parent_photo(
                $conn,
                (int) $companyId,
                'hotel_booking_hotel_photos',
                'hotel_id',
                (int) $hotelId,
                $sample,
                $upsertErrors
            );
            if ($upsertErrors !== []) {
                echo colorText('[FAIL] ' . implode(' ', $upsertErrors), 'fail') . $nl;
                itm_script_output_end();
                exit(1);
            }
        }
    }

    $hotelIds = itm_hotel_booking_company_hotel_ids($conn, (int) $companyId);
    if ($hotelIds === []) {
        $hotelIds = [$hotelId];
    }

    foreach ($roomTypeSamples as $sample) {
        $typeCode = (string) $sample['type_code'];
        $typeId = itm_seed_hotel_booking_room_type_id_by_code($conn, (int) $companyId, $typeCode);
        if ($typeId <= 0) {
            echo colorText("[SKIP] company {$companyId} room type {$typeCode} not found", 'warn') . $nl;
            continue;
        }

        $sourceAbs = itm_seed_hotel_booking_sample_source_abs($sample);
        if ($sourceAbs === '') {
            echo colorText("[FAIL] company {$companyId} missing source for {$sample['stored']}", 'fail') . $nl;
            $failCount++;
            continue;
        }

        $relSource = str_replace('\\', '/', str_replace(ROOT_PATH, '', $sourceAbs));
        echo colorText("[OK] room_type {$typeCode} ({$typeId}) {$sample['stored']} <= {$relSource}", 'pass') . $nl;
        $okCount++;

        if ($apply) {
            foreach ($hotelIds as $seedHotelId) {
                $relTypeDir = itm_hotel_booking_photo_storage_dir((int) $seedHotelId, 'room_types_photos');
                $absTypeDir = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relTypeDir);
                if (!itm_ensure_upload_directory($absTypeDir, 'upload')) {
                    echo colorText("[FAIL] could not ensure storage dir {$relTypeDir}", 'fail') . $nl;
                    itm_script_output_end();
                    exit(1);
                }
                copy($sourceAbs, $absTypeDir . DIRECTORY_SEPARATOR . $sample['stored']);
            }
            $upsertErrors = [];
            itm_seed_hotel_booking_upsert_parent_photo(
                $conn,
                (int) $companyId,
                'booking_rooms_type_photos',
                'room_type_id',
                (int) $typeId,
                $sample,
                $upsertErrors
            );
            if ($upsertErrors !== []) {
                echo colorText('[FAIL] ' . implode(' ', $upsertErrors), 'fail') . $nl;
                itm_script_output_end();
                exit(1);
            }
        }
    }

    echo $nl;
}

echo colorText("Summary: {$okCount} OK, {$failCount} FAIL", $failCount > 0 ? 'fail' : 'pass') . $nl;
itm_apply_script_finish_hint($apply, $boot['is_cli'], $okCount, $nl, 'seed_hotel_booking_sample_photos.php');
itm_script_output_end();
exit($failCount > 0 ? 1 : 0);
