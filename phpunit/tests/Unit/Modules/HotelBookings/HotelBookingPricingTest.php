<?php

use PHPUnit\Framework\TestCase;

final class HotelBookingPricingTest extends TestCase
{
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 5);
    }

    protected function setUp(): void
    {
        require_once self::repoRoot() . '/config/config.php';
    }

    /**
     * Test Scenario 1: 1 Person booking (1 adult, 0 children, 0 babies)
     */
    public function testPricingFor1Person(): void
    {
        $basePerNight = 120.00;
        $checkIn = '2026-08-01';
        $checkOut = '2026-08-02'; // 1 night
        $occupancy = [
            'rooms' => 1,
            'adults' => 1,
            'children' => 0,
            'babies' => 0
        ];
        $discountPercent = 0.0;
        $draft = [
            'rate_plan' => 'room_only',
            'traveling_with_pet' => 0
        ];

        $breakdown = itm_hotel_booking_portal_checkout_breakdown(
            $basePerNight,
            $checkIn,
            $checkOut,
            $occupancy,
            $discountPercent,
            $draft
        );

        // Subtotal = 120.00 base (rooms=1, adults=1 is <= 2 included, so no extra adult supplement)
        $this->assertEquals(120.00, $breakdown['room_charges']);
        $this->assertEquals(120.00, $breakdown['total']);
    }

    /**
     * Test Scenario 2: 2 Persons booking (2 adults, 0 children, 0 babies)
     */
    public function testPricingFor2Persons(): void
    {
        $basePerNight = 120.00;
        $checkIn = '2026-08-01';
        $checkOut = '2026-08-02'; // 1 night
        $occupancy = [
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'babies' => 0
        ];
        $discountPercent = 0.0;
        $draft = [
            'rate_plan' => 'room_only',
            'traveling_with_pet' => 0
        ];

        $breakdown = itm_hotel_booking_portal_checkout_breakdown(
            $basePerNight,
            $checkIn,
            $checkOut,
            $occupancy,
            $discountPercent,
            $draft
        );

        // Subtotal = 120.00 base
        $this->assertEquals(120.00, $breakdown['room_charges']);
        $this->assertEquals(120.00, $breakdown['total']);
    }

    /**
     * Test Scenario 3: Pet supplement booking (+50.00 Eur)
     */
    public function testPricingWithPet(): void
    {
        $basePerNight = 120.00;
        $checkIn = '2026-08-01';
        $checkOut = '2026-08-02'; // 1 night
        $occupancy = [
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'babies' => 0
        ];
        $discountPercent = 0.0;
        $draft = [
            'rate_plan' => 'room_only',
            'traveling_with_pet' => 1
        ];

        $breakdown = itm_hotel_booking_portal_checkout_breakdown(
            $basePerNight,
            $checkIn,
            $checkOut,
            $occupancy,
            $discountPercent,
            $draft
        );

        // Subtotal = 120.00 base + 50.00 pet supplement = 170.00 EUR
        $this->assertEquals(170.00, $breakdown['room_charges']);
        $this->assertEquals(170.00, $breakdown['total']);
    }

    /**
     * Test Scenario 4: 2 Rooms booking
     */
    public function testPricingFor2Rooms(): void
    {
        $basePerNight = 120.00;
        $checkIn = '2026-08-01';
        $checkOut = '2026-08-02'; // 1 night
        $occupancy = [
            'rooms' => 2,
            'adults' => 2,
            'children' => 0,
            'babies' => 0
        ];
        $discountPercent = 0.0;
        $draft = [
            'rate_plan' => 'room_only',
            'traveling_with_pet' => 0
        ];

        $breakdown = itm_hotel_booking_portal_checkout_breakdown(
            $basePerNight,
            $checkIn,
            $checkOut,
            $occupancy,
            $discountPercent,
            $draft
        );

        // Subtotal = 120.00 * 2 rooms = 240.00 EUR
        $this->assertEquals(240.00, $breakdown['room_charges']);
        $this->assertEquals(240.00, $breakdown['total']);
    }

    /**
     * Test Scenario 5: Booking with 2 Children (+22.00 Eur each child supplement per night)
     */
    public function testPricingWith2Children(): void
    {
        $basePerNight = 120.00;
        $checkIn = '2026-08-01';
        $checkOut = '2026-08-02'; // 1 night
        $occupancy = [
            'rooms' => 1,
            'adults' => 2,
            'children' => 2,
            'babies' => 0
        ];
        $discountPercent = 0.0;
        $draft = [
            'rate_plan' => 'room_only',
            'traveling_with_pet' => 0
        ];

        $breakdown = itm_hotel_booking_portal_checkout_breakdown(
            $basePerNight,
            $checkIn,
            $checkOut,
            $occupancy,
            $discountPercent,
            $draft
        );

        // Subtotal = 120.00 base + (2 children * 22.00 supplement) = 164.00 EUR
        $this->assertEquals(164.00, $breakdown['room_charges']);
        $this->assertEquals(164.00, $breakdown['total']);
    }

    /**
     * Test Scenario 6: Booking with 1 Baby (Babies do not carry nightly supplements)
     */
    public function testPricingWith1Baby(): void
    {
        $basePerNight = 120.00;
        $checkIn = '2026-08-01';
        $checkOut = '2026-08-02'; // 1 night
        $occupancy = [
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'babies' => 1
        ];
        $discountPercent = 0.0;
        $draft = [
            'rate_plan' => 'room_only',
            'traveling_with_pet' => 0
        ];

        $breakdown = itm_hotel_booking_portal_checkout_breakdown(
            $basePerNight,
            $checkIn,
            $checkOut,
            $occupancy,
            $discountPercent,
            $draft
        );

        // Subtotal = 120.00 base + 0.00 baby = 120.00 EUR
        $this->assertEquals(120.00, $breakdown['room_charges']);
        $this->assertEquals(120.00, $breakdown['total']);
    }

    /**
     * Test Scenario 7: Booking with upsell/upgrade accepted (+35.00 upgrade price per night)
     */
    public function testPricingWithUpsell(): void
    {
        $basePerNight = 120.00;
        $checkIn = '2026-08-01';
        $checkOut = '2026-08-02'; // 1 night
        $occupancy = [
            'rooms' => 1,
            'adults' => 2,
            'children' => 0,
            'babies' => 0
        ];
        $discountPercent = 0.0;
        $draft = [
            'rate_plan' => 'room_only',
            'traveling_with_pet' => 0,
            'upgrade_accepted' => 1,
            'upgrade_price_per_night' => 35.00
        ];

        $breakdown = itm_hotel_booking_portal_checkout_breakdown(
            $basePerNight,
            $checkIn,
            $checkOut,
            $occupancy,
            $discountPercent,
            $draft
        );

        // Subtotal = 120.00 base + 35.00 upgrade supplement = 155.00 EUR
        $this->assertEquals(155.00, $breakdown['room_charges']);
        $this->assertEquals(155.00, $breakdown['total']);
    }
}
