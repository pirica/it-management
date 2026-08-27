<?php

use PHPUnit\Framework\TestCase;

final class HotelBookingPortalDateFormatTest extends TestCase
{
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 5);
    }

    protected function setUp(): void
    {
        require_once self::repoRoot() . '/config/config.php';
    }

    public function testPortalDateFormatPositions(): void
    {
        $this->assertSame(
            '17/08/2026',
            itm_hotel_booking_portal_format_date_display('2026-08-17', ['portal_date_format' => 'european_ddmmyyyy'])
        );
        $this->assertSame(
            '08/17/2026',
            itm_hotel_booking_portal_format_date_display('2026-08-17', ['portal_date_format' => 'us_mmddyyyy'])
        );
        $this->assertSame(
            '2026-08-17',
            itm_hotel_booking_portal_format_date_display('2026-08-17', ['portal_date_format' => 'iso_yyyymmdd'])
        );
        $this->assertSame(
            '17/Aug/2026',
            itm_hotel_booking_portal_format_date_display('2026-08-17', ['portal_date_format' => 'european_ddmmmyyyy'])
        );
    }

    public function testPortalDateFormatFromSettingsAcceptsDdMmmYyyy(): void
    {
        $this->assertSame(
            'european_ddmmmyyyy',
            itm_hotel_booking_portal_date_format_from_settings(['portal_date_format' => 'european_ddmmmyyyy'])
        );
    }

    public function testDatetimeDefaultEuropean2WhenUnset(): void
    {
        $enabled = itm_hotel_booking_portal_datetime_format_enabled_map([]);
        $this->assertTrue(!empty($enabled['european2']));
        $this->assertSame('european2', itm_hotel_booking_portal_datetime_format_default_from_settings([]));
    }

    public function testDatetimeDisplayUsesEnabledDefault(): void
    {
        $settings = [
            'portal_time_format' => 'h24',
            'portal_datetime_european2_enabled' => 1,
            'portal_datetime_format_default' => 'european2',
        ];
        $formatted = itm_hotel_booking_portal_format_datetime_display('2026-08-17 22:58:00', $settings);
        $this->assertStringContainsString('17/AUG/2026', $formatted);
        $this->assertStringContainsString('22:58', $formatted);
    }
}
