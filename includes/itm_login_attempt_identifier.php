<?php
/**
 * Login and password-reset attempt identifier normalization.
 *
 * Why: Failed auth logging must support rate limiting without persisting secrets
 * when users mistype passwords into the email/username field.
 */

if (!function_exists('itm_login_attempt_identifier_redacted')) {
    /**
     * Stable redacted token for rate limiting without persisting the raw secret.
     */
    function itm_login_attempt_identifier_redacted(string $rawIdentifier): string
    {
        return '[redacted:' . substr(hash('sha256', $rawIdentifier), 0, 16) . ']';
    }
}

if (!function_exists('itm_login_attempt_identifier_looks_like_password')) {
    /**
     * Heuristic for secrets mistyped into the email/username field.
     */
    function itm_login_attempt_identifier_looks_like_password(string $rawIdentifier): bool
    {
        if (preg_match('/[^a-zA-Z0-9_\-\.]/', $rawIdentifier)) {
            return true;
        }

        $hasLower = (bool)preg_match('/[a-z]/', $rawIdentifier);
        $hasUpper = (bool)preg_match('/[A-Z]/', $rawIdentifier);
        $hasDigit = (bool)preg_match('/[0-9]/', $rawIdentifier);

        // Why: Mistyped passwords often mix case and digits; plain usernames rarely do all three at length.
        if ($hasLower && $hasUpper && $hasDigit && strlen($rawIdentifier) >= 8) {
            return true;
        }

        return false;
    }
}

if (!function_exists('itm_normalize_login_attempt_identifier')) {
    /**
     * Returns a safe value for attempts.email storage and rate-limit keys.
     */
    function itm_normalize_login_attempt_identifier($rawIdentifier): ?string
    {
        $rawIdentifier = trim((string)$rawIdentifier);
        if ($rawIdentifier === '') {
            return null;
        }

        if (filter_var($rawIdentifier, FILTER_VALIDATE_EMAIL)) {
            return substr($rawIdentifier, 0, 255);
        }

        // Why: Passwords mistyped into the email field often contain @ but are not valid emails.
        if (strpos($rawIdentifier, '@') !== false) {
            return itm_login_attempt_identifier_redacted($rawIdentifier);
        }

        if (itm_login_attempt_identifier_looks_like_password($rawIdentifier)) {
            return itm_login_attempt_identifier_redacted($rawIdentifier);
        }

        if (preg_match('/^[a-zA-Z0-9_\-\.]{1,64}$/', $rawIdentifier)) {
            return $rawIdentifier;
        }

        return itm_login_attempt_identifier_redacted($rawIdentifier);
    }
}
