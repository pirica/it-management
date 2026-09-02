<?php
/**
 * Central HTTP security headers for browser-facing responses.
 *
 * Why: Legacy UI relies on inline scripts and a few CDN assets; CSP is pragmatic
 * (unsafe-inline + jsdelivr) until modules adopt nonces. HSTS is sent only on HTTPS.
 */

if (!function_exists('itm_request_is_https_from_server')) {
    /**
     * HTTPS detection from a $_SERVER-style array (testable without web SAPI).
     *
     * @param array<string, mixed> $server
     */
    function itm_request_is_https_from_server(array $server): bool
    {
        $httpsFlag = strtolower((string)($server['HTTPS'] ?? ''));
        if ($httpsFlag === 'on' || $httpsFlag === '1') {
            return true;
        }

        if (isset($server['SERVER_PORT']) && (int)$server['SERVER_PORT'] === 443) {
            return true;
        }

        $forwardedProtoRaw = (string)($server['HTTP_X_FORWARDED_PROTO'] ?? '');
        $forwardedProtoParts = array_map('trim', explode(',', strtolower($forwardedProtoRaw)));
        $forwardedProto = $forwardedProtoParts[0] ?? '';
        if ($forwardedProto === 'https') {
            return true;
        }

        $forwardedSsl = strtolower((string)($server['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($forwardedSsl === 'on') {
            return true;
        }

        $requestScheme = strtolower((string)($server['REQUEST_SCHEME'] ?? ''));
        return $requestScheme === 'https';
    }
}

if (!function_exists('itm_request_is_https')) {
    /**
     * Whether the active web request is served over TLS (direct or trusted proxy hint).
     */
    function itm_request_is_https(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        return itm_request_is_https_from_server($_SERVER);
    }
}

if (!function_exists('itm_session_cookie_secure_from_config')) {
    /**
     * Resolve Secure cookie flag from env + request TLS (testable without web SAPI).
     *
     * @param string|null $forceEnv Raw ITM_SESSION_COOKIE_SECURE value, or null to skip override.
     */
    function itm_session_cookie_secure_from_config(?string $appUrl, ?string $forceEnv, bool $requestIsHttps): bool
    {
        if ($forceEnv !== null && $forceEnv !== '') {
            return filter_var($forceEnv, FILTER_VALIDATE_BOOLEAN);
        }

        $appUrl = trim((string)$appUrl);
        if ($appUrl !== '') {
            $parsed = parse_url($appUrl);
            if (is_array($parsed) && isset($parsed['scheme'])
                && strtolower((string)$parsed['scheme']) === 'https') {
                return true;
            }
        }

        return $requestIsHttps;
    }
}

if (!function_exists('itm_session_cookie_secure')) {
    /**
     * Whether session/CSRF cookies should set the Secure attribute.
     *
     * Why: Production installs set ITM_APP_URL to https://… so cookies stay Secure even when
     * a reverse proxy omits HTTPS / X-Forwarded-Proto on the PHP hop (ITM-PENTEST-014).
     */
    function itm_session_cookie_secure(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $forceEnv = getenv('ITM_SESSION_COOKIE_SECURE');
        $force = ($forceEnv !== false && $forceEnv !== '') ? (string)$forceEnv : null;

        return itm_session_cookie_secure_from_config(
            (string)getenv('ITM_APP_URL'),
            $force,
            itm_request_is_https()
        );
    }
}

if (!function_exists('itm_build_content_security_policy')) {
    /**
     * CSP compatible with inline module UI and known CDN script/style hosts.
     */
    function itm_build_content_security_policy(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://cdn.jsdelivr.net",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return implode('; ', $directives);
    }
}

if (!function_exists('itm_send_security_headers')) {
    /**
     * Emit standard browser hardening headers once per request (no-op under CLI).
     */
    function itm_send_security_headers(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        static $sent = false;
        if ($sent) {
            return;
        }
        $sent = true;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: ' . itm_build_content_security_policy());

        if (itm_request_is_https()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}
