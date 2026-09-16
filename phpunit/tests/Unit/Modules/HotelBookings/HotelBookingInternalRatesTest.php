<?php

use PHPUnit\Framework\TestCase;

final class HotelBookingInternalRatesTest extends TestCase
{
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 5);
    }

    protected function setUp(): void
    {
        require_once self::repoRoot() . '/config/config.php';
    }

    public function testNormalizeInternalRateAliases(): void
    {
        $this->assertSame('comp', itm_hotel_booking_normalize_internal_rate_code('COMPIMENTARY'));
        $this->assertSame('comp', itm_hotel_booking_normalize_internal_rate_code('COMPLIMENTARY'));
        $this->assertSame('use', itm_hotel_booking_normalize_internal_rate_code('HOUSE_USE'));
        $this->assertSame('use', itm_hotel_booking_normalize_internal_rate_code('use'));
        $this->assertSame('comp', itm_hotel_booking_normalize_internal_rate_code('comp'));
    }

    public function testApplyInternalRateToBreakdown(): void
    {
        $base = [
            'room_charges' => 180.0,
            'tourist_tax' => 9.0,
            'total' => 189.0,
        ];
        $use = itm_hotel_booking_apply_internal_rate_to_breakdown($base, 'use');
        $this->assertSame(0.0, (float) $use['room_charges']);
        $this->assertSame(9.0, (float) $use['total']);

        $comp = itm_hotel_booking_apply_internal_rate_to_breakdown($base, 'comp');
        $this->assertSame(0.0, (float) $comp['total']);
    }

    public function testPortalCheckoutBreakdownHonoursInternalRate(): void
    {
        $breakdown = itm_hotel_booking_portal_checkout_breakdown(
            100.0,
            '2026-08-01',
            '2026-08-03',
            ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0],
            0.0,
            ['internal_rate_code' => 'comp'],
            0.0
        );
        $this->assertSame(0.0, (float) $breakdown['total']);
    }

    public function testGuestInternalRateHiddenUnlessSettingEnabled(): void
    {
        $settingsOff = ['portal_show_internal_rates' => 0];
        $this->assertSame('', itm_hotel_booking_portal_parse_internal_rate_code(['internal_rate_code' => 'comp'], $settingsOff));
        $settingsOn = ['portal_show_internal_rates' => 1];
        $this->assertSame('comp', itm_hotel_booking_portal_parse_internal_rate_code(['internal_rate_code' => 'comp'], $settingsOn));
    }
}
