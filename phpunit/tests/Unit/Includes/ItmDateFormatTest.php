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
        $this->assertSame('2026-06-18', itm_parse_date_input('18/Jun/2026'));
    }

    public function testRejectsUsStyleWhenUkInvalid(): void
    {
        $this->assertNull(itm_parse_date_input('06/18/2026'));
    }

    public function testFormatDateDisplayUsesUkLayout(): void
    {
        $this->assertSame('18/Jun/2026', itm_format_date_display('2026-06-18'));
        $this->assertSame('18/Jun/2026', itm_format_date_display('18/06/2026'));
    }

    public function testFormatDatetimeDisplayUsesUkLayout(): void
    {
        $this->assertSame('18/Jun/2026 14:30', itm_format_datetime_display('2026-06-18 14:30:00'));
    }

    public function testFormatAuditTimestampDisplayUsesUkLayout(): void
    {
        $this->assertSame('01/Jan/2026 - 00:00:01', itm_format_audit_timestamp_display('2026-01-01 00:00:01'));
        $this->assertSame('31/Aug/2026 - 14:30:00', itm_format_audit_timestamp_display('2026-08-31 14:30:00'));
        $this->assertSame('', itm_format_audit_timestamp_display(null));
    }

    public function testCellScalarDisplayFormatsDateFields(): void
    {
        $this->assertSame('18/Jun/2026', itm_format_cell_scalar_display('termination_date', '2026-06-18'));
    }

    public function testCellScalarDisplayFormatsMoneyFieldsWhenLocaleHelperLoaded(): void
    {
        if (!function_exists('itm_ui_locale_format_money_display')) {
            require_once __DIR__ . '/../../../../includes/itm_ui_locale_format.php';
        }
        $saved = $GLOBALS['ui_config'] ?? null;
        $GLOBALS['ui_config'] = [
            'ui_money_symbol' => 'EUR',
            'ui_money_symbol_suffix' => 1,
            'ui_money_symbol_prefix' => 0,
        ];
        $this->assertSame('69.50€', itm_format_cell_scalar_display('price', '69.5'));
        $GLOBALS['ui_config'] = [
            'ui_money_symbol' => 'EUR',
            'ui_money_symbol_suffix' => 0,
            'ui_money_symbol_prefix' => 1,
        ];
        $this->assertSame('€69.50', itm_format_cell_scalar_display('purchase_cost', '69.5'));
        if ($saved !== null) {
            $GLOBALS['ui_config'] = $saved;
        } else {
            unset($GLOBALS['ui_config']);
        }
    }

    public function testFormatDateDisplayRespectsUiConfigDateFormat(): void
    {
        if (!function_exists('itm_ui_locale_format_date_display')) {
            require_once __DIR__ . '/../../../../includes/itm_ui_locale_format.php';
        }
        $saved = $GLOBALS['ui_config'] ?? null;
        $GLOBALS['ui_config'] = ['ui_date_format' => 'us_mmddyyyy'];
        $this->assertSame('08/17/2026', itm_format_date_display('2026-08-17'));
        $GLOBALS['ui_config'] = ['ui_date_format' => 'european_ddmmmyyyy'];
        $this->assertSame('17/Aug/2026', itm_format_date_display('2026-08-17'));
        if ($saved !== null) {
            $GLOBALS['ui_config'] = $saved;
        } else {
            unset($GLOBALS['ui_config']);
        }
    }

    public function testIsoWeekBoundsMatchPhpWeekForMidYearDate(): void
    {
        $bounds = itm_iso_week_bounds(2026, 25);
        $this->assertNotNull($bounds);
        $this->assertSame('2026-06-15', $bounds['start']);
        $this->assertSame('2026-06-21', $bounds['end']);
        $this->assertSame('25', date('W', strtotime('2026-06-18')));
        $this->assertGreaterThanOrEqual(strtotime($bounds['start']), strtotime('2026-06-18'));
        $this->assertLessThanOrEqual(strtotime($bounds['end']), strtotime('2026-06-18'));
    }

    public function testIsoWeekBoundsSpanYearBoundaryForWeekOne(): void
    {
        $bounds = itm_iso_week_bounds(2026, 1);
        $this->assertNotNull($bounds);
        $this->assertSame('2025-12-29', $bounds['start']);
        $this->assertSame('2026-01-04', $bounds['end']);
    }

    public function testSqlValidDatePredicateAvoidsZeroDateLiteral(): void
    {
        $predicate = itm_sql_valid_date_predicate('e.termination_date');
        $this->assertSame("e.termination_date >= '1970-01-01'", $predicate);
        $this->assertStringNotContainsString('0000-00-00', $predicate);
    }

    public function testParseDatetimeInputAcceptsUkAbbreviatedMonth(): void
    {
        $this->assertSame('2026-06-18 14:30:00', itm_parse_datetime_input('18/Jun/2026 14:30'));
        $this->assertSame('2026-06-18 14:30:45', itm_parse_datetime_input('18/Jun/2026 14:30:45'));
    }

    public function testDatetimeInputLocalValueNormalizesToHtmlFive(): void
    {
        $this->assertSame('2026-06-18T14:30', itm_datetime_input_local_value('2026-06-18 14:30:00'));
        $this->assertSame('2026-06-18T14:30', itm_datetime_input_local_value('18/Jun/2026 14:30'));
        $this->assertSame('', itm_datetime_input_local_value(''));
    }
}
