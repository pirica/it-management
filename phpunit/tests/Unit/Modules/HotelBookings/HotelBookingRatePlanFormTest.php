<?php

use PHPUnit\Framework\TestCase;

final class HotelBookingRatePlanFormTest extends TestCase
{
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 5);
    }

    public function testResolvePortalRatePlanIdIgnoresAddNewMarker(): void
    {
        require_once self::repoRoot() . '/modules/hotel_bookings/includes/hb_booking_form.php';
        $this->assertSame(0, hb_booking_resolve_portal_rate_plan_id(null, 1, 1, '__add_new__'));
    }

    public function testRatePlanFormAuditPasses(): void
    {
        require_once self::repoRoot() . '/modules/hotel_bookings/includes/hb_booking_form.php';
        $failures = hb_booking_rate_plan_form_audit_failures(self::repoRoot());
        $this->assertSame([], $failures, implode('; ', $failures));
    }

    public function testRatePlanSelectJsHandlesQuickAdd(): void
    {
        $js = (string) file_get_contents(self::repoRoot() . '/js/hotel-bookings-rate-plan-select.js');
        $this->assertStringContainsString('__add_new__', $js);
        $this->assertStringContainsString('ensureModalOnBody', $js);
        $this->assertStringContainsString('handleQuickAddSelection', $js);
        $this->assertStringContainsString('create.php?embed=1', $js);
        $this->assertStringNotContainsString('roomSelect.focus', $js);
    }

    public function testBookingFormProbeScriptExists(): void
    {
        $path = self::repoRoot() . '/scripts/lib/itm_hospitality_booking_form_probe.php';
        $this->assertFileExists($path);
    }
}
