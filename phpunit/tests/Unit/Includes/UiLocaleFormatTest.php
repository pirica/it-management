<?php

use PHPUnit\Framework\TestCase;

final class UiLocaleFormatTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('itm_ui_locale_format_date_display')) {
            require_once __DIR__ . '/../../../../includes/itm_date_format.php';
            require_once __DIR__ . '/../../../../includes/itm_ui_locale_format.php';
        }
    }

    public function testDefaultDateFormatIsEuropeanAbbreviatedMonth(): void
    {
        $config = itm_ui_locale_normalize_post_values([])['values'];
        $this->assertSame('european_ddmmmyyyy', $config['ui_date_format']);
        $this->assertSame('17/Aug/2026', itm_ui_locale_format_date_display('2026-08-17', $config));
    }

    public function testMoneySuffixAndPrefixAreMutuallyExclusive(): void
    {
        $suffix = itm_ui_locale_normalize_post_values([
            'ui_money_symbol_suffix' => '1',
        ])['values'];
        $this->assertSame(1, $suffix['ui_money_symbol_suffix']);
        $this->assertSame(0, $suffix['ui_money_symbol_prefix']);

        $prefix = itm_ui_locale_normalize_post_values([
            'ui_money_symbol_prefix' => '1',
        ])['values'];
        $this->assertSame(0, $prefix['ui_money_symbol_suffix']);
        $this->assertSame(1, $prefix['ui_money_symbol_prefix']);
    }

    public function testDatetimeDefaultFallsBackWhenDisabled(): void
    {
        $values = itm_ui_locale_normalize_post_values([
            'ui_datetime_european2_enabled' => '0',
            'ui_datetime_european1_enabled' => '1',
            'ui_datetime_format_default' => 'european2',
        ])['values'];
        $this->assertSame('european1', $values['ui_datetime_format_default']);
        $this->assertSame('17/08/2026 22:58', itm_ui_locale_format_datetime_display('2026-08-17 22:58:00', $values));
    }
}
