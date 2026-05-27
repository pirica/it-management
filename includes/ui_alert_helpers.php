<?php
/**
 * Shared alert rendering for CRUD validation and database errors.
 *
 * Why: Modules duplicated alert-error markup; centralizing keeps light/dark
 * styling consistent and applies human-friendly DB message formatting.
 */

if (!function_exists('itm_error_message_looks_like_raw_mysql')) {
    function itm_error_message_looks_like_raw_mysql($message) {
        $text = trim((string)$message);
        if ($text === '') {
            return false;
        }

        if (stripos($text, 'Database error:') === 0 || stripos($text, 'DB error:') === 0) {
            return true;
        }

        $needles = [
            "cannot be null",
            "doesn't have a default value",
            'Data too long for column',
            'Out of range value for column',
            'Incorrect datetime value',
            'Incorrect date value',
            'foreign key constraint fails',
            'Duplicate entry',
        ];

        foreach ($needles as $needle) {
            if (stripos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('itm_infer_mysql_error_code_from_message')) {
    /**
     * Map raw mysqli text to errno branches used by itm_format_db_constraint_error().
     *
     * Why: Callers often pass mysqli_error() without errno; duplicate/FK guidance lives in the 1062/1451/1452 switch.
     */
    function itm_infer_mysql_error_code_from_message($message) {
        $text = trim((string)$message);
        if ($text === '') {
            return 0;
        }

        if (stripos($text, 'Duplicate entry') !== false) {
            return 1062;
        }

        if (stripos($text, 'foreign key constraint fails') !== false) {
            if (preg_match('/Cannot delete or update a parent row/i', $text)) {
                return 1451;
            }
            if (preg_match('/Cannot add or update a child row/i', $text)) {
                return 1452;
            }

            return 1451;
        }

        return 0;
    }
}

if (!function_exists('itm_normalize_user_error_message')) {
    function itm_normalize_user_error_message($message) {
        $text = trim((string)$message);
        if ($text === '') {
            return '';
        }

        if (preg_match('/^(?:Database error|DB error):?\s*(.+)$/is', $text, $prefixMatch)) {
            $text = trim((string)($prefixMatch[1] ?? ''));
        }

        if (function_exists('itm_error_message_looks_like_raw_mysql') && itm_error_message_looks_like_raw_mysql($text)) {
            if (function_exists('itm_format_db_constraint_error')) {
                $errorCode = function_exists('itm_infer_mysql_error_code_from_message')
                    ? itm_infer_mysql_error_code_from_message($text)
                    : 0;

                return itm_format_db_constraint_error($errorCode, $text);
            }
        }

        return $text;
    }
}

if (!function_exists('itm_render_alert_errors')) {
    /**
     * @param array|string $errors
     */
    function itm_render_alert_errors($errors, $type = 'error') {
        if (is_string($errors)) {
            $errors = trim($errors) === '' ? [] : [$errors];
        }
        if (!is_array($errors)) {
            return '';
        }

        $normalized = [];
        foreach ($errors as $error) {
            $text = itm_normalize_user_error_message($error);
            if ($text !== '') {
                $normalized[] = $text;
            }
        }

        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return '';
        }

        $typeClass = 'itm-alert-error';
        if ($type === 'success') {
            $typeClass = 'itm-alert-success';
        } elseif ($type === 'info') {
            $typeClass = 'itm-alert-info';
        } elseif ($type === 'warning') {
            $typeClass = 'itm-alert-warning';
        }

        $icon = '⚠';
        if ($type === 'success') {
            $icon = '✓';
        } elseif ($type === 'info') {
            $icon = 'ℹ';
        }

        $html = '<div class="itm-alert ' . sanitize($typeClass) . ' alert alert-' . sanitize($type === 'error' ? 'error' : $type) . '" role="alert">';
        $html .= '<span class="itm-alert-icon" aria-hidden="true">' . sanitize($icon) . '</span>';
        $html .= '<div class="itm-alert-body">';

        if (count($normalized) === 1) {
            $html .= '<p class="itm-alert-message">' . sanitize($normalized[0]) . '</p>';
        } else {
            $html .= '<ul class="itm-alert-list">';
            foreach ($normalized as $message) {
                $html .= '<li>' . sanitize($message) . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div></div>';

        return $html;
    }
}

if (!function_exists('itm_humanize_api_error_message')) {
    function itm_humanize_api_error_message($message) {
        return itm_normalize_user_error_message($message);
    }
}
