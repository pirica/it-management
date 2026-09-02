<?php
/**
 * Per-employee locale display preferences from ui_configuration (Settings → UI Configuration).
 * Hotel booking portal keeps separate portal_* columns on hotel_booking_settings.
 */

if (!function_exists('itm_ui_locale_money_symbol_allowed_codes')) {
    function itm_ui_locale_money_symbol_allowed_codes()
    {
        return ['EUR', 'GBP', 'USD'];
    }
}

if (!function_exists('itm_ui_locale_money_symbol_glyph_map')) {
    function itm_ui_locale_money_symbol_glyph_map()
    {
        return ['EUR' => '€', 'GBP' => '£', 'USD' => '$'];
    }
}

if (!function_exists('itm_ui_locale_money_symbol_code_from_config')) {
    function itm_ui_locale_money_symbol_code_from_config($config)
    {
        $config = is_array($config) ? $config : [];
        $code = strtoupper(trim((string) ($config['ui_money_symbol'] ?? 'EUR')));
        if (!in_array($code, itm_ui_locale_money_symbol_allowed_codes(), true)) {
            return 'EUR';
        }
        return $code;
    }
}

if (!function_exists('itm_ui_locale_money_format_options_from_config')) {
    /**
     * @return array{symbol:string,suffix:bool}
     */
    function itm_ui_locale_money_format_options_from_config($config)
    {
        $config = is_array($config) ? $config : [];
        $code = itm_ui_locale_money_symbol_code_from_config($config);
        $symbolMap = itm_ui_locale_money_symbol_glyph_map();
        $suffix = true;
        if (array_key_exists('ui_money_symbol_prefix', $config) && !empty($config['ui_money_symbol_prefix'])) {
            $suffix = false;
        } elseif (array_key_exists('ui_money_symbol_suffix', $config)) {
            $suffix = !empty($config['ui_money_symbol_suffix']);
        }
        return [
            'symbol' => $symbolMap[$code] ?? '€',
            'suffix' => $suffix,
        ];
    }
}

if (!function_exists('itm_ui_locale_date_format_from_config')) {
    function itm_ui_locale_date_format_from_config($config)
    {
        $config = is_array($config) ? $config : [];
        $fmt = strtolower(trim((string) ($config['ui_date_format'] ?? 'european_ddmmmyyyy')));
        if (!in_array($fmt, ['european_ddmmyyyy', 'european_ddmmmyyyy', 'us_mmddyyyy', 'iso_yyyymmdd'], true)) {
            return 'european_ddmmmyyyy';
        }
        return $fmt;
    }
}

if (!function_exists('itm_ui_locale_time_format_from_config')) {
    function itm_ui_locale_time_format_from_config($config)
    {
        $config = is_array($config) ? $config : [];
        $fmt = strtolower(trim((string) ($config['ui_time_format'] ?? 'h24')));
        return $fmt === 'h12' ? 'h12' : 'h24';
    }
}

if (!function_exists('itm_ui_locale_datetime_format_enabled_map')) {
    function itm_ui_locale_datetime_format_enabled_map($config)
    {
        $config = is_array($config) ? $config : [];
        return [
            'european1' => !empty($config['ui_datetime_european1_enabled']),
            'european2' => array_key_exists('ui_datetime_european2_enabled', $config)
                ? !empty($config['ui_datetime_european2_enabled'])
                : true,
            'iso' => !empty($config['ui_datetime_iso_enabled']),
            'readable' => !empty($config['ui_datetime_readable_enabled']),
        ];
    }
}

if (!function_exists('itm_ui_locale_datetime_format_default_from_config')) {
    function itm_ui_locale_datetime_format_default_from_config($config)
    {
        $config = is_array($config) ? $config : [];
        $enabled = itm_ui_locale_datetime_format_enabled_map($config);
        $default = strtolower(trim((string) ($config['ui_datetime_format_default'] ?? 'european2')));
        if (!in_array($default, ['european1', 'european2', 'iso', 'readable'], true)) {
            $default = 'european2';
        }
        if (!empty($enabled[$default])) {
            return $default;
        }
        foreach (['european2', 'european1', 'readable', 'iso'] as $candidate) {
            if (!empty($enabled[$candidate])) {
                return $candidate;
            }
        }
        return 'european2';
    }
}

if (!function_exists('itm_ui_locale_active_config')) {
    /**
     * Resolve locale keys from global ui_configuration when available.
     *
     * @return array<string,mixed>
     */
    function itm_ui_locale_active_config()
    {
        if (isset($GLOBALS['ui_config']) && is_array($GLOBALS['ui_config'])) {
            return $GLOBALS['ui_config'];
        }
        return [];
    }
}

if (!function_exists('itm_ui_locale_normalize_post_values')) {
    /**
     * Validate Settings save_ui_config POST fields for locale preferences.
     *
     * @return array{values:array<string,mixed>,errors:array<int,string>}
     */
    function itm_ui_locale_normalize_post_values(array $post)
    {
        $errors = [];
        $moneySymbol = strtoupper(trim((string) ($post['ui_money_symbol'] ?? 'EUR')));
        if (!in_array($moneySymbol, itm_ui_locale_money_symbol_allowed_codes(), true)) {
            $moneySymbol = 'EUR';
        }
        $moneySuffix = !empty($post['ui_money_symbol_suffix']) ? 1 : 0;
        $moneyPrefix = !empty($post['ui_money_symbol_prefix']) ? 1 : 0;
        if ($moneyPrefix) {
            $moneySuffix = 0;
        } elseif (!$moneySuffix) {
            $moneySuffix = 1;
        }

        $dateFormat = strtolower(trim((string) ($post['ui_date_format'] ?? 'european_ddmmmyyyy')));
        if (!in_array($dateFormat, ['european_ddmmyyyy', 'european_ddmmmyyyy', 'us_mmddyyyy', 'iso_yyyymmdd'], true)) {
            $dateFormat = 'european_ddmmmyyyy';
        }

        $timeFormat = strtolower(trim((string) ($post['ui_time_format'] ?? 'h24')));
        if ($timeFormat !== 'h12') {
            $timeFormat = 'h24';
        }

        $dtEuropean1 = !empty($post['ui_datetime_european1_enabled']) ? 1 : 0;
        $dtEuropean2 = !empty($post['ui_datetime_european2_enabled']) ? 1 : 0;
        $dtIso = !empty($post['ui_datetime_iso_enabled']) ? 1 : 0;
        $dtReadable = !empty($post['ui_datetime_readable_enabled']) ? 1 : 0;
        if (!$dtEuropean1 && !$dtEuropean2 && !$dtIso && !$dtReadable) {
            $dtEuropean2 = 1;
        }

        $dtDefault = strtolower(trim((string) ($post['ui_datetime_format_default'] ?? 'european2')));
        if (!in_array($dtDefault, ['european1', 'european2', 'iso', 'readable'], true)) {
            $dtDefault = 'european2';
        }
        $enabledMap = [
            'european1' => $dtEuropean1,
            'european2' => $dtEuropean2,
            'iso' => $dtIso,
            'readable' => $dtReadable,
        ];
        if (empty($enabledMap[$dtDefault])) {
            foreach (['european2', 'european1', 'readable', 'iso'] as $candidate) {
                if (!empty($enabledMap[$candidate])) {
                    $dtDefault = $candidate;
                    break;
                }
            }
        }

        return [
            'values' => [
                'ui_money_symbol' => $moneySymbol,
                'ui_money_symbol_suffix' => $moneySuffix,
                'ui_money_symbol_prefix' => $moneyPrefix,
                'ui_date_format' => $dateFormat,
                'ui_time_format' => $timeFormat,
                'ui_datetime_european1_enabled' => $dtEuropean1,
                'ui_datetime_european2_enabled' => $dtEuropean2,
                'ui_datetime_iso_enabled' => $dtIso,
                'ui_datetime_readable_enabled' => $dtReadable,
                'ui_datetime_format_default' => $dtDefault,
            ],
            'errors' => $errors,
        ];
    }
}

if (!function_exists('itm_ui_locale_format_date_display')) {
    function itm_ui_locale_format_date_display($rawValue, $config = null)
    {
        $canonical = function_exists('itm_parse_date_input') ? itm_parse_date_input($rawValue) : null;
        if ($canonical === null) {
            $text = trim((string) $rawValue);
            return ($text === '' || $text === '0000-00-00') ? '' : $text;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $canonical);
        if (!$dt instanceof DateTimeImmutable) {
            return trim((string) $rawValue);
        }
        $config = is_array($config) ? $config : itm_ui_locale_active_config();
        $fmt = itm_ui_locale_date_format_from_config($config);
        if ($fmt === 'us_mmddyyyy') {
            return $dt->format('m/d/Y');
        }
        if ($fmt === 'iso_yyyymmdd') {
            return $dt->format('Y-m-d');
        }
        if ($fmt === 'european_ddmmyyyy') {
            return $dt->format('d/m/Y');
        }
        return $dt->format('d/M/Y');
    }
}

if (!function_exists('itm_ui_locale_format_datetime_display')) {
    function itm_ui_locale_format_datetime_display($rawValue, $config = null, $styleOverride = null)
    {
        $config = is_array($config) ? $config : itm_ui_locale_active_config();
        $raw = trim((string) $rawValue);
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return '';
        }
        $canonical = function_exists('itm_parse_datetime_input') ? itm_parse_datetime_input($raw) : null;
        if ($canonical === null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $canonical = $raw . ' 00:00:00';
        }
        if ($canonical === null) {
            return $raw;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $canonical);
        if (!$dt instanceof DateTimeImmutable) {
            return $raw;
        }
        $style = $styleOverride !== null
            ? (string) $styleOverride
            : itm_ui_locale_datetime_format_default_from_config($config);
        $use12 = itm_ui_locale_time_format_from_config($config) === 'h12';
        $timeFmt = $use12 ? 'g:i A' : 'H:i';
        $timeFmtSeconds = $use12 ? 'g:i:s A' : 'H:i:s';
        if ($style === 'audit') {
            $datePart = itm_ui_locale_format_date_display($dt->format('Y-m-d'), $config);
            return $datePart . ' - ' . $dt->format($timeFmtSeconds);
        }
        if ($style === 'european1') {
            return $dt->format('d/m/Y ' . $timeFmt);
        }
        if ($style === 'iso') {
            return gmdate('Y-m-d\TH:i:s\Z', $dt->getTimestamp());
        }
        if ($style === 'readable') {
            return $dt->format('j M Y, ' . $timeFmt);
        }
        return $dt->format('d/M/Y ' . $timeFmt);
    }
}

if (!function_exists('itm_is_money_field_name')) {
    /**
     * Heuristic: list/view decimal columns that represent currency amounts (not counts).
     */
    function itm_is_money_field_name($fieldName)
    {
        $field = strtolower(trim((string) $fieldName));
        if ($field === '') {
            return false;
        }
        if (in_array($field, [
            'price',
            'purchase_cost',
            'amount',
            'subtotal',
            'total',
            'cost',
            'salvage_value',
            'unit_cost',
            'line_total',
            'total_amount',
            'unit_price',
        ], true)) {
            return true;
        }
        if (substr($field, -6) === '_price' || substr($field, -5) === '_cost' || substr($field, -7) === '_amount') {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_ui_locale_format_money_with_options')) {
    function itm_ui_locale_format_money_with_options($amount, array $moneyOptions, $style = 'decimal')
    {
        $amount = (float) $amount;
        $symbol = (string) ($moneyOptions['symbol'] ?? '€');
        $suffix = !empty($moneyOptions['suffix']);
        $formatted = number_format($amount, 2, '.', $style === 'short' ? '' : ',');
        if ($style === 'short' && substr($formatted, -3) === '.00') {
            $formatted = substr($formatted, 0, -3);
        }
        if ($suffix) {
            return $formatted . $symbol;
        }
        return $symbol . $formatted;
    }
}

if (!function_exists('itm_ui_locale_format_money_display')) {
    function itm_ui_locale_format_money_display($amount, $config = null, $style = 'decimal')
    {
        $config = is_array($config) ? $config : itm_ui_locale_active_config();
        $moneyOptions = itm_ui_locale_money_format_options_from_config($config);
        return itm_ui_locale_format_money_with_options($amount, $moneyOptions, $style);
    }
}

if (!function_exists('itm_ui_locale_format_month_short_labels')) {
    /**
     * Month axis labels (1–12) for chart datasets — follows ui_date_format.
     *
     * @return array<int,string>
     */
    function itm_ui_locale_format_month_short_labels($config = null)
    {
        $config = is_array($config) ? $config : itm_ui_locale_active_config();
        $fmt = itm_ui_locale_date_format_from_config($config);
        $labels = [];
        for ($month = 1; $month <= 12; $month++) {
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('2026-%02d-15', $month));
            if (!$dt instanceof DateTimeImmutable) {
                continue;
            }
            if ($fmt === 'iso_yyyymmdd' || $fmt === 'us_mmddyyyy' || $fmt === 'european_ddmmyyyy') {
                $labels[$month] = $dt->format('m');
            } else {
                $labels[$month] = $dt->format('M');
            }
        }
        return $labels;
    }
}

if (!function_exists('itm_ui_locale_format_year_month_chart_label')) {
    /**
     * Compact year-month label for trend charts (input canonical Y-m).
     */
    function itm_ui_locale_format_year_month_chart_label($yearMonth, $config = null)
    {
        $yearMonth = trim((string) $yearMonth);
        if (!preg_match('/^(\d{4})-(\d{2})$/', $yearMonth)) {
            return $yearMonth;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $yearMonth . '-01');
        if (!$dt instanceof DateTimeImmutable) {
            return $yearMonth;
        }
        $config = is_array($config) ? $config : itm_ui_locale_active_config();
        $fmt = itm_ui_locale_date_format_from_config($config);
        if ($fmt === 'iso_yyyymmdd') {
            return $dt->format('Y-m');
        }
        if ($fmt === 'us_mmddyyyy' || $fmt === 'european_ddmmyyyy') {
            return $dt->format('m/Y');
        }
        return $dt->format('M/Y');
    }
}

if (!function_exists('itm_ui_locale_format_chart_day_label')) {
    /**
     * Short day label for dense chart axes (input Y-m-d or parseable date text).
     */
    function itm_ui_locale_format_chart_day_label($rawValue, $config = null)
    {
        $canonical = function_exists('itm_parse_date_input') ? itm_parse_date_input($rawValue) : null;
        if ($canonical === null) {
            return trim((string) $rawValue);
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $canonical);
        if (!$dt instanceof DateTimeImmutable) {
            return trim((string) $rawValue);
        }
        $config = is_array($config) ? $config : itm_ui_locale_active_config();
        $fmt = itm_ui_locale_date_format_from_config($config);
        if ($fmt === 'iso_yyyymmdd') {
            return $dt->format('Y-m-d');
        }
        if ($fmt === 'us_mmddyyyy') {
            return $dt->format('n/j');
        }
        if ($fmt === 'european_ddmmyyyy') {
            return $dt->format('j/n');
        }
        return $dt->format('j/M');
    }
}

if (!function_exists('itm_ui_locale_format_month_full_label')) {
    /**
     * Full month label for insight cards (defaults to current calendar month).
     */
    function itm_ui_locale_format_month_full_label($month = null, $config = null, $year = null)
    {
        $month = $month === null ? (int) date('n') : (int) $month;
        $year = $year === null ? (int) date('Y') : (int) $year;
        if ($month < 1 || $month > 12) {
            return '';
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-01', $year, $month));
        if (!$dt instanceof DateTimeImmutable) {
            return '';
        }
        $config = is_array($config) ? $config : itm_ui_locale_active_config();
        $fmt = itm_ui_locale_date_format_from_config($config);
        if ($fmt === 'iso_yyyymmdd') {
            return $dt->format('Y-m');
        }
        return $dt->format('F');
    }
}

if (!function_exists('itm_ui_locale_chart_money_format_payload')) {
    /**
     * JSON payload for Chart.js money tick/tooltip formatters.
     *
     * @return array{symbol:string,suffix:bool}
     */
    function itm_ui_locale_chart_money_format_payload($config = null)
    {
        $config = is_array($config) ? $config : itm_ui_locale_active_config();
        $moneyOptions = itm_ui_locale_money_format_options_from_config($config);
        return [
            'symbol' => (string) ($moneyOptions['symbol'] ?? '€'),
            'suffix' => !empty($moneyOptions['suffix']),
        ];
    }
}
