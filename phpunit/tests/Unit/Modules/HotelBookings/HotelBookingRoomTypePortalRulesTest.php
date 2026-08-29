<?php

use PHPUnit\Framework\TestCase;

final class HotelBookingRoomTypePortalRulesTest extends TestCase
{
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 5);
    }

    private static function pricingDefaults(): array
    {
        return itm_hotel_booking_portal_pricing_defaults();
    }

    private static function baseTypeRow(array $overrides = []): array
    {
        return array_merge([
            'max_adults' => 2,
            'max_children' => 2,
            'max_babies' => 1,
            'max_total_guests' => 4,
            'min_adults' => 1,
            'included_adults_per_room' => 2,
            'child_max_age' => 12,
            'adults_only' => 0,
            'portal_bookable' => 1,
            'portal_included_children_free' => 0,
            'portal_extra_adult_supplement_percent' => null,
            'portal_child_nightly_supplement' => null,
            'portal_baby_nightly_supplement' => null,
            'portal_single_occupancy_discount_percent' => null,
            'min_stay_nights' => 1,
            'max_stay_nights' => null,
            'min_advance_booking_days' => 0,
            'max_advance_booking_days' => null,
            'closed_to_arrival_days' => '',
            'closed_to_departure_days' => '',
            'pets_allowed' => 1,
        ], $overrides);
    }

    protected function setUp(): void
    {
        require_once self::repoRoot() . '/config/config.php';
    }

    public function testMaxTotalGuestsRejectsOverflow(): void
    {
        $typeRow = self::baseTypeRow(['max_total_guests' => 4]);
        $occ = ['rooms' => 1, 'adults' => 2, 'children' => 2, 'babies' => 1];
        $this->assertFalse(itm_hotel_booking_room_type_fits_occupancy($typeRow, $occ));
    }

    public function testMinAdultsPerRoomSlice(): void
    {
        $typeRow = self::baseTypeRow(['min_adults' => 2]);
        $occ = ['rooms' => 1, 'adults' => 1, 'children' => 0, 'babies' => 0];
        $this->assertFalse(itm_hotel_booking_room_type_fits_occupancy($typeRow, $occ));
    }

    public function testAdultsOnlyRejectsChildrenAndBabies(): void
    {
        $typeRow = self::baseTypeRow(['adults_only' => 1]);
        $this->assertFalse(itm_hotel_booking_room_type_fits_occupancy($typeRow, ['rooms' => 1, 'adults' => 2, 'children' => 1, 'babies' => 0]));
        $this->assertFalse(itm_hotel_booking_room_type_fits_occupancy($typeRow, ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 1]));
    }

    public function testIncludedAdultsPerRoomAffectsQuote(): void
    {
        $pricing = self::pricingDefaults();
        $occ = ['rooms' => 1, 'adults' => 3, 'children' => 0, 'babies' => 0];
        $includedThree = self::baseTypeRow(['included_adults_per_room' => 3]);
        $includedTwo = self::baseTypeRow(['included_adults_per_room' => 2]);
        $quoteThree = itm_hotel_booking_portal_quote_nightly(100.0, $occ, 0, $pricing, 0, $includedThree);
        $quoteTwo = itm_hotel_booking_portal_quote_nightly(100.0, $occ, 0, $pricing, 0, $includedTwo);
        $this->assertEqualsWithDelta(100.0, $quoteThree, 0.01);
        $this->assertEqualsWithDelta(135.0, $quoteTwo, 0.01);
        $this->assertTrue($quoteThree < $quoteTwo);
    }

    public function testPricingOverridesAndFirstChildFree(): void
    {
        $pricing = self::pricingDefaults();
        $typeRow = self::baseTypeRow([
            'portal_child_nightly_supplement' => 30.0,
            'portal_baby_nightly_supplement' => 10.0,
            'portal_included_children_free' => 1,
            'portal_single_occupancy_discount_percent' => 10.0,
        ]);
        $rules = itm_hotel_booking_portal_room_type_effective_rules($typeRow, $pricing);
        $this->assertSame(30.0, $rules['child_nightly_supplement']);
        $this->assertTrue($rules['included_children_free']);

        $familyOcc = ['rooms' => 1, 'adults' => 2, 'children' => 2, 'babies' => 1];
        $withChild = itm_hotel_booking_portal_quote_nightly(100.0, $familyOcc, 0, $pricing, 0, $typeRow, $rules);
        $withoutFreeChild = itm_hotel_booking_portal_quote_nightly(100.0, $familyOcc, 0, $pricing, 0, self::baseTypeRow([
            'portal_child_nightly_supplement' => 30.0,
            'portal_baby_nightly_supplement' => 10.0,
            'portal_included_children_free' => 0,
        ]));
        $this->assertLessThan($withoutFreeChild, $withChild);

        $soloOcc = ['rooms' => 1, 'adults' => 1, 'children' => 0, 'babies' => 0];
        $solo = itm_hotel_booking_portal_quote_nightly(100.0, $soloOcc, 0, $pricing, 0, $typeRow, $rules);
        $this->assertEqualsWithDelta(90.0, $solo, 0.01);
    }

    public function testStayValidationMinMaxNightsAndAdvance(): void
    {
        $typeRow = self::baseTypeRow([
            'min_stay_nights' => 3,
            'max_stay_nights' => 5,
            'min_advance_booking_days' => 7,
            'max_advance_booking_days' => 30,
        ]);
        $today = '2026-08-01';
        $this->assertFalse(itm_hotel_booking_portal_room_type_validate_stay($typeRow, '2026-08-02', '2026-08-04', $today)['ok']);
        $this->assertTrue(itm_hotel_booking_portal_room_type_validate_stay($typeRow, '2026-08-10', '2026-08-13', $today)['ok']);
        $this->assertFalse(itm_hotel_booking_portal_room_type_validate_stay($typeRow, '2026-08-10', '2026-08-16', $today)['ok']);
        $this->assertFalse(itm_hotel_booking_portal_room_type_validate_stay($typeRow, '2026-08-05', '2026-08-08', $today)['ok']);
        $this->assertFalse(itm_hotel_booking_portal_room_type_validate_stay($typeRow, '2026-09-05', '2026-09-08', $today)['ok']);
    }

    public function testClosedToArrivalAndDepartureWeekdays(): void
    {
        $typeRow = self::baseTypeRow([
            'closed_to_arrival_days' => '5,6',
            'closed_to_departure_days' => '0',
        ]);
        $this->assertSame([5, 6], itm_hotel_booking_portal_weekday_closed_list('5,6'));
        // 2026-08-07 is Friday (5)
        $this->assertFalse(itm_hotel_booking_portal_room_type_validate_stay($typeRow, '2026-08-07', '2026-08-09', '2026-08-01')['ok']);
        // 2026-08-10 Monday arrival, depart Wednesday — Sunday departure blocked when COT is 0
        $this->assertFalse(itm_hotel_booking_portal_room_type_validate_stay($typeRow, '2026-08-10', '2026-08-16', '2026-08-01')['ok']);
    }

    public function testComplimentaryRoomCredit(): void
    {
        $settings = [
            'portal_complimentary_min_rooms_paid' => 10,
            'portal_complimentary_rooms_free' => 1,
        ];
        $lineTotals = array_fill(0, 11, 100.0);
        $lineTotals[0] = 50.0;
        $credit = itm_hotel_booking_portal_complimentary_room_credit($settings, 11, $lineTotals);
        $this->assertEqualsWithDelta(50.0, $credit, 0.01);
        $this->assertSame(0.0, itm_hotel_booking_portal_complimentary_room_credit($settings, 10, $lineTotals));
    }

    public function testOccupancyLineLabel(): void
    {
        $label = itm_hotel_booking_portal_occupancy_line_label(['rooms' => 1, 'adults' => 2, 'children' => 1, 'babies' => 1]);
        $this->assertStringContainsString('2 adults', $label);
        $this->assertStringContainsString('1 child', $label);
        $this->assertStringContainsString('1 baby', $label);
    }

    public function testPortalBookableCardContract(): void
    {
        $typeRow = self::baseTypeRow(['portal_bookable' => 0]);
        $occ = ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0];
        $result = itm_hotel_booking_portal_room_type_card_available($typeRow, $occ, '2026-08-10', '2026-08-11', true);
        $this->assertFalse($result['available']);
        $this->assertSame('not_bookable', $result['reason']);
    }

    public function testDraftPetPolicyStrictAllLines(): void
    {
        global $conn;
        if (!$conn) {
            $this->markTestSkipped('Database connection unavailable');
        }
        $companyId = 1;
        $draft = [
            'room_type_id' => 1,
            'room_lines' => [
                ['room_type_id' => 1],
                ['room_type_id' => 2],
            ],
        ];
        $allowed = itm_hotel_booking_portal_draft_pets_allowed($conn, $companyId, $draft);
        $this->assertIsBool($allowed);
        $policy = itm_hotel_booking_portal_draft_pet_policy($conn, $companyId, 1, $draft);
        $this->assertArrayHasKey('daily_fee', $policy);
        $this->assertArrayHasKey('max_weight_kg', $policy);
    }

    public function testDraftRequiresApprovalHelperExists(): void
    {
        $this->assertTrue(function_exists('itm_hotel_booking_portal_draft_requires_approval'));
    }

    public function testExtraBedsExtendEffectiveMaxTotal(): void
    {
        $typeRow = self::baseTypeRow([
            'max_total_guests' => 4,
            'max_adults' => 4,
            'max_children' => 2,
            'max_babies' => 2,
            'extra_bed_allowed' => 1,
            'max_extra_beds' => 2,
        ]);
        $this->assertSame(6, itm_hotel_booking_portal_effective_max_total_guests($typeRow));
        $occ = ['rooms' => 1, 'adults' => 2, 'children' => 2, 'babies' => 2];
        $this->assertTrue(itm_hotel_booking_room_type_fits_occupancy($typeRow, $occ));
        $typeRow['max_extra_beds'] = 0;
        $this->assertFalse(itm_hotel_booking_room_type_fits_occupancy($typeRow, $occ));
    }

    public function testChildMaxAgeRejectsChildrenWhenBandTooYoung(): void
    {
        $typeRow = self::baseTypeRow(['child_max_age' => 1]);
        $this->assertFalse(itm_hotel_booking_room_type_fits_occupancy($typeRow, ['rooms' => 1, 'adults' => 2, 'children' => 1, 'babies' => 0]));
        $this->assertTrue(itm_hotel_booking_room_type_fits_occupancy($typeRow, ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 1]));
    }

    public function testCribIncludedZerosBabySupplement(): void
    {
        $pricing = self::pricingDefaults();
        $typeRow = self::baseTypeRow([
            'portal_baby_nightly_supplement' => 15.0,
            'crib_included' => 1,
        ]);
        $rules = itm_hotel_booking_portal_room_type_effective_rules($typeRow, $pricing);
        $this->assertTrue($rules['crib_included']);
        $occ = ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 1];
        $withCrib = itm_hotel_booking_portal_quote_nightly(100.0, $occ, 0, $pricing, 0, $typeRow, $rules);
        $withoutCrib = itm_hotel_booking_portal_quote_nightly(100.0, $occ, 0, $pricing, 0, self::baseTypeRow([
            'portal_baby_nightly_supplement' => 15.0,
            'crib_included' => 0,
        ]));
        $this->assertEqualsWithDelta(100.0, $withCrib, 0.01);
        $this->assertEqualsWithDelta(115.0, $withoutCrib, 0.01);
    }

    public function testMultiRoomPerSliceMinAdultsAndMaxTotal(): void
    {
        $typeRow = self::baseTypeRow(['min_adults' => 2, 'max_total_guests' => 3]);
        $occ = ['rooms' => 2, 'adults' => 3, 'children' => 0, 'babies' => 0];
        $this->assertFalse(itm_hotel_booking_room_type_fits_occupancy($typeRow, $occ));
        $occOk = ['rooms' => 2, 'adults' => 4, 'children' => 0, 'babies' => 0];
        $this->assertTrue(itm_hotel_booking_room_type_fits_occupancy($typeRow, $occOk));
    }

    public function testConnectingUnitCheckoutLineCount(): void
    {
        $primary = ['connecting_room_id' => 20];
        $this->assertSame(2, itm_hotel_booking_portal_checkout_required_room_line_count($primary, ['rooms' => 1]));
        $this->assertFalse(itm_hotel_booking_portal_connecting_unit_fits_occupancy(null, 0, ['id' => 1], ['id' => 2], ['rooms' => 1, 'adults' => 2]));
    }

    public function testCheckoutRequiredLineCountForConnectingUnit(): void
    {
        $roomRow = ['connecting_room_id' => 5];
        $this->assertSame(2, itm_hotel_booking_portal_checkout_required_room_line_count($roomRow, ['rooms' => 1, 'adults' => 2]));
        $this->assertSame(3, itm_hotel_booking_portal_checkout_required_room_line_count($roomRow, ['rooms' => 3, 'adults' => 4]));
    }
}
