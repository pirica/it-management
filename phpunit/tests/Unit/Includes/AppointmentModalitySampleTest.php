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
}
