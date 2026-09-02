<?php
if (!function_exists('itm_request_password_approval_secret')) {
    /**
     * HMAC key for Request Password HR/HOD email approval links.
     *
     * Why: Secret must not live in source — set ITM_REQUEST_PASSWORD_APPROVAL_SECRET in project root .env.
     */
    function itm_request_password_approval_secret()
    {
        $env = getenv('ITM_REQUEST_PASSWORD_APPROVAL_SECRET');
        if ($env !== false && $env !== '') {
            return (string) $env;
        }

        return '';
    }
}
