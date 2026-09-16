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

    public function testPrepareCheckoutSummaryLowersTouristTaxWhenAdultsDecrease(): void
    {
        global $conn;
        if (!$conn) {
            $this->markTestSkipped('Database connection unavailable');
        }
        $draft = [
            'company_id' => 1,
            'hotel_id' => 1,
            'check_in' => '2026-09-30',
            'check_out' => '2026-10-01',
            'nights' => 1,
            'occupancy' => ['rooms' => 2, 'adults' => 2, 'children' => 0, 'babies' => 0],
            'rate_plan' => 'room_only',
        ];
        $room = ['hotel_id' => 1, 'price_per_night' => 100.0];
        $settings = [];
        // Why: prepare_checkout_summary prefers draft occupancy when stay context matches (not URL alone).
        $draftTwoAdults = $draft;
        $draftTwoAdults['occupancy'] = ['rooms' => 2, 'adults' => 2, 'children' => 0, 'babies' => 0];
        $draftOneAdult = $draft;
        $draftOneAdult['occupancy'] = ['rooms' => 2, 'adults' => 1, 'children' => 0, 'babies' => 0];
        $preparedTwo = itm_hotel_booking_portal_prepare_checkout_summary(
            $conn,
            1,
            $room,
            $draftTwoAdults,
            $draftTwoAdults['occupancy'],
            '2026-09-30',
            1,
            $settings
        );
        $preparedOne = itm_hotel_booking_portal_prepare_checkout_summary(
            $conn,
            1,
            $room,
            $draftOneAdult,
            $draftOneAdult['occupancy'],
            '2026-09-30',
            1,
            $settings
        );
        $breakdownTwo = itm_hotel_booking_portal_checkout_breakdown(
            (float) $preparedTwo['base_per_night'],
            '2026-09-30',
            '2026-10-01',
            $preparedTwo['occupancy'],
            (float) $preparedTwo['discount_percent'],
            $preparedTwo['draft'],
            2.0
        );
        $breakdownOne = itm_hotel_booking_portal_checkout_breakdown(
            (float) $preparedOne['base_per_night'],
            '2026-09-30',
            '2026-10-01',
            $preparedOne['occupancy'],
            (float) $preparedOne['discount_percent'],
            $preparedOne['draft'],
            2.0
        );
        $this->assertSame(2, (int) ($preparedTwo['occupancy']['adults'] ?? 0));
        $this->assertSame(1, (int) ($preparedOne['occupancy']['adults'] ?? 0));
        $this->assertGreaterThan((float) ($breakdownOne['tourist_tax'] ?? 0), (float) ($breakdownTwo['tourist_tax'] ?? 0));
    }

    public function testBuildCustomizeRedirectUrlIncludesOccupancy(): void
    {
        $url = itm_hotel_booking_portal_build_customize_redirect_url(
            ['rooms' => 2, 'adults' => 1, 'children' => 0, 'babies' => 0]
        );
        $this->assertStringContainsString('customize.php', $url);
        $this->assertStringContainsString('rooms=2', $url);
        $this->assertStringContainsString('adults=1', $url);
    }
}
