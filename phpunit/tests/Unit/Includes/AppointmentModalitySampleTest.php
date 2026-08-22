<?php

use PHPUnit\Framework\TestCase;

class AppointmentModalitySampleTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('itm_appointment_regression_expected_modality_for_dow')) {
            require_once __DIR__ . '/../../../../includes/itm_appointment.php';
        }
    }

    public function testWednesdayIsRemoteOnlyInCanonicalSample(): void
    {
        $flags = itm_appointment_regression_expected_modality_for_dow(3);
        $this->assertFalse($flags['in_person']);
        $this->assertTrue($flags['remote']);
    }

    public function testMondayAllowsBothModalitiesInCanonicalSample(): void
    {
        $flags = itm_appointment_regression_expected_modality_for_dow(1);
        $this->assertTrue($flags['in_person']);
        $this->assertTrue($flags['remote']);
    }

    public function testSundayIsClosedInCanonicalSample(): void
    {
        $flags = itm_appointment_regression_expected_modality_for_dow(0);
        $this->assertFalse($flags['in_person']);
        $this->assertFalse($flags['remote']);
    }

    public function testCanonicalBusinessHoursMatrixHasSevenDays(): void
    {
        $rows = itm_appointment_regression_sample_business_hours_by_dow();
        $this->assertCount(7, $rows);
        $this->assertSame(0, $rows[3]['allows_in_person']);
        $this->assertSame(1, $rows[3]['allows_remote']);
        $this->assertSame(1, $rows[1]['allows_in_person']);
        $this->assertSame(1, $rows[4]['allows_in_person']);
    }

    public function testSettingsBookingEnabledRequiresActiveRow(): void
    {
        $this->assertFalse(itm_appointment_settings_booking_enabled(null));
        $this->assertFalse(itm_appointment_settings_booking_enabled(['active' => 0]));
        $this->assertTrue(itm_appointment_settings_booking_enabled(['active' => 1]));
    }

    public function testIcsBuilderIncludesCoreFields(): void
    {
        $ics = itm_appointment_build_ics_vevent(
            [
                'id' => 42,
                'appointment_date' => '2026-03-01',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'timezone' => 'Europe/London',
            ],
            [
                'reason_name' => 'VPN',
                'type_label' => 'Remote',
                'employee_name' => 'Alice',
                'appointment_type_name' => 'remote',
                'timezone' => 'Europe/London',
            ]
        );
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('UID:appointment-42@it-management', $ics);
        $this->assertStringContainsString('SUMMARY:', $ics);
        $this->assertStringContainsString('DTSTART;TZID=', $ics);
    }

    public function testSlotIsPastUsesTimezone(): void
    {
        $tz = new DateTimeZone('UTC');
        $futureDate = (new DateTime('now', $tz))->modify('+2 days')->format('Y-m-d');
        $this->assertFalse(itm_appointment_slot_is_past($futureDate, '09:00:00', 'UTC'));

        $pastDate = (new DateTime('now', $tz))->modify('-2 days')->format('Y-m-d');
        $this->assertTrue(itm_appointment_slot_is_past($pastDate, '09:00:00', 'UTC'));
    }
}
