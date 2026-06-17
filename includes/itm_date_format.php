<?php
/**
 * UK (en-GB) date display and parsing contract for ITM.
 *
 * Storage remains MySQL DATE/DATETIME in Y-m-d / Y-m-d H:i:s.
 * UI lists, views, and imports accept and show dd/mm/yyyy.
 */

if (!function_exists('itm_is_date_field_name')) {
    /**
     * Heuristic: column names that store calendar dates without time semantics.
     */
    function itm_is_date_field_name($fieldName)
    {
        $field = strtolower(trim((string)$fieldName));
        if ($field === '') {
            return false;
        }
        if ($field === 'birthday') {
            return true;
        }
        if (substr($field, -5) === '_date') {
            return true;
        }
        return in_array($field, [
            'date_time_in',
            'date_time_out',
            'log_date',
            'report_date',
            'due_date',
            'certificate_expiry',
            'warranty_expiry',
            'purchase_date',
            'expiry_date',
        ], true);
    }
}

if (!function_exists('itm_is_datetime_field_name')) {
    function itm_is_datetime_field_name($fieldName)
    {
        $field = strtolower(trim((string)$fieldName));
        if ($field === '') {
            return false;
        }
        if (substr($field, -9) === '_datetime') {
            return true;
        }
        return in_array($field, ['created_at', 'updated_at', 'approved_at', 'end_datetime', 'start_datetime'], true);
    }
}

if (!function_exists('itm_parse_date_input')) {
    /**
     * Parse user/import text to canonical Y-m-d. Prefers dd/mm/yyyy (UK).
     *
     * @return string|null Y-m-d or null when not parseable
     */
    function itm_parse_date_input($rawValue)
    {
        $raw = trim((string)$rawValue);
        if ($raw === '' || $raw === '0000-00-00' || strcasecmp($raw, 'null') === 0 || $raw === '—') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $isoMatch)) {
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
            if ($dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $raw) {
                return $raw;
            }
        }

        if (is_numeric($raw)) {
            $serial = (float)$raw;
            if ($serial >= 20000 && $serial <= 80000) {
                $unix = (int)round(($serial - 25569) * 86400);
                if ($unix > 0) {
                    return gmdate('Y-m-d', $unix);
                }
            }
        }

        $ukFormats = ['d/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'j-n-Y', 'j.n.Y'];
        foreach ($ukFormats as $format) {
            $dt = DateTimeImmutable::createFromFormat('!' . $format, $raw);
            if ($dt instanceof DateTimeImmutable) {
                $errors = DateTimeImmutable::getLastErrors();
                $warn = (int)($errors['warning_count'] ?? 0);
                $err = (int)($errors['error_count'] ?? 0);
                if ($warn === 0 && $err === 0) {
                    return $dt->format('Y-m-d');
                }
            }
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $prefixMatch)) {
            return $prefixMatch[1];
        }

        return null;
    }
}

if (!function_exists('itm_parse_datetime_input')) {
    /**
     * Parse user/import text to canonical Y-m-d H:i:s.
     *
     * @return string|null
     */
    function itm_parse_datetime_input($rawValue)
    {
        $raw = trim((string)$rawValue);
        if ($raw === '' || $raw === '0000-00-00 00:00:00' || strcasecmp($raw, 'null') === 0) {
            return null;
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
        ];
        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat('!' . $format, $raw);
            if ($dt instanceof DateTimeImmutable) {
                $errors = DateTimeImmutable::getLastErrors();
                $warn = (int)($errors['warning_count'] ?? 0);
                $err = (int)($errors['error_count'] ?? 0);
                if ($warn === 0 && $err === 0) {
                    if (strpos($format, 'H') === false) {
                        return $dt->format('Y-m-d') . ' 00:00:00';
                    }
                    return $dt->format('Y-m-d H:i:s');
                }
            }
        }

        $dateOnly = itm_parse_date_input($raw);
        if ($dateOnly !== null) {
            return $dateOnly . ' 00:00:00';
        }

        return null;
    }
}

if (!function_exists('itm_format_date_display')) {
    /**
     * Display a stored or raw date as dd/mm/yyyy.
     */
    function itm_format_date_display($rawValue)
    {
        $canonical = itm_parse_date_input($rawValue);
        if ($canonical === null) {
            $text = trim((string)$rawValue);
            return ($text === '' || $text === '0000-00-00') ? '' : $text;
        }

        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $canonical);
        if (!$dt instanceof DateTimeImmutable) {
            return trim((string)$rawValue);
        }

        return $dt->format('d/m/Y');
    }
}

if (!function_exists('itm_format_datetime_display')) {
    /**
     * Display a stored or raw datetime as dd/mm/yyyy HH:mm.
     */
    function itm_format_datetime_display($rawValue)
    {
        $raw = trim((string)$rawValue);
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return '';
        }

        $canonical = itm_parse_datetime_input($raw);
        if ($canonical === null) {
            return $raw;
        }

        $dt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $canonical);
        if (!$dt instanceof DateTimeImmutable) {
            return $raw;
        }

        return $dt->format('d/m/Y H:i');
    }
}

if (!function_exists('itm_format_cell_scalar_display')) {
    /**
     * Format a list/view scalar for display (dates → dd/mm/yyyy).
     */
    function itm_format_cell_scalar_display($fieldName, $value)
    {
        $text = trim((string)($value ?? ''));
        if ($text === '' || $text === '0000-00-00' || $text === '0000-00-00 00:00:00') {
            return $text;
        }

        if (itm_is_datetime_field_name($fieldName) || preg_match('/\d{2}:\d{2}/', $text)) {
            if (itm_is_date_field_name($fieldName) && !preg_match('/\d{2}:\d{2}/', $text)) {
                return itm_format_date_display($text);
            }
            return itm_format_datetime_display($text);
        }

        if (itm_is_date_field_name($fieldName) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return itm_format_date_display($text);
        }

        return $text;
    }
}

if (!function_exists('itm_normalize_sql_date_literal')) {
    /**
     * Normalize POST/import text before writing DATE/DATETIME columns.
     *
     * @return string|null Canonical SQL fragment value (unquoted) or null when empty/invalid
     */
    function itm_normalize_sql_date_literal($rawValue, $columnType)
    {
        $type = strtolower(trim((string)$columnType));
        if (preg_match('/\bdatetime\b|\btimestamp\b/i', $type)) {
            return itm_parse_datetime_input($rawValue);
        }
        if (preg_match('/\bdate\b/i', $type)) {
            return itm_parse_date_input($rawValue);
        }
        return null;
    }
}

if (!function_exists('itm_sql_date_fragment')) {
    /**
     * Build a quoted SQL date literal or NULL from user/import text (dd/mm/yyyy aware).
     */
    function itm_sql_date_fragment($conn, $rawValue)
    {
        $raw = trim((string)$rawValue);
        if ($raw === '') {
            return 'NULL';
        }
        $parsed = itm_parse_date_input($raw);
        if ($parsed === null) {
            return 'NULL';
        }
        return "'" . mysqli_real_escape_string($conn, $parsed) . "'";
    }
}
