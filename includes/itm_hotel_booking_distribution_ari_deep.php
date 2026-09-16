<?php
/**
 * Deeper ARI: LOS, restrictions, derived rates, delta checksum.
 */

if (!function_exists('itm_hotel_booking_distribution_load_rate_plan_mappings')) {
    function itm_hotel_booking_distribution_load_rate_plan_mappings($conn, $companyId, $channelId, $hotelId) {
        $rows = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM hotel_booking_distribution_rate_plan_mappings
             WHERE company_id = ? AND channel_id = ? AND hotel_id = ? AND deleted_at IS NULL AND active = 1
             ORDER BY external_rate_plan_code'
        );
        if (!$stmt) {
            return $rows;
        }
        mysqli_stmt_bind_param($stmt, 'iii', $companyId, $channelId, $hotelId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }
}

if (!function_exists('itm_hotel_booking_distribution_load_ari_restrictions_for_range')) {
    function itm_hotel_booking_distribution_load_ari_restrictions_for_range($conn, $companyId, $channelId, $hotelId, $startDate, $endDate) {
        $map = [];
        $stmt = mysqli_prepare(
            $conn,
            'SELECT * FROM hotel_booking_distribution_ari_restrictions
             WHERE company_id = ? AND channel_id = ? AND hotel_id = ? AND deleted_at IS NULL AND active = 1
               AND stay_date >= ? AND stay_date < ?
             ORDER BY stay_date, room_type_id'
        );
        if (!$stmt) {
            return $map;
        }
        mysqli_stmt_bind_param($stmt, 'iiiss', $companyId, $channelId, $hotelId, $startDate, $endDate);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) {
            $typeId = (int) ($row['room_type_id'] ?? 0);
            $date = (string) ($row['stay_date'] ?? '');
            $rpId = (int) ($row['rate_plan_mapping_id'] ?? 0);
            $map[$typeId][$date][$rpId] = $row;
        }
        mysqli_stmt_close($stmt);
        return $map;
    }
}

if (!function_exists('itm_hotel_booking_distribution_apply_day_restrictions')) {
    function itm_hotel_booking_distribution_apply_day_restrictions(array $dayRow, array $ratePlans, array $restrictionsForDay) {
        $dayRow['rate_plans'] = [];
        foreach ($ratePlans as $rp) {
            $rpId = (int) ($rp['id'] ?? 0);
            $restr = $restrictionsForDay[$rpId] ?? $restrictionsForDay[0] ?? null;
            $multiplier = (float) ($rp['price_multiplier'] ?? 1);
            $minLos = (int) ($rp['min_los'] ?? 1);
            $maxLos = $rp['max_los'] !== null ? (int) $rp['max_los'] : null;
            $stopSell = !empty($dayRow['stop_sell']);
            $cta = 0;
            $ctd = 0;
            if (is_array($restr)) {
                $multiplier *= (float) ($restr['derived_price_multiplier'] ?? 1);
                $minLos = max($minLos, (int) ($restr['min_los'] ?? 1));
                if ($restr['max_los'] !== null && $restr['max_los'] !== '') {
                    $maxLos = $maxLos === null ? (int) $restr['max_los'] : min($maxLos, (int) $restr['max_los']);
                }
                $stopSell = $stopSell || !empty($restr['stop_sell']);
                $cta = (int) ($restr['closed_to_arrival'] ?? 0);
                $ctd = (int) ($restr['closed_to_departure'] ?? 0);
                if ($restr['base_price_override'] !== null && $restr['base_price_override'] !== '') {
                    $dayRow['price_per_night'] = round((float) $restr['base_price_override'], 2);
                }
            }
            $derivedPrice = round(((float) ($dayRow['price_per_night'] ?? 0)) * $multiplier, 2);
            $dayRow['rate_plans'][] = [
                'rate_plan_mapping_id' => $rpId,
                'external_rate_plan_code' => (string) ($rp['external_rate_plan_code'] ?? ''),
                'portal_rate_plan_id' => (int) ($rp['portal_rate_plan_id'] ?? 0),
                'min_los' => $minLos,
                'max_los' => $maxLos,
                'derived_price_per_night' => $derivedPrice,
                'stop_sell' => $stopSell,
                'closed_to_arrival' => $cta,
                'closed_to_departure' => $ctd,
            ];
            if ($stopSell) {
                $dayRow['stop_sell'] = true;
            }
            if ($cta) {
                $dayRow['closed_to_arrival'] = 1;
            }
            if ($ctd) {
                $dayRow['closed_to_departure'] = 1;
            }
        }
        if (!empty($dayRow['rate_plans'])) {
            $dayRow['price_per_night'] = min(array_column($dayRow['rate_plans'], 'derived_price_per_night'));
        }
        return $dayRow;
    }
}

if (!function_exists('itm_hotel_booking_distribution_enrich_ari_snapshot')) {
    function itm_hotel_booking_distribution_enrich_ari_snapshot($conn, array $channelRow, array $snapshot) {
        if (empty($snapshot['success'])) {
            return $snapshot;
        }
        $companyId = (int) ($channelRow['company_id'] ?? 0);
        $channelId = (int) ($channelRow['id'] ?? 0);
        $hotelId = (int) ($snapshot['hotel_id'] ?? 0);
        $startDate = (string) ($snapshot['start_date'] ?? '');
        $endDate = (string) ($snapshot['end_date'] ?? '');
        if ($hotelId < 1 || $startDate === '' || $endDate === '') {
            return $snapshot;
        }
        $ratePlans = itm_hotel_booking_distribution_load_rate_plan_mappings($conn, $companyId, $channelId, $hotelId);
        $restrictions = itm_hotel_booking_distribution_load_ari_restrictions_for_range($conn, $companyId, $channelId, $hotelId, $startDate, $endDate);
        foreach ($snapshot['inventory'] as &$inv) {
            $typeId = (int) ($inv['room_type_id'] ?? 0);
            foreach ($inv['days'] as &$day) {
                $date = (string) ($day['date'] ?? '');
                $dayRestr = $restrictions[$typeId][$date] ?? [];
                $day = itm_hotel_booking_distribution_apply_day_restrictions($day, $ratePlans, $dayRestr);
            }
            unset($day);
        }
        unset($inv);
        $snapshot['rate_plan_mappings'] = array_map(static function ($rp) {
            return [
                'external_rate_plan_code' => $rp['external_rate_plan_code'] ?? '',
                'portal_rate_plan_id' => (int) ($rp['portal_rate_plan_id'] ?? 0),
                'min_los' => (int) ($rp['min_los'] ?? 1),
                'max_los' => $rp['max_los'] !== null ? (int) $rp['max_los'] : null,
                'price_multiplier' => (float) ($rp['price_multiplier'] ?? 1),
            ];
        }, $ratePlans);
        return $snapshot;
    }
}

if (!function_exists('itm_hotel_booking_distribution_ari_snapshot_checksum')) {
    function itm_hotel_booking_distribution_ari_snapshot_checksum(array $snapshot) {
        $normalized = $snapshot;
        unset($normalized['_ota_action']);
        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('itm_hotel_booking_distribution_should_skip_delta_push')) {
    function itm_hotel_booking_distribution_should_skip_delta_push(array $channelRow, array $snapshot, $forcePush = false) {
        if ($forcePush) {
            return false;
        }
        $checksum = itm_hotel_booking_distribution_ari_snapshot_checksum($snapshot);
        $previous = (string) ($channelRow['last_ari_push_checksum'] ?? '');
        return $previous !== '' && hash_equals($previous, $checksum);
    }
}

if (!function_exists('itm_hotel_booking_distribution_store_ari_push_checksum')) {
    function itm_hotel_booking_distribution_store_ari_push_checksum($conn, array $channelRow, array $snapshot) {
        $channelId = (int) ($channelRow['id'] ?? 0);
        $companyId = (int) ($channelRow['company_id'] ?? 0);
        $checksum = itm_hotel_booking_distribution_ari_snapshot_checksum($snapshot);
        $stmt = mysqli_prepare(
            $conn,
            'UPDATE hotel_booking_distribution_channels SET last_ari_push_checksum = ?, updated_at = NOW() WHERE id = ? AND company_id = ?'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sii', $checksum, $channelId, $companyId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        return $checksum;
    }
}
