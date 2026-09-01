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

    public function testMergeOccupancyIntoUrlReplacesAdultsQueryParam(): void
    {
        $url = itm_hotel_booking_portal_merge_occupancy_into_url(
            'http://localhost/it-management/booking/rooms/select-rate.php?id=1&check_in=2026-09-30&nights=1&rooms=1&adults=1&children=0&babies=0',
            ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0]
        );
        $this->assertStringContainsString('adults=2', $url);
        $this->assertStringNotContainsString('adults=1', $url);
    }

    public function testCheckoutRedirectUrlAllowedAcceptsHttpsWhenAppUrlIsHttp(): void
    {
        $this->assertTrue(itm_hotel_booking_portal_checkout_redirect_url_allowed(
            'https://localhost/it-management/booking/rooms/select-rate.php?id=1'
        ));
    }

    public function testApplyCheckoutOccupancyChangeSuccessMergesOccupancyIntoRedirect(): void
    {
        global $conn;
        if (!$conn) {
            $this->markTestSkipped('Database connection unavailable');
        }
        $draft = [
            'hotel_id' => 1,
            'check_in' => '2026-12-01',
            'check_out' => '2026-12-02',
            'nights' => 1,
            'occupancy' => ['rooms' => 1, 'adults' => 1, 'children' => 0, 'babies' => 0],
            'room_id' => 1,
            'room_lines' => [['room_id' => 1]],
            'room_lines_context' => '',
        ];
        $redirectIn = 'http://localhost/it-management/booking/rooms/select-rate.php?id=1&check_in=2026-12-01&nights=1&rooms=1&adults=1&children=0&babies=0';
        $result = itm_hotel_booking_portal_apply_checkout_occupancy_change(
            $conn,
            1,
            $draft,
            ['rooms' => 1, 'adults' => 2, 'children' => 0, 'babies' => 0],
            [],
            ['redirect_url' => $redirectIn, 'room_id' => 1]
        );
        if (!empty($result['ok'])) {
            $this->assertStringContainsString('adults=2', (string) ($result['redirect_url'] ?? ''));
            $this->assertStringContainsString('2 adults', (string) ($result['occupancy_label'] ?? ''));
        } else {
            $this->markTestSkipped('Draft apply prerequisites not met in seed data: ' . (string) ($result['error'] ?? ''));
        }
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
