<?php

use PHPUnit\Framework\TestCase;

final class HotelBookingCheckoutOccupancyTest extends TestCase
{
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 5);
    }

    protected function setUp(): void
    {
        require_once self::repoRoot() . '/config/config.php';
        if (!defined('APPURL')) {
            define('APPURL', 'http://localhost/it-management/booking');
        }
    }

    public function testBuildRoomsRestartUrlIncludesStayAndOccupancy(): void
    {
        $url = itm_hotel_booking_portal_build_rooms_restart_url(
            1,
            '2026-09-24',
            2,
            ['rooms' => 2, 'adults' => 2, 'children' => 1, 'babies' => 0]
        );
        $this->assertStringContainsString('rooms.php', $url);
        $this->assertStringContainsString('id=1', $url);
        $this->assertStringContainsString('check_in=2026-09-24', $url);
        $this->assertStringContainsString('nights=2', $url);
        $this->assertStringContainsString('rooms=2', $url);
        $this->assertStringContainsString('adults=2', $url);
        $this->assertStringContainsString('children=1', $url);
    }

    public function testApplyCheckoutOccupancyChangeRejectsExpiredDraft(): void
    {
        global $conn;
        $result = itm_hotel_booking_portal_apply_checkout_occupancy_change(
            $conn,
            1,
            ['hotel_id' => 0],
            [],
            []
        );
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['restart']);
        $this->assertStringContainsString('expired', strtolower((string) ($result['error'] ?? '')));
    }

    public function testApplyCheckoutOccupancyChangeRoomCountRestartsAtStepOne(): void
    {
        global $conn;
        if (!$conn) {
            $this->markTestSkipped('Database connection unavailable');
        }
        $draft = [
            'hotel_id' => 1,
            'check_in' => '2026-12-01',
            'check_out' => '2026-12-03',
            'nights' => 2,
            'occupancy' => ['rooms' => 1, 'adults' => 1, 'children' => 0, 'babies' => 0],
            'room_id' => 1,
        ];
        $result = itm_hotel_booking_portal_apply_checkout_occupancy_change(
            $conn,
            1,
            $draft,
            ['rooms' => 2, 'adults' => 2, 'children' => 0, 'babies' => 0],
            []
        );
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['restart']);
        $this->assertNotEmpty($result['redirect_url']);
        $this->assertStringContainsString('rooms.php', (string) $result['redirect_url']);
        $this->assertStringContainsString('rooms=2', (string) $result['redirect_url']);
    }
}
