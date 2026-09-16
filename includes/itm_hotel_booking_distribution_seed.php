<?php
/**
 * Sample distribution channels + FK mappings for hospitality admin and API demos.
 */

if (!function_exists('itm_hotel_booking_distribution_demo_api_key')) {
  /**
   * Deterministic demo API key (matches db/02_data.sql seeds). Slot 1=itm_demo, 2=booking_com, 3=opentravel.
   */
  function itm_hotel_booking_distribution_demo_api_key($companyId, $slot = 1) {
    $companyId = (int) $companyId;
    $slot = max(1, min(3, (int) $slot));
    if ($companyId < 1) {
      return '';
    }
    return sprintf('itm_hbd_seed_demo_c%02d%02d', $companyId, $slot);
  }
}

if (!function_exists('itm_hotel_booking_distribution_count_channels')) {
  function itm_hotel_booking_distribution_count_channels($conn, $companyId) {
    $companyId = (int) $companyId;
    $stmt = mysqli_prepare(
      $conn,
      'SELECT COUNT(*) AS cnt FROM hotel_booking_distribution_channels WHERE company_id = ? AND deleted_at IS NULL'
    );
    if (!$stmt) {
      return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $companyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
    return (int) ($row['cnt'] ?? 0);
  }
}

if (!function_exists('itm_hotel_booking_distribution_ensure_tenant_hotel_stack')) {
  /**
   * Ensure at least one hotel, rooms, and portal rate plans exist for distribution FK mappings.
   *
   * @return array{hotel_id:int,created_hotel:bool,created_rooms:int,created_rate_plans:int}
   */
  function itm_hotel_booking_distribution_ensure_tenant_hotel_stack($conn, $companyId) {
    $companyId = (int) $companyId;
    $result = ['hotel_id' => 0, 'created_hotel' => false, 'created_rooms' => 0, 'created_rate_plans' => 0];
    if ($companyId < 1) {
      return $result;
    }

    $hstmt = mysqli_prepare(
      $conn,
      'SELECT id FROM hotel_booking_hotels WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY id ASC LIMIT 1'
    );
    $hotelId = 0;
    if ($hstmt) {
      mysqli_stmt_bind_param($hstmt, 'i', $companyId);
      mysqli_stmt_execute($hstmt);
      $hres = mysqli_stmt_get_result($hstmt);
      $hrow = $hres ? mysqli_fetch_assoc($hres) : null;
      mysqli_stmt_close($hstmt);
      $hotelId = (int) ($hrow['id'] ?? 0);
    }

    if ($hotelId < 1) {
      $ins = mysqli_prepare(
        $conn,
        'INSERT INTO hotel_booking_hotels (company_id, name, description, location, phone, website_url, reviews_url, check_in_time, check_out_time, currency_code, parking_info, pets_policy, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
      );
      if ($ins) {
        $name = 'TechCorp Retreat';
        $description = 'A comfortable property for business and leisure.';
        $location = 'Lisbon, Portugal';
        $phone = '+351 210 000 001';
        $website = 'https://example.com/techcorp-retreat';
        $reviews = 'https://example.com/reviews';
        $checkIn = '15:00:00';
        $checkOut = '12:00:00';
        $currency = 'EUR';
        $parking = 'Self and valet parking complimentary.';
        $pets = 'Pets allowed under 15kg with fee.';
        mysqli_stmt_bind_param(
          $ins,
          'isssssssssss',
          $companyId,
          $name,
          $description,
          $location,
          $phone,
          $website,
          $reviews,
          $checkIn,
          $checkOut,
          $currency,
          $parking,
          $pets
        );
        if (mysqli_stmt_execute($ins)) {
          $hotelId = (int) mysqli_insert_id($conn);
          $result['created_hotel'] = true;
        }
        mysqli_stmt_close($ins);
      }
    }

    $result['hotel_id'] = $hotelId;
    if ($hotelId < 1) {
      return $result;
    }

    $roomSeeds = [
      ['DLX', '201', 'Deluxe King 201', '2', 120.00, 2, 1, 32.00, 'Garden'],
      ['SUP', '202', 'Superior Twin 202', '2', 95.00, 2, 2, 28.00, 'City'],
      ['STD', '101', 'Standard Queen 101', '1', 75.00, 2, 1, 24.00, 'City'],
      ['POOL', '301', 'Grand Deluxe Pool 301', '3', 145.00, 2, 1, 38.00, 'Pool'],
    ];
    foreach ($roomSeeds as $seed) {
      $cstmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS cnt FROM hotel_booking_rooms WHERE company_id = ? AND hotel_id = ? AND room_number = ? AND deleted_at IS NULL'
      );
      $exists = 0;
      if ($cstmt) {
        mysqli_stmt_bind_param($cstmt, 'iis', $companyId, $hotelId, $seed[1]);
        mysqli_stmt_execute($cstmt);
        $cres = mysqli_stmt_get_result($cstmt);
        $crow = $cres ? mysqli_fetch_assoc($cres) : null;
        mysqli_stmt_close($cstmt);
        $exists = (int) ($crow['cnt'] ?? 0);
      }
      if ($exists > 0) {
        continue;
      }
      $typeStmt = mysqli_prepare(
        $conn,
        'SELECT id FROM booking_rooms_types WHERE company_id = ? AND code = ? AND deleted_at IS NULL AND active = 1 LIMIT 1'
      );
      $typeId = 0;
      if ($typeStmt) {
        mysqli_stmt_bind_param($typeStmt, 'is', $companyId, $seed[0]);
        mysqli_stmt_execute($typeStmt);
        $tres = mysqli_stmt_get_result($typeStmt);
        $trow = $tres ? mysqli_fetch_assoc($tres) : null;
        mysqli_stmt_close($typeStmt);
        $typeId = (int) ($trow['id'] ?? 0);
      }
      if ($typeId < 1) {
        continue;
      }
      $hskStmt = mysqli_prepare(
        $conn,
        'SELECT id FROM hotel_booking_housekeeping_statuses WHERE company_id = ? AND code = \'INSP\' AND deleted_at IS NULL LIMIT 1'
      );
      $hskId = null;
      if ($hskStmt) {
        mysqli_stmt_bind_param($hskStmt, 'i', $companyId);
        mysqli_stmt_execute($hskStmt);
        $hkres = mysqli_stmt_get_result($hskStmt);
        $hkrow = $hkres ? mysqli_fetch_assoc($hkres) : null;
        mysqli_stmt_close($hskStmt);
        $hskId = isset($hkrow['id']) ? (int) $hkrow['id'] : null;
      }
      $rins = mysqli_prepare(
        $conn,
        'INSERT INTO hotel_booking_rooms (company_id, hotel_id, room_type_id, housekeeping_status_id, room_number, name, floor, price_per_night, num_persons, num_beds, size_sqm, view_label, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
      );
      if ($rins) {
        mysqli_stmt_bind_param(
          $rins,
          'iiiisssdiids',
          $companyId,
          $hotelId,
          $typeId,
          $hskId,
          $seed[1],
          $seed[2],
          $seed[3],
          $seed[4],
          $seed[5],
          $seed[6],
          $seed[7],
          $seed[8]
        );
        if (mysqli_stmt_execute($rins)) {
          $result['created_rooms']++;
        }
        mysqli_stmt_close($rins);
      }
    }

    $ratePlanSeeds = [
      [1, 'Best Available Rate', 'room_only', 'cancellation_policy/1_cancellation_policy.html'],
      [2, 'Breakfast Included', 'breakfast', 'cancellation_policy/2_cancellation_policy.html'],
      [3, 'Flexible Rate', 'flexible', 'cancellation_policy/3_cancellation_policy.html'],
      [4, 'Non-Refundable Rate', 'non_refundable', 'cancellation_policy/4_cancellation_policy.html'],
    ];
    foreach ($ratePlanSeeds as $rp) {
      $rpCheck = mysqli_prepare(
        $conn,
        'SELECT id FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND plan_slot = ? AND deleted_at IS NULL LIMIT 1'
      );
      $hasPlan = false;
      if ($rpCheck) {
        mysqli_stmt_bind_param($rpCheck, 'iii', $companyId, $hotelId, $rp[0]);
        mysqli_stmt_execute($rpCheck);
        $rpres = mysqli_stmt_get_result($rpCheck);
        $hasPlan = (bool) ($rpres && mysqli_fetch_assoc($rpres));
        mysqli_stmt_close($rpCheck);
      }
      if ($hasPlan) {
        continue;
      }
      $rpins = mysqli_prepare(
        $conn,
        'INSERT INTO hotel_booking_portal_rate_plans (company_id, hotel_id, plan_slot, name, rate_plan_slug, cancellation_policy_url, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
      );
      if ($rpins) {
        mysqli_stmt_bind_param($rpins, 'iiisss', $companyId, $hotelId, $rp[0], $rp[1], $rp[2], $rp[3]);
        if (mysqli_stmt_execute($rpins)) {
          $result['created_rate_plans']++;
        }
        mysqli_stmt_close($rpins);
      }
    }

    return $result;
  }
}

if (!function_exists('itm_hotel_booking_distribution_seed_sample_data')) {
  /**
   * Insert demo channels (itm_demo, booking_com, opentravel) with hotel/room-type/rate-plan mappings.
   *
   * @return array{success:bool,channels_created:int,skipped:bool,errors:list<string>,demo_api_keys:list<string>}
   */
  function itm_hotel_booking_distribution_seed_sample_data($conn, $companyId, $employeeId) {
    $companyId = (int) $companyId;
    $employeeId = (int) $employeeId;
    $errors = [];
    $demoKeys = [];

    if ($companyId < 1) {
      return ['success' => false, 'channels_created' => 0, 'skipped' => false, 'errors' => ['invalid_company'], 'demo_api_keys' => []];
    }

    if (itm_hotel_booking_distribution_count_channels($conn, $companyId) > 0) {
      return ['success' => true, 'channels_created' => 0, 'skipped' => true, 'errors' => [], 'demo_api_keys' => []];
    }

    itm_hotel_booking_distribution_ensure_tenant_hotel_stack($conn, $companyId);

    $channelDefs = [
      ['itm_demo', 'ITM Demo Channel', 'itm_native', 1, null, 0],
      ['booking_com', 'Booking.com Sandbox', 'booking_com', 2, 'DEMO-PROP', 1],
      ['opentravel', 'OpenTravel OTA', 'opentravel', 3, null, 0],
    ];

    $created = 0;
    foreach ($channelDefs as $def) {
      [$code, $name, $standard, $slot, $propertyPrefix, $sandbox] = $def;
      $plainKey = itm_hotel_booking_distribution_demo_api_key($companyId, $slot);
      $prefix = itm_hotel_booking_distribution_api_key_prefix($plainKey);
      $hash = itm_hotel_booking_distribution_hash_api_key($plainKey);
      $propertyId = $propertyPrefix !== null ? $propertyPrefix . '-' . $companyId : null;

      $ins = mysqli_prepare(
        $conn,
        'INSERT INTO hotel_booking_distribution_channels (company_id, channel_code, name, standard, api_key_prefix, api_key_hash, partner_property_id, partner_sandbox_mode, hourly_rate_limit, active, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1000, 1, NULLIF(?, 0), NOW())'
      );
      if (!$ins) {
        $errors[] = 'channel_prepare_' . $code;
        continue;
      }
      mysqli_stmt_bind_param($ins, 'issssssii', $companyId, $code, $name, $standard, $prefix, $hash, $propertyId, $sandbox, $employeeId);
      if (!mysqli_stmt_execute($ins)) {
        $errors[] = 'channel_insert_' . $code;
        mysqli_stmt_close($ins);
        continue;
      }
      $channelId = (int) mysqli_insert_id($conn);
      mysqli_stmt_close($ins);
      if ($channelId < 1) {
        $errors[] = 'channel_id_' . $code;
        continue;
      }

      $demoKeys[] = $plainKey;
      $created++;

      if (function_exists('itm_hotel_booking_distribution_sync_hotel_mappings')) {
        itm_hotel_booking_distribution_sync_hotel_mappings($conn, $companyId, $channelId, $employeeId, false);
      }
      if (function_exists('itm_hotel_booking_distribution_sync_room_type_mappings')) {
        itm_hotel_booking_distribution_sync_room_type_mappings($conn, $companyId, $channelId, $employeeId, false);
      }

      if ($standard === 'booking_com') {
        $hotelId = 0;
        $hstmt = mysqli_prepare(
          $conn,
          'SELECT id FROM hotel_booking_hotels WHERE company_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY id ASC LIMIT 1'
        );
        if ($hstmt) {
          mysqli_stmt_bind_param($hstmt, 'i', $companyId);
          mysqli_stmt_execute($hstmt);
          $hres = mysqli_stmt_get_result($hstmt);
          $hrow = $hres ? mysqli_fetch_assoc($hres) : null;
          mysqli_stmt_close($hstmt);
          $hotelId = (int) ($hrow['id'] ?? 0);
        }
        if ($hotelId > 0) {
          $rpstmt = mysqli_prepare(
            $conn,
            'SELECT id, plan_slot FROM hotel_booking_portal_rate_plans WHERE company_id = ? AND hotel_id = ? AND deleted_at IS NULL AND active = 1 ORDER BY plan_slot ASC'
          );
          if ($rpstmt) {
            mysqli_stmt_bind_param($rpstmt, 'ii', $companyId, $hotelId);
            mysqli_stmt_execute($rpstmt);
            $rpres = mysqli_stmt_get_result($rpstmt);
            while ($rpres && ($rprow = mysqli_fetch_assoc($rpres))) {
              $planId = (int) ($rprow['id'] ?? 0);
              $planSlot = (int) ($rprow['plan_slot'] ?? 0);
              if ($planId < 1) {
                continue;
              }
              $externalCode = 'RP' . $planSlot;
              $rpins = mysqli_prepare(
                $conn,
                'INSERT INTO hotel_booking_distribution_rate_plan_mappings (company_id, channel_id, hotel_id, portal_rate_plan_id, external_rate_plan_code, min_los, max_los, price_multiplier, active, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, NULL, 1.00, 1, NULLIF(?, 0), NOW())
                 ON DUPLICATE KEY UPDATE external_rate_plan_code = VALUES(external_rate_plan_code), active = 1, updated_by = VALUES(created_by), updated_at = NOW()'
              );
              if ($rpins) {
                mysqli_stmt_bind_param($rpins, 'iiiisi', $companyId, $channelId, $hotelId, $planId, $externalCode, $employeeId);
                mysqli_stmt_execute($rpins);
                mysqli_stmt_close($rpins);
              }
            }
            mysqli_stmt_close($rpstmt);
          }
        }
      }
    }

    return [
      'success' => empty($errors),
      'channels_created' => $created,
      'skipped' => false,
      'errors' => $errors,
      'demo_api_keys' => $demoKeys,
    ];
  }
}