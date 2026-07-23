<?php
/**
 * Employee contact email contract: at least one of work_email or personal_email.
 *
 * Why: UI keeps both fields optional individually; create/edit/import must reject both empty.
 */

if (!function_exists('itm_employee_contact_email_trim')) {
    function itm_employee_contact_email_trim($value)
    {
        return trim((string)$value);
    }
}

if (!function_exists('itm_employee_has_contact_email')) {
    function itm_employee_has_contact_email($workEmail, $personalEmail)
    {
        return itm_employee_contact_email_trim($workEmail) !== ''
            || itm_employee_contact_email_trim($personalEmail) !== '';
    }
}

if (!function_exists('itm_employee_contact_email_validation_error')) {
    function itm_employee_contact_email_validation_error()
    {
        return 'At least one of Work Email or Personal Email is required.';
    }
}

if (!function_exists('itm_employee_validate_contact_email_or_error')) {
    /**
     * @return string|null Error message when both emails are empty; null when valid.
     */
    function itm_employee_validate_contact_email_or_error($workEmail, $personalEmail)
    {
        if (itm_employee_has_contact_email($workEmail, $personalEmail)) {
            return null;
        }

        return itm_employee_contact_email_validation_error();
    }
}

if (!function_exists('itm_employee_contact_email_from_sql_value')) {
    /**
     * Decode import rowValues fragments ('NULL' or quoted SQL literals) to plain text.
     */
    function itm_employee_contact_email_from_sql_value($sqlFragment)
    {
        $sqlFragment = trim((string)$sqlFragment);
        if ($sqlFragment === '' || strcasecmp($sqlFragment, 'NULL') === 0) {
            return '';
        }

        if (strlen($sqlFragment) >= 2 && $sqlFragment[0] === "'" && substr($sqlFragment, -1) === "'") {
            $inner = substr($sqlFragment, 1, -1);
            return str_replace(["\\'", '\\\\'], ["'", '\\'], $inner);
        }

        return $sqlFragment;
    }
}

if (!function_exists('itm_employee_resolve_contact_emails_after_merge')) {
    /**
     * @param array<string,mixed> $incoming
     * @param array<string,mixed>|null $existing
     * @param string[] $providedFields
     * @return array{0:string,1:string}
     */
    function itm_employee_resolve_contact_emails_after_merge(array $incoming, $existing, array $providedFields)
    {
        $existing = is_array($existing) ? $existing : [];
        $work = itm_employee_contact_email_trim($incoming['work_email'] ?? '');
        $personal = itm_employee_contact_email_trim($incoming['personal_email'] ?? '');

        if (!in_array('work_email', $providedFields, true)) {
            $work = itm_employee_contact_email_trim($existing['work_email'] ?? '');
        }
        if (!in_array('personal_email', $providedFields, true)) {
            $personal = itm_employee_contact_email_trim($existing['personal_email'] ?? '');
        }

        return [$work, $personal];
    }
}
