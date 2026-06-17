<?php

use PHPUnit\Framework\TestCase;

final class ItmDateFormatTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('itm_parse_date_input')) {
            require_once __DIR__ . '/../../../../includes/itm_date_format.php';
        }
    }
    public function testParseUkDateFormatsToIso(): void
    {
        $this->assertSame('2026-06-18', itm_parse_date_input('18/06/2026'));
        $this->assertSame('2026-06-18', itm_parse_date_input('18-06-2026'));
        $this->assertSame('2026-06-18', itm_parse_date_input('2026-06-18'));
    }

    public function testRejectsUsStyleWhenUkInvalid(): void
    {
        $this->assertNull(itm_parse_date_input('06/18/2026'));
    }

    public function testFormatDateDisplayUsesUkLayout(): void
    {
        $this->assertSame('18/06/2026', itm_format_date_display('2026-06-18'));
        $this->assertSame('18/06/2026', itm_format_date_display('18/06/2026'));
    }

    public function testFormatDatetimeDisplayUsesUkLayout(): void
    {
        $this->assertSame('18/06/2026 14:30', itm_format_datetime_display('2026-06-18 14:30:00'));
    }

    public function testCellScalarDisplayFormatsDateFields(): void
    {
        $this->assertSame('18/06/2026', itm_format_cell_scalar_display('termination_date', '2026-06-18'));
    }
}
